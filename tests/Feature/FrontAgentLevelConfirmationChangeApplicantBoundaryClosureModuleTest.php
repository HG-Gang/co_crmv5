<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 前端代理商级别变更确认-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法确认现代接口 /api/front/agents/level-confirmation/changes 的直系代理商级别变更。
 * - 验证普通客户账号无法确认旧接口 /user/proxy/confirmLevelChange 的直系代理商级别变更。
 * - 验证被拒绝后目标代理商的确认状态、级别与佣金比例均未被修改。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端代理商级别变更确认接口的权限边界回归测试，防止客户账号越权修改代理商级别。
 *
 * 入参例子：
 * - POST /api/front/agents/level-confirmation/changes
 *   请求体：{ "userId": 411980101, "agent_gId": 987198, "comm_prop": 999, "extra_val": 0 }
 * - POST /user/proxy/confirmLevelChange（旧接口，请求体同上）
 *
 * 返回值：
 * - 两个接口均返回 HTTP 200，业务 code 为 PERMISSION_DENIED。
 *
 * 异常或失败场景：
 * - 若客户账号能确认成功（返回非 PERMISSION_DENIED），或目标代理商数据被改动，测试失败。
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

class FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法通过现代接口确认直系代理商级别变更。
     *
     * 构造客户-代理商父子关系后请求 POST /api/front/agents/level-confirmation/changes，
     * 断言返回 PERMISSION_DENIED 且代理商确认数据未被修改。
     */
    public function test_customer_account_cannot_confirm_modern_direct_agent_level_change(): void
    {
        $customerId = 411980100;
        $childAgentId = 411980101;

        $levelId = $this->insertAgentLevel(987198, 'level-confirmation-change-modern');
        $this->insertUserInfo($customerId, 'level-confirmation-change-modern-customer', 2, 0);
        $this->insertUserInfo($childAgentId, 'level-confirmation-change-modern-agent', 1, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/agents/level-confirmation/changes', [
                'userId' => $childAgentId,
                'agent_gId' => $levelId,
                'comm_prop' => 999,
                'extra_val' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertAgentLevelConfirmationWasNotChanged($childAgentId, $levelId);
    }

    /**
     * 验证客户账号无法通过旧接口确认直系代理商级别变更。
     *
     * 请求 POST /user/proxy/confirmLevelChange，断言返回 PERMISSION_DENIED
     * 且代理商确认数据未被修改。
     */
    public function test_customer_account_cannot_confirm_legacy_direct_agent_level_change(): void
    {
        $customerId = 411980200;
        $childAgentId = 411980201;

        $levelId = $this->insertAgentLevel(987298, 'level-confirmation-change-legacy');
        $this->insertUserInfo($customerId, 'level-confirmation-change-legacy-customer', 2, 0);
        $this->insertUserInfo($childAgentId, 'level-confirmation-change-legacy-agent', 1, $customerId);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/confirmLevelChange', [
                'userId' => $childAgentId,
                'agent_gId' => $levelId,
                'comm_prop' => 999,
                'extra_val' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertAgentLevelConfirmationWasNotChanged($childAgentId, $levelId);
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 198 项、confirmLevelChange、level-confirmation/changes 及本测试类名。
     */
    public function test_final_checklist_records_agent_level_confirmation_change_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 198.', $checklist);
        $this->assertStringContainsString('confirmLevelChange', $checklist);
        $this->assertStringContainsString('level-confirmation/changes', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest', $checklist);
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
     * 插入一条带父子关系的测试用户数据。
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

        DB::table('agent_descendants')
            ->whereIn('agent_id', [$userId, $parentId])
            ->orWhereIn('descendant_id', [$userId, $parentId])
            ->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-level-confirmation-change-boundary-' . $userId . '@example.test',
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
            'phone' => '1789800' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'is_agent_confirmed' => 0,
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
}
