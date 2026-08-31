<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminButtonPermissionVisibilityTest
 *
 * 文件功能：
 * - 验证后台布局 JS 统一缓存权限并隐藏无权限按钮，敏感按钮必须声明 permission slug。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台按钮权限展示回归测试。
 *
 * 测试目标：
 * - Blade 页面中的敏感操作按钮必须声明 data-permission。
 * - data-permission 的值必须对应 permissions.slug，前端才能按菜单接口返回的权限数组隐藏按钮。
 * - layout.js 必须提供统一的权限缓存和按钮显隐逻辑，不能每个页面各写一套判断。
 */
class AdminButtonPermissionVisibilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 敏感按钮必须在 Blade 模板中声明权限 slug。
     *
     * @return void
     */
    public function test_sensitive_buttons_declare_permission_slugs(): void
    {
        $expectations = [
            '/admin/users' => ['admin_user_status'],
            '/admin/roles' => ['admin_role_create', 'admin_role_update', 'admin_role_delete', 'admin_role_assign_permissions'],
            '/admin/permissions' => ['admin_permission_update'],
            '/admin/menus' => ['admin_menu_create', 'admin_menu_update'],
            '/admin/data-scopes' => [
                'admin_data_scope_role_save',
                'admin_data_scope_binding_save',
                'admin_data_scope_binding_delete',
            ],
            '/admin/deposits' => ['admin_deposit_approve', 'admin_deposit_reject'],
            '/admin/withdrawals' => ['admin_withdraw_process', 'admin_withdraw_complete', 'admin_withdraw_reject'],
            '/admin/commissions' => ['admin_commission_settle'],
            '/admin/vouchers' => ['admin_voucher_approve', 'admin_voucher_reject'],
            '/admin/risk' => ['admin_risk_force_close'],
            '/admin/cancel-applies' => ['admin_cancel_apply_approve', 'admin_cancel_apply_reject'],
        ];

        foreach ($expectations as $path => $slugs) {
            $response = $this->get($path);
            $response->assertOk();

            foreach ($slugs as $slug) {
                $response->assertSee('data-permission="' . $slug . '"', false);
            }
        }
    }

    /**
     * 后台布局 JS 必须统一缓存权限并隐藏无权限按钮。
     *
     * @return void
     */
    public function test_layout_js_contains_unified_button_permission_visibility_logic(): void
    {
        $layoutJs = $this->adminLayuiScript('layout.js');

        $this->assertStringContainsString('window.CrmAdminPermissions', $layoutJs);
        $this->assertStringContainsString('applyPermissionVisibility', $layoutJs);
        $this->assertStringContainsString('data-permission', $layoutJs);
        $this->assertStringContainsString('crm_admin_permissions', $layoutJs);
    }
}
