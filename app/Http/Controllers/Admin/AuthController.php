<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/10
 * Time: 17:34
 */

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use App\Models\AdminLoginLog;
use App\Services\JwtService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Admin Authentication Controller
 * 后台管理员认证控制器。
 *
 * 功能逻辑说明：
 * - 本控制器承载 admin_api_login、admin_api_logout、admin_api_refreshToken、admin_api_profileInfo、
 *   admin_api_updateProfile、admin_api_changePassword、admin_api_uploadAvatar 等后台认证基础接口。
 * - 登录接口不进入 check.permission:admin；登录后的基础资料、改密、头像和刷新 Token 接口属于白名单能力，
 *   仍需要通过 jwt.auth:admin 与 sso:admin 确认当前管理员身份。
 * - 业务权限控制继续交给 check.permission:admin 和 permissions.api_route；本控制器只处理“是谁”和“当前 Token 是否有效”。
 * - admin_api_login 负责登录签发 Token；admin_api_refreshToken 负责使用当前有效 Token 换取新 Token。
 * - username 表示后台管理员登录名，password 表示后台管理员登录密码，sub 表示 admins.id，guard 固定为 admin。
 * - AdminLoginLog 记录登录审计信息；jwt_token 表示当前请求解析出的后台 JWT。
 * - profileInfo 返回当前登录管理员资料；email 表示管理员邮箱，mobile 表示管理员手机号。
 * - old_password 表示当前旧密码，password_confirmation 表示新密码确认值，修改密码成功后使当前 Token 失效。
 * - avatar 表示上传的管理员头像文件；登录后接口仍由 jwt.auth:admin、sso:admin 和 check.permission:admin 明确边界。
 *
 * 文件功能：
 * - 前后端分离后台认证：登录签发 JWT、登出/改密使当前 Token 失效、刷新 Token、查询与更新本人资料、上传头像。
 * - 输入 username/email + password、jwt_token、旧密码与新密码等；输出 access_token/token_type/expires_in 与管理员资料。
 *
 * 适用场景：
 * - 后台 API 登录入口 admin_api_login 与登录后的基础白名单接口；身份由 jwt.auth:admin、sso:admin 确认。
 *
 * 安全边界：
 * - 账号不存在与密码错误统一返回 invalid_credentials，不向调用方泄漏账号是否存在（防枚举）。
 * - 旧入口验证码错误不会进入账号查询与 JWT 签发链；验证码一次性消费，成功后立即失效。
 * - 改密成功后立即失效当前 Token，强制重新登录；密码字段不写入日志与响应。
 * - 本控制器不记录、不输出任何真实 Token 或密钥值。
 */
class AuthController extends AdminBaseController
{
    /**
     * JWT 服务实例。
     *
     * 参数逻辑说明：
     * - JwtService 负责生成、刷新、失效后台 JWT，JWT 中的 sub 表示 admins.id，guard 固定为 admin。
     *
     * @var JwtService
     */
    protected $jwtService;

    /**
     * 构造后台认证控制器。
     *
     * @param JwtService $jwtService JWT 服务对象，用于登录签发 Token、登出/改密失效 Token、刷新 Token。
     */
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * 显示后台登录页。
     *
     * 逻辑说明：
     * - 当前主后台页面路由位于 routes/web.php 的 `/admin/login`，API 登录入口是 admin_api_login。
     * - 此方法保留给旧控制器调用路径使用，但仍必须返回 `admin_layui::auth.login` 现代 Blade 登录页。
     *
     * @return \Illuminate\View\View
     */
    public function showLogin()
    {
        return view("admin_layui::auth.login");
    }

    /**
     * 后台管理员登录。
     *
     * 参数逻辑说明：
     * - username 表示后台管理员登录账号，可传 admins.username 或 admins.email。
     * - password 表示后台管理员登录密码，只用于 Hash::check 校验，不写入日志或响应。
     * - 登录成功后生成 JWT，sub 表示 admins.id，guard 固定为 admin。
     * - AdminLoginLog 记录登录审计信息，包括管理员 ID、登录 IP 和 User-Agent。
     *
     * @param Request $request HTTP 请求对象，承载 username、password、客户端 IP 和 User-Agent。
     * @return \Illuminate\Http\JsonResponse 登录成功返回 access_token、token_type、expires_in 和基础管理员信息。
     */
    public function login(Request $request)
    {
        $legacyLogin = $this->isLegacyLoginRequest($request);

        // 旧后台使用 loginUid/loginPassword/cptcode；先归一化字段，确保兼容请求不会
        // 因现代 API 的 username/password 命名差异而被错误判定为缺少参数。
        if ($legacyLogin && !$this->verifyLegacyLoginCaptcha($request)) {
            return $this->error('auth.invalid_captcha', ResponseCode::VALIDATION_FAILED);
        }

        $loginInput = $legacyLogin ? [
            'username' => trim((string) $this->legacyLoginInput($request, 'loginUid')),
            'password' => (string) $this->legacyLoginInput($request, 'loginPassword'),
        ] : $request->all();

        $validator = Validator::make($loginInput, [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        // 现代 Blade 以 username 承载 email；旧入口已在上方归一化 loginUid。
        $account = (string) $request->username;
        if ($legacyLogin) {
            $account = (string) $loginInput['username'];
        }
        $admin = Admin::where('username', $account)
            ->orWhere('email', $account)
            ->first();

        // 账号不存在与密码错误走同一错误文案：不泄漏账号是否存在，避免攻击者枚举有效账号。
        if (!$admin || !Hash::check($loginInput['password'], $admin->password)) {
            return $this->error(__('admin.invalid_credentials'), ResponseCode::AUTH_FAILED);
        }

        // 禁用账号不允许登录，与凭据错误共用 AUTH_FAILED 语义，不单独提示禁用状态。
        if (method_exists($admin, 'isActive') && !$admin->isActive()) {
            return $this->error(__('admin.account_disabled'), ResponseCode::AUTH_FAILED);
        }

        $token = $this->jwtService->generateToken([
            'sub'   => $admin->id,
            'guard' => 'admin',
        ]);

        $admin->update([
            'login_count' => ((int) $admin->login_count) + 1,
            'last_login_ip' => $request->ip(),
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        AdminLoginLog::create([
            'admin_id'   => $admin->id,
            'login_ip'   => $request->ip(),
            'ip_address' => '',
            'user_agent' => $request->userAgent(),
        ]);

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user'         => [
                'id'       => $admin->id,
                'username' => $admin->username,
            ],
        ], __('admin.login_successful'));
    }

    /**
     * 判断请求是否来自旧后台登录入口。
     *
     * 旧 `LegacyAdminController` 会写入 `X-Legacy-Admin-Route`，直接提交旧字段时
     * 也保留字段特征作为兜底；现代 `/api/admin/login` 请求不会进入旧验证码分支。
     *
     * @param Request $request 当前登录请求。
     * @return bool true 表示需要按旧字段和旧会话验证码处理。
     */
    public function isLegacyLoginRequest(Request $request): bool
    {
        return $request->headers->get('X-Legacy-Admin-Route') === 'index/admin/logon'
            || $request->hasAny(['loginUid', 'loginPassword', 'cptcode']);
    }

    /**
     * 读取旧登录字段。
     *
     * 兼容控制器把旧请求转发为内部子请求时会保留原始 `Content-Type`；对于 JSON
     * 请求，Laravel 的 input() 会优先读取空 JSON body，而转发参数实际位于 Symfony
     * request bag。这里先读 request bag，再回退到标准 input()，同时兼容表单和 JSON。
     *
     * @param Request $request 当前内部登录请求。
     * @param string $key 旧字段名。
     * @return mixed 旧请求字段值；缺失时返回空字符串。
     */
    private function legacyLoginInput(Request $request, string $key)
    {
        if ($request->request->has($key)) {
            return $request->request->get($key);
        }

        return $request->input($key, '');
    }

    /**
     * 校验旧后台图形验证码并在成功后消费会话值。
     *
     * `LegacyAdminController::captcha` 将明文验证码写入
     * `legacy_admin_captcha_code`；兼容转发是嵌套请求，优先从请求 Session 读取，
     * 再从当前应用 Session 管理器读取，确保旧入口和 Feature 测试都使用同一会话。
     * 错误验证码不会进入账号查询或 JWT 签发链，成功验证码只允许使用一次。
     *
     * @param Request $request 当前登录请求，承载旧字段 `cptcode`。
     * @return bool 验证码匹配返回 true，否则返回 false。
     */
    private function verifyLegacyLoginCaptcha(Request $request): bool
    {
        $submitted = strtoupper(trim((string) $this->legacyLoginInput($request, 'cptcode')));
        if ($submitted === '') {
            return false;
        }

        $session = null;
        if ($request->hasSession()) {
            $session = $request->session();
        } elseif (function_exists('session')) {
            $session = session();
        }

        if (!$session) {
            return false;
        }

        // 旧项目 Captcha::create/check 使用 captcha Session 与 captcha_<md5(hash)> Cache。
        // 先走真实组件，确保验证码有有效期且只能消费一次；自定义键仅保留给迁移期旧会话。
        if ($session->has('captcha')) {
            return app('captcha')->check($submitted);
        }

        $expected = strtoupper(trim((string) $session->get('legacy_admin_captcha_code', '')));
        $matched = $expected !== '' && hash_equals($expected, $submitted);

        // 旧 Captcha::check 在成功后移除 Session 值，兼容实现保持一次性消费语义。
        if ($matched) {
            $session->forget('legacy_admin_captcha_code');
        }

        return $matched;
    }

    /**
     * 后台管理员退出登录。
     *
     * 参数逻辑说明：
     * - jwt_token 表示当前请求解析出的后台 JWT，由 jwt.auth:admin 中间件写入 request attributes。
     * - 若存在 jwt_token，则调用 JwtService::invalidateToken 使当前 Token 立即失效。
     *
     * @param Request $request HTTP 请求对象，用于读取 jwt_token。
     * @return \Illuminate\Http\JsonResponse 退出成功响应。
     */
    public function logout(Request $request)
    {
        $token = $request->attributes->get('jwt_token');
        if ($token) {
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], __('admin.logout_successful'));
    }

    /**
     * 读取当前登录管理员资料。
     *
     * 逻辑说明：
     * - profileInfo 返回当前登录管理员资料，数据来源为 jwt.auth:admin 解析后的 request user('admin')。
     * - 该接口是后台基础白名单接口，只返回当前管理员，不允许通过参数读取其他管理员。
     *
     * @param Request $request HTTP 请求对象，用于读取当前 admin guard 用户。
     * @return \Illuminate\Http\JsonResponse 当前管理员资料响应。
     */
    public function profileInfo(Request $request)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('admin.not_logged_in'), ResponseCode::AUTH_FAILED);
        }
        return $this->success($admin, __('admin.profile_fetched'));
    }

    /**
     * 更新当前登录管理员资料。
     *
     * 参数逻辑说明：
     * - email 表示管理员邮箱，允许为空，但传入时必须符合邮箱格式且不超过 100 个字符。
     * - mobile 表示管理员手机号，允许为空，最大长度 20 个字符。
     * - username、password、role_id 等敏感字段不允许通过本接口更新。
     *
     * @param Request $request HTTP 请求对象，承载 email、mobile 和当前 admin guard 用户。
     * @return \Illuminate\Http\JsonResponse 更新后的当前管理员资料。
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('admin.not_logged_in'), ResponseCode::AUTH_FAILED);
        }

        $validator = Validator::make($request->all(), [
            'email'  => 'nullable|email|max:100|unique:admins,email,' . $admin->id,
            'mobile' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $updateData = $request->only(['email', 'mobile']);
        if (!empty($updateData)) {
            $admin->update($updateData);
        }

        return $this->success($admin->fresh(), __('admin.profile_updated'));
    }

    /**
     * 修改当前登录管理员密码。
     *
     * 参数逻辑说明：
     * - old_password 表示当前旧密码，必须先通过 Hash::check 与 admins.password 比对。
     * - password 表示新密码，至少 6 位，并使用 Laravel confirmed 规则要求 password_confirmation 一致。
     * - password_confirmation 表示新密码确认值，只参与校验，不写入数据库。
     * - 修改密码成功后使当前 Token 失效，要求管理员重新登录，降低旧 Token 继续使用的风险。
     *
     * @param Request $request HTTP 请求对象，承载 old_password、password、password_confirmation 和 jwt_token。
     * @return \Illuminate\Http\JsonResponse 密码修改结果响应。
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $admin = $request->user('admin');

        if (!Hash::check($request->old_password, $admin->password)) {
            return $this->error(__('response.old_password_wrong'), ResponseCode::OLD_PASSWORD_WRONG);
        }

        $admin->update(['password' => Hash::make($request->password)]);

        // 改密成功后使当前 Token 立即失效，旧 Token 不能再访问后台接口。
        $token = $request->attributes->get('jwt_token');
        if ($token) {
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], __('admin.password_changed'));
    }

    /**
     * 上传当前登录管理员头像。
     *
     * 参数逻辑说明：
     * - avatar 表示上传的管理员头像文件，必须是 jpeg、png、jpg、gif 图片，大小不超过 2048KB。
     * - 文件保存到 public/admin/avatars，响应返回 Storage::url 生成的访问地址。
     * - 当前逻辑只返回上传 URL，是否写入 admins.avatar 需要后续按真实字段再开启。
     *
     * @param Request $request HTTP 请求对象，承载 avatar 文件和当前 admin guard 用户。
     * @return \Illuminate\Http\JsonResponse 上传成功返回头像 URL。
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('admin.not_logged_in'), ResponseCode::AUTH_FAILED);
        }

        if ($request->file('avatar')) {
            $path = $request->file('avatar')->store('public/admin/avatars');
            $url = Storage::url($path);
            
            // $admin->update(['avatar' => $url]);
            
            return $this->success(['url' => $url], __('admin.avatar_uploaded'));
        }

        return $this->error(__('admin.upload_failed'), ResponseCode::INTERNAL_ERROR);
    }

    /**
     * 刷新后台 JWT。
     *
     * 参数逻辑说明：
     * - refreshToken 使用当前有效 Token 换取新 Token。
     * - jwt_token 表示当前请求解析出的后台 JWT；缺失时返回 TOKEN_MISSING，过期或刷新失败时返回 TOKEN_EXPIRED。
     * - 新 Token 继续使用 Bearer 类型，供后台 Blade + JS 后续请求写入 Authorization 请求头。
     *
     * @param Request $request HTTP 请求对象，用于读取 jwt_token。
     * @return \Illuminate\Http\JsonResponse 刷新成功返回新的 access_token 和 token_type。
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
            // 刷新失败统一按过期处理，不向调用方区分 token 无效、过期或服务端异常的具体原因。
            return $this->error(__('response.token_expired'), ResponseCode::TOKEN_EXPIRED);
        }
    }
}
