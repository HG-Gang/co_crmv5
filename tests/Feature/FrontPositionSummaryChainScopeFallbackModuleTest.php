<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:21
 */

/**
 * 前台持仓汇总链路作用域兜底闭环测试。
 *
 * 文件功能：
 * - 验证持仓汇总链路（/api/front/positions/summary 的 data.chain）在 agent_descendants
 *   行缺失时，回退使用 user_infos.parent_id 构建代理链路。
 * - 验证权限清单文档记录了该兜底闭环。
 *
 * 适用场景：
 * - 前台持仓汇总“代理链路展示”的回归测试，防止后代表缺失时链路断裂。
 *
 * 入参例子：
 * - 登录账号：rootAgentId（account_type=1）。
 * - 构造两级代理：levelOneAgentId、levelTwoAgentId（parent_id 逐级挂接）。
 * - 接口参数：target_id={levelTwoAgentId}&per_page=20。
 *
 * 返回值：
 * - code 为 SUCCESS；data.list.data 含目标代理，data.chain 按 parent_id 顺序返回
 *   根代理、一级代理、二级代理的用户名。
 *
 * 异常或失败场景：
 * - agent_descendants 缺失时链路仍按 parent_id 关系正确组装。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontPositionSummaryChainScopeFallbackModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证 agent_descendants 缺失时持仓汇总链路按 parent_id 树组装。
    public function test_position_summary_chain_uses_parent_id_tree_when_family_tree_rows_are_missing(): void
    {
        $rootAgentId = 411810100;
        $levelOneAgentId = $rootAgentId + 1;
        $levelTwoAgentId = $rootAgentId + 2;

        $userIds = [$rootAgentId, $levelOneAgentId, $levelTwoAgentId];
        $this->deleteAgentDescendantRows($userIds);

        $this->insertUserInfo($rootAgentId, 'position-chain-root-agent', 1, 0);
        $this->insertUserInfo($levelOneAgentId, 'position-chain-level-one', 1, $rootAgentId);
        $this->insertUserInfo($levelTwoAgentId, 'position-chain-level-two', 1, $levelOneAgentId);
        DB::table('user_infos')->where('user_id', $levelTwoAgentId)->update([
            'family_tree' => '999999,' . $levelTwoAgentId,
        ]);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/positions/summary?target_id=' . $levelTwoAgentId . '&per_page=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame($levelTwoAgentId, (int) $response->json('data.list.data.0.user_id'));

        $chain = $response->json('data.chain');
        $this->assertSame(
            [$rootAgentId, $levelOneAgentId, $levelTwoAgentId],
            array_map('intval', array_column($chain, 'user_id'))
        );
        $this->assertSame('position-chain-root-agent', $chain[0]['user_name']);
        $this->assertSame('position-chain-level-one', $chain[1]['user_name']);
        $this->assertSame('position-chain-level-two', $chain[2]['user_name']);
    }

    // 校验权限清单文档记录了持仓汇总链路作用域兜底闭环。
    public function test_final_checklist_records_position_summary_chain_scope_fallback(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString('## 181.', $checklist);
        $this->assertStringContainsString('PositionController::summaryChain', $checklist);
        $this->assertStringContainsString('FrontPositionSummaryChainScopeFallbackModuleTest', $checklist);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds', $checklist);
        $this->assertStringContainsString('user_infos.parent_id', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-position-chain-' . $userId . '@example.test',
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
            'phone' => '1788100' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : (string) $userId,
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
