<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台持仓汇总作用域兜底源码契约测试。
 *
 * 文件功能：
 * - 以源码断言方式验证 FrontPositionSummaryController 统一使用
 *   FrontLegacyData::userScopeIds 计算作用域（含 parent_id 兜底），
 *   不再直接查询 AgentDescendant 表。
 * - 验证权限清单文档记录了该兜底闭环。
 *
 * 适用场景：
 * - 前台持仓汇总作用域实现的回归测试，防止实现回退到旧的 descendant 直查方式。
 *
 * 入参例子：
 * - 读取 app/Http/Controllers/Front/PositionSummaryController.php 源码做字符串断言。
 *
 * 返回值：
 * - 无返回值；全部通过字符串包含/不包含断言。
 *
 * 异常或失败场景：
 * - 源码缺少 userScopeIds 调用或重新出现 AgentDescendant 直查时断言失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontPositionSummaryScopeFallbackModuleTest extends TestCase
{
    // 验证前台持仓汇总控制器统一使用共享 parent_id 树作用域兜底。
    public function test_front_position_summary_uses_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/PositionSummaryController.php')) ?: '';

        $this->assertStringContainsString('use App\Support\FrontLegacyData;', $source);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($agentId, false, null, true)', $source);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds((int) $child->user_id, true)', $source);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($agentId, true)', $source);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($agentId, false, 1)', $source);
        $this->assertStringContainsString('in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true)', $source);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', \$agentId)", $source);
    }

    // 校验权限清单文档记录了前台持仓汇总作用域兜底闭环。
    public function test_final_checklist_records_front_position_summary_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 163. 2026-07-07 前台持仓汇总 parent_id 作用域兜底闭环',
            '`FrontPositionSummaryController`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontPositionSummaryScopeFallbackModuleTest`',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }
}
