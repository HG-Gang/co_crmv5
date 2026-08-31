<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前端实时佣金-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证现代实时佣金接口 /api/front/commissions/realtime 只返回当前代理商自身分支的订单佣金。
 * - 验证旧接口 /user/realtime/realtimeRebateSearch 拒绝其他分支的 user_id / userId 伪装过滤。
 * - 验证旧返佣明细接口 /user/realtime/rebate_detail/{orderNo}/agent 对他人分支订单返回 404 且不泄漏内容。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端实时佣金列表与明细接口的归属权边界回归测试。
 *
 * 入参例子：
 * - GET /api/front/commissions/realtime?user_id={其他分支客户ID}&userId={其他分支客户ID}&per_page=20
 * - POST /user/realtime/realtimeRebateSearch（body: { "user_id": ..., "userId": ..., "limit": 20 }）
 * - GET /user/realtime/rebate_detail/{他人订单号}/agent
 *
 * 返回值：
 * - 合法查询返回 SUCCESS 且仅含自身分支数据；伪装查询返回 SUCCESS 但列表为空；
 *   他人订单明细返回 404。
 *
 * 异常或失败场景：
 * - 若其他分支的订单或佣金数据被返回，或自身合法查询缺失，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontCommissionRealtimeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代实时佣金接口拒绝其他分支的伪装用户过滤。
     *
     * 构造自身与他人的订单佣金数据，未过滤时只返回自身数据；
     * 带他人 user_id / userId 伪装时返回空列表。
     */
    public function test_modern_realtime_commission_rejects_spoofed_other_branch_user_filter(): void
    {
        $viewerAgentId = 412440100;
        $ownCustomerId = 412440101;
        $otherAgentId = 412440102;
        $otherCustomerId = 412440103;
        $ownTicket = 82440101;
        $otherTicket = 82440102;

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId], [$ownTicket, $otherTicket]);
        $this->insertUserInfo($viewerAgentId, 'commission-realtime-owner-viewer-agent', 1, 0);
        $this->insertUserInfo($ownCustomerId, 'commission-realtime-owner-own-customer', 2, $viewerAgentId);
        $this->insertUserInfo($otherAgentId, 'commission-realtime-owner-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'commission-realtime-owner-other-customer', 2, $otherAgentId);
        $this->insertClosedTrade($ownCustomerId, $ownTicket, 'visible realtime commission owner order');
        $this->insertClosedTrade($otherCustomerId, $otherTicket, 'other realtime commission owner order');
        $this->insertCommissionRecord($viewerAgentId, $ownTicket, 'front-commission-realtime-owner-own');
        $this->insertCommissionRecord($otherAgentId, $otherTicket, 'front-commission-realtime-owner-other');

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/realtime?per_page=20');

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString((string) $ownTicket, $visibleResponse->getContent());
        $this->assertStringNotContainsString((string) $otherTicket, $visibleResponse->getContent());
        $this->assertStringNotContainsString('other realtime commission owner order', $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/realtime?user_id=' . $otherCustomerId . '&userId=' . $otherCustomerId . '&per_page=20');

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString((string) $otherTicket, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('commission-realtime-owner-other-customer', $spoofedResponse->getContent());
    }

    /**
     * 验证旧实时佣金接口拒绝其他分支的伪装用户过滤。
     *
     * 未过滤时只返回自身分支数据；带他人 user_id / userId 伪装时返回空列表。
     */
    public function test_legacy_realtime_commission_rejects_spoofed_other_branch_user_filter(): void
    {
        $viewerAgentId = 412440200;
        $ownCustomerId = 412440201;
        $otherAgentId = 412440202;
        $otherCustomerId = 412440203;
        $ownTicket = 82440201;
        $otherTicket = 82440202;

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId], [$ownTicket, $otherTicket]);
        $this->insertUserInfo($viewerAgentId, 'commission-realtime-legacy-owner-agent', 1, 0);
        $this->insertUserInfo($ownCustomerId, 'commission-realtime-legacy-owner-customer', 2, $viewerAgentId);
        $this->insertUserInfo($otherAgentId, 'commission-realtime-legacy-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'commission-realtime-legacy-other-customer', 2, $otherAgentId);
        $this->insertClosedTrade($ownCustomerId, $ownTicket, 'visible legacy realtime commission order');
        $this->insertClosedTrade($otherCustomerId, $otherTicket, 'other legacy realtime commission order');
        $this->insertCommissionRecord($viewerAgentId, $ownTicket, 'front-commission-realtime-legacy-own');
        $this->insertCommissionRecord($otherAgentId, $otherTicket, 'front-commission-realtime-legacy-other');

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/realtime/realtimeRebateSearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString((string) $ownTicket, $visibleResponse->getContent());
        $this->assertStringNotContainsString((string) $otherTicket, $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/realtime/realtimeRebateSearch', [
                'user_id' => $otherCustomerId,
                'userId' => $otherCustomerId,
                'limit' => 20,
            ]);

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString((string) $otherTicket, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('commission-realtime-legacy-other-customer', $spoofedResponse->getContent());
    }

    /**
     * 验证旧实时返佣明细接口拒绝他人分支订单且不泄漏内容。
     *
     * 自身订单返回 200 并展示返佣信息；他人订单返回 404 且响应不含订单数据。
     */
    public function test_legacy_realtime_rebate_detail_rejects_other_branch_order_without_leaking_content(): void
    {
        $viewerAgentId = 412440300;
        $ownCustomerId = 412440301;
        $otherAgentId = 412440302;
        $otherCustomerId = 412440303;
        $ownTicket = 82440301;
        $otherTicket = 82440302;

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId], [$ownTicket, $otherTicket]);
        $this->insertUserInfo($viewerAgentId, 'commission-detail-owner-viewer-agent', 1, 0);
        $this->insertUserInfo($ownCustomerId, 'commission-detail-owner-own-customer', 2, $viewerAgentId);
        $this->insertUserInfo($otherAgentId, 'commission-detail-owner-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'commission-detail-owner-other-customer', 2, $otherAgentId);
        $this->insertClosedTrade($ownCustomerId, $ownTicket, 'visible realtime detail owner order');
        $this->insertClosedTrade($otherCustomerId, $otherTicket, 'other realtime detail owner order');
        $this->insertCommissionRecord($viewerAgentId, $ownTicket, 'front-commission-realtime-detail-own');
        $this->insertCommissionRecord($otherAgentId, $otherTicket, 'front-commission-realtime-detail-other');

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $ownResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/user/realtime/rebate_detail/' . $ownTicket . '/agent');

        $ownResponse->assertOk()
            ->assertSee((string) $ownTicket, false)
            ->assertSee('当前账户返佣', false);

        $otherResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/user/realtime/rebate_detail/' . $otherTicket . '/agent');

        $otherResponse->assertNotFound();
        $this->assertStringNotContainsString((string) $otherTicket, $otherResponse->getContent());
        $this->assertStringNotContainsString('other realtime detail owner order', $otherResponse->getContent());
        $this->assertStringNotContainsString('commission-detail-owner-other-customer', $otherResponse->getContent());
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 244 项、CommissionController 相关方法及本测试类名。
     */
    public function test_final_checklist_records_realtime_commission_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 244.', $checklist);
        $this->assertStringContainsString('CommissionController::realTime', $checklist);
        $this->assertStringContainsString('CommissionController::realtimeRebateSearch', $checklist);
        $this->assertStringContainsString('CommissionController::realtimeRebateDetail', $checklist);
        $this->assertStringContainsString('/api/front/commissions/realtime', $checklist);
        $this->assertStringContainsString('user/realtime/realtimeRebateSearch', $checklist);
        $this->assertStringContainsString('user/realtime/rebate_detail/{orderNo}/{role}', $checklist);
        $this->assertStringContainsString('FrontCommissionRealtimeOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据，代理商默认级别 1、佣金比例 0.1。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-commission-realtime-owner-' . $userId . '@example.test',
            'password' => Hash::make('password'),
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
            'phone' => '1782440' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 0.1 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 插入一条已平仓并结算的交易订单记录。
     *
     * @param int $userId 所属用户 ID。
     * @param int $ticket 订单票号。
     * @param string $comment 订单备注。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertClosedTrade(int $userId, int $ticket, string $comment): void
    {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-07-09 09:00:00',
            'open_price' => 2300.12,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => '2026-07-09 10:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 12.34,
            'swaps' => 0,
            'close_price' => 2305.34,
            'profit' => 56.78,
            'taxes' => 0,
            'comment' => $comment,
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => '2026-07-09 10:00:00',
            'settlement_status' => 1,
            'settled_at' => '2026-07-09 10:05:00',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 插入一条关联订单号的佣金记录。
     *
     * @param int $agentId 归属代理商 ID。
     * @param int $ticket 关联订单号（mt4_order_id）。
     * @param string $uniqueId 佣金记录唯一标识。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertCommissionRecord(int $agentId, int $ticket, string $uniqueId): void
    {
        $now = time();

        DB::table('commission_records')->insert([
            'unique_id' => $uniqueId,
            'agent_id' => $agentId,
            'parent_id' => 0,
            'agent_profit' => 12.34,
            'agent_volume' => 1,
            'equity_value' => 0,
            'equity_diff' => 0,
            'settle_cycle' => 0,
            'mt4_order_id' => $ticket,
            'date_range' => '2026-07-09',
            'settle_status' => 2,
            'fee' => 0,
            'swap' => 0,
            'commission_amount' => 12.34,
            'returned_amount' => 0,
            'deposit' => 0,
            'real_amount' => 12.34,
            'data_type' => 'mainData',
            'manual_reason' => '',
            'remarks' => 'realtime commission owner boundary fixture',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的佣金记录、交易订单、层级关系及用户信息测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param array<int, int> $tickets 待清理的订单票号列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, array $tickets): void
    {
        DB::table('commission_records')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('parent_id', $userIds)
            ->orWhereIn('mt4_order_id', $tickets)
            ->delete();

        DB::table('user_trades')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('ticket', $tickets)
            ->delete();

        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
