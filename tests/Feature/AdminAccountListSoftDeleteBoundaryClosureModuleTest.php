<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:52
 */

/**
 * 后台管理员列表软删除边界闭包测试。
 *
 * 文件功能：
 * - 验证管理员列表接口（/api/admin/adminList、/api/admin/admins）不返回已软删除（deleted_at 非空）的管理员。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 258 项）。
 *
 * 适用场景：
 * - 后台管理员账号管理模块列表接口的软删除过滤回归测试。
 *
 * 入参例子：
 * - POST /api/admin/adminList
 *   {
 *     "per_page": 10000
 *   }
 *
 * 方法功能：
 * - test_admin_list_endpoints_exclude_soft_deleted_admins：同时插入未删除与已软删除管理员，断言列表只返回未删除记录。
 * - test_final_checklist_records_admin_list_soft_delete_boundary：校验最终清单文档包含第 258 项边界记录。
 *
 * 返回值：
 * - 列表成功返回 code=SUCCESS；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若列表包含已软删除管理员，测试断言失败。
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

class AdminAccountListSoftDeleteBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 同时插入未删除与已软删除管理员，断言列表只返回未删除记录。
     *
     * @return void
     */
    public function test_admin_list_endpoints_exclude_soft_deleted_admins(): void
    {
        $actor = $this->ensureSuperAdmin();
        $activeId = $this->createManagedAdmin('admin-list-active-visible', 'admin-list-active-visible@example.test', null);
        $deletedId = $this->createManagedAdmin('admin-list-soft-deleted', 'admin-list-soft-deleted@example.test', time());

        foreach (['/api/admin/adminList', '/api/admin/admins'] as $endpoint) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post($endpoint, [
                    'per_page' => 10000,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            $rows = collect(data_get($response->json(), 'data.data', []));

            $this->assertNotNull($rows->firstWhere('id', $activeId), $endpoint . ' 必须返回未删除管理员。');
            $this->assertNull($rows->firstWhere('id', $deletedId), $endpoint . ' 不能返回已软删除管理员。');
            $this->assertStringNotContainsString('admin-list-soft-deleted@example.test', $response->getContent());
        }
    }

    /**
     * 校验最终清单文档第 258 项记录了列表软删除边界。
     *
     * @return void
     */
    public function test_final_checklist_records_admin_list_soft_delete_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 258.', $checklist);
        $this->assertStringContainsString('AdminController::index', $checklist);
        $this->assertStringContainsString('/api/admin/adminList', $checklist);
        $this->assertStringContainsString('/api/admin/admins', $checklist);
        $this->assertStringContainsString('deleted_at', $checklist);
        $this->assertStringContainsString('AdminAccountListSoftDeleteBoundaryClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-list-soft-delete-super',
                'email' => 'admin-list-soft-delete-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedAdmin(string $username, string $email, int $deletedAt = null): int
    {
        $now = time();

        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        return (int) DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('list-soft-delete-secret'),
            'mobile' => '13925800000',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deletedAt,
        ]);
    }
}
