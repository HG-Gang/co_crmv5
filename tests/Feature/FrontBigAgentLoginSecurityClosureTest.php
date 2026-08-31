<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 01:56
 */

/**
 * FrontBigAgentLoginSecurityClosureTest
 *
 * 文件功能：
 * - 验证大代理登录验证码与防爆破闭环：验证码一次性且不回传明文、连续失败后强制验证码、IP+账号限流、成功清理失败状态、过期验证码拒绝且不建会话。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 大代理登录 CAPTCHA 与防爆破闭环测试。
 *
 * 测试只验证公开行为：验证码端点不回传明文字段，失败达到阈值后才要求验证码，
 * IP+账号组合被限流，成功会清理失败状态，验证码过期/消费后不能重放，旧字段别名仍可用。
 */
class FrontBigAgentLoginSecurityClosureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_and_modern_captcha_routes_store_one_time_code_without_json_plaintext(): void
    {
        foreach ([
            '/user/agents/captcha' => 'legacy-big-agent-captcha-' . uniqid(),
            '/user/agents/login/captcha' => 'legacy-big-agent-captcha-alias-' . uniqid(),
            '/api/front/auth/big-number/captcha' => 'modern-big-agent-captcha-' . uniqid(),
        ] as $path => $key) {
            $response = $this->get($path . '?key=' . urlencode($key));

            $response->assertOk();
            $this->assertStringStartsWith('image/svg+xml', (string) $response->headers->get('content-type'));
            $this->assertStringNotContainsString('"captcha_code"', $response->getContent());
            $this->assertSame($key, (string) session('big_agent_captcha_key'));

            $cacheKey = $this->captchaCacheKey($key);
            $this->assertNotNull(Cache::get($cacheKey));
            $this->assertMatchesRegularExpression('/<text[^>]*>[A-Z2-9]{5}<\/text>/', $response->getContent());
        }
    }

    public function test_legacy_login_requires_captcha_after_two_failures_and_consumes_alias_code_once(): void
    {
        $id = 4971101;
        $username = 'legacy-security-' . $id;
        $email = $username . '@example.test';
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, $username, $email, 'legacy-security-password');

        $request = function (array $payload) {
            return $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
                ->postJson('/user/agents/signIn', $payload);
        };

        $request(['loginUid' => $username, 'loginPassword' => 'wrong-password'])
            ->assertOk()
            ->assertJsonPath('loginStatus', 404)
            ->assertJsonPath('errpsw', __('auth.failed'));

        $request(['loginUid' => $username, 'loginPassword' => 'wrong-password'])
            ->assertOk()
            ->assertJsonPath('loginStatus', 404)
            ->assertJsonPath('captcha_required', true);

        $request(['loginUid' => $username, 'loginPassword' => 'legacy-security-password'])
            ->assertOk()
            ->assertJsonPath('loginStatus', 400)
            ->assertJsonPath('captcha_required', true)
            ->assertJsonPath('errcptcode', __('auth.invalid_captcha'))
            ->assertSessionMissing('bigAgents');

        $key = 'legacy-security-key-' . $id;
        $code = $this->issueCaptcha('/user/agents/captcha', $key);
        $success = $request([
            'loginUid' => $username,
            'loginPassword' => 'legacy-security-password',
            // 旧页面字段 + 新验证码 key 别名必须同时兼容。
            'captchaKey' => $key,
            'cptcode' => $code,
        ]);

        $success->assertOk()
            ->assertJsonPath('msg', 'OK')
            ->assertJsonPath('loginStatus', 200)
            ->assertSessionHas('bigAgents.id', $id);
        $this->assertArrayNotHasKey('captcha_code', $success->json());
        $this->assertFalse(Cache::has($this->captchaCacheKey($key)));

        // 成功后先重新制造两次失败，再验证刚才的验证码不能重放。
        $request(['loginUid' => $username, 'loginPassword' => 'wrong-password']);
        $request(['loginUid' => $username, 'loginPassword' => 'wrong-password']);
        $request([
            'loginUid' => $username,
            'loginPassword' => 'legacy-security-password',
            'captcha_key' => $key,
            'captcha_code' => $code,
        ])->assertOk()
            ->assertJsonPath('loginStatus', 400)
            ->assertJsonPath('errcptcode', __('auth.invalid_captcha'));
    }

    public function test_legacy_login_rate_limit_is_scoped_to_ip_and_account(): void
    {
        $id = 4971102;
        $username = 'legacy-rate-' . $id;
        $email = $username . '@example.test';
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, $username, $email, 'legacy-rate-password');

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
                ->postJson('/user/agents/signIn', [
                    'loginUid' => $username,
                    'loginPassword' => 'wrong-password',
                ])
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
            ->postJson('/user/agents/signIn', [
                'loginUid' => $username,
                'loginPassword' => 'wrong-password',
            ])
            ->assertOk()
            ->assertJsonPath('loginStatus', 429)
            ->assertJsonPath('code', ResponseCode::RATE_LIMITED);

        // 同一账号换 IP 不应继承另一 IP 的组合限流状态。
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12'])
            ->postJson('/user/agents/signIn', [
                'loginUid' => $username,
                'loginPassword' => 'wrong-password',
            ])
            ->assertOk()
            ->assertJsonPath('loginStatus', 404);
    }

    public function test_modern_login_requires_and_consumes_captcha_then_clears_failures(): void
    {
        $userId = 4971103;
        $email = 'modern-security-' . $userId . '@example.test';
        $this->deleteUser($userId, $email);
        $this->insertUserAgent($userId, $email, 'modern-security-password');

        $request = function (array $payload) {
            return $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.31'])
                ->postJson('/api/front/auth/big-number/login', $payload);
        };

        $request(['user_id' => $userId, 'password' => 'wrong-password'])
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED);
        $request(['user_id' => $userId, 'password' => 'wrong-password'])
            ->assertJsonPath('data.captcha_required', true);

        $key = 'modern-security-key-' . $userId;
        $code = $this->issueCaptcha('/api/front/auth/big-number/captcha', $key);
        $success = $request([
            'user_id' => $userId,
            'password' => 'modern-security-password',
            'captcha_key' => $key,
            'captcha_code' => $code,
        ]);

        $success->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user.user_id', $userId);
        $this->assertFalse(Cache::has($this->captchaCacheKey($key)));
        $this->assertArrayNotHasKey('captcha_code', $success->json());
    }

    public function test_expired_captcha_is_rejected_without_creating_login_session(): void
    {
        $id = 4971104;
        $username = 'legacy-expired-' . $id;
        $email = $username . '@example.test';
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, $username, $email, 'legacy-expired-password');

        $request = function (array $payload) {
            return $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.44'])
                ->postJson('/user/agents/signIn', $payload);
        };
        $request(['loginUid' => $username, 'loginPassword' => 'wrong-password']);
        $request(['loginUid' => $username, 'loginPassword' => 'wrong-password']);

        $key = 'legacy-expired-key-' . $id;
        $this->issueCaptcha('/user/agents/captcha', $key);
        Cache::put($this->captchaCacheKey($key), 'ABCDE', now()->subMinute());

        $request([
            'loginUid' => $username,
            'loginPassword' => 'legacy-expired-password',
            'captcha_key' => $key,
            'captcha_code' => 'ABCDE',
        ])->assertOk()
            ->assertJsonPath('loginStatus', 400)
            ->assertJsonPath('errcptcode', __('auth.invalid_captcha'))
            ->assertSessionMissing('bigAgents');
    }

    private function issueCaptcha(string $path, string $key): string
    {
        $response = $this->get($path . '?key=' . urlencode($key));
        $response->assertOk();
        preg_match('/<text[^>]*>([^<]+)<\/text>/', $response->getContent(), $matches);
        $code = strtoupper(trim((string) ($matches[1] ?? '')));
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}$/', $code);
        $this->assertSame($code, (string) Cache::get($this->captchaCacheKey($key)));

        return $code;
    }

    private function captchaCacheKey(string $key): string
    {
        return 'front_big_agent_captcha_' . sha1($key);
    }

    private function insertBigAgent(int $id, string $username, string $email, string $password): void
    {
        $now = time();
        DB::table('big_agents')->insert([
            'id' => $id,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make($password),
            'sub_agent_ids' => '',
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserAgent(int $userId, string $email, string $password): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make($password),
            'account_type' => 1,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'modern-security-agent',
            'phone' => '1380000' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 1,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 1,
            'comm_rate' => 0.1,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function deleteBigAgent(int $id): void
    {
        DB::table('big_agent_login_logs')->where('big_agent_id', $id)->delete();
        DB::table('big_agents')->where('id', $id)->delete();
    }

    private function deleteUser(int $userId, string $email): void
    {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();
    }
}
