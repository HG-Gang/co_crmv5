<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 19:42
 */

/**
 * AdminWithdrawalsUiClosureModuleTest
 *
 * 文件功能：
 * - 验证后台双 UI 出金列表交互契约：Layui 完整筛选与权限动作、真实列与拒绝原因、CrmUI 导出/筛选/详情/原因/权限暴露。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 锁定后台双 UI 出金列表的筛选、详情、导出和拒绝原因交互。
 */
class AdminWithdrawalsUiClosureModuleTest extends TestCase
{
    public function test_layui_withdrawal_page_renders_complete_filters_and_permission_actions(): void
    {
        $response = $this->get('/admin/withdrawals')->assertOk();

        foreach ([
            'name="local_order_no"',
            'name="mt4_ticket"',
            'name="user_id"',
            'name="status"',
            'name="start_date"',
            'name="end_date"',
            'id="withdrawStartDate"',
            'id="withdrawEndDate"',
            'id="exportWithdrawals"',
            'lay-event="detail"',
            'data-permission="admin_withdraw_export"',
            'data-permission="admin_withdraw_process"',
            'data-permission="admin_withdraw_complete"',
            'data-permission="admin_withdraw_reject"',
        ] as $needle) {
            $response->assertSee($needle, false);
        }

        $response->assertSee('for="withdrawLocalOrderNo"', false)
            ->assertSee('for="withdrawMt4Ticket"', false)
            ->assertSee('for="withdrawStartDate"', false)
            ->assertSee('for="withdrawEndDate"', false);
    }

    public function test_layui_withdrawal_script_uses_real_columns_current_filters_and_reject_reason(): void
    {
        $source = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $start = strpos($source, "registry['withdrawals/index']");
        $end = strpos($source, "registry['legacy/users/index']", $start === false ? 0 : $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $module = substr($source, (int) $start, (int) $end - (int) $start);

        foreach ([
            "field: 'local_order_no'",
            "field: 'mt4_ticket'",
            "field: 'apply_amount'",
            "field: 'actual_amount'",
            "field: 'fee'",
            "laydate.render({elem: '#withdrawStartDate'",
            "laydate.render({elem: '#withdrawEndDate'",
            "downloadAdminCsv($, layer, '/api/admin/exportWithdrawals'",
            "obj.event === 'detail'",
            "obj.event === 'reject'",
            'layer.prompt',
            "{reason: reason}",
        ] as $needle) {
            $this->assertStringContainsString($needle, $module);
        }

        $this->assertStringNotContainsString("field: 'amount'", $module);
    }

    public function test_crmui_withdrawal_page_exposes_export_filters_detail_reason_and_permissions(): void
    {
        $response = $this->get('/admin-crmui/withdrawals')->assertOk();

        foreach ([
            'name="local_order_no"',
            'name="mt4_ticket"',
            'name="user_id"',
            'name="start_date"',
            'name="end_date"',
            'data-crmui-action="export"',
            'data-crmui-row-action="detail"',
            'data-crmui-row-action="reject"',
            'data-permission="admin_withdraw_process"',
            'data-permission="admin_withdraw_complete"',
            'data-permission="admin_withdraw_reject"',
            'name:reason:textarea:',
            '<th data-key="reject_reason">',
        ] as $needle) {
            $response->assertSee($needle, false);
        }

        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $this->assertStringContainsString(
            "exportActions('admin_api_exportWithdrawals', 'withdrawals_export.csv')",
            $controller
        );
    }
}
