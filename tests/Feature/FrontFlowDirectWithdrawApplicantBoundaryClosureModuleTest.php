<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台直接客户出金流水申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能读取直接客户的出金流水，包括现代接口
 *   /api/front/flows/direct-withdrawals 与遗留接口 /user/flow/directWithdrawalFlowSearch。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台流水模块“直接客户出金流水”功能的回归测试，防止普通客户越权查看他人流水。
 *
 * 入参例子：
 * - 登录账号：普通客户（account_type=2），其下挂一个直接客户（account_type=2）。
 * - 为该直接客户构造 withdraw_records 数据（local_order_no 形如 FDWB-21201）。
 *
 * 返回值：
 * - 接口返回 HTTP 200，code 为 PERMISSION_DENIED，响应体不含订单号与客户用户名。
 *
 * 异常或失败场景：
 * - 普通客户访问直接客户出金流水接口时被拒绝（PERMISSION_DENIED）。
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

class FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代接口读取直接客户的出金流水。
    public function test_customer_account_cannot_read_modern_direct_customer_withdraw_flows(): void
    {
        $customerId = 412120100;
        $childId = 412120101;
        $orderNo = 'FDWB-21201';

        $this->deleteFixtureRows([$customerId, $childId], [$orderNo]);
        $this->insertUserInfo($customerId, 'flow-direct-withdraw-boundary-customer', 2, 0);
        $this->insertUserInfo($childId, 'flow-direct-withdraw-boundary-child', 2, $customerId);
        $this->insertWithdrawRecord($childId, 'flow-direct-withdraw-boundary-child', $orderNo);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/flows/direct-withdrawals');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString($orderNo, $response->getContent());
        $this->assertStringNotContainsString('flow-direct-withdraw-boundary-child', $response->getContent());
    }

    // 验证普通客户不能通过遗留接口读取直接客户的出金流水。
    public function test_customer_account_cannot_read_legacy_direct_customer_withdraw_flows(): void
    {
        $customerId = 412120200;
        $childId = 412120201;
        $orderNo = 'FDWB-21202';

        $this->deleteFixtureRows([$customerId, $childId], [$orderNo]);
        $this->insertUserInfo($customerId, 'flow-direct-withdraw-legacy-boundary-customer', 2, 0);
        $this->insertUserInfo($childId, 'flow-direct-withdraw-legacy-boundary-child', 2, $customerId);
        $this->insertWithdrawRecord($childId, 'flow-direct-withdraw-legacy-boundary-child', $orderNo);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/flow/directWithdrawalFlowSearch');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString($orderNo, $response->getContent());
        $this->assertStringNotContainsString('flow-direct-withdraw-legacy-boundary-child', $response->getContent());
    }

    // 校验权限清单文档记录了直接客户出金流水申请人边界闭环。
    public function test_final_checklist_records_direct_withdraw_flow_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 212.', $checklist);
        $this->assertStringContainsString('directWithdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('/api/front/flows/direct-withdrawals', $checklist);
        $this->assertStringContainsString('user/flow/directWithdrawalFlowSearch', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-flow-direct-withdraw-boundary-' . $userId . '@example.test',
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
            'phone' => '1782120' . substr((string) $userId, -4),
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

    private function insertWithdrawRecord(int $userId, string $userName, string $orderNo): void
    {
        $now = time();

        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'mt4_ticket' => '0',
            'apply_amount' => 212.50,
            'actual_amount' => 212.50,
            'fee' => 0,
            'exchange_rate' => 1,
            'rmb_fee' => 0,
            'bank_no' => '622200000000212',
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
        DB::table('withdraw_records')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('local_order_no', $orderNos)
            ->delete();
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
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
