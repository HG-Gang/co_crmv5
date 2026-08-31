<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:07
 */

/**
 * 前端佣金服务订单计算-封闭模块测试。
 *
 * 文件功能：
 * - 验证仅依赖 user_infos.parent_id 链条（无 agent_descendants 数据）时，CommissionService 实时佣金按下一级链条费率计算。
 * - 验证结算流程按下一级链条费率生成佣金记录并落库。
 * - 验证最终权限检查清单文档记录了该封闭模块。
 *
 * 适用场景：
 * - CommissionService 实时佣金与结算计算在层级表缺失时的回归测试。
 *
 * 入参例子：
 * - app(CommissionService::class)->calculateRealTimeCommission(411840100)
 * - app(CommissionService::class)->calculateSettlement(411840200, ['2026-07-01', '2026-07-03'])
 *
 * 返回值：
 * - 实时佣金返回数组：{ "total": 1.00, "breakdown": [{ "ticket": ..., "user_id": ..., "commission": 1.00 }] }
 * - 结算返回数组：{ "status": "success", "record": { agent_id, agent_volume, commission_amount, real_amount, settle_status } }
 *
 * 异常或失败场景：
 * - 若佣金金额、成交量或落库记录数量与预期不符，测试失败。
 */

namespace Tests\Feature;

use App\Services\CommissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontCommissionServiceOrderCalculationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证实时佣金对仅含 parent_id 链的订单使用下一级链条费率。
     *
     * 构造三级代理商链与一笔持仓订单，调用 calculateRealTimeCommission，
     * 断言总额、明细与佣金金额符合预期。
     */
    public function test_realtime_commission_uses_next_chain_rate_for_parent_id_only_orders(): void
    {
        $rootAgentId = 411840100;
        $subAgentId = $rootAgentId + 1;
        $customerId = $rootAgentId + 2;
        $ticket = 84100100;

        $groupId = $this->insertSpreadGroup('commission-service-realtime');
        $this->deleteCommissionFixtureRows([$rootAgentId, $subAgentId, $customerId], [$ticket]);
        $this->insertUserInfo($rootAgentId, 'service-root-agent', 1, 0, $groupId, 30.0);
        $this->insertUserInfo($subAgentId, 'service-sub-agent', 1, $rootAgentId, $groupId, 20.0);
        $this->insertUserInfo($customerId, 'service-customer', 2, $subAgentId, $groupId, 0.0);
        $this->insertTrade($customerId, $ticket, 100, '1970-01-01 00:00:00', 0);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());

        $result = app(CommissionService::class)->calculateRealTimeCommission($rootAgentId);

        $this->assertSame(1.00, $result['total']);
        $this->assertCount(1, $result['breakdown']);
        $this->assertSame($ticket, (int) $result['breakdown'][0]['ticket']);
        $this->assertSame($customerId, (int) $result['breakdown'][0]['user_id']);
        $this->assertSame(1.00, $result['breakdown'][0]['commission']);
    }

    /**
     * 验证结算流程按下一级链条费率写入佣金记录。
     *
     * 构造已平仓订单并调用 calculateSettlement，
     * 断言返回记录字段正确且 commission_records 落库一条对应记录。
     */
    public function test_settlement_writes_record_with_next_chain_rate_for_parent_id_only_orders(): void
    {
        $rootAgentId = 411840200;
        $subAgentId = $rootAgentId + 1;
        $customerId = $rootAgentId + 2;
        $ticket = 84100200;

        $groupId = $this->insertSpreadGroup('commission-service-settlement');
        $this->deleteCommissionFixtureRows([$rootAgentId, $subAgentId, $customerId], [$ticket]);
        $this->insertUserInfo($rootAgentId, 'service-settle-root', 1, 0, $groupId, 30.0);
        $this->insertUserInfo($subAgentId, 'service-settle-sub', 1, $rootAgentId, $groupId, 20.0);
        $this->insertUserInfo($customerId, 'service-settle-customer', 2, $subAgentId, $groupId, 0.0);
        $this->insertTrade($customerId, $ticket, 100, '2026-07-02 10:00:00', 0);

        $result = app(CommissionService::class)->calculateSettlement($rootAgentId, ['2026-07-01', '2026-07-03']);

        $this->assertSame('success', $result['status']);
        $record = $result['record'];
        $this->assertSame($rootAgentId, (int) $record->agent_id);
        $this->assertSame(1.00, (float) $record->agent_volume);
        $this->assertSame(1.00, (float) $record->commission_amount);
        $this->assertSame(1.00, (float) $record->real_amount);
        $this->assertSame(1, (int) $record->settle_status);

        $this->assertSame(
            1,
            DB::table('commission_records')
                ->where('agent_id', $rootAgentId)
                ->where('data_type', 'mainData')
                ->where('commission_amount', 1.00)
                ->count()
        );
    }

    public function test_order_commission_ignores_stale_family_tree_and_uses_current_parent_chain(): void
    {
        $rootAgentId = 411840300;
        $childAgentId = $rootAgentId + 1;
        $unrelatedAgentId = $rootAgentId + 2;
        $customerId = $rootAgentId + 3;
        $ticket = 84100300;

        $groupId = $this->insertSpreadGroup('commission-service-stale-tree');
        $this->deleteCommissionFixtureRows(
            [$rootAgentId, $childAgentId, $unrelatedAgentId, $customerId],
            [$ticket]
        );
        $this->insertUserInfo($rootAgentId, 'service-stale-root', 1, 0, $groupId, 30.0);
        $this->insertUserInfo($childAgentId, 'service-stale-child', 1, $rootAgentId, $groupId, 20.0);
        $this->insertUserInfo($unrelatedAgentId, 'service-stale-unrelated', 1, $rootAgentId, $groupId, 5.0);
        $this->insertUserInfo($customerId, 'service-stale-customer', 2, $childAgentId, $groupId, 0.0);
        DB::table('user_infos')->where('user_id', $customerId)->update([
            'family_tree' => ',0,' . $rootAgentId . ',' . $unrelatedAgentId . ','
                . $childAgentId . ',' . $customerId . ',',
        ]);
        $this->insertTrade($customerId, $ticket, 100, '2026-07-02 10:00:00', 0);

        $trade = \App\Models\UserTrade::where('ticket', $ticket)->firstOrFail();
        $details = app(CommissionService::class)->orderCommissionDetails($trade, $rootAgentId);
        $rootDetail = collect($details)->firstWhere('agent_id', $rootAgentId);

        $this->assertNotNull($rootDetail);
        $this->assertSame(1.00, (float) $rootDetail['commission_amount']);
        $this->assertFalse(collect($details)->contains('agent_id', $unrelatedAgentId));
    }

    /**
     * 验证最终权限检查清单记录了本次封闭模块。
     *
     * 断言清单包含第 184 项、CommissionService 相关方法及本测试类名。
     */
    public function test_final_checklist_records_commission_service_order_calculation_closure(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 184.', $checklist);
        $this->assertStringContainsString('CommissionService::calculateRealTimeCommission', $checklist);
        $this->assertStringContainsString('CommissionService::calculateSettlement', $checklist);
        $this->assertStringContainsString('parent_id-only', $checklist);
        $this->assertStringContainsString('FrontCommissionServiceOrderCalculationClosureModuleTest', $checklist);
    }

    /**
     * 插入一条启佣金的点差组配置并返回组 ID。
     *
     * @param string $name 组名称。
     * @return int 新插入点差组的自增 ID。
     */
    private function insertSpreadGroup(string $name): int
    {
        $now = time();
        $groupId = (int) DB::table('group_configs')->insertGetId([
            'pair_id' => null,
            'name' => $name,
            'radix' => 10,
            'category' => 1,
            'has_commission' => 1,
            'is_enabled' => 1,
            'is_ecn' => 0,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('spread_configs')->insert([
            'spread' => 10,
            'agent_group_id' => $groupId,
            'spread_ratio' => 1,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $groupId;
    }

    /**
     * 插入带组别与佣金比例的测试用户数据。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @param int $groupId 点差组 ID。
     * @param float $commissionRate 佣金比例。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, int $groupId, float $commissionRate): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-commission-service-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1788400' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => $groupId,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $commissionRate,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 插入一条指定成交量、平仓时间与结算状态的交易订单记录。
     *
     * @param int $userId 所属用户 ID。
     * @param int $ticket 订单票号。
     * @param int $volume 成交量。
     * @param string $closeTime 平仓时间（1970-01-01 表示未平仓）。
     * @param int $settlementStatus 结算状态。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertTrade(int $userId, int $ticket, int $volume, string $closeTime, int $settlementStatus): void
    {
        $now = time();
        $isOpen = $closeTime === '1970-01-01 00:00:00';

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => $volume,
            'open_time' => '2026-07-01 09:00:00',
            'open_price' => 2300.00,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => $closeTime,
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => $isOpen ? 0 : 2310.00,
            'profit' => $isOpen ? 0 : 100.00,
            'taxes' => 0,
            'comment' => 'commission service order calculation closure',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => $isOpen ? '2026-07-01 09:00:00' : $closeTime,
            'settlement_status' => $settlementStatus,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的层级关系、佣金记录及交易订单测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param array<int, int> $tickets 待清理的订单票号列表。
     * @return void 无返回值。
     */
    private function deleteCommissionFixtureRows(array $userIds, array $tickets): void
    {
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();

        DB::table('commission_records')
            ->where(function ($query) use ($userIds, $tickets) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('parent_id', $userIds)
                    ->orWhereIn('mt4_order_id', $tickets);
            })
            ->delete();

        DB::table('user_trades')->whereIn('ticket', $tickets)->delete();
    }
}
