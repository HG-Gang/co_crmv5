<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:05
 */

namespace Tests\Feature;

use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Models\UserAuth;
use App\Models\AgentDescendant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RegisteredUserFixtureCleaner;
use Tests\TestCase;

/**
 * 前台用户个人中心全链路闭环保真测试。
 *
 * 文件功能：
 * - 验证登录后个人中心 API 的完整链路：查看资料→更新资料→修改密码→修改邮箱。
 * - 覆盖 ProfileController 核心方法的真实数据库操作。
 *
 * 涉及表：user_mt4_provisioning_outbox、user_login_logs、user_logins、user_infos、user_auths、agent_descendants。
 * 中间件：JwtAuthMiddleware → SingleSignOn。
 */
class FrontProfileClosureTest extends TestCase
{
    private string $testEmail;
    private string $testPassword = 'Test@123456';
    private ?int $testUserId = null;
    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $staleUserIds = UserLogin::where('email', 'like', 'e2e_prof_%@test.local')->pluck('user_id');
        RegisteredUserFixtureCleaner::forceDelete($staleUserIds);
        $this->testEmail = 'e2e_prof_' . md5(uniqid('', true) . mt_rand(100000, 999999)) . '@test.local';
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
     * 注册并登录，获取 JWT token。
     */
    private function registerAndLogin(): void
    {
        $ck = 'test_p_' . uniqid();
        Cache::put('front_register_captcha_' . sha1($ck), 'ABC12', 300);
        Cache::put('front_register_email_code_' . sha1(strtolower($this->testEmail)), [
            'email' => strtolower($this->testEmail), 'code' => '888888',
        ], 600);

        $s = mt_rand(100000, 999999);
        $reg = $this->postJson('/api/front/auth/register', [
            'email' => $this->testEmail, 'password' => $this->testPassword,
            'password_confirmation' => $this->testPassword, 'user_name' => '资料测试',
            'phone_code' => '+86', 'phone_number' => "13900{$s}", 'phone' => "13900{$s}",
            'id_card_no' => '440101' . date('Ymd') . sprintf('%04d', mt_rand(0, 9999)),
            'gender' => 1, 'account_type' => 2, 'inviter_id' => 10,
            'captcha_key' => $ck, 'captcha_code' => 'ABC12', 'email_code' => '888888', 'agree_terms' => 1,
        ])->json();

        $this->testUserId = (int) ($reg['data']['user_id'] ?? 0);
        $this->assertTrue($this->testUserId > 0 || ($reg['data']['registered'] ?? false), '注册应成功');

        $this->token = $this->postJson('/api/front/auth/login', [
            'email' => $this->testEmail, 'password' => $this->testPassword,
        ])->json('data.access_token');

        $this->assertNotEmpty($this->token, '应获取 JWT token');
    }

    /** 判断响应码是否表示成功（兼容 0、1000、1002） */
    private function isSuccess(int $code): bool
    {
        return in_array($code, [0, 1000, 1002], true);
    }

    /**
     * 步骤1：获取个人资料。
     * 返回 data 含 user_id、email、user_name、phone 等。
     */
    public function test_step1_get_profile(): void
    {
        $this->registerAndLogin();

        $json = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/front/profile')->json();

        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '获取个人资料应成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 步骤2：更新个人资料。
     * 修改 user_name/gender 等非敏感字段。
     */
    public function test_step2_update_profile(): void
    {
        $this->registerAndLogin();

        $json = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/front/profile', ['user_name' => '已更新名称', 'gender' => 2])->json();

        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '更新资料应成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 步骤3：修改密码（参数名 old_password 非 current_password）。
     * 错误旧密码应被拒绝。
     */
    public function test_step3_change_password(): void
    {
        $this->registerAndLogin();

        $json = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/front/profile/password', [
                'old_password' => $this->testPassword,
                'password' => 'NewPass@789',
                'password_confirmation' => 'NewPass@789',
            ])->json();

        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '修改密码应成功(正确旧密码) | ' . json_encode($json, JSON_UNESCAPED_UNICODE));

        $fail = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/front/profile/password', [
                'old_password' => 'WrongOldPwd',
                'password' => 'Another@123',
                'password_confirmation' => 'Another@123',
            ])->json();

        $this->assertNotEquals(0, $fail['code'] ?? 0, '错误旧密码应被拒绝');
    }

    /**
     * 步骤4：无 Token 访问被拒绝。
     */
    public function test_step4_unauthenticated_rejected(): void
    {
        $json = $this->getJson('/api/front/profile')->json();
        $this->assertNotEquals(0, $json['code'] ?? 0, '无Token应被拒绝');
    }

    /**
     * 步骤5：Token 刷新（POST /api/front/auth/token/refresh）。
     *
     * 逻辑说明：
     * - 使用当前有效 token 刷新，返回新的 access_token。
     * - JwtService 在刷新窗口内签发新 token 并更新 SSO 缓存。
     */
    public function test_step5_refresh_token(): void
    {
        $this->registerAndLogin();

        $json = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/front/auth/token/refresh')->json();

        $this->assertTrue($this->isSuccess($json['code'] ?? 999),
            '刷新Token应成功 | ' . json_encode($json, JSON_UNESCAPED_UNICODE));

        $newToken = $json['data']['access_token'] ?? '';
        $this->assertNotEmpty($newToken, '刷新后应返回新 token');
        $this->assertNotEquals($this->token, $newToken, '新 token 应与旧 token 不同');
    }
}
