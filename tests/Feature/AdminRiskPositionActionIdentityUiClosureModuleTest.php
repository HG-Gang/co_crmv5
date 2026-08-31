<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:01
 */

/**
 * AdminRiskPositionActionIdentityUiClosureModuleTest
 *
 * 文件功能：
 * - 验证双 UI 只能使用后端解析出的 MT4 强平动作身份：force_close_id 传递且不可操作行隐藏。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 锁定双 UI 只能使用后端解析出的 MT4 强平动作身份。
 */
class AdminRiskPositionActionIdentityUiClosureModuleTest extends TestCase
{
    public function test_crmui_and_layui_use_force_close_id_and_hide_unactionable_rows(): void
    {
        $this->get('/admin-crmui/risk/positions')
            ->assertOk()
            ->assertViewHas('page', function (array $page): bool {
                foreach ($page['rowActions'] ?? [] as $action) {
                    if (($action['key'] ?? '') === 'force_close') {
                        return ($action['recordKey'] ?? '') === 'force_close_id';
                    }
                }

                return false;
            });

        $layuiBlade = (string) file_get_contents(resource_path('admin/layui/risk/index.blade.php'));
        $layuiScript = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $crmUiScript = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));

        $this->assertStringContainsString('d.force_close_id', $layuiBlade);
        $this->assertStringContainsString('obj.data.force_close_id', $layuiScript);
        $this->assertStringNotContainsString(
            "'/api/admin/riskForceClose/' + encodeURIComponent(obj.data.id)",
            $layuiScript
        );
        $this->assertStringContainsString("actionKey === 'force_close'", $crmUiScript);
        $this->assertStringContainsString("recordIdentifier(row, recordKey)", $crmUiScript);
    }
}
