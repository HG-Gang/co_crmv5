<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/30
 * Time: 02:10
 */

/**
 * AdminWithdrawBatchApplyUiClosureModuleTest
 *
 * 文件功能：
 * - 锁定后台出金「批量审核」入口在 Layui 与 CrmUI 两套家族均已暴露，且复刻旧四个状态页的全部约束。
 * - 旧行为参照：项目1 resources/views/admin/withdraw_status/{pending,processing,completed,failed}_browse.blade.php
 *   各自带勾选列 + 「批量操作」按钮 + 目标状态弹窗（含备注），跃迁规则 0→{1,2,3}、1→{2,3}，
 *   目标为 3（拒绝）时备注必填，终态行（status 2/3）禁止勾选。
 * - 同时锁定实现取向：复用旧 URI index/admin/amount/batchWithdrawApply（已具备 JWT 通道 +
 *   按 payload.status 动态改判权限），不新增现代端点、不新增 permissions 记录。
 * - 输入：Blade 渲染 HTML 与前端 JS 源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖批量接口的运行时状态机行为（由 AdminLegacyBatchWithdrawApplyClosureModuleTest 锁定）。
 */

namespace Tests\Feature;

use App\Models\Admin;
use Tests\TestCase;

/**
 * 后台出金批量审核双 UI 闭环测试。
 */
class AdminWithdrawBatchApplyUiClosureModuleTest extends TestCase
{
    /**
     * 旧后台批量出金入口 URI。两套 UI 都必须提交到这个地址，
     * 因为它承载了「按目标状态动态改判权限 + 逐条复用现代状态机」的既有闭环。
     *
     * @var string
     */
    private const LEGACY_BATCH_URI = '/index/admin/amount/batchWithdrawApply';

    /**
     * 锁定 Layui 出金页已渲染批量入口按钮与批量弹窗骨架。
     *
     * 为什么必须锁定：旧后台四个状态页各自带「批量操作」按钮，新后台合并为单页后
     * 曾经只有导出与单行操作，批量能力在后端已实现却没有任何 UI 入口，属于逻辑未闭环。
     * 备注上限断言 500 与后端 reject() 的 reason 校验对齐，防止前端放宽后整批被后端拒绝。
     *
     * @return void
     */
    public function test_layui_withdrawals_page_exposes_batch_entry_and_modal(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $html = $this->actingAs($admin, 'admin')
            ->get('/admin/withdrawals')
            ->assertOk()
            ->getContent();

        // 批量入口按钮与权限标记：slug 仅用于前端显隐，后端仍按目标状态独立鉴权。
        $this->assertStringContainsString('id="batchWithdrawButton"', $html);
        $this->assertStringContainsString('data-permission="admin_withdraw_process"', $html);

        // 弹窗骨架：三个目标状态单选 + 备注输入，备注上限与后端 reject() 的 500 字一致。
        $this->assertStringContainsString('id="batchWithdrawModal"', $html);
        foreach (['1', '2', '3'] as $target) {
            $this->assertStringContainsString(
                '<input type="radio" name="target_status" value="' . $target . '"',
                $html
            );
        }
        $this->assertStringContainsString('id="batchWithdrawRemark"', $html);
        $this->assertStringContainsString('maxlength="500"', $html);
    }

    /**
     * 锁定 Layui 批量脚本逐条复刻旧页面的跃迁约束、备注必填与终态禁选规则。
     *
     * 为什么按源码文本断言：这些约束是纯前端交互逻辑，没有 HTTP 出口可断言，
     * 一旦被后续重构悄悄删掉，管理员会提交必然失败的批次却得不到解释。
     * 跃迁白名单必须与旧 updateRadioButtons 等价，否则会放开旧逻辑禁止的状态跳转。
     *
     * @return void
     */
    public function test_layui_batch_module_replicates_legacy_transition_and_remark_rules(): void
    {
        $module = $this->layuiWithdrawalsModule();

        // 勾选列：旧四个状态页都以 checkbox 列承载批量选择。
        $this->assertStringContainsString("{type: 'checkbox', fixed: 'left'}", $module);

        // 跃迁白名单必须与旧 updateRadioButtons 等价：来源 0 可到 1/2/3，来源 1 只能到 2/3。
        $this->assertStringContainsString("'0': ['1', '2', '3']", $module);
        $this->assertStringContainsString("'1': ['2', '3']", $module);

        // 三段勾选校验：非空、来源状态可批量、来源状态一致。
        $this->assertStringContainsString('admin.batch_select_records_first', $module);
        $this->assertStringContainsString('admin.batch_select_processable_only', $module);
        $this->assertStringContainsString('admin.batch_select_same_status', $module);

        // 目标为拒绝时备注必填，复用单条拒绝的同一提示键。
        $this->assertStringContainsString("targetStatus === '3'", $module);
        $this->assertStringContainsString('admin.reject_reason_required', $module);

        // 终态行禁用勾选：避免提交注定被状态机拒绝的行。
        $this->assertStringContainsString('disableTerminalWithdrawCheckboxes', $module);
        $this->assertStringContainsString("status === '2' || status === '3'", $module);

        // 提交目标与 CSRF：旧 URI 位于 web 中间件组内，除 JWT 外必须带 X-CSRF-TOKEN。
        $this->assertStringContainsString(self::LEGACY_BATCH_URI, $module);
        $this->assertStringContainsString("'X-CSRF-TOKEN'", $module);
        $this->assertStringContainsString('orderList:', $module);
        $this->assertStringContainsString('recordId: row.id', $module);
    }

    /**
     * 锁定 CrmUI 出金页同样暴露批量入口，且声明式配置由后端下发。
     *
     * 为什么要求配置来自后端：跃迁白名单属于业务规则，写死在前端会与后端状态机产生双口径；
     * 由 PageController 的 batch 声明统一下发，可保证两套 UI 与后端共用同一份规则。
     *
     * @return void
     */
    public function test_crmui_withdrawals_page_exposes_batch_entry_and_modal(): void
    {
        $html = $this->get('/admin-crmui/withdrawals')->assertOk()->getContent();

        // 批量入口按钮及其声明式配置：URI、来源状态字段与跃迁白名单都由后端定义传入。
        $this->assertStringContainsString('data-crmui-batch-open', $html);
        $this->assertStringContainsString('data-batch-source-field="status"', $html);
        $this->assertStringContainsString('data-batch-transitions=', $html);
        $this->assertStringContainsString('data-batch-target-statuses=', $html);
        $this->assertStringContainsString(self::LEGACY_BATCH_URI, $html);

        // 勾选列与全选框，以及批量弹窗的三个目标状态与备注。
        $this->assertStringContainsString('data-crmui-select-column', $html);
        $this->assertStringContainsString('data-crmui-select-all', $html);
        $this->assertStringContainsString('data-crmui-batch-modal', $html);
        $this->assertStringContainsString('data-crmui-batch-remark', $html);
        foreach (['1', '2', '3'] as $target) {
            $this->assertStringContainsString('value="' . $target . '"', $html);
        }
    }

    /**
     * 锁定批量能力是「特性开关」而非全局默认，未声明的页面不得出现任何批量节点。
     *
     * 为什么必须锁定：批量勾选列写在 CrmUI 共享渲染器里，约 49 个后台页面共用该渲染器。
     * 若渲染器无条件输出勾选列，会给所有列表页凭空加一列并破坏既有列宽与表头契约。
     * 这条测试是共享渲染器改动的影响面护栏。
     *
     * @return void
     */
    public function test_crmui_batch_is_opt_in_and_absent_from_pages_without_declaration(): void
    {
        // 特性开关取向：未声明 batch 的页面不得渲染任何勾选列或批量节点，
        // 否则共享渲染器的改动会波及其余 CrmUI 页面。
        foreach (['/admin-crmui/deposits', '/admin-crmui/users'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('data-crmui-batch-open', $html, $path);
            $this->assertStringNotContainsString('data-crmui-select-column', $html, $path);
            $this->assertStringNotContainsString('data-crmui-batch-modal', $html, $path);
        }
    }

    /**
     * 锁定 CrmUI 渲染器的批量约束与 Layui 家族同口径，并正确处理部分失败。
     *
     * 最关键的一条是部分失败处理：后端批量部分失败返回 3006，
     * 该码不在共享 businessCodeSucceeded 白名单内，会被当作请求错误走 onError。
     * 若不在 onError 里识别 data.total 并按批量结果渲染，管理员只会看到一句「请求失败」，
     * 而实际上已有部分记录被真实改状态——这是最危险的一类静默不一致。
     *
     * @return void
     */
    public function test_crmui_renderer_replicates_legacy_batch_constraints(): void
    {
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        // 勾选与全选联动，以及重渲染后清空选择（服务端分页下不跨页保持选择）。
        $this->assertStringContainsString('function bindBatchActions()', $script);
        $this->assertStringContainsString('function syncBatchSelectionState(', $script);
        $this->assertStringContainsString('function resetBatchSelection(', $script);

        // 终态行禁用勾选：来源状态不在 transitions 白名单内即 disabled。
        $this->assertStringContainsString('function batchSelectCellHtml(', $script);
        $this->assertStringContainsString("eligible ? '' : ' disabled'", $script);

        // 三段勾选校验与备注必填，键名与 Layui 家族保持一致。
        $this->assertStringContainsString('admin.batch_select_records_first', $script);
        $this->assertStringContainsString('admin.batch_select_same_status', $script);
        $this->assertStringContainsString('admin.batch_target_status_invalid', $script);
        $this->assertStringContainsString('admin.reject_reason_required', $script);

        // 部分失败（3006）不在 businessCodeSucceeded 白名单内，必须在 onError 里按批量结果渲染，
        // 不能当作请求错误吞掉逐条聚合数。
        $this->assertStringContainsString("typeof response.data.total !== 'undefined'", $script);
        $this->assertStringContainsString('function finishBatchSubmit(', $script);
    }

    /**
     * 锁定实现取向：复用旧批量端点，不新增 /api/admin 现代路由。
     *
     * 为什么这是安全边界而非风格偏好：后台 API 受 check.permission:admin 保护，
     * 按 permissions.api_route 逐条鉴权。新增路由若不同步新增 permissions 记录，
     * 非超管一律 403；而新增 permissions 记录属于改动生产权限表的高风险操作。
     * 旧端点已按 payload.status 动态映射到既有三个权限点，因此复用它是零权限变更的唯一路径。
     *
     * @return void
     */
    public function test_batch_entry_reuses_legacy_endpoint_without_new_modern_route(): void
    {
        $adminRoutes = file_get_contents(base_path('routes/admin.php')) ?: '';

        // 取向锁定：不得为批量出金新增 /api/admin 现代端点，
        // 否则需要新增 permissions 记录才能让非超管使用，属于动生产权限表的高风险变更。
        $this->assertStringNotContainsString('withdrawBatchApply', $adminRoutes);
        $this->assertStringNotContainsString('admin_api_withdrawBatchApply', $adminRoutes);
    }

    /**
     * 锁定批量相关语言键在中英文两套语言包均存在且非空。
     *
     * 为什么必须锁定：缺键时 Laravel 的 __() 会原样返回键名，
     * 页面会直接显示 admin.batch_operation 这类原始字符串，属肉眼可见的瑕疵；
     * 值为空字符串则会渲染出无文字的按钮，比缺键更难被发现。
     *
     * @return void
     */
    public function test_batch_language_keys_exist_in_both_locales(): void
    {
        $keys = [
            'admin.batch_operation',
            'admin.batch_withdraw_title',
            'admin.batch_target_status',
            'admin.batch_select_records_first',
            'admin.batch_select_processable_only',
            'admin.batch_select_same_status',
            'admin.batch_target_status_required',
            'admin.batch_target_status_invalid',
            'admin.batch_selected_count',
            'admin.batch_withdraw_result',
            'crmui.actions.select_all',
        ];

        foreach (['zh-CN', 'en'] as $locale) {
            foreach ($keys as $key) {
                $value = __($key, [], $locale);

                $this->assertNotSame($key, $value, "缺少语言键 {$key}（{$locale}）");
                $this->assertNotSame('', trim((string) $value), "语言键为空 {$key}（{$locale}）");
            }
        }
    }

    /**
     * 截取 Layui pages.js 中出金页注册块，避免断言命中其他模块的同名片段。
     *
     * @return string registry['withdrawals/index'] 到下一个 registry 之间的源码。
     */
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
