<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:05
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgentDescendant;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserLoginLog;
use App\Models\UserMt4ProvisioningOutbox;
use App\Models\UserOnline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RegisteredUserFixtureCleaner;
use Tests\TestCase;

/**
 * 注册用户测试夹具清理器行为回归。
 *
 * 文件功能：
 * - 通过真实注册接口创建登录、资料、认证、代理关系与 MT4 Outbox。
 * - 验证共享清理器物理删除测试拥有的全部记录，包括软删除后仍占唯一键的 Outbox。
 *
 * 安全边界：
 * - 只删除本测试响应返回的 user_id，不按状态或全表范围批量清理。
 * - RED 阶段的 tearDown 直接执行同一安全顺序，避免故意失败污染后续测试。
 */
final class RegisteredUserFixtureCleanerTest extends TestCase
{
    private ?int $testUserId = null;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testUserId !== null) {
                RegisteredUserFixtureCleaner::forceDelete([$this->testUserId]);
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * 清理器必须先移除 Outbox，再完整移除注册用户的关联记录。
     *
     * @return void 所有表均不存在目标 user_id 时通过。
     */
    public function test_cleaner_force_deletes_the_complete_registered_user_fixture(): void
    {
        $suffix = (string) random_int(10000000, 99999999);
        $email = 'fixture_cleaner_' . $suffix . '@test.local';
        $captchaKey = 'fixture_cleaner_' . $suffix;
        Cache::put('front_register_captcha_' . sha1($captchaKey), 'C1E2A', 300);
        Cache::put('front_register_email_code_' . sha1($email), [
            'email' => $email,
            'code' => '888888',
        ], 600);

        $response = $this->postJson('/api/front/auth/register', [
            'email' => $email,
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
            'user_name' => '夹具清理验证',
            'phone_code' => '+86',
            'phone_number' => '137' . $suffix,
            'phone' => '137' . $suffix,
            'id_card_no' => '4401011990' . $suffix,
            'gender' => 1,
            'account_type' => 2,
            'inviter_id' => 10,
            'captcha_key' => $captchaKey,
            'captcha_code' => 'C1E2A',
            'email_code' => '888888',
            'agree_terms' => 1,
        ])->json();

        $this->testUserId = (int) ($response['data']['user_id'] ?? 0);
        $this->assertGreaterThan(
            0,
            $this->testUserId,
            '真实注册必须返回可清理的 user_id：' . json_encode($response, JSON_UNESCAPED_UNICODE)
        );
        $this->assertTrue(
            UserMt4ProvisioningOutbox::withTrashed()->where('user_id', $this->testUserId)->exists(),
            '注册提交后必须存在对应 MT4 Outbox，回归测试才覆盖原始泄漏。'
        );
        $login = $this->postJson('/api/front/auth/login', [
            'email' => $email,
            'password' => 'Test@123456',
        ])->json();
        $this->assertContains($login['code'] ?? null, [0, 1000], '真实登录必须成功并生成审计记录。');
        $this->assertTrue(
            UserLoginLog::withTrashed()->where('user_id', $this->testUserId)->exists(),
            '真实登录后必须存在审计记录，回归测试才覆盖登录日志泄漏。'
        );
        UserOnline::query()->create([
            'user_id' => $this->testUserId,
            'last_activity' => time(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'fixture-cleaner-test',
        ]);

        RegisteredUserFixtureCleaner::forceDelete([$this->testUserId]);

        $this->assertRegisteredFixtureMissing($this->testUserId);
        $this->testUserId = null;
    }

    /**
     * 断言目标业务用户在全部注册相关表中均已消失。
     *
     * @param int $userId 本测试真实注册产生的业务用户 ID。
     * @return void 任一表仍存在记录时明确失败。
     */
    private function assertRegisteredFixtureMissing(int $userId): void
    {
        $this->assertFalse(UserMt4ProvisioningOutbox::withTrashed()->where('user_id', $userId)->exists());
        $this->assertFalse(UserLoginLog::withTrashed()->where('user_id', $userId)->exists());
        $this->assertFalse(UserOnline::where('user_id', $userId)->exists());
        $this->assertFalse(AgentDescendant::withTrashed()->where('descendant_id', $userId)->exists());
        $this->assertFalse(AgentDescendant::withTrashed()->where('agent_id', $userId)->exists());
        $this->assertFalse(UserAuth::withTrashed()->where('user_id', $userId)->exists());
        $this->assertFalse(UserInfo::withTrashed()->where('user_id', $userId)->exists());
        $this->assertFalse(UserLogin::withTrashed()->where('user_id', $userId)->exists());
    }
}
