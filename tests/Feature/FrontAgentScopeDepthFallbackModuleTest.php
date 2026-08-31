<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:21
 */

/**
 * 前端代理商数据范围深度-回退模块测试。
 *
 * 文件功能：
 * - 验证 agent_descendants 表缺少数据时，现代接口 /api/front/agents/direct 回退使用 user_infos.parent_id 树计算各层级代理商深度。
 * - 验证返回列表中各级代理商的 depth 字段为 1、2、3 递增。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止代理商列表 depth 计算在层级数据缺失时回退失败的回归测试。
 *
 * 入参例子：
 * - GET /api/front/agents/direct?per_page=20
 *
 * 返回值：
 * - HTTP 200，code 为 SUCCESS；data.list.data 中各代理商的 depth 按层级递增。
 *
 * 异常或失败场景：
 * - 若 depth 字段缺失或层级深度计算错误，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontAgentScopeDepthFallbackModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证层级表缺失时代理商列表深度回退使用 parent_id 树。
     *
     * 构造四级代理商链并清空 agent_descendants 后请求代理商列表，
     * 断言各级 depth 分别为 1、2、3。
     */
    public function test_agent_list_depth_uses_parent_id_tree_when_family_tree_rows_are_missing(): void
    {
        $rootAgentId = 411800100;
        $levelOneAgentId = $rootAgentId + 1;
        $levelTwoAgentId = $rootAgentId + 2;
        $levelThreeAgentId = $rootAgentId + 3;

        $userIds = [$rootAgentId, $levelOneAgentId, $levelTwoAgentId, $levelThreeAgentId];
        $this->deleteAgentDescendantRows($userIds);

        $this->insertUserInfo($rootAgentId, 'agent-depth-root-agent', 1, 0);
        $this->insertUserInfo($levelOneAgentId, 'agent-depth-level-one', 1, $rootAgentId);
        $this->insertUserInfo($levelTwoAgentId, 'agent-depth-level-two', 1, $levelOneAgentId);
        $this->insertUserInfo($levelThreeAgentId, 'agent-depth-level-three', 1, $levelTwoAgentId);
        DB::table('user_infos')->whereIn('user_id', [$levelTwoAgentId, $levelThreeAgentId])->update([
            'family_tree' => DB::raw("CONCAT({$rootAgentId}, ',', user_id)"),
        ]);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/direct?per_page=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = collect(data_get($response->json(), 'data.list.data', []))
            ->keyBy(function (array $row) {
                return (int) $row['user_id'];
            });

        $this->assertSame(1, (int) $rows[$levelOneAgentId]['depth']);
        $this->assertSame(2, (int) $rows[$levelTwoAgentId]['depth']);
        $this->assertSame(3, (int) $rows[$levelThreeAgentId]['depth']);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 180 项、AgentController::scopeDepth 及本测试类名。
     */
    public function test_final_checklist_records_agent_scope_depth_fallback(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString('## 180.', $checklist);
        $this->assertStringContainsString('AgentController::scopeDepth', $checklist);
        $this->assertStringContainsString('FrontAgentScopeDepthFallbackModuleTest', $checklist);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds', $checklist);
        $this->assertStringContainsString('user_infos.parent_id', $checklist);
    }

    /**
     * 插入带父子关系的测试用户数据，代理商默认级别 2、佣金比例 0.2。
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
            'email' => 'front-agent-depth-' . $userId . '@example.test',
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
            'phone' => '1788000' . substr((string) $userId, -4),
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
     * 清理指定用户相关的 agent_descendants 测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
     */
    private function deleteAgentDescendantRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->whereIn('agent_id', $userIds)
            ->orWhereIn('descendant_id', $userIds)
            ->delete();
    }
}
