<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 09:55
 */

/**
 * Phase2CrmUiPermissionContractTest
 *
 * 文件功能：
 * - 验证 Phase2 CrmUI 权限契约：操作声明的权限 slug 真实存在、后台脚本包含权限可见性钩子并以 POST 调用 admin menus 接口获取权限。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 2 CrmUI 操作权限契约。
 *
 * 页面仍由 Blade 渲染，data-permission 只负责无权限时的前端显隐；API 的
 * check.permission:admin 继续作为最终安全边界。本测试只复用已有 permissions.slug。
 */
final class Phase2CrmUiPermissionContractTest extends TestCase
{
    /**
     * Phase 2 的 CrmUI 页面必须把新增、编辑、删除、审核和层级操作绑定到现有权限 slug。
     */
    public function test_phase_two_crmui_operations_declare_existing_permission_slugs(): void
    {
        $expectations = [
            '/admin-crmui/admins' => [
                'admin_admin_create',
                'admin_admin_update',
                'admin_admin_reset_password',
                'admin_admin_delete',
            ],
            '/admin-crmui/roles' => [
                'admin_role_create',
                'admin_role_assign_permissions',
                'admin_role_update',
                'admin_role_delete',
            ],
            '/admin-crmui/permissions' => [
                'admin_permission_create',
                'admin_permission_update',
                'admin_permission_delete',
            ],
            '/admin-crmui/users' => [
                'admin_user_status',
                'admin_user_review_auth',
            ],
            '/admin-crmui/agents' => [
                'admin_agent_descendants',
                'admin_agent_stats',
                'admin_agent_confirm',
                'admin_agent_reject_confirmation',
                'admin_agent_update_level',
                'admin_agent_update_commission',
            ],
            '/admin-crmui/authentications' => [
                'admin_auth_pending_list',
                'admin_auth_certified_list',
                'admin_user_review_auth',
            ],
            '/admin-crmui/group-configs' => [
                'admin_group_config_create',
                'admin_group_config_update',
                'admin_group_config_delete',
            ],
            '/admin-crmui/blacklist' => [
                'admin_blacklist_create',
                'admin_blacklist_update',
                'admin_blacklist_delete',
            ],
            '/admin-crmui/big-agents' => [
                'admin_big_agent_create',
                'admin_big_agent_update',
                'admin_big_agent_delete',
            ],
        ];

        foreach ($expectations as $path => $slugs) {
            $content = $this->get($path)->assertOk()->getContent();

            foreach ($slugs as $slug) {
                $this->assertStringContainsString(
                    'data-permission="' . $slug . '"',
                    $content,
                    $path . ' must expose ' . $slug . ' on the corresponding CrmUI operation.'
                );
            }
        }
    }

    /**
     * CrmUI 行操作渲染必须保留权限标记，供运行时统一隐藏无权限按钮。
     */
    public function test_crmui_admin_script_contains_permission_visibility_hook(): void
    {
        $source = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));

        $this->assertStringContainsString('data-permission', $source);
        $this->assertStringContainsString('applyPermissionVisibility', $source);
    }

    /**
     * 权限引导接口是后台 POST 路由，前端必须使用同一 HTTP 方法并带上统一请求头。
     */
    public function test_crmui_admin_script_posts_to_admin_menus_for_permissions(): void
    {
        $source = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));

        $this->assertStringContainsString(
            "request({url: '/api/admin/menus', method: 'POST'})",
            $source
        );
    }
}
