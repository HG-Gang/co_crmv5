<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 22:55
 */

/**
 * AdminBatchAmountImportRetryModuleTest
 *
 * 文件功能：
 * - 验证批量入金/出金导入失败重试契约：重试路由与权限、Blade 按钮、Layui JS 调用、控制器重置逻辑，且仅失败记录可重试。
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
 * 后台批量入金/出金导入失败重试契约测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `BatchAmountController` 包含 `againDepositAmount` 与 `againWithdrawAmount`，用于把失败导入记录重新放回待处理队列。
 * - 新项目第一阶段已经有导入列表和手工新增，本测试继续约束失败重试的路由、权限、Blade 按钮、JS 调用和控制器逻辑。
 * - 当前本地 MySQL 3307 不稳定，本测试只做源码和路由契约验证；真实 DB 重试写入在数据库恢复后再做集成验证。
 */
class AdminBatchAmountImportRetryModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 批量导入失败重试 API 必须注册并挂后台权限中间件。
     *
     * @return void
     */
    public function test_batch_amount_retry_api_routes_are_registered_with_permission_middleware(): void
    {
        foreach (['admin_api_retryDepositImport', 'admin_api_retryWithdrawImport'] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册。');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    /**
     * 控制器必须提供入金和出金失败重试方法，并只允许失败记录回到待处理状态。
     *
     * @return void
     */
    public function test_batch_amount_controller_declares_retry_logic(): void
    {
        // $source：批量资金导入控制器源码，用于确认失败重试不只是前端按钮。
        $source = file_get_contents(app_path('Http/Controllers/Admin/BatchAmountImportController.php')) ?: '';

        $this->assertStringContainsString('retryDepositImport', $source);
        $this->assertStringContainsString('retryWithdrawImport', $source);
        $this->assertStringContainsString('retryImportRecord', $source);
        $this->assertStringContainsString('is_synced', $source);
        $this->assertStringContainsString('fail_reason', $source);
        $this->assertStringContainsString('import_retry_only_failed', $source);
        $this->assertStringContainsString('AdminDataScopeService', $source);
    }

    /**
     * 批量入金和出金 Blade 页面必须给失败重试按钮声明 data-permission。
     *
     * @return void
     */
    public function test_batch_amount_blade_pages_contain_retry_buttons(): void
    {
        $depositBlade = file_get_contents(resource_path('admin/layui/deposit-imports/index.blade.php')) ?: '';
        $withdrawBlade = file_get_contents(resource_path('admin/layui/withdraw-imports/index.blade.php')) ?: '';

        $this->assertStringContainsString('id="depositImportActions"', $depositBlade);
        $this->assertStringContainsString('lay-event="retryDepositImport"', $depositBlade);
        $this->assertStringContainsString('data-permission="admin_batch_deposit_import_retry"', $depositBlade);

        $this->assertStringContainsString('id="withdrawImportActions"', $withdrawBlade);
        $this->assertStringContainsString('lay-event="retryWithdrawImport"', $withdrawBlade);
        $this->assertStringContainsString('data-permission="admin_batch_withdraw_import_retry"', $withdrawBlade);
    }

    /**
     * 批量导入 JS 必须调用重试 API 并刷新当前表格。
     *
     * @return void
     */
    public function test_batch_amount_layui_scripts_call_retry_apis(): void
    {
        $depositJs = $this->adminLayuiScript('deposit-imports/index.js');
        $withdrawJs = $this->adminLayuiScript('withdraw-imports/index.js');

        $this->assertStringContainsString('/api/admin/retryDepositImport/', $depositJs);
        $this->assertStringContainsString('retryDepositImport', $depositJs);
        $this->assertStringContainsString('depositImportTable', $depositJs);

        $this->assertStringContainsString('/api/admin/retryWithdrawImport/', $withdrawJs);
        $this->assertStringContainsString('retryWithdrawImport', $withdrawJs);
        $this->assertStringContainsString('withdrawImportTable', $withdrawJs);
    }

    /**
     * 权限迁移必须声明两个失败重试接口权限。
     *
     * @return void
     */
    public function test_batch_amount_permission_migration_declares_retry_permissions(): void
    {
        $source = file_get_contents(database_path('migrations/2026_06_07_000004_add_admin_batch_amount_import_permissions.php')) ?: '';

        $this->assertStringContainsString('admin_batch_deposit_import_retry', $source);
        $this->assertStringContainsString('admin_api_retryDepositImport', $source);
        $this->assertStringContainsString('admin_batch_withdraw_import_retry', $source);
        $this->assertStringContainsString('admin_api_retryWithdrawImport', $source);
    }

    public function test_batch_amount_retry_apis_reset_failed_records_to_pending(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $userId = 982501;
        $this->ensureImportUser($userId, 'Batch Amount Retry User');

        foreach ([
            '/api/admin/retryDepositImport/' => ['table' => 'deposit_imports', 'batch_no' => 'RETRY-DEPOSIT-FAILED'],
            '/api/admin/retryWithdrawImport/' => ['table' => 'withdraw_imports', 'batch_no' => 'RETRY-WITHDRAW-FAILED'],
        ] as $uri => $case) {
            $id = $this->createAmountImportRecord($case['table'], $userId, $case['batch_no'], 2, 'sync failed');

            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($admin, 'admin')
                ->post($uri . $id);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS)
                ->assertJsonPath('data.is_synced', 0)
                ->assertJsonPath('data.fail_reason', '');

            $this->assertDatabaseHas($case['table'], [
                'id' => $id,
                'is_synced' => 0,
                'fail_reason' => '',
                'updated_by' => $admin->id,
            ]);
        }
    }

    public function test_batch_amount_retry_apis_reject_non_failed_records_without_mutating_them(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $userId = 982502;
        $this->ensureImportUser($userId, 'Batch Amount Retry Guard User');

        foreach ([0, 1] as $syncStatus) {
            foreach ([
                '/api/admin/retryDepositImport/' => ['table' => 'deposit_imports', 'batch_no' => 'RETRY-DEPOSIT-GUARD-' . $syncStatus],
                '/api/admin/retryWithdrawImport/' => ['table' => 'withdraw_imports', 'batch_no' => 'RETRY-WITHDRAW-GUARD-' . $syncStatus],
            ] as $uri => $case) {
                $id = $this->createAmountImportRecord($case['table'], $userId, $case['batch_no'], $syncStatus, 'keep reason');

                $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                    ->actingAs($admin, 'admin')
                    ->post($uri . $id);

                $response->assertOk()
                    ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

                $this->assertDatabaseHas($case['table'], [
                    'id' => $id,
                    'is_synced' => $syncStatus,
                    'fail_reason' => 'keep reason',
                ]);
            }
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

    private function createAmountImportRecord(string $table, int $userId, string $batchNo, int $syncStatus, string $failReason): int
    {
        $now = time();

        return (int) DB::table($table)->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Batch Amount Retry User',
            'amount' => 42.50,
            'remarks' => 'retry api test',
            'mt4_order_id' => 990501,
            'batch_no' => $batchNo,
            'is_synced' => $syncStatus,
            'fail_reason' => $failReason,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
