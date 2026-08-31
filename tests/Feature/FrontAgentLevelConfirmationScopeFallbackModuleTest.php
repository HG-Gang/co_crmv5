<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 前端代理商级别确认-数据范围回退模块测试。
 *
 * 文件功能：
 * - 通过源码静态断言 AgentController::confirmLevel 使用 FrontLegacyData::userScopeIds 获取可见代理商范围。
 * - 通过源码静态断言 AgentController::confirmLevelChange 使用 userScopeIds 校验目标用户归属。
 * - 验证实现未回退到旧的 AgentDescendant 或 parent_id 直查写法。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止前端代理商级别确认接口在数据范围实现上回退到旧查询方式的回归测试。
 *
 * 入参例子：
 * - 无请求参数，直接读取 app/Http/Controllers/Front/AgentController.php 源码断言。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若源码中缺少 userScopeIds 调用，或出现旧查询写法，测试失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontAgentLevelConfirmationScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证前端代理商级别确认使用共享的直系代理商数据范围回退实现。
     *
     * 截取 confirmLevel 与 confirmLevelChange 两个方法源码，
     * 断言使用 userScopeIds 且未使用 AgentDescendant / parent_id 旧写法。
     */
    public function test_front_agent_level_confirmation_uses_shared_direct_agent_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $confirmLevelMethod = $this->sourceBetween($source, 'public function confirmLevel(Request $request): JsonResponse', 'public function proxyConfirmSearch');
        $confirmLevelChangeMethod = $this->sourceBetween($source, 'public function confirmLevelChange(Request $request): JsonResponse', 'public function groupChangeList');

        $this->assertStringContainsString(
            '$agentIds = FrontLegacyData::userScopeIds((int) $userInfo->user_id, false, 1, true);',
            $confirmLevelMethod
        );
        $this->assertStringContainsString("UserInfo::with(['login', 'level'])", $confirmLevelMethod);
        $this->assertStringContainsString("->whereIn('user_id', array_values(array_unique(\$agentIds)))", $confirmLevelMethod);
        $this->assertStringContainsString("->where('account_type', 1)", $confirmLevelMethod);
        $this->assertStringContainsString("->where('is_agent_confirmed', 0)", $confirmLevelMethod);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', (int) \$userInfo->user_id)", $confirmLevelMethod);
        $this->assertStringNotContainsString('if (!$agentIds)', $confirmLevelMethod);

        $this->assertStringContainsString(
            '$directAgentIds = FrontLegacyData::userScopeIds($agentId, false, 1, true);',
            $confirmLevelChangeMethod
        );
        $this->assertStringContainsString('in_array($targetUserId, $directAgentIds, true)', $confirmLevelChangeMethod);
        $this->assertStringContainsString('(float) $level->user_commission', $confirmLevelChangeMethod);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', \$agentId)", $confirmLevelChangeMethod);
        $this->assertStringNotContainsString("UserInfo::where('parent_id', \$agentId)", $confirmLevelChangeMethod);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 170 项、Front\AgentController、userScopeIds 及本测试类名。
     */
    public function test_final_checklist_records_front_agent_level_confirmation_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 170.',
            '`Front\\AgentController`',
            '`confirmLevel`',
            '`confirmLevelChange`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontAgentLevelConfirmationScopeFallbackModuleTest`',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }

    /**
     * 截取两个标记字符串之间的源码片段。
     *
     * @param string $content 完整源码内容。
     * @param string $startToken 起始标记。
     * @param string $endToken 结束标记。
     * @return string 两标记之间的源码；任一标记缺失时返回空字符串。
     */
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
