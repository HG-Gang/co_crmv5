<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 后台批量导入重试接口路由 id 严格校验闭包测试。
 *
 * 文件功能：
 * - 验证批量金额/信用导入重试接口（retryDepositImport、retryWithdrawImport、retryCreditImport）对非严格整数路由 id（如 {id}abc）返回校验失败，且不重置导入记录状态。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 295 项）。
 *
 * 适用场景：
 * - 后台批量导入模块重试入口的路由参数严格校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/retryDepositImport/{id}abc
 *
 * 方法功能：
 * - test_batch_amount_retry_routes_reject_non_strict_route_id_without_resetting_records：入金/出金重试路由非严格 id 被拒，断言记录状态未重置。
 * - test_batch_credit_retry_route_rejects_non_strict_route_id_without_resetting_record：信用重试路由非严格 id 被拒，断言记录状态未重置。
 * - test_final_checklist_records_batch_import_retry_route_id_validation_boundary：校验最终清单文档包含第 295 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非严格路由 id 被接受并重置记录状态，测试断言失败。
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

class AdminBatchImportRetryRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 入金/出金重试路由非严格 id：断言校验失败且记录状态未重置。
     *
     * @return void
     */
    public function test_batch_amount_retry_routes_reject_non_strict_route_id_without_resetting_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 982701;
        $this->ensureImportUser($userId, 'Batch Import Route User');

        foreach ([
            '/api/admin/retryDepositImport/' => ['table' => 'deposit_imports', 'batch_no' => 'ROUTE-ID-DEPOSIT'],
            '/api/admin/retryWithdrawImport/' => ['table' => 'withdraw_imports', 'batch_no' => 'ROUTE-ID-WITHDRAW'],
        ] as $uri => $case) {
            $id = $this->createAmountImportRecord($case['table'], $userId, $case['batch_no']);

            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post($uri . $id . 'abc');

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertDatabaseHas($case['table'], [
                'id' => $id,
                'is_synced' => 2,
                'fail_reason' => 'sync failed',
                'updated_by' => 0,
            ]);
        }
    }

    /**
     * 信用重试路由非严格 id：断言校验失败且记录状态未重置。
     *
     * @return void
     */
    public function test_batch_credit_retry_route_rejects_non_strict_route_id_without_resetting_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 982702;
        $this->ensureImportUser($userId, 'Batch Credit Route User');
        $id = $this->createCreditImportRecord($userId, 'ROUTE-ID-CREDIT');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/retryCreditImport/' . $id . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('credit_imports', [
            'id' => $id,
            'is_synced' => 2,
            'fail_reason' => 'credit sync failed',
            'updated_by' => 0,
        ]);
    }

    /**
     * 校验最终清单文档第 295 项记录了批量导入重试路由 id 校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_batch_import_retry_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 295.', $checklist);
        $this->assertStringContainsString('BatchAmountImportController::retryDepositImport', $checklist);
        $this->assertStringContainsString('BatchAmountImportController::retryWithdrawImport', $checklist);
        $this->assertStringContainsString('BatchCreditImportController::retryCreditImport', $checklist);
        $this->assertStringContainsString('/api/admin/retryDepositImport/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/retryWithdrawImport/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/retryCreditImport/{id}', $checklist);
        $this->assertStringContainsString('deposit_imports.id', $checklist);
        $this->assertStringContainsString('withdraw_imports.id', $checklist);
        $this->assertStringContainsString('credit_imports.id', $checklist);
        $this->assertStringContainsString('AdminBatchImportRetryRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-batch-import-route-id-super',
                'email' => 'admin-batch-import-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
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

    private function createAmountImportRecord(string $table, int $userId, string $batchNo): int
    {
        $now = time();

        return (int) DB::table($table)->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Batch Import Route User',
            'amount' => '42.50',
            'remarks' => 'route id retry test',
            'mt4_order_id' => 990701,
            'batch_no' => $batchNo,
            'is_synced' => 2,
            'fail_reason' => 'sync failed',
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createCreditImportRecord(int $userId, string $batchNo): int
    {
        $now = time();

        return (int) DB::table('credit_imports')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Batch Credit Route User',
            'credit_type' => 3,
            'amount' => '42.50',
            'batch_no' => $batchNo,
            'mt4_order_id' => 990702,
            'is_synced' => 2,
            'fail_reason' => 'credit sync failed',
            'remarks' => 'route id retry test',
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
