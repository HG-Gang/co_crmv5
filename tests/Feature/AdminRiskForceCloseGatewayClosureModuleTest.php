<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

declare(strict_types=1);

/**
 * 后台风险强平接口对接外部网关（RiskForceCloseGateway）的功能测试。
 *
 * 文件功能：
 * - 验证网关拒绝强平时接口 fail-closed 返回 OPERATION_NOT_ALLOWED 且不改动持仓、不写操作日志。
 * - 验证网关强平成功时返回供应商参考号并写入操作审计日志。
 * - 验证 Mt4ManagerService 暴露 closeOrder 强平命令。
 *
 * 适用场景：
 * - 风控后台手动强平用户持仓，强平结果依赖外部 MT4 网关返回。
 *
 * 入参例子：
 * - POST /api/admin/riskForceClose/{tradeId}（tradeId 为 mt4_trades 主键）。
 *
 * 返回值：
 * - 成功返回 code=ResponseCode::SUCCESS，data 含 ticket、login、provider_reference。
 * - 网关拒绝返回 code=ResponseCode::OPERATION_NOT_ALLOWED。
 *
 * 异常或失败场景：
 * - 网关返回 rejected 时持仓保持开仓状态、不产生 risk_force_close 操作日志。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\RiskForceCloseGateway;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Risk\RiskForceCloseResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRiskForceCloseGatewayClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证网关拒绝强平时接口失败关闭、持仓不变且无操作日志。
    public function test_force_close_fails_closed_when_gateway_rejects_and_does_not_mutate_open_trade(): void
    {
        $actor = $this->ensureSuperAdmin();
        $login = 982811;
        $ticket = 9929611;
        $tradeId = $this->createOpenTrade($login, $ticket);

        $this->app->instance(RiskForceCloseGateway::class, new class implements RiskForceCloseGateway {
            public function close(int $login, int $ticket, string $comment): RiskForceCloseResult
            {
                return RiskForceCloseResult::rejected('provider_rejected');
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/riskForceClose/' . $tradeId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('mt4_trades', [
            'id' => $tradeId,
            'ticket' => $ticket,
            'login' => $login,
            'close_time' => 0,
        ]);
        $this->assertSame(
            0,
            DB::table('operation_logs')
                ->where('target_user_id', $login)
                ->where('content', 'like', '%risk_force_close%')
                ->count()
        );
    }

    // 验证网关强平成功时返回供应商参考号并写入操作审计日志。
    public function test_force_close_records_audit_and_returns_provider_reference_on_success(): void
    {
        $actor = $this->ensureSuperAdmin();
        $login = 982812;
        $ticket = 9929612;
        $tradeId = $this->createOpenTrade($login, $ticket);

        $this->app->instance(RiskForceCloseGateway::class, new class implements RiskForceCloseGateway {
            public function close(int $login, int $ticket, string $comment): RiskForceCloseResult
            {
                return RiskForceCloseResult::closed('88001');
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/riskForceClose/' . $tradeId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.ticket', $ticket)
            ->assertJsonPath('data.login', $login)
            ->assertJsonPath('data.provider_reference', '88001');

        $this->assertGreaterThan(
            0,
            DB::table('operation_logs')
                ->where('admin_id', (int) $actor->getKey())
                ->where('target_user_id', $login)
                ->where('content', 'like', '%risk_force_close%')
                ->where('content', 'like', '%' . $ticket . '%')
                ->count()
        );
    }

    // 校验 Mt4ManagerService 源码包含 closeOrder 方法与 ORDER_CLOSE 命令。
    public function test_mt4_manager_exposes_order_close_command(): void
    {
        $source = file_get_contents(app_path('Services/Mt4ManagerService.php')) ?: '';
        $this->assertStringContainsString('function closeOrder', $source);
        $this->assertStringContainsString('ORDER_CLOSE', $source);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-risk-force-close-gateway-super',
                'email' => 'admin-risk-force-close-gateway-super@example.test',
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
                'symbol' => 'EURUSD',
                'cmd' => 0,
                'volume' => 1.00,
                'open_price' => 1.1000,
                'close_price' => null,
                'commission' => 0,
                'swaps' => 0,
                'profit' => -12.50,
                'open_time' => $now - 3600,
                'close_time' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('mt4_trades')->where('ticket', $ticket)->value('id');
    }
}
