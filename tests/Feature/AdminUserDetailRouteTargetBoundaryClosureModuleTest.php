<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:05
 */

/**
 * 后台用户详情路由目标边界闭合测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/users/{user} 以路由参数 {user} 作为目标用户，
 *   忽略请求体伪造的 user_id，防止越权查看其他用户详情。
 * - 验证该边界修复已登记在 docs/admin-backend-blade-permission-final-checklist.md（第 268 项）。
 *
 * 适用场景：
 * - 回归 AdminUserController::userDetail 与 /api/admin/users/{user} 的用户归属边界，
 *   防止详情接口再次回退为按请求体 user_id 查询。
 *
 * 入参例子：
 * - POST /api/admin/users/98726801，body 携带 user_id=98726802（伪造值）；
 *   断言响应 data.user_id 为 98726801（字符串形式，旧兼容 JSON 契约）、
 *   data.user_name 为 'Target Detail User'，且响应不含伪造用户的任何数据。
 *
 * 返回值：
 * - 测试无返回值；断言全部通过即表示闭环：路由参数优先、伪造 user_id 被忽略。
 *
 * 异常或失败场景：
 * - 断言失败意味着详情路由可能被伪造 user_id 劫持（越权风险），
 *   或 checklist 文档未登记该边界修复，需要立即排查。
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

class AdminUserDetailRouteTargetBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rest_detail_route_uses_route_user_and_ignores_spoofed_body_user_id(): void
    {
        $admin = $this->ensureSuperAdmin();
        $targetUserId = 98726801;
        $otherUserId = 98726802;
        $this->createManagedUser($targetUserId, 'Target Detail User');
        $this->createManagedUser($otherUserId, 'Other Detail User');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/users/' . $targetUserId, [
                'user_id' => $otherUserId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.user_id', (string) $targetUserId)
            ->assertJsonPath('data.user_name', 'Target Detail User')
            // 旧兼容 JSON 契约中登录资料 user_id 同样为字符串。
            ->assertJsonPath('data.login.user_id', (string) $targetUserId);

        $this->assertStringNotContainsString('Other Detail User', $response->getContent());
    }

    public function test_final_checklist_records_admin_user_detail_route_target_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 268.', $checklist);
        $this->assertStringContainsString('AdminUserController::userDetail', $checklist);
        $this->assertStringContainsString('/api/admin/users/{user}', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('AdminUserDetailRouteTargetBoundaryClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-detail-route-super',
                'email' => 'admin-user-detail-route-super@example.test',
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
            'email' => 'admin-user-detail-route-' . $userId . '@example.test',
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
            'phone' => '188268' . substr((string) $userId, -5),
            'account_type' => 2,
            'parent_id' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
