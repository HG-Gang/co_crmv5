<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:49
 */

/**
 * AdminUserUpdateLegacyLocalProfileClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新旧本地资料字段边界：性别/礼品/备注字段写入、非法旧本地资料值拒绝部分写入、现代敏感别名仍被忽略。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台普通用户资料编辑旧本地资料字段闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 CustomerController::cust_save_info 会提交 sex、gift_allowed、userremark。
 * - 新项目分别写入 user_infos.gender、user_infos.is_gift_allowed、user_infos.remark。
 * - 这些字段只影响本地资料和礼品入口，不需要伪造 MT4 或短信外部服务。
 */
class AdminUserUpdateLegacyLocalProfileClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_user_update_writes_legacy_gender_gift_and_remark_fields(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727801;
        $this->seedUser($userId, 'Before Local Profile');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $userId,
                    'username' => 'After Local Profile',
                    'gift_allowed' => '1',
                    'userremark' => '旧后台备注已迁移',
                ],
                'sex' => '女',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Local Profile',
            'gender' => 2,
            'is_gift_allowed' => 1,
            'remark' => '旧后台备注已迁移',
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $userId)
            ->first();

        $this->assertNotNull($log, '旧本地资料字段成功更新后必须写入 operation_logs 审计记录。');
        $this->assertStringContainsString('gender:1->2', (string) $log->content);
        $this->assertStringContainsString('is_gift_allowed:0->1', (string) $log->content);
        $this->assertStringContainsString('remark:初始备注->旧后台备注已迁移', (string) $log->content);
    }

    public function test_admin_user_update_rejects_invalid_legacy_local_profile_values_without_partial_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727802;
        $this->seedUser($userId, 'Before Invalid Local Profile');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'Should Not Persist Local Profile',
                'data' => [
                    'gift_allowed' => '2',
                    'userremark' => 'Should Not Persist Remark',
                ],
                'sex' => '未知',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Invalid Local Profile',
            'gender' => 1,
            'is_gift_allowed' => 0,
            'remark' => '初始备注',
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_admin_user_update_still_ignores_modern_local_profile_sensitive_aliases(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727803;
        $this->seedUser($userId, 'Before Modern Alias Ignore');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'After Modern Alias Ignore',
                'gender' => 2,
                'is_gift_allowed' => 1,
                'remark' => 'Modern remark should be ignored',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Modern Alias Ignore',
            'gender' => 1,
            'is_gift_allowed' => 0,
            'remark' => '初始备注',
        ]);
    }

    public function test_final_checklist_records_admin_user_update_legacy_local_profile_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('userremark', $checklist);
        $this->assertStringContainsString('gift_allowed', $checklist);
        $this->assertStringContainsString('user_infos.gender', $checklist);
        $this->assertStringContainsString('user_infos.is_gift_allowed', $checklist);
        $this->assertStringContainsString('AdminUserUpdateLegacyLocalProfileClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-local-profile-super',
                'email' => 'admin-user-update-local-profile-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建旧本地资料字段测试用户，并清理同 user_id 的审计和登录资料。
     */
    private function seedUser(int $userId, string $userName): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-local-profile-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '18827801001',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'mt4_group' => 'LOCAL-PROFILE-GROUP',
            'leverage' => 100,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_gift_allowed' => 0,
            'remark' => '初始备注',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
