<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 16:10
 */

/**
 * AdminWhsExpZeroDualUiClosureModuleTest
 *
 * 文件功能：
 * - 验证仓位清零双 UI 页面契约：Layui 暴露候选/记录工作流与可访问筛选并就地切换表格、CrmUI 匹配记录筛选页签与动作权限，且只消费真实后台 API。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * 仓位清零双 UI 页面契约：两个页签都只消费真实后台 API，不在前端构造业务数据。
 */
class AdminWhsExpZeroDualUiClosureModuleTest extends TestCase
{
    public function test_layui_exposes_candidate_and_record_workflows_with_accessible_filters(): void
    {
        App::setLocale('zh-CN');
        $response = $this->get('/admin/whs-exp-zero')->assertOk();

        foreach ([
            'lay-filter="whsExpZeroTabs"',
            'lay-id="zero_candidates"',
            'lay-id="zero_records"',
            'id="whsExpZeroCandidateSearchForm"',
            'id="whsExpZeroRecordSearchForm"',
            'id="whsExpZeroTable"',
            'id="whsExpZeroRecordTable"',
            'name="user_id"',
            'name="user_name"',
            'name="status"',
            'name="start_date"',
            'name="end_date"',
            'type="date"',
            'value="0"',
            'value="1"',
            'value="2"',
            'value="3"',
            'data-permission="admin_whs_exp_zero_records"',
            'data-permission="admin_whs_exp_zero"',
            'role="tablist"',
            'role="tabpanel"',
        ] as $needle) {
            $response->assertSee($needle, false);
        }

        foreach ([
            __('admin.user_id'),
            __('admin.user_name'),
            __('admin.status'),
            __('admin.start_date'),
            __('admin.end_date'),
            __('admin.pending'),
            __('admin.processing'),
            __('admin.completed'),
        ] as $label) {
            $response->assertSee($label, false);
        }
    }

    public function test_layui_switches_tables_in_place_and_uses_only_real_whs_apis(): void
    {
        $module = $this->layuiModule();

        foreach ([
            "layui.use(['table', 'form', 'layer', 'jquery', 'element']",
            "url: '/api/admin/whsExpZeroList'",
            "url: '/api/admin/whsExpZeroRecords'",
            "url: '/api/admin/whsExpZero'",
            "element.on('tab(whsExpZeroTabs)'",
            "form.on('submit(searchWhsExpZeroCandidates)'",
            "form.on('submit(searchWhsExpZeroRecords)'",
            "table.reload('whsExpZeroTable'",
            "table.reload('whsExpZeroRecordTable'",
            'ensureWhsExpZeroRecords',
            'reloadWhsExpZeroRecords',
            'renderWhsExpZeroRecords',
            "field: 'balance_before'",
            "field: 'credit_amount'",
            "field: 'zero_amount'",
            "field: 'status_name'",
            "field: 'created_at'",
            "field: 'processed_at'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $module);
        }

        $this->assertStringNotContainsString('window.location', $module);
        $this->assertStringNotContainsString('mock', strtolower($module));
        $this->assertStringNotContainsString("renderWhsExpZeroRecords();\n                    table.reload('whsExpZeroRecordTable'", $module);
    }

    public function test_crmui_matches_record_filters_tabs_and_action_permissions(): void
    {
        $html = $this->get('/admin-crmui/whs-exp-zero')->assertOk()->getContent();

        foreach ([
            'data-crmui-view="zero_candidates"',
            'data-crmui-view="zero_records"',
            'data-api-url="http://localhost/api/admin/whsExpZeroList"',
            'data-api-url="http://localhost/api/admin/whsExpZeroRecords"',
            'name="user_id"',
            'name="user_name"',
            'name="status"',
            'name="start_date" type="date"',
            'name="end_date" type="date"',
            'data-permission="admin_whs_exp_zero_records"',
            'data-permission="admin_whs_exp_zero"',
            'data-crmui-row-action="one_key_zero"',
            'data-action-view="zero_candidates"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    private function layuiModule(): string
    {
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $start = strpos($script, "registry['whs-exp-zero/index']");
        $end = strpos($script, "registry['withdraw-flows/index']", (int) $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($script, (int) $start, (int) $end - (int) $start);
    }
}
