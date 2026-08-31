<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 前台本人流水作用域属主边界闭环测试。
 *
 * 文件功能：
 * - 验证账号流水（/api/front/flows/account）、入金流水（/api/front/flows/deposits）
 *   与遗留出金流水（/user/flow/withdrawalFlowSearch、/user/flow/withdrawApplyFlowSearch）
 *   忽略伪造的 user_id / userId 过滤参数，只返回当前登录用户自己的流水。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台流水模块“本人流水”的越权过滤回归测试，防止通过查询参数读取他人流水。
 *
 * 入参例子：
 * - 登录账号：viewerId（account_type=2）。
 * - 构造本人订单（如 FLOW-OWN-DEP-VIEWER）与他人的订单（如 FLOW-OWN-DEP-OTHER）。
 * - 伪造参数：user_id={otherId}&userId={otherId}。
 *
 * 返回值：
 * - code 为 SUCCESS；本人数据正常返回（合计金额正确），他人数据不出现。
 * - 被伪造过滤的入金/出金接口 data.list.data 为空数组，合计为 0。
 *
 * 异常或失败场景：
 * - 伪造 user_id / userId 时返回空列表，不泄露其他用户的订单与用户名。
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

class FrontFlowOwnScopeOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证账号流水忽略伪造的 user_id / userId，只返回当前用户流水。
    public function test_account_flow_ignores_spoofed_user_id_and_returns_current_user_flows_only(): void
    {
        $viewerId = 412380100;
        $otherId = 412380101;
        $viewerEmail = 'front-flow-own-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-flow-own-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'flow-own-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'flow-own-other', $otherEmail);
        $this->insertDepositRecord($viewerId, 'flow-own-viewer', 'FLOW-OWN-DEP-VIEWER', 110, 110);
        $this->insertDepositRecord($otherId, 'flow-own-other', 'FLOW-OWN-DEP-OTHER', 999, 999);
        $this->insertWithdrawRecord($viewerId, 'flow-own-viewer', 'FLOW-OWN-WDR-VIEWER', 220, 215);
        $this->insertWithdrawRecord($otherId, 'flow-own-other', 'FLOW-OWN-WDR-OTHER', 888, 888);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/account?user_id=' . $otherId . '&userId=' . $otherId . '&limit=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.totalRow.amount', 330)
            ->assertJsonPath('data.totalRow.actual_amount', 325);

        $rows = $response->json('data.list.data');
        $this->assertCount(2, $rows);
        $this->assertSame([$viewerId], array_values(array_unique(array_map(static function ($row) {
            return (int) $row['user_id'];
        }, $rows))));
        $this->assertStringContainsString('FLOW-OWN-DEP-VIEWER', $response->getContent());
        $this->assertStringContainsString('FLOW-OWN-WDR-VIEWER', $response->getContent());
        $this->assertStringNotContainsString('FLOW-OWN-DEP-OTHER', $response->getContent());
        $this->assertStringNotContainsString('FLOW-OWN-WDR-OTHER', $response->getContent());
        $this->assertStringNotContainsString('flow-own-other', $response->getContent());
    }

    // 验证现代入金流水忽略伪造的 user_id / userId，不泄露他人记录。
    public function test_modern_deposit_flow_rejects_spoofed_user_filter_without_leaking_other_user_records(): void
    {
        $viewerId = 412380200;
        $otherId = 412380201;
        $viewerEmail = 'front-flow-own-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-flow-own-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'flow-deposit-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'flow-deposit-other', $otherEmail);
        $this->insertDepositRecord($viewerId, 'flow-deposit-viewer', 'FLOW-DEPOSIT-VIEWER', 120, 120);
        $this->insertDepositRecord($otherId, 'flow-deposit-other', 'FLOW-DEPOSIT-OTHER', 990, 990);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/deposits?user_id=' . $otherId . '&userId=' . $otherId . '&limit=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.totalRow.amount', 0)
            ->assertJsonPath('data.totalRow.actual_amount', 0);

        $this->assertSame([], $response->json('data.list.data'));
        $this->assertStringNotContainsString('FLOW-DEPOSIT-OTHER', $response->getContent());
        $this->assertStringNotContainsString('flow-deposit-other', $response->getContent());
    }

    // 验证遗留出金流水忽略伪造的 user_id / userId，不泄露他人记录。
    public function test_legacy_withdraw_flows_reject_spoofed_user_filter_without_leaking_other_user_records(): void
    {
        $viewerId = 412380300;
        $otherId = 412380301;
        $viewerEmail = 'front-flow-own-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-flow-own-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'flow-withdraw-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'flow-withdraw-other', $otherEmail);
        $this->insertWithdrawRecord($viewerId, 'flow-withdraw-viewer', 'FLOW-WITHDRAW-VIEWER', 130, 125);
        $this->insertWithdrawRecord($otherId, 'flow-withdraw-other', 'FLOW-WITHDRAW-OTHER', 980, 980);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        foreach (['/user/flow/withdrawalFlowSearch', '/user/flow/withdrawApplyFlowSearch'] as $uri) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->postJson($uri, [
                    'user_id' => $otherId,
                    'userId' => $otherId,
                    'limit' => 20,
                ]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS)
                ->assertJsonPath('data.totalRow.apply_amount', 0)
                ->assertJsonPath('data.totalRow.actual_amount', 0);
            $this->assertSame([], $response->json('data.list.data'));
            $this->assertStringNotContainsString('FLOW-WITHDRAW-OTHER', $response->getContent());
            $this->assertStringNotContainsString('flow-withdraw-other', $response->getContent());
        }
    }

    // 校验权限清单文档记录了本人流水属主边界闭环。
    public function test_final_checklist_records_own_flow_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 238.', $checklist);
        $this->assertStringContainsString('FlowController::accountFlow', $checklist);
        $this->assertStringContainsString('FlowController::depositFlowSearch', $checklist);
        $this->assertStringContainsString('FlowController::withdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('FlowController::withdrawApplyFlowSearch', $checklist);
        $this->assertStringContainsString('/api/front/flows/account', $checklist);
        $this->assertStringContainsString('/api/front/flows/deposits', $checklist);
        $this->assertStringContainsString('user/flow/withdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('FrontFlowOwnScopeOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertDepositRecord(int $userId, string $userName, string $orderNo, float $amount, float $actualAmount): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => 0,
            'amount' => $amount,
            'actual_amount' => $actualAmount,
            'exchange_rate' => 1,
            'channel_name' => 'Flow Boundary Deposit',
            'channel_order_no' => $orderNo . '-CHANNEL',
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => '2026-07-09 11:00:00',
            'remarks' => 'own flow boundary deposit fixture',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertWithdrawRecord(int $userId, string $userName, string $orderNo, float $applyAmount, float $actualAmount): void
    {
        $now = time();

        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => '0',
            'apply_amount' => $applyAmount,
            'actual_amount' => $actualAmount,
            'fee' => $applyAmount - $actualAmount,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '622200000000238',
            'bank_name' => 'Flow Boundary Bank',
            'bank_addr' => 'Flow Boundary Branch',
            'status' => 2,
            'local_order_no' => $orderNo,
            'third_order_no' => $orderNo . '-THIRD',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
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
            'phone' => '1392380' . substr((string) $userId, -4),
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

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
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
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
