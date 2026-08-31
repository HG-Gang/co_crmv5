<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前台账户总览数据范围回退（Scope Fallback）闭环测试。
 *
 * 文件功能：
 * - 验证当 agent_descendants 关系表缺失时，账户总览改用 user_infos.parent_id 构建树形范围。
 * - 验证直接/间接代理与客户数量、关联金额、客户性别画像均按回退范围正确统计。
 * - 验证最终清单文档已记录该回退逻辑。
 *
 * 适用场景：
 * - 前台代理账户总览在关系表数据缺失时的兼容回归测试。
 *
 * 入参例子：
 * - GET /api/front/account/profile（以 rootAgent 登录，agent_descendants 为空）。
 *
 * 返回值：
 * - code 为 SUCCESS；direct_agents、direct_customers、indirect_customers、relation_amount 等按预期统计。
 *
 * 异常或失败场景：
 * - 若回退未生效或统计错误，断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontAccountOverviewScopeFallbackModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证关系表缺失时账户总览按 parent_id 树回退统计。
     */
    public function test_account_overview_uses_parent_id_tree_for_customer_profile_when_family_tree_rows_are_missing(): void
    {
        $rootAgentId = 411700100;
        $directAgentId = $rootAgentId + 1;
        $directCustomerId = $rootAgentId + 2;
        $indirectCustomerId = $rootAgentId + 3;

        $this->deleteAgentDescendantRows([$rootAgentId, $directAgentId, $directCustomerId, $indirectCustomerId]);
        $this->deleteFlowRows([$rootAgentId, $directAgentId, $directCustomerId, $indirectCustomerId]);

        $this->insertUserInfo($rootAgentId, 'account-overview-root-agent', 1, 0, 1);
        $this->insertUserInfo($directAgentId, 'account-overview-direct-agent', 1, $rootAgentId, 1);
        $this->insertUserInfo($directCustomerId, 'account-overview-direct-customer', 2, $rootAgentId, 2);
        $this->insertUserInfo($indirectCustomerId, 'account-overview-indirect-customer', 2, $directAgentId, 1);
        $this->insertDepositRecord($directCustomerId, 'AODC-' . $rootAgentId, 100.25);
        $this->insertDepositRecord($indirectCustomerId, 'AOIC-' . $rootAgentId, 150.50);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/profile');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $data = $response->json('data');
        $this->assertSame(1, (int) $data['direct_agents']);
        $this->assertSame(1, (int) $data['direct_customers']);
        $this->assertSame(1, (int) $data['indirect_customers']);
        $this->assertSame(250.75, (float) $data['relation_amount']);
        $this->assertSame(1, (int) $data['customer_gender_profile']['male']['count']);
        $this->assertSame(1, (int) $data['customer_gender_profile']['female']['count']);
    }

    /**
     * 验证最终清单文档已记录账户总览范围回退（## 177）。
     */
    public function test_final_checklist_records_account_overview_scope_fallback(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString('## 177.', $checklist);
        $this->assertStringContainsString('AccountController::accountOverviewData', $checklist);
        $this->assertStringContainsString('AccountController::customerGenderProfile', $checklist);
        $this->assertStringContainsString('FrontAccountOverviewScopeFallbackModuleTest', $checklist);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId, int $gender): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-account-overview-' . $userId . '@example.test',
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
            'phone' => '1787000' . substr((string) $userId, -4),
            'gender' => $gender,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
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

    private function insertDepositRecord(int $userId, string $orderNo, float $amount): void
    {
        $now = time();

        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => 'account-overview-customer-' . $userId,
            'mt4_ticket' => $userId,
            'amount' => $amount,
            'actual_amount' => $amount,
            'exchange_rate' => 1,
            'channel_name' => 'manual-bank',
            'channel_order_no' => 'CH-' . $orderNo,
            'local_order_no' => $orderNo,
            'status' => '02',
            'payment_time' => date('Y-m-d H:i:s', $now),
            'remarks' => 'account overview scope fallback test',
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

    /**
     * @param array<int, int> $userIds
     */
    private function deleteFlowRows(array $userIds): void
    {
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('commission_records')->whereIn('agent_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
    }
}
