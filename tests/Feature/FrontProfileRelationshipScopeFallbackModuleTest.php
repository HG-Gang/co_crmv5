<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:20
 */

/**
 * 前台关系路径作用域兜底闭环测试。
 *
 * 文件功能：
 * - 验证关系路径（ProfileController::relationShip）在 family_tree 与 agent_descendants
 *   行均缺失时，回退使用 user_infos.parent_id 组装代理链路。
 * - 验证权限清单文档记录了该兜底闭环。
 *
 * 适用场景：
 * - 前台个人资料“关系路径”的回归测试，防止后代表数据缺失时关系展示为空。
 *
 * 入参例子：
 * - 构造三级链：rootAgentId（account_type=1）-> subAgentId（account_type=1）-> customerId（account_type=2）。
 * - GET /api/front/profile/relationship-path?userId={customerId}（直接调用控制器）。
 *
 * 返回值：
 * - real 返回 "rootAgentId -> subAgentId -> customerId" 格式的关系路径字符串。
 *
 * 异常或失败场景：
 * - family_tree 与 agent_descendants 缺失时仍按 parent_id 关系正确组装路径。
 */

namespace Tests\Feature;

use App\Http\Controllers\Front\ProfileController;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontProfileRelationshipScopeFallbackModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证 family_tree 与 agent_descendants 缺失时关系路径按 parent_id 树组装。
    public function test_profile_relationship_path_uses_parent_id_tree_when_family_tree_and_descendant_rows_are_missing(): void
    {
        $rootAgentId = 411500100;
        $subAgentId = $rootAgentId + 1;
        $customerId = $rootAgentId + 2;

        $this->deleteAgentDescendantRows([$rootAgentId, $subAgentId, $customerId]);
        $this->insertUserInfo($rootAgentId, 'relationship-root-agent', 1, 0);
        $this->insertUserInfo($subAgentId, 'relationship-sub-agent', 1, $rootAgentId);
        $this->insertUserInfo($customerId, 'relationship-customer', 2, $subAgentId);

        $this->assertSame(0, DB::table('agent_descendants')->where('descendant_id', $customerId)->count());

        $request = Request::create('/api/front/profile/relationship-path', 'GET', ['userId' => $customerId]);
        $login = UserLogin::where('user_id', $rootAgentId)->firstOrFail();
        $request->setUserResolver(function () use ($login) {
            return $login;
        });
        $payload = app(ProfileController::class)->relationShip($request)->getData(true);

        $this->assertSame($rootAgentId . ' -> ' . $subAgentId . ' -> ' . $customerId, $payload['real']);
    }

    // 校验权限清单文档记录了关系路径 parent_id 兜底闭环。
    public function test_final_checklist_records_profile_relationship_parent_id_fallback(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString(
            '## 175. 2026-07-09',
            $checklist
        );
        $this->assertStringContainsString('ProfileController::relationshipIds', $checklist);
        $this->assertStringContainsString('FrontProfileRelationshipScopeFallbackModuleTest', $checklist);
        $this->assertStringContainsString('`user_infos.parent_id`', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'profile-relationship-' . $userId . '@example.test',
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
            'phone' => '1785000' . substr((string) $userId, -4),
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '999999,' . $userId,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 0.2 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
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
