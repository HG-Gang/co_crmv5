<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台家族树代理统计数据作用域兜底闭环测试。
 *
 * 文件功能：
 * - 验证 FamilyTreeService::getAgentStats 在 agent_descendants 行缺失时，
 *   回退使用 user_infos.parent_id 构建代理树统计口径。
 * - 验证权限清单文档记录了该兜底闭环。
 *
 * 适用场景：
 * - 前台家族树代理统计数据（交易量、盈亏、佣金、活跃用户、新增注册）的回归测试。
 *
 * 入参例子：
 * - rootAgentId: 411000100（根代理 ID）
 * - subAgentId: 411000101（子代理 ID）
 * - visibleCustomerId: 411000102（树内可见客户）
 * - hiddenCustomerId: 411000103（树外客户，不应计入）
 * - user_trades / commission_records 中的交易与佣金数据
 *
 * 返回值：
 * - stats 数组包含 total_volume、total_profit、total_commission、active_users、new_registrations。
 *
 * 异常或失败场景：
 * - agent_descendants 缺失时统计仍按 parent_id 关系正确聚合，树外数据不计入。
 */

namespace Tests\Feature;

use App\Services\FamilyTreeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontFamilyTreeAgentStatsScopeFallbackModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证 agent_descendants 缺失时按 parent_id 树统计代理数据且不含树外数据。
    public function test_family_tree_agent_stats_use_parent_id_tree_when_descendant_rows_are_missing(): void
    {
        $rootAgentId = 411000100;
        $subAgentId = $rootAgentId + 1;
        $visibleCustomerId = $rootAgentId + 2;
        $hiddenCustomerId = $rootAgentId + 3;
        $visibleTicket = $rootAgentId + 100;
        $hiddenTicket = $rootAgentId + 101;

        $this->deleteAgentDescendantRows([$rootAgentId, $subAgentId, $visibleCustomerId, $hiddenCustomerId]);
        $this->insertUserInfo($rootAgentId, 'stats-root-agent', 1, 0);
        $this->insertUserInfo($subAgentId, 'stats-sub-agent', 1, $rootAgentId);
        $this->insertUserInfo($visibleCustomerId, 'stats-visible-customer', 2, $subAgentId);
        $this->insertUserInfo($hiddenCustomerId, 'stats-hidden-customer', 2, $rootAgentId + 1000);
        $this->insertUserTrade($visibleCustomerId, $visibleTicket, 300, 45.25);
        $this->insertUserTrade($hiddenCustomerId, $hiddenTicket, 700, 91.50);
        $this->insertCommissionRecord($rootAgentId, 'stats-visible-commission', 12.75);
        $this->insertCommissionRecord($rootAgentId + 1000, 'stats-hidden-commission', 88.00);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());

        $stats = (new FamilyTreeService())->getAgentStats($rootAgentId);

        $this->assertSame(300.0, (float) $stats['total_volume']);
        $this->assertSame(45.25, (float) $stats['total_profit']);
        $this->assertSame(12.75, (float) $stats['total_commission']);
        $this->assertSame(1, (int) $stats['active_users']);
        $this->assertSame(2, (int) $stats['new_registrations']);
    }

    // 校验权限清单文档记录了家族树代理统计作用域兜底闭环。
    public function test_final_checklist_records_family_tree_agent_stats_scope_fallback(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString(
            '## 174. 2026-07-09',
            $checklist
        );
        $this->assertStringContainsString('FamilyTreeService::getAgentStats', $checklist);
        $this->assertStringContainsString('FrontFamilyTreeAgentStatsScopeFallbackModuleTest', $checklist);
        $this->assertStringContainsString('`user_infos.parent_id`', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'family-tree-stats-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1790000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserTrade(int $userId, int $ticket, float $volume, float $profit): void
    {
        $now = time();

        DB::table('user_trades')->where('ticket', $ticket)->delete();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => $volume,
            'open_time' => date('Y-m-d H:i:s', $now),
            'open_price' => 1900,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => date('Y-m-d H:i:s', $now),
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 0,
            'conv_rate2' => 0,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 1915,
            'profit' => $profit,
            'taxes' => 0,
            'comment' => 'family-tree-stats-test',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => date('Y-m-d H:i:s', $now),
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertCommissionRecord(int $agentId, string $uniqueId, float $commissionAmount): void
    {
        $now = time();

        DB::table('commission_records')->where('unique_id', $uniqueId)->delete();
        DB::table('commission_records')->insert([
            'unique_id' => $uniqueId,
            'agent_id' => $agentId,
            'parent_id' => 0,
            'commission_amount' => $commissionAmount,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function deleteAgentDescendantRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('descendant_id', $userIds)
            ->delete();
    }
}
