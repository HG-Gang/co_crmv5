<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 11:32
 */

/**
 * 前台本人订单作用域属主边界闭环测试。
 *
 * 文件功能：
 * - 验证现代持仓订单（/api/front/orders/open）与遗留持仓/平仓搜索
 *   （/user/open/openOrderSearch、/user/close/closeOrderSearch）忽略伪造的
 *   user_id / userId 过滤参数，只返回当前用户自己的订单。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台订单列表的越权过滤回归测试，防止通过查询参数读取他人订单。
 *
 * 入参例子：
 * - 登录账号：viewerId（account_type=2）。
 * - 构造本人订单（viewerTicket）与他人订单（otherTicket）。
 * - 伪造参数：user_id={otherId}&userId={otherId}。
 *
 * 返回值：
 * - code 为 SUCCESS；伪造过滤时 data.list.data 为空数组，不含他人 ticket 与用户名。
 *
 * 异常或失败场景：
 * - 伪造 user_id / userId 时返回空列表，不泄露其他用户的订单数据。
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

class FrontOrderOwnScopeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证现代持仓订单忽略伪造的 user_id / userId，不泄露他人订单。
    public function test_modern_open_orders_reject_spoofed_user_filter_without_leaking_other_user_orders(): void
    {
        [$viewerId, $otherId] = $this->unusedUserIds(2);
        [$viewerTicket, $otherTicket] = $this->unusedTickets(2);
        $viewerEmail = 'front-order-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-order-boundary-' . $otherId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-open-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'order-open-other', $otherEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'XAUUSD', true, 'viewer open boundary order');
        $this->insertTrade($otherId, $otherTicket, 'XAGUSD', true, 'other open boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/orders/open?user_id=' . $otherId . '&userId=' . $otherId . '&per_page=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame([], $response->json('data.list.data'));
        $this->assertStringNotContainsString((string) $otherTicket, $response->getContent());
        $this->assertStringNotContainsString('order-open-other', $response->getContent());
        $this->assertStringNotContainsString('other open boundary order', $response->getContent());
    }

    // 验证遗留持仓搜索忽略伪造的 user_id / userId，不泄露他人订单。
    public function test_legacy_open_order_search_rejects_spoofed_user_filter_without_leaking_other_user_orders(): void
    {
        [$viewerId, $otherId] = $this->unusedUserIds(2);
        [$viewerTicket, $otherTicket] = $this->unusedTickets(2);
        $viewerEmail = 'front-order-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-order-boundary-' . $otherId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-open-legacy-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'order-open-legacy-other', $otherEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'EURUSD', true, 'viewer legacy open boundary order');
        $this->insertTrade($otherId, $otherTicket, 'GBPUSD', true, 'other legacy open boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/open/openOrderSearch', [
                'user_id' => $otherId,
                'userId' => $otherId,
                'limit' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame([], $response->json('data.list.data'));
        $this->assertStringNotContainsString((string) $otherTicket, $response->getContent());
        $this->assertStringNotContainsString('order-open-legacy-other', $response->getContent());
        $this->assertStringNotContainsString('other legacy open boundary order', $response->getContent());
    }

    // 验证遗留平仓搜索忽略伪造的 user_id / userId，不泄露他人订单。
    public function test_legacy_closed_order_search_rejects_spoofed_user_filter_without_leaking_other_user_orders(): void
    {
        [$viewerId, $otherId] = $this->unusedUserIds(2);
        [$viewerTicket, $otherTicket] = $this->unusedTickets(2);
        $viewerEmail = 'front-order-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-order-boundary-' . $otherId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-closed-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'order-closed-other', $otherEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'USDJPY', false, 'viewer closed boundary order');
        $this->insertTrade($otherId, $otherTicket, 'AUDUSD', false, 'other closed boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/close/closeOrderSearch', [
                'user_id' => $otherId,
                'userId' => $otherId,
                'limit' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame([], $response->json('data.list.data'));
        $this->assertStringNotContainsString((string) $otherTicket, $response->getContent());
        $this->assertStringNotContainsString('order-closed-other', $response->getContent());
        $this->assertStringNotContainsString('other closed boundary order', $response->getContent());
    }

    // 验证遗留第二套平仓搜索同样忽略伪造 userId，不泄露他人订单。
    public function test_legacy_closed_order2_search_rejects_spoofed_user_id_without_leaking_other_user_orders(): void
    {
        [$viewerId, $otherId] = $this->unusedUserIds(2);
        [$viewerTicket, $otherTicket] = $this->unusedTickets(2);
        $viewerEmail = 'front-order-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-order-boundary-' . $otherId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-closed-v2-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'order-closed-v2-other', $otherEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'NZDUSD', false, 'viewer closed v2 boundary order');
        $this->insertTrade($otherId, $otherTicket, 'USDCAD', false, 'other closed v2 boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/close/closeOrder2Search', [
                'userId' => $otherId,
                'limit' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame([], $response->json('data.list.data'));
        $this->assertStringNotContainsString((string) $otherTicket, $response->getContent());
        $this->assertStringNotContainsString('order-closed-v2-other', $response->getContent());
        $this->assertStringNotContainsString('other closed v2 boundary order', $response->getContent());
    }

    // 校验权限清单文档记录了本人订单属主边界闭环。
    public function test_final_checklist_records_order_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 239.', $checklist);
        $this->assertStringContainsString('OrderController::openOrders', $checklist);
        $this->assertStringContainsString('OrderController::openOrderSearch', $checklist);
        $this->assertStringContainsString('OrderController::closedOrders', $checklist);
        $this->assertStringContainsString('OrderController::closeOrderSearch', $checklist);
        $this->assertStringContainsString('/api/front/orders/open', $checklist);
        $this->assertStringContainsString('user/open/openOrderSearch', $checklist);
        $this->assertStringContainsString('user/close/closeOrderSearch', $checklist);
        $this->assertStringContainsString('FrontOrderOwnScopeOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertTrade(int $userId, int $ticket, string $symbol, bool $open, string $comment): void
    {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => $symbol,
            'digits' => 2,
            'cmd' => 0,
            'volume' => 10,
            'open_time' => '2026-07-09 10:00:00',
            'open_price' => 2300.12,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => $open ? '1970-01-01 00:00:00' : '2026-07-09 11:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => -3.5,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => $open ? 0 : 2310.12,
            'profit' => $open ? 0 : 100,
            'taxes' => 0,
            'comment' => $comment,
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => $open ? '2026-07-09 10:00:00' : '2026-07-09 11:00:00',
            'settlement_status' => $open ? 0 : 1,
            'settled_at' => $open ? null : '2026-07-09 11:05:00',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserInfo(int $userId, string $userName, string $email): void
    {
        $now = time();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => 2,
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
            'phone' => '1392390' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
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
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /** @return array<int, int> */
    private function unusedUserIds(int $count): array
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $start = random_int(1000000000, 1900000000 - $count);
            $userIds = range($start, $start + $count - 1);
            $occupied = DB::table('user_logins')->useWritePdo()->whereIn('user_id', $userIds)->exists()
                || DB::table('user_infos')->useWritePdo()->whereIn('user_id', $userIds)->exists()
                || DB::table('user_trades')->useWritePdo()->whereIn('user_id', $userIds)->exists();

            if (!$occupied) {
                return $userIds;
            }
        }

        throw new \RuntimeException('Unable to allocate unused order-scope fixture user IDs.');
    }

    /** @return array<int, int> */
    private function unusedTickets(int $count): array
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $start = random_int(1000000000, 1900000000 - $count);
            $tickets = range($start, $start + $count - 1);

            if (!DB::table('user_trades')->useWritePdo()->whereIn('ticket', $tickets)->exists()) {
                return $tickets;
            }
        }

        throw new \RuntimeException('Unable to allocate unused order-scope fixture tickets.');
    }
}
