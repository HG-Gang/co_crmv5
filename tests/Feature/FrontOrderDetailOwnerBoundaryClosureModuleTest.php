<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 11:32
 */

/**
 * 前台订单详情属主边界闭环测试。
 *
 * 文件功能：
 * - 验证遗留持仓/平仓订单详情路由（/open/order_detail/{orderId}/{orderType}/{role}、
 *   /close/order_detail/{orderId}/{orderType}/{role}）只渲染当前用户自己的订单。
 * - 验证查看他人订单时返回 404 且不泄露订单号、用户名与备注内容。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台订单详情页的越权回归测试，防止通过订单 ID 遍历查看他人交易明细。
 *
 * 入参例子：
 * - GET /open/order_detail/{viewerTicket}/open/customer（本人订单）。
 * - GET /open/order_detail/{otherTicket}/open/customer、/close/order_detail/{otherTicket}/closed/customer。
 *
 * 返回值：
 * - 本人订单返回 200 并渲染 ticket、用户名与备注。
 * - 他人订单返回 404，响应体不含任何他人订单内容。
 *
 * 异常或失败场景：
 * - 访问他人持仓/平仓订单详情被拒绝（404）且内容零泄露。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontOrderDetailOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证遗留持仓订单详情只渲染当前用户自己的订单。
    public function test_legacy_open_order_detail_renders_current_user_order_only(): void
    {
        [$viewerId] = $this->unusedUserIds(1);
        [$viewerTicket] = $this->unusedTickets(1);
        $viewerEmail = 'front-order-detail-boundary-' . $viewerId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-detail-open-viewer', $viewerEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'XAUUSD', true, 'viewer open detail boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/open/order_detail/' . $viewerTicket . '/open/customer');

        $response->assertOk()
            ->assertSee((string) $viewerTicket, false)
            ->assertSee('order-detail-open-viewer', false)
            ->assertSee('viewer open detail boundary order', false);
    }

    // 验证遗留平仓订单详情能够渲染当前用户自己的真实订单。
    public function test_legacy_closed_order_detail_renders_current_user_order_only(): void
    {
        [$viewerId] = $this->unusedUserIds(1);
        [$viewerTicket] = $this->unusedTickets(1);
        $viewerEmail = 'front-order-detail-boundary-' . $viewerId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-detail-closed-viewer', $viewerEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'XAGUSD', false, 'viewer closed detail boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/close/order_detail/' . $viewerTicket . '/closed/customer');

        $response->assertOk()
            ->assertSee((string) $viewerTicket, false)
            ->assertSee('order-detail-closed-viewer', false)
            ->assertSee('viewer closed detail boundary order', false);
    }

    // 验证遗留持仓订单详情拒绝他人订单且不泄露内容。
    public function test_legacy_open_order_detail_rejects_other_user_order_without_leaking_content(): void
    {
        [$viewerId, $otherId] = $this->unusedUserIds(2);
        [$viewerTicket, $otherTicket] = $this->unusedTickets(2);
        $viewerEmail = 'front-order-detail-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-order-detail-boundary-' . $otherId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-detail-open-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'order-detail-open-boundary-other', $otherEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'EURUSD', true, 'viewer own open detail boundary order');
        $this->insertTrade($otherId, $otherTicket, 'GBPUSD', true, 'other open detail boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/open/order_detail/' . $otherTicket . '/open/customer');

        $response->assertNotFound();
        $this->assertStringNotContainsString((string) $otherTicket, $response->getContent());
        $this->assertStringNotContainsString('order-detail-open-boundary-other', $response->getContent());
        $this->assertStringNotContainsString('other open detail boundary order', $response->getContent());
    }

    // 验证遗留平仓订单详情拒绝他人订单且不泄露内容。
    public function test_legacy_closed_order_detail_rejects_other_user_order_without_leaking_content(): void
    {
        [$viewerId, $otherId] = $this->unusedUserIds(2);
        [$viewerTicket, $otherTicket] = $this->unusedTickets(2);
        $viewerEmail = 'front-order-detail-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-order-detail-boundary-' . $otherId . '@example.test';

        $this->insertUserInfo($viewerId, 'order-detail-closed-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'order-detail-closed-boundary-other', $otherEmail);
        $this->insertTrade($viewerId, $viewerTicket, 'USDJPY', false, 'viewer own closed detail boundary order');
        $this->insertTrade($otherId, $otherTicket, 'AUDUSD', false, 'other closed detail boundary order');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/close/order_detail/' . $otherTicket . '/closed/customer');

        $response->assertNotFound();
        $this->assertStringNotContainsString((string) $otherTicket, $response->getContent());
        $this->assertStringNotContainsString('order-detail-closed-boundary-other', $response->getContent());
        $this->assertStringNotContainsString('other closed detail boundary order', $response->getContent());
    }

    // 校验权限清单文档记录了订单详情属主边界闭环。
    public function test_final_checklist_records_order_detail_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 240.', $checklist);
        $this->assertStringContainsString('OrderController::openOrderDetail', $checklist);
        $this->assertStringContainsString('OrderController::closeOrderDetail', $checklist);
        $this->assertStringContainsString('open/order_detail/{orderId}/{orderType}/{role}', $checklist);
        $this->assertStringContainsString('close/order_detail/{orderId}/{orderType}/{role}', $checklist);
        $this->assertStringContainsString('FrontOrderDetailOwnerBoundaryClosureModuleTest', $checklist);
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
            'phone' => '1392400' . substr((string) $userId, -4),
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

        throw new \RuntimeException('Unable to allocate unused order-detail fixture user IDs.');
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

        throw new \RuntimeException('Unable to allocate unused order-detail fixture tickets.');
    }
}
