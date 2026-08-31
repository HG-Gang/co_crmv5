<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 15:59
 */

/**
 * AdminRightsSummaryPermissionMigrationTest
 *
 * 文件功能：
 * - 验证权益汇总页面与 API 的权限 slug、guard_type、route 和 api_route 由迁移类完整写入 permissions 表。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台权益汇总权限迁移测试。
 *
 * 功能逻辑说明：
 * - 权益汇总页面和 API 必须由 `permissions` 数据表驱动，不能只靠代码写死入口。
 * - 本测试直接执行迁移类，验证权限 slug、guard_type、route 和 api_route 是否完整写入。
 * - 当前本机 MySQL 3307 不可达时，本测试会暴露数据库连接错误，作为真实 DB 阻塞证据。
 */
class AdminRightsSummaryPermissionMigrationTest extends TestCase
{
    /**
     * 权益汇总页面/API 权限必须写入权限表。
     *
     * @return void
     */
    public function test_rights_summary_permissions_are_seeded_by_migration(): void
    {
        // $migrationPath：权益汇总权限迁移文件路径，用于确保权限字典可重复执行。
        $migrationPath = database_path('migrations/2026_06_07_000007_add_admin_rights_summary_permissions.php');

        $this->assertFileExists($migrationPath);

        require_once $migrationPath;
        (new \AddAdminRightsSummaryPermissions())->up();

        // $expectedPermissions：必须落到 permissions 表的权限 slug 与页面/API 映射。
        $expectedPermissions = [
            'admin_rights_summary' => [
                'route' => '/admin/rights-summary',
                'api_route' => '',
            ],
            'admin_rights_summary_list' => [
                'route' => '',
                'api_route' => 'admin_api_rightsSummaryList',
            ],
            'admin_rights_summary_export' => [
                'route' => '',
                'api_route' => 'admin_api_exportRightsSummary',
            ],
        ];

        foreach ($expectedPermissions as $slug => $expected) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            $this->assertNotNull($permission, $slug . ' 权限未写入 permissions 表');
            $this->assertSame('admin', $permission->guard_type);
            $this->assertSame($expected['route'], $permission->route);
            $this->assertSame($expected['api_route'], $permission->api_route);
        }
    }
}
