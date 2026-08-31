<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:44
 */

/**
 * AdminUserUpdateEmailClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新邮箱边界：旧 useremail 落到 user_logins.email 并写审计日志、重复或格式错误邮箱失败关闭不产生部分写入。
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
use Tests\TestCase;

/**
 * 后台普通用户资料编辑邮箱闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 CustomerController::cust_save_info 会读取 useremail 并校验邮箱唯一性。
 * - 新项目用户登录邮箱保存在 user_logins.email，用户详情页已提交 email 字段，后端必须把该字段落到登录表。
 * - 邮箱重复或格式错误时必须失败关闭，不能先写 user_infos 基础资料，避免前端看到部分成功。
 */
class AdminUserUpdateEmailClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_user_update_accepts_legacy_useremail_and_updates_login_email_with_audit_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98728201;
        $this->seedUser($userId, 'Before Email Update', '18828201001', 'before-email-update@example.test');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $userId,
                    'username' => 'After Email Update',
                    'userphoneNo' => '18828201999',
                    'useremail' => 'After-Email-Update@Example.Test',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Email Update',
            'phone' => '86-18828201999',
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'email' => 'after-email-update@example.test',
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $userId)
            ->first();

        $this->assertNotNull($log, 'updateUser 修改邮箱后必须写 operation_logs 审计记录。');
        $this->assertStringContainsString(
            'login.email:before-email-update@example.test->after-email-update@example.test',
            (string) $log->content
        );
    }

    public function test_admin_user_update_rejects_duplicate_email_without_partial_profile_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98728202;
        $otherUserId = 98728203;
        $this->seedUser($userId, 'Before Duplicate Email', '18828202001', 'before-duplicate-email@example.test');
        $this->seedUser($otherUserId, 'Other Duplicate Owner', '18828203001', 'duplicate-email-owner@example.test');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'Should Not Persist',
                'phone' => '18828202999',
                'email' => 'duplicate-email-owner@example.test',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Duplicate Email',
            'phone' => '18828202001',
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'email' => 'before-duplicate-email@example.test',
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_final_checklist_records_admin_user_update_email_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 373.', $checklist);
        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('user_logins.email', $checklist);
        $this->assertStringContainsString('useremail', $checklist);
        $this->assertStringContainsString('AdminUserUpdateEmailClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-email-super',
                'email' => 'admin-user-update-email-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function seedUser(int $userId, string $userName, string $phone, string $email): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->orWhere('email', $email)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => bcrypt('password'),
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
            'phone' => $phone,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'mt4_group' => 'EMAIL-GROUP',
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
