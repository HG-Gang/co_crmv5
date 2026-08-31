<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/12
 * Time: 13:13
 */

/**
 * 文件功能：验证分组配置创建、更新接口对 radix、category 等数值字段的严格校验，
 *           非法值不得写入或变更配置，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/createGroupConfig、/api/admin/updateGroupConfig/{id}
 *           接口的字段校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/createGroupConfig：{group_name, radix, category, ...}
 * - POST /api/admin/updateGroupConfig/{id}：{group_name, radix, category, ...}
 *
 * 返回值：
 * - 非法数值（如 radix='50abc'、category='1abc'）时返回 code=VALIDATION_FAILED，
 *   不落库或保持原配置不变。
 *
 * 异常或失败场景：
 * - 非严格数字字段值时校验失败，不产生任何写入变更。
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

class AdminGroupConfigValueValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 创建分组配置时应拒绝非严格 radix 且不写入配置。
    public function test_create_group_config_rejects_non_strict_radix_without_writing_config(): void
    {
        $actor = $this->ensureSuperAdmin();

        DB::table('group_configs')->where('name', 'Invalid Radix Group')->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/createGroupConfig', [
                'group_name' => 'Invalid Radix Group',
                'radix' => '50abc',
                'category' => 2,
                'has_commission' => 0,
                'is_enabled' => 1,
                'is_ecn' => 0,
                'is_default' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertFalse(DB::table('group_configs')->where('name', 'Invalid Radix Group')->exists());
    }

    // 更新分组配置时应拒绝非严格 category 且配置保持原样。
    public function test_update_group_config_rejects_non_strict_category_without_changing_config(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedGroupConfig('Invalid Category Group');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateGroupConfig/' . $targetId, [
                'group_name' => 'Invalid Category Group Updated',
                'radix' => 70,
                'category' => '1abc',
                'has_commission' => 1,
                'is_enabled' => 0,
                'is_ecn' => 1,
                'is_default' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $config = DB::table('group_configs')->where('id', $targetId)->first();

        $this->assertSame('Invalid Category Group', (string) $config->name);
        $this->assertSame('50.00', number_format((float) $config->radix, 2, '.', ''));
        $this->assertSame(2, (int) $config->category);
        $this->assertSame(0, (int) $config->has_commission);
        $this->assertSame(1, (int) $config->is_enabled);
        $this->assertSame(0, (int) $config->is_ecn);
        $this->assertSame(0, (int) $config->is_default);
    }

    /**
     * @dataProvider nonStrictUpdateFieldProvider
     */
    public function test_update_group_config_validates_raw_numeric_fields_before_casting(
        string $field,
        string $invalidValue,
        string $messageFragment
    ): void {
        $actor = $this->ensureSuperAdmin();
        $name = 'Invalid Raw Update ' . $field . ' ' . uniqid();
        $targetId = $this->createManagedGroupConfig($name);
        $payload = [
            'group_name' => $name,
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
        ];
        $payload[$field] = $invalidValue;

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateGroupConfig/' . $targetId, $payload);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertStringContainsString($messageFragment, (string) $response->json('message'));

        $config = DB::table('group_configs')->where('id', $targetId)->first();
        $this->assertSame($name, (string) $config->name);
        $this->assertSame('50.00', number_format((float) $config->radix, 2, '.', ''));
        $this->assertSame(2, (int) $config->category);
        $this->assertSame(0, (int) $config->has_commission);
        $this->assertSame(1, (int) $config->is_enabled);
        $this->assertSame(0, (int) $config->is_ecn);
        $this->assertSame(0, (int) $config->is_default);
    }

    public function nonStrictUpdateFieldProvider(): array
    {
        return [
            'radix' => ['radix', '50abc', 'number'],
            'category' => ['category', '1abc', 'category'],
            'has_commission' => ['has_commission', 'oops', 'has commission'],
            'is_enabled' => ['is_enabled', 'oops', 'is enabled'],
            'is_ecn' => ['is_ecn', 'oops', 'is ecn'],
            'is_default' => ['is_default', 'oops', 'is default'],
        ];
    }

    // 核对最终检查清单文档记录了分组配置数值校验边界。
    public function test_final_checklist_records_group_config_value_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 289.', $checklist);
        $this->assertStringContainsString('GroupConfigController::normalizePayload', $checklist);
        $this->assertStringContainsString('/api/admin/createGroupConfig', $checklist);
        $this->assertStringContainsString('/api/admin/updateGroupConfig/{id}', $checklist);
        $this->assertStringContainsString('radix', $checklist);
        $this->assertStringContainsString('category', $checklist);
        $this->assertStringContainsString('group_configs.radix', $checklist);
        $this->assertStringContainsString('AdminGroupConfigValueValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-group-config-value-super',
                'email' => 'admin-group-config-value-super@example.test',
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
