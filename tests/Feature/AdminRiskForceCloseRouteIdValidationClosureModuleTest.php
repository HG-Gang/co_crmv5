<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台风险强平接口路由 id 严格校验的功能测试。
 *
 * 文件功能：
 * - 验证强平路由参数 id 传入非严格数字时接口返回校验失败。
 * - 验证校验失败时不返回强平信号数据、持仓保持开仓状态。
 *
 * 适用场景：
 * - 风控后台强平操作，防止非法路由 id 触发误强平。
 *
 * 入参例子：
 * - POST /api/admin/riskForceClose/{tradeId}abc（tradeId 为 mt4_trades 主键）。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED，且响应不含 ticket/login。
 *
 * 异常或失败场景：
 * - 路由 id 非严格整数时接口拒绝执行并保持持仓记录不变。
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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRiskForceCloseRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证风险强平时非严格路由 id 被拒绝且不返回强平信号、持仓不变。
    public function test_risk_force_close_route_rejects_non_strict_route_id_without_returning_trade_signal(): void
    {
        $actor = $this->ensureSuperAdmin();
        $login = 982801;
        $ticket = 9929601;
        $tradeId = $this->createOpenTrade($login, $ticket);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/riskForceClose/' . $tradeId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonMissing([
                'ticket' => $ticket,
                'login' => $login,
            ]);

        $this->assertDatabaseHas('mt4_trades', [
            'id' => $tradeId,
            'ticket' => $ticket,
            'login' => $login,
            'cmd' => 0,
            'close_time' => 0,
        ]);
    }

    // 校验最终检查清单文档记录了风险强平路由 id 校验边界。
    public function test_final_checklist_records_risk_force_close_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 296.', $checklist);
        $this->assertStringContainsString('RiskController::forceClose', $checklist);
        $this->assertStringContainsString('/api/admin/riskForceClose/{id}', $checklist);
        $this->assertStringContainsString('mt4_trades.id', $checklist);
        $this->assertStringContainsString('AdminRiskForceCloseRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-risk-force-close-route-id-super',
                'email' => 'admin-risk-force-close-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createOpenTrade(int $login, int $ticket): int
    {
        $now = time();

        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $login,
                'symbol' => 'RISKID',
                'cmd' => 0,
                'volume' => 1.25,
                'open_price' => 1888.12,
                'close_price' => null,
                'commission' => -1.50,
                'swaps' => 0,
                'profit' => 42.80,
                'open_time' => $now - 1800,
                'close_time' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('mt4_trades')->where('ticket', $ticket)->value('id');
    }
}
