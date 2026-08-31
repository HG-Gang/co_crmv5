<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:53
 */

/**
 * 前端仪表盘-数据范围回退模块测试。
 *
 * 文件功能：
 * - 通过源码静态断言 DashboardController 月度指标使用 FrontLegacyData::userScopeIds 计算可见用户范围。
 * - 通过源码静态断言 FamilyTreeService::getSubAgentStats 使用 userScopeIds 统计直系/全部代理商与客户数量。
 * - 验证实现未回退到 AgentDescendant 旧查询写法。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止前端仪表盘与家族树统计在数据范围实现上回退到旧查询方式的回归测试。
 *
 * 入参例子：
 * - 无请求参数，直接读取 DashboardController.php 与 FamilyTreeService.php 源码断言。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若源码中缺少 userScopeIds 调用或出现 AgentDescendant 旧写法，测试失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontDashboardScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证前端仪表盘月度指标使用共享的父级树数据范围。
     *
     * 断言 DashboardController 源码使用 userScopeIds 并合并当前用户，
     * 且未使用 AgentDescendant 旧写法。
     */
    public function test_front_dashboard_uses_shared_parent_tree_scope_for_monthly_metrics(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/DashboardController.php')) ?: '';

        $this->assertStringContainsString('use App\Support\FrontLegacyData;', $source);
        $this->assertStringContainsString('$descendantIds = FrontLegacyData::userScopeIds($userId, false);', $source);
        $this->assertStringContainsString('$scopeUserIds = array_values(array_unique(array_merge([$userId], $descendantIds)));', $source);
        $this->assertStringNotContainsString('use App\Models\AgentDescendant;', $source);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', \$userId)", $source);
    }

    /**
     * 验证家族树仪表盘统计使用共享的父级树数据范围回退实现。
     *
     * 断言 FamilyTreeService 源码使用 userScopeIds 统计直系/全部代理商与客户数量，
     * 且未使用 AgentDescendant 旧统计写法。
     */
    public function test_family_tree_dashboard_stats_use_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Services/FamilyTreeService.php')) ?: '';

        $this->assertStringContainsString('use App\Support\FrontLegacyData;', $source);
        $this->assertStringContainsString('$directAgents = count(FrontLegacyData::userScopeIds($agentId, false, 1, true));', $source);
        $this->assertStringContainsString('$allAgents = count(FrontLegacyData::userScopeIds($agentId, false, 1));', $source);
        $this->assertStringContainsString('$directCustomers = count(FrontLegacyData::userScopeIds($agentId, false, 2, true));', $source);
        $this->assertStringContainsString('$allCustomers = count(FrontLegacyData::userScopeIds($agentId, false, 2));', $source);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', \$agentId)->where('is_direct', 1)->where('descendant_type', 1)->count()", $source);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 166 项、Front\DashboardController、FamilyTreeService::getSubAgentStats 及本测试类名。
     */
    public function test_final_checklist_records_front_dashboard_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 166. 2026-07-07 前台首页 parent_id 作用域兜底闭环',
            '`Front\\DashboardController`',
            '`FamilyTreeService::getSubAgentStats`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontDashboardScopeFallbackModuleTest`',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }
}
