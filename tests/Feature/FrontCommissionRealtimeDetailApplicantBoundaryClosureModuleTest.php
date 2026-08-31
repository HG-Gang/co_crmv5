<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前端实时返佣明细-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法读取旧接口 /user/realtime/rebate_detail/{orderNo}/agent 的实时返佣明细。
 * - 验证被拒绝时响应为 403 且不泄漏订单信息。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端实时返佣明细接口的权限边界回归测试，防止客户账号越权读取返佣明细。
 *
 * 入参例子：
 * - GET /user/realtime/rebate_detail/62020401/agent
 *
 * 返回值：
 * - 接口返回 HTTP 403（Forbidden），响应不含订单号。
 *
 * 异常或失败场景：
 * - 若客户账号能读取返佣明细（非 403），或响应泄漏订单数据，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法读取旧接口的实时返佣明细。
     *
     * 构造客户账号、子客户与平仓订单后请求 rebate_detail 接口，
     * 断言返回 403 且响应不含订单号。
     */
    public function test_customer_account_cannot_read_legacy_realtime_rebate_detail(): void
    {
        $customerId = 412040100;
        $childId = 412040101;
        $ticket = 62020401;

        $this->deleteFixtureRows([$customerId, $childId], [$ticket]);
        $this->insertUserInfo($customerId, 'commission-realtime-detail-boundary-customer', 2, 0);
        $this->insertUserInfo($childId, 'commission-realtime-detail-boundary-child', 2, $customerId);
        $this->insertClosedTrade($childId, $ticket);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->get('/user/realtime/rebate_detail/' . $ticket . '/agent');

        $response->assertForbidden();
        $this->assertStringNotContainsString((string) $ticket, $response->getContent());
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 204 项、realtimeRebateDetail、user/realtime/rebate_detail 及本测试类名。
     */
    public function test_final_checklist_records_realtime_rebate_detail_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 204.', $checklist);
        $this->assertStringContainsString('realtimeRebateDetail', $checklist);
        $this->assertStringContainsString('user/realtime/rebate_detail', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据。
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
            'email' => 'front-commission-realtime-detail-boundary-' . $userId . '@example.test',
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
            'phone' => '1782040' . substr((string) $userId, -4),
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

    /**
     * 插入一条已平仓的用户交易订单记录。
     *
     * @param int $userId 所属用户 ID。
     * @param int $ticket 订单票号。
     * @return void 无返回值，仅写入数据库。
     */
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
            'comment' => 'ordinary customer realtime detail boundary fixture',
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
     * 清理指定用户的佣金记录、交易订单及层级关系测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param array<int, int> $tickets 待清理的订单票号列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, array $tickets): void
    {
        DB::table('commission_records')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('mt4_order_id', $tickets)
            ->delete();

        DB::table('user_trades')->whereIn('ticket', $tickets)->delete();

        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
