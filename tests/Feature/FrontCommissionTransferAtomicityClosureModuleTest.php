<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/21
 * Time: 05:03
 */

/**
 * FrontCommissionTransferAtomicityClosureModuleTest
 *
 * 文件功能：
 * - 验证代理返佣转账金额精度与并发余额保护：拒绝科学计数法和超过两位小数，Saga 先取外部资金快照再锁定双方本地余额行。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Tests\TestCase;

/** 代理返佣转账金额精度和并发余额保护闭环测试。 */
class FrontCommissionTransferAtomicityClosureModuleTest extends TestCase
{
    public function test_transfer_rejects_scientific_notation_and_more_than_two_decimals(): void
    {
        foreach (['1e3', '0.001'] as $amount) {
            $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->postJson('/api/front/commissions/transfers', [
                    'sub_agent_id' => 1,
                    'amount' => $amount,
                ])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    public function test_transfer_saga_uses_external_funding_snapshot_then_locks_both_local_balance_rows(): void
    {
        $service = (string) file_get_contents(app_path('Services/CommissionTransfer/CommissionTransferService.php'));
        $finalizer = (string) file_get_contents(app_path('Services/CommissionTransfer/CommissionTransferLedgerFinalizer.php'));

        $this->assertStringContainsString('$this->fundingGateway->withdraw(', $service);
        $this->assertStringContainsString('$this->fundingGateway->deposit(', $service);
        $this->assertSame(2, substr_count($service, '$this->snapshotGateway->snapshot('));
        $this->assertStringContainsString('$this->ledgerFinalizer->finalizeCompleted(', $service);

        $withdraw = strpos($service, '$this->fundingGateway->withdraw(');
        $deposit = strpos($service, '$this->fundingGateway->deposit(');
        $sourceSnapshot = strpos($service, '$sourceSnapshot = $this->snapshotGateway->snapshot(');
        $targetSnapshot = strpos($service, '$targetSnapshot = $this->snapshotGateway->snapshot(');
        $finalize = strpos($service, '$this->finalize($claim);');
        $this->assertNotFalse($withdraw);
        $this->assertNotFalse($deposit);
        $this->assertNotFalse($sourceSnapshot);
        $this->assertNotFalse($targetSnapshot);
        $this->assertNotFalse($finalize);
        $this->assertTrue(
            $withdraw < $deposit
                && $deposit < $sourceSnapshot
                && $sourceSnapshot < $targetSnapshot
                && $targetSnapshot < $finalize,
            'The saga must finish both external funding commands, then capture both snapshots, then finalize local state.'
        );

        $this->assertStringContainsString("UserInfo::whereIn('user_id', [\$transfer->source_user_id, \$transfer->target_user_id])", $finalizer);
        $this->assertStringContainsString("->orderBy('user_id')", $finalizer);
        $this->assertStringContainsString("->lockForUpdate()", $finalizer);
        $this->assertStringContainsString('DB::transaction(function ()', $finalizer);
        $this->assertStringContainsString('CommissionRecord::firstOrCreate', $finalizer);
        $this->assertStringContainsString("\$source->total_funds = \$sourceBalanceAfter", $finalizer);
        $this->assertStringContainsString("\$target->total_funds = \$targetBalanceAfter", $finalizer);
        $this->assertStringContainsString('$source->saveOrFail()', $finalizer);
        $this->assertStringContainsString('$target->saveOrFail()', $finalizer);
    }
}
