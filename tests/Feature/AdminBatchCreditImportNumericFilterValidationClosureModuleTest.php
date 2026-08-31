<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 后台批量信用导入数字筛选严格校验闭包测试。
 *
 * 文件功能：
 * - 验证信用导入列表与导出接口（creditImportList、exportCreditImports）对非严格数字筛选（user_id、credit_type、is_synced 如 {id}abc）返回校验失败，且不返回记录/不导出 CSV。
 * - 验证 docs/admin-backend-blade-permission-final-checklist.md 最终清单记录了本边界（第 326 项）。
 *
 * 适用场景：
 * - 后台批量信用导入模块列表与导出入口的数字筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/creditImportList
 *   {
 *     "user_id": "983781abc",
 *     "limit": 5
 *   }
 *
 * 方法功能：
 * - test_credit_import_list_rejects_non_strict_numeric_filters_without_returning_record：列表接口对非法数字筛选返回校验失败且无记录。
 * - test_credit_import_export_rejects_non_strict_numeric_filters_without_returning_csv：导出接口对非法数字筛选返回校验失败且不导出 CSV。
 * - test_final_checklist_records_batch_credit_import_numeric_filter_validation_boundary：校验最终清单文档包含第 326 项边界记录。
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

class AdminBatchCreditImportNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 批量赠点导入数字校验用例的夹具业务用户 ID。
     * @var int
     */
    private const TEST_USER_ID = 983781;
    /**
     * 夹具用户的 user_name 标记。断言导入记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Batch Credit Numeric Validation User';
    /**
     * 赠点导入的固定批次号标记。断言批次过滤与数字校验按它命中。
     * @var string
     */
    private const TEST_BATCH_NO = 'CREDIT-NUMERIC-VALIDATION-983781';

    protected function tearDown(): void
    {
        DB::table('credit_imports')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    /**
     * 信用导入列表接口对非法数字筛选：断言校验失败且不返回记录。
     *
     * @return void
     */
    public function test_credit_import_list_rejects_non_strict_numeric_filters_without_returning_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createCreditImportRecord();

        foreach ($this->invalidNumericFilters() as $field => $value) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/creditImportList', [
                    $field => $value,
                    'limit' => 5,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
            $this->assertStringNotContainsString(self::TEST_BATCH_NO, $response->getContent());
        }
    }

    /**
     * 信用导入导出接口对非法数字筛选：断言校验失败且不导出 CSV。
     *
     * @return void
     */
    public function test_credit_import_export_rejects_non_strict_numeric_filters_without_returning_csv(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createCreditImportRecord();

        foreach ($this->invalidNumericFilters() as $field => $value) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/exportCreditImports', [
                    $field => $value,
                ]);

            $response->assertOk();
            $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));
            $response->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
            $this->assertStringNotContainsString(self::TEST_BATCH_NO, $response->getContent());
        }
    }

    /**
     * 校验最终清单文档第 326 项记录了信用导入数字筛选校验边界。
     *
     * @return void
     */
    public function test_final_checklist_records_batch_credit_import_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 326.', $checklist);
        $this->assertStringContainsString('BatchCreditImportController::creditImportList', $checklist);
        $this->assertStringContainsString('BatchCreditImportController::exportCreditImports', $checklist);
        $this->assertStringContainsString('/api/admin/creditImportList', $checklist);
        $this->assertStringContainsString('/api/admin/exportCreditImports', $checklist);
        $this->assertStringContainsString('credit_imports.user_id', $checklist);
        $this->assertStringContainsString('credit_imports.credit_type', $checklist);
        $this->assertStringContainsString('credit_imports.is_synced', $checklist);
        $this->assertStringContainsString('AdminBatchCreditImportNumericFilterValidationClosureModuleTest', $checklist);
    }

    private function invalidNumericFilters(): array
    {
        return [
            'user_id' => self::TEST_USER_ID . 'abc',
            'credit_type' => '3abc',
            'is_synced' => '2abc',
        ];
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-batch-credit-numeric-super',
                'email' => 'admin-batch-credit-numeric-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createCreditImportRecord(): void
    {
        $now = time();

        DB::table('credit_imports')->where('user_id', self::TEST_USER_ID)->delete();
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

        DB::table('credit_imports')->insert([
            'user_id' => self::TEST_USER_ID,
            'user_name' => self::TEST_USER_NAME,
            'credit_type' => 3,
            'amount' => '88.88',
            'batch_no' => self::TEST_BATCH_NO,
            'mt4_order_id' => 880781,
            'is_synced' => 2,
            'fail_reason' => 'credit numeric validation fixture',
            'remarks' => 'batch credit numeric validation',
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now - 600,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
