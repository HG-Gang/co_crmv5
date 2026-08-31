<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:25
 */

namespace App\Http\Controllers\Front;

use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Models\UserAuth;
use App\Models\UserLoginLog;
use App\Mail\FrontRegistrationSuccessNotification;
use App\Mail\FrontRegistrationVerificationCode;
use App\Services\JwtService;
use App\Services\UserRegistrationService;
use App\Services\FrontRegisterRuleService;
use App\Services\UserPasswordService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

/**
 * 前台用户认证控制器。
 *
 * 文件功能：
 * - 处理前台用户登录、注册、注销、令牌刷新和旧前台登录/注册接口兼容。
 * - 新版 Layui 页面通过 JSON 接口获取 JWT；旧页面兼容接口继续返回历史 loginStatus 字段，避免旧模板提交逻辑失效。
 * - 注册图形验证码、登录密码和账号状态都在后端再次校验；邮箱验证码发送接口仅作旧入口兼容。
 *
 * 安全边界：
 * - 图形验证码写入 Cache，注册成功后才消费；邮箱验证码缓存不参与注册主链路。
 * - 同一邮箱注册先取 Cache 锁互斥，user_logins.email 唯一索引仍是并发写入的最终防线。
 * - 登录与旧登录接口对账号不存在、密码错误返回统一失败文案，不向未认证请求泄漏账号存在性。
 * - 密码只参与 Hash::check，不写入响应、日志或异常消息；异常日志只记录邮箱哈希。
 * - 修改密码、退出登录都会使当前 JWT 进入黑名单；刷新只换发新令牌并作废旧 token，超窗必须重新登录。
 * - 令牌签发、SSO 缓存与刷新窗口由 JwtService 统一维护，sub 固定对应 user_logins.id。
 */
class AuthController extends FrontBaseController
{
    /**
     * 同一邮箱注册互斥锁的 TTL（秒），固定 120。注册落库（家族树、代理关系、佣金规则）通常远小于 2 分钟，
     * 该窗口足以覆盖一次完整注册请求；锁过期后唯一索引 user_logins.email 仍是并发写入的最终防线，
     * 过短会削弱互斥、过长则让被阻塞的正常重试等待过久。
     *
     * @var int
     */
    private const REGISTER_LOCK_TTL_SECONDS = 120;

    /**
     * @var int PHONE_NUMBER_MIN_LENGTH 注册手机号最小位数，11 位可通过，10 位被拒绝。
     *
     * 中国大陆手机号本地部分固定 11 位，因此 11 是允许的下边界；国家区号由 phone_code 单独提交，不占用本字段长度。
     */
    private const PHONE_NUMBER_MIN_LENGTH = 11;

    /**
     * @var int PHONE_NUMBER_MAX_LENGTH 注册手机号最大位数，20 位可通过，21 位被拒绝。
     *
     * 该上限同时写入 Blade 的 maxlength 与前端 JS 正则，保证浏览器能输入并完整显示长于 11 位的国际号码。
     */
    private const PHONE_NUMBER_MAX_LENGTH = 20;

    /**
     * 注册服务实例。
     *
     * 参数逻辑说明：
     * - registrationService 表示注册服务，负责用户注册时的家族树、父级代理、佣金规则、组别和用户编号生成。
     *
     * @var UserRegistrationService
     */
    protected $registrationService;

    /**
     * JWT 服务实例。
     *
     * 参数逻辑说明：
     * - jwtService 表示 JWT 服务，负责前台 user guard 的令牌签发、刷新、失效和单点登录缓存同步。
     *
     * @var JwtService
     */
    protected $jwtService;

    /**
     * 密码服务：负责前台注册时的密码哈希落库与 MT4 登录端密码同步。
     * 登录/注册链路中本地与 MT4 双侧密码必须一致；缺失时注册只能落本地库，
     * 用户随后将无法登录 MT4 交易端，属静默数据不一致。
     *
     * @var UserPasswordService
     */
    protected $passwordService;

    /**
     * 构造前台认证控制器。
     *
     * @param UserRegistrationService $registrationService 注册服务，处理真实注册落库和注册链路规则。
     * @param JwtService $jwtService JWT 服务，处理登录后的 access_token 和 SSO 状态。
     */
    public function __construct(
        UserRegistrationService $registrationService,
        JwtService $jwtService,
        UserPasswordService $passwordService
    )
    {
        $this->registrationService = $registrationService;
        $this->jwtService = $jwtService;
        $this->passwordService = $passwordService;
    }

    /**
     * 渲染前台登录页。
     *
     * 逻辑说明：
     * - showLogin 用于渲染前台 Layui 登录页。
     * - 页面只负责展示表单，登录校验统一提交到 login() 或 legacySignIn()。
     *
     * @return \Illuminate\View\View
     */
    public function showLogin()
    {
        return view('front_layui::auth.login_v2');
    }

    /**
     * 渲染前台注册页。
     *
     * 逻辑说明：
     * - showRegister 用于渲染前台 Layui 注册页。
     * - 邀请人、账户类型和返佣模式兼容参数由 legacyRegisterPage() 注入。
     *
     * @return \Illuminate\View\View
     */
    public function showRegister()
    {
        return view('front_layui::auth.register_v2');
    }

    /**
     * 兼容旧前台注册链接。
     *
     * 参数逻辑说明：
     * - legacyRegisterPage 用于兼容旧前台注册链接。
     * - registerType 为旧路由中的注册类型，值为 agents 时 account_type=1 表示代理，否则 account_type=2 表示普通客户。
     * - userId 表示邀请人用户编号，会注入注册页 inviterId。
     * - commType 表示旧页面传入的返佣模式，会注入注册页 legacyCommissionMode。
     *
     * @param Request $request HTTP 请求对象，承载 query.user_id、query.comm_type 等旧页面兼容参数。
     * @param string|null $registerType 旧路由注册类型，agents=代理注册，其他值按普通客户注册处理。
     * @param int|string|null $userId 旧路由邀请人编号。
     * @param string|null $commType 旧路由返佣模式。
     * @return \Illuminate\View\View
     */
    public function legacyRegisterPage(Request $request, $registerType = null, $userId = null, $commType = null)
    {
        $accountType = $registerType === 'agents' ? 1 : 2;

        return view('front_layui::auth.register_v2', [
            'inviterId' => $userId ?: $request->query('user_id', ''),
            'legacyRegisterType' => $registerType,
            'legacyAccountType' => $accountType,
            'legacyCommissionMode' => $commType ?: $request->query('comm_type', ''),
        ]);
    }

    /**
     * 处理前台注册。
     *
     * 参数逻辑说明：
     * - register 用于处理前台注册。
     * - account_type=1 表示代理，account_type=2 表示普通客户。
     * - captcha_key 表示图形验证码缓存键，用于定位 registerCaptcha() 写入的验证码。
     * - captcha_code 表示用户输入的图形验证码，必须与缓存中的验证码一致。
     * - email_code 表示邮箱验证码；兼容字段会被标准化，但当前注册主链路不依赖它。
     * - 注册主链路只校验图形验证码，不依赖邮箱验证码。
     * - inviter_id 表示邀请人用户编号，注册服务会据此校验上下级和返佣规则。
     *
     * @param Request $request HTTP 请求对象，承载注册表单、验证码、邀请人和账户类型参数。
     * @return \Illuminate\Http\JsonResponse 注册成功返回 access_token 和前台用户基础信息。
     */
    public function register(Request $request)
    {
        // 新旧表单字段先归一化，统一交给同一套校验规则，避免旧页面字段缺失导致校验口径分裂。
        $request->merge($this->normalizedRegisterInput($request));

        $validator = Validator::make($request->all(), [
            'email'         => 'required|email|max:255',
            'password'      => ['required', 'string', 'min:6', 'confirmed', 'regex:/^[a-zA-Z][\s\S]*\d$/'],
            'user_name'     => 'required|string|max:100',
            'phone_code'    => 'required|string|max:10',
            // phone_number 与前端 Blade/JS 共用同一口径：纯数字 11-20 位，允许长于 11 位的国际号码。
            'phone_number'  => ['required', 'string', 'min:' . self::PHONE_NUMBER_MIN_LENGTH, 'max:' . self::PHONE_NUMBER_MAX_LENGTH, 'regex:/^[0-9]+$/'],
            'phone'         => 'required|string|max:50',
            'id_card_no'    => 'required|string|max:50',
            'gender'        => 'nullable|in:1,2',
            'account_type'  => 'required|in:1,2', // account_type=1 表示代理，account_type=2 表示普通客户。
            'inviter_id'    => 'nullable|integer',
            'captcha_key'   => 'required|string|max:80',
            'captcha_code'  => 'required|string|max:10',
            'agree_terms'   => 'accepted',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $parentId = $request->inviter_id ?: null;
        $accountType = (int) $request->account_type;
        $commissionMode = (string) $request->input(
            'commission_mode',
            $request->routeIs('legacy_user_register_into') ? $request->input('comm_type', '') : ''
        );

        // 缓存锁只提供同邮箱注册的前置互斥；user_logins.email 唯一索引仍是并发写入的最终防线。
        $lockKey = 'front_register_submit_lock_' . sha1(strtolower((string) $request->input('email')));
        $lock = Cache::lock($lockKey, self::REGISTER_LOCK_TTL_SECONDS);
        if (!$lock->get()) {
            return $this->error('response.rate_limited', ResponseCode::RATE_LIMITED);
        }
        $registered = false;

        try {
            // 生产注册只校验图形验证码；邮箱验证码接口保留兼容，但不参与注册主链路。
            if (!$this->verifyRegisterCaptcha($request)) {
                return $this->error('auth.invalid_captcha', ResponseCode::VALIDATION_ERROR);
            }

            // 注册规则校验（邀请人关系、返佣模式、资料唯一性）通过后才允许真实落库。
            $errors = $this->registrationService->validateRegistration($request->all(), $parentId, $accountType, $commissionMode);
            if (!empty($errors)) {
                return $this->error(reset($errors), ResponseCode::VALIDATION_ERROR);
            }

            // 只取白名单字段参与注册，请求中的其他参数不进入落库链路。
            $data = $request->only(['email', 'password', 'password_confirmation', 'user_name', 'phone', 'gender', 'id_card_no', 'address']);
            $data['commission_mode'] = $commissionMode;
            $data['ip'] = $request->ip();
            // 完成注册，包括家族树构建、来自父级的佣金率、组分配和用户编号序列生成。
            $result = $this->registrationService->register($data, $parentId, $accountType);
            if (!is_array($result)) {
                throw new \UnexpectedValueException('Registration service returned a non-array result.');
            }
            $registered = ($result['registered'] ?? false) === true;
            if ($registered) {
                $this->consumeRegisterCaptcha($request);
            } elseif (($result['success'] ?? null) === false) {
                return $this->error($result['message'] ?? 'response.validation_failed', ResponseCode::VALIDATION_ERROR);
            } else {
                throw new \UnexpectedValueException('Registration service did not explicitly confirm local registration.');
            }

            $provisioningStatus = $result['provisioning_status'] ?? null;
            if (!is_string($provisioningStatus) || trim($provisioningStatus) === '') {
                throw new \UnexpectedValueException('Registration service returned an invalid provisioning status.');
            }
            if ($provisioningStatus !== 'pending') {
                throw new \UnexpectedValueException('Local registration must leave MT4 provisioning pending.');
            }
            if (($result['success'] ?? null) !== true) {
                throw new \UnexpectedValueException('Processed registration did not explicitly confirm success.');
            }
            $userLogin = $result['user_login'] ?? null;
            if (!$userLogin instanceof UserLogin) {
                throw new \UnexpectedValueException('Registration service returned an invalid user login.');
            }

            $userLogin->refresh();
            if (!$userLogin->isActive() || !$this->hasBusinessProfile($userLogin)) {
                throw new \UnexpectedValueException('Registration service returned an inactive local account.');
            }

            // 本地注册提交后发送欢迎邮件；发送失败只记录日志，不影响注册成功响应。
            $this->sendRegistrationSuccessNotification($userLogin);

            // 本地账号可用后立即签发 JWT；MT4 预配保持 pending，不参与注册成功判定。
            $token = $this->jwtService->generateToken([
                'sub'   => $userLogin->id,
                'guard' => 'user',
            ]);


            return $this->success([
                'registered'          => true,
                'provisioning_status' => $provisioningStatus,
                'user_id'             => (int) $userLogin->user_id,
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'expires_in'   => config('jwt.ttl') * 60,
                'user'         => [
                    'id'       => $userLogin->id,
                    'user_id'  => $userLogin->user_id,
                    'email'    => $userLogin->email,
                ],
            ], __('auth.register_success'));
        } catch (Throwable $e) {
            Log::error('Front registration failed.', [
                'email_hash' => sha1(strtolower((string) $request->input('email', ''))),
                'exception' => $e,
            ]);

            // 已成功落库但后续链路异常时返回“注册完成需登录”，避免把已存在账号当普通内部错误重复处理。
            if ($registered) {
                return $this->error('response.registration_completed_login_required', ResponseCode::INTERNAL_ERROR, [
                    'registered' => true,
                    'login_required' => true,
                ]);
            }

            return $this->error('', ResponseCode::INTERNAL_ERROR);
        } finally {
            // 无论成功失败都释放邮箱注册锁，避免锁残留阻塞后续注册请求。
            $lock->release();
        }
    }

    /**
     * 处理新版前台登录。
     *
     * 参数逻辑说明：
     * - login 用于处理新版前台登录。
     * - account 表示统一账号输入，可为 email 或 user_id。
     * - email 表示邮箱登录账号；user_id 表示 MT/业务用户编号登录账号。
     * - password 表示用户登录密码，只用于 Hash::check 校验，不写入响应或日志。
     *
     * @param Request $request HTTP 请求对象，承载 account、email、user_id、password 和客户端 IP。
     * @return \Illuminate\Http\JsonResponse 登录成功返回 access_token 和前台用户基础信息。
     */
    public function login(Request $request)
    {
        // password 表示登录密码，缺失时直接返回多语言校验失败提示。
        $password = $request->input('password');
        if (!$password) {
            return $this->error(__('auth.password_required'), ResponseCode::VALIDATION_FAILED);
        }

        // 支持统一账号输入，自动判断 email 或 user_id，避免前端维护两套登录入口。
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

        // 根据登录账号类型查找 user_logins 记录，后续统一校验密码和启用状态。
        $userLogin = null;
        if ($email !== '') {
            $userLogin = UserLogin::where('email', $email)->first();
        } elseif ($userId !== '') {
            $userLogin = UserLogin::where('user_id', $userId)->first();
        }

        // 账号不存在与密码错误返回同一文案，避免未认证请求探测有效邮箱。
        if (!$userLogin || !Hash::check($request->password, $userLogin->password)) {
            return $this->error(__('auth.failed'), ResponseCode::INVALID_CREDENTIALS);
        }

        if (!$userLogin->isActive()) {
            return $this->error(__('auth.account_disabled'), ResponseCode::INVALID_CREDENTIALS);
        }

        if (!$this->hasBusinessProfile($userLogin)) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }
        // 生成 JWT 令牌并更新 SSO 缓存，sub 对应 user_logins.id，guard 固定为 user。
        $token = $this->jwtService->generateToken([
            'sub'   => $userLogin->id,
            'guard' => 'user',
        ]);

        // 更新登录信息；last_login_ip 表示最后登录 IP，last_login_at 表示最后登录时间。
        $userLogin->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        // 记录登录日志；login_id 对应 user_logins.id，user_id 对应业务用户编号。
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
     * 判断登录账号是否已经具备可进入前台的业务资料。
     */
    private function hasBusinessProfile(UserLogin $userLogin): bool
    {
        return (bool) ($userLogin->userInfo ?: UserInfo::where('user_id', $userLogin->user_id)->first());
    }

    /**
     * 发送注册成功欢迎邮件（best-effort）。
     *
     * 参数逻辑说明：
     * - 本地注册成功后调用，向注册邮箱发送业务账号与注册成功提示。
     * - 发送失败只记录警告日志，不抛出异常，保证注册成功响应不受邮件链路影响。
     *
     * @param UserLogin $userLogin 已成功提交本地注册的业务账号。
     * @return void
     */
    private function sendRegistrationSuccessNotification(UserLogin $userLogin): void
    {
        try {
            $info = $userLogin->userInfo ?: UserInfo::where('user_id', $userLogin->user_id)->first();
            Mail::to($userLogin->email)->send(new FrontRegistrationSuccessNotification(
                (int) $userLogin->user_id,
                (string) ($info->user_name ?? '')
            ));
        } catch (Throwable $exception) {
            Log::warning('Registration success notification email failed.', [
                'user_id' => $userLogin->user_id,
                'exception' => $exception,
            ]);
        }
    }


    /**
     * 兼容旧前台登录接口。
     *
     * 参数逻辑说明：
     * - legacySignIn 用于兼容旧前台登录接口。
     * - loginUid 表示旧页面提交的账号，可为邮箱或 user_id。
     * - loginPassword 表示旧页面提交的密码，兼容 password 字段。
     * - loginStatus 保留旧前端判断成功/失败使用的状态字段。
     *
     * @param Request $request HTTP 请求对象，承载 loginUid、loginPassword、account、password 和 session。
     * @return \Illuminate\Http\JsonResponse 返回旧页面兼容结构和 JWT。
     */
    public function legacySignIn(Request $request)
    {
        // 旧项目在账号查询前无条件校验验证码；Mews Captcha check 成功后会消费 Session/Cache。
        if (!app('captcha')->check(trim((string) $request->input('cptcode', '')))) {
            return response()->json([
                'errcptcode' => '验证码错误!',
                'loginStatus' => 400,
            ]);
        }

        $account = trim((string) $request->input('loginUid', $request->input('account', '')));
        $password = (string) $request->input('loginPassword', $request->input('password', ''));

        if ($account === '' || $password === '') {
            return response()->json([
                'errpsw' => '账号或密码不能为空',
                'loginStatus' => 400,
            ]);
        }

        $isEmail = filter_var($account, FILTER_VALIDATE_EMAIL) !== false;
        if (!$isEmail && (!ctype_digit($account) || (int) $account <= 0)) {
            return response()->json([
                'notactive' => '无效账户或账户被禁用!',
                'loginStatus' => 401,
            ]);
        }

        $userLogin = $isEmail
            ? UserLogin::where('email', strtolower($account))->first()
            : UserLogin::where('user_id', (int) $account)->first();

        if (!$userLogin || !$userLogin->isActive()) {
            return response()->json([
                'notactive' => '无效账户或账户被禁用!',
                'loginStatus' => 401,
            ]);
        }

        try {
            // 本地模式由 UserPasswordService 校验 Hash；MT4 模式由受门控网关给出远端结果。
            $passwordStatus = $this->passwordService->verify($userLogin, $password);
        } catch (Throwable $exception) {
            // 门控拒绝或传输异常均为未知结果，禁止把它降级为密码错误或放行。
            Log::warning('Legacy front login password verification unavailable.', [
                'user_id' => $userLogin->user_id,
                'exception' => $exception,
            ]);
            $passwordStatus = 'network_failure';
        }

        if ($passwordStatus === 'network_failure') {
            return response()->json([
                'mt4msg' => '网络故障,暂时无法登陆',
                'loginStatus' => 500,
            ]);
        }

        if ($passwordStatus !== 'verified') {
            return response()->json([
                'errpsw' => '密码错误!',
                'loginStatus' => 404,
            ]);
        }

        Auth::guard('user')->login($userLogin);
        $request->session()->put('suser', $userLogin->userInfo ? $userLogin->userInfo->toArray() : []);

        $token = $this->jwtService->generateToken([
            'sub' => $userLogin->id,
            'guard' => 'user',
        ]);

        $userLogin->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        UserLoginLog::create([
            'login_id' => $userLogin->id,
            'user_id' => $userLogin->user_id,
            'login_ip' => $request->ip(),
            'ip_location' => '',
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'msg' => 'OK',
            'loginStatus' => 200,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id' => $userLogin->id,
                'user_id' => $userLogin->user_id,
                'email' => $userLogin->email,
            ],
        ]);
    }

    /**
     * 退出前台登录。
     *
     * 读取 jwt.auth:user 中间件写入的 jwt_token，使其进入黑名单并清除 SSO 缓存，
     * 避免退出后旧令牌继续访问接口；无令牌时仍返回成功，保证重复退出幂等。
     *
     * @param Request $request 当前前台请求，承载 jwt.auth:user 写入的 jwt_token。
     * @return \Illuminate\Http\JsonResponse 退出成功响应。
     */
    public function logout(Request $request)
    {
        $token = $request->attributes->get('jwt_token');
        if ($token) {
            // 使当前令牌失效并清除 SSO 缓存，避免退出后旧 token 继续访问接口。
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], __('auth.logout_success'));
    }

    /**
     * 刷新前台 JWT。
     *
     * 参数逻辑说明：
     * - jwt_token 表示中间件解析出的当前令牌，缺失时返回 token_missing。
     * - refreshToken 只负责换发新令牌，不修改用户资料和菜单权限。
     *
     * @param Request $request HTTP 请求对象，承载 jwt.auth:user 写入的 jwt_token。
     * @return \Illuminate\Http\JsonResponse 刷新成功返回新的 access_token。
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
     * 修改当前前台用户密码。
     *
     * 参数逻辑说明：
     * - old_password 表示当前旧密码，必须先与 user_logins.password 比对。
     * - password 表示新密码，必须满足最小长度并与 password_confirmation 一致。
     * - 修改成功后使当前 token 失效，要求用户重新登录。
     *
     * @param Request $request HTTP 请求对象，承载 old_password、password、password_confirmation 和 jwt_token。
     * @return \Illuminate\Http\JsonResponse 密码修改结果。
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

        if (!$this->passwordService->change($user, (string) $request->password)) {
            return $this->error('response.mt4_sync_failed', ResponseCode::MT4_SYNC_FAILED);
        }

        // 使当前令牌失效，避免改密后旧会话继续访问。
        $token = $request->attributes->get('jwt_token');
        if ($token) {
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], 'auth.password_changed');
    }

    /**
     * 验证邀请人。
     *
     * 参数逻辑说明：
     * - inviter_id 表示邀请人用户编号。
     * - account_type 表示注册账户类型，account_type=1 表示代理，account_type=2 表示普通客户。
     * - commission_mode/comm_type 表示返佣模式兼容参数。
     *
     * @param Request $request HTTP 请求对象，承载邀请人、账户类型和返佣模式。
     * @param FrontRegisterRuleService $registerRules 注册规则服务，负责校验邀请关系是否合法。
     * @return \Illuminate\Http\JsonResponse 返回邀请人是否可用及提示文案。
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
     * 检查邮箱是否已注册。
     *
     * 参数逻辑说明：
     * - email 表示待检查的注册邮箱，对应 user_logins.email。
     *
     * @param Request $request HTTP 请求对象，承载 email。
     * @return \Illuminate\Http\JsonResponse 返回 exists 布尔值。
     */
    public function checkEmail(Request $request)
    {
        $exists = UserLogin::where('email', $request->email)->exists();
        return $this->success(['exists' => $exists]);
    }

    /**
     * 生成注册图形验证码。
     *
     * 参数逻辑说明：
     * - key 表示前端传入的验证码缓存标识，清洗后参与生成 captcha_key 对应的缓存键。
     * - 验证码 SVG 只用于展示，真实校验仍由 verifyRegisterCaptcha() 读取缓存完成。
     *
     * @param Request $request HTTP 请求对象，query.key 用于生成验证码缓存键。
     * @return \Illuminate\Http\Response SVG 图形验证码响应。
     */
    public function registerCaptcha(Request $request)
    {
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->query('key', ''));
        if ($key === '') {
            $key = bin2hex(random_bytes(8));
            if ($request->hasSession()) {
                $request->session()->put('front_register_captcha_key', $key);
            }
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

    /**
     * 生成旧前台登录图形验证码。
     *
     * 登录验证码必须独立复用旧项目 custom_captcha 的 Session/Cache 契约，
     * 不得与注册页的 front_register_captcha_* SVG 缓存体系混用。
     *
     * @param Request $request 当前旧登录请求。
     * @return \Symfony\Component\HttpFoundation\Response 验证码 PNG 响应。
     */
    public function loginCaptcha(Request $request)
    {
        return app('captcha')->create('custom_captcha');
    }

    /**
     * 注册前置资料校验。
     *
     * 参数逻辑说明：
     * - email、phone、id_card_no 分别检查邮箱、手机号、证件号是否已存在。
     * - inviter_id 存在时调用 FrontRegisterRuleService 继续校验邀请人和返佣规则。
     *
     * @param Request $request HTTP 请求对象，承载注册资料、邀请人和账户类型。
     * @return \Illuminate\Http\JsonResponse 返回资料是否可注册及错误明细。
     */
    public function registerVerifyInfo(Request $request)
    {
        $request->merge($this->normalizedRegisterInput($request));

        $errors = [];
        if ($request->filled('email') && UserLogin::where('email', $request->input('email'))->exists()) {
            $errors['email'] = __('auth.email_exists');
        }
        if ($request->filled('phone') && UserInfo::where('phone', $request->input('phone'))->exists()) {
            $errors['phone'] = __('auth.phone_exists');
        }
        if ($request->filled('id_card_no') && UserAuth::where('id_card_no', $request->input('id_card_no'))->exists()) {
            $errors['id_card_no'] = __('auth.id_card_exists');
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

    /**
     * 发送注册邮箱验证码。
     *
     * 参数逻辑说明：
     * - registerSendCode 用于发送注册邮箱验证码。
     * - email 表示接收验证码的邮箱。
     * - phone_code、phone_number、id_card_no、inviter_id、account_type 会先进入注册前置校验。
     * - rateKey 表示邮箱验证码发送频率限制缓存键，同一邮箱和 IP 60 秒内只允许发送一次。
     *
     * @param Request $request HTTP 请求对象，承载邮箱、手机号、证件号、邀请人和账户类型。
     * @return \Illuminate\Http\JsonResponse 发送成功返回 sent=true。
     */
    public function registerSendCode(Request $request)
    {
        $request->merge($this->normalizedRegisterInput($request));

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'phone_code' => 'required|string|max:10',
            // 与 register() 保持同一手机号口径，避免验证码入口比注册入口更宽松。
            'phone_number' => ['required', 'string', 'min:' . self::PHONE_NUMBER_MIN_LENGTH, 'max:' . self::PHONE_NUMBER_MAX_LENGTH, 'regex:/^[0-9]+$/'],
            'id_card_no' => 'required|string|max:50',
            'inviter_id' => 'nullable|integer',
            'account_type' => 'required|in:1,2',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $precheck = $this->registerVerifyInfo($request)->getData(true);
        if (($precheck['code'] ?? ResponseCode::ERROR) !== ResponseCode::SUCCESS) {
            return $this->error($precheck['message'] ?? 'response.validation_failed', ResponseCode::VALIDATION_ERROR, $precheck['data'] ?? []);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $rateKey = 'front_register_email_code_rate_' . sha1($email . '|' . $request->ip());
        if (!Cache::add($rateKey, 1, now()->addSeconds(60))) {
            return $this->error('response.rate_limited', ResponseCode::RATE_LIMITED);
        }

        $code = (string) random_int(123456, 999999);

        try {
            Mail::to($email)->send(new FrontRegistrationVerificationCode($code));
        } catch (Exception $e) {
            Cache::forget($rateKey);
            return $this->error('response.email_send_failed', ResponseCode::EMAIL_SEND_FAILED);
        }

        Cache::put($this->registerEmailCodeCacheKey($email), [
            'email' => $email,
            'code' => $code,
        ], now()->addMinutes(10));

        return $this->success(['sent' => true], 'response.success');
    }

    /**
     * 标准化注册表单字段。
     *
     * 参数逻辑说明：
     * - normalizedRegisterInput 用于兼容旧页面字段名称。
     * - useremail、username、modules、userphoneNo、userIdcardNo、reguserverfcode、userverfcode 为旧前台字段。
     * - 返回数组会 merge 到 Request，供新版校验规则统一读取。
     *
     * @param Request $request HTTP 请求对象，承载新旧注册字段。
     * @return array<string, mixed> 标准化后的注册字段。
     */
    private function normalizedRegisterInput(Request $request): array
    {
        $isLegacyPayload = $request->routeIs(
            'legacy_user_register_into',
            'legacy_user_register_send_code',
            'legacy_user_register_verify_info'
        );
        $email = strtolower(trim((string) $request->input('email', $isLegacyPayload ? $request->input('useremail', '') : '')));
        $phoneCode = trim((string) $request->input('phone_code', $isLegacyPayload ? $request->input('modules', '') : ''));
        $phoneNumber = trim((string) $request->input('phone_number', $isLegacyPayload ? $request->input('userphoneNo', '') : ''));
        $phone = $phoneCode !== '' && $phoneNumber !== '' ? $phoneCode . '-' . $phoneNumber : trim((string) $request->input('phone', ''));
        $accountType = $request->input('account_type');
        if (($accountType === null || $accountType === '') && $isLegacyPayload) {
            $legacyType = strtolower(trim((string) $request->input('register_type', '')));
            if (in_array($legacyType, ['1', 'agent', 'agents'], true)) {
                $accountType = 1;
            } elseif (in_array($legacyType, ['2', 'user', 'customer', 'member'], true)) {
                $accountType = 2;
            }
        }

        $inviterId = $request->input('inviter_id', $isLegacyPayload ? $request->input('userInviterId', $request->input('parent_id')) : null);
        if ($isLegacyPayload && is_string($inviterId) && preg_match('/^(\d+)A$/i', trim($inviterId), $matches)) {
            $inviterId = (int) $matches[1];
        }
        if ($accountType !== null && (int) $accountType === 2 && !$inviterId) {
            $inviterId = 10;
        }

        $captchaKey = (string) $request->input('captcha_key', '');
        if ($captchaKey === '' && $isLegacyPayload && $request->hasSession()) {
            $captchaKey = (string) $request->session()->get('front_register_captcha_key', '');
        }

        $passwordConfirmation = $request->input('password_confirmation');
        if (($passwordConfirmation === null || $passwordConfirmation === '') && $isLegacyPayload) {
            $passwordConfirmation = $request->input('againpassword', $request->input('password'));
        }
        $gender = $request->input('gender', $isLegacyPayload ? $request->input('sex') : null);
        $agreeTerms = $request->input('agree_terms', $isLegacyPayload ? $request->input('agreeRule') : null);
        if ($isLegacyPayload) {
            $gender = $gender === null ? null : (string) $gender;
            $agreeTerms = $agreeTerms === null ? null : (string) $agreeTerms;
        }

        return [
            'email' => $email,
            'user_name' => trim((string) $request->input('user_name', $isLegacyPayload ? $request->input('username', '') : '')),
            'phone_code' => $phoneCode,
            'phone_number' => $phoneNumber,
            'phone' => $phone,
            'id_card_no' => trim((string) $request->input('id_card_no', $isLegacyPayload ? $request->input('userIdcardNo', '') : '')),
            'gender' => $gender,
            'account_type' => $accountType,
            'inviter_id' => $inviterId,
            'agree_terms' => $agreeTerms,
            'password_confirmation' => $passwordConfirmation,
            'captcha_key' => $captchaKey,
            'captcha_code' => trim((string) $request->input('captcha_code', $isLegacyPayload ? $request->input('reguserverfcode', '') : '')),
            'email_code' => trim((string) $request->input('email_code', $isLegacyPayload ? $request->input('userverfcode', '') : '')),
        ];
    }

    /**
     * 校验注册图形验证码。
     *
     * 参数逻辑说明：
     * - verifyRegisterCaptcha 用于校验图形验证码。
     * - captcha_key 表示图形验证码缓存键，必须与 registerCaptcha() 使用的 key 对应。
     * - captcha_code 表示用户输入的图形验证码，比较时统一转为大写。
     *
     * @param Request $request HTTP 请求对象，承载 captcha_key 和 captcha_code。
     * @return bool 验证码正确返回 true，否则返回 false。
     */
    private function verifyRegisterCaptcha(Request $request): bool
    {
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->input('captcha_key', ''));
        $expected = Cache::get($this->registerCaptchaCacheKey($key));

        return $expected && strtoupper((string) $expected) === strtoupper(trim((string) $request->input('captcha_code')));
    }

    /**
     * 在注册数据成功落库后消费一次性图形验证码。
     *
     * @param Request $request 已通过验证码校验并完成注册的请求。
     * @return void
     */
    private function consumeRegisterCaptcha(Request $request): void
    {
        $captchaKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->input('captcha_key', ''));

        Cache::forget($this->registerCaptchaCacheKey($captchaKey));
    }

    /**
     * 生成注册图形验证码缓存键。
     *
     * @param string $key 前端验证码标识，参与 sha1 后生成缓存键。
     * @return string 图形验证码缓存键。
     */
    private function registerCaptchaCacheKey(string $key): string
    {
        return 'front_register_captcha_' . sha1($key);
    }

    /**
     * 生成注册邮箱验证码缓存键。
     *
     * @param string $email 注册邮箱，统一小写后参与 sha1 生成缓存键。
     * @return string 邮箱验证码缓存键。
     */
    private function registerEmailCodeCacheKey(string $email): string
    {
        return 'front_register_email_code_' . sha1(strtolower($email));
    }
}
