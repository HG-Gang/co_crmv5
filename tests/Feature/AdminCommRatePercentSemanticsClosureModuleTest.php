<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 19:50
 */

/**
 * AdminCommRatePercentSemanticsClosureModuleTest
 *
 * 文件功能：
 * - 锁定 user_infos.comm_rate 的百分数语义（0 到 100）：后台两处更新入口
 *   （AgentController::updateCommission 与旧代理编辑保存 LegacyAdminController:4644）都必须接受百分数值、
 *   拒绝超过 100 的越界值，并把整数百分数原样落库。
 * - 背景与证据：user_infos.comm_rate 是整数列，种子/生产数据为 65/85（百分数），佣金引擎
 *   CommissionService 按 /100 计算、建档按 min(comm_rate, max_commission=85) 继承、
 *   旧后台验证为 max:100——历史 max:1 离群点位于 AgentController::updateCommission（已修正，本测试锁定）
 *   与未路由的死控制器 UserController（已同步修正口径以绝后患）。2026-08-29 统一修正。
 *
 * 适用场景：
 * - 后台代理佣金比例更新入口的语义回归。
 *
 * 方法功能：
 * - test_update_agent_commission_accepts_percent_value_and_stores_it：85 被接受且原样落库为 85。
 * - test_update_agent_commission_rejects_value_over_100：150 被拒且原值不变。
 *
 * 返回值：
 * - 断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若两入口重新出现 0..1 分数口径（百分数被拒绝）或越界值被写入，测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCommRatePercentSemanticsClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_agent_commission_accepts_percent_value_and_stores_it(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 98727301;
        $this->createAgent($agentUserId, 20);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateAgentCommission', [
                'agent_id' => $agentUserId,
                'comm_rate' => 85,
            ]);

        $response->assertOk();

        $stored = (int) DB::table('user_infos')->where('user_id', $agentUserId)->value('comm_rate');
        $this->assertSame(85, $stored, '百分数 85 必须原样落库，不得按 0..1 分数口径截断或缩放。');
    }

    public function test_update_agent_commission_rejects_value_over_100(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentUserId = 98727302;
        $this->createAgent($agentUserId, 85);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post('/api/admin/updateAgentCommission', [
                'agent_id' => $agentUserId,
                'comm_rate' => 150,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $stored = (int) DB::table('user_infos')->where('user_id', $agentUserId)->value('comm_rate');
        $this->assertSame(85, $stored, '越界值不得写入 user_infos.comm_rate。');
    }

    /**
     * 创建可复用的超级管理员账号（id=1），与既有佣金/等级校验测试保持同一夹具口径。
     *
     * @return Admin 查询到的管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'comm-rate-percent-super',
                'email' => 'comm-rate-percent-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建指定佣金比例的代理夹具，字段口径与既有佣金校验测试一致。
     *
     * @param int $userId 业务代理用户 ID。
     * @param int $commRate 初始佣金比例（百分数）。
     * @return void
     */
    private function createAgent(int $userId, int $commRate): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'comm-rate-percent-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 1,
            'role_id' => 0,
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
            'user_name' => 'Percent Semantics Agent',
            'phone' => '188273' . substr((string) $userId, -5),
            'account_type' => 1,
            'parent_id' => 0,
            'level_id' => 1,
            'comm_rate' => $commRate,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
