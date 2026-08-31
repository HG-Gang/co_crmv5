<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 00:59
 */

/**
 * FrontAgentTargetUserIdValidationClosureModuleTest
 *
 * 文件功能：
 * - 验证代理可见用户 ID 严格校验闭环：登录历史拒绝部分数字目标 ID，现代用户详情路由在控制器前拒绝非数字路由参数。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
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

/** 代理可见用户 ID 严格校验闭环测试。 */
class FrontAgentTargetUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_agent_login_history_rejects_partially_numeric_target_ids(): void
    {
        $agentId = 412150100;
        $targetId = 412150200;
        $this->insertUser($agentId, 1, 0, 'strict-target-agent');
        $this->insertUser($targetId, 2, $agentId, 'strict-target-customer');

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/login-history?user_id=' . $targetId . 'abc')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_modern_user_detail_route_rejects_non_numeric_route_id_before_controller(): void
    {
        $agentId = 412150300;
        $this->insertUser($agentId, 1, 0, 'strict-route-agent');

        $login = UserLogin::where('user_id', $agentId)->firstOrFail();
        $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/users/not-a-number')
            ->assertNotFound();
    }

    private function insertUser(int $userId, int $accountType, int $parentId, string $name): void
    {
        $now = time();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $name . '@example.test',
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
            'user_name' => $name,
            'phone' => '17815' . substr((string) $userId, -6),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : (string) $userId,
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
