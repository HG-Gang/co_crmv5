<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台直接代理出入金流水申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能读取直接代理的入金/出金流水，包括现代接口
 *   /api/front/flows/direct-agent-deposits、/api/front/flows/direct-agent-withdrawals
 *   与遗留接口 /user/flow/directAgentsDepositFlowSearch、/user/flow/directAgentsWithdrawalFlowSearch。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台流水模块“直接代理出入金”功能的回归测试，防止普通客户越权查看代理流水。
 *
 * 入参例子：
 * - 登录账号：普通客户（account_type=2），其下挂一个直接代理（account_type=1）。
 * - 为该代理构造 deposit_records / withdraw_records 数据（local_order_no 形如 FDAGD-21301）。
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 PERMISSION_DENIED，响应体不含订单号与代理用户名。
 *
 * 异常或失败场景：
 * - 普通客户访问直接代理出入金流水接口时被拒绝（PERMISSION_DENIED）。
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

class FrontFlowDirectAgentApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代/遗留接口读取直接代理的入金流水。
    public function test_customer_account_cannot_read_direct_agent_deposit_flows(): void
    {
        $customerId = 412130100;
        $childAgentId = 412130101;
        $orderNo = 'FDAGD-21301';

        $this->deleteFixtureRows([$customerId, $childAgentId], [$orderNo]);
        $this->insertUserInfo($customerId, 'flow-direct-agent-deposit-boundary-customer', 2, 0);
        $this->insertUserInfo($childAgentId, 'flow-direct-agent-deposit-boundary-agent', 1, $customerId);
        $this->insertDepositRecord($childAgentId, 'flow-direct-agent-deposit-boundary-agent', $orderNo);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();

        $modern = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-agent-deposits');
        $modern->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString($orderNo, $modern->getContent());
        $this->assertStringNotContainsString('flow-direct-agent-deposit-boundary-agent', $modern->getContent());

        $legacy = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/directAgentsDepositFlowSearch');
        $legacy->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString($orderNo, $legacy->getContent());
        $this->assertStringNotContainsString('flow-direct-agent-deposit-boundary-agent', $legacy->getContent());
    }

    // 验证普通客户不能通过现代/遗留接口读取直接代理的出金流水。
    public function test_customer_account_cannot_read_direct_agent_withdraw_flows(): void
    {
        $customerId = 412130200;
        $childAgentId = 412130201;
        $orderNo = 'FDAGW-21302';

        $this->deleteFixtureRows([$customerId, $childAgentId], [$orderNo]);
        $this->insertUserInfo($customerId, 'flow-direct-agent-withdraw-boundary-customer', 2, 0);
        $this->insertUserInfo($childAgentId, 'flow-direct-agent-withdraw-boundary-agent', 1, $customerId);
        $this->insertWithdrawRecord($childAgentId, 'flow-direct-agent-withdraw-boundary-agent', $orderNo);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();

        $modern = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-agent-withdrawals');
        $modern->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString($orderNo, $modern->getContent());
        $this->assertStringNotContainsString('flow-direct-agent-withdraw-boundary-agent', $modern->getContent());

        $legacy = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/directAgentsWithdrawalFlowSearch');
        $legacy->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString($orderNo, $legacy->getContent());
        $this->assertStringNotContainsString('flow-direct-agent-withdraw-boundary-agent', $legacy->getContent());
    }

    // 校验权限清单文档记录了直接代理流水申请人边界闭环。
    public function test_final_checklist_records_direct_agent_flow_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 213.', $checklist);
        $this->assertStringContainsString('direct_agents_deposit', $checklist);
        $this->assertStringContainsString('direct_agents_withdraw', $checklist);
        $this->assertStringContainsString('/api/front/flows/direct-agent-deposits', $checklist);
        $this->assertStringContainsString('/api/front/flows/direct-agent-withdrawals', $checklist);
        $this->assertStringContainsString('FrontFlowDirectAgentApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-flow-direct-agent-boundary-' . $userId . '@example.test',
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
            'phone' => '1782130' . substr((string) $userId, -4),
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

    private function insertDepositRecord(int $userId, string $userName, string $orderNo): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => 0,
            'amount' => 213.10,
            'actual_amount' => 213.10,
            'exchange_rate' => 1,
            'channel_name' => 'Bank',
            'channel_order_no' => $orderNo . '-CHANNEL',
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => '2026-07-09 13:10:00',
            'remarks' => 'direct agent deposit boundary fixture',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertWithdrawRecord(int $userId, string $userName, string $orderNo): void
    {
        $now = time();

        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => '0',
            'apply_amount' => 213.20,
            'actual_amount' => 213.20,
            'fee' => 0,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '622200000000213',
            'bank_name' => 'Boundary Bank',
            'bank_addr' => 'Boundary Branch',
            'status' => 2,
            'local_order_no' => $orderNo,
            'third_order_no' => $orderNo . '-THIRD',
            'reject_reason' => null,
            'mt4_return_status' => '',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $orderNos
     */
    private function deleteFixtureRows(array $userIds, array $orderNos): void
    {
        DB::table('deposit_records')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('local_order_no', $orderNos)
            ->delete();
        DB::table('withdraw_records')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('local_order_no', $orderNos)
            ->delete();
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
