<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 04:26
 */

/**
 * 前台遗留登录 userId 严格校验闭环测试。
 *
 * 文件功能：
 * - 验证遗留登录接口 /user/signIn 拒绝非严格数字的 loginUid（如带字母后缀或小数），
 *   且失败时不产生任何登录状态（会话、认证态、登录日志）。
 * - 验证权限清单文档记录了该校验边界闭环。
 *
 * 适用场景：
 * - 前台遗留登录入口的安全回归测试，防止宽松 userId 解析导致的账号混淆。
 *
 * 入参例子：
 * - loginUid: 412710100abc / 412710100.9（非法）
 * - loginPassword: legacy-login-password
 *
 * 返回值：
 * - 返回 HTTP 200，loginStatus 为 401，session 不含 suser，user 守卫未认证，
 *   user_login_logs 无新增记录。
 *
 * 异常或失败场景：
 * - 非法 userId 登录被拒绝且不留任何登录痕迹。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontLegacyLoginUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证遗留登录拒绝非严格 userId 且不产生登录状态。
    public function test_legacy_login_rejects_non_strict_user_ids_without_creating_login_state(): void
    {
        config(['captcha.characters' => ['A']]);
        $userId = 412710100;
        $this->deleteFixtureRows($userId);
        $this->insertUser($userId);

        foreach (['412710100abc', '412710100.9'] as $invalidAccount) {
            $this->get('/user/captcha')->assertOk();
            $session = $this->app['session.store']->all();
            $response = $this->withSession($session)->postJson('/user/signIn', [
                'loginUid' => $invalidAccount,
                'loginPassword' => 'legacy-login-password',
                'cptcode' => 'aaaa',
            ]);

            $response->assertOk()
                ->assertJsonPath('loginStatus', 401)
                ->assertSessionMissing('suser');
            $this->assertFalse(Auth::guard('user')->check());
        }

        $this->assertSame(0, DB::table('user_login_logs')->where('user_id', $userId)->count());
    }

    // 校验权限清单文档记录了遗留登录 userId 校验边界闭环。
    public function test_final_checklist_records_legacy_login_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 347.', $checklist);
        $this->assertStringContainsString('AuthController::legacySignIn', $checklist);
        $this->assertStringContainsString('user/signIn', $checklist);
        $this->assertStringContainsString('FrontLegacyLoginUserIdValidationClosureModuleTest', $checklist);
    }

    private function insertUser(int $userId): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-legacy-login-strict@example.test',
            'password' => Hash::make('legacy-login-password'),
            'account_type' => 2,
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
            'user_name' => 'legacy-login-strict-user',
            'phone' => '1392710100',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function deleteFixtureRows(int $userId): void
    {
        DB::table('user_login_logs')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', 'front-legacy-login-strict@example.test')->delete();
    }
}
