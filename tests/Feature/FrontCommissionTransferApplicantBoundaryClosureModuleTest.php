<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前端佣金转账-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法向直系代理商发起佣金转账。
 * - 验证被拒绝后双方余额与转账记录均未变化。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端佣金转账接口的权限边界回归测试，防止客户账号越权转账。
 *
 * 入参例子：
 * - POST /api/front/commissions/transfers
 *   请求体：{ "sub_agent_id": 412000101, "amount": 18.75, "remark": "..." }
 *
 * 返回值：
 * - 接口返回 HTTP 200，业务 code 为 PERMISSION_DENIED，双方 total_funds 不变且无 transfer 记录。
 *
 * 异常或失败场景：
 * - 若客户账号转账成功（返回非 PERMISSION_DENIED），或余额/记录被改动，测试失败。
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

class FrontCommissionTransferApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法向直系代理商转账佣金。
     *
     * 构造客户-代理商父子关系后请求 POST /api/front/commissions/transfers，
     * 断言返回 PERMISSION_DENIED 且双方余额、转账记录均未变化。
     */
    public function test_customer_account_cannot_transfer_commission_to_direct_agent(): void
    {
        $customerId = 412000100;
        $directAgentId = 412000101;

        $this->deleteTransferFixtureRows([$customerId, $directAgentId]);
        $this->insertUserInfo($customerId, 'commission-transfer-boundary-customer', 2, 0, 100.00);
        $this->insertUserInfo($directAgentId, 'commission-transfer-boundary-agent', 1, $customerId, 6.00);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/commissions/transfers', [
                'sub_agent_id' => $directAgentId,
                'amount' => 18.75,
                'remark' => 'customer must not use agent commission transfer',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertTransferWasNotChanged($customerId, $directAgentId, 100.00, 6.00);
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 200 项、CommissionController::transfer、/api/front/commissions/transfers 及本测试类名。
     */
    public function test_final_checklist_records_commission_transfer_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 200.', $checklist);
        $this->assertStringContainsString('CommissionController::transfer', $checklist);
        $this->assertStringContainsString('/api/front/commissions/transfers', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontCommissionTransferApplicantBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带余额的测试用户数据，代理商默认级别 1、佣金比例 0.2 且已确认。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @param float $totalFunds 账户总资金。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, float $totalFunds): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-commission-transfer-boundary-' . $userId . '@example.test',
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
            'phone' => '1782000' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
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
     * 清理指定用户的层级关系及佣金记录测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
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

    /**
     * 断言转账未发生：双方余额保持、无 transfer 类型佣金记录。
     *
     * @param int $sourceUserId 转出方用户 ID。
     * @param int $targetUserId 转入方用户 ID。
     * @param float $sourceBalance 转出方期望余额。
     * @param float $targetBalance 转入方期望余额。
     * @return void 断言失败时抛出 AssertionFailedError。
     */
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
