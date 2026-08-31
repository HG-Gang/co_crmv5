<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前端佣金-数据范围回退模块测试。
 *
 * 文件功能：
 * - 通过源码静态断言 CommissionController::transferAgentOptions 使用 FrontLegacyData::userScopeIds 获取直系代理商选项。
 * - 通过源码静态断言 transfer 使用 userScopeIds 校验目标子代理商归属。
 * - 验证实现未回退到 AgentDescendant 旧查询写法。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止前端佣金转账相关接口在数据范围实现上回退到旧查询方式的回归测试。
 *
 * 入参例子：
 * - 无请求参数，直接读取 app/Http/Controllers/Front/CommissionController.php 源码断言。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若源码中缺少 userScopeIds 调用或出现 AgentDescendant 旧写法，测试失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontCommissionScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证前端佣金转账使用共享的父级树数据范围回退实现。
     *
     * 截取 transferAgentOptions 与 transfer 方法源码，
     * 断言使用 userScopeIds 且未导入/使用 AgentDescendant。
     */
    public function test_front_commission_transfer_uses_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CommissionController.php')) ?: '';
        $optionsMethod = $this->sourceBetween($source, 'public function transferAgentOptions(Request $request): JsonResponse', 'public function transfer(Request $request): JsonResponse');
        $transferMethod = $this->sourceBetween($source, 'public function transfer(Request $request): JsonResponse', "\n}");

        $this->assertStringContainsString('use App\Models\UserInfo;', $source);
        $this->assertStringContainsString('use App\Support\FrontLegacyData;', $source);
        $this->assertStringNotContainsString('use App\Models\AgentDescendant;', $source);

        $this->assertStringContainsString('$directAgentIds = FrontLegacyData::userScopeIds($agentId, false, 1, true);', $optionsMethod);
        $this->assertStringContainsString("UserInfo::with('level')", $optionsMethod);
        $this->assertStringContainsString("->whereIn('user_id', \$directAgentIds)", $optionsMethod);
        $this->assertStringContainsString("->where('account_type', 1)", $optionsMethod);
        $this->assertStringContainsString("->orderBy('user_id')", $optionsMethod);

        foreach (["'value'", "'label'", "'user_id'", "'user_name'", "'agent_level_name'"] as $field) {
            $this->assertStringContainsString($field, $optionsMethod);
        }

        $this->assertStringContainsString('$directAgentIds = FrontLegacyData::userScopeIds($agentId, false, 1, true);', $transferMethod);
        $this->assertStringContainsString('$isSubAgent = in_array((int) $subAgentId, $directAgentIds, true);', $transferMethod);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', \$agentId)", $source);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 167 项、Front\CommissionController、transferAgentOptions 及本测试类名。
     */
    public function test_final_checklist_records_front_commission_transfer_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 167.',
            '`Front\\CommissionController`',
            '`transferAgentOptions`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontCommissionScopeFallbackModuleTest`',
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
