<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端佣金历史-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法读取现代接口 /api/front/commissions/history 的佣金历史列表。
 * - 验证请求即使携带 orderId 也不返回佣金记录。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端佣金历史列表接口的权限边界回归测试，防止客户账号越权读取佣金数据。
 *
 * 入参例子：
 * - GET /api/front/commissions/history?orderId=62020301
 *
 * 返回值：
 * - 接口返回 HTTP 200，业务 code 为 PERMISSION_DENIED，响应不含佣金记录。
 *
 * 异常或失败场景：
 * - 若客户账号能读到佣金记录，或返回码不是 PERMISSION_DENIED，测试失败。
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

class FrontCommissionHistoryApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法读取现代接口的佣金历史列表。
     *
     * 构造客户账号与佣金记录后请求 GET /api/front/commissions/history，
     * 断言返回 PERMISSION_DENIED 且响应不含佣金记录。
     */
    public function test_customer_account_cannot_read_modern_commission_history_list(): void
    {
        $customerId = 412030100;
        $uniqueId = 'front-commission-history-boundary-' . $customerId;
        $orderId = 62020301;

        $this->deleteFixtureRows([$customerId], [$uniqueId]);
        $this->insertUserInfo($customerId, 'commission-history-boundary-customer', 2, 0);
        $this->insertCommissionRecord($customerId, $uniqueId, $orderId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/history?orderId=' . $orderId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertStringNotContainsString($uniqueId, $response->getContent());
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 203 项、history、/api/front/commissions/history 及本测试类名。
     */
    public function test_final_checklist_records_history_commission_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 203.', $checklist);
        $this->assertStringContainsString('history', $checklist);
        $this->assertStringContainsString('/api/front/commissions/history', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontCommissionHistoryApplicantBoundaryClosureModuleTest', $checklist);
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
            'email' => 'front-commission-history-boundary-' . $userId . '@example.test',
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
            'phone' => '1782030' . substr((string) $userId, -4),
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
     * 插入一条佣金记录。
     *
     * @param int $agentId 归属代理商 ID。
     * @param string $uniqueId 佣金记录唯一标识。
     * @param int $orderId 关联订单号（mt4_order_id）。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertCommissionRecord(int $agentId, string $uniqueId, int $orderId): void
    {
        $now = time();

        DB::table('commission_records')->insert([
            'unique_id' => $uniqueId,
            'agent_id' => $agentId,
            'parent_id' => 0,
            'agent_profit' => 12.34,
            'agent_volume' => 1.25,
            'equity_value' => 0,
            'equity_diff' => 0,
            'settle_cycle' => 0,
            'mt4_order_id' => $orderId,
            'date_range' => '2026-07-09',
            'settle_status' => 1,
            'fee' => 0,
            'swap' => 0,
            'commission_amount' => 8.88,
            'returned_amount' => 0,
            'deposit' => 0,
            'real_amount' => 8.88,
            'data_type' => 'mainData',
            'manual_reason' => '',
            'remarks' => 'ordinary customer history boundary fixture',
            'created_by' => 'feature-test',
            'updated_by' => 'feature-test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的佣金记录及层级关系测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param array<int, string> $uniqueIds 待清理的佣金唯一标识列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, array $uniqueIds): void
    {
        DB::table('commission_records')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('unique_id', $uniqueIds)
            ->delete();

        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
    }
}
