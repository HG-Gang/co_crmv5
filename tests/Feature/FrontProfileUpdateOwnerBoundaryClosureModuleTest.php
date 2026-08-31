<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/09
 * Time: 05:37
 */

/**
 * FrontProfileUpdateOwnerBoundaryClosureModuleTest
 *
 * 文件功能：
 * - 验证前台资料更新归属边界：忽略伪造 user_id 仅更新当前登录用户，并记录到最终清单。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontProfileUpdateOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_profile_update_ignores_spoofed_user_id_and_updates_current_user_only(): void
    {
        $viewerId = 412230100;
        $otherId = 412230101;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'profile-update-boundary-viewer', '13922300100', 'Viewer Original Address');
        $this->insertUserInfo($otherId, 'profile-update-boundary-other', '13922300101', 'Other Original Address');
        $this->insertUserAuth($viewerId, 'ID412230100');
        $this->insertUserAuth($otherId, 'ID412230101');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->patchJson('/api/front/profile', [
                'user_name' => 'profile-update-boundary-renamed',
                'phone' => '13922300999',
                'id_card_no' => 'ID412230999',
                'gender' => 2,
                'address' => 'Viewer Updated Address',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'user_name' => 'profile-update-boundary-renamed',
            'phone' => '13922300999',
            'gender' => 2,
            'address' => 'Viewer Updated Address',
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $viewerId,
            'id_card_no' => 'ID412230999',
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'user_name' => 'profile-update-boundary-other',
            'phone' => '13922300101',
            'gender' => 1,
            'address' => 'Other Original Address',
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $otherId,
            'id_card_no' => 'ID412230101',
        ]);
        $this->assertDatabaseMissing('user_infos', [
            'user_id' => $otherId,
            'user_name' => 'profile-update-boundary-renamed',
        ]);
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $otherId,
            'id_card_no' => 'ID412230999',
        ]);
    }

    public function test_final_checklist_records_profile_update_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 223.', $checklist);
        $this->assertStringContainsString('ProfileController::updateProfile', $checklist);
        $this->assertStringContainsString('/api/front/profile', $checklist);
        $this->assertStringContainsString('PATCH', $checklist);
        $this->assertStringContainsString('FrontProfileUpdateOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, string $phone, string $address): void
    {
        $now = time();
        $email = 'front-profile-update-boundary-' . $userId . '@example.test';

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
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
            'phone' => $phone,
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
            'address' => $address,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserAuth(int $userId, string $idCardNo): void
    {
        $now = time();

        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'id_card_no' => $idCardNo,
            'id_card_status' => 2,
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
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
