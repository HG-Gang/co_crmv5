<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:44
 */

/**
 * 前台直客佣金划转申请人边界闭环测试。
 *
 * 文件功能：
 * - 验证普通客户（account_type=2）不能通过现代接口 /api/front/customers/commission-transfers
 *   或遗留接口 /user/proxy/directUserCommTrans 向直接客户划转佣金。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台“直接客户佣金划转”功能的回归测试，防止普通客户越权操作佣金转账。
 *
 * 入参例子：
 * - depositId: 411990101（收款直接客户 ID）
 * - comm_money: 15.50（划转金额）
 * - password: transfer-password（资金密码）
 * - remark: customer must not transfer（备注）
 *
 * 返回值：
 * - 接口均返回 HTTP 200，msg 为 FAIL，errorType 为 NOTALLOW。
 * - 双方 total_funds 余额与 commission_records 保持不变。
 *
 * 异常或失败场景：
 * - 普通客户调用划转接口时被拒绝（NOTALLOW）。
 * - 权限清单缺失对应记录时断言失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontDirectTransferApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证普通客户不能通过现代接口向直接客户划转佣金。
    public function test_customer_account_cannot_transfer_commission_through_modern_direct_customer_endpoint(): void
    {
        $customerId = 411990100;
        $directCustomerId = 411990101;

        $this->deleteTransferFixtureRows([$customerId, $directCustomerId]);
        $this->insertUserInfo($customerId, 'direct-transfer-modern-boundary-customer', 2, 0, 80.00);
        $this->insertUserInfo($directCustomerId, 'direct-transfer-modern-boundary-child', 2, $customerId, 5.00);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/customers/commission-transfers', [
                'depositId' => $directCustomerId,
                'comm_money' => 15.50,
                'password' => 'transfer-password',
                'remark' => 'customer must not transfer',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('errorType', 'NOTALLOW');

        $this->assertTransferWasNotChanged($customerId, $directCustomerId, 80.00, 5.00);
    }

    // 验证普通客户不能通过遗留接口向直接客户划转佣金。
    public function test_customer_account_cannot_transfer_commission_through_legacy_direct_customer_endpoint(): void
    {
        $customerId = 411990200;
        $directCustomerId = 411990201;

        $this->deleteTransferFixtureRows([$customerId, $directCustomerId]);
        $this->insertUserInfo($customerId, 'direct-transfer-legacy-boundary-customer', 2, 0, 90.00);
        $this->insertUserInfo($directCustomerId, 'direct-transfer-legacy-boundary-child', 2, $customerId, 7.00);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/directUserCommTrans', [
                'depositId' => $directCustomerId,
                'comm_money' => 16.25,
                'password' => 'transfer-password',
                'remark' => 'legacy customer must not transfer',
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('errorType', 'NOTALLOW');

        $this->assertTransferWasNotChanged($customerId, $directCustomerId, 90.00, 7.00);
    }

    // 校验权限清单文档记录了直接客户划转申请人边界闭环。
    public function test_final_checklist_records_direct_transfer_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 199.', $checklist);
        $this->assertStringContainsString('directUserCommTrans', $checklist);
        $this->assertStringContainsString('customers/commission-transfers', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontDirectTransferApplicantBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, float $totalFunds): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-direct-transfer-boundary-' . $userId . '@example.test',
            'password' => Hash::make('transfer-password'),
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
            'phone' => '1789900' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => $totalFunds,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => $totalFunds,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function deleteTransferFixtureRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();

        DB::table('commission_records')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('parent_id', $userIds);
            })
            ->delete();
    }

    private function assertTransferWasNotChanged(
        int $sourceUserId,
        int $targetUserId,
        float $sourceBalance,
        float $targetBalance
    ): void {
        $this->assertSame($sourceBalance, (float) DB::table('user_infos')->where('user_id', $sourceUserId)->value('total_funds'));
        $this->assertSame($targetBalance, (float) DB::table('user_infos')->where('user_id', $targetUserId)->value('total_funds'));
        $this->assertSame(
            0,
            DB::table('commission_records')
                ->where('data_type', 'transfer')
                ->whereIn('agent_id', [$sourceUserId, $targetUserId])
                ->count()
        );
    }
}
