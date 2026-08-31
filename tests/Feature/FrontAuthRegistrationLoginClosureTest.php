<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 01:03
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Models\UserAuth;
use App\Models\AgentDescendant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RegisteredUserFixtureCleaner;
use Tests\TestCase;

/**
 * 前台用户注册登录全链路闭环保真测试。
 *
 * 文件功能：
 * - 模拟完整用户注册（含验证码）→登录→访问受保护API→登出→Token失效全流程。
 * - 验证 AuthController + UserRegistrationService + JwtService + SingleSignOn 四方协作。
 *
 * 涉及数据库表：
 * - user_mt4_provisioning_outbox（开户出队）、user_login_logs（登录审计）、user_logins（登录账号）。
 * - user_infos（业务资料）、user_auths（实名初始化）、agent_descendants（家族树）。
 *
 * 异常或失败场景：
 * - 空字段注册 → code=4005。
 * - 邮箱重复 → code≠0。
 * - 密码错误 → code≠0。
 * - 无Token → code≠0。
 * - 登出后Token失效（SSO机制）→ code≠0。
 */
class FrontAuthRegistrationLoginClosureTest extends TestCase
{
    private string $testEmail;
    private string $testPassword = 'Test@123456';
    private ?int $testUserId = null;
    /** 数据库中第一个可用代理的 user_id */
    private int $validInviterId = 10;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // 清理上一次测试可能遗留的脏数据（按测试邮箱前缀匹配）
        $staleUserIds = UserLogin::where('email', 'like', 'e2e_%@test.local')->pluck('user_id');
        RegisteredUserFixtureCleaner::forceDelete($staleUserIds);

        $suffix = uniqid('', true) . mt_rand(100000, 999999);
        $this->testEmail = 'e2e_' . md5($suffix) . '@test.local';
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testUserId) {
                RegisteredUserFixtureCleaner::forceDelete([$this->testUserId]);
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * 生成注册所需的 captcha 模拟数据。
     *
     * 缓存键格式：front_register_captcha_{sha1(key)}（对应 AuthController::registerCaptchaCacheKey）
     *
     * @return array{captcha_key:string, captcha_code:string}
     */
    private function fakeCaptcha(): array
    {
        $key = 'test_' . uniqid();
        $code = 'A1B2C';
        Cache::put('front_register_captcha_' . sha1($key), $code, now()->addMinutes(10));
        return ['captcha_key' => $key, 'captcha_code' => $code];
    }

    /**
     * 生成注册所需的 email_code 模拟数据。
     *
     * 缓存键格式：front_register_email_code_{sha1(strtolower(email))}（对应 AuthController::registerEmailCodeCacheKey）
     * 缓存值格式：['email' => $email, 'code' => $code]（对应 AuthController::registerSendCode 写缓存逻辑）
     *
     * @return string 6位邮箱验证码
     */
    /**
     * 执行注册请求，返回原始 JSON 响应数据。
     *
     * 逻辑说明：
     * - 每次调用使用随机 email 和 phone 避免唯一键冲突。
     * - MT4 同步可能失败（测试环境无 MT4 服务器），但用户本地数据应成功创建（registered=true）。
     *
     * @param string|null $email 可覆盖 email，null 则用 $this->testEmail。
     * @return array 注册响应的完整 JSON 数据
     */
    private function doRegister(string $email = null): array
    {
        $captcha = $this->fakeCaptcha();
        $email = $email ?? $this->testEmail;
        $suffix = (string)(time() * 1000 + rand(100000, 999999));

        return $this->postJson('/api/front/auth/register', [
            'email' => $email,
            'password' => $this->testPassword,
            'password_confirmation' => $this->testPassword,
            'user_name' => '端到端测试' . substr($suffix, -6),
            'phone_code' => '+86',
            'phone_number' => '13800' . substr($suffix, -6),
            'phone' => '13800' . substr($suffix, -6),
            'id_card_no' => '4401011990' . substr($suffix, 0, 8),
            'gender' => 1,
            'account_type' => 2,
            'inviter_id' => $this->validInviterId,
            'captcha_key' => $captcha['captcha_key'],
            'captcha_code' => $captcha['captcha_code'],
            'agree_terms' => 1,
        ])->json();
    }

    /**
     * 步骤1：空字段注册被参数校验拦截。
     * 返回：code≠0（4005），message 含字段名。
     */
    public function test_step1_empty_fields_rejected(): void
    {
        $json = $this->postJson('/api/front/auth/register', ['email' => '', 'password' => ''])->json();
        $this->assertNotEquals(0, $json['code'] ?? 0, '空字段注册应返回非0错误码');
    }

    /**
     * 步骤2：获取图形验证码（SVG 格式）。
     * 返回：Content-Type=image/svg+xml，含验证码 SVG。
     */
    public function test_step2_captcha_endpoint_returns_svg(): void
    {
        $response = $this->get('/api/front/auth/register/captcha?key=test_step2');
        $response->assertStatus(200);
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type', ''));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    /**
     * 步骤3：验证邀请人存在。
     * 返回：code=0 或 1000（成功码），data.valid=true。
     */
    public function test_step3_inviter_valid(): void
    {
        $json = $this->getJson('/api/front/auth/inviter?inviter_id=' . $this->validInviterId)->json();
        $successCode = $json['code'] ?? 999;
        $this->assertTrue(
            $successCode === 0 || $successCode === 1000,
            '验证邀请人(user_id=' . $this->validInviterId . ')应返回成功码，实际 code=' . $successCode .
            ' | ' . json_encode($json, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * 步骤4：完整注册流程（含验证码）。
     *
     * 执行链路：
     * 1. POST /api/front/auth/register → AuthController@register
     * 2. normalizeRegisterInput() → 兼容旧字段名
     * 3. 参数校验 → 验证码校验（captcha + email_code）
     * 4. UserRegistrationService@register → 事务写入 user_logins + user_infos + user_auths + agent_descendants
     * 5. MT4 建仓出队（UserMt4ProvisioningProcessor，异步）
     *
     * 返回：
     * - code=0 或 code=2025：2025 表示用户已注册但 MT4 同步待重试（测试环境无 MT4 服务器，正常）。
     * - data.registered=true：用户本地数据创建成功。
     * - data.user_id：新用户的业务 ID。
     */
    public function test_step4_complete_registration(): void
    {
        $json = $this->doRegister();
        $code = $json['code'] ?? 999;
        $registered = $json['data']['registered'] ?? false;
        $provisioningStatus = $json['data']['provisioning_status'] ?? '';

        // code=0 表示完全成功；code=2025 表示用户已注册但 MT4 同步待重试（测试环境无 MT4 服务器）
        $isSuccess = $code === ResponseCode::SUCCESS
            && $registered === true
            && $provisioningStatus === 'pending';
        $this->assertTrue($isSuccess,
            '注册应成功(code=0)或用户已创建但MT4待重试(code=2025)，实际 code=' . $code .
            ' registered=' . var_export($registered, true) .
            ' provisioning=' . $provisioningStatus .
            ' | ' . json_encode($json, JSON_UNESCAPED_UNICODE));

        $this->testUserId = (int) ($json['data']['user_id'] ?? 0);
        $this->assertGreaterThan(0, $this->testUserId, '应返回有效的 user_id');

        $this->assertNotNull(UserLogin::where('user_id', $this->testUserId)->first(), 'user_logins 应有记录');
        $this->assertNotNull(UserInfo::where('user_id', $this->testUserId)->first(), 'user_infos 应有记录');
        $this->assertNotNull(UserAuth::where('user_id', $this->testUserId)->first(), 'user_auths 应有记录');
    }

    /**
     * 步骤5：重复邮箱注册被拒绝。
     *
     * 逻辑说明：
     * - 使用步骤4已存在的邮箱再次注册。
     * - 后端 user_logins.email 唯一索引 + isEmailExists() 双重校验。
     *
     * 返回：code≠0，msg 含"email"或"邮箱"。
     */
    public function test_step5_duplicate_email_rejected(): void
    {
        $this->test_step4_complete_registration();
        $json = $this->doRegister($this->testEmail);

        $this->assertNotEquals(0, $json['code'] ?? 0,
            '重复邮箱应被拒绝，实际 code=' . ($json['code'] ?? '?') .
            ' msg=' . ($json['msg'] ?? $json['message'] ?? '') .
            ' | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 步骤6：登录获取JWT + 仪表盘访问。
     *
     * 执行链路：
     * 1. POST /api/front/auth/login（email + password）
     * 2. AuthController@login → UserLogin::where('email') → Hash::check(password)
     * 3. JwtService::generate(user_id, guard='user') → 签发 JWT（sub/guard/jti/iat/exp）
     * 4. Redis::set(sso:user:{user_id}, jti) → SSO 缓存写入
     * 5. GET /api/front/dashboard + Bearer token
     * 6. JwtAuthMiddleware → 验证签名+有效期 → 解析 sub/guard/jti
     * 7. SingleSignOn → Redis::get(sso:user:{sub}) === jti → 通过
     * 8. DashboardController@dashboardData → 返回仪表盘 JSON
     *
     * 返回：
     * - 登录：code=0，data.access_token 非空，token_type=Bearer。
     * - 仪表盘：code=0，data 含用户数据。
     */
    public function test_step6_login_and_dashboard_access(): void
    {
        $this->test_step4_complete_registration();

        $loginJson = $this->postJson('/api/front/auth/login', [
            'email' => $this->testEmail,
            'password' => $this->testPassword,
        ])->json();

        $loginCode = $loginJson['code'] ?? 999;
        $this->assertTrue($loginCode === 0 || $loginCode === 1000,
            '登录应返回 code=0 或 1000，实际 code=' . $loginCode .
            ' | ' . ($loginJson['msg'] ?? $loginJson['message'] ?? '无') .
            ' | ' . json_encode($loginJson, JSON_UNESCAPED_UNICODE));

        $token = $loginJson['data']['access_token'] ?? '';
        $this->assertNotEmpty($token, '应返回 access_token');
        $this->assertEquals('Bearer', $loginJson['data']['token_type'] ?? '');

        $dashJson = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/front/dashboard')->json();

        $dashCode = $dashJson['code'] ?? 999;
        $this->assertTrue($dashCode === 0 || $dashCode === 1000,
            '带Token访问仪表盘应成功，实际 code=' . $dashCode .
            ' | ' . ($dashJson['msg'] ?? $dashJson['message'] ?? '') .
            ' | ' . json_encode($dashJson, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 步骤7：错误密码登录被拒绝。
     * 返回：code≠0（密码错误/账号认证失败）。
     */
    public function test_step7_wrong_password_rejected(): void
    {
        $this->test_step4_complete_registration();

        $json = $this->postJson('/api/front/auth/login', [
            'email' => $this->testEmail,
            'password' => 'WrongPassword999',
        ])->json();

        $this->assertNotEquals(0, $json['code'] ?? 0,
            '错误密码应被拒绝，实际：' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 步骤8：无Token访问受保护API被拒绝。
     * 返回：code≠0（缺少令牌/认证失败）。
     */
    public function test_step8_no_token_rejected(): void
    {
        $json = $this->getJson('/api/front/dashboard')->json();
        $this->assertNotEquals(0, $json['code'] ?? 0,
            '无Token应被拒绝，实际：' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 步骤9：登出后Token失效（SSO 单点登录）。| Logout invalidates token (SSO mechanism).
     *
     * 执行链路：
     * 1. 登录 → JwtService::generate → Redis sso:user:{sub}=jti
     * 2. 登出 → JwtService::invalidate → Redis::del(sso:user:{sub})
     * 3. 旧Token重试 → SSO 中间件发现 Redis 缓存无匹配 jti → 返回 SSO_CONFLICT
     *
     * 返回：code≠0（认证失败/SSO冲突）。
     */
    public function test_step9_logout_invalidates_token(): void
    {
        $this->test_step4_complete_registration();

        $token = $this->postJson('/api/front/auth/login', [
            'email' => $this->testEmail,
            'password' => $this->testPassword,
        ])->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/front/auth/logout')
            ->assertStatus(200);

        $json = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/front/dashboard')->json();

        $this->assertNotEquals(0, $json['code'] ?? 0,
            '登出后旧Token应失效，实际：' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }
}
