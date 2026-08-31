<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 19:22
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台持仓代理树范围兜底测试。
 *
 * 文件功能：
 * - 约束 PositionController 统一通过 FrontLegacyData 读取 agent_descendants 与 parent_id 兜底范围。
 * - 验证代理持仓查询不在控制器内直接复制关系表查询，避免不同入口产生不一致的授权树。
 *
 * 执行结果：
 * - 断言通过表示主汇总、下钻与交易明细仍共用同一代理范围规则。
 * - 断言失败表示控制器可能重新引入旧的关系树分叉实现。
 */
class FrontPositionScopeFallbackModuleTest extends TestCase
{
    public function test_front_position_controller_uses_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/PositionController.php')) ?: '';

        $this->assertStringContainsString('use App\Support\FrontLegacyData;', $source);
        $this->assertStringContainsString('$allowedAgentIds = FrontLegacyData::userScopeIds($agentId, true, 1);', $source);
        $this->assertStringContainsString('return FrontLegacyData::userScopeIds($agentId, false, 1, true);', $source);
        $this->assertStringContainsString('$allDescendantIds = FrontLegacyData::userScopeIds($agentId, true);', $source);
        $this->assertStringContainsString('in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true)', $source);
        $this->assertStringNotContainsString('use App\Models\AgentDescendant;', $source);
        $this->assertStringNotContainsString('AgentDescendant::where', $source);
    }

    public function test_final_checklist_records_front_position_controller_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 164. 2026-07-07 前台主持仓控制器 parent_id 作用域兜底闭环',
            '`Front\\PositionController`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontPositionScopeFallbackModuleTest`',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }
}
