<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 03:43
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台代理作用域回退与直属写入边界测试。
 *
 * 文件功能：
 * - 校验代理树查询同时使用闭包表和 parent_id 回退逻辑。
 * - 校验需要直属关系的写入动作不会退化成整棵下级树可操作。
 * - 校验迁移清单保留本模块的测试与后续验证说明。
 *
 * 适用场景：
 * - agent_descendants 尚未完整回填时，前台列表仍可按 user_infos.parent_id 查询。
 * - 佣金转账和客户转组只能操作直属下级，不能误用递归可见范围。
 *
 * 返回值：
 * - 源码契约存在时测试通过。
 * - 关键回退或直属限制被删除时测试失败，阻止行为回归。
 */
class FrontAgentScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证共享作用域会合并闭包表与 parent_id 回退树。
     *
     * @return void 缺失任一查询来源、循环保护或直属层级判断时断言失败。
     */
    public function test_front_legacy_data_scope_uses_parent_tree_as_single_source(): void
    {
        $source = file_get_contents(app_path('Support/FrontLegacyData.php')) ?: '';

        $this->assertStringContainsString('private static function parentTreeScopeIds', $source);
        $this->assertStringContainsString('private static function collectParentTreeScopeIds', $source);
        $this->assertStringContainsString('$ids = self::parentTreeScopeIds($userId, $descendantType, $directOnly);', $source);
        $this->assertStringContainsString("UserInfo::where('parent_id', \$agentId)", $source);
        $this->assertStringContainsString('if (isset($visited[$childId]))', $source);
        $this->assertStringContainsString('$matchesDirect = $directOnly === null || ($directOnly ? $depth === 1 : $depth > 1);', $source);
        $this->assertStringNotContainsString('AgentDescendant::', $source);
    }

    /**
     * 验证可见范围与需要直属授权的写入范围保持区分。
     *
     * @return void 可见性仍使用递归范围；转组和转账必须调用 directOnly=true 的直属判断。
     */
    public function test_front_agent_visibility_uses_shared_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';

        $this->assertStringContainsString('FrontLegacyData::userScopeIds($currentUserId, false)', $source);
        $this->assertStringContainsString('in_array($targetUserId', $source);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($agentId, false, null, true)', $source);
        $this->assertStringContainsString('$this->isDirectTransferTarget($agentId, $targetUserId)', $source);
        $this->assertStringNotContainsString('$isDescendant = $this->canViewUser($agentId, $targetUserId);', $source);
        $this->assertStringNotContainsString("return AgentDescendant::where('agent_id', \$currentUserId)", $source);
    }

    /**
     * 验证历史清单持续记录作用域回退模块的迁移状态。
     *
     * @return void 清单缺少测试、数据来源或后续验证说明时断言失败。
     */
    public function test_final_checklist_records_front_agent_parent_tree_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 162. 2026-07-07 前台代理 parent_id 作用域兜底闭环',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontAgentScopeFallbackModuleTest`',
            '真实数据库恢复后仍需补充代理下级列表、直属客户列表、资金流水和持仓汇总的接口级回归',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }
}
