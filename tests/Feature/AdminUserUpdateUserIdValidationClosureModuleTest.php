<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 管理员更新用户信息时 user_id 参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 验证 /api/admin/updateUser 接口对非严格 user_id（如带字母后缀）返回校验失败。
 * - 验证校验失败时不会写入或修改 user_infos 中的用户资料。
 * - 验证最终清单文档已记录该校验边界。
 *
 * 适用场景：
 * - 管理员后台用户资料更新接口的入参安全回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateUser
 *   user_id: 98728101abc（非严格 user_id）
 *   user_name: Updated By Non Strict Id
 *   phone: 18828101999
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 VALIDATION_FAILED。
 * - 数据库 user_infos 中原用户资料保持不变。
 *
 * 异常或失败场景：
 * - 若接口放行非严格 user_id 或写入资料，断言失败。
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

class AdminUserUpdateUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证旧版用户更新接口拒绝非严格 user_id，且不修改 user_infos 资料。
     */
    public function test_legacy_user_update_rejects_non_strict_user_id_without_writing_profile(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98728101;

        $this->createManagedUser($userId, 'Original Strict Update Name', '18828101001');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateUser', [
                'user_id' => $userId . 'abc',
                'user_name' => 'Updated By Non Strict Id',
                'phone' => '18828101999',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $record = DB::table('user_infos')->where('user_id', $userId)->first();
        $this->assertSame('Original Strict Update Name', (string) $record->user_name);
        $this->assertSame('18828101001', (string) $record->phone);
    }

    /**
     * 验证最终清单文档已记录管理员更新用户 user_id 校验边界（## 281）。
     */
    public function test_final_checklist_records_admin_user_update_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 281.', $checklist);
        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('/api/admin/updateUser', $checklist);
        $this->assertStringContainsString('user_id', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('AdminUserUpdateUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-user-id-super',
                'email' => 'admin-user-update-user-id-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedUser(int $userId, string $userName, string $phone): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-user-id-' . $userId . '@example.test',
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
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
