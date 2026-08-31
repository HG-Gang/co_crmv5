<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证后台大代理（big_agents）创建、更新、删除接口对 legacy 状态字段
 *           与路由 ID 的严格校验边界，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/createBigAgent、/api/admin/updateBigAgent/{id}、
 *           /api/admin/deleteBigAgent/{id} 接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/createBigAgent：{username, password, status}
 * - POST /api/admin/updateBigAgent/{id}：{username, status}
 * - POST /api/admin/deleteBigAgent/{id}：无请求体
 *
 * 返回值：
 * - 校验失败时统一返回 code=VALIDATION_FAILED，且不产生任何数据变更。
 *
 * 异常或失败场景：
 * - status 传入非严格数字（如 '1abc'）或路由 ID 带非数字后缀（如 {id}abc）时校验失败。
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

class AdminBigAgentIdStatusValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 创建大代理时传入非法 legacy 状态值应校验失败且不写入账号。
    public function test_create_big_agent_rejects_invalid_legacy_status_without_writing_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $username = 'admin-big-agent-invalid-status-create';

        DB::table('big_agents')->where('username', $username)->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createBigAgent', [
                'username' => $username,
                'password' => 'big-agent-secret',
                'status' => '1abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertFalse(DB::table('big_agents')->where('username', $username)->exists());
    }

    // 更新大代理时传入非法 legacy 状态值应校验失败且账号保持原样。
    public function test_update_big_agent_rejects_invalid_legacy_status_without_changing_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedBigAgent(
            'admin-big-agent-invalid-status-update',
            'admin-big-agent-invalid-status-update@example.test',
            1
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateBigAgent/' . $targetId, [
                'username' => 'admin-big-agent-invalid-status-updated',
                'status' => '1abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $target = DB::table('big_agents')->where('id', $targetId)->first();

        $this->assertSame('admin-big-agent-invalid-status-update', (string) $target->username);
        $this->assertSame(1, (int) $target->is_enabled);
    }

    // 更新大代理时路由 ID 带非数字后缀应校验失败且账号保持原样。
    public function test_update_big_agent_rejects_non_strict_route_id_without_changing_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedBigAgent(
            'admin-big-agent-route-id-update',
            'admin-big-agent-route-id-update@example.test',
            1
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateBigAgent/' . $targetId . 'abc', [
                'username' => 'admin-big-agent-route-id-updated',
                'is_enabled' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $target = DB::table('big_agents')->where('id', $targetId)->first();

        $this->assertSame('admin-big-agent-route-id-update', (string) $target->username);
        $this->assertSame(1, (int) $target->is_enabled);
    }

    // 删除大代理时路由 ID 带非数字后缀应校验失败且不删除账号。
    public function test_delete_big_agent_rejects_non_strict_route_id_without_deleting_account(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedBigAgent(
            'admin-big-agent-route-id-delete',
            'admin-big-agent-route-id-delete@example.test',
            1
        );

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteBigAgent/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $target = DB::table('big_agents')->where('id', $targetId)->first();

        $this->assertNotNull($target);
        $this->assertNull($target->deleted_at);
        $this->assertSame('admin-big-agent-route-id-delete', (string) $target->username);
    }

    // 核对最终检查清单文档记录了大代理 ID/状态校验边界。
    public function test_final_checklist_records_big_agent_id_status_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 285.', $checklist);
        $this->assertStringContainsString('BigAgentController::store', $checklist);
        $this->assertStringContainsString('BigAgentController::update', $checklist);
        $this->assertStringContainsString('BigAgentController::destroy', $checklist);
        $this->assertStringContainsString('/api/admin/createBigAgent', $checklist);
        $this->assertStringContainsString('/api/admin/updateBigAgent/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/deleteBigAgent/{id}', $checklist);
        $this->assertStringContainsString('big_agents.id', $checklist);
        $this->assertStringContainsString('big_agents.is_enabled', $checklist);
        $this->assertStringContainsString('AdminBigAgentIdStatusValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-big-agent-validation-super',
                'email' => 'admin-big-agent-validation-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedBigAgent(string $username, string $email, int $isEnabled): int
    {
        $now = time();

        DB::table('big_agents')
            ->where('username', $username)
            ->orWhere('email', $email)
            ->delete();

        return (int) DB::table('big_agents')->insertGetId([
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('big-agent-secret'),
            'sub_agent_ids' => '',
            'is_enabled' => $isEnabled,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
