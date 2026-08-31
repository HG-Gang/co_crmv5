<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前端佣金服务-数据范围回退模块测试。
 *
 * 文件功能：
 * - 通过源码静态断言 CommissionService::calculateRealTimeCommission 使用 FrontLegacyData::userScopeIds 获取客户范围并查询持仓订单。
 * - 通过源码静态断言 calculateSettlement 使用 userScopeIds 查询已平仓且未结算订单。
 * - 验证实现未回退到 DB::table('agent_descendants') 旧查询写法。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止佣金服务在数据范围实现上回退到旧查询方式的回归测试。
 *
 * 入参例子：
 * - 无请求参数，直接读取 app/Services/CommissionService.php 源码断言。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若源码中缺少 userScopeIds 调用或出现 agent_descendants 旧查询，测试失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontCommissionServiceScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证前端佣金服务使用共享的父级树数据范围回退实现。
     *
     * 截取 calculateRealTimeCommission 与 calculateSettlement 源码，
     * 断言使用 userScopeIds 且未使用 agent_descendants 旧查询。
     */
    public function test_front_commission_service_uses_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Services/CommissionService.php')) ?: '';
        $realtimeMethod = $this->sourceBetween($source, 'public function calculateRealTimeCommission(int $agentId): array', 'public function calculateSettlement');
        $settlementMethod = $this->sourceBetween($source, 'public function calculateSettlement(int $agentId, array $dateRange): array', 'public function settleCommission');

        $this->assertStringContainsString('$descendantIds = FrontLegacyData::userScopeIds($agentId, false);', $realtimeMethod);
        $this->assertStringContainsString('UserTrade::whereIn(\'user_id\', $descendantIds)', $realtimeMethod);
        $this->assertStringContainsString('->open()', $realtimeMethod);
        $this->assertStringNotContainsString("DB::table('agent_descendants')", $realtimeMethod);

        $this->assertStringContainsString('$descendantIds = FrontLegacyData::userScopeIds($agentId, false);', $settlementMethod);
        $this->assertStringContainsString('UserTrade::whereIn(\'user_id\', $descendantIds)', $settlementMethod);
        $this->assertStringContainsString('->closed()', $settlementMethod);
        $this->assertStringContainsString("->where('settlement_status', 0)", $settlementMethod);
        $this->assertStringNotContainsString("DB::table('agent_descendants')", $settlementMethod);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 172 项、CommissionService、calculateRealTimeCommission 及本测试类名。
     */
    public function test_final_checklist_records_front_commission_service_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 172.',
            '`CommissionService`',
            '`calculateRealTimeCommission`',
            '`calculateSettlement`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontCommissionServiceScopeFallbackModuleTest`',
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
