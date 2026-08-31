<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:29
 */

/**
 * 前端大数模块-数据范围回退模块测试。
 *
 * 文件功能：
 * - 通过源码静态断言 BigNumberController 使用严格批量范围解析计算代理与客户并集。
 * - 验证实现未回退到 AgentDescendant::whereIn 旧查询写法。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止前端大数模块在数据范围实现上回退到旧查询方式的回归测试。
 *
 * 入参例子：
 * - 无请求参数，直接读取 app/Http/Controllers/Front/BigNumberController.php 源码断言。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若范围扩展未使用严格批量解析、订单接口逐代理递归，或出现 AgentDescendant 旧写法，测试失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontBigNumberScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证前端大数控制器使用共享的父级树数据范围回退实现。
     *
     * 截取 subAgentIdsForRequest 与 legacyOrderListResponse 源码，
     * 断言子代理扩展和订单客户范围复用严格批量解析，且订单接口不逐代理递归。
     */
    public function test_front_big_number_controller_uses_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/BigNumberController.php')) ?: '';
        $scopeMethod = $this->sourceBetween($source, 'private function subAgentIdsForRequest(Request $request, bool $includeDescendants): array', 'private function legacyAgentListResponse');
        $orderMethod = $this->sourceBetween($source, 'private function legacyOrderListResponse(Request $request, bool $open)', "\n}");
        $scopeMethod = str_replace("\r\n", "\n", $scopeMethod);

        $this->assertStringContainsString('use App\Support\FrontLegacyData;', $source);
        $this->assertStringContainsString(
            '$scope = FrontLegacyData::strictAgentNetworkIdsOrNull($subAgentIds);',
            $scopeMethod
        );
        $this->assertStringContainsString(
            "if (\$scope === null) {\n            return [];\n        }",
            $scopeMethod
        );
        $this->assertStringContainsString("return \$scope['agent_ids'];", $scopeMethod);
        $this->assertStringContainsString(
            '$configuredAgentIds = $this->subAgentIdsForRequest($request, false);',
            $orderMethod
        );
        $this->assertStringContainsString('FrontLegacyData::strictAgentNetworkIdsOrNull(', $orderMethod);
        $this->assertStringContainsString("\$customerIds = \$scope['customer_ids'];", $orderMethod);
        $this->assertStringNotContainsString('FrontLegacyData::userScopeIds($agentId, false, 2)', $orderMethod);
        $this->assertStringNotContainsString('$customerIds = collect($agentIds)', $orderMethod);
        $this->assertStringNotContainsString("\\App\\Models\\AgentDescendant::whereIn('agent_id', \$subAgentIds)", $source);
        $this->assertStringNotContainsString("\\App\\Models\\AgentDescendant::whereIn('agent_id', \$agentIds)", $source);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 168 项、Front\BigNumberController、subAgentIdsForRequest 及本测试类名。
     */
    public function test_final_checklist_records_front_big_number_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 168.',
            '`Front\\BigNumberController`',
            '`subAgentIdsForRequest`',
            '`legacyOrderListResponse`',
            '`big_agents.sub_agent_ids`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontBigNumberScopeFallbackModuleTest`',
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
