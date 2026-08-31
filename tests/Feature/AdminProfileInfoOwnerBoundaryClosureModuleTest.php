<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台管理员个人资料信息接口的归属边界测试。
 *
 * 文件功能：
 * - 验证 profileInfo 接口始终返回当前登录管理员的信息，忽略请求中伪造的 id/admin_id。
 * - 验证响应数据不泄露 password 密码哈希及他人邮箱。
 *
 * 适用场景：
 * - 后台个人中心“我的资料”展示，防止越权查看他人资料。
 *
 * 入参例子：
 * - POST /api/admin/profileInfo，body：{"id": 其它管理员id, "admin_id": 其它管理员id}。
 *
 * 返回值：
 * - 成功返回 code=ResponseCode::SUCCESS，data 为当前管理员信息。
 *
 * 异常或失败场景：
 * - 伪造目标管理员 id 时仍返回当前管理员数据，且响应不含密码哈希。
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

class AdminProfileInfoOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证资料信息接口忽略伪造目标、只返回当前管理员并隐藏密码哈希。
    public function test_profile_info_returns_current_admin_and_hides_password(): void
    {
        $current = $this->createManagedAdmin(
            'admin-profile-info-current',
            'admin-profile-info-current@example.test',
            'current-secret',
            '13926100001'
        );
        $other = $this->createManagedAdmin(
            'admin-profile-info-other',
            'admin-profile-info-other@example.test',
            'other-secret',
            '13926100002'
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($current, 'admin')
            ->post('/api/admin/profileInfo', [
                'id' => $other->id,
                'admin_id' => $other->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.id', $current->id)
            ->assertJsonPath('data.username', 'admin-profile-info-current')
            ->assertJsonPath('data.email', 'admin-profile-info-current@example.test');

        $payload = $response->json();
        $this->assertArrayNotHasKey('password', $payload['data'] ?? []);

        $content = $response->getContent();
        $currentHash = (string) DB::table('admins')->where('id', $current->id)->value('password');
        $otherHash = (string) DB::table('admins')->where('id', $other->id)->value('password');

        $this->assertStringNotContainsString('admin-profile-info-other@example.test', $content);
        $this->assertStringNotContainsString($currentHash, $content);
        $this->assertStringNotContainsString($otherHash, $content);
    }

    // 校验最终检查清单文档记录了个人资料信息归属边界。
    public function test_final_checklist_records_admin_profile_info_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 261.', $checklist);
        $this->assertStringContainsString('AuthController::profileInfo', $checklist);
        $this->assertStringContainsString('/api/admin/profileInfo', $checklist);
        $this->assertStringContainsString('admins.password', $checklist);
        $this->assertStringContainsString('AdminProfileInfoOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function createManagedAdmin(string $username, string $email, string $password, string $mobile): Admin
    {
        $now = time();

        DB::table('admins')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        $id = DB::table('admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'mobile' => $mobile,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($id);
    }
}
