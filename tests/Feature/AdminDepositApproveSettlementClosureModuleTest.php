<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

declare(strict_types=1);

/**
 * 文件功能：验证后台入金审核通过（depositApprove）走异步结算：入队
 *           SettleDepositPayment 并写 payment_settlement_outbox，而非立即加余额；
 *           同时验证结算中/已结算状态的幂等保护。
 *
 * 适用场景：后台 /api/admin/depositApprove 接口的结算流程回归测试。
 *
 * 入参例子：
 * - POST /api/admin/depositApprove：{id}
 *
 * 返回值：
 * - 待结算订单：code=SUCCESS，payment_status=success、settlement_status=pending，
 *   写 outbox 并推送 SettleDepositPayment 队列；
 * - 结算中或已结算订单：code=OPERATION_NOT_ALLOWED，不重复入队。
 *
 * 异常或失败场景：
 * - settlement_status 处于 processing/settled 时拒绝再次审核，不产生 outbox。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Jobs\SettleDepositPayment;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminDepositApproveSettlementClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 手动审核通过应入队结算任务并写 outbox，而非立即增加余额。
    public function test_manual_approve_enqueues_settlement_instead_of_fake_immediate_balance_success(): void
    {
        Queue::fake();
        $actor = $this->ensureSuperAdmin();
        $depositId = $this->createOfflineDeposit('DEP-ADMIN-APPROVE-1', '01');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/depositApprove', ['id' => $depositId]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('deposit_records', [
            'id' => $depositId,
            'payment_status' => 'success',
            'settlement_status' => 'pending',
            'status' => '01',
        ]);

        $outbox = DB::table('payment_settlement_outbox')
            ->where('event_type', 'deposit_settlement')
            ->where('deposit_record_id', $depositId)
            ->first();
        $this->assertNotNull($outbox);
        $this->assertSame('pending', $outbox->status);

        Queue::assertPushed(SettleDepositPayment::class);
    }

    // 结算进行中的订单再次审核应被拒绝且不重复入队。
    public function test_approve_rejects_when_settlement_already_in_progress(): void
    {
        Queue::fake();
        $actor = $this->ensureSuperAdmin();
        $depositId = $this->createOfflineDeposit('DEP-ADMIN-APPROVE-2', '01', 'success', 'processing');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/depositApprove', ['id' => $depositId]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertSame(
            0,
            DB::table('payment_settlement_outbox')->where('deposit_record_id', $depositId)->count()
        );
        Queue::assertNothingPushed();
    }

    // 已结算订单再次审核应幂等拒绝且不重复入队。
    public function test_approve_is_idempotent_when_settlement_already_settled(): void
    {
        Queue::fake();
        $actor = $this->ensureSuperAdmin();
        $depositId = $this->createOfflineDeposit('DEP-ADMIN-APPROVE-3', '02', 'success', 'settled');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/depositApprove', ['id' => $depositId]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        Queue::assertNothingPushed();
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-deposit-approve-settlement',
                'email' => 'admin-deposit-approve-settlement@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createOfflineDeposit(
        string $localOrderNo,
        string $status,
        string $paymentStatus = 'pending',
        string $settlementStatus = 'pending'
    ): int {
        $now = time();

        return (int) DB::table('deposit_records')->insertGetId([
            'user_id' => 984901,
            'user_name' => 'Admin Approve Settlement User',
            'mt4_ticket' => 0,
            'amount' => 200.00,
            'actual_amount' => 200.00,
            'exchange_rate' => 1,
            'channel_name' => 'offline bank',
            'channel_order_no' => '',
            'local_order_no' => $localOrderNo,
            'idempotency_key' => 'admin-approve-' . $localOrderNo,
            'gateway_code' => 'offline',
            'merchant_id' => '',
            'currency' => 'USD',
            'provider_amount' => 200.00,
            'payment_status' => $paymentStatus,
            'settlement_status' => $settlementStatus,
            'provider_payload_hash' => hash('sha256', $localOrderNo),
            'status' => $status,
            'payment_time' => null,
            'remarks' => 'manual offline deposit',
            'created_by' => 'admin',
            'updated_by' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
