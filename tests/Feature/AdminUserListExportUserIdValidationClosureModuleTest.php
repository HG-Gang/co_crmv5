<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户列表与导出接口 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时用户列表与导出接口均返回校验失败。
 * - 验证校验失败时不返回测试用户、导出响应保持 JSON 而非流式 CSV。
 *
 * 适用场景：
 * - 后台用户管理页面的用户 id 精确筛选与导出，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/userList，body：{"user_id": "98728301abc", "limit": 5}。
 * - POST /api/admin/exportUsers，body：{"user_id": "98728302abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED，导出不产生 StreamedResponse。
 *
 * 异常或失败场景：
 * - user_id 非严格整数时接口拒绝查询并返回校验失败响应。
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
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AdminUserListExportUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证用户列表对非严格 user_id 筛选返回校验失败且不返回测试用户。
    public function test_user_list_rejects_non_strict_user_id_without_returning_user_row(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98728301;
        $userName = 'Admin User List Strict User Id';

        $this->createManagedUser($userId, $userName);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/userList', [
                'user_id' => $userId . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString($userName, $response->getContent());
    }

    // 验证用户导出对非严格 user_id 筛选返回校验失败且不流式输出用户数据。
    public function test_user_export_rejects_non_strict_user_id_without_streaming_user_row(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98728302;
        $userName = 'Admin User Export Strict User Id';

        $this->createManagedUser($userId, $userName);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/exportUsers', [
                'user_id' => $userId . 'abc',
            ]);

        $this->assertNotInstanceOf(StreamedResponse::class, $response->baseResponse);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString($userName, $response->getContent());
    }

    // 校验最终检查清单文档记录了用户列表导出 user_id 校验边界。
    public function test_final_checklist_records_admin_user_list_export_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 283.', $checklist);
        $this->assertStringContainsString('AdminUserController::filteredUserQuery', $checklist);
        $this->assertStringContainsString('/api/admin/userList', $checklist);
        $this->assertStringContainsString('/api/admin/exportUsers', $checklist);
        $this->assertStringContainsString('user_id', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('AdminUserListExportUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-list-export-user-id-super',
                'email' => 'admin-user-list-export-user-id-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedUser(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-list-export-user-id-' . $userId . '@example.test',
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
            'phone' => '188283' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
