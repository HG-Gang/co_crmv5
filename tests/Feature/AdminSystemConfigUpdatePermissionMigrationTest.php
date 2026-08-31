<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 00:17
 */

/**
 * AdminSystemConfigUpdatePermissionMigrationTest
 *
 * 文件功能：
 * - 验证系统配置更新按钮与接口权限由迁移写入 permissions 表并绑定 admin_api_updateSystemConfig 命名路由。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台系统配置更新权限迁移测试。
 *
 * 测试目标：
 * - 系统配置编辑按钮必须由 permissions 表中的 admin_system_config_update 权限驱动。
 * - 更新系统配置接口必须绑定 admin_api_updateSystemConfig，保证 check.permission:admin 能在接口层继续鉴权。
 */
class AdminSystemConfigUpdatePermissionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 系统配置更新权限迁移必须写入按钮/API 权限。
     *
     * @return void
     */
    public function test_system_config_update_permission_is_seeded_by_migration(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000002_add_admin_system_config_update_permission.php');

        $this->assertFileExists($migrationPath, '系统配置更新权限迁移文件不存在。');

        require_once $migrationPath;

        DB::table('permissions')->where('slug', 'admin_system_config_update')->delete();

        (new \AddAdminSystemConfigUpdatePermission())->up();

        $record = DB::table('permissions')->where('slug', 'admin_system_config_update')->first();

        $this->assertNotNull($record, 'admin_system_config_update 权限未写入 permissions 表。');
        $this->assertSame('admin', $record->guard_type);
        $this->assertSame(3, (int) $record->type);
        $this->assertSame('admin_api_updateSystemConfig', (string) $record->api_route);
        $this->assertSame(1, (int) $record->status);
    }
}
