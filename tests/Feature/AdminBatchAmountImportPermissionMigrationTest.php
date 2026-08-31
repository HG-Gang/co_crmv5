<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 15:23
 */

/**
 * AdminBatchAmountImportPermissionMigrationTest
 *
 * 文件功能：
 * - 验证批量入金/出金导入的页面入口、列表接口与新增接口权限由迁移类写入 permissions 表。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台批量入金/出金权限迁移测试。
 *
 * 功能逻辑说明：
 * - 批量资金导入属于高风险资金操作，页面入口、列表接口和新增接口都必须写入 `permissions` 表。
 * - 前端按钮通过 `permissions.slug` 控制显示，后端接口通过 `permissions.api_route` 与 `check.permission:admin` 做二次鉴权。
 * - 本测试直接执行迁移类，验证数据表配置是否完整，避免只在页面中写死按钮权限。
 */
class AdminBatchAmountImportPermissionMigrationTest extends TestCase
{
    /**
     * 批量入金/出金页面和动作权限必须写入权限表。
     *
     * @return void
     */
    public function test_batch_amount_import_permissions_are_seeded_by_migration(): void
    {
        // $migrationPath：权限迁移文件路径，确保本模块权限配置有可重复执行的数据库来源。
        $migrationPath = database_path('migrations/2026_06_07_000004_add_admin_batch_amount_import_permissions.php');

        $this->assertFileExists($migrationPath);

        require_once $migrationPath;
        (new \AddAdminBatchAmountImportPermissions())->up();

        // $expectedPermissions：必须写入 permissions 表的 slug 与 api_route 对应关系。
        $expectedPermissions = [
            'admin_deposit_imports' => '',
            'admin_batch_deposit_import_list' => 'admin_api_depositImportList',
            'admin_batch_deposit_import_create' => 'admin_api_createDepositImport',
            'admin_batch_deposit_import_template' => 'admin_api_depositImportTemplate',
            'admin_batch_deposit_import_export' => 'admin_api_exportDepositImports',
            'admin_batch_deposit_import_retry' => 'admin_api_retryDepositImport',
            'admin_withdraw_imports' => '',
            'admin_batch_withdraw_import_list' => 'admin_api_withdrawImportList',
            'admin_batch_withdraw_import_create' => 'admin_api_createWithdrawImport',
            'admin_batch_withdraw_import_template' => 'admin_api_withdrawImportTemplate',
            'admin_batch_withdraw_import_export' => 'admin_api_exportWithdrawImports',
            'admin_batch_withdraw_import_retry' => 'admin_api_retryWithdrawImport',
        ];

        foreach ($expectedPermissions as $slug => $apiRoute) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            $this->assertNotNull($permission, $slug . ' 权限未写入 permissions 表');
            $this->assertSame('admin', $permission->guard_type);
            $this->assertSame($apiRoute, $permission->api_route);
        }
    }
}
