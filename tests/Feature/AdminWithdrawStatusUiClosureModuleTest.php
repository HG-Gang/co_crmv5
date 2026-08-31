<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 12:50
 */

/**
 * AdminWithdrawStatusUiClosureModuleTest
 *
 * 文件功能：
 * - 验证后台出金状态双 UI 契约：Layui/CrmUI 状态页各渲染一个固定隐藏状态且查询参数不可覆盖、汇总状态选择可切换、筛选/搜索/重置/导出共用表单。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use App\Models\Admin;
use Tests\TestCase;

/**
 * 锁定后台双 UI 专属出金状态页，防止查询参数和页面操作覆盖固定状态。
 */
class AdminWithdrawStatusUiClosureModuleTest extends TestCase
{
    /**
     * @return array<string, array{state:string, status:string, otherStatus:string}>
     */
    public static function withdrawStatusProvider(): array
    {
        return [
            'pending' => ['state' => 'pending', 'status' => '0', 'otherStatus' => '3'],
            'processing' => ['state' => 'processing', 'status' => '1', 'otherStatus' => '0'],
            'completed' => ['state' => 'completed', 'status' => '2', 'otherStatus' => '1'],
            'failed' => ['state' => 'failed', 'status' => '3', 'otherStatus' => '2'],
        ];
    }

    /**
     * @dataProvider withdrawStatusProvider
     */
    public function test_layui_status_pages_render_one_fixed_hidden_status(
        string $state,
        string $status,
        string $otherStatus
    ): void {
        $admin = Admin::query()->findOrFail(1);

        foreach (['/index/admin/withdraw/', '/admin/withdraw/'] as $prefix) {
            $html = $this->actingAs($admin, 'admin')
                ->get($prefix . $state . '?status=' . $otherStatus)
                ->assertOk()
                ->getContent();

            $this->assertFixedStatusFilter($html, $status, $prefix . $state);
            $this->assertStringContainsString('data-layui-page="withdrawals/index"', $html);
        }
    }

    public function test_layui_withdrawal_summary_keeps_switchable_status_select(): void
    {
        $html = $this->get('/admin/withdrawals')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<select\b[^>]*\bname="status"[^>]*>/i', $html);
        foreach (['0', '1', '2', '3'] as $status) {
            $this->assertStringContainsString('<option value="' . $status . '"', $html);
        }
        $this->assertSame(0, $this->hiddenStatusCount($html));
    }

    public function test_layui_load_search_reset_and_export_share_the_filter_form(): void
    {
        $module = $this->layuiWithdrawalsModule();

        $this->assertStringContainsString('function currentWithdrawFilters()', $module);
        $this->assertStringContainsString("return serializeForm($, '#withdrawSearchForm');", $module);
        $this->assertStringContainsString('where: currentWithdrawFilters(),', $module);
        $this->assertStringContainsString(
            "table.reload('withdrawTable', {where: currentWithdrawFilters(), page: {curr: 1}});",
            $module
        );
        $this->assertStringContainsString("$('#withdrawSearchForm').on('reset'", $module);
        $this->assertStringContainsString(
            "downloadAdminCsv($, layer, '/api/admin/exportWithdrawals', currentWithdrawFilters(), 'withdrawals_export.csv');",
            $module
        );
        $this->assertStringNotContainsString('{where: data.field', $module);
    }

    /**
     * @dataProvider withdrawStatusProvider
     */
    public function test_crmui_status_pages_render_one_fixed_hidden_status_and_ignore_query_override(
        string $state,
        string $status,
        string $otherStatus
    ): void {
        $html = $this->get('/admin-crmui/withdraw/' . $state . '?status=' . $otherStatus)
            ->assertOk()
            ->getContent();

        $this->assertFixedStatusFilter($html, $status, '/admin-crmui/withdraw/' . $state);
        $this->assertStringContainsString('data-crmui-page="admin.withdrawals"', $html);
    }

    public function test_crmui_summary_and_filter_data_flow_remain_shared(): void
    {
        $html = $this->get('/admin-crmui/withdrawals')->assertOk()->getContent();
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        $this->assertMatchesRegularExpression('/<select\b[^>]*\bname="status"[^>]*>/i', $html);
        foreach (['0', '1', '2', '3'] as $status) {
            $this->assertStringContainsString('<option value="' . $status . '"', $html);
        }
        $this->assertSame(0, $this->hiddenStatusCount($html));

        $this->assertStringContainsString("readForm(\$page.find('[data-crmui-filter]'))", $script);
        $this->assertStringContainsString('var filter = currentPageFilter($page);', $script);
        $this->assertStringContainsString('currentPageFilter($page), {export: 1}', $script);
        $this->assertStringContainsString("\$page.find('[data-crmui-filter]')[0].reset();", $script);
        $this->assertStringContainsString('loadPage($page);', $script);
    }

    private function assertFixedStatusFilter(string $html, string $status, string $path): void
    {
        $pattern = '/<input\b(?=[^>]*\btype="hidden")(?=[^>]*\bname="status")(?=[^>]*\bvalue="'
            . preg_quote($status, '/') . '")[^>]*>/i';

        $this->assertSame(1, preg_match_all($pattern, $html), '专属状态页未输出唯一固定 status：' . $path);
        $this->assertDoesNotMatchRegularExpression('/<select\b[^>]*\bname="status"[^>]*>/i', $html);
    }

    private function hiddenStatusCount(string $html): int
    {
        return preg_match_all(
            '/<input\b(?=[^>]*\btype="hidden")(?=[^>]*\bname="status")[^>]*>/i',
            $html
        );
    }

    private function layuiWithdrawalsModule(): string
    {
        $source = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $start = strpos($source, "registry['withdrawals/index']");
        $end = strpos($source, "registry['legacy/users/index']", $start === false ? 0 : $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($source, (int) $start, (int) $end - (int) $start);
    }
}
