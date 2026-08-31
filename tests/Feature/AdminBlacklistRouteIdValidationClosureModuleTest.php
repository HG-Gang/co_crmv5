<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 文件功能：验证黑名单（blacklists）更新、删除接口对非严格路由 ID 的校验边界，
 *           并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/updateBlacklist/{id}、/api/admin/deleteBlacklist/{id}
 *           接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateBlacklist/{id}abc：{name, id_card, email, phone}
 * - POST /api/admin/deleteBlacklist/{id}abc：无请求体
 *
 * 返回值：
 * - 路由 ID 带非数字后缀时返回 code=VALIDATION_FAILED，且黑名单记录保持原样。
 *
 * 异常或失败场景：
 * - 路由 ID 非严格数字（如 {id}abc）时校验失败，不做任何更新或删除。
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

class AdminBlacklistRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 更新黑名单时路由 ID 带非数字后缀应校验失败且记录保持原样。
    public function test_update_blacklist_rejects_non_strict_route_id_without_changing_entry(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedBlacklist('Route Id Blacklist Original', 'ID293001');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateBlacklist/' . $targetId . 'abc', [
                'name' => 'Route Id Blacklist Updated',
                'id_card' => 'ID293999',
                'email' => 'blacklist-updated@example.test',
                'phone' => '13929300999',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $entry = DB::table('blacklists')->where('id', $targetId)->first();

        $this->assertSame('Route Id Blacklist Original', (string) $entry->name);
        $this->assertSame('ID293001', (string) $entry->id_card);
        $this->assertSame('blacklist-original@example.test', (string) $entry->email);
        $this->assertSame('13929300001', (string) $entry->phone);
        $this->assertNull($entry->deleted_at);
    }

    // 删除黑名单时路由 ID 带非数字后缀应校验失败且不删除记录。
    public function test_delete_blacklist_rejects_non_strict_route_id_without_deleting_entry(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedBlacklist('Route Id Blacklist Delete', 'ID293002');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteBlacklist/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $entry = DB::table('blacklists')->where('id', $targetId)->first();

        $this->assertNotNull($entry);
        $this->assertNull($entry->deleted_at);
        $this->assertSame('Route Id Blacklist Delete', (string) $entry->name);
    }

    // 核对最终检查清单文档记录了黑名单路由 ID 校验边界。
    public function test_final_checklist_records_blacklist_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 293.', $checklist);
        $this->assertStringContainsString('BlacklistController::update', $checklist);
        $this->assertStringContainsString('BlacklistController::destroy', $checklist);
        $this->assertStringContainsString('/api/admin/updateBlacklist/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/deleteBlacklist/{id}', $checklist);
        $this->assertStringContainsString('blacklists.id', $checklist);
        $this->assertStringContainsString('AdminBlacklistRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-blacklist-route-id-super',
                'email' => 'admin-blacklist-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedBlacklist(string $name, string $idCard): int
    {
        $now = time();

        DB::table('blacklists')
            ->whereIn('id_card', [$idCard, 'ID293999'])
            ->delete();

        return (int) DB::table('blacklists')->insertGetId([
            'name' => $name,
            'id_card' => $idCard,
            'email' => 'blacklist-original@example.test',
            'phone' => '13929300001',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
