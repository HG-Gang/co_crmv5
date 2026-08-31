<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:32
 */

/**
 * DefaultAdminAndFrontMenuRoleMigrationTest
 *
 * 文件功能：
 * - 验证默认管理员与前台菜单角色迁移声明必要修复项，且前台代理与客户角色声明不同的菜单范围。
 * - 输入：权限/结构迁移类与测试数据库；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证 check.permission 中间件的运行时鉴权与按钮渲染（由模块契约测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 默认账号与前台菜单角色初始化迁移测试。
 *
 * 功能逻辑说明：
 * - 后台登录控制器读取的是 admins 表，因此默认超级管理员必须写入 admins 表，而不是只写 admin_logins 表。
 * - 前台 Layui 菜单接口按 user_logins.role_id 读取 roles 与 role_permissions，因此初始化数据必须补齐前台角色、菜单权限和授权关系。
 * - 本测试只做迁移文件契约检查，不连接真实 DB，避免本机 3307 暂不可用时掩盖代码层缺口。
 */
class DefaultAdminAndFrontMenuRoleMigrationTest extends TestCase
{
    /**
     * 默认后台账号与前台菜单授权修复迁移必须存在并声明完整职责。
     *
     * 参数和断言含义：
     * - $migrationPath：目标迁移文件路径，是后续真实 DB 执行 `php artisan migrate` 的唯一初始化来源。
     * - $content：迁移源码内容，用于确认关键表、字段、账号、角色和菜单权限没有漏写。
     *
     * @return void
     */
    public function test_default_admin_and_front_menu_role_migration_declares_required_repairs(): void
    {
        $migrationPath = database_path('migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php');

        $this->assertFileExists($migrationPath, '默认超级管理员和前台菜单角色修复迁移不存在。');

        $content = file_get_contents($migrationPath);

        $this->assertStringContainsString("Schema::table('user_logins'", $content, '迁移必须兼容补齐 user_logins.role_id 字段。');
        $this->assertStringContainsString("'role_id'", $content, '迁移必须维护前台用户登录记录的 role_id。');
        $this->assertStringContainsString("DB::table('admins')->updateOrInsert", $content, '默认后台账号必须写入当前登录模型读取的 admins 表。');
        $this->assertStringContainsString("'superadmin'", $content, '迁移必须声明超级管理员登录账号 superadmin。');
        $this->assertStringContainsString("'abc123'", $content, '迁移必须声明统一的超级管理员初始密码 abc123。');
        $this->assertStringNotContainsString('Admin@123456', $content, '迁移不得继续保留已废止的超级管理员初始密码。');
        $this->assertStringContainsString("DB::table('role_permissions')->updateOrInsert", $content, '前台菜单授权必须写入 role_permissions 中间表。');

        foreach ($this->requiredFrontMenuSlugs() as $slug) {
            $this->assertStringContainsString($slug, $content, $slug . ' 前台菜单权限未在迁移中声明。');
        }
    }

    /**
     * agent 登录后 Layui 左侧菜单至少需要的前台权限 slug。
     *
     * @return array<int, string> permissions.slug 列表。
     */
    /**
     * 前台代理商和普通客户必须使用两套不同菜单授权配置。
     *
     * 业务逻辑说明：
     * - agentMenuSlugs 是代理商登录后 Layui 左侧菜单的授权来源，必须包含代理管理和返佣管理菜单。
     * - customerMenuSlugs 是普通客户登录后 Layui 左侧菜单的授权来源，不能包含代理管理和返佣管理菜单。
     * - 两个菜单集合都来自迁移写入的 permissions 与 role_permissions 配置，避免前端写死菜单造成权限绕过。
     *
     * 参数含义：
     * - $migration：迁移类实例，用于通过反射读取私有菜单授权配置。
     * - $agentSlugs：代理商角色应拥有的 permissions.slug 列表。
     * - $customerSlugs：普通客户角色应拥有的 permissions.slug 列表。
     *
     * @return void
     */
    public function test_front_agent_and_customer_roles_declare_different_menu_scopes(): void
    {
        $migration = $this->makeMigrationInstance();

        $agentSlugs = $this->callPrivateArrayMethod($migration, 'agentMenuSlugs');
        $customerSlugs = $this->callPrivateArrayMethod($migration, 'customerMenuSlugs');

        foreach (['front_agent', 'front_agent_sub', 'front_agent_customers', 'front_commission', 'front_commission_rt'] as $agentOnlySlug) {
            $this->assertContains($agentOnlySlug, $agentSlugs, '代理商菜单必须包含代理或返佣专属权限：' . $agentOnlySlug);
            $this->assertNotContains($agentOnlySlug, $customerSlugs, '普通客户菜单不能包含代理或返佣专属权限：' . $agentOnlySlug);
        }

        foreach (['front_dashboard', 'front_profile', 'front_account', 'front_deposit_withdraw', 'front_trading', 'front_gift', 'front_news'] as $sharedSlug) {
            $this->assertContains($sharedSlug, $agentSlugs, '代理商菜单必须保留通用权限：' . $sharedSlug);
            $this->assertContains($sharedSlug, $customerSlugs, '普通客户菜单必须保留通用权限：' . $sharedSlug);
        }
    }

    private function requiredFrontMenuSlugs(): array
    {
        return [
            'front_dashboard',
            'front_profile',
            'front_account',
            'front_account_info',
            'front_account_balance',
            'front_deposit_withdraw',
            'front_deposit',
            'front_withdraw',
            'front_flow',
            'front_trading',
            'front_position_summary',
            'front_open_orders',
            'front_closed_orders',
            'front_agent',
            'front_agent_sub',
            'front_agent_customers',
            'front_agent_confirm',
            'front_group_change',
            'front_commission',
            'front_commission_rt',
            'front_commission_hist',
            'front_commission_transfer',
            'front_gift',
            'front_gift_address',
            'front_gift_list',
            'front_news',
        ];
    }

    /**
     * 创建默认管理员与前台菜单角色迁移实例。
     *
     * 参数含义：
     * - $migrationPath：迁移源码路径，用于在测试进程中加载迁移类。
     *
     * @return object 迁移类实例。
     */
    private function makeMigrationInstance(): object
    {
        $migrationPath = database_path('migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php');
        require_once $migrationPath;

        return new \FixDefaultAdminAndFrontMenuRoles();
    }

    /**
     * 通过反射调用迁移类私有数组方法。
     *
     * 参数含义：
     * - $migration：迁移类实例。
     * - $methodName：需要读取的私有方法名称，例如 agentMenuSlugs 或 customerMenuSlugs。
     *
     * @param object $migration 迁移类实例。
     * @param string $methodName 私有方法名称。
     * @return array<int, string> 私有方法返回的菜单权限 slug 列表。
     */
    private function callPrivateArrayMethod(object $migration, string $methodName): array
    {
        $method = new \ReflectionMethod($migration, $methodName);
        $method->setAccessible(true);

        return $method->invoke($migration);
    }
}
