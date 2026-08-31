<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户更新接口字段白名单机制的功能测试。
 *
 * 文件功能：
 * - 验证 updateUser 接口只允许写入 user_name、phone 等基础资料字段。
 * - 验证 user_id、account_type、parent_id、auth_status、total_funds、is_deposit_allowed、is_withdrawal_allowed 等敏感字段被忽略。
 *
 * 适用场景：
 * - 后台用户编辑页面，防止通过伪造字段越权修改敏感属性。
 *
 * 入参例子：
 * - PATCH /api/admin/users/{userId}，body：{"id": 123456, "user_id": 99999999, "user_name": "...", "phone": "...", ...敏感字段}。
 *
 * 返回值：
 * - 更新成功返回 code=ResponseCode::UPDATED。
 *
 * 异常或失败场景：
 * - 伪造的 user_id 不会创建或更新其他用户记录；敏感字段保持原值。
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

class AdminUserUpdateFieldWhitelistClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证用户更新只写入基础资料字段并忽略伪造的 id、user_id 及敏感字段。
    public function test_update_user_only_writes_basic_profile_fields_and_ignores_sensitive_fields(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726601;
        $this->createManagedUser($userId);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'id' => 123456,
                'user_id' => 99999999,
                'user_name' => 'Updated Basic Name',
                'phone' => '18826601999',
                'account_type' => 1,
                'parent_id' => 12345,
                'auth_status' => 1,
                'total_funds' => 999999,
                'is_deposit_allowed' => 0,
                'is_withdrawal_allowed' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $record = DB::table('user_infos')->where('user_id', $userId)->first();

        $this->assertSame('Updated Basic Name', (string) $record->user_name);
        $this->assertSame('18826601999', (string) $record->phone);
        $this->assertSame(2, (int) $record->account_type);
        $this->assertSame(0, (int) $record->parent_id);
        $this->assertSame(0, (int) $record->auth_status);
        $this->assertSame('10.00', number_format((float) $record->total_funds, 2, '.', ''));
        $this->assertSame(1, (int) $record->is_deposit_allowed);
        $this->assertSame(1, (int) $record->is_withdrawal_allowed);
        $this->assertFalse(DB::table('user_infos')->where('user_id', 99999999)->exists());
    }

    // 校验最终检查清单文档记录了用户更新字段白名单边界。
    public function test_final_checklist_records_admin_user_update_field_whitelist_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 266.', $checklist);
        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('/api/admin/users/{user}', $checklist);
        $this->assertStringContainsString('user_name', $checklist);
        $this->assertStringContainsString('phone', $checklist);
        $this->assertStringContainsString('AdminUserUpdateFieldWhitelistClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-super',
                'email' => 'admin-user-update-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedUser(int $userId): void
    {
        $now = time();

        DB::table('user_infos')->whereIn('user_id', [$userId, 99999999])->delete();
        DB::table('user_logins')->whereIn('user_id', [$userId, 99999999])->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-' . $userId . '@example.test',
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
            'user_name' => 'Original Basic Name',
            'phone' => '18826601001',
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'total_funds' => 10,
            'is_deposit_allowed' => 1,
            'is_withdrawal_allowed' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
