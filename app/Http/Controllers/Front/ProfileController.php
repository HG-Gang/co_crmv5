<?php

namespace App\Http\Controllers\Front;

use App\Models\UserInfo;
use App\Models\UserAuth;
use App\Models\UserLogin;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

/**
 * Front User Profile Controller
 * 前台用户资料控制器
 * 
 * Handles profile information, updates, password/email changes, and avatar uploads.
 * 处理资料信息、更新、密码/邮箱更改和头像上传。
 */
class ProfileController extends FrontBaseController
{
    /**
     * Get current user profile info
     * 获取当前用户资料信息
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function profileInfo(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        if (!$userLogin) {
            return $this->error(__('auth.unauthorized'), ResponseCode::INVALID_CREDENTIALS);
        }

        $userInfo = $userLogin->userInfo;
        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::INTERNAL_ERROR);
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
            $authPayload['bank_card_img_tmp_url'] = $this->resolveFileUrl($userAuth->bank_card_img_tmp ?? '');
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
     * Update user profile
     * 更新用户资料
     * 
     * @param Request $request
     * @return JsonResponse
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
     * Change Password
     * 修改密码
     * 
     * @param Request $request
     * @return JsonResponse
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

        if (!Hash::check($request->old_password, $user->password)) {
            return $this->error('auth.old_password_error', ResponseCode::OLD_PASSWORD_WRONG);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return $this->success([], 'auth.password_changed', ResponseCode::UPDATED);
    }

    /**
     * Change Email
     * 修改邮箱
     * 
     * @param Request $request
     * @return JsonResponse
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

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }
        if (trim((string) $request->input('verify_phone')) !== (string) $userInfo->phone
            || strtolower(trim((string) $request->input('current_email'))) !== strtolower((string) $userLogin->email)) {
            return $this->error(__('profile.email_verify_failed'), ResponseCode::VALIDATION_FAILED);
        }
        
        $userLogin->update(['email' => $request->new_email]);

        return $this->success([], __('response.updated'));
    }

    /**
     * Upload Avatar
     * 上传头像
     * 
     * @param Request $request
     * @return JsonResponse
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
            // Delete old avatar
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

    public function submitBankCard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'bank_no' => 'required|string|max:50',
            'bank_addr' => 'required|string|max:500',
            'bank_card_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
            'bank_card_back_img' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $userInfo = $request->user('user')->userInfo;
        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $path = $this->storeProfileFile($request, 'bank_card_img', 'auth/' . $userInfo->user_id . '/bank');
        $backPath = $request->hasFile('bank_card_back_img')
            ? $this->storeProfileFile($request, 'bank_card_back_img', 'auth/' . $userInfo->user_id . '/bank')
            : '';

        UserAuth::updateOrCreate(
            ['user_id' => $userInfo->user_id],
            [
                'bank_name' => trim((string) $request->input('bank_name')),
                'bank_no' => trim((string) $request->input('bank_no')),
                'bank_addr' => trim((string) $request->input('bank_addr')),
                'bank_card_img' => $backPath ? $path . '|' . $backPath : $path,
                'bank_status' => 1,
                'bank_remarks' => '',
            ]
        );

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

    public function submitBankChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verify_phone' => 'required|string',
            'verify_email' => 'required|email',
            'bank_name' => 'required|string|max:255',
            'bank_no' => 'required|string|max:50',
            'bank_addr' => 'required|string|max:500',
            'bank_card_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
            'bank_card_back_img' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        [$userLogin, $userInfo] = $this->verifiedContactUser($request);
        if (!$userInfo) {
            return $this->error(__('profile.email_verify_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $path = $this->storeProfileFile($request, 'bank_card_img', 'auth/' . $userInfo->user_id . '/bank-change');
        $backPath = $request->hasFile('bank_card_back_img')
            ? $this->storeProfileFile($request, 'bank_card_back_img', 'auth/' . $userInfo->user_id . '/bank-change')
            : '';

        UserAuth::updateOrCreate(
            ['user_id' => $userInfo->user_id],
            [
                'bank_name_tmp' => trim((string) $request->input('bank_name')),
                'bank_no_tmp' => trim((string) $request->input('bank_no')),
                'bank_addr_tmp' => trim((string) $request->input('bank_addr')),
                'bank_card_img_tmp' => $backPath ? $path . '|' . $backPath : $path,
                'bank_status' => 3,
                'bank_remarks' => '',
            ]
        );

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

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

        [$userLogin, $userInfo] = $this->verifiedContactUser($request);
        if (!$userInfo) {
            return $this->error(__('profile.email_verify_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $userInfo->update(['phone' => trim((string) $request->input('new_phone'))]);

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * Resolve avatar browser URL
     * 统一头像 URL 规则：外链和绝对路径原样返回，storage 相对路径转成公开 URL，空值使用默认头像。
     *
     * @param string|null $avatar
     * @return string
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

    private function resolveFileUrl($path): string
    {
        if (!$path) {
            return '';
        }

        $firstPath = explode('|', (string) $path)[0] ?? '';

        return $this->resolveAvatarUrl($firstPath);
    }

    private function storeProfileFile(Request $request, string $field, string $directory): string
    {
        $path = $request->file($field)->store($directory, 'public');
        $this->mirrorPublicDiskFile($path);

        return $path;
    }

    private function verifiedContactUser(Request $request): array
    {
        /** @var UserLogin $userLogin */
        $userLogin = $request->user('user');
        $userInfo = $userLogin ? $userLogin->userInfo : null;
        if (!$userInfo) {
            return [$userLogin, null];
        }

        $phoneMatches = trim((string) $request->input('verify_phone')) === (string) $userInfo->phone;
        $emailMatches = strtolower(trim((string) $request->input('verify_email'))) === strtolower((string) $userLogin->email);

        return [$userLogin, ($phoneMatches && $emailMatches) ? $userInfo : null];
    }

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

    private function maskPhone(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) >= 7
            ? substr($value, 0, 3) . '****' . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

    private function maskEmail(string $value): string
    {
        if ($value === '' || strpos($value, '@') === false) {
            return $value;
        }

        [$name, $domain] = explode('@', $value, 2);
        $visible = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $visible . '***@' . $domain;
    }

    private function maskIdCard(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) > 8
            ? substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

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
