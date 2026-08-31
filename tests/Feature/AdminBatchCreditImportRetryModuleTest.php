<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 22:56
 */

/**
 * AdminBatchCreditImportRetryModuleTest
 *
 * 文件功能：
 * - 验证批量信用导入失败重试契约：重试路由与权限、Blade 按钮、Layui JS 调用、控制器重置逻辑，且仅失败记录可重试。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台批量信用导入失败重试契约测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `BatchCreditController::againCreditAmount` 用于把失败的信用导入记录重新放回待处理队列。
 * - 新项目必须提供独立的信用导入重试接口，并继续使用 `permissions.api_route` 与 `check.permission:admin` 做后端鉴权。
 * - 当前本地 MySQL 3307 不稳定，本测试只验证源码、路由、权限迁移、Blade 和 JS 契约。
 */
class AdminBatchCreditImportRetryModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 批量信用导入失败重试 API 必须注册并挂后台权限中间件。
     *
     * @return void
     */
    public function test_credit_import_retry_api_route_is_registered_with_permission_middleware(): void
    {
        $this->assertTrue(Route::has('admin_api_retryCreditImport'), 'admin_api_retryCreditImport API 路由未注册。');
        $this->assertContains('check.permission:admin', Route::getRoutes()->getByName('admin_api_retryCreditImport')->gatherMiddleware());
    }

    /**
     * 控制器必须提供信用导入失败重试逻辑，并只允许失败记录回到待处理状态。
     *
     * @return void
     */
    public function test_credit_import_controller_declares_retry_logic(): void
    {
        // $source：批量信用导入控制器源码，用于确认失败重试不是前端假按钮。
        $source = file_get_contents(app_path('Http/Controllers/Admin/BatchCreditImportController.php')) ?: '';

        $this->assertStringContainsString('retryCreditImport', $source);
        $this->assertStringContainsString('CreditImport::query()', $source);
        $this->assertStringContainsString('is_synced', $source);
        $this->assertStringContainsString('fail_reason', $source);
        $this->assertStringContainsString('import_retry_only_failed', $source);
        $this->assertStringContainsString('AdminDataScopeService', $source);
    }

    /**
     * 批量信用导入 Blade 页面必须给失败重试按钮声明 data-permission。
     *
     * @return void
     */
    public function test_credit_import_blade_page_contains_retry_button(): void
    {
        $source = file_get_contents(resource_path('admin/layui/credit-imports/index.blade.php')) ?: '';

        $this->assertStringContainsString('id="creditImportActions"', $source);
        $this->assertStringContainsString('lay-event="retryCreditImport"', $source);
        $this->assertStringContainsString('data-permission="admin_batch_credit_import_retry"', $source);
    }

    /**
     * 批量信用导入 JS 必须调用重试 API 并刷新当前表格。
     *
     * @return void
     */
    public function test_credit_import_layui_script_calls_retry_api(): void
    {
        $source = $this->adminLayuiScript('credit-imports/index.js');

        $this->assertStringContainsString('/api/admin/retryCreditImport/', $source);
        $this->assertStringContainsString('retryCreditImport', $source);
        $this->assertStringContainsString('creditImportTable', $source);
    }

    /**
     * 权限迁移必须声明信用导入失败重试接口权限。
     *
     * @return void
     */
    public function test_credit_import_permission_migration_declares_retry_permission(): void
    {
        $source = file_get_contents(database_path('migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php')) ?: '';

        $this->assertStringContainsString('admin_batch_credit_import_retry', $source);
        $this->assertStringContainsString('admin_api_retryCreditImport', $source);
    }

    public function test_credit_import_retry_api_resets_failed_record_to_pending(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $userId = 982601;
        $this->ensureImportUser($userId, 'Batch Credit Retry User');
        $id = $this->createCreditImportRecord($userId, 'RETRY-CREDIT-FAILED', 2, 'credit sync failed');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/retryCreditImport/' . $id);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.is_synced', 0)
            ->assertJsonPath('data.fail_reason', '');

        $this->assertDatabaseHas('credit_imports', [
            'id' => $id,
            'is_synced' => 0,
            'fail_reason' => '',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_credit_import_retry_api_rejects_non_failed_records_without_mutating_them(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $userId = 982602;
        $this->ensureImportUser($userId, 'Batch Credit Retry Guard User');

        foreach ([0, 1] as $syncStatus) {
            $id = $this->createCreditImportRecord($userId, 'RETRY-CREDIT-GUARD-' . $syncStatus, $syncStatus, 'keep credit reason');

            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($admin, 'admin')
                ->post('/api/admin/retryCreditImport/' . $id);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

            $this->assertDatabaseHas('credit_imports', [
                'id' => $id,
                'is_synced' => $syncStatus,
                'fail_reason' => 'keep credit reason',
            ]);
        }
    }

    private function ensureImportUser(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function createCreditImportRecord(int $userId, string $batchNo, int $syncStatus, string $failReason): int
    {
        $now = time();

        return (int) DB::table('credit_imports')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Batch Credit Retry User',
            'credit_type' => 3,
            'amount' => 42.50,
            'batch_no' => $batchNo,
            'mt4_order_id' => 990601,
            'is_synced' => $syncStatus,
            'fail_reason' => $failReason,
            'remarks' => 'retry api test',
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
