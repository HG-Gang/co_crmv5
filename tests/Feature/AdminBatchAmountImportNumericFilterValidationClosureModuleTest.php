<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 后台批量金额导入数字筛选严格校验闭包测试。
 *
 * 文件功能：
 * - 验证入金/出金导入列表与导出接口（depositImportList、withdrawImportList、exportDepositImports、exportWithdrawImports）对非严格数字筛选（user_id、is_synced 如 {id}abc）返回校验失败，且不返回记录/不导出 CSV。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 327 项）。
 *
 * 适用场景：
 * - 后台批量金额导入模块列表与导出入口的数字筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/depositImportList
 *   {
 *     "user_id": "983782abc",
 *     "limit": 5
 *   }
 *
 * 方法功能：
 * - test_amount_import_lists_reject_non_strict_numeric_filters_without_returning_records：四个列表端点对非法数字筛选返回校验失败且无记录。
 * - test_amount_import_exports_reject_non_strict_numeric_filters_without_returning_csv：四个导出端点对非法数字筛选返回校验失败且不导出 CSV。
 * - test_final_checklist_records_batch_amount_import_numeric_filter_validation_boundary：校验最终清单文档包含第 327 项边界记录。
 *
 * 返回值：
 * - 校验失败时接口返回 code=VALIDATION_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若非法数字筛选被接受并返回记录或导出 CSV，测试断言失败。
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

class AdminBatchAmountImportNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 批量金额导入数字校验用例的夹具业务用户 ID。
     * @var int
     */
    private const TEST_USER_ID = 983782;
    /**
     * 夹具用户的 user_name 标记。断言导入记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Batch Amount Numeric Validation User';
    /**
     * 入金导入的固定批次号标记。断言批次过滤与数字校验按它命中。
     * @var string
     */
    private const TEST_DEPOSIT_BATCH_NO = 'DEPOSIT-NUMERIC-VALIDATION-983782';
    /**
     * 出金导入的固定批次号标记。断言批次过滤与数字校验按它命中。
     * @var string
     */
    private const TEST_WITHDRAW_BATCH_NO = 'WITHDRAW-NUMERIC-VALIDATION-983782';

    protected function tearDown(): void
    {
        DB::table('deposit_imports')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('withdraw_imports')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    /**
     * 四个列表端点对非法数字筛选：断言校验失败且不返回记录。
     *
     * @return void
     */
    public function test_amount_import_lists_reject_non_strict_numeric_filters_without_returning_records(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createAmountImportRecords();

        foreach ($this->listEndpoints() as $uri) {
            foreach ($this->invalidNumericFilters() as $field => $value) {
                $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                    ->actingAs($actor, 'admin')
                    ->post($uri, [
                        $field => $value,
                        'limit' => 5,
                    ]);

                $response->assertOk()
                    ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

                $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
                $this->assertStringNotContainsString(self::TEST_DEPOSIT_BATCH_NO, $response->getContent());
                $this->assertStringNotContainsString(self::TEST_WITHDRAW_BATCH_NO, $response->getContent());
            }
        }
    }

    /**
     * 四个导出端点对非法数字筛选：断言校验失败且不导出 CSV。
     *
     * @return void
     */
    public function test_amount_import_exports_reject_non_strict_numeric_filters_without_returning_csv(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createAmountImportRecords();

        foreach ($this->exportEndpoints() as $uri) {
            foreach ($this->invalidNumericFilters() as $field => $value) {
                $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                    ->actingAs($actor, 'admin')
                    ->post($uri, [
                        $field => $value,
                    ]);

                $response->assertOk();
                $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));
                $response->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
                $this->assertStringNotContainsString(self::TEST_DEPOSIT_BATCH_NO, $response->getContent());
                $this->assertStringNotContainsString(self::TEST_WITHDRAW_BATCH_NO, $response->getContent());
            }
        }
    }

    /**
     * 校验最终清单文档第 327 项记录了金额导入数字筛选校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_batch_amount_import_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 327.', $checklist);
        $this->assertStringContainsString('BatchAmountImportController::depositImportList', $checklist);
        $this->assertStringContainsString('BatchAmountImportController::withdrawImportList', $checklist);
        $this->assertStringContainsString('BatchAmountImportController::exportDepositImports', $checklist);
        $this->assertStringContainsString('BatchAmountImportController::exportWithdrawImports', $checklist);
        $this->assertStringContainsString('/api/admin/depositImportList', $checklist);
        $this->assertStringContainsString('/api/admin/withdrawImportList', $checklist);
        $this->assertStringContainsString('/api/admin/exportDepositImports', $checklist);
        $this->assertStringContainsString('/api/admin/exportWithdrawImports', $checklist);
        $this->assertStringContainsString('deposit_imports.user_id', $checklist);
        $this->assertStringContainsString('withdraw_imports.user_id', $checklist);
        $this->assertStringContainsString('deposit_imports.is_synced', $checklist);
        $this->assertStringContainsString('withdraw_imports.is_synced', $checklist);
        $this->assertStringContainsString('AdminBatchAmountImportNumericFilterValidationClosureModuleTest', $checklist);
    }

    private function invalidNumericFilters(): array
    {
        return [
            'user_id' => self::TEST_USER_ID . 'abc',
            'is_synced' => '2abc',
        ];
    }

    private function listEndpoints(): array
    {
        return [
            '/api/admin/depositImportList',
            '/api/admin/withdrawImportList',
        ];
    }

    private function exportEndpoints(): array
    {
        return [
            '/api/admin/exportDepositImports',
            '/api/admin/exportWithdrawImports',
        ];
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-batch-amount-numeric-super',
                'email' => 'admin-batch-amount-numeric-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAmountImportRecords(): void
    {
        $now = time();

        DB::table('deposit_imports')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('withdraw_imports')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        DB::table('user_infos')->insert([
            'user_id' => self::TEST_USER_ID,
            'login_id' => 0,
            'user_name' => self::TEST_USER_NAME,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) self::TEST_USER_ID,
            'total_funds' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'created_at' => $now - 3600,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        foreach ([
            'deposit_imports' => self::TEST_DEPOSIT_BATCH_NO,
            'withdraw_imports' => self::TEST_WITHDRAW_BATCH_NO,
        ] as $table => $batchNo) {
            DB::table($table)->insert([
                'user_id' => self::TEST_USER_ID,
                'user_name' => self::TEST_USER_NAME,
                'amount' => '188.88',
                'batch_no' => $batchNo,
                'mt4_order_id' => 880782,
                'is_synced' => 2,
                'fail_reason' => 'amount numeric validation fixture',
                'remarks' => 'batch amount numeric validation',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now - 600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }
}
