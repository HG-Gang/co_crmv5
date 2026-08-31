<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 前端代理商主列表-数据范围回退模块测试。
 *
 * 文件功能：
 * - 通过源码静态断言 AgentController::subList / customerList 使用 FrontLegacyData::userScopeIds 获取数据范围。
 * - 验证 direct_only 参数传递与 account_type 过滤、关联加载及输出字段的写法。
 * - 验证实现未回退到 AgentDescendant::query() 旧查询写法。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止前端代理商主列表接口在数据范围实现上回退到旧查询方式的回归测试。
 *
 * 入参例子：
 * - 无请求参数，直接读取 app/Http/Controllers/Front/AgentController.php 源码断言。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若源码中缺少 userScopeIds 调用、字段缺失或出现 AgentDescendant::query() 旧写法，测试失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontAgentMainListScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证前端代理商主列表使用共享的父级树数据范围回退实现。
     *
     * 截取 subList 与 customerList 方法源码，断言使用 userScopeIds、
     * 关联加载与 account_type 过滤，且未使用 AgentDescendant::query()。
     */
    public function test_front_agent_main_lists_use_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/AgentController.php')) ?: '';
        $subListMethod = $this->sourceBetween($source, 'public function subList(Request $request): JsonResponse', 'public function proxyListSearch');
        $customerListMethod = $this->sourceBetween($source, 'public function customerList(Request $request): JsonResponse', 'public function directCustListSearch');

        $this->assertStringContainsString('$directOnly = $request->has(\'direct_only\') && $request->direct_only == 1;', $subListMethod);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($queryAgentId, false, 1, $directOnly ? true : null)', $subListMethod);
        $this->assertStringContainsString("UserInfo::with(['login', 'level'])", $subListMethod);
        $this->assertStringContainsString("->whereIn('user_id', \$descendantIds)", $subListMethod);
        $this->assertStringContainsString("->where('account_type', 1)", $subListMethod);
        foreach (["'depth'", "'is_direct'", "'descendant'", "'can_drill_agents'", "'can_drill_customers'", "'is_directly_sub'"] as $field) {
            $this->assertStringContainsString($field, $subListMethod);
        }
        $this->assertStringNotContainsString('AgentDescendant::query()', $subListMethod);

        $this->assertStringContainsString('$directOnly = $request->has(\'direct_only\') && $request->direct_only == 1;', $customerListMethod);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($queryAgentId, false, 2, $directOnly ? true : null)', $customerListMethod);
        $this->assertStringContainsString("UserInfo::with(['login', 'level'])", $customerListMethod);
        $this->assertStringContainsString("->whereIn('user_id', \$descendantIds)", $customerListMethod);
        $this->assertStringContainsString("->where('account_type', 2)", $customerListMethod);
        foreach (["'depth'", "'is_direct'", "'descendant'", "'comm_trans'", "'change_group'"] as $field) {
            $this->assertStringContainsString($field, $customerListMethod);
        }
        $this->assertStringNotContainsString('AgentDescendant::query()', $customerListMethod);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 169 项、Front\AgentController、subList、customerList 及本测试类名。
     */
    public function test_final_checklist_records_front_agent_main_list_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 169.',
            '`Front\\AgentController`',
            '`subList`',
            '`customerList`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontAgentMainListScopeFallbackModuleTest`',
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
