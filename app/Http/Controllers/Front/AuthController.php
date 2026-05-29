<?php

namespace App\Http\Controllers\Front;

use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Models\UserAuth;
use App\Models\UserLoginLog;
use App\Services\JwtService;
use App\Services\UserRegistrationService;
use App\Services\FrontRegisterRuleService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Exception;

/**
 * Front User Authentication Controller
 * 前台用户认证控制器
 * 
 * Handles login, registration, logout, and token refresh for front-end users.
 * 处理前台用户的登录、注册、注销和令牌刷新。
 */
class AuthController extends FrontBaseController
{
    /**
     * @var UserRegistrationService
     */
    protected $registrationService;

    /**
     * @var JwtService
     */
    protected $jwtService;

    public function __construct(UserRegistrationService $registrationService, JwtService $jwtService)
    {
        $this->registrationService = $registrationService;
        $this->jwtService = $jwtService;
    }

    /**
     * Show login page
     * 显示登录页面
     *
     * @return \Illuminate\View\View
     */
    public function showLogin()
    {
        return view('front_layui::auth.login');
        // 第六次需求统一前台公开登录页为 Layui Blade 模板。
        return view('front_layui::auth.login');
    }

    /**
     * Show registration page
     * 显示注册页面
     *
     * @return \Illuminate\View\View
     */
    public function showRegister()
    {
        return view('front_layui::auth.register');
        // 第六次需求统一前台注册页为 Layui Blade 模板。
        return view('front_layui::auth.register');
    }

    /**
     * Process registration
     * 处理注册
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->merge($this->normalizedRegisterInput($request));

        $validator = Validator::make($request->all(), [
            'email'         => 'required|email|max:255',
            'password'      => 'required|string|min:6|confirmed',
            'user_name'     => 'required|string|max:100',
            'phone_code'    => 'required|string|max:10',
            'phone_number'  => 'required|string|max:30',
            'phone'         => 'required|string|max:50',
            'id_card_no'    => 'required|string|max:50',
            'gender'        => 'nullable|in:1,2',
            'account_type'  => 'required|in:1,2', // 1=agent, 2=customer
            'inviter_id'    => 'nullable|integer',
            'captcha_key'   => 'required|string|max:80',
            'captcha_code'  => 'required|string|max:10',
            'email_code'    => 'required|string|max:10',
            'agree_terms'   => 'accepted',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        if (!$this->verifyRegisterCaptcha($request)) {
            return $this->error('Invalid captcha', ResponseCode::VALIDATION_ERROR);
        }

        if (!$this->verifyRegisterEmailCode($request)) {
            return $this->error('Invalid email verification code', ResponseCode::VALIDATION_ERROR);
        }

        $parentId = $request->inviter_id ?: null;
        $accountType = (int) $request->account_type;
        $commissionMode = (string) $request->input('commission_mode', $request->input('comm_type', ''));

        $errors = $this->registrationService->validateRegistration($request->all(), $parentId, $accountType, $commissionMode);
        if (!empty($errors)) {
            return $this->error(reset($errors), ResponseCode::VALIDATION_ERROR);
        }

        $data = $request->only(['email', 'password', 'password_confirmation', 'user_name', 'phone', 'gender', 'id_card_no', 'address']);
        $data['ip'] = $request->ip();

        try {
            // COMPLETE registration with family_tree, commission rate from parent, group assignment, ID sequence generation
            // 完成注册，包括家族树构建、来自父级的佣金率、组分配、ID序列生成
            $result = $this->registrationService->register($data, $parentId, $accountType);
            if (isset($result['success']) && !$result['success']) {
                return $this->error($result['message'] ?? 'response.validation_failed', ResponseCode::VALIDATION_ERROR);
            }
            /** @var UserLogin $userLogin */
            $userLogin = $result['user_login'];

            // Generate JWT token (SSO is handled inside JwtService::generateToken)
            // 生成 JWT 令牌（SSO 在 JwtService::generateToken 内部处理）
            $token = $this->jwtService->generateToken([
                'sub'   => $userLogin->id,
                'guard' => 'user',
            ]);

            return $this->success([
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'expires_in'   => config('jwt.ttl') * 60,
                'user'         => [
                    'id'       => $userLogin->id,
                    'user_id'  => $userLogin->user_id,
                    'email'    => $userLogin->email,
                ],
            ], __('auth.register_success'));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::INTERNAL_ERROR);
        }
    }

    /**
     * User Login
     * 用户登录
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // 密码必填 | Password required
        $password = $request->input('password');
        if (!$password) {
            return $this->error(__('auth.password_required'), ResponseCode::VALIDATION_FAILED);
        }

        // 支持统一账号输入，自动判断 email 或 user_id | Auto-detect email or user_id from unified account input
        $account = trim((string) $request->input('account', ''));
        $email  = trim((string) $request->input('email', ''));
        $userId = trim((string) $request->input('user_id', ''));

        if ($account !== '') {
            if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
                $email = $account;
                $userId = '';
            } else {
                $userId = $account;
                $email = '';
            }
        }

        if ($email === '' && $userId === '') {
            return $this->error(__('auth.email_or_userid_required'), ResponseCode::VALIDATION_FAILED);
        }

        // 根据登录账号类型查找用户 | Find user by detected login account type
        $userLogin = null;
        if ($email !== '') {
            $userLogin = UserLogin::where('email', $email)->first();
        } elseif ($userId !== '') {
            $userLogin = UserLogin::where('user_id', $userId)->first();
        }

        if (!$userLogin || !Hash::check($request->password, $userLogin->password)) {
            return $this->error(__('auth.failed'), ResponseCode::INVALID_CREDENTIALS);
        }

        if (!$userLogin->isActive()) {
            return $this->error(__('auth.account_disabled'), ResponseCode::INVALID_CREDENTIALS);
        }

        // Generate JWT token and update SSO cache
        // 生成 JWT 令牌并更新 SSO 缓存
        $token = $this->jwtService->generateToken([
            'sub'   => $userLogin->id,
            'guard' => 'user',
        ]);

        // Update login info
        // 更新登录信息
        $userLogin->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        // Record login log
        // 记录登录日志
        UserLoginLog::create([
            'login_id'   => $userLogin->id,
            'user_id'    => $userLogin->user_id,
            'login_ip'   => $request->ip(),
            'ip_location'=> '',
            'user_agent' => $request->userAgent(),
        ]);

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user'         => [
                'id'       => $userLogin->id,
                'user_id'  => $userLogin->user_id,
                'email'    => $userLogin->email,
            ],
        ], __('auth.login_success'));
    }

    /**
     * User Logout
     * 用户退出
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $token = $request->attributes->get('jwt_token');
        if ($token) {
            // Invalidate current token and clear SSO cache
            // 使当前令牌失效并清除 SSO 缓存
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], __('auth.logout_success'));
    }

    /**
     * Refresh Token
     * 刷新令牌
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        $token = $request->attributes->get('jwt_token');
        if (!$token) {
            return $this->error(__('response.token_missing'), ResponseCode::TOKEN_MISSING);
        }
        try {
            $newToken = $this->jwtService->refreshToken($token);
            return $this->success(['access_token' => $newToken, 'token_type' => 'Bearer']);
        } catch (Exception $e) {
            return $this->error(__('response.token_expired'), ResponseCode::TOKEN_EXPIRED);
        }
    }

    /**
     * Change Password
     * 修改密码
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
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
            return $this->error('auth.old_password_error', ResponseCode::INTERNAL_ERROR);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Invalidate current token
        // 使当前令牌失效
        $token = $request->attributes->get('jwt_token');
        if ($token) {
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], 'auth.password_changed');
    }

    /**
     * 验证邀请人 | Validate inviter
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateInviter(Request $request, FrontRegisterRuleService $registerRules)
    {
        $inviterId = (int) $request->input('inviter_id', 0);
        $accountType = (int) $request->input('account_type', 2);
        $commissionMode = (string) $request->input('commission_mode', $request->input('comm_type', ''));

        $result = $registerRules->validate($inviterId, $accountType, $commissionMode);
        if (!$result['valid']) {
            return $this->success([
                'valid' => false,
                'message' => __($result['message']),
            ]);
        }

        return $this->success([
            'valid' => true,
            'inviter_name' => $result['inviter_name'],
            'account_type' => $result['account_type'],
            'message' => __($result['message']),
        ]);
    }

    /**
     * 检查邮箱是否已注册 | Check if email is registered
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEmail(Request $request)
    {
        $exists = UserLogin::where('email', $request->email)->exists();
        return $this->success(['exists' => $exists]);
    }

    public function registerCaptcha(Request $request)
    {
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->query('key', ''));
        if ($key === '') {
            $key = bin2hex(random_bytes(8));
        }

        $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5));
        Cache::put($this->registerCaptchaCacheKey($key), $code, now()->addMinutes(10));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="132" height="44" viewBox="0 0 132 44">'
            . '<rect width="132" height="44" fill="#f8fafc"/>'
            . '<path d="M6 12 C30 38, 60 4, 126 30" stroke="#cbd5e1" fill="none" stroke-width="2"/>'
            . '<path d="M10 32 C42 6, 78 42, 122 12" stroke="#dbeafe" fill="none" stroke-width="2"/>'
            . '<text x="18" y="30" font-family="Arial, sans-serif" font-size="22" font-weight="700" letter-spacing="4" fill="#1f2937">'
            . e($code)
            . '</text></svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function registerVerifyInfo(Request $request)
    {
        $request->merge($this->normalizedRegisterInput($request));

        $errors = [];
        if ($request->filled('email') && UserLogin::where('email', $request->input('email'))->exists()) {
            $errors['email'] = 'Email already registered';
        }
        if ($request->filled('phone') && UserInfo::where('phone', $request->input('phone'))->exists()) {
            $errors['phone'] = 'Phone already registered';
        }
        if ($request->filled('id_card_no') && UserAuth::where('id_card_no', $request->input('id_card_no'))->exists()) {
            $errors['id_card_no'] = 'ID card already registered';
        }

        $parentId = $request->input('inviter_id');
        if ($parentId !== null && $parentId !== '') {
            $accountType = (int) $request->input('account_type', 2);
            $commissionMode = (string) $request->input('commission_mode', $request->input('comm_type', ''));
            $rule = app(FrontRegisterRuleService::class)->validate((int) $parentId, $accountType, $commissionMode);
            if (!$rule['valid']) {
                $errors['inviter_id'] = __($rule['message']);
            }
        }

        if ($errors) {
            return $this->error(reset($errors), ResponseCode::VALIDATION_ERROR, ['errors' => $errors]);
        }

        return $this->success(['valid' => true], 'response.success');
    }

    public function registerSendCode(Request $request)
    {
        $request->merge($this->normalizedRegisterInput($request));

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'phone_code' => 'required|string|max:10',
            'phone_number' => 'required|string|max:30',
            'id_card_no' => 'required|string|max:50',
            'inviter_id' => 'nullable|integer',
            'account_type' => 'required|in:1,2',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $precheck = $this->registerVerifyInfo($request)->getData(true);
        if (($precheck['code'] ?? ResponseCode::ERROR) !== ResponseCode::SUCCESS) {
            return $this->error($precheck['message'] ?? 'Validation failed', ResponseCode::VALIDATION_ERROR, $precheck['data'] ?? []);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $rateKey = 'front_register_email_code_rate_' . sha1($email . '|' . $request->ip());
        if (!Cache::add($rateKey, 1, now()->addSeconds(60))) {
            return $this->error('Please request the email code later', ResponseCode::RATE_LIMITED);
        }

        $code = (string) random_int(123456, 999999);

        try {
            Mail::raw('Your registration verification code is: ' . $code, function ($message) use ($email) {
                $message->to($email)->subject('Registration verification code');
            });
        } catch (Exception $e) {
            Cache::forget($rateKey);
            return $this->error('Email send failed', ResponseCode::EMAIL_SEND_FAILED);
        }

        Cache::put($this->registerEmailCodeCacheKey($email), [
            'email' => $email,
            'code' => $code,
        ], now()->addMinutes(10));

        return $this->success(['sent' => true], 'response.success');
    }

    private function normalizedRegisterInput(Request $request): array
    {
        $email = strtolower(trim((string) $request->input('email', $request->input('useremail', ''))));
        $phoneCode = trim((string) $request->input('phone_code', $request->input('modules', '')));
        $phoneNumber = trim((string) $request->input('phone_number', $request->input('userphoneNo', '')));
        $phone = $phoneCode !== '' && $phoneNumber !== '' ? $phoneCode . '-' . $phoneNumber : trim((string) $request->input('phone', ''));

        return [
            'email' => $email,
            'user_name' => trim((string) $request->input('user_name', $request->input('username', ''))),
            'phone_code' => $phoneCode,
            'phone_number' => $phoneNumber,
            'phone' => $phone,
            'id_card_no' => trim((string) $request->input('id_card_no', $request->input('userIdcardNo', ''))),
            'captcha_code' => trim((string) $request->input('captcha_code', $request->input('reguserverfcode', ''))),
            'email_code' => trim((string) $request->input('email_code', $request->input('userverfcode', ''))),
        ];
    }

    private function verifyRegisterCaptcha(Request $request): bool
    {
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->input('captcha_key', ''));
        $expected = Cache::pull($this->registerCaptchaCacheKey($key));

        return $expected && strtoupper((string) $expected) === strtoupper(trim((string) $request->input('captcha_code')));
    }

    private function verifyRegisterEmailCode(Request $request): bool
    {
        $email = strtolower(trim((string) $request->input('email')));
        $payload = Cache::get($this->registerEmailCodeCacheKey($email));

        if (!$payload || !is_array($payload)) {
            return false;
        }
        if (($payload['email'] ?? '') !== $email) {
            return false;
        }
        if ((string) ($payload['code'] ?? '') !== trim((string) $request->input('email_code'))) {
            return false;
        }

        Cache::forget($this->registerEmailCodeCacheKey($email));
        return true;
    }

    private function registerCaptchaCacheKey(string $key): string
    {
        return 'front_register_captcha_' . sha1($key);
    }

    private function registerEmailCodeCacheKey(string $email): string
    {
        return 'front_register_email_code_' . sha1(strtolower($email));
    }
}
