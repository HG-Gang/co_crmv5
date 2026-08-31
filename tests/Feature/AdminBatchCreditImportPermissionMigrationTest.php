<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/12
 * Time: 23:58
 */

/**
 * AdminBatchCreditImportPermissionMigrationTest
 *
 * 文件功能：
 * - 验证批量信用导入的页面入口、列表接口与新增接口权限由迁移类写入 permissions 表。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台批量信用导入权限迁移测试。
 *
 * 功能逻辑说明：
 * - 批量信用导入会影响客户信用额度，属于资金相关高风险操作。
 * - 页面入口、列表接口和新增接口必须全部来自 `permissions` 数据表配置。
 * - 本测试直接执行迁移类，验证 `permissions.slug` 和 `permissions.api_route` 是否完整写入。
 */
class AdminBatchCreditImportPermissionMigrationTest extends TestCase
{
    /**
     * 批量信用导入页面和动作权限必须写入权限表。
     *
     * @return void
     */
    public function test_credit_import_permissions_are_seeded_by_migration(): void
    {
        // $migrationPath：权限迁移文件路径，用于保证权限配置有可重复执行的数据表来源。
        $migrationPath = database_path('migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php');

        $this->assertFileExists($migrationPath);

        require_once $migrationPath;
        (new \AddAdminBatchCreditImportPermissions())->up();

        // $expectedPermissions：必须写入 permissions 表的权限 slug 和接口路由名。
        $expectedPermissions = [
            'admin_credit_imports' => '',
            'admin_batch_credit_import_list' => 'admin_api_creditImportList',
            'admin_batch_credit_import_create' => 'admin_api_createCreditImport',
            'admin_batch_credit_import_template' => 'admin_api_creditImportTemplate',
            'admin_batch_credit_import_export' => 'admin_api_exportCreditImports',
            'admin_batch_credit_import_retry' => 'admin_api_retryCreditImport',
            'admin_batch_credit_import_sync' => 'admin_api_syncCreditImport',
        ];

        foreach ($expectedPermissions as $slug => $apiRoute) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            $this->assertNotNull($permission, $slug . ' 权限未写入 permissions 表');
            $this->assertSame('admin', $permission->guard_type);
            $this->assertSame($apiRoute, $permission->api_route);
        }
    }
}
