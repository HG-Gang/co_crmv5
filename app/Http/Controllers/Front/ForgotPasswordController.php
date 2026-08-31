<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Mail\FrontResetPasswordCode;
use App\Models\UserLogin;
use App\Services\UserPasswordService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 前台找回密码控制器。
 *
 * 文件功能：
 * - 新前台接口包含 `/api/front/auth/password/email-code` 和 `/api/front/auth/password/reset`。
 * - 旧前台兼容接口包含 `user/check_user_info`、`user/forgetpswSendCode`、`user/forgetPasswordInfoVerification` 和 `user/change_password`。
 * - 验证码统一写入 Cache key `front_reset_code:{email}`，缓存值绑定 user_id、email、code，有效期 600 秒。
 * - 新接口使用 ApiResponse 与 Laravel 多语言 key；旧接口保留 msg/err/col 结构，避免旧页面脚本失效。
 *
 * 安全边界：
 * - 验证码缓存绑定 user_id、email、code 三要素，校验时三者全部匹配才放行。
 * - 同一邮箱 + IP 60 秒内只允许发送一次验证码；邮件发送失败会回滚限流标记，允许立即重试。
 * - 密码与 MT4 同步全部成功后才删除验证码缓存，旧验证码不可再次使用；MT4 失败时保留凭据以便重试。
 * - 旧接口错误码（IDerror/UserDisable/emailerror/errorCodedate）是旧页面脚本契约，响应不包含任何用户资料字段。
 * - 密码明文永不写入响应或日志。
 */
class ForgotPasswordController extends FrontBaseController
{
    /**
     * 密码服务：找回密码链路的核心依赖，负责本地密码更新与 MT4 登录端密码同步的双侧一致性；
     * “双侧都成功才消费验证码、MT4 失败保留凭据以便重试”的语义封装在该服务内，缺失时重置流程无法保证 MT4 侧密码同步。
     *
     * @var UserPasswordService
     */
    private $passwordService;

    /**
     * 构造函数注入密码服务。
     *
     * @param UserPasswordService $passwordService 密码修改服务，负责本地密码与 MT4 登录端同步。
     */
    public function __construct(UserPasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    /**
     * 渲染旧前台找回密码页面。
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function showForgotPassword()
    {
        return view('front_layui::auth.forgot-password');
    }

    /**
     * 发送找回密码邮箱验证码。
     *
     * 参数含义：
     * - email 表示接收验证码的登录邮箱，是新前台接口使用的标准参数。
     * - useremail 表示旧前台提交的邮箱参数，进入方法后会归一化到 email。
     * - front_reset_code:{email} 表示验证码缓存 key，缓存值绑定当前用户 ID、标准化邮箱和 6 位验证码。
     * - front_reset_code_rate_{hash} 表示邮箱和请求 IP 的 60 秒发送限流 key。
     * - debug_code 仅在非生产环境返回，方便本地和测试环境验证流程。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse
     */
    public function sendResetCode(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', $request->input('useremail', ''))));
        $request->merge(['email' => $email]);
        $isLegacyRequest = $request->hasAny(['useremail', 'userId', 'user_id']);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            if ($isLegacyRequest) {
                return response()->json(['status' => false]);
            }

            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $login = UserLogin::where('email', $email)->first();
        if (!$login) {
            if ($isLegacyRequest) {
                return response()->json(['status' => false]);
            }

            return $this->error('response.user_not_found', ResponseCode::USER_NOT_FOUND);
        }

        if (!$login->isActive()) {
            if ($isLegacyRequest) {
                return response()->json(['status' => false]);
            }

            return $this->error('response.user_disabled', ResponseCode::USER_DISABLED);
        }

        if ($isLegacyRequest) {
            $legacyUserId = $this->validatedLegacyUserId($request);
            if ($legacyUserId === null
                || $legacyUserId !== (int) $login->user_id
                || strtolower((string) $login->email) !== $email) {
                return response()->json(['status' => false]);
            }
        }

        $rateKey = 'front_reset_code_rate_' . sha1($email . '|' . $request->ip());
        if (!Cache::add($rateKey, 1, 60)) {
            if ($isLegacyRequest) {
                return response()->json(['status' => false]);
            }

            return $this->error('response.rate_limited', ResponseCode::RATE_LIMITED);
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::to($login->email)->send(new FrontResetPasswordCode($code));
        } catch (Throwable $exception) {
            Cache::forget($rateKey);
            if ($isLegacyRequest) {
                return response()->json(['status' => false]);
            }

            return $this->error('response.email_send_failed', ResponseCode::EMAIL_SEND_FAILED);
        }

        Cache::put($this->resetCodeCacheKey($email), [
            'user_id' => (int) $login->user_id,
            'email' => $email,
            'code' => $code,
        ], 600);

        if ($isLegacyRequest) {
            return response()->json(['status' => true]);
        }

        return $this->success([
            'email' => $login->email,
            'debug_code' => app()->environment('production') ? '' : $code,
        ], 'auth.reset_code_sent', ResponseCode::SUCCESS);
    }

    /**
     * 重置前台登录密码。
     *
     * 参数含义：
     * - email 表示登录邮箱，兼容旧参数 useremail。
     * - code 表示新前台提交的验证码，兼容旧参数 codedata。
     * - codedata 表示旧前台提交的验证码参数。
     * - password 表示新密码，必须不少于 6 位。
     * - password_confirmation 表示 Laravel confirmed 规则使用的确认密码；旧接口未传时默认使用 password。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', $request->input('useremail', '')))),
            'code' => trim((string) $request->input('code', $request->input('codedata', ''))),
            'password_confirmation' => $request->input('password_confirmation', $request->input('password')),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $email = strtolower(trim((string) $request->input('email')));
        // 验证码按邮箱读取缓存并比对 code、email、user_id 三要素，任一不符都按验证码无效处理。
        $payload = $this->resetCodePayload($email);
        if (!$payload
            || (string) $payload['email'] !== $email
            || (string) $payload['code'] !== (string) $request->input('code')) {
            return $this->error('auth.reset_code_invalid', ResponseCode::VALIDATION_FAILED);
        }

        $login = UserLogin::where('email', $request->input('email'))->first();
        if (!$login) {
            return $this->error('response.user_not_found', ResponseCode::USER_NOT_FOUND);
        }

        if (!$login->isActive()) {
            return $this->error('response.user_disabled', ResponseCode::USER_DISABLED);
        }

        if ((int) $payload['user_id'] !== (int) $login->user_id) {
            return $this->error('auth.reset_code_invalid', ResponseCode::VALIDATION_FAILED);
        }

        if (!$this->passwordService->change($login, (string) $request->input('password'))) {
            return $this->error('response.mt4_sync_failed', ResponseCode::MT4_SYNC_FAILED);
        }

        // 密码与 MT4 同步成功后才消费验证码，防止同一验证码再次用于修改密码。
        Cache::forget($this->resetCodeCacheKey($email));

        return $this->success([], 'auth.password_reset_success', ResponseCode::UPDATED);
    }

    /**
     * checkUserInfo 用于旧前台找回密码第一步校验用户 ID 与邮箱。
     *
     * 参数含义：
     * - userId / user_id 表示旧前台提交的业务用户 ID。
     * - useremail / email 表示旧前台提交的邮箱参数。
     * - IDerror、UserDisable、emailerror 是旧页面脚本识别的错误码，不能在本轮改名。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse
     */
    public function checkUserInfo(Request $request): JsonResponse
    {
        $userId = $this->validatedLegacyUserId($request);
        $email = strtolower(trim((string) $request->input('useremail', $request->input('email', ''))));

        if ($userId === null) {
            return $this->legacyFail('IDerror', 'userId');
        }

        $login = UserLogin::where('user_id', $userId)->first();
        if (!$login) {
            return $this->legacyFail('IDerror', 'userId');
        }
        if (!$login->isActive()) {
            return $this->legacyFail('UserDisable', 'userId');
        }
        if ($email === '' || strtolower((string) $login->email) !== $email) {
            return $this->legacyFail('emailerror', 'userId');
        }

        return $this->legacySuccess();
    }

    /**
     * forgetPasswordInfoVerification 用于旧前台校验邮箱验证码。
     *
     * 参数含义：
     * - useremail / email 表示验证码所属邮箱。
     * - codedata / code 表示用户输入的验证码。
     * - errorCodedate 是旧页面脚本识别的验证码错误码，保留原拼写。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse
     */
    public function forgetPasswordInfoVerification(Request $request): JsonResponse
    {
        $userId = $this->validatedLegacyUserId($request);
        $email = strtolower(trim((string) $request->input('useremail', $request->input('email', ''))));
        $code = trim((string) $request->input('codedata', $request->input('code', '')));
        if ($userId === null) {
            return $this->legacyFail('IDerror', 'userId');
        }

        $login = UserLogin::where('user_id', $userId)->first();
        if (!$login) {
            return $this->legacyFail('IDerror', 'userId');
        }
        if (!$login->isActive()) {
            return $this->legacyFail('UserDisable', 'userId');
        }
        if ($email === '' || strtolower((string) $login->email) !== $email) {
            return $this->legacyFail('emailerror', 'userId');
        }

        $payload = $this->resetCodePayload($email);
        if (!$payload
            || (int) $payload['user_id'] !== $userId
            || (string) $payload['email'] !== $email
            || (string) $payload['code'] !== $code) {
            return $this->legacyFail('errorCodedate', 'getVerifyCode');
        }

        return $this->legacySuccess();
    }

    /**
     * saveChangePassword 用于旧前台保存新密码。
     *
     * 参数含义：
     * - userId / user_id / accountno 表示旧前台提交的业务用户 ID。
     * - password / newPsw 表示新密码。
     * - codedata / code / userverfcode 表示邮箱验证码，必须与绑定当前用户和邮箱的缓存一致。
     * - password_confirmation / againpassword 表示确认密码，必须与新密码一致。
     * - passworderr 是旧页面脚本识别的新密码格式错误码。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse
     */
    public function saveChangePassword(Request $request): JsonResponse
    {
        $userId = $this->validatedLegacyUserId($request);
        $password = (string) $request->input('password', $request->input('newPsw', ''));
        $confirmation = (string) $request->input('password_confirmation', $request->input('againpassword', ''));
        $code = trim((string) $request->input('codedata', $request->input('code', $request->input('userverfcode', ''))));

        if ($userId === null) {
            return $this->legacyFail('IDerror', 'userId');
        }

        $login = UserLogin::where('user_id', $userId)->first();
        if (!$login) {
            return $this->legacyFail('IDerror', 'userId');
        }
        if (!$login->isActive()) {
            return $this->legacyFail('UserDisable', 'userId');
        }

        $email = strtolower((string) $login->email);
        $payload = $this->resetCodePayload($email);
        if ($code === ''
            || !$payload
            || (int) $payload['user_id'] !== $userId
            || (string) $payload['email'] !== $email
            || (string) $payload['code'] !== $code) {
            return $this->legacyFail('errorCodedate', 'getVerifyCode');
        }
        if ($password === '' || strlen($password) < 6 || $confirmation !== $password) {
            return $this->legacyFail('passworderr', 'password');
        }

        if (!$this->passwordService->change($login, $password)) {
            return $this->legacyFail('neterr', 'nocol');
        }

        // 密码与 MT4 同步成功后才消费验证码，防止同一验证码再次用于修改密码。
        Cache::forget($this->resetCodeCacheKey($email));

        return $this->legacySuccess();
    }

    /**
     * 解析并校验旧前台提交的业务用户 ID。
     *
     * 依次兼容 userId/user_id/accountno 三个旧字段，任一非空且为不小于 1 的整数才返回；
     * 其余情况返回 null，调用方按 IDerror 处理，避免把非法输入当作合法用户继续查询。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return int|null 合法业务用户 ID；缺失或非法时为 null。
     */
    private function validatedLegacyUserId(Request $request): ?int
    {
        $rawUserId = $request->input('userId', $request->input('user_id', $request->input('accountno')));
        $validator = Validator::make(['user_id' => $rawUserId], [
            'user_id' => 'required|integer|min:1',
        ]);

        return $validator->fails() ? null : (int) $rawUserId;
    }

    /**
     * 读取指定邮箱绑定的验证码缓存载荷。
     *
     * 缓存必须同时包含 user_id、email、code 三个键才视为有效；
     * 结构不完整或缺失时返回 null，调用方按验证码无效处理，不继续密码修改流程。
     *
     * @param string $email 标准化（小写）后的登录邮箱。
     * @return array{user_id: int, email: string, code: string}|null 验证码缓存载荷；无效时为 null。
     */
    private function resetCodePayload(string $email): ?array
    {
        $payload = Cache::get($this->resetCodeCacheKey($email));
        if (!is_array($payload)
            || !isset($payload['user_id'], $payload['email'], $payload['code'])) {
            return null;
        }

        return $payload;
    }

    /**
     * 生成邮箱验证码缓存 key。
     *
     * 邮箱统一小写后再拼入 key，避免同一邮箱因大小写不同生成多个缓存条目。
     *
     * @param string $email 登录邮箱。
     * @return string Cache key，形如 front_reset_code:{email}。
     */
    private function resetCodeCacheKey(string $email): string
    {
        return 'front_reset_code:' . strtolower($email);
    }

    /**
     * 返回旧前台成功响应。
     *
     * 参数含义：
     * - msg=SUC 表示旧前台业务成功。
     * - err=noerr 表示没有错误码。
     * - col=nocol 表示没有需要高亮的表单字段。
     *
     * @return JsonResponse
     */
    private function legacySuccess(): JsonResponse
    {
        return response()->json([
            'msg' => 'SUC',
            'err' => 'noerr',
            'col' => 'nocol',
        ]);
    }

    /**
     * legacyFail 用于保留旧前台 msg/err/col 响应结构。
     *
     * 参数含义：
     * - $err：旧页面脚本识别的业务错误码，例如 IDerror、emailerror、errorCodedate。
     * - $col：旧页面脚本需要高亮或聚焦的表单字段名。
     *
     * @param string $err 旧前台错误码。
     * @param string $col 旧前台字段名。
     * @return JsonResponse
     */
    private function legacyFail(string $err, string $col): JsonResponse
    {
        return response()->json([
            'msg' => 'FAIL',
            'err' => $err,
            'col' => $col,
        ]);
    }
}
