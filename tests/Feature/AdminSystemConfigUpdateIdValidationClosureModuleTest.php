<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台系统配置更新接口请求 id 严格校验的功能测试。
 *
 * 文件功能：
 * - 验证请求体 id 传入非严格数字时系统配置更新接口返回校验失败。
 * - 验证校验失败后配置记录的 value、description 等字段不被修改。
 *
 * 适用场景：
 * - 后台系统配置管理页面的更新操作，防止非法 id 误改配置。
 *
 * 入参例子：
 * - POST /api/admin/updateSystemConfig，body：{"id": "1abc", "value": "...", "group": "...", "description": "..."}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 请求 id 非严格整数时接口拒绝执行并保持原配置记录不变。
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

class AdminSystemConfigUpdateIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 夹具 system_configs 行的 key。验证更新接口按主键校验拒绝非法 ID 且不误伤该行。
     * @var string
     */
    private const TEST_KEY = 'system_config_id_validation_key';

    protected function tearDown(): void
    {
        DB::table('system_configs')->where('key', self::TEST_KEY)->delete();

        parent::tearDown();
    }

    // 验证更新系统配置时非严格 id 被拒绝且配置原值不变。
    public function test_update_system_config_rejects_non_strict_id_without_updating_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $configId = $this->createSystemConfig();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateSystemConfig', [
                'id' => $configId . 'abc',
                'value' => 'changed-by-invalid-id',
                'group' => 'validation',
                'description' => 'Should not update',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $config = DB::table('system_configs')->where('id', $configId)->first();

        $this->assertSame('original-value', (string) $config->value);
        $this->assertSame('Original description', (string) $config->description);
    }

    // 校验最终检查清单文档记录了系统配置更新 id 校验边界。
    public function test_final_checklist_records_system_config_update_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 330.', $checklist);
        $this->assertStringContainsString('SystemConfigController::update', $checklist);
        $this->assertStringContainsString('SystemConfigController::updateSingleConfig', $checklist);
        $this->assertStringContainsString('/api/admin/updateSystemConfig', $checklist);
        $this->assertStringContainsString('system_configs.id', $checklist);
        $this->assertStringContainsString('AdminSystemConfigUpdateIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-system-config-id-super',
                'email' => 'admin-system-config-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createSystemConfig(): int
    {
        $now = time();

        DB::table('system_configs')->where('key', self::TEST_KEY)->delete();

        return (int) DB::table('system_configs')->insertGetId([
            'key' => self::TEST_KEY,
            'value' => 'original-value',
            'group' => 'validation',
            'description' => 'Original description',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
