<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证分组配置（group_configs）更新、删除接口对非严格路由 ID 的
 *           校验边界，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/updateGroupConfig/{id}、/api/admin/deleteGroupConfig/{id}
 *           接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateGroupConfig/{id}abc：{group_name, radix, category, ...}
 * - POST /api/admin/deleteGroupConfig/{id}abc：无请求体
 *
 * 返回值：
 * - 路由 ID 带非数字后缀时返回 code=VALIDATION_FAILED，配置保持原样。
 *
 * 异常或失败场景：
 * - 路由 ID 非严格数字（如 {id}abc）时校验失败，不做更新或删除。
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

class AdminGroupConfigRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 更新分组配置时路由 ID 带非数字后缀应校验失败且配置保持原样。
    public function test_update_group_config_rejects_non_strict_route_id_without_changing_config(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedGroupConfig('Route Id Group Original');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateGroupConfig/' . $targetId . 'abc', [
                'group_name' => 'Route Id Group Updated',
                'radix' => 88,
                'category' => 1,
                'has_commission' => 1,
                'is_enabled' => 0,
                'is_ecn' => 1,
                'is_default' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $config = DB::table('group_configs')->where('id', $targetId)->first();

        $this->assertSame('Route Id Group Original', (string) $config->name);
        $this->assertSame('50.00', number_format((float) $config->radix, 2, '.', ''));
        $this->assertSame(2, (int) $config->category);
        $this->assertSame(0, (int) $config->has_commission);
        $this->assertSame(1, (int) $config->is_enabled);
        $this->assertSame(0, (int) $config->is_ecn);
        $this->assertSame(0, (int) $config->is_default);
    }

    // 删除分组配置时路由 ID 带非数字后缀应校验失败且不删除配置。
    public function test_delete_group_config_rejects_non_strict_route_id_without_deleting_config(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedGroupConfig('Route Id Group Delete');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteGroupConfig/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $config = DB::table('group_configs')->where('id', $targetId)->first();

        $this->assertNotNull($config);
        $this->assertNull($config->deleted_at);
        $this->assertSame('Route Id Group Delete', (string) $config->name);
    }

    // 核对最终检查清单文档记录了分组配置路由 ID 校验边界。
    public function test_final_checklist_records_group_config_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 288.', $checklist);
        $this->assertStringContainsString('GroupConfigController::update', $checklist);
        $this->assertStringContainsString('GroupConfigController::destroy', $checklist);
        $this->assertStringContainsString('/api/admin/updateGroupConfig/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/deleteGroupConfig/{id}', $checklist);
        $this->assertStringContainsString('group_configs.id', $checklist);
        $this->assertStringContainsString('AdminGroupConfigRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-group-config-route-id-super',
                'email' => 'admin-group-config-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedGroupConfig(string $name): int
    {
        $now = time();

        DB::table('group_configs')->where('name', $name)->delete();

        return (int) DB::table('group_configs')->insertGetId([
            'name' => $name,
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
