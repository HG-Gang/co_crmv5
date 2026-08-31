<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 14:35
 */

/**
 * AdminBatchAmountImportModuleTest
 *
 * 文件功能：
 * - 验证批量入金/出金导入模块：Blade 页面注册、API 路由权限中间件、CSV 模板/导出下载、上传创建与 MT4 登录不匹配拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台批量入金/出金导入模块覆盖测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `BatchAmountController` 负责批量入金、批量出金导入与导入记录查询。
 * - 新项目已经有 `deposit_imports` 和 `withdraw_imports` 真实数据表，本测试要求后台补齐 Blade 页面、API 路由和数据表权限配置。
 * - 页面按钮必须使用 `data-permission` 绑定 `permissions.slug`，后端接口必须继续走 `check.permission:admin` 中间件。
 */
class AdminBatchAmountImportModuleTest extends TestCase
{
    /**
     * 批量入金/出金页面必须注册为独立 Blade 路由。
     *
     * @return void
     */
    public function test_batch_amount_import_pages_are_registered(): void
    {
        foreach (['admin_page_deposit_imports', 'admin_page_withdraw_imports'] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' 页面路由未注册');
        }
    }

    /**
     * 批量入金页面必须包含列表、筛选和新增导入记录入口。
     *
     * @return void
     */
    public function test_deposit_import_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/deposit-imports');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="depositImportTable"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"deposit-imports/index\"", false);
        $response->assertSee('id="addDepositImport"', false);
        $response->assertSee('data-permission="admin_batch_deposit_import_create"', false);
        $response->assertSee('id="downloadDepositImportTemplate"', false);
        $response->assertSee('data-permission="admin_batch_deposit_import_template"', false);
        $response->assertSee('id="exportDepositImports"', false);
        $response->assertSee('data-permission="admin_batch_deposit_import_export"', false);
        $response->assertSee('id="depositImportModal"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="amount"', false);
        $response->assertSee('name="batch_no"', false);
        // CSV 批量导入 UI：导入按钮、共享上传组件弹窗与提交入口（同一 create 权限口）。
        $response->assertSee('id="importDepositImportFile"', false);
        $response->assertSee('id="depositImportUploadModal"', false);
        $response->assertSee('data-crm-upload="deposit_import_csv"', false);
        $response->assertSee('data-upload-exts="csv"', false);
        $response->assertSee('id="submitDepositImportFile"', false);
    }

    /**
     * 批量出金页面必须包含列表、筛选和新增导入记录入口。
     *
     * @return void
     */
    public function test_withdraw_import_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/withdraw-imports');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="withdrawImportTable"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"withdraw-imports/index\"", false);
        $response->assertSee('id="addWithdrawImport"', false);
        $response->assertSee('data-permission="admin_batch_withdraw_import_create"', false);
        $response->assertSee('id="downloadWithdrawImportTemplate"', false);
        $response->assertSee('data-permission="admin_batch_withdraw_import_template"', false);
        $response->assertSee('id="exportWithdrawImports"', false);
        $response->assertSee('data-permission="admin_batch_withdraw_import_export"', false);
        $response->assertSee('id="withdrawImportModal"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="amount"', false);
        $response->assertSee('name="batch_no"', false);
        // CSV 批量导入 UI：导入按钮、共享上传组件弹窗与提交入口（同一 create 权限口）。
        $response->assertSee('id="importWithdrawImportFile"', false);
        $response->assertSee('id="withdrawImportUploadModal"', false);
        $response->assertSee('data-crm-upload="withdraw_import_csv"', false);
        $response->assertSee('data-upload-exts="csv"', false);
        $response->assertSee('id="submitWithdrawImportFile"', false);
    }

    /**
     * 批量导入接口必须注册并挂载后台接口鉴权中间件。
     *
     * @return void
     */
    public function test_crmui_amount_import_forms_match_backend_required_fields(): void
    {
        foreach ([
            '/admin-crmui/deposit-imports' => ['user_id', 'user_name', 'amount', 'batch_no', 'mt4_order_id', 'remarks'],
            '/admin-crmui/withdraw-imports' => ['user_id', 'user_name', 'amount', 'batch_no', 'mt4_order_id', 'remarks'],
        ] as $uri => $fields) {
            $response = $this->get($uri);

            $response->assertOk();
            foreach ($fields as $field) {
                $response->assertSee('name="' . $field . '"', false);
            }
            // CrmUI 侧 CSV 批量导入：动作按钮（data-crmui-action="import"）与导入弹窗、共享上传块。
            $response->assertSee('data-crmui-action="import"', false);
            $response->assertSee('data-crmui-import-modal', false);
            $response->assertSee('data-crm-upload="csv_import"', false);
            $response->assertSee('data-crmui-import-submit', false);
        }
    }

    public function test_batch_amount_import_api_routes_are_registered_with_permission_middleware(): void
    {
        foreach ([
            'admin_api_depositImportList',
            'admin_api_createDepositImport',
            'admin_api_depositImportTemplate',
            'admin_api_exportDepositImports',
            'admin_api_withdrawImportList',
            'admin_api_createWithdrawImport',
            'admin_api_withdrawImportTemplate',
            'admin_api_exportWithdrawImports',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    /**
     * 模板下载和当前筛选结果导出必须返回 CSV 下载响应，供旧项目批量文件流转继续使用。
     *
     * @return void
     */
    public function test_batch_amount_template_and_export_endpoints_return_csv_downloads(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $now = time();
        $userId = 982301;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Batch Amount Export User',
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

        DB::table('deposit_imports')->updateOrInsert(
            ['batch_no' => 'AMOUNT-EXPORT-DEPOSIT', 'user_id' => $userId],
            [
                'user_name' => 'Batch Amount Export User',
                'amount' => 123.45,
                'remarks' => 'deposit export csv',
                'mt4_order_id' => 770001,
                'is_synced' => 2,
                'fail_reason' => 'deposit failed',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        DB::table('withdraw_imports')->updateOrInsert(
            ['batch_no' => 'AMOUNT-EXPORT-WITHDRAW', 'user_id' => $userId],
            [
                'user_name' => 'Batch Amount Export User',
                'amount' => 67.89,
                'remarks' => 'withdraw export csv',
                'mt4_order_id' => 770002,
                'is_synced' => 1,
                'fail_reason' => '',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        foreach ([
            '/api/admin/depositImportTemplate' => ['user_id,user_name,mt4_login,amount,batch_no,mt4_order_id,remarks', 'deposit_import_template.csv'],
            '/api/admin/withdrawImportTemplate' => ['user_id,user_name,mt4_login,amount,batch_no,mt4_order_id,remarks', 'withdraw_import_template.csv'],
            '/api/admin/exportDepositImports' => ['AMOUNT-EXPORT-DEPOSIT', 'deposit_imports_export.csv'],
            '/api/admin/exportWithdrawImports' => ['AMOUNT-EXPORT-WITHDRAW', 'withdraw_imports_export.csv'],
        ] as $uri => [$needle, $fileName]) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($admin, 'admin')
                ->post($uri);

            $response->assertOk();
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
            $this->assertStringContainsString($fileName, (string) $response->headers->get('content-disposition'));
            $this->assertStringContainsString($needle, $response->streamedContent());
        }
    }

    public function test_batch_amount_create_endpoints_accept_csv_uploads(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $now = time();
        $depositUserId = 982311;
        $withdrawUserId = 982312;

        foreach ([
            $depositUserId => 'Batch Deposit Csv User',
            $withdrawUserId => 'Batch Withdraw Csv User',
        ] as $userId => $userName) {
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

        $cases = [
            '/api/admin/createDepositImport' => [
                'table' => 'deposit_imports',
                'user_id' => $depositUserId,
                'batch_no' => 'CSV-DEPOSIT-BATCH',
                'amount' => '135.79',
            ],
            '/api/admin/createWithdrawImport' => [
                'table' => 'withdraw_imports',
                'user_id' => $withdrawUserId,
                'batch_no' => 'CSV-WITHDRAW-BATCH',
                'amount' => '246.80',
            ],
        ];

        foreach ($cases as $uri => $case) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($admin, 'admin')
                ->post($uri, [
                    'file' => $this->csvUpload(
                        'user_id,user_name,amount,batch_no,mt4_order_id,remarks' . "\n" .
                        $case['user_id'] . ',Uploaded Csv User,' . $case['amount'] . ',' . $case['batch_no'] . ',990001,parsed from csv' . "\n"
                    ),
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::CREATED)
                ->assertJsonPath('data.created', 1);

            $this->assertDatabaseHas($case['table'], [
                'user_id' => $case['user_id'],
                'batch_no' => $case['batch_no'],
                'amount' => $case['amount'],
                'mt4_order_id' => 990001,
                'remarks' => 'parsed from csv',
                'is_synced' => 0,
            ]);
        }
    }

    public function test_batch_amount_csv_upload_rejects_mismatched_mt4_login(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $now = time();
        $userId = 982313;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Batch Amount Mt4 Guard User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'mt4_code' => 880313,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        foreach ([
            '/api/admin/createDepositImport' => 'deposit_imports',
            '/api/admin/createWithdrawImport' => 'withdraw_imports',
        ] as $uri => $table) {
            $batchNo = 'CSV-MT4-MISMATCH-' . basename($uri);
            DB::table($table)->where('user_id', $userId)->where('batch_no', $batchNo)->delete();

            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($admin, 'admin')
                ->post($uri, [
                    'file' => $this->csvUpload(
                        'user_id,user_name,mt4_login,amount,batch_no,mt4_order_id,remarks' . "\n" .
                        $userId . ',Batch Amount Mt4 Guard User,990313,10.00,' . $batchNo . ',0,mt4 mismatch' . "\n"
                    ),
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
            $this->assertStringContainsString('MT4', (string) $response->json('message'));
            $this->assertDatabaseMissing($table, [
                'user_id' => $userId,
                'batch_no' => $batchNo,
            ]);
        }
    }

    private function csvUpload(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'batch_amount_csv_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'batch-import.csv', 'text/csv', null, true);
    }
}
