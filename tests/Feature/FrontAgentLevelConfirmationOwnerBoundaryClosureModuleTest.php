<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 前端代理商级别确认-归属者边界封闭模块测试。
 *
 * 文件功能：
 * - 验证现代与旧接口的级别确认列表只返回当前代理商自身分支下的数据。
 * - 验证通过 userId 参数伪装查询其他分支代理商时返回空列表。
 * - 验证级别变更确认只能作用于自身直系代理商，对其他分支的变更请求返回 PERMISSION_DENIED 且不修改数据。
 * - 验证对自身直系代理商的合法变更可以成功并落库。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端代理商级别确认（列表与变更）接口的归属权边界回归测试。
 *
 * 入参例子：
 * - GET /api/front/agents/level-confirmation?userId={其他分支代理商ID}&per_page=20
 * - POST /api/front/agents/level-confirmation/changes
 *   请求体：{ "userId": 412490103, "agent_gId": 987349, "comm_prop": 999, "extra_val": 0 }
 * - POST /user/proxy/proxyConfirmSearch（旧接口，body: { "userId": ..., "limit": 20 }）
 * - POST /user/proxy/confirmLevelChange（旧接口）
 *
 * 返回值：
 * - 合法查询返回 SUCCESS 且仅含自身分支数据；伪装查询返回 SUCCESS 但列表为空。
 * - 越权变更返回 PERMISSION_DENIED；自身直系变更返回 SUCCESS。
 *
 * 异常或失败场景：
 * - 若自身分支外的数据被返回、越权变更生效或合法变更失败，测试失败。
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

class FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代与旧接口的级别确认列表拒绝其他分支代理商过滤。
     *
     * 构造查看者代理商及其直系代理商、其他根代理商及其直系代理商，
     * 断言列表只含自身直系数据，带 userId 伪装参数时返回空列表。
     */
    public function test_modern_and_legacy_level_confirmation_list_reject_other_branch_agent_filter(): void
    {
        $viewerAgentId = 412490100;
        $ownDirectAgentId = 412490101;
        $otherRootAgentId = 412490102;
        $otherDirectAgentId = 412490103;

        $this->deleteFixtureRows([$viewerAgentId, $ownDirectAgentId, $otherRootAgentId, $otherDirectAgentId]);
        $levelId = $this->insertAgentLevel(987249, 'level-confirmation-owner-list');
        $this->insertUserInfo($viewerAgentId, 'level-confirmation-owner-viewer', 1, 0, $levelId, 1, 0.2);
        $this->insertUserInfo($ownDirectAgentId, 'level-confirmation-owner-own-agent', 1, $viewerAgentId, 0, 0, 0);
        $this->insertUserInfo($otherRootAgentId, 'level-confirmation-owner-other-root', 1, 0, $levelId, 1, 0.2);
        $this->insertUserInfo($otherDirectAgentId, 'level-confirmation-owner-other-agent', 1, $otherRootAgentId, 0, 0, 0);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $modernVisible = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/level-confirmation?per_page=20');

        $modernVisible->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString('level-confirmation-owner-own-agent', $modernVisible->getContent());
        $this->assertStringNotContainsString('level-confirmation-owner-other-agent', $modernVisible->getContent());

        $modernSpoofed = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/level-confirmation?userId=' . $otherDirectAgentId . '&per_page=20');

        $modernSpoofed->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $modernSpoofed->json('data.list.data'));
        $this->assertStringNotContainsString('level-confirmation-owner-other-agent', $modernSpoofed->getContent());

        $legacyVisible = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/proxyConfirmSearch', ['limit' => 20]);

        $legacyVisible->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString('level-confirmation-owner-own-agent', $legacyVisible->getContent());
        $this->assertStringNotContainsString('level-confirmation-owner-other-agent', $legacyVisible->getContent());

        $legacySpoofed = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/proxyConfirmSearch', [
                'userId' => $otherDirectAgentId,
                'limit' => 20,
            ]);

        $legacySpoofed->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $legacySpoofed->json('data.list.data'));
        $this->assertStringNotContainsString('level-confirmation-owner-other-agent', $legacySpoofed->getContent());
    }

    /**
     * 验证级别变更确认拒绝其他分支代理商且不产生数据更新。
     *
     * 对自身直系代理商的变更返回 SUCCESS 并更新确认状态，
     * 对其他分支代理商的变更（现代与旧接口）返回 PERMISSION_DENIED 且数据不变。
     */
    public function test_modern_and_legacy_level_confirmation_change_reject_other_branch_without_update(): void
    {
        $viewerAgentId = 412490200;
        $ownDirectAgentId = 412490201;
        $otherRootAgentId = 412490202;
        $otherDirectAgentId = 412490203;

        $this->deleteFixtureRows([$viewerAgentId, $ownDirectAgentId, $otherRootAgentId, $otherDirectAgentId]);
        $levelId = $this->insertAgentLevel(987349, 'level-confirmation-owner-change');
        $this->insertUserInfo($viewerAgentId, 'level-change-owner-viewer', 1, 0, $levelId, 1, 0.2);
        $this->insertUserInfo($ownDirectAgentId, 'level-change-owner-own-agent', 1, $viewerAgentId, 0, 0, 0);
        $this->insertUserInfo($otherRootAgentId, 'level-change-owner-other-root', 1, 0, $levelId, 1, 0.2);
        $this->insertUserInfo($otherDirectAgentId, 'level-change-owner-other-agent', 1, $otherRootAgentId, 0, 0, 0);

        $login = UserLogin::where('user_id', $viewerAgentId)->firstOrFail();
        $modernRejected = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/level-confirmation/changes', [
                'userId' => $otherDirectAgentId,
                'agent_gId' => $levelId,
                'comm_prop' => 999,
                'extra_val' => 0,
            ]);

        $modernRejected->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertAgentLevelConfirmationWasNotChanged($otherDirectAgentId, $levelId);

        $legacyRejected = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/confirmLevelChange', [
                'userId' => $otherDirectAgentId,
                'agent_gId' => $levelId,
                'comm_prop' => 999,
                'extra_val' => 0,
            ]);

        $legacyRejected->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertAgentLevelConfirmationWasNotChanged($otherDirectAgentId, $levelId);

        $accepted = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/level-confirmation/changes', [
                'userId' => $ownDirectAgentId,
                'agent_gId' => $levelId,
                'comm_prop' => 999,
                'extra_val' => 1,
            ]);

        $accepted->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertAgentLevelConfirmationWasChanged($ownDirectAgentId, $levelId, 13.0);
    }

    /**
     * 验证最终权限检查清单记录了本次归属者边界封闭模块。
     *
     * 断言清单包含第 249 项、confirmLevel、proxyConfirmSearch、confirmLevelChange
     * 及相关接口路径和本测试类名。
     */
    public function test_final_checklist_records_agent_level_confirmation_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 249.', $checklist);
        $this->assertStringContainsString('AgentController::confirmLevel', $checklist);
        $this->assertStringContainsString('AgentController::proxyConfirmSearch', $checklist);
        $this->assertStringContainsString('AgentController::confirmLevelChange', $checklist);
        $this->assertStringContainsString('/api/front/agents/level-confirmation', $checklist);
        $this->assertStringContainsString('/api/front/agents/level-confirmation/changes', $checklist);
        $this->assertStringContainsString('user/proxy/proxyConfirmSearch', $checklist);
        $this->assertStringContainsString('user/proxy/confirmLevelChange', $checklist);
        $this->assertStringContainsString('FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入一条代理商级别配置并返回级别 ID。
     *
     * @param int $levelCode 级别编码，用于去重清理。
     * @param string $name 级别名称。
     * @return int 新插入级别的自增 ID。
     */
    private function insertAgentLevel(int $levelCode, string $name): int
    {
        $now = time();

        DB::table('agent_levels')->where('level_code', $levelCode)->delete();

        return (int) DB::table('agent_levels')->insertGetId([
            'level_code' => $levelCode,
            'name' => $name,
            'max_commission' => 100,
            'min_commission' => 0,
            'user_commission' => 12,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 插入带父子关系、级别与确认状态的测试用户数据。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @param int $parentId 上级用户 ID（0 表示无上级）。
     * @param int $levelId 代理商级别 ID。
     * @param int $isAgentConfirmed 是否已确认代理商级别（0/1）。
     * @param float $commRate 佣金比例。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(
        int $userId,
        string $userName,
        int $accountType,
        int $parentId,
        int $levelId,
        int $isAgentConfirmed,
        float $commRate
    ): void {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-agent-level-owner-' . $userId . '@example.test',
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
            'phone' => '1782490' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $this->familyTreeFor($userId, $parentId) : '',
            'group_id' => 0,
            'level_id' => $levelId,
            'comm_rate' => $commRate,
            'auth_status' => 1,
            'is_agent_confirmed' => $isAgentConfirmed,
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
     * 根据上级用户 ID 拼接包含祖先链的 family_tree 字符串。
     *
     * @param int $userId 当前用户 ID。
     * @param int $parentId 上级用户 ID。
     * @return string 形如 "祖先ID,...,上级ID,当前ID" 的逗号分隔字符串。
     */
    private function familyTreeFor(int $userId, int $parentId): string
    {
        $parentTree = (string) DB::table('user_infos')->where('user_id', $parentId)->value('family_tree');
        $ids = array_values(array_filter(array_map('intval', explode(',', $parentTree))));
        $ids[] = $parentId;
        $ids[] = $userId;

        return implode(',', array_values(array_unique($ids)));
    }

    /**
     * 清理指定用户相关的 agent_descendants、user_infos、user_logins 测试数据。
     *
     * @param array<int, int> $userIds 待清理的用户 ID 列表。
     * @return void 无返回值。
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }

    /**
     * 断言目标代理商的级别确认状态、级别与佣金比例均未被修改。
     *
     * @param int $userId 目标代理商用户 ID。
     * @param int $levelId 请求中试图设置的级别 ID。
     * @return void 断言失败时抛出 AssertionFailedError。
     */
    private function assertAgentLevelConfirmationWasNotChanged(int $userId, int $levelId): void
    {
        $userInfo = DB::table('user_infos')->where('user_id', $userId)->first();

        $this->assertNotNull($userInfo);
        $this->assertSame(0, (int) $userInfo->is_agent_confirmed);
        $this->assertNotSame($levelId, (int) $userInfo->level_id);
        $this->assertSame(0.0, (float) $userInfo->comm_rate);
    }

    /**
     * 断言目标代理商的级别确认已按预期生效。
     *
     * @param int $userId 目标代理商用户 ID。
     * @param int $levelId 期望的级别 ID。
     * @param float $expectedRate 期望的佣金比例。
     * @return void 断言失败时抛出 AssertionFailedError。
     */
    private function assertAgentLevelConfirmationWasChanged(int $userId, int $levelId, float $expectedRate): void
    {
        $userInfo = DB::table('user_infos')->where('user_id', $userId)->first();

        $this->assertNotNull($userInfo);
        $this->assertSame(1, (int) $userInfo->is_agent_confirmed);
        $this->assertSame($levelId, (int) $userInfo->level_id);
        $this->assertSame($expectedRate, (float) $userInfo->comm_rate);
    }
}
