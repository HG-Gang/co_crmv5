<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 14:34
 */

/**
 * AdminBatchCreditImportModuleTest
 *
 * 文件功能：
 * - 验证批量信用导入模块：Blade 页面、API 路由权限中间件、CSV 模板/导出下载、上传创建与 MT4 登录不匹配拒绝。
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
 * 后台批量信用导入模块覆盖测试。
 *
 * 功能逻辑说明：
 * - 旧项目 `BatchCreditController` 负责信用额度批量导入、导入记录查询和失败重试。
 * - 新项目已经有真实数据表 `credit_imports`，本测试先约束 Blade 页面、API 路由和手工新增导入记录入口。
 * - 当前真实 DB 的 `credit_imports` 表为空，因此测试不伪造已有导入样本，只验证页面/API/权限入口已经迁移。
 * - 后续 Excel/CSV 文件解析和 MT4 同步重试可以继续复用本模块的字段口径和权限配置。
 */
class AdminBatchCreditImportModuleTest extends TestCase
{
    /**
     * 批量信用导入页面必须注册为独立 Blade 路由。
     *
     * @return void
     */
    public function test_credit_import_page_is_registered(): void
    {
        $this->assertTrue(Route::has('admin_page_credit_imports'), 'admin_page_credit_imports 页面路由未注册');
    }

    /**
     * 批量信用导入页面必须包含列表、筛选和新增导入记录入口。
     *
     * @return void
     */
    public function test_credit_import_page_renders_blade_controls(): void
    {
        $response = $this->get('/admin/credit-imports');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('id="creditImportTable"', false);
        $response->assertSee('/js/apps/admin/layui/pages.js', false);
        $response->assertSee("data-layui-page=\"credit-imports/index\"", false);
        $response->assertSee('id="addCreditImport"', false);
        $response->assertSee('data-permission="admin_batch_credit_import_create"', false);
        $response->assertSee('id="downloadCreditImportTemplate"', false);
        $response->assertSee('data-permission="admin_batch_credit_import_template"', false);
        $response->assertSee('id="exportCreditImports"', false);
        $response->assertSee('data-permission="admin_batch_credit_import_export"', false);
        $response->assertSee('id="creditImportModal"', false);
        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="credit_type"', false);
        $response->assertSee('name="amount"', false);
        $response->assertSee('name="batch_no"', false);
        // CSV 批量导入 UI：导入按钮、共享上传组件弹窗与提交入口（同一 create 权限口）。
        $response->assertSee('id="importCreditImportFile"', false);
        $response->assertSee('id="creditImportUploadModal"', false);
        $response->assertSee('data-crm-upload="credit_import_csv"', false);
        $response->assertSee('data-upload-exts="csv"', false);
        $response->assertSee('id="submitCreditImportFile"', false);
    }

    /**
     * 批量信用导入接口必须注册并挂载后台接口鉴权中间件。
     *
     * @return void
     */
    public function test_crmui_credit_import_form_matches_backend_required_fields(): void
    {
        $response = $this->get('/admin-crmui/credit-imports');

        $response->assertOk();
        foreach (['user_id', 'user_name', 'credit_type', 'amount', 'batch_no', 'mt4_order_id', 'remarks'] as $field) {
            $response->assertSee('name="' . $field . '"', false);
        }
        // CrmUI 侧 CSV 批量导入：动作按钮（data-crmui-action="import"）与导入弹窗、共享上传块。
        $response->assertSee('data-crmui-action="import"', false);
        $response->assertSee('data-crmui-import-modal', false);
        $response->assertSee('data-crm-upload="csv_import"', false);
        $response->assertSee('data-crmui-import-submit', false);
    }

    public function test_credit_import_api_routes_are_registered_with_permission_middleware(): void
    {
        foreach ([
            'admin_api_creditImportList',
            'admin_api_createCreditImport',
            'admin_api_creditImportTemplate',
            'admin_api_exportCreditImports',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName . ' API 路由未注册');
            $this->assertContains('check.permission:admin', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    /**
     * 批量信用模板和导出接口必须返回 CSV 下载响应，保留旧项目批量信用文件流转能力。
     *
     * @return void
     */
    public function test_credit_import_template_and_export_endpoints_return_csv_downloads(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $now = time();
        $userId = 982401;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Batch Credit Export User',
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

        DB::table('credit_imports')->updateOrInsert(
            ['batch_no' => 'CREDIT-EXPORT-BATCH', 'user_id' => $userId],
            [
                'user_name' => 'Batch Credit Export User',
                'credit_type' => 3,
                'amount' => 88.88,
                'remarks' => 'credit export csv',
                'mt4_order_id' => 880001,
                'is_synced' => 2,
                'fail_reason' => 'credit failed',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        foreach ([
            '/api/admin/creditImportTemplate' => ['user_id,user_name,mt4_login,credit_type,amount,batch_no,mt4_order_id,remarks', 'credit_import_template.csv'],
            '/api/admin/exportCreditImports' => ['CREDIT-EXPORT-BATCH', 'credit_imports_export.csv'],
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

    public function test_credit_import_create_endpoint_accepts_csv_upload(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $now = time();
        $userId = 982411;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Batch Credit Csv User',
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
        DB::table('credit_imports')->where('user_id', $userId)->where('batch_no', 'CSV-CREDIT-MT4-MISMATCH')->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/createCreditImport', [
                'file' => $this->csvUpload(
                    'user_id,user_name,credit_type,amount,batch_no,mt4_order_id,remarks' . "\n" .
                    $userId . ',Uploaded Credit User,3,321.09,CSV-CREDIT-BATCH,990003,credit parsed from csv' . "\n"
                ),
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('credit_imports', [
            'user_id' => $userId,
            'batch_no' => 'CSV-CREDIT-BATCH',
            'credit_type' => 3,
            'amount' => '321.09',
            'mt4_order_id' => 990003,
            'remarks' => 'credit parsed from csv',
            'is_synced' => 0,
        ]);
    }

    public function test_credit_import_csv_upload_rejects_mismatched_mt4_login(): void
    {
        $admin = Admin::query()->first() ?: Admin::factory()->create();
        $now = time();
        $userId = 982412;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Batch Credit Mt4 Guard User',
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'mt4_code' => 880412,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('credit_imports')->where('user_id', $userId)->where('batch_no', 'CSV-CREDIT-MT4-MISMATCH')->delete();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/createCreditImport', [
                'file' => $this->csvUpload(
                    'user_id,user_name,mt4_login,credit_type,amount,batch_no,mt4_order_id,remarks' . "\n" .
                    $userId . ',Batch Credit Mt4 Guard User,990412,3,10.00,CSV-CREDIT-MT4-MISMATCH,0,mt4 mismatch' . "\n"
                ),
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertStringContainsString('MT4', (string) $response->json('message'));
        $this->assertDatabaseMissing('credit_imports', [
            'user_id' => $userId,
            'batch_no' => 'CSV-CREDIT-MT4-MISMATCH',
        ]);
    }

    private function csvUpload(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'batch_credit_csv_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'credit-import.csv', 'text/csv', null, true);
    }
}
