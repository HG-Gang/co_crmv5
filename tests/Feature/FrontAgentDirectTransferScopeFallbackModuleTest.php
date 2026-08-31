<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前台代理直接转账共享直属范围回退（Scope Fallback）测试。
 *
 * 文件功能：
 * - 验证 AgentController::directUserCommTrans 使用共享的 isDirectTransferTarget 校验目标是否直属。
 * - 验证该校验基于 FrontLegacyData::userScopeIds 回退实现，不再直接查询 agent_descendants 或 user_infos.parent_id。
 * - 验证最终清单文档已记录该回退逻辑。
 *
 * 适用场景：
 * - 前台代理直接佣金转账目标校验的回归测试。
 *
 * 入参例子：
 * - 无外部入参；直接读取 AgentController 源码片段断言。
 *
 * 返回值：
 * - 源码包含预期调用与回退实现时断言通过。
 *
 * 异常或失败场景：
 * - 若源码仍直接依赖 AgentDescendant::where 或 UserInfo::where(parent_id)，断言失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontAgentDirectTransferScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证代理直接转账使用共享直属范围回退实现。
     */
    public function test_front_agent_direct_transfer_uses_shared_direct_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $transferMethod = $this->sourceBetween($source, 'public function directUserCommTrans(Request $request): JsonResponse', 'public function getSubAgentsGrpIdList');
        $scopeMethod = $this->sourceBetween($source, 'private function isDirectTransferTarget(int $agentId, int $targetUserId): bool', 'private function legacyGroupColor');

        $this->assertStringNotContainsString('use App\Models\AgentDescendant;', $source);
        $this->assertStringContainsString('$this->isDirectTransferTarget($agentId, $targetUserId)', $transferMethod);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($agentId, false, null, true)', $scopeMethod);
        $this->assertStringContainsString('in_array($targetUserId, FrontLegacyData::userScopeIds($agentId, false, null, true), true)', $scopeMethod);
        $this->assertStringNotContainsString('AgentDescendant::where', $scopeMethod);
        $this->assertStringNotContainsString("UserInfo::where('parent_id', \$agentId)", $scopeMethod);
    }

    /**
     * 验证最终清单文档已记录直接转账范围回退（## 171）。
     */
    public function test_final_checklist_records_front_agent_direct_transfer_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 171.',
            '`Front\\AgentController`',
            '`directUserCommTrans`',
            '`isDirectTransferTarget`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontAgentDirectTransferScopeFallbackModuleTest`',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }

    private function sourceBetween(string $content, string $startToken, string $endToken): string
    {
        $start = strpos($content, $startToken);
        $end = $start === false ? false : strpos($content, $endToken, $start + strlen($startToken));

        if ($start === false || $end === false) {
            return '';
        }

        return substr($content, $start, $end - $start);
    }
}
