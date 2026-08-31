<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/10
 * Time: 17:56
 */

/**
 * AdminLegacyLoginCaptchaClosureTest
 *
 * 文件功能：
 * - 验证旧后台登录验证码闭环：缺失/错误验证码在查询凭据前拒绝、成功登录更新元数据与审计、错密码/禁用管理员不改动数据，现代 API 保持仅账号密码契约。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 旧后台登录验证码闭环测试。
 *
 * 旧项目 LoginController::logon 在查询管理员前无条件调用图形验证码校验，
 * 因此兼容入口必须拒绝缺失/错误验证码；现代 API 仍保留独立的 username/password 契约。
 */
class AdminLegacyLoginCaptchaClosureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_login_rejects_missing_captcha_before_credentials_lookup(): void
    {
        $response = $this->postJson('/index/admin/logon', [
            'loginUid' => 'not-used@example.test',
            'loginPassword' => 'wrong-password',
            'cptcode' => '',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('message', __('auth.invalid_captcha'));
    }

    public function test_legacy_login_accepts_old_fields_when_session_captcha_matches(): void
    {
        $adminId = 986901;
        $this->deleteAdmin($adminId);
        $now = time();

        DB::table('admins')->insert([
            'id' => $adminId,
            'role_id' => null,
            'mobile' => null,
            'email' => 'legacy-captcha-' . $adminId . '@example.test',
            'username' => 'legacy-captcha-' . $adminId,
            'password' => bcrypt('legacy-captcha-password'),
            'login_count' => 0,
            'last_login_ip' => null,
            'last_login_at' => null,
            'last_login_address' => null,
            'status' => 1,
            'jwt_token_id' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->withSession([
            'legacy_admin_captcha_code' => 'ABCD',
        ])->postJson('/index/admin/logon', [
            'loginUid' => 'legacy-captcha-' . $adminId,
            'loginPassword' => 'legacy-captcha-password',
            'cptcode' => 'abcd',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.token_type', 'Bearer');
        $this->assertNotEmpty($response->json('data.access_token'));
        $response->assertSessionMissing('legacy_admin_captcha_code');

        $this->postJson('/index/admin/logon', [
            'loginUid' => 'legacy-captcha-' . $adminId,
            'loginPassword' => 'legacy-captcha-password',
            'cptcode' => 'abcd',
        ])->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('message', __('auth.invalid_captcha'));
    }

    public function test_successful_legacy_login_updates_metadata_count_and_one_audit_row(): void
    {
        $adminId = 986904;
        $this->insertAdmin($adminId, 7, 1, '10.0.0.1', '2026-08-01 10:00:00');
        $beforeAuditCount = DB::table('admin_login_logs')->where('admin_id', $adminId)->count();
        $requestStartedAt = time();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.42'])
            ->withSession(['legacy_admin_captcha_code' => 'ABCD'])
            ->postJson('/index/admin/logon', [
                'loginUid' => 'legacy-captcha-' . $adminId,
                'loginPassword' => 'legacy-captcha-password',
                'cptcode' => 'abcd',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.token_type', 'Bearer');
        $requestFinishedAt = time();

        $admin = DB::table('admins')->where('id', $adminId)->first();
        $this->assertSame(8, (int) $admin->login_count);
        $this->assertSame('127.0.0.42', $admin->last_login_ip);
        $this->assertNotSame('2026-08-01 10:00:00', (string) $admin->last_login_at);
        $lastLoginTimestamp = strtotime((string) $admin->last_login_at);
        $this->assertNotFalse($lastLoginTimestamp);
        $this->assertGreaterThanOrEqual($requestStartedAt, $lastLoginTimestamp);
        $this->assertLessThanOrEqual($requestFinishedAt, $lastLoginTimestamp);
        $this->assertSame(
            $beforeAuditCount + 1,
            DB::table('admin_login_logs')->where('admin_id', $adminId)->count()
        );
    }

    public function test_legacy_login_rejects_wrong_password_without_mutating_admin_or_audit(): void
    {
        $adminId = 986905;
        $this->insertAdmin($adminId, 9, 1, '10.0.0.2', '2026-08-02 10:00:00');
        $before = DB::table('admins')->where('id', $adminId)->first();
        $beforeAuditCount = DB::table('admin_login_logs')->where('admin_id', $adminId)->count();

        $response = $this->withSession(['legacy_admin_captcha_code' => 'ABCD'])
            ->postJson('/index/admin/logon', [
                'loginUid' => 'legacy-captcha-' . $adminId,
                'loginPassword' => 'wrong-password',
                'cptcode' => 'abcd',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED)
            ->assertJsonPath('message', __('admin.invalid_credentials'));
        $after = DB::table('admins')->where('id', $adminId)->first();
        $this->assertSame((int) $before->login_count, (int) $after->login_count);
        $this->assertSame($before->last_login_ip, $after->last_login_ip);
        $this->assertSame($before->last_login_at, $after->last_login_at);
        $this->assertSame(
            $beforeAuditCount,
            DB::table('admin_login_logs')->where('admin_id', $adminId)->count()
        );
        $response->assertSessionMissing('legacy_admin_captcha_code');
    }

    public function test_legacy_login_rejects_disabled_admin_without_mutating_admin_or_audit(): void
    {
        $adminId = 986906;
        $this->insertAdmin($adminId, 4, 0, '10.0.0.3', '2026-08-03 10:00:00');
        $before = DB::table('admins')->where('id', $adminId)->first();
        $beforeAuditCount = DB::table('admin_login_logs')->where('admin_id', $adminId)->count();

        $response = $this->withSession(['legacy_admin_captcha_code' => 'ABCD'])
            ->postJson('/index/admin/logon', [
                'loginUid' => 'legacy-captcha-' . $adminId,
                'loginPassword' => 'legacy-captcha-password',
                'cptcode' => 'abcd',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED)
            ->assertJsonPath('message', __('admin.account_disabled'));
        $after = DB::table('admins')->where('id', $adminId)->first();
        $this->assertSame((int) $before->login_count, (int) $after->login_count);
        $this->assertSame($before->last_login_ip, $after->last_login_ip);
        $this->assertSame($before->last_login_at, $after->last_login_at);
        $this->assertSame(
            $beforeAuditCount,
            DB::table('admin_login_logs')->where('admin_id', $adminId)->count()
        );
        $response->assertSessionMissing('legacy_admin_captcha_code');
    }

    public function test_legacy_login_rejects_wrong_captcha_without_writing_login_audit(): void
    {
        $adminId = 986903;
        $this->deleteAdmin($adminId);
        $now = time();

        DB::table('admins')->insert([
            'id' => $adminId,
            'role_id' => null,
            'mobile' => null,
            'email' => 'legacy-captcha-' . $adminId . '@example.test',
            'username' => 'legacy-captcha-' . $adminId,
            'password' => bcrypt('legacy-captcha-password'),
            'login_count' => 0,
            'last_login_ip' => null,
            'last_login_at' => null,
            'last_login_address' => null,
            'status' => 1,
            'jwt_token_id' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $before = DB::table('admin_login_logs')->where('admin_id', $adminId)->count();
        $adminBefore = DB::table('admins')->where('id', $adminId)->first();
        $this->withSession(['legacy_admin_captcha_code' => 'ABCD'])
            ->postJson('/index/admin/logon', [
                'loginUid' => 'legacy-captcha-' . $adminId,
                'loginPassword' => 'legacy-captcha-password',
                'cptcode' => 'WRONG',
            ])->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('message', __('auth.invalid_captcha'));

        $this->assertSame($before, DB::table('admin_login_logs')->where('admin_id', $adminId)->count());
        $adminAfter = DB::table('admins')->where('id', $adminId)->first();
        $this->assertSame((int) $adminBefore->login_count, (int) $adminAfter->login_count);
        $this->assertSame($adminBefore->last_login_ip, $adminAfter->last_login_ip);
        $this->assertSame($adminBefore->last_login_at, $adminAfter->last_login_at);
    }

    public function test_legacy_login_page_exposes_captcha_only_for_legacy_entrypoint(): void
    {
        $legacy = $this->get('/index/admin/login')->assertOk();
        $legacy->assertViewIs('admin_layui::auth.login')
            ->assertSee('data-legacy-admin-login="1"', false)
            ->assertSee('name="cptcode"', false)
            ->assertSee('/index/admin/captcha', false)
            ->assertSee('/index/admin/logon', false);

        $modern = $this->get('/admin/login')->assertOk();
        $modern->assertViewIs('admin_layui::auth.login')
            ->assertDontSee('data-legacy-admin-login="1"', false)
            ->assertDontSee('name="cptcode"', false)
            ->assertSee('/api/admin/login', false);
    }

    public function test_modern_api_login_remains_password_only(): void
    {
        $adminId = 986902;
        $this->deleteAdmin($adminId);
        $now = time();

        DB::table('admins')->insert([
            'id' => $adminId,
            'role_id' => null,
            'mobile' => null,
            'email' => 'modern-login-' . $adminId . '@example.test',
            'username' => 'modern-login-' . $adminId,
            'password' => bcrypt('modern-login-password'),
            'login_count' => 0,
            'last_login_ip' => null,
            'last_login_at' => null,
            'last_login_address' => null,
            'status' => 1,
            'jwt_token_id' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'username' => 'modern-login-' . $adminId,
            'password' => 'modern-login-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    private function deleteAdmin(int $adminId): void
    {
        DB::table('admins')->where('id', $adminId)->delete();
    }

    private function insertAdmin(
        int $adminId,
        int $loginCount,
        int $status,
        ?string $lastLoginIp,
        ?string $lastLoginAt
    ): void {
        $this->deleteAdmin($adminId);
        $now = time();

        DB::table('admins')->insert([
            'id' => $adminId,
            'role_id' => null,
            'mobile' => null,
            'email' => 'legacy-captcha-' . $adminId . '@example.test',
            'username' => 'legacy-captcha-' . $adminId,
            'password' => bcrypt('legacy-captcha-password'),
            'login_count' => $loginCount,
            'last_login_ip' => $lastLoginIp,
            'last_login_at' => $lastLoginAt,
            'last_login_address' => null,
            'status' => $status,
            'jwt_token_id' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
