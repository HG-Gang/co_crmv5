<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 前端代理商级别选项-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）无法读取现代接口 /api/front/agents/direct-level-options 的直系代理商级别选项。
 * - 验证普通客户账号无法读取旧接口 /user/proxy/getSubAgentsGrpIdList 的级别选项。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端代理商级别选项接口的权限边界回归测试，防止客户账号越权读取级别配置。
 *
 * 入参例子：
 * - GET /api/front/agents/direct-level-options（现代接口，JWT 用户态）
 * - POST /user/proxy/getSubAgentsGrpIdList（旧接口，body: { "agentGId": 0 }）
 *
 * 返回值：
 * - 两个接口均返回 HTTP 200，业务 code 为 PERMISSION_DENIED。
 *
 * 异常或失败场景：
 * - 若客户账号能读到级别选项，或返回码不是 PERMISSION_DENIED，测试失败。
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

class FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法读取现代接口的直系代理商级别选项。
     *
     * 构造 account_type=2 的客户账号后请求 GET /api/front/agents/direct-level-options，
     * 断言返回 PERMISSION_DENIED。
     */
    public function test_customer_account_cannot_read_modern_agent_level_options(): void
    {
        $customerId = 411900100;

        $this->insertAgentLevel(890100, 'level-option-boundary-modern');
        $this->insertUserInfo($customerId, 'level-option-modern-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/direct-level-options');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 验证客户账号无法读取旧接口的代理商级别选项。
     *
     * 请求 POST /user/proxy/getSubAgentsGrpIdList，断言返回 PERMISSION_DENIED。
     */
    public function test_customer_account_cannot_read_legacy_agent_level_options(): void
    {
        $customerId = 411900200;

        $this->insertAgentLevel(890200, 'level-option-boundary-legacy');
        $this->insertUserInfo($customerId, 'level-option-legacy-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/getSubAgentsGrpIdList', [
                'agentGId' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 190 项、getSubAgentsGrpIdList、agentList 及本测试类名。
     */
    public function test_final_checklist_records_agent_level_options_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 190.', $checklist);
        $this->assertStringContainsString('getSubAgentsGrpIdList', $checklist);
        $this->assertStringContainsString('agentList', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest', $checklist);
    }

    /**
     * 插入一条代理商级别配置。
     *
     * @param int $levelCode 级别编码，用于去重清理。
     * @param string $name 级别名称。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertAgentLevel(int $levelCode, string $name): void
    {
        $now = time();

        DB::table('agent_levels')->where('level_code', $levelCode)->delete();
        DB::table('agent_levels')->insert([
            'level_code' => $levelCode,
            'name' => $name,
            'max_commission' => 100,
            'min_commission' => 0,
            'user_commission' => 10,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 向 user_logins 与 user_infos 表插入一条测试用户数据。
     *
     * @param int $userId 用户 ID。
     * @param string $userName 用户名。
     * @param int $accountType 账号类型（1=代理商，2=客户）。
     * @return void 无返回值，仅写入数据库。
     */
    private function insertUserInfo(int $userId, string $userName, int $accountType): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-agent-level-boundary-' . $userId . '@example.test',
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
            'phone' => '1789000' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => '',
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
}
