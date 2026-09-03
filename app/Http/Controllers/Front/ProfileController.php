<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\UserInfo;
use App\Models\UserAuth;
use App\Models\UserLogin;
use App\Models\WithdrawRecord;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use App\Services\UserPasswordService;
use App\Services\JwtService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * 前台用户资料控制器。
 *
 * 文件功能：
 * - 处理资料读取、资料更新、密码修改、邮箱修改、头像上传、实名认证、银行卡认证、银行卡换绑、销户校验、关系链查询和旧前台资料接口兼容。
 * - 新版 Layui/Naive 页面通过 `/api/front/profile*` 资源风格接口读取和保存资料。
 * - 旧前台仍通过 `user/editpsw_save`、`user/center/cancelVerifyInfo`、`user/relationShipHtml` 等路由进入，本控制器统一兼容旧字段名与旧响应结构。
 * - 涉及文件上传的接口统一保存到 public disk，并通过 mirrorPublicDiskFile 同步公开目录，避免未建立 storage 软链时图片无法访问。
 *
 * 安全边界：
 * - 密码校验：本地哈希与 MT4 网关双重确认，network_failure（网络结果未知）时失败关闭，不执行任何改密或写库。
 * - 验证码用途隔离：updverify（资料修改）、change（换绑）、cancel（销户）使用独立缓存/Session 键，校验成功后才消费，防止跨操作重放。
 * - 身份一致性：verify_phone、verify_email 必须与资料表一致，当前登录用户一律来自 user guard 或旧会话解析，请求体不能覆盖用户身份。
 * - 脱敏值防护：手机号、身份证含 `*` 的展示值直接拒绝写回数据库；敏感字段只以 *_masked 形式返回。
 * - 文件上传只允许 jpeg/png/jpg/gif 并带大小上限，保存目录固定为 auth/{user_id}/ 与 avatars/{user_id}/，文件名由服务端生成。
 */
class ProfileController extends FrontBaseController
{
    /**
     * 密码服务：资料控制器内所有改密动作（旧 editpsw_save 与新版接口）都经它完成
     * “本地哈希 + MT4 网关”双侧校验与写入；network_failure 时失败关闭的语义封装在其内部，
     * 绕过它单独写本地库会造成 MT4 交易端密码与本地不一致。
     *
     * @var UserPasswordService
     */
    private $passwordService;

    /**
     * JWT 服务：修改密码、邮箱换绑、注销等敏感操作后需要作废当前令牌（写入黑名单）并同步 SSO 缓存；
     * 缺失时敏感变更后旧 token 仍然有效，构成会话残留风险。
     *
     * @var JwtService
     */
    private $jwtService;

    public function __construct(UserPasswordService $passwordService, JwtService $jwtService)
    {
        $this->passwordService = $passwordService;
        $this->jwtService = $jwtService;
    }

    /**
     * profileInfo 用于返回当前前台用户资料。
     *
     * 返回字段含义：
     * - login 表示 user_logins 登录表资料，包含登录账号、邮箱、账号类型和启停状态。
     * - info 表示 user_infos 业务资料，包含姓名、电话、头像、资金属性和账户类型等页面展示字段。
     * - auth 表示 user_auths 认证资料，包含身份证、银行卡、审核状态、图片地址和脱敏字段。
     * - avatar_url 表示浏览器可直接访问的头像地址，前端无需再判断相对路径、storage 路径或外链。
     *
     * @param Request $request HTTP 请求对象，承载当前 front user token 或旧前台会话。
     * @return JsonResponse 当前用户资料、业务资料和认证资料响应。
     */
    public function profileInfo(Request $request): JsonResponse
    {
        [$userLogin, $userInfo] = $this->currentProfileContext($request);
        if (!$userLogin || !$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $userAuth = UserAuth::where('user_id', $userInfo->user_id)->first();

        // profileInfo 同时服务顶部栏、资料页和编辑页；这里把登录表、资料表、认证表拆开返回，
        // 并额外补 avatar_url，前端无需猜测头像是相对路径、storage 路径还是空值。
        $phone = (string) $userInfo->phone;
        $email = (string) $userLogin->email;
        $idCardNo = (string) ($userAuth->id_card_no ?? $userAuth->id_card ?? '');

        $info = $userInfo->toArray();
        $info['phone'] = $phone;
        $info['phone_masked'] = $this->maskPhone($phone);
        $info['email'] = $email;
        $info['email_masked'] = $this->maskEmail($email);
        $info['avatar_url'] = $this->resolveAvatarUrl($userInfo->avatar);
        $info['id_card_no'] = $idCardNo;
        $info['id_card_no_masked'] = $this->maskIdCard($idCardNo);

        $authPayload = $userAuth ? $userAuth->toArray() : [];
        if ($authPayload) {
            $authPayload['id_card_no'] = $idCardNo;
            $authPayload['id_card_no_masked'] = $info['id_card_no_masked'];
            $authPayload['id_card_status_text'] = $this->idCardStatusText((int) ($userAuth->id_card_status ?? 0));
            $authPayload['bank_status_text'] = $this->bankStatusText((int) ($userAuth->bank_status ?? 0));
            $authPayload['bank_no_masked'] = $this->maskBankNo((string) ($userAuth->bank_no ?? ''));
            $authPayload['bank_no_tmp_masked'] = $this->maskBankNo((string) ($userAuth->bank_no_tmp ?? ''));
            $authPayload['id_card_front_url'] = $this->resolveFileUrl($userAuth->id_card_front ?? '');
            $authPayload['id_card_back_url'] = $this->resolveFileUrl($userAuth->id_card_back ?? '');
            $authPayload['bank_card_img_url'] = $this->resolveFileUrl($userAuth->bank_card_img ?? '');
            $authPayload['bank_card_back_img_url'] = $this->resolveFileUrl($userAuth->bank_card_back_img ?? '');
            $authPayload['bank_card_img_tmp_url'] = $this->resolveFileUrl($userAuth->bank_card_img_tmp ?? '');
            $authPayload['bank_card_back_img_tmp_url'] = $this->resolveFileUrl($userAuth->bank_card_back_img_tmp ?? '');
        }

        $data = [
            'login' => array_merge($userLogin->only(['id', 'user_id', 'account_type', 'is_enabled', 'last_login_at']), [
                'email' => $email,
                'email_masked' => $info['email_masked'],
            ]),
            'info'  => $info,
            'auth'  => $authPayload ?: null,
        ];

        return $this->success($data, __('response.query_success'));
    }

    /**
     * updateProfile 用于更新当前前台用户基础资料。
     *
     * 参数含义：
     * - user_name 表示用户姓名，对应 user_infos.user_name。
     * - phone 表示联系电话，若传入带 `*` 的脱敏值则拒绝保存，避免把展示值写回数据库。
     * - id_card_no 表示身份证号，会写入或更新 user_auths.id_card_no。
     * - gender 表示性别，1=男，2=女。
     * - address 表示联系地址，对应 user_infos.address。
     *
     * @param Request $request HTTP 请求对象，承载基础资料表单。
     * @return JsonResponse 更新结果响应。
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_name' => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'id_card_no'=> 'nullable|string|max:50',
            'gender'    => 'nullable|in:1,2',
            'address'   => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if ($userInfo) {
            $payload = $request->only([
                'user_name', 'gender', 'address',
            ]);

            // 手机号展示值会带 `*` 脱敏；检测到脱敏值必须拒绝保存，防止把页面展示值写回数据库。
            if ($request->filled('phone')) {
                $phone = trim((string) $request->input('phone'));
                if (strpos($phone, '*') !== false) {
                    return $this->error(__('profile.phone_masked_invalid'), ResponseCode::VALIDATION_FAILED);
                }
                $payload['phone'] = $phone;
            }

            if (isset($payload['gender'])) {
                $payload['gender'] = (int) $payload['gender'];
            }

            $userInfo->update($payload);

            // 身份证号同样拒绝脱敏值；写入前按 user_id 建卡，保证每个用户只有一条认证记录。
            if ($request->filled('id_card_no')) {
                $idCardNo = trim((string) $request->input('id_card_no'));
                if (strpos($idCardNo, '*') !== false) {
                    return $this->error(__('profile.id_card_masked_invalid'), ResponseCode::VALIDATION_FAILED);
                }

                UserAuth::updateOrCreate(
                    ['user_id' => $userInfo->user_id],
                    ['id_card_no' => $idCardNo]
                );
            }

            return $this->success([], 'response.updated', ResponseCode::UPDATED);
        }

        return $this->error(__('auth.user_info_not_found'), ResponseCode::INTERNAL_ERROR);
    }

    /**
     * changePassword 用于修改当前前台用户登录密码。
     *
     * 参数含义：
     * - old_password 表示当前旧密码，先校验本地哈希，再由 MT4 网关确认远端密码状态。
     * - password 表示新密码，至少 6 位并按 Laravel confirmed 规则要求确认值一致。
     * - password_confirmation 表示新密码确认值，只参与校验，不写入数据库。
     * - MT4 网络结果未知时返回 THIRD_PARTY_ERROR，并停止密码重置和本地写库。
     *
     * @param Request $request HTTP 请求对象，承载旧密码、新密码和确认密码。
     * @return JsonResponse 密码修改结果响应。
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        /** @var UserLogin $user */
        $user = $request->user('user');

        // 第一道：本地哈希校验旧密码，不匹配直接拒绝，避免把无效凭据发给 MT4 网关。
        $oldPassword = (string) $request->input('old_password');
        if (!$this->passwordService->verifyLocal($user, $oldPassword)) {
            return $this->error('auth.old_password_error', ResponseCode::OLD_PASSWORD_WRONG);
        }

        // 第二道：MT4 网关确认远端密码状态；网络结果未知时失败关闭，禁止继续重置密码。
        $passwordState = $this->passwordService->verify($user, $oldPassword);
        if ($passwordState === 'network_failure') {
            return $this->error('', ResponseCode::THIRD_PARTY_ERROR, [
                'error' => 'NETWORKFAIL',
                'field' => 'FATALCANOTCONNECT',
            ]);
        }
        if ($passwordState !== 'verified') {
            return $this->error('auth.old_password_error', ResponseCode::OLD_PASSWORD_WRONG, [
                'error' => 'apipswerr',
                'field' => 'old_password',
            ]);
        }

        // 第三道：MT4 改密成功后本地才同步；本地或远端任一侧失败都返回失败，不产生只改一半的状态。
        if (!$this->passwordService->change($user, (string) $request->password)) {
            return $this->error('response.mt4_sync_failed', ResponseCode::MT4_SYNC_FAILED);
        }

        // 改密完成后注销当前会话并作废旧 JWT，强制用户用新密码重新登录。
        $this->invalidatePasswordChangeSession($request);

        return $this->success([], 'auth.password_changed', ResponseCode::UPDATED);
    }

    /**
     * user_editpsw_save 用于兼容旧前台修改密码入口。
     *
     * 参数含义：
     * - olduserpsw 表示旧前台提交的旧密码，兼容新版 old_password。
     * - newuserpsw 表示旧前台提交的新密码，兼容新版 password。
     * - confirmuserpsw 表示旧前台提交的新密码确认值，兼容新版 password_confirmation。
     * - localpswerr 表示本地哈希不匹配，apipswerr 表示 MT4 明确拒绝，FATALCANOTCONNECT 表示网络结果未知。
     *
     * @param Request $request HTTP 请求对象，承载新旧字段名的密码表单。
     * @return JsonResponse 旧前台兼容响应，包含 msg、err、col。
     */
    public function user_editpsw_save(Request $request): JsonResponse
    {
        $oldPassword = (string) $request->input('old_password', $request->input('olduserpsw', ''));
        $newPassword = (string) $request->input('password', $request->input('newuserpsw', ''));
        $confirmPassword = (string) $request->input('password_confirmation', $request->input('confirmuserpsw', ''));

        if ($oldPassword === '') {
            return $this->legacyFail('olduserpsw', 'olduserpsw');
        }
        if ($newPassword === '' || strlen($newPassword) < 6) {
            return $this->legacyFail('newuserpsw', 'newuserpsw');
        }
        if ($newPassword !== $confirmPassword) {
            return $this->legacyFail('confirmuserpsw', 'confirmuserpsw');
        }

        /** @var UserLogin|null $user */
        $user = $this->legacyFrontUserLogin($request);
        if (!$user || !$this->passwordService->verifyLocal($user, $oldPassword)) {
            return $this->legacyFail('localpswerr', 'olduserpsw');
        }

        $passwordState = $this->passwordService->verify($user, $oldPassword);
        if ($passwordState === 'network_failure') {
            return $this->legacyFail('FATALCANOTCONNECT', 'nocol');
        }
        if ($passwordState !== 'verified') {
            return $this->legacyFail('apipswerr', 'olduserpsw');
        }

        if (!$this->passwordService->change($user, $newPassword)) {
            return $this->legacyFail('FATALCANOTCONNECT', 'nocol');
        }

        $this->invalidatePasswordChangeSession($request);

        return $this->legacySuccess('SUCCESS');
    }

    /**
     * 改密成功后清理全部旧登录态。
     *
     * 同时作废旧 JWT、登出 user guard 并清空旧 session 登录标记，防止旧令牌在密码变更后继续有效。
     *
     * @param Request $request 当前 HTTP 请求对象，从中读取 jwt_token 与 session。
     * @return void
     */
    private function invalidatePasswordChangeSession(Request $request): void
    {
        $token = $request->attributes->get('jwt_token');
        if (is_string($token) && $token !== '') {
            $this->jwtService->invalidateToken($token);
        }

        Auth::guard('user')->logout();
        if ($request->hasSession()) {
            $request->session()->forget('suser');
        }
    }

    /**
     * changeEmail 用于修改当前前台登录邮箱。
     *
     * 参数含义：
     * - verify_phone 表示用于校验身份的手机号，必须与 user_infos.phone 一致。
     * - current_email 表示当前邮箱，必须与 user_logins.email 一致。
     * - new_email 表示新邮箱，必须符合邮箱格式且在 user_logins.email 中唯一。
     * - password 表示当前密码，本地模式校验哈希，MT4 模式通过交易密码网关校验。
     * - verification_code 表示发往 new_email 的一次性验证码，成功后立即消费。
     *
     * @param Request $request HTTP 请求对象，承载手机号、当前邮箱和新邮箱。
     * @return JsonResponse 邮箱修改结果响应。
     */
    public function changeEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verify_phone' => 'required|string',
            'current_email' => 'required|email',
            'new_email' => 'required|email|unique:user_logins,email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        /** @var UserLogin|null $userLogin */
        $userLogin = $request->user('user');
        $userInfo = $userLogin ? $userLogin->userInfo : null;

        if (!$userLogin || !$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }
        // 一致性校验阶段：手机号与邮箱都必须与资料表一致，任一不匹配即失败关闭，防止盗用会话修改他人资料。
        if (!$this->phoneMatches((string) $request->input('verify_phone'), (string) $userInfo->phone)
            || strtolower(trim((string) $request->input('current_email'))) !== strtolower((string) $userLogin->email)) {
            return $this->error(__('profile.email_verify_failed'), ResponseCode::VALIDATION_FAILED, [
                'error' => 'emailErr',
                'field' => 'current_email',
            ]);
        }

        // 密码确认阶段：MT4 结果未知时同样失败关闭，不能继续走验证码环节。
        $passwordState = $this->passwordService->verify($userLogin, (string) $request->input('password', ''));
        if ($passwordState !== 'verified') {
            return $this->sensitivePasswordError($passwordState, 'pswErr');
        }

        // 验证码核验阶段：code、目标邮箱、用途类型（updverify + email）全部匹配才放行。
        $newEmail = strtolower(trim((string) $request->input('new_email')));
        $verification = $this->profileVerification($request, 'updverify', (int) $userInfo->user_id);
        $submittedCode = trim((string) $request->input('verification_code', ''));
        if ($submittedCode === '' || $submittedCode !== $verification['code']) {
            return $this->error('', ResponseCode::VALIDATION_FAILED, [
                'error' => 'codeErr',
                'field' => 'verification_code',
            ]);
        }
        if (
            $verification['email'] === ''
            || strtolower($verification['email']) !== $newEmail
            || ($verification['type'] !== '' && $verification['type'] !== 'email')
        ) {
            return $this->error('', ResponseCode::VALIDATION_FAILED, [
                'error' => 'emailErr',
                'field' => 'new_email',
            ]);
        }

        // 写库阶段：改邮箱成功后立即消费验证码，同一验证码不能用于下一次修改。
        $userLogin->update(['email' => $newEmail]);
        $this->forgetProfileVerification($request, 'updverify', (int) $userInfo->user_id);

        return $this->success([], __('response.updated'));
    }

    /**
     * uploadAvatar 用于上传当前前台用户头像。
     *
     * 参数含义：
     * - avatar 表示新版头像上传文件字段，允许 jpeg、png、jpg、gif，最大 2048KB。
     *
     * 逻辑说明：
     * - 上传新头像前会删除旧头像文件和公开目录镜像，避免无用图片长期残留。
     * - 响应同时返回 avatar 存储路径和 url 浏览器访问地址。
     *
     * @param Request $request HTTP 请求对象，承载头像文件。
     * @return JsonResponse 上传成功返回头像路径和访问地址。
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $userLogin = $request->user('user');
        $userInfo = $userLogin ? $userLogin->userInfo : null;

        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::USER_NOT_FOUND);
        }

        if ($request->hasFile('avatar')) {
            // 上传新头像前清理旧头像，避免 public disk 和公开镜像目录残留失效文件。
            if ($userInfo->avatar && Storage::disk('public')->exists($userInfo->avatar)) {
                Storage::disk('public')->delete($userInfo->avatar);
            }
            $this->deletePublicMirror($userInfo->avatar);

            $path = $request->file('avatar')->store('avatars/' . $userInfo->user_id, 'public');
            $this->mirrorPublicDiskFile($path);
            $userInfo->update(['avatar' => $path]);

            return $this->success([
                'url' => $this->resolveAvatarUrl($path),
                'avatar' => $path,
            ], 'response.uploaded', ResponseCode::UPLOADED);
        }

        return $this->error('response.file_upload_failed', ResponseCode::FILE_UPLOAD_FAILED);
    }

    /**
     * submitIdentity 用于提交实名认证资料。
     *
     * 参数含义：
     * - id_card_no 表示身份证号，对应 user_auths.id_card_no。
     * - id_card_front 表示身份证正面图片，保存到 auth/{user_id}/identity。
     * - id_card_back 表示身份证反面图片，保存到 auth/{user_id}/identity。
     * - id_card_status=1 表示实名认证待审核，后台审核后会改为通过或拒绝。
     *
     * @param Request $request HTTP 请求对象，承载身份证号与身份证正反面图片。
     * @return JsonResponse 实名认证提交结果响应。
     */
    public function submitIdentity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_card_no' => 'required|string|max:50',
            'id_card_front' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
            'id_card_back' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $userInfo = $request->user('user')->userInfo;
        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $frontPath = $this->storeProfileFile($request, 'id_card_front', 'auth/' . $userInfo->user_id . '/identity');
        $backPath = $this->storeProfileFile($request, 'id_card_back', 'auth/' . $userInfo->user_id . '/identity');

        UserAuth::updateOrCreate(
            ['user_id' => $userInfo->user_id],
            [
                'id_card_no' => trim((string) $request->input('id_card_no')),
                'id_card_front' => $frontPath,
                'id_card_back' => $backPath,
                'id_card_status' => 1,
                'id_card_remarks' => '',
            ]
        );

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * uploadIdCard 用于兼容旧前台实名认证上传入口。
     *
     * 参数含义：
     * - username 表示旧前台提交的真实姓名，兼容新版 user_name。
     * - userIdcardNo 表示旧前台提交的身份证号，兼容新版 id_card_no。
     * - Idphoto1/file_img1 表示旧前台身份证正面图片字段。
     * - Idphoto2/file_img2 表示旧前台身份证反面图片字段。
     *
     * @param Request $request HTTP 请求对象，承载旧前台实名认证字段。
     * @return JsonResponse 旧前台兼容响应，包含 msg、err、col。
     */
    public function uploadIdCard(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        $userInfo = $userLogin ? $userLogin->userInfo : null;
        if (!$userInfo) {
            return $this->legacyFail('userNotFound', 'userId');
        }

        $realName = trim((string) $request->input('user_name', $request->input('username', $userInfo->user_name)));
        $idCardNo = trim((string) $request->input('id_card_no', $request->input('userIdcardNo', '')));
        $frontField = $this->firstUploadedField($request, ['id_card_front', 'Idphoto1', 'file_img1']);
        $backField = $this->firstUploadedField($request, ['id_card_back', 'Idphoto2', 'file_img2']);

        if ($realName === '') {
            return $this->legacyFail('username', 'username');
        }
        if ($idCardNo === '') {
            return $this->legacyFail('userIdcardNo', 'userIdcardNo');
        }
        if (UserAuth::where('id_card_no', $idCardNo)->where('user_id', '!=', $userInfo->user_id)->exists()) {
            // 身份证号全局唯一：已被其他账号占用时拒绝，防止冒用他人证件完成实名。
            return $this->legacyFail('IdcardNoExiste', 'userIdcardNo');
        }
        if (!$frontField) {
            return $this->legacyFail('POSERRORFORMAT1', 'Idphoto1');
        }
        if (!$backField) {
            return $this->legacyFail('POSERRORFORMAT2', 'Idphoto2');
        }

        $validator = Validator::make($request->all(), [
            $frontField => 'image|mimes:jpeg,png,jpg,gif|max:10240',
            $backField => 'image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);
        if ($validator->fails()) {
            return $this->legacyFail('POSERRORFORMAT1', $frontField);
        }

        $frontPath = $this->storeProfileFile($request, $frontField, 'auth/' . $userInfo->user_id . '/identity');
        $backPath = $this->storeProfileFile($request, $backField, 'auth/' . $userInfo->user_id . '/identity');

        $userInfo->update(['user_name' => $realName]);
        $authPayload = [
            'id_card_no' => $idCardNo,
            'id_card_front' => $frontPath,
            'id_card_back' => $backPath,
            'id_card_status' => 1,
            'id_card_remarks' => '',
        ];
        if (Schema::hasColumn('user_auths', 'real_name')) {
            $authPayload['real_name'] = $realName;
        }

        UserAuth::updateOrCreate(['user_id' => $userInfo->user_id], $authPayload);

        return $this->legacySuccess('SUC', 'NOTERROR');
    }

    /**
     * submitBankCard 用于提交银行卡认证资料。
     *
     * 参数含义：
     * - bank_name 表示开户银行。
     * - bank_no 表示银行卡号。
     * - bank_addr 表示开户行地址。
     * - bank_card_img 表示银行卡正面图片。
     * - bank_card_back_img 表示银行卡反面图片。
     * - bank_status=1 表示银行卡认证待审核。
     *
     * @param Request $request HTTP 请求对象，承载银行卡资料和图片。
     * @return JsonResponse 银行卡认证提交结果响应。
     */
    public function submitBankCard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'bank_no' => 'required|string|max:50',
            'bank_addr' => 'required|string|max:500',
            'bank_card_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
            'bank_card_back_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $userInfo = $request->user('user')->userInfo;
        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $frontPath = $this->storeProfileFile($request, 'bank_card_img', 'auth/' . $userInfo->user_id . '/bank');
        $backPath = $this->storeProfileFile($request, 'bank_card_back_img', 'auth/' . $userInfo->user_id . '/bank');
        UserAuth::updateOrCreate(
            ['user_id' => $userInfo->user_id],
            [
                'bank_name' => trim((string) $request->input('bank_name')),
                'bank_no' => trim((string) $request->input('bank_no')),
                'bank_addr' => trim((string) $request->input('bank_addr')),
                'bank_card_img' => $frontPath,
                'bank_card_back_img' => $backPath,
                'bank_status' => 1,
                'bank_remarks' => '',
            ]
        );

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * uploadBankCard 用于兼容旧前台银行卡认证上传入口。
     *
     * @param Request $request HTTP 请求对象，承载旧前台银行卡字段。
     * @return JsonResponse 旧前台兼容响应。
     */
    public function uploadBankCard(Request $request): JsonResponse
    {
        return $this->legacyBankCardUpload($request, false);
    }

    /**
     * submitBankChange 用于提交银行卡换绑资料。
     *
     * 参数含义：
     * - verify_phone 表示用于身份校验的当前手机号。
     * - verify_email 表示用于身份校验的当前邮箱。
     * - bank_name_tmp 表示待审核的新开户银行，来自请求 bank_name。
     * - bank_no_tmp 表示待审核的新银行卡号，来自请求 bank_no。
     * - bank_addr_tmp 表示待审核的新开户行地址，来自请求 bank_addr。
     * - bank_status=3 表示银行卡换绑待审核。
     *
     * @param Request $request HTTP 请求对象，承载身份校验字段、新银行卡资料和图片。
     * @return JsonResponse 银行卡换绑提交结果响应。
     */
    public function submitBankChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verify_phone' => 'required|string',
            'verify_email' => 'required|email',
            'bank_name' => 'required|string|max:255',
            'bank_no' => 'required|string|max:50',
            'bank_addr' => 'required|string|max:500',
            'bank_card_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
            'bank_card_back_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        // 身份一致性阶段：手机号与邮箱都必须与资料表一致，作为换绑身份确认。
        [$userLogin, $userInfo] = $this->verifiedContactUser($request);
        if (!$userLogin || !$userInfo) {
            return $this->error(__('profile.email_verify_failed'), ResponseCode::VALIDATION_FAILED);
        }

        // 换绑前置校验阶段：银行卡审核状态、待处理出金、密码、邮箱与一次性验证码（change 用途）全部通过才允许提交。
        $bankChangeError = $this->bankChangeValidationError($request, $userLogin, $userInfo);
        if ($bankChangeError !== null) {
            $responseCode = $bankChangeError['error'] === 'NETWORKFAIL'
                ? ResponseCode::THIRD_PARTY_ERROR
                : ResponseCode::VALIDATION_FAILED;

            return $this->error('', $responseCode, [
                'error' => $bankChangeError['error'],
                'field' => $bankChangeError['field'],
            ]);
        }

        // 文件与写库阶段：新卡资料写入 *_tmp 字段并置 bank_status=3 待审核，成功后才消费 change 验证码。
        $frontPath = $this->storeProfileFile($request, 'bank_card_img', 'auth/' . $userInfo->user_id . '/bank-change');
        $backPath = $this->storeProfileFile($request, 'bank_card_back_img', 'auth/' . $userInfo->user_id . '/bank-change');
        UserAuth::updateOrCreate(
            ['user_id' => $userInfo->user_id],
            [
                'bank_name_tmp' => trim((string) $request->input('bank_name')),
                'bank_no_tmp' => trim((string) $request->input('bank_no')),
                'bank_addr_tmp' => trim((string) $request->input('bank_addr')),
                'bank_card_img_tmp' => $frontPath,
                'bank_card_back_img_tmp' => $backPath,
                'bank_status' => 3,
                'bank_remarks' => '',
            ]
        );

        $this->forgetProfileVerification($request, 'change', (int) $userInfo->user_id);

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * uploadChangeBankCard 用于兼容旧前台银行卡换绑上传入口。
     *
     * @param Request $request HTTP 请求对象，承载旧前台银行卡换绑字段。
     * @return JsonResponse 旧前台兼容响应。
     */
    public function uploadChangeBankCard(Request $request): JsonResponse
    {
        return $this->legacyBankCardUpload($request, true);
    }

    /**
     * changePhone 用于修改当前前台用户联系电话。
     *
     * 参数含义：
     * - verify_phone 表示当前手机号校验值。
     * - verify_email 表示当前邮箱校验值。
     * - new_phone 表示新联系电话。
     * - password 表示当前密码，验证失败时不写入手机号。
     *
     * @param Request $request HTTP 请求对象，承载联系方式换绑字段。
     * @return JsonResponse 手机号修改结果响应。
     */
    public function changePhone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verify_phone' => 'required|string',
            'verify_email' => 'required|email',
            'new_phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        // 身份与密码阶段：联系方式必须与资料一致，密码 MT4 结果未知时失败关闭，不写手机号。
        [$userLogin, $userInfo] = $this->verifiedContactUser($request);
        if (!$userLogin || !$userInfo) {
            return $this->error(__('profile.email_verify_failed'), ResponseCode::VALIDATION_FAILED, [
                'error' => 'phoneErr',
                'field' => 'verify_phone',
            ]);
        }

        $passwordState = $this->passwordService->verify($userLogin, (string) $request->input('password', ''));
        if ($passwordState !== 'verified') {
            return $this->sensitivePasswordError($passwordState, 'pswErr');
        }

        // 写库阶段：只有新手机号入参，旧手机号校验值不落库。
        $userInfo->update(['phone' => trim((string) $request->input('new_phone'))]);

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * uploadHeadImg 用于兼容旧前台头像上传入口。
     *
     * 参数含义：
     * - headimg/file_img/file 表示旧前台可能提交的头像文件字段别名。
     *
     * @param Request $request HTTP 请求对象，承载旧前台头像文件。
     * @return JsonResponse 旧前台兼容响应。
     */
    public function uploadHeadImg(Request $request): JsonResponse
    {
        $field = $this->firstUploadedField($request, ['avatar', 'headimg', 'file_img', 'file']);
        if (!$field) {
            return $this->legacyFail('uploadErr', 'headimg');
        }

        $validator = Validator::make($request->all(), [
            $field => 'image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);
        if ($validator->fails()) {
            return $this->legacyFail('POSERRORFORMAT', $field);
        }

        $userLogin = $this->legacyFrontUserLogin($request);
        $userInfo = $userLogin ? $userLogin->userInfo : null;
        if (!$userInfo) {
            return $this->legacyFail('userNotFound', 'userId');
        }

        if ($userInfo->avatar && Storage::disk('public')->exists($userInfo->avatar)) {
            Storage::disk('public')->delete($userInfo->avatar);
        }
        $this->deletePublicMirror($userInfo->avatar);

        $path = $request->file($field)->store('avatars/' . $userInfo->user_id, 'public');
        $this->mirrorPublicDiskFile($path);
        $userInfo->update(['avatar' => $path]);

        return $this->legacySuccess('SUC', 'NOTERROR');
    }

    /**
     * updatePhoneEmailInfo 用于兼容旧前台手机号或邮箱更新入口。
     *
     * 参数含义：
     * - type 表示更新类型，email=修改邮箱，phone=修改手机号。
     * - password 表示必填的当前登录密码；MT4 网络未知时返回 NETWORKFAIL，不能继续写库。
     * - oldemail/current_email 表示当前邮箱。
     * - useremail/new_email 表示新邮箱。
     * - oldphonefill/verify_phone 表示当前手机号。
     * - userphoneNo/newuserphoneNo/new_phone 表示新手机号。
     *
     * @param Request $request HTTP 请求对象，承载旧前台联系方式更新字段。
     * @return JsonResponse 旧前台兼容响应。
     */
    public function updatePhoneEmailInfo(Request $request): JsonResponse
    {
        $type = (string) $request->input('type', '');
        $password = (string) $request->input('password', '');

        /** @var UserLogin $userLogin */
        $userLogin = $this->legacyFrontUserLogin($request);
        $userInfo = $userLogin ? $userLogin->userInfo : null;
        if (!$userLogin || !$userInfo) {
            return $this->contactInfoFail(
                $request,
                'userNotFound',
                'userId',
                ResponseCode::USER_NOT_FOUND
            );
        }
        if (!in_array($type, ['email', 'phone'], true)) {
            return $this->contactInfoFail($request, 'typeErr', 'type');
        }
        $passwordState = $this->passwordService->verify($userLogin, $password);
        if ($passwordState === 'network_failure') {
            return $this->contactInfoFail(
                $request,
                'NETWORKFAIL',
                'FATALCANOTCONNECT',
                ResponseCode::THIRD_PARTY_ERROR
            );
        }
        if ($passwordState !== 'verified') {
            return $this->contactInfoFail(
                $request,
                'pswErr',
                'password',
                ResponseCode::OLD_PASSWORD_WRONG
            );
        }

        if ($type === 'email') {
            // 邮箱分支：核对当前邮箱、验证码（updverify 用途）与目标邮箱一致性，并拒绝已被其他账号占用的邮箱。
            $currentEmail = trim((string) $request->input('oldemail', $request->input('current_email', $userLogin->email)));
            $newEmail = trim((string) $request->input('useremail', $request->input('new_email', '')));
            $verification = $this->profileVerification($request, 'updverify', (int) $userInfo->user_id);
            $submittedCode = trim((string) $request->input('updVerifyCode', $request->input('verification_code', '')));

            if ($submittedCode === '' || $submittedCode !== $verification['code']) {
                return $this->contactInfoFail($request, 'codeErr', 'verfyCode');
            }
            if ($verification['email'] === '' || strtolower($newEmail) !== strtolower($verification['email'])) {
                return $this->contactInfoFail($request, 'emailErr', 'useremail');
            }
            if (strtolower($currentEmail) !== strtolower((string) $userLogin->email)) {
                return $this->contactInfoFail($request, 'emailErr', 'useremail');
            }
            if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                return $this->contactInfoFail($request, 'emailErr', 'useremail');
            }
            if (UserLogin::where('email', $newEmail)->where('id', '!=', $userLogin->id)->exists()) {
                return $this->contactInfoFail(
                    $request,
                    'emailExists',
                    'useremail',
                    ResponseCode::EMAIL_EXISTS
                );
            }

            $userLogin->update(['email' => $newEmail]);
            $this->forgetProfileVerification($request, 'updverify', (int) $userInfo->user_id);

            return $this->contactInfoSuccess($request, 'email');
        }

        if ($type === 'phone') {
            // 手机号分支：旧手机号必须与资料一致（兼容带 86- 区号），新手机号缺省区号时按中国手机号规则补齐。
            $oldPhone = trim((string) $request->input('oldphonefill', $request->input('verify_phone', $userInfo->phone)));
            $newPhone = trim((string) $request->input('userphoneNo', $request->input('newuserphoneNo', $request->input('new_phone', ''))));
            if ($oldPhone !== '' && $oldPhone !== (string) $userInfo->phone && ('86-' . $oldPhone) !== (string) $userInfo->phone) {
                return $this->contactInfoFail($request, 'phoneErr', 'userphoneNo');
            }
            if ($newPhone === '') {
                return $this->contactInfoFail($request, 'phoneErr', 'userphoneNo');
            }

            $userInfo->update(['phone' => strpos($newPhone, '-') === false ? '86-' . $newPhone : $newPhone]);

            return $this->contactInfoSuccess($request, 'phone');
        }

        return $this->contactInfoFail($request, 'typeErr', 'type');
    }

    /**
     * changeBankCardVerifyCode 用于兼容旧前台银行卡换绑前的邮箱校验。
     *
     * @param Request $request HTTP 请求对象，承载 useremail 或 verify_email。
     * @return JsonResponse 旧前台兼容响应。
     */
    public function changeBankCardVerifyCode(Request $request): JsonResponse
    {
        [$userLogin] = $this->currentProfileContext($request);
        $email = strtolower(trim((string) $request->input('useremail', $request->input('verify_email', ''))));

        if (!$userLogin) {
            return $this->legacyFail('userNotFound', 'userId');
        }
        if ($email === '' || strtolower((string) $userLogin->email) !== $email) {
            return $this->legacyFail('useremail', 'useremail');
        }

        return $this->legacySuccess();
    }

    /**
     * updateVerifyInfo 用于兼容旧前台手机号或邮箱唯一性校验。
     *
     * 参数含义：
     * - type 表示校验类型，phone=校验手机号，email=校验邮箱。
     * - userphoneNo 表示旧前台提交的手机号。
     * - useremail 表示旧前台提交的邮箱。
     *
     * @param Request $request HTTP 请求对象，承载旧前台唯一性校验字段。
     * @return JsonResponse 旧前台兼容校验响应。
     */
    public function updateVerifyInfo(Request $request): JsonResponse
    {
        [$userLogin, $userInfo] = $this->currentProfileContext($request);
        $type = (string) $request->input('type', '');
        $phone = $this->normalizeChinaPhone((string) $request->input('userphoneNo', ''));
        $email = strtolower(trim((string) $request->input('useremail', $request->input('email', ''))));

        if (!$userLogin || !$userInfo) {
            return response()->json(['msg' => 'FAIL', 'err' => 'userNotFound']);
        }

        if ($type === 'phone') {
            $exists = $phone !== '' && UserInfo::where('phone', $phone)
                ->where('user_id', '!=', $userInfo->user_id)
                ->exists();

            return $exists
                ? response()->json(['msg' => 'FAIL', '_tel' => 'userphoneNo'])
                : response()->json(['msg' => 'SUC']);
        }

        if ($type === 'email') {
            $exists = $email !== '' && UserLogin::where('email', $email)
                ->where('id', '!=', $userLogin->id)
                ->exists();

            return $exists
                ? response()->json(['msg' => 'FAIL', '_eml' => 'useremail'])
                : response()->json(['msg' => 'SUC']);
        }

        return response()->json(['msg' => 'FAIL', 'err' => 'typeErr']);
    }

    /**
     * cancelVerifyInfo 用于校验销户前的手机号、邮箱和身份证号。
     *
     * 参数含义：
     * - userphoneNo/phone 表示用户提交的当前手机号。
     * - useremail/email 表示用户提交的当前邮箱。
     * - userIdcardNo/id_card_no 表示用户提交的身份证号。
     *
     * @param Request $request HTTP 请求对象，承载销户前身份校验字段。
     * @return JsonResponse 旧前台兼容校验响应。
     */
    public function cancelVerifyInfo(Request $request): JsonResponse
    {
        [$userLogin, $userInfo] = $this->currentProfileContext($request);
        if (!$userLogin || !$userInfo) {
            return $this->legacyFail('userNotFound', 'userId');
        }

        $submittedPhone = (string) $request->input('userphoneNo', $request->input('phone', ''));
        $submittedEmail = strtolower(trim((string) $request->input('useremail', $request->input('email', ''))));
        $submittedIdCard = trim((string) $request->input('userIdcardNo', $request->input('id_card_no', '')));
        $auth = UserAuth::where('user_id', $userInfo->user_id)->first();
        $idCardNo = trim((string) ($auth->id_card_no ?? $auth->id_card ?? ''));

        if (!$this->phoneMatches($submittedPhone, (string) $userInfo->phone)) {
            return $this->legacyFail('phoneErr', 'userphoneNo');
        }
        if ($submittedEmail === '' || strtolower((string) $userLogin->email) !== $submittedEmail) {
            return $this->legacyFail('emailErr', 'useremail');
        }
        if ($idCardNo === '' || $submittedIdCard !== $idCardNo) {
            return $this->legacyFail('IDcardnoErr', 'IDcard_no');
        }

        return $this->legacySuccess('SUC', 'NOErr', 'NOCOL');
    }

    /**
     * updVerifyPassSendCode 用于发送修改资料验证邮件验证码。
     *
     * @param Request $request HTTP 请求对象，承载邮箱和旧前台验证码用途字段。
     * @return JsonResponse 邮件发送结果响应。
     */
    public function updVerifyPassSendCode(Request $request): JsonResponse
    {
        return $this->sendLegacyProfileCode($request, 'updverify');
    }

    /**
     * changeBankCardSendCode 用于发送银行卡换绑验证邮件验证码。
     *
     * @param Request $request HTTP 请求对象，承载邮箱和旧前台验证码用途字段。
     * @return JsonResponse 邮件发送结果响应。
     */
    public function changeBankCardSendCode(Request $request): JsonResponse
    {
        return $this->sendLegacyProfileCode($request, 'change');
    }

    /**
     * cancelVerifyPassSendCode 用于发送销户验证邮件验证码。
     *
     * @param Request $request HTTP 请求对象，承载邮箱和旧前台验证码用途字段。
     * @return JsonResponse 邮件发送结果响应。
     */
    public function cancelVerifyPassSendCode(Request $request): JsonResponse
    {
        return $this->sendLegacyProfileCode($request, 'cancel');
    }

    /**
     * relationShip 用于返回代理关系链文本。
     *
     * 参数含义：
     * - userId/user_id 表示目标业务用户 ID。
     *
     * @param Request $request HTTP 请求对象，承载目标用户 ID。
     * @return JsonResponse 关系链文本响应。
     */
    public function relationShip(Request $request): JsonResponse
    {
        $legacyLabels = !$request->is('api/front/profile/relationship-path');

        return response()->json(['real' => $this->relationshipText($request, ' -> ', $legacyLabels)]);
    }

    /**
     * relationShipHtml 用于兼容旧前台普通用户关系链 HTML 接口。
     *
     * @param Request $request HTTP 请求对象，承载目标用户 ID。
     * @return JsonResponse 关系链文本响应。
     */
    public function relationShipHtml(Request $request): JsonResponse
    {
        return response()->json(['real' => $this->renderRelationshipHtmlPath($request, false)]);
    }

    /**
     * relationShipHtmlV2 用于兼容旧前台代理关系链 HTML 接口。
     *
     * @param Request $request HTTP 请求对象，承载目标用户 ID。
     * @return JsonResponse 关系链文本响应。
     */
    public function relationShipHtmlV2(Request $request): JsonResponse
    {
        return response()->json(['real' => $this->renderRelationshipHtmlPath($request, true)]);
    }

    /**
     * resolveAvatarUrl 用于统一头像浏览器 URL 规则。
     *
     * 逻辑说明：
     * - 外链和绝对路径原样返回。
     * - storage 相对路径统一转成 `/storage/...` 公开 URL。
     * - 空值使用默认头像 `/images/default-avatar.svg`。
     *
     * @param string|null $avatar 数据库存储的头像路径。
     * @return string 浏览器可直接访问的头像地址。
     */
    private function resolveAvatarUrl($avatar): string
    {
        if (!$avatar) {
            return '/images/default-avatar.svg';
        }

        if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
            $path = parse_url($avatar, PHP_URL_PATH);

            if (is_string($path) && strpos($path, '/storage/') === 0) {
                return $path;
            }

            return $avatar;
        }

        if (strpos($avatar, '/') === 0) {
            return $avatar;
        }

        return '/storage/' . ltrim($avatar, '/');
    }

    /**
     * resolveFileUrl 用于统一资料认证文件的浏览器 URL 规则。
     *
     * 参数含义：
     * - $path 表示 user_auths 表中保存的图片或文件路径，可能为空、外链、/storage 绝对路径或 public disk 相对路径。
     *
     * @param string|null $path 数据库存储的认证文件路径。
     * @return string 浏览器可直接访问的文件地址；为空时返回空字符串，避免页面误展示默认头像。
     */
    private function resolveFileUrl($path): string
    {
        if (!$path) {
            return '';
        }

        return $this->resolveAvatarUrl($path);
    }

    /**
     * storeProfileFile 用于保存资料认证文件。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，必须已经通过上传文件校验。
     * - $field 表示上传文件字段名，例如 id_card_front、bank_card_img、bank_card_back_img。
     * - $directory 表示 public disk 下的保存目录，例如 auth/{user_id}/identity 或 auth/{user_id}/bank。
     *
     * @param Request $request HTTP 请求对象，承载已校验的上传文件。
     * @param string $field 上传字段名称。
     * @param string $directory 保存目录。
     * @return string 写入数据库的 public disk 相对路径。
     */
    private function storeProfileFile(Request $request, string $field, string $directory): string
    {
        $path = $request->file($field)->store($directory, 'public');
        $this->mirrorPublicDiskFile($path);

        return $path;
    }

    /**
     * 校验当前用户提交的手机号和邮箱是否与资料表一致。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，verify_phone 为手机号校验值，verify_email 为邮箱校验值。
     *
     * @param Request $request HTTP 请求对象。
     * @return array{0: UserLogin|null, 1: UserInfo|null} 第一项为登录账号，第二项为通过校验的业务资料；校验失败时第二项为 null。
     */
    private function verifiedContactUser(Request $request): array
    {
        /** @var UserLogin $userLogin */
        [$userLogin, $userInfo] = $this->currentProfileContext($request);
        if (!$userLogin || !$userInfo) {
            return [$userLogin, null];
        }

        $phoneMatches = $this->phoneMatches(
            (string) $request->input('verify_phone'),
            (string) $userInfo->phone
        );
        $emailMatches = strtolower(trim((string) $request->input('verify_email'))) === strtolower((string) $userLogin->email);

        return [$userLogin, ($phoneMatches && $emailMatches) ? $userInfo : null];
    }

    /**
     * 获取当前前台资料上下文。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，可来自新版 JWT 前台接口，也可来自旧前台 session 兼容入口。
     *
     * @param Request $request HTTP 请求对象。
     * @return array{0: UserLogin|null, 1: UserInfo|null} 当前登录表资料和业务资料。
     */
    private function currentProfileContext(Request $request): array
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        $userInfo = $this->legacyFrontUserInfo($request);

        return [$userLogin, $userInfo];
    }

    /**
     * 统一中国手机号格式。
     *
     * 参数含义：
     * - $phone 表示用户提交的手机号；未带国家区号时补为 86-手机号，已带横线时保持原值。
     *
     * @param string $phone 原始手机号。
     * @return string 规范化后的手机号。
     */
    private function normalizeChinaPhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        return strpos($phone, '-') === false ? '86-' . $phone : $phone;
    }

    /**
     * 判断用户提交手机号是否匹配数据库手机号。
     *
     * 参数含义：
     * - $submitted 表示表单提交手机号，可能是不带 86- 的本地号码。
     * - $stored 表示 user_infos.phone 中保存的手机号，可能带 86- 国家区号。
     *
     * @param string $submitted 用户提交手机号。
     * @param string $stored 数据库保存手机号。
     * @return bool true=手机号匹配，false=手机号为空或不一致。
     */
    private function phoneMatches(string $submitted, string $stored): bool
    {
        $submitted = trim($submitted);
        $stored = trim($stored);
        if ($submitted === '' || $stored === '') {
            return false;
        }

        $storedLocal = strpos($stored, '-') === false ? $stored : substr($stored, strpos($stored, '-') + 1);

        return $submitted === $stored || $submitted === $storedLocal || $this->normalizeChinaPhone($submitted) === $stored;
    }

    /**
     * 发送旧前台资料相关邮箱验证码。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，useremail/email 为接收邮箱，userphoneNo/type 会随验证码一起缓存。
     * - $purpose 表示验证码用途，例如 updverify=资料修改，change=银行卡换绑，cancel=销户校验。
     *
     * @param Request $request HTTP 请求对象。
     * @param string $purpose 验证码用途标识。
     * @return JsonResponse 旧前台布尔格式响应，status=true 表示发送成功。
     */
    private function sendLegacyProfileCode(Request $request, string $purpose): JsonResponse
    {
        [$userLogin, $userInfo] = $this->currentProfileContext($request);
        if (!$userLogin || !$userInfo) {
            return response()->json(['status' => false]);
        }

        $email = strtolower(trim((string) $request->input('useremail', $request->input('email', $userLogin->email))));
        $type = (string) $request->input('type', '');
        // 只有 updverify + email 场景允许向新邮箱发码；其余用途必须发给当前登录邮箱，防止验证码被发往他人邮箱。
        $allowsNewEmailTarget = $purpose === 'updverify' && $type === 'email';
        if (
            $email === ''
            || (!$allowsNewEmailTarget && strtolower((string) $userLogin->email) !== $email)
        ) {
            return response()->json(['status' => false]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['status' => false]);
        }
        if (
            $allowsNewEmailTarget
            && UserLogin::where('email', $email)->where('id', '!=', $userLogin->id)->exists()
        ) {
            return response()->json(['status' => false]);
        }

        // 发码前先废止旧验证码：即使本次邮件发送失败，旧码也不能继续用于当前用途，防止重放。
        $this->forgetProfileVerification($request, $purpose, (int) $userInfo->user_id);
        $code = (string) random_int(123456, 999999);

        try {
            Mail::raw('Your verification code is: ' . $code, function ($message) use ($email, $purpose) {
                $message->to($email)->subject('Front profile verification code - ' . $purpose);
            });
        } catch (Exception $e) {
            return response()->json(['status' => false]);
        }

        // 只有邮件服务确认发送成功后才写缓存，避免用户拿不到邮件但系统留下有效验证码。
        Cache::put('front_profile_' . $purpose . '_code:' . $userInfo->user_id, [
            'code' => $code,
            'email' => $email,
            'phone' => trim((string) $request->input('userphoneNo', '')),
            'type' => $type,
        ], now()->addMinutes(10));

        return response()->json(['status' => true]);
    }

    /**
     * 生成代理关系链文本。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，userId/user_id 为目标业务用户 ID。
     * - $separator 表示关系链分隔符，新接口使用带空格箭头，旧 HTML 接口使用紧凑箭头。
     *
     * @param Request $request HTTP 请求对象。
     * @param string $separator 关系链分隔符。
     * @return string 由上级代理到目标用户组成的关系链文本。
     */
    private function relationshipText(Request $request, string $separator, bool $useLegacyLabels = false): string
    {
        $nodes = $this->relationshipNodes($request);
        if ($nodes === []) {
            return '';
        }

        $ids = array_map(static function (UserInfo $node): int {
            return (int) $node->user_id;
        }, $nodes);
        if (!$ids) {
            return '';
        }

        if (!$useLegacyLabels) {
            return implode($separator, array_map('strval', $ids));
        }

        return implode($separator, array_map(function (UserInfo $node): string {
            return $this->relationshipLabel($node);
        }, $nodes));
    }

    /**
     * 生成旧前台关系链 HTML。
     *
     * 参数含义：
     * - $request 表示旧 relationShipHtml 请求，fname 为前端点击节点时调用的函数名。
     * - $withPositionPrefix 表示代理 V2 入口是否需要“我的位置”前缀和层级 class。
     *
     * @param Request $request HTTP 请求对象。
     * @param bool $withPositionPrefix true=代理 V2 位置链，false=普通旧关系链。
     * @return string 使用真实 user_infos 资料渲染的旧前台关系链 HTML。
     */
    private function renderRelationshipHtmlPath(Request $request, bool $withPositionPrefix): string
    {
        $nodes = $this->relationshipNodes($request);
        if ($nodes === []) {
            return '';
        }

        $callback = $this->relationshipCallbackName($request);
        $parts = array_map(function (UserInfo $node) use ($callback, $withPositionPrefix): string {
            $userId = (int) $node->user_id;
            $label = e($this->relationshipLabel($node));
            $class = 'crm-relationship-node';
            if ($withPositionPrefix) {
                $class .= ' crm-relationship-node-level-' . max(0, (int) $node->group_id);
            }

            $open = '<span class="' . e($class) . '" data-user_id="' . $userId . '">';
            $close = '</span>';
            if ($callback === '') {
                return $open . $label . $close;
            }

            $onclick = e($callback . '(' . $userId . ')');

            return $open
                . '<a href="javascript:void(0)" onclick="' . $onclick . '">' . $label . '</a>'
                . $close;
        }, $nodes);

        $html = implode('->', $parts);

        return $withPositionPrefix ? '我的位置: ' . $html : $html;
    }

    /**
     * 读取并校验旧前台传入的关系链节点点击函数名。
     *
     * @param Request $request HTTP 请求对象。
     * @return string 可安全放入 onclick 的函数名；非法或缺失时返回空字符串。
     */
    private function relationshipCallbackName(Request $request): string
    {
        $callback = trim((string) $request->input('fname', ''));

        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$.]*$/', $callback) === 1 ? $callback : '';
    }

    /**
     * 按旧前台格式生成关系链节点标签。
     *
     * @param UserInfo $node 关系链上的用户资料。
     * @return string user_name[user_id] 格式的节点标签。
     */
    private function relationshipLabel(UserInfo $node): string
    {
        $name = trim((string) $node->user_name);
        if ($name === '') {
            $name = (string) $node->user_id;
        }

        return $name . '[' . (int) $node->user_id . ']';
    }

    /**
     * 解析当前请求可见的关系链用户资料节点。
     *
     * @param Request $request HTTP 请求对象。
     * @return array<int, UserInfo> 从顶层代理到目标用户的真实资料节点。
     */
    private function relationshipNodes(Request $request): array
    {
        $targetUserId = (int) $request->input('userId', $request->input('user_id', 0));
        if ($targetUserId <= 0) {
            return [];
        }
        if (!$this->canViewRelationshipTarget($request, $targetUserId)) {
            return [];
        }

        $target = UserInfo::where('user_id', $targetUserId)->first();
        if (!$target) {
            return [];
        }

        $ids = $this->relationshipIds($target);
        if (!$ids) {
            $ids = [$targetUserId];
        }

        $nodes = UserInfo::whereIn('user_id', $ids)->get()->keyBy('user_id');

        return array_values(array_filter(array_map(static function (int $userId) use ($nodes): ?UserInfo {
            $node = $nodes->get($userId);

            return $node instanceof UserInfo ? $node : null;
        }, $ids)));
    }

    /**
     * 校验当前登录用户是否有权查看目标用户的关系链。
     *
     * 只允许查看本人关系链，或代理账号（account_type=1）查看其直属代理范围内用户的关系链，
     * 防止普通用户通过 userId 遍历他人代理关系。
     *
     * @param Request $request 当前 HTTP 请求对象，用于解析登录用户。
     * @param int $targetUserId 目标业务用户 ID。
     * @return bool true=允许查看，false=无权查看。
     */
    private function canViewRelationshipTarget(Request $request, int $targetUserId): bool
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return false;
        }

        $currentUserId = (int) $userLogin->user_id;
        if ($targetUserId === $currentUserId) {
            return true;
        }
        if ((int) $userLogin->account_type !== 1) {
            return false;
        }

        return in_array($targetUserId, FrontLegacyData::userScopeIds($currentUserId, false), true);
    }

    /**
     * 获取目标用户的代理关系链 ID 列表。
     *
     * 参数含义：
     * - $target 表示目标业务用户资料，优先使用 user_infos.family_tree，缺失时回退 agent_descendants 关系表，再兼容 user_infos.parent_id 导入关系。
     *
     * @param UserInfo $target 目标用户业务资料。
     * @return array<int, int> 代理关系链业务用户 ID 列表。
     */
    private function relationshipIds(UserInfo $target): array
    {
        return $this->parentRelationshipIds($target);
    }

    /**
     * 按 parent_id 向上补齐缺少 family_tree 和闭包表时的关系链。
     *
     * @param UserInfo $target 目标用户业务资料。
     * @return array<int, int> 从最上级代理到目标用户的业务用户 ID 列表。
     */
    private function parentRelationshipIds(UserInfo $target): array
    {
        $targetUserId = (int) $target->user_id;
        $ids = [$targetUserId];
        $visited = [$targetUserId => true];
        $parentId = (int) $target->parent_id;

        while ($parentId > 0 && !isset($visited[$parentId])) {
            array_unshift($ids, $parentId);
            $visited[$parentId] = true;

            $parent = UserInfo::where('user_id', $parentId)->first(['user_id', 'parent_id']);
            if (!$parent) {
                break;
            }

            $parentId = (int) $parent->parent_id;
        }

        return $ids;
    }

    /**
     * legacyBankCardUpload 用于兼容旧前台银行卡上传入口。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象，兼容 bankclass、bankno、bankinfo、bankimg 等旧字段和新版字段。
     * - $isChange 表示是否为银行卡换绑流程；true 写入 *_tmp 字段并设置 bank_status=3，false 写入正式银行卡字段并设置 bank_status=1。
     *
     * @param Request $request HTTP 请求对象。
     * @param bool $isChange true=银行卡换绑，false=首次或重新提交银行卡认证。
     * @return JsonResponse 旧前台 msg/err/col 结构响应。
     */
    private function legacyBankCardUpload(Request $request, bool $isChange): JsonResponse
    {
        [$userLogin, $userInfo] = $this->currentProfileContext($request);
        if (!$userLogin || !$userInfo) {
            return $this->legacyFail('userNotFound', 'userId');
        }

        $bankName = trim((string) $request->input('bank_name', $request->input('bankclass', '')));
        $bankNo = trim((string) $request->input('bank_no', $request->input('bankno', '')));
        $bankAddr = trim((string) $request->input('bank_addr', $request->input('bankinfo', '')));
        $frontField = $this->firstUploadedField($request, ['bank_card_img', 'bankimg', 'file_img', 'file']);
        $backField = $this->firstUploadedField($request, ['bank_card_back_img', 'bank_card_img_back', 'bankimg_back', 'file_img_back']);

        if ($isChange) {
            $bankChangeError = $this->bankChangeValidationError($request, $userLogin, $userInfo);
            if ($bankChangeError !== null) {
                return $this->legacyFail($bankChangeError['error'], $bankChangeError['field']);
            }
        }

        if ($bankName === '') {
            return $this->legacyFail('bankclass', 'bankclass');
        }
        if ($bankNo === '') {
            return $this->legacyFail('bankno', 'bankno');
        }
        if ($bankAddr === '') {
            return $this->legacyFail('bankinfo', 'bankinfo');
        }
        if (!$frontField) {
            return $this->legacyFail('POSERRORFORMAT', 'bankimg');
        }

        $rules = [$frontField => 'image|mimes:jpeg,png,jpg,gif|max:10240'];
        if ($backField) {
            $rules[$backField] = 'image|mimes:jpeg,png,jpg,gif|max:10240';
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->legacyFail('POSERRORFORMAT', $frontField);
        }

        $dir = $isChange ? 'auth/' . $userInfo->user_id . '/bank-change' : 'auth/' . $userInfo->user_id . '/bank';
        $frontPath = $this->storeProfileFile($request, $frontField, $dir);
        $backPath = $backField ? $this->storeProfileFile($request, $backField, $dir) : '';

        $payload = $isChange
            ? [
                'bank_name_tmp' => $bankName,
                'bank_no_tmp' => $bankNo,
                'bank_addr_tmp' => $bankAddr,
                'bank_card_img_tmp' => $frontPath,
                'bank_status' => 3,
                'bank_remarks' => '',
            ]
            : [
                'bank_name' => $bankName,
                'bank_no' => $bankNo,
                'bank_addr' => $bankAddr,
                'bank_card_img' => $frontPath,
                'bank_status' => 1,
                'bank_remarks' => '',
            ];

        if ($backPath !== '') {
            $payload[$isChange ? 'bank_card_back_img_tmp' : 'bank_card_back_img'] = $backPath;
        }

        UserAuth::updateOrCreate(['user_id' => $userInfo->user_id], $payload);

        if ($isChange) {
            $this->forgetProfileVerification($request, 'change', (int) $userInfo->user_id);
        }

        return $this->legacySuccess('SUC', 'NOTERROR');
    }

    /**
     * 校验银行卡换绑的完整业务前置条件。
     *
     * 参数含义：
     * - $request 表示当前换绑请求，兼容现代 verification_code 与旧 userverfcode 字段。
     * - $userLogin 表示认证上下文解析出的当前登录账号，不能由请求体 user_id 覆盖。
     * - $userInfo 表示当前账号对应的业务资料。
     *
     * 返回值：
     * - null 表示银行卡审核状态、待处理出金、密码、邮箱和一次性验证码全部校验通过。
     * - error/field 表示旧前台错误码及对应字段，现代接口会把它们放入 data.error/data.field。
     *
     * @param Request $request 当前银行卡换绑请求。
     * @param UserLogin $userLogin 当前登录账号。
     * @param UserInfo $userInfo 当前业务资料。
     * @return array{error: string, field: string}|null 校验失败信息或 null。
     */
    private function bankChangeValidationError(
        Request $request,
        UserLogin $userLogin,
        UserInfo $userInfo
    ): ?array {
        // 前置条件：银行卡必须处于已认证（bank_status=2）状态，未认证或已存在换绑申请都直接拒绝。
        $userAuth = UserAuth::where('user_id', $userInfo->user_id)->first();
        if (!$userAuth || (int) $userAuth->bank_status !== 2) {
            return ['error' => 'errbankpendingauth', 'field' => 'nocol'];
        }

        // 资金安全：有待处理出金（status 0/1）时禁止换绑，避免出金与银行卡资料变更交错。
        $hasPendingWithdrawal = WithdrawRecord::where('user_id', $userInfo->user_id)
            ->whereIn('status', [0, 1])
            ->exists();
        if ($hasPendingWithdrawal) {
            return ['error' => 'errisapplying', 'field' => 'nocol'];
        }

        // 密码确认：MT4 网络结果未知时返回 NETWORKFAIL，不能继续任何换绑动作。
        $passwordState = $this->passwordService->verify(
            $userLogin,
            (string) $request->input('password', '')
        );
        if ($passwordState === 'network_failure') {
            return ['error' => 'NETWORKFAIL', 'field' => 'FATALCANOTCONNECT'];
        }
        if ($passwordState !== 'verified') {
            return ['error' => 'errpassword', 'field' => 'password'];
        }

        // 邮箱一致性：换绑校验邮箱必须与登录邮箱一致，防止使用他人邮箱上下文完成换绑。
        $submittedEmail = strtolower(trim((string) $request->input(
            'verify_email',
            $request->input('useremail', '')
        )));
        if ($submittedEmail === '' || $submittedEmail !== strtolower((string) $userLogin->email)) {
            return ['error' => 'erruseremail', 'field' => 'useremail'];
        }

        // 一次性验证码（change 用途）：验证码与发码邮箱都必须匹配，防止旧验证码跨用途使用。
        $verification = $this->profileVerification($request, 'change', (int) $userInfo->user_id);
        $submittedCode = trim((string) $request->input(
            'verification_code',
            $request->input('userverfcode', '')
        ));
        if ($submittedCode === '' || $submittedCode !== $verification['code']) {
            return ['error' => 'erruserverfcode', 'field' => 'userverfcode'];
        }
        if ($verification['email'] === '' || $submittedEmail !== strtolower($verification['email'])) {
            return ['error' => 'erruseremail', 'field' => 'useremail'];
        }

        return null;
    }

    /**
     * 读取当前用户指定用途的一次性验证码上下文。
     *
     * 优先读取新架构缓存；缓存不存在时读取旧 Session 字段，使历史 Blade 页面无需改协议。
     * 返回的 code/email/phone/type 均为字符串，空字符串表示对应凭据不存在，调用方必须失败关闭。
     *
     * @param Request $request 当前 HTTP 请求，用于读取旧 Session。
     * @param string $purpose 验证码用途，支持 updverify、change、cancel。
     * @param int $userId 当前认证用户 ID，用于隔离缓存键。
     * @return array{code: string, email: string, phone: string, type: string} 验证码上下文。
     */
    private function profileVerification(Request $request, string $purpose, int $userId): array
    {
        $cached = Cache::get('front_profile_' . $purpose . '_code:' . $userId);
        if (is_array($cached)) {
            return [
                'code' => trim((string) ($cached['code'] ?? '')),
                'email' => trim((string) ($cached['email'] ?? '')),
                'phone' => trim((string) ($cached['phone'] ?? '')),
                'type' => trim((string) ($cached['type'] ?? '')),
            ];
        }

        // API 路由不挂载 Session 中间件；缓存未命中时必须返回空凭据并失败关闭，不能抛出 500。
        if (!$request->hasSession()) {
            return ['code' => '', 'email' => '', 'phone' => '', 'type' => ''];
        }

        $prefix = $purpose === 'updverify' ? 'updverify' : $purpose;
        $emailKey = $purpose === 'change' ? 'changeuseremail' : $prefix . 'Email';
        $phoneKey = $purpose === 'change' ? 'changePhoneNo' : $prefix . 'phoneNo';
        $codeKey = $purpose === 'change' ? 'changeCode' : $prefix . 'Code';

        return [
            'code' => trim((string) $request->session()->get($codeKey, '')),
            'email' => trim((string) $request->session()->get($emailKey, '')),
            'phone' => trim((string) $request->session()->get($phoneKey, '')),
            'type' => trim((string) $request->session()->get($prefix . 'Type', '')),
        ];
    }

    /**
     * 消费当前用户指定用途的一次性验证码。
     *
     * 只有业务写库成功后调用；同时删除新缓存与旧 Session 字段，防止同一验证码跨协议重放。
     *
     * @param Request $request 当前 HTTP 请求，用于清理旧 Session。
     * @param string $purpose 验证码用途，支持 updverify、change、cancel。
     * @param int $userId 当前认证用户 ID。
     * @return void
     */
    private function forgetProfileVerification(Request $request, string $purpose, int $userId): void
    {
        Cache::forget('front_profile_' . $purpose . '_code:' . $userId);

        if (!$request->hasSession()) {
            return;
        }

        if ($purpose === 'change') {
            $request->session()->forget(['changeCode', 'changeuseremail', 'changePhoneNo', 'changeType']);

            return;
        }

        $request->session()->forget([
            $purpose . 'Code',
            $purpose . 'Email',
            $purpose . 'phoneNo',
            $purpose . 'Type',
        ]);
    }

    /**
     * 从候选字段中查找第一个实际上传的文件字段。
     *
     * 参数含义：
     * - $request 表示当前 HTTP 请求对象。
     * - $fields 表示候选上传字段名列表，按兼容优先级从高到低排列。
     *
     * @param Request $request HTTP 请求对象。
     * @param array<int, string> $fields 候选字段名列表。
     * @return string|null 找到时返回字段名，未上传任何候选字段时返回 null。
     */
    private function firstUploadedField(Request $request, array $fields): ?string
    {
        foreach ($fields as $field) {
            if ($request->hasFile($field)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * 按当前路由返回联系方式更新成功结果。
     *
     * 现代 API 必须返回 code/message/data，供 CRMUI 严格判断业务成功；旧 URI
     * 继续返回 msg/err/col，避免破坏历史 Blade/JavaScript 调用方。
     */
    private function contactInfoSuccess(Request $request, string $type): JsonResponse
    {
        if ($request->routeIs('front_api_profile_contact_info')) {
            return $this->success(['type' => $type], '', ResponseCode::UPDATED);
        }

        return $this->legacySuccess('SUC');
    }

    /**
     * 按当前路由返回联系方式更新失败结果。
     *
     * @param int $modernCode 现代 API 的明确业务码；旧 URI 仍使用原 err/col。
     */
    private function contactInfoFail(
        Request $request,
        string $error,
        string $field,
        int $modernCode = ResponseCode::VALIDATION_FAILED
    ): JsonResponse
    {
        if ($request->routeIs('front_api_profile_contact_info')) {
            return $this->error('', $modernCode, [
                'error' => $error,
                'field' => $field,
            ]);
        }

        return $this->legacyFail($error, $field);
    }

    /**
     * 返回现代敏感操作的统一密码校验失败响应。
     *
     * 参数含义：
     * - $passwordState 表示 UserPasswordService 返回的 rejected 或 network_failure。
     * - $rejectedError 表示密码明确错误时写入 data.error 的旧业务错误码。
     *
     * 返回结果：
     * - rejected 返回 OLD_PASSWORD_WRONG、password 字段，页面提示当前密码错误。
     * - network_failure 返回 THIRD_PARTY_ERROR、FATALCANOTCONNECT，页面提示 MT4 网络结果未知。
     *
     * @param string $passwordState 密码服务验证状态。
     * @param string $rejectedError 密码错误业务码。
     * @return JsonResponse 现代 API 统一错误响应。
     */
    private function sensitivePasswordError(string $passwordState, string $rejectedError): JsonResponse
    {
        if ($passwordState === 'network_failure') {
            return $this->error('', ResponseCode::THIRD_PARTY_ERROR, [
                'error' => 'NETWORKFAIL',
                'field' => 'FATALCANOTCONNECT',
            ]);
        }

        return $this->error('', ResponseCode::OLD_PASSWORD_WRONG, [
            'error' => $rejectedError,
            'field' => 'password',
        ]);
    }

    /**
     * 生成旧前台成功响应。
     *
     * 参数含义：
     * - $msg 表示旧前台成功标识，通常为 SUC 或 SUCCESS。
     * - $err 表示错误占位字段，成功时保持 noerr/NOTERROR 等旧文案。
     * - $col 表示旧前台高亮字段名，成功时通常为 nocol。
     *
     * @param string $msg 成功消息标识。
     * @param string $err 错误占位标识。
     * @param string $col 字段占位标识。
     * @return JsonResponse 旧前台 msg/err/col 结构响应。
     */
    private function legacySuccess(string $msg = 'SUC', string $err = 'noerr', string $col = 'nocol'): JsonResponse
    {
        return response()->json([
            'msg' => $msg,
            'err' => $err,
            'col' => $col,
        ]);
    }

    /**
     * 生成旧前台失败响应。
     *
     * 参数含义：
     * - $err 表示旧前台识别的错误码，例如 bankno、userNotFound、phoneErr。
     * - $col 表示需要旧页面定位或高亮的字段名。
     *
     * @param string $err 旧前台错误码。
     * @param string $col 旧前台字段标识。
     * @return JsonResponse 旧前台 msg/err/col 结构响应。
     */
    private function legacyFail(string $err, string $col = 'nocol'): JsonResponse
    {
        return response()->json([
            'msg' => 'FAIL',
            'err' => $err,
            'col' => $col,
        ]);
    }

    /**
     * 转换实名认证状态为多语言文案。
     *
     * 参数含义：
     * - $status 表示 user_auths.id_card_status，1=待审核，2=已通过，4=已拒绝，其他=未认证。
     *
     * @param int $status 实名认证状态码。
     * @return string 当前语言下的状态文案。
     */
    private function idCardStatusText(int $status): string
    {
        if ($status === 1) {
            return __('front.status_pending');
        }
        if ($status === 2) {
            return __('front.status_approved');
        }
        if ($status === 4) {
            return __('front.status_rejected');
        }

        return __('front.status_unverified');
    }

    /**
     * 转换银行卡认证状态为多语言文案。
     *
     * 参数含义：
     * - $status 表示 user_auths.bank_status，1=银行卡认证待审核，2=已通过，3=换绑待审核，4=已拒绝。
     *
     * @param int $status 银行卡认证状态码。
     * @return string 当前语言下的状态文案。
     */
    private function bankStatusText(int $status): string
    {
        if ($status === 1 || $status === 3) {
            return __('front.status_pending');
        }
        if ($status === 2) {
            return __('front.status_approved');
        }
        if ($status === 4) {
            return __('front.status_rejected');
        }

        return __('front.status_unverified');
    }

    /**
     * 在未创建 storage 软链时同步 public disk 文件到公开目录。
     *
     * 参数含义：
     * - $path 表示 public disk 相对路径，例如 avatars/1001/a.png 或 auth/1001/bank/a.jpg。
     *
     * @param string $path public disk 相对路径。
     * @return void
     */
    private function mirrorPublicDiskFile(string $path): void
    {
        if (is_link(public_path('storage'))) {
            return;
        }

        $source = Storage::disk('public')->path($path);
        $target = public_path('storage/' . ltrim($path, '/'));

        if (!is_file($source)) {
            return;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);
    }

    /**
     * 删除 public/storage 下的镜像文件。
     *
     * 参数含义：
     * - $path 表示数据库保存的文件路径，可能是外链、/storage 路径或 public disk 相对路径。
     *
     * @param string|null $path 需要删除镜像的文件路径。
     * @return void
     */
    private function deletePublicMirror($path): void
    {
        if (!$path || is_link(public_path('storage'))) {
            return;
        }

        $value = (string) $path;
        if (strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        }
        $value = ltrim($value, '/');
        if (strpos($value, 'storage/') === 0) {
            $value = substr($value, 8);
        }

        $target = public_path('storage/' . ltrim($value, '/'));
        if (is_file($target)) {
            File::delete($target);
        }
    }

    /**
     * 手机号脱敏。
     *
     * 参数含义：
     * - $value 表示原始手机号，空值保持为空。
     *
     * @param string $value 原始手机号。
     * @return string 脱敏后的手机号。
     */
    private function maskPhone(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) >= 7
            ? substr($value, 0, 3) . '****' . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

    /**
     * 邮箱脱敏。
     *
     * 参数含义：
     * - $value 表示原始邮箱，非邮箱格式保持原值，避免误处理旧数据。
     *
     * @param string $value 原始邮箱。
     * @return string 脱敏后的邮箱。
     */
    private function maskEmail(string $value): string
    {
        if ($value === '' || strpos($value, '@') === false) {
            return $value;
        }

        [$name, $domain] = explode('@', $value, 2);
        $visible = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $visible . '***@' . $domain;
    }

    /**
     * 身份证号脱敏。
     *
     * 参数含义：
     * - $value 表示原始身份证号，空值保持为空。
     *
     * @param string $value 原始身份证号。
     * @return string 脱敏后的身份证号。
     */
    private function maskIdCard(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) > 8
            ? substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

    /**
     * 银行卡号脱敏。
     *
     * 参数含义：
     * - $value 表示原始银行卡号，空值保持为空。
     *
     * @param string $value 原始银行卡号。
     * @return string 脱敏后的银行卡号。
     */
    private function maskBankNo(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) > 8
            ? substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }
}
