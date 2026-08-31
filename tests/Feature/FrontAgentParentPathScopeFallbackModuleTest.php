<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:21
 */

/**
 * 前端代理商父级路径-数据范围回退模块测试。
 *
 * 文件功能：
 * - 验证 agent_descendants 表缺少数据时，旧接口 /user/proxy/parentPath 回退使用 user_infos.parent_id 树构建层级路径。
 * - 验证返回的 tree 与 path 包含完整祖先链。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止父级路径接口在层级数据缺失时无法工作的回归测试。
 *
 * 入参例子：
 * - POST /user/proxy/parentPath
 *   请求体：{ "user_id": 411820102, "event_name": "returnPreLevel" }
 *
 * 返回值：
 * - HTTP 200，code=200；data.tree 为按根到目标排序的节点 HTML 数组，data.path 包含各级用户名。
 *
 * 异常或失败场景：
 * - 若树节点顺序或路径内容与预期祖先链不符，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontAgentParentPathScopeFallbackModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证层级表缺失时父级路径回退使用 parent_id 树。
     *
     * 构造三级代理商链并清空 agent_descendants 后请求 parentPath，
     * 断言 tree 顺序与 path 内容均为根到目标的完整链。
     */
    public function test_proxy_parent_path_uses_parent_id_tree_when_family_tree_rows_are_missing(): void
    {
        $rootAgentId = 411820100;
        $levelOneAgentId = $rootAgentId + 1;
        $levelTwoAgentId = $rootAgentId + 2;

        $userIds = [$rootAgentId, $levelOneAgentId, $levelTwoAgentId];
        $this->deleteAgentDescendantRows($userIds);

        $this->insertUserInfo($rootAgentId, 'parent-path-root-agent', 1, 0);
        $this->insertUserInfo($levelOneAgentId, 'parent-path-level-one', 1, $rootAgentId);
        $this->insertUserInfo($levelTwoAgentId, 'parent-path-level-two', 1, $levelOneAgentId);
        DB::table('user_infos')->where('user_id', $levelTwoAgentId)->update([
            'family_tree' => '999999,' . $levelTwoAgentId,
        ]);

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count());

        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/parentPath', [
                'user_id' => $levelTwoAgentId,
                'event_name' => 'returnPreLevel',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', 200);

        $tree = $response->json('data.tree');
        $this->assertSame(
            [$rootAgentId, $levelOneAgentId, $levelTwoAgentId],
            array_map([$this, 'htmlUserId'], $tree)
        );

        $path = $response->json('data.path');
        $this->assertStringContainsString('parent-path-root-agent[' . $rootAgentId . ']', $path);
        $this->assertStringContainsString('parent-path-level-one[' . $levelOneAgentId . ']', $path);
        $this->assertStringContainsString('parent-path-level-two[' . $levelTwoAgentId . ']', $path);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 182 项、AgentController::getParentPath 及本测试类名。
     */
    public function test_final_checklist_records_agent_parent_path_scope_fallback(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString('## 182.', $checklist);
        $this->assertStringContainsString('AgentController::getParentPath', $checklist);
        $this->assertStringContainsString('FrontAgentParentPathScopeFallbackModuleTest', $checklist);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds', $checklist);
        $this->assertStringContainsString('user_infos.parent_id', $checklist);
    }

    /**
     * 从节点 HTML 中解析 data-user_id 属性值。
     *
     * @param string $html 节点 HTML 片段。
     * @return int 解析出的用户 ID；解析失败时返回 0。
     */
    private function htmlUserId(string $html): int
    {
        preg_match('/data-user_id="(\d+)"/', $html, $matches);

        return (int) ($matches[1] ?? 0);
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
            'email' => 'front-parent-path-' . $userId . '@example.test',
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
            'phone' => '1788200' . substr((string) $userId, -4),
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
