<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:06
 */

/**
 * AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新出入金开关边界：接受旧开关字段、非法旧开关值拒绝部分写入。
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
 * 后台普通用户资料编辑出入金开关闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 CustomerController::cust_save_info 使用 isoutmoney 和 isallowmoney 保存出金、入金限制。
 * - 新项目真实字段为 user_infos.is_withdrawal_allowed 和 user_infos.is_deposit_allowed。
 * - 字段值沿用旧项目语义：0 表示允许，1 表示禁止。
 * - 本测试只允许旧字段别名进入该分支，现代敏感字段仍由既有白名单测试保护，避免普通资料编辑接口被任意扩权。
 */
class AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_user_update_accepts_legacy_deposit_and_withdrawal_switch_fields(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727501;
        $this->seedUser($userId, 'Before Switch Update', 0, 0);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $userId,
                    'username' => 'After Switch Update',
                ],
                'isoutmoney' => '1',
                'isallowmoney' => '1',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Switch Update',
            'is_withdrawal_allowed' => 1,
            'is_deposit_allowed' => 1,
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $userId)
            ->first();

        $this->assertNotNull($log, 'updateUser 修改出入金开关后必须写 operation_logs 审计记录。');
        $this->assertStringContainsString('is_withdrawal_allowed:0->1', (string) $log->content);
        $this->assertStringContainsString('is_deposit_allowed:0->1', (string) $log->content);
    }

    public function test_admin_user_update_rejects_invalid_legacy_switch_without_partial_profile_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98727502;
        $this->seedUser($userId, 'Before Invalid Switch', 0, 0);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'Should Not Persist Switch',
                'isoutmoney' => '2',
                'isallowmoney' => '0abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Invalid Switch',
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_final_checklist_records_admin_user_update_deposit_withdrawal_switch_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 375.', $checklist);
        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('isoutmoney', $checklist);
        $this->assertStringContainsString('isallowmoney', $checklist);
        $this->assertStringContainsString('is_withdrawal_allowed', $checklist);
        $this->assertStringContainsString('is_deposit_allowed', $checklist);
        $this->assertStringContainsString('AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-switch-super',
                'email' => 'admin-user-update-switch-super@example.test',
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
     * 创建出入金开关测试用户。
     *
     * 参数含义：
     * - $userId 表示业务用户 ID，用于 user_infos 和 user_logins 关联。
     * - $userName 表示更新前姓名，用来判断失败分支是否出现部分写入。
     * - $withdrawalAllowed 表示 user_infos.is_withdrawal_allowed，0=允许出金，1=禁止出金。
     * - $depositAllowed 表示 user_infos.is_deposit_allowed，0=允许入金，1=禁止入金。
     *
     * @param int $userId 业务用户 ID。
     * @param string $userName 用户姓名。
     * @param int $withdrawalAllowed 出金限制标记。
     * @param int $depositAllowed 入金限制标记。
     * @return void
     */
    private function seedUser(int $userId, string $userName, int $withdrawalAllowed, int $depositAllowed): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-switch-' . $userId . '@example.test',
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
            'phone' => '18827501001',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'mt4_group' => 'SWITCH-GROUP',
            'leverage' => 100,
            'is_withdrawal_allowed' => $withdrawalAllowed,
            'is_deposit_allowed' => $depositAllowed,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
