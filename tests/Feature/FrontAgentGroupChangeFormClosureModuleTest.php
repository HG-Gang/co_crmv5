<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 16:05
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台代理客户转组表单闭环测试。
 *
 * 文件功能：
 * - 校验旧详情路由传入的客户 ID 会回填到现代转组表单。
 * - 校验组别列表接口返回的 available_groups 会渲染到目标组下拉框。
 * - 防止共享 Layui 模块页升级时丢失客户转组页面的关键预填逻辑。
 *
 * 适用场景：
 * - 用户从 /user/cust/change/group/{uid} 或 /front/agent/group-change/{uid} 进入申请页。
 * - 用户直接打开组别变更列表页并提交新的客户转组申请。
 *
 * 返回值：
 * - 页面和脚本包含完整预填、动态组选项与加载调用链时测试通过。
 * - 任一环节被删除时测试失败，避免前端出现空客户或空组选项。
 */
class FrontAgentGroupChangeFormClosureModuleTest extends TestCase
{
    /**
     * 详情路由的旧客户 ID 必须只在转组表单为空时回填。
     *
     * @return void 页面未保留 ID、脚本未读取属性或强行覆盖已有快捷入口值时断言失败。
     */
    public function test_group_change_detail_route_prefills_target_customer_without_overriding_pending_action(): void
    {
        $blade = file_get_contents(resource_path('front/layui/agent/group-change.blade.php')) ?: '';
        $partial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $modulePage = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';

        $this->assertStringContainsString('data-legacy-target-user-id="{{ (int) ($legacyTargetUserId ?? 0) }}"', $partial);
        $this->assertStringContainsString("'submitApi' => '/api/front/agents/group-change-applications'", $blade);
        $this->assertStringContainsString('function applyLegacyTargetUserId()', $modulePage);
        $this->assertStringContainsString("\$page.attr('data-legacy-target-user-id')", $modulePage);
        $this->assertStringContainsString("[name=\"target_user_id\"]", $modulePage);
        $this->assertStringContainsString('if (!$targetInput.length || $.trim($targetInput.val()))', $modulePage);
        $this->assertStringContainsString('applyPendingFormValues();', $modulePage);
        $this->assertStringContainsString('applyLegacyTargetUserId();', $modulePage);
    }

    /**
     * 转组组别下拉框必须使用当前列表响应的 available_groups。
     *
     * @return void 缺少组选项响应渲染或未在成功加载后调用时断言失败。
     */
    public function test_group_change_form_renders_available_groups_from_list_response(): void
    {
        $blade = file_get_contents(resource_path('front/layui/agent/group-change.blade.php')) ?: '';
        $modulePage = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';

        $this->assertStringContainsString("'dynamicOptions' => 'available_groups'", $blade);
        $this->assertStringContainsString('function renderResponseDynamicOptions()', $modulePage);
        $this->assertStringContainsString('currentMeta[key]', $modulePage);
        $this->assertStringContainsString('renderDynamicOptions($select, normalizeOptionList(items));', $modulePage);
        $this->assertStringContainsString('currentMeta = res.data || {};', $modulePage);
        $this->assertStringContainsString('renderResponseDynamicOptions();', $modulePage);
    }
}
