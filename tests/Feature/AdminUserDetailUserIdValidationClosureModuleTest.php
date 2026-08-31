<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户详情接口 user_id 筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id 传入非严格数字时用户详情接口返回校验失败。
 * - 验证校验失败时不返回测试用户的资料信息。
 *
 * 适用场景：
 * - 后台用户详情页面的 user_id 精确查询，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/userDetail，body：{"user_id": "98728001abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
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
use Tests\TestCase;

class AdminUserDetailUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证用户详情对非严格 user_id 返回校验失败且不返回测试用户资料。
    public function test_legacy_user_detail_rejects_non_strict_user_id_without_returning_profile(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98728001;
        $userName = 'Admin User Detail Strict User Id';

        $this->createManagedUser($userId, $userName);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/userDetail', [
                'user_id' => $userId . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString($userName, $response->getContent());
    }

    // 校验最终检查清单文档记录了用户详情 user_id 校验边界。
    public function test_final_checklist_records_admin_user_detail_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 280.', $checklist);
        $this->assertStringContainsString('AdminUserController::userDetail', $checklist);
        $this->assertStringContainsString('/api/admin/userDetail', $checklist);
        $this->assertStringContainsString('user_id', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('AdminUserDetailUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-detail-user-id-super',
                'email' => 'admin-user-detail-user-id-super@example.test',
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
            'email' => 'admin-user-detail-user-id-' . $userId . '@example.test',
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
            'phone' => '188280' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
