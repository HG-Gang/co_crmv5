<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户状态变更接口路由目标归属边界的测试。
 *
 * 文件功能：
 * - 验证 PATCH /api/admin/users/{user}/status 接口以路由中的 user id 为目标。
 * - 验证请求体中伪造的 user_id 被忽略，不会影响其他用户状态。
 *
 * 适用场景：
 * - 后台用户启停操作，防止通过伪造 body user_id 误改他人状态。
 *
 * 入参例子：
 * - PATCH /api/admin/users/{targetUserId}/status，body：{"user_id": 其它用户id, "is_enabled": 0}。
 *
 * 返回值：
 * - 操作成功返回 code=ResponseCode::SUCCESS。
 *
 * 异常或失败场景：
 * - body 中伪造 user_id 时仍只修改路由目标用户，其他用户状态不变。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserStatusRouteTargetBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function lockUser($userId)
            {
                return ['status' => 'ok', 'message' => 'locked', 'data' => []];
            }

            public function unlockUser($userId)
            {
                return ['status' => 'ok', 'message' => 'unlocked', 'data' => []];
            }
        });
    }

    // 验证状态变更以路由 user id 为目标并忽略伪造的 body user_id。
    public function test_rest_status_route_uses_route_user_and_ignores_spoofed_body_user_id(): void
    {
        $admin = $this->ensureSuperAdmin();
        $targetUserId = 98726701;
        $otherUserId = 98726702;
        $this->createManagedUser($targetUserId, 1);
        $this->createManagedUser($otherUserId, 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $targetUserId . '/status', [
                'user_id' => $otherUserId,
                'is_enabled' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame(0, (int) DB::table('user_logins')->where('user_id', $targetUserId)->value('is_enabled'));
        $this->assertSame(1, (int) DB::table('user_logins')->where('user_id', $otherUserId)->value('is_enabled'));
    }

    // 校验最终检查清单文档记录了用户状态路由目标边界。
    public function test_final_checklist_records_admin_user_status_route_target_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 267.', $checklist);
        $this->assertStringContainsString('AdminUserController::changeUserStatus', $checklist);
        $this->assertStringContainsString('/api/admin/users/{user}/status', $checklist);
        $this->assertStringContainsString('user_logins.is_enabled', $checklist);
        $this->assertStringContainsString('AdminUserStatusRouteTargetBoundaryClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-status-route-super',
                'email' => 'admin-user-status-route-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedUser(int $userId, int $isEnabled): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-status-route-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => $isEnabled,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'Admin User Status Route ' . $userId,
            'phone' => '188267' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
