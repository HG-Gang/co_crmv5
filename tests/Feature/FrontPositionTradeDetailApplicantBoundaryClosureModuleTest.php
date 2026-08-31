<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台持仓交易明细申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能读取子客户的持仓交易明细，包括现代接口
 *   /api/front/positions/trades 与遗留接口 /user/position/v2/positionSummaryClickSearch。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台持仓模块“交易明细下钻”功能的回归测试，防止普通客户越权查看他人交易明细。
 *
 * 入参例子：
 * - 登录账号：普通客户（account_type=2），其下挂一个子客户（account_type=2）。
 * - 现代接口参数：user_id={childId}&ticket={ticket}&status=1；
 *   遗留接口参数：user_id、ticket、status。
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 PERMISSION_DENIED，响应体不含 ticket 与子客户用户名。
 *
 * 异常或失败场景：
 * - 普通客户读取子客户持仓交易明细时被拒绝（PERMISSION_DENIED）。
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

class FrontPositionTradeDetailApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代接口读取子客户的持仓交易明细。
    public function test_customer_account_cannot_read_modern_child_position_trade_detail(): void
    {
        $customerId = 412060100;
        $childId = 412060101;
        $ticket = 62020601;

        $this->deleteFixtureRows([$customerId, $childId], [$ticket]);
        $this->insertUserInfo($customerId, 'position-trade-detail-boundary-customer', 2, 0);
        $this->insertUserInfo($childId, 'position-trade-detail-boundary-child', 2, $customerId);
        $this->insertClosedTrade($childId, $ticket);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/positions/trades?user_id=' . $childId . '&ticket=' . $ticket . '&status=1');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString((string) $ticket, $response->getContent());
        $this->assertStringNotContainsString('position-trade-detail-boundary-child', $response->getContent());
    }

    // 验证普通客户不能通过遗留接口读取子客户的持仓交易明细。
    public function test_customer_account_cannot_read_legacy_child_position_trade_detail(): void
    {
        $customerId = 412060200;
        $childId = 412060201;
        $ticket = 62020602;

        $this->deleteFixtureRows([$customerId, $childId], [$ticket]);
        $this->insertUserInfo($customerId, 'position-trade-detail-legacy-boundary-customer', 2, 0);
        $this->insertUserInfo($childId, 'position-trade-detail-legacy-boundary-child', 2, $customerId);
        $this->insertClosedTrade($childId, $ticket);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/position/v2/positionSummaryClickSearch', [
                'user_id' => $childId,
                'ticket' => $ticket,
                'status' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString((string) $ticket, $response->getContent());
        $this->assertStringNotContainsString('position-trade-detail-legacy-boundary-child', $response->getContent());
    }

    // 校验权限清单文档记录了持仓交易明细申请人边界闭环。
    public function test_final_checklist_records_position_trade_detail_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 206.', $checklist);
        $this->assertStringContainsString('positionDetail', $checklist);
        $this->assertStringContainsString('clickSearch', $checklist);
        $this->assertStringContainsString('/api/front/positions/trades', $checklist);
        $this->assertStringContainsString('user/position/v2/positionSummaryClickSearch', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontPositionTradeDetailApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-position-trade-detail-boundary-' . $userId . '@example.test',
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
            'phone' => '1782060' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
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

    private function insertClosedTrade(int $userId, int $ticket): void
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
            'conv_rate1' => 0,
            'conv_rate2' => 0,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 2305.34,
            'profit' => 12.34,
            'taxes' => 0,
            'comment' => 'ordinary customer child trade detail boundary fixture',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => '2026-07-09 10:00:00',
            'settlement_status' => 0,
            'settled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, int> $tickets
     */
    private function deleteFixtureRows(array $userIds, array $tickets): void
    {
        DB::table('commission_records')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('mt4_order_id', $tickets)
            ->delete();

        DB::table('user_trades')->whereIn('ticket', $tickets)->orWhereIn('user_id', $userIds)->delete();

        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
