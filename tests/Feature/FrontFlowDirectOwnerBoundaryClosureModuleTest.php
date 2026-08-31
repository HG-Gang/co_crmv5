<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台直接出入金流水属主边界闭环测试。
 *
 * 文件功能：
 * - 验证代理账号只能看到自己直接客户的入金/出金流水，伪造的 user_id / userId
 *   过滤参数不会泄露其他代理分支的数据（现代与遗留接口均覆盖）。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台流水模块“直接客户/直接代理出入金流水”的越权过滤回归测试。
 *
 * 入参例子：
 * - 登录账号：查看方代理（account_type=1）。
 * - 构造本分支订单（ownOrderNo）与其他分支订单（otherOrderNo）的 deposit_records / withdraw_records。
 * - 伪造参数：?user_id={otherCustomerId}&userId={otherCustomerId} 或请求体同名字段。
 *
 * 返回值：
 * - 正常请求返回 code 为 SUCCESS，仅含本分支订单。
 * - 伪造过滤请求返回 code 为 SUCCESS 但 data.list.data 为空数组，不含其他分支数据。
 *
 * 异常或失败场景：
 * - 伪造 user_id / userId 时返回空列表，不泄露其他代理分支的订单与用户名。
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

class FrontFlowDirectOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证现代接口直接客户入金流水忽略伪造的 user_id / userId 过滤参数。
    public function test_modern_direct_customer_deposit_flows_reject_spoofed_other_branch_user_filter(): void
    {
        $viewerAgentId = 412410100;
        $ownCustomerId = 412410101;
        $otherAgentId = 412410102;
        $otherCustomerId = 412410103;
        $ownOrderNo = 'FDDOB-24101-OWN';
        $otherOrderNo = 'FDDOB-24101-OTHER';

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId], [$ownOrderNo, $otherOrderNo]);
        $this->insertUserInfo($viewerAgentId, 'direct-flow-owner-viewer-agent', 1, 0);
        $this->insertUserInfo($ownCustomerId, 'direct-flow-owner-own-customer', 2, $viewerAgentId);
        $this->insertUserInfo($otherAgentId, 'direct-flow-owner-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'direct-flow-owner-other-customer', 2, $otherAgentId);
        $this->insertDepositRecord($ownCustomerId, 'direct-flow-owner-own-customer', $ownOrderNo);
        $this->insertDepositRecord($otherCustomerId, 'direct-flow-owner-other-customer', $otherOrderNo);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-deposits?per_page=20');

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString($ownOrderNo, $visibleResponse->getContent());
        $this->assertStringNotContainsString($otherOrderNo, $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-deposits?user_id=' . $otherCustomerId . '&userId=' . $otherCustomerId . '&per_page=20');

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString($otherOrderNo, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('direct-flow-owner-other-customer', $spoofedResponse->getContent());
    }

    // 验证遗留接口直接客户出金流水忽略伪造的 user_id / userId 过滤参数。
    public function test_legacy_direct_customer_withdraw_flows_reject_spoofed_other_branch_user_filter(): void
    {
        $viewerAgentId = 412410200;
        $ownCustomerId = 412410201;
        $otherAgentId = 412410202;
        $otherCustomerId = 412410203;
        $ownOrderNo = 'FDWOB-24102-OWN';
        $otherOrderNo = 'FDWOB-24102-OTHER';

        $this->deleteFixtureRows([$viewerAgentId, $ownCustomerId, $otherAgentId, $otherCustomerId], [$ownOrderNo, $otherOrderNo]);
        $this->insertUserInfo($viewerAgentId, 'direct-withdraw-owner-viewer-agent', 1, 0);
        $this->insertUserInfo($ownCustomerId, 'direct-withdraw-owner-own-customer', 2, $viewerAgentId);
        $this->insertUserInfo($otherAgentId, 'direct-withdraw-owner-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'direct-withdraw-owner-other-customer', 2, $otherAgentId);
        $this->insertWithdrawRecord($ownCustomerId, 'direct-withdraw-owner-own-customer', $ownOrderNo);
        $this->insertWithdrawRecord($otherCustomerId, 'direct-withdraw-owner-other-customer', $otherOrderNo);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/directWithdrawalFlowSearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString($ownOrderNo, $visibleResponse->getContent());
        $this->assertStringNotContainsString($otherOrderNo, $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/directWithdrawalFlowSearch', [
                'user_id' => $otherCustomerId,
                'userId' => $otherCustomerId,
                'limit' => 20,
            ]);

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString($otherOrderNo, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('direct-withdraw-owner-other-customer', $spoofedResponse->getContent());
    }

    // 验证现代接口直接代理入金流水忽略伪造的 user_id / userId 过滤参数。
    public function test_modern_direct_agent_deposit_flows_reject_spoofed_other_branch_agent_filter(): void
    {
        $viewerAgentId = 412410300;
        $ownAgentId = 412410301;
        $otherRootAgentId = 412410302;
        $otherAgentId = 412410303;
        $ownOrderNo = 'FDADOB-24103-OWN';
        $otherOrderNo = 'FDADOB-24103-OTHER';

        $this->deleteFixtureRows([$viewerAgentId, $ownAgentId, $otherRootAgentId, $otherAgentId], [$ownOrderNo, $otherOrderNo]);
        $this->insertUserInfo($viewerAgentId, 'direct-agent-flow-owner-viewer-agent', 1, 0);
        $this->insertUserInfo($ownAgentId, 'direct-agent-flow-owner-own-agent', 1, $viewerAgentId);
        $this->insertUserInfo($otherRootAgentId, 'direct-agent-flow-owner-other-root', 1, 0);
        $this->insertUserInfo($otherAgentId, 'direct-agent-flow-owner-other-agent', 1, $otherRootAgentId);
        $this->insertDepositRecord($ownAgentId, 'direct-agent-flow-owner-own-agent', $ownOrderNo);
        $this->insertDepositRecord($otherAgentId, 'direct-agent-flow-owner-other-agent', $otherOrderNo);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-agent-deposits?per_page=20');

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString($ownOrderNo, $visibleResponse->getContent());
        $this->assertStringNotContainsString($otherOrderNo, $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-agent-deposits?user_id=' . $otherAgentId . '&userId=' . $otherAgentId . '&per_page=20');

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString($otherOrderNo, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('direct-agent-flow-owner-other-agent', $spoofedResponse->getContent());
    }

    // 验证遗留接口直接代理出金流水忽略伪造的 user_id / userId 过滤参数。
    public function test_legacy_direct_agent_withdraw_flows_reject_spoofed_other_branch_agent_filter(): void
    {
        $viewerAgentId = 412410400;
        $ownAgentId = 412410401;
        $otherRootAgentId = 412410402;
        $otherAgentId = 412410403;
        $ownOrderNo = 'FDAWOB-24104-OWN';
        $otherOrderNo = 'FDAWOB-24104-OTHER';

        $this->deleteFixtureRows([$viewerAgentId, $ownAgentId, $otherRootAgentId, $otherAgentId], [$ownOrderNo, $otherOrderNo]);
        $this->insertUserInfo($viewerAgentId, 'direct-agent-withdraw-owner-viewer', 1, 0);
        $this->insertUserInfo($ownAgentId, 'direct-agent-withdraw-owner-own-agent', 1, $viewerAgentId);
        $this->insertUserInfo($otherRootAgentId, 'direct-agent-withdraw-owner-other-root', 1, 0);
        $this->insertUserInfo($otherAgentId, 'direct-agent-withdraw-owner-other-agent', 1, $otherRootAgentId);
        $this->insertWithdrawRecord($ownAgentId, 'direct-agent-withdraw-owner-own-agent', $ownOrderNo);
        $this->insertWithdrawRecord($otherAgentId, 'direct-agent-withdraw-owner-other-agent', $otherOrderNo);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/directAgentsWithdrawalFlowSearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString($ownOrderNo, $visibleResponse->getContent());
        $this->assertStringNotContainsString($otherOrderNo, $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/directAgentsWithdrawalFlowSearch', [
                'user_id' => $otherAgentId,
                'userId' => $otherAgentId,
                'limit' => 20,
            ]);

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString($otherOrderNo, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('direct-agent-withdraw-owner-other-agent', $spoofedResponse->getContent());
    }

    // 校验权限清单文档记录了直接流水属主边界闭环。
    public function test_final_checklist_records_direct_flow_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 241.', $checklist);
        $this->assertStringContainsString('FlowController::directDepositFlowSearch', $checklist);
        $this->assertStringContainsString('FlowController::directWithdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('/api/front/flows/direct-deposits', $checklist);
        $this->assertStringContainsString('/api/front/flows/direct-agent-deposits', $checklist);
        $this->assertStringContainsString('user/flow/directWithdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('user/flow/directAgentsWithdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('FrontFlowDirectOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-flow-direct-owner-' . $userId . '@example.test',
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
            'phone' => '1782410' . substr((string) $userId, -4),
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

    private function insertDepositRecord(int $userId, string $userName, string $orderNo): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => 0,
            'amount' => 241.10,
            'actual_amount' => 241.10,
            'exchange_rate' => 1,
            'channel_name' => 'Boundary Bank',
            'channel_order_no' => $orderNo . '-CHANNEL',
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => '2026-07-09 14:10:00',
            'remarks' => 'direct flow owner boundary fixture',
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
            'apply_amount' => 241.20,
            'actual_amount' => 241.20,
            'fee' => 0,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '6222000000002410',
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
