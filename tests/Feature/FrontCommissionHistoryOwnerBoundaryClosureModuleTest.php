<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 前端佣金历史-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证现代佣金历史接口 /api/front/commissions/history 只返回当前代理商自己的佣金记录。
 * - 验证通过 orderId 伪装过滤其他代理商的订单时返回空列表且不泄漏数据。
 * - 验证 dataType 过滤不会跨越代理商数据范围。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端佣金历史接口的归属权边界回归测试。
 *
 * 入参例子：
 * - GET /api/front/commissions/history?per_page=20
 * - GET /api/front/commissions/history?orderId={其他代理商订单号}&per_page=20
 * - GET /api/front/commissions/history?dataType=transfer&orderId={其他代理商订单号}&per_page=20
 *
 * 返回值：
 * - 合法查询返回 SUCCESS 且 analytics.agent_id 为当前代理商；伪装查询返回 SUCCESS 但列表为空。
 *
 * 异常或失败场景：
 * - 若其他代理商的佣金记录被返回，或合法查询缺失自身记录，测试失败。
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

class FrontCommissionHistoryOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证佣金历史按其他订单号过滤时仍保持当前代理商范围。
     *
     * 构造当前代理商与他人的佣金记录，未过滤时只返回自身记录；
     * 带他人 orderId 过滤时返回空列表。
     */
    public function test_modern_commission_history_keeps_current_agent_scope_when_filtering_by_other_order(): void
    {
        $viewerAgentId = 412450100;
        $otherAgentId = 412450101;
        $ownOrderId = 82450101;
        $otherOrderId = 82450102;
        $ownUniqueId = 'front-commission-history-owner-own';
        $otherUniqueId = 'front-commission-history-owner-other';

        $this->deleteFixtureRows([$viewerAgentId, $otherAgentId], [$ownUniqueId, $otherUniqueId], [$ownOrderId, $otherOrderId]);
        $this->insertUserInfo($viewerAgentId, 'commission-history-owner-viewer-agent', 1, 0);
        $this->insertUserInfo($otherAgentId, 'commission-history-owner-other-agent', 1, 0);
        $this->insertCommissionRecord($viewerAgentId, $ownUniqueId, $ownOrderId, 'mainData', 'visible history owner record');
        $this->insertCommissionRecord($otherAgentId, $otherUniqueId, $otherOrderId, 'mainData', 'other history owner record');

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/history?per_page=20');

        $visibleResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.analytics.agent_id', $viewerAgentId);
        $this->assertStringContainsString($ownUniqueId, $visibleResponse->getContent());
        $this->assertStringContainsString((string) $ownOrderId, $visibleResponse->getContent());
        $this->assertStringNotContainsString($otherUniqueId, $visibleResponse->getContent());
        $this->assertStringNotContainsString('other history owner record', $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/history?orderId=' . $otherOrderId . '&per_page=20');

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.analytics.agent_id', $viewerAgentId);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString($otherUniqueId, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('other history owner record', $spoofedResponse->getContent());
    }

    /**
     * 验证佣金历史的 dataType 过滤不会跨越代理商数据范围。
     *
     * 按 dataType=transfer 查询只返回自身转账类记录；
     * 再叠加他人 orderId 过滤时返回空列表。
     */
    public function test_modern_commission_history_data_type_filter_does_not_cross_agent_scope(): void
    {
        $viewerAgentId = 412450200;
        $otherAgentId = 412450201;
        $ownOrderId = 82450201;
        $otherOrderId = 82450202;
        $ownUniqueId = 'front-commission-history-transfer-owner-own';
        $otherUniqueId = 'front-commission-history-transfer-owner-other';

        $this->deleteFixtureRows([$viewerAgentId, $otherAgentId], [$ownUniqueId, $otherUniqueId], [$ownOrderId, $otherOrderId]);
        $this->insertUserInfo($viewerAgentId, 'commission-history-transfer-owner-agent', 1, 0);
        $this->insertUserInfo($otherAgentId, 'commission-history-transfer-other-agent', 1, 0);
        $this->insertCommissionRecord($viewerAgentId, $ownUniqueId, $ownOrderId, 'transfer', 'visible transfer history owner record');
        $this->insertCommissionRecord($otherAgentId, $otherUniqueId, $otherOrderId, 'transfer', 'other transfer history owner record');

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/history?dataType=transfer&per_page=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString($ownUniqueId, $response->getContent());
        $this->assertStringContainsString((string) $ownOrderId, $response->getContent());
        $this->assertStringNotContainsString($otherUniqueId, $response->getContent());
        $this->assertStringNotContainsString('other transfer history owner record', $response->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/commissions/history?dataType=transfer&orderId=' . $otherOrderId . '&per_page=20');

        $spoofedResponse->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $spoofedResponse->json('data.list.data'));
        $this->assertStringNotContainsString($otherUniqueId, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('other transfer history owner record', $spoofedResponse->getContent());
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 245 项、CommissionController::history、orderId、dataType 及本测试类名。
     */
    public function test_final_checklist_records_commission_history_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 245.', $checklist);
        $this->assertStringContainsString('CommissionController::history', $checklist);
        $this->assertStringContainsString('/api/front/commissions/history', $checklist);
        $this->assertStringContainsString('orderId', $checklist);
        $this->assertStringContainsString('dataType', $checklist);
        $this->assertStringContainsString('FrontCommissionHistoryOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据，代理商默认级别 1、佣金比例 0.1。
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
            'email' => 'front-commission-history-owner-' . $userId . '@example.test',
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
            'phone' => '1782450' . substr((string) $userId, -4),
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

    /**
     * 插入一条指定数据类型与备注的佣金记录。
     *
     * @param int $agentId 归属代理商 ID。
     * @param string $uniqueId 佣金记录唯一标识。
     * @param int $orderId 关联订单号（mt4_order_id）。
     * @param string $dataType 数据类型（如 mainData、transfer）。
     * @param string $remarks 备注内容。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertCommissionRecord(int $agentId, string $uniqueId, int $orderId, string $dataType, string $remarks): void
    {
        $now = time();

        DB::table('commission_records')->insert([
            'unique_id' => $uniqueId,
            'agent_id' => $agentId,
            'parent_id' => 0,
            'agent_profit' => 12.34,
            'agent_volume' => 1,
            'equity_value' => 0,
            'equity_diff' => 0,
            'settle_cycle' => 0,
            'mt4_order_id' => $orderId,
            'date_range' => '2026-07-09',
            'settle_status' => 2,
            'fee' => 0,
            'swap' => 0,
            'commission_amount' => 12.34,
            'returned_amount' => 0,
            'deposit' => 0,
            'real_amount' => 12.34,
            'data_type' => $dataType,
            'manual_reason' => '',
            'remarks' => $remarks,
            'created_by' => 'phpunit',
            'updated_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 清理指定用户的佣金记录、层级关系及用户信息测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @param array<int, string> $uniqueIds 待清理的佣金唯一标识列表。
     * @param array<int, int> $orderIds 待清理的订单号列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds, array $uniqueIds, array $orderIds): void
    {
        DB::table('commission_records')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('parent_id', $userIds)
            ->orWhereIn('unique_id', $uniqueIds)
            ->orWhereIn('mt4_order_id', $orderIds)
            ->delete();

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
