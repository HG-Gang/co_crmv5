<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:05
 */

/**
 * 前台个人资料改密所有者边界闭合测试。
 *
 * 文件功能：
 * - 验证现代接口 POST /api/front/profile/password 与旧接口 POST /user/editpsw_save
 *   都忽略请求体伪造的 user_id/userId，只更新当前登录用户自己的密码。
 * - 验证 MT4 密码同步失败时失败关闭：本地旧密码哈希保持不变，
 *   且不影响其他用户（现代接口返回 code = MT4_SYNC_FAILED，旧接口返回 FAIL/FATALCANOTCONNECT）。
 * - 验证改密成功后会话收口：现代接口使 JWT 失效并登出 user guard，
 *   旧接口清除 suser 会话并登出 user guard。
 * - 验证以上边界已登记在 docs/admin-backend-blade-permission-final-checklist.md
 *   （第 252 项改密所有者边界、第 341 项 UserPasswordService）。
 *
 * 适用场景：
 * - 回归 ProfileController::changePassword / user_editpsw_save 与 UserPasswordService，
 *   防止改密越权或 MT4 同步失败时破坏本地凭据。
 *
 * 入参例子：
 * - viewer 与 other 两个用户，请求体中携带 old_password/password/password_confirmation
 *   以及伪造的 user_id/userId（指向 other），断言只更新 viewer 的密码哈希。
 *
 * 返回值：
 * - 测试无返回值；Hash::check 断言当前用户新密码生效、他人密码未变、
 *   会话/jwt_token 已失效即表示闭环。
 *
 * 异常或失败场景：
 * - 断言失败意味着存在改密越权（他人密码被篡改）、MT4 失败时未保留本地哈希，
 *   或改密后会话未失效（旧 token 仍可用），属于安全回归。
 */
namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\TradePasswordGateway;
use App\Facades\Mt4ManagerApi;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontProfilePasswordOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_modern_password_change_ignores_spoofed_user_id_and_updates_current_login_only(): void
    {
        $viewerId = 412520100;
        $otherId = 412520101;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'profile-password-modern-viewer', 'modern-old-password');
        $this->insertUserInfo($otherId, 'profile-password-modern-other', 'modern-other-password');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/password', [
                'old_password' => 'modern-old-password',
                'password' => 'modern-new-password',
                'password_confirmation' => 'modern-new-password',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertTrue(Hash::check('modern-new-password', $this->passwordHashFor($viewerId)));
        $this->assertFalse(Hash::check('modern-old-password', $this->passwordHashFor($viewerId)));
        $this->assertTrue(Hash::check('modern-other-password', $this->passwordHashFor($otherId)));
        $this->assertFalse(Hash::check('modern-new-password', $this->passwordHashFor($otherId)));
    }

    public function test_legacy_password_change_ignores_spoofed_user_id_and_updates_current_login_only(): void
    {
        $viewerId = 412520200;
        $otherId = 412520201;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'profile-password-legacy-viewer', 'legacy-old-password');
        $this->insertUserInfo($otherId, 'profile-password-legacy-other', 'legacy-other-password');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/editpsw_save', [
                'olduserpsw' => 'legacy-old-password',
                'newuserpsw' => 'legacy-new-password',
                'confirmuserpsw' => 'legacy-new-password',
                'userId' => $otherId,
                'user_id' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS');

        $this->assertTrue(Hash::check('legacy-new-password', $this->passwordHashFor($viewerId)));
        $this->assertFalse(Hash::check('legacy-old-password', $this->passwordHashFor($viewerId)));
        $this->assertTrue(Hash::check('legacy-other-password', $this->passwordHashFor($otherId)));
        $this->assertFalse(Hash::check('legacy-new-password', $this->passwordHashFor($otherId)));
    }

    public function test_legacy_password_change_accepts_suser_session_without_user_guard(): void
    {
        $viewerId = 412520250;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'profile-password-legacy-suser', 'legacy-suser-old-password');
        config(['mt4.enabled' => false]);

        $response = $this->withSession([
            'suser' => [
                'user_id' => $viewerId,
                'user_name' => 'profile-password-legacy-suser',
            ],
        ])->postJson('/user/editpsw_save', [
            'olduserpsw' => 'legacy-suser-old-password',
            'newuserpsw' => 'legacy-suser-new-password',
            'confirmuserpsw' => 'legacy-suser-new-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertSessionMissing('suser');
        $this->assertTrue(Hash::check('legacy-suser-new-password', $this->passwordHashFor($viewerId)));
    }

    public function test_modern_password_change_preserves_all_local_hashes_when_mt4_sync_fails(): void
    {
        $viewerId = 412520300;
        $otherId = 412520301;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'profile-password-modern-mt4-viewer', 'modern-mt4-old-password');
        $this->insertUserInfo($otherId, 'profile-password-modern-mt4-other', 'modern-mt4-other-password');
        config(['mt4.enabled' => true]);
        $this->bindVerifiedPasswordGateway();
        Mt4ManagerApi::shouldReceive('changePassword')
            ->once()
            ->with($viewerId, 'modern-mt4-new-password')
            ->andReturn(['status' => 'error', 'message' => 'MT4 unavailable']);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/profile/password', [
                'old_password' => 'modern-mt4-old-password',
                'password' => 'modern-mt4-new-password',
                'password_confirmation' => 'modern-mt4-new-password',
                'user_id' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertTrue(Hash::check('modern-mt4-old-password', $this->passwordHashFor($viewerId)));
        $this->assertTrue(Hash::check('modern-mt4-other-password', $this->passwordHashFor($otherId)));
    }

    public function test_legacy_password_change_preserves_local_hash_when_mt4_sync_fails(): void
    {
        $viewerId = 412520400;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'profile-password-legacy-mt4-viewer', 'legacy-mt4-old-password');
        config(['mt4.enabled' => true]);
        $this->bindVerifiedPasswordGateway();
        Mt4ManagerApi::shouldReceive('changePassword')
            ->once()
            ->with($viewerId, 'legacy-mt4-new-password')
            ->andReturn(['status' => 'error', 'message' => 'MT4 unavailable']);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/editpsw_save', [
                'olduserpsw' => 'legacy-mt4-old-password',
                'newuserpsw' => 'legacy-mt4-new-password',
                'confirmuserpsw' => 'legacy-mt4-new-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'FATALCANOTCONNECT');
        $this->assertTrue(Hash::check('legacy-mt4-old-password', $this->passwordHashFor($viewerId)));
    }

    public function test_modern_password_change_invalidates_jwt_and_logs_out_user_guard(): void
    {
        $viewerId = 412520500;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'profile-password-modern-session-viewer', 'modern-session-old-password');
        config(['mt4.enabled' => false]);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $jwtService = app(JwtService::class);
        $token = $jwtService->generateToken(['sub' => $login->getAuthIdentifier(), 'guard' => 'user']);
        $payload = $jwtService->getPayload($token);

        $response = $this->withToken($token)
            ->postJson('/api/front/profile/password', [
                'old_password' => 'modern-session-old-password',
                'password' => 'modern-session-new-password',
                'password_confirmation' => 'modern-session-new-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);
        $this->assertTrue(Cache::has('jwt_blacklist:' . $payload->jti));
        $this->assertFalse(Cache::has('sso:user:' . $login->getAuthIdentifier()));
        $this->assertFalse(Auth::guard('user')->check());
    }

    public function test_legacy_password_change_logs_out_user_guard_and_removes_suser_session(): void
    {
        $viewerId = 412520600;

        $this->deleteFixtureRows([$viewerId]);
        $this->insertUserInfo($viewerId, 'profile-password-legacy-session-viewer', 'legacy-session-old-password');
        config(['mt4.enabled' => false]);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withSession(['suser' => ['user_id' => $viewerId]])
            ->postJson('/user/editpsw_save', [
                'olduserpsw' => 'legacy-session-old-password',
                'newuserpsw' => 'legacy-session-new-password',
                'confirmuserpsw' => 'legacy-session-new-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertSessionMissing('suser');
        $this->assertFalse(Auth::guard('user')->check());
    }

    public function test_final_checklist_records_profile_password_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 252.', $checklist);
        $this->assertStringContainsString('ProfileController::changePassword', $checklist);
        $this->assertStringContainsString('ProfileController::user_editpsw_save', $checklist);
        $this->assertStringContainsString('/api/front/profile/password', $checklist);
        $this->assertStringContainsString('user/editpsw_save', $checklist);
        $this->assertStringContainsString('FrontProfilePasswordOwnerBoundaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('## 341.', $checklist);
        $this->assertStringContainsString('UserPasswordService', $checklist);
        $this->assertStringContainsString('jwt_token', $checklist);
        $this->assertStringContainsString('suser', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, string $password): void
    {
        $now = time();
        $email = 'front-profile-password-boundary-' . $userId . '@example.test';

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make($password),
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
            'user_name' => $userName,
            'phone' => '1392520' . substr((string) $userId, -4),
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

    /**
     * @param array<int, int> $userIds
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }

    private function passwordHashFor(int $userId): string
    {
        return (string) DB::table('user_logins')
            ->where('user_id', $userId)
            ->value('password');
    }

    /**
     * 绑定旧密码远端校验成功结果，使测试只验证后续 MT4 重置密码失败分支。
     */
    private function bindVerifiedPasswordGateway(): void
    {
        $this->app->instance(TradePasswordGateway::class, new class implements TradePasswordGateway {
            /**
             * 返回明确验证通过，允许控制器继续调用密码重置接口。
             */
            public function verify(int $userId, string $password): TradePasswordVerificationResult
            {
                return TradePasswordVerificationResult::verified();
            }
        });
    }
}
