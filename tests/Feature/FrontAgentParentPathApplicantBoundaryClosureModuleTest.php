<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 前端代理商父级路径-申请人边界封闭模块测试。
 *
 * 文件功能：
 * - 验证普通客户账号（account_type=2）查询自身父级路径时，现代接口 /api/front/agents/hierarchy-path 返回空 path 与空 tree。
 * - 验证普通客户账号查询自身父级路径时，旧接口 /user/proxy/parentPath 返回空 path 与空 tree。
 * - 验证最终权限检查清单文档记录了该边界封闭模块。
 *
 * 适用场景：
 * - 前端代理商父级路径接口的权限边界回归测试，客户账号不应获得代理商层级路径。
 *
 * 入参例子：
 * - GET /api/front/agents/hierarchy-path?user_id={客户ID}
 * - POST /user/proxy/parentPath（body: { "userId": {客户ID} }）
 *
 * 返回值：
 * - 两个接口均返回 HTTP 200，data.path 为空字符串、data.tree 为空数组。
 *
 * 异常或失败场景：
 * - 若客户账号能查到非空路径或层级树，测试失败。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontAgentParentPathApplicantBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证客户账号无法通过现代接口读取自身代理商父级路径。
     *
     * 请求 GET /api/front/agents/hierarchy-path，断言 data.path 为空、data.tree 为空数组。
     */
    public function test_customer_account_cannot_read_modern_agent_parent_path_for_self(): void
    {
        $customerId = 411960100;

        $this->insertUserInfo($customerId, 'parent-path-modern-boundary-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/agents/hierarchy-path?user_id=' . $customerId);

        $response->assertOk()
            ->assertJsonPath('data.path', '')
            ->assertJsonPath('data.tree', []);
    }

    /**
     * 验证客户账号无法通过旧接口读取自身代理商父级路径。
     *
     * 请求 POST /user/proxy/parentPath，断言 data.path 为空、data.tree 为空数组。
     */
    public function test_customer_account_cannot_read_legacy_agent_parent_path_for_self(): void
    {
        $customerId = 411960200;

        $this->insertUserInfo($customerId, 'parent-path-legacy-boundary-customer', 2);

        $login = UserLogin::where('user_id', $customerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/proxy/parentPath', [
                'userId' => $customerId,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.path', '')
            ->assertJsonPath('data.tree', []);
    }

    /**
     * 验证最终权限检查清单记录了本次边界封闭模块。
     *
     * 断言清单包含第 196 项、getParentPath、hierarchy-path 及本测试类名。
     */
    public function test_final_checklist_records_agent_parent_path_applicant_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 196.', $checklist);
        $this->assertStringContainsString('getParentPath', $checklist);
        $this->assertStringContainsString('account_type=1', $checklist);
        $this->assertStringContainsString('hierarchy-path', $checklist);
        $this->assertStringContainsString('FrontAgentParentPathApplicantBoundaryClosureModuleTest', $checklist);
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
            'email' => 'front-parent-path-boundary-' . $userId . '@example.test',
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
            'phone' => '1789600' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
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
