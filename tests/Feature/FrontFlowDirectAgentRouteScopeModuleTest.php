<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:05
 */

/**
 * 前台直属代理流水路由作用域闭合测试。
 *
 * 文件功能：
 * - 验证直属代理入金/出金路由（/api/front/flows/direct-agent-deposits、
 *   /api/front/flows/direct-agent-withdrawals）使用代理作用域而非客户作用域，
 *   与普通客户路由（/api/front/flows/direct-deposits、/api/front/flows/direct-withdrawals）
 *   互不串数据。
 * - 验证该作用域修复已登记在 docs/admin-backend-blade-permission-final-checklist.md（第 176 项）。
 *
 * 适用场景：
 * - 回归 FlowController::directDepositFlowSearch 与 directWithdrawalFlowSearch，
 *   防止直属代理流水被错误地按客户作用域过滤或泄露。
 *
 * 入参例子：
 * - root 代理(411600100)登录，其直属代理与直属客户名下分别插入
 *   入金记录（order_no 形如 'DADEP-xxx' / 'DCDEP-xxx'）与出金记录
 *   （order_no 形如 'DAWDR-xxx' / 'DCWDR-xxx'），再分别请求代理与客户路由。
 *
 * 返回值：
 * - 测试无返回值；断言各自响应只含对应 flow_type 的记录、order_no 与 userId
 *   正确（userId 为字符串，旧兼容 JSON 契约），且不含对方记录即表示闭环。
 *
 * 异常或失败场景：
 * - 断言失败意味着代理路由与客户路由作用域混淆，
 *   代理流水可能被过滤丢失或客户流水被泄露，属于越权回归。
 */
namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontFlowDirectAgentRouteScopeModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_direct_agent_deposit_route_uses_agent_scope_instead_of_customer_scope(): void
    {
        $rootAgentId = 411600100;
        $directAgentId = $rootAgentId + 1;
        $directCustomerId = $rootAgentId + 2;
        $agentOrderNo = 'DADEP-' . $rootAgentId;
        $customerOrderNo = 'DCDEP-' . $rootAgentId;

        $this->prepareDirectFlowFixture($rootAgentId, $directAgentId, $directCustomerId);
        $this->insertDepositRecord($directAgentId, 'direct-agent-deposit-user', $agentOrderNo, 610.25);
        $this->insertDepositRecord($directCustomerId, 'direct-customer-deposit-user', $customerOrderNo, 120.75);

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();

        $agentResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-agent-deposits?per_page=10');

        $agentResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.order_no', $agentOrderNo)
            // 旧兼容 JSON 契约中 userId 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.userId', (string) $directAgentId)
            ->assertJsonPath('data.list.data.0.flow_type', 'direct_agents_deposit');
        $this->assertStringNotContainsString($customerOrderNo, $agentResponse->getContent());

        $customerResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-deposits?per_page=10');

        $customerResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.order_no', $customerOrderNo)
            // 旧兼容 JSON 契约中 userId 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.userId', (string) $directCustomerId)
            ->assertJsonPath('data.list.data.0.flow_type', 'direct_deposit');
        $this->assertStringNotContainsString($agentOrderNo, $customerResponse->getContent());
    }

    public function test_direct_agent_withdraw_route_uses_agent_scope_instead_of_customer_scope(): void
    {
        $rootAgentId = 411600200;
        $directAgentId = $rootAgentId + 1;
        $directCustomerId = $rootAgentId + 2;
        $agentOrderNo = 'DAWDR-' . $rootAgentId;
        $customerOrderNo = 'DCWDR-' . $rootAgentId;

        $this->prepareDirectFlowFixture($rootAgentId, $directAgentId, $directCustomerId);
        $this->insertWithdrawRecord($directAgentId, 'direct-agent-withdraw-user', $agentOrderNo, 410.25);
        $this->insertWithdrawRecord($directCustomerId, 'direct-customer-withdraw-user', $customerOrderNo, 90.75);

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();

        $agentResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-agent-withdrawals?per_page=10');

        $agentResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.order_no', $agentOrderNo)
            // 旧兼容 JSON 契约中 userId 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.userId', (string) $directAgentId)
            ->assertJsonPath('data.list.data.0.flow_type', 'direct_agents_withdraw');
        $this->assertStringNotContainsString($customerOrderNo, $agentResponse->getContent());

        $customerResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-withdrawals?per_page=10');

        $customerResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.list.data.0.order_no', $customerOrderNo)
            // 旧兼容 JSON 契约中 userId 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.userId', (string) $directCustomerId)
            ->assertJsonPath('data.list.data.0.flow_type', 'direct_withdraw');
        $this->assertStringNotContainsString($agentOrderNo, $customerResponse->getContent());
    }

    public function test_final_checklist_records_direct_agent_flow_route_scope_fix(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString('## 176.', $checklist);
        $this->assertStringContainsString('FlowController::directDepositFlowSearch', $checklist);
        $this->assertStringContainsString('FlowController::directWithdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('FrontFlowDirectAgentRouteScopeModuleTest', $checklist);
        $this->assertStringContainsString('direct_agents_deposit', $checklist);
        $this->assertStringContainsString('direct_agents_withdraw', $checklist);
    }

    private function prepareDirectFlowFixture(int $rootAgentId, int $directAgentId, int $directCustomerId): void
    {
        $this->deleteAgentDescendantRows([$rootAgentId, $directAgentId, $directCustomerId]);
        DB::table('deposit_records')->whereIn('user_id', [$rootAgentId, $directAgentId, $directCustomerId])->delete();
        DB::table('withdraw_records')->whereIn('user_id', [$rootAgentId, $directAgentId, $directCustomerId])->delete();

        $this->insertUserInfo($rootAgentId, 'direct-flow-root-agent', 1, 0);
        $this->insertUserInfo($directAgentId, 'direct-flow-child-agent', 1, $rootAgentId);
        $this->insertUserInfo($directCustomerId, 'direct-flow-child-customer', 2, $rootAgentId);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-flow-direct-agent-' . $userId . '@example.test',
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
            'phone' => '1786000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
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

    private function insertDepositRecord(int $userId, string $userName, string $orderNo, float $amount): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => $userId,
            'amount' => $amount,
            'actual_amount' => $amount,
            'exchange_rate' => 1,
            'channel_name' => 'manual-bank',
            'channel_order_no' => 'CH-' . $orderNo,
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => date('Y-m-d H:i:s', $now),
            'remarks' => 'direct flow route scope test',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertWithdrawRecord(int $userId, string $userName, string $orderNo, float $amount): void
    {
        $now = time();

        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => (string) $userId,
            'apply_amount' => $amount,
            'actual_amount' => $amount,
            'fee' => 0,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '6222000000000000',
            'bank_name' => 'Test Bank',
            'bank_addr' => 'Test Branch',
            'status' => 2,
            'local_order_no' => $orderNo,
            'third_order_no' => 'TH-' . $orderNo,
            'reject_reason' => '',
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
     */
    private function deleteAgentDescendantRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('descendant_id', $userIds)
            ->delete();
    }
}
