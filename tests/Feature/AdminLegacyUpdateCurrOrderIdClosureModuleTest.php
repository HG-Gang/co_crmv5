<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:03
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Models\WithdrawRecord;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧后台出金本地订单号入口闭环测试。
 *
 * 文件功能：
 * - 验证旧 `updateCurrOrderId` 的 `recordId` 能映射到现代 `withdraw_records.id`。
 * - 验证旧入口只生成 `BROTC-时间-WR-用户ID` 本地订单号，不顺手改变出金审核状态。
 * - 验证已有资金 outbox 的记录不允许改号，避免破坏 `withdraw_settlement_outbox.local_order_no` 幂等身份。
 * - 验证缺少 `recordId` 时返回参数失败，避免把 userId 或订单号误当出金记录主键。
 */
class AdminLegacyUpdateCurrOrderIdClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 本夹具订单号前缀（LEGACY-UPDATE-CURR-）。断言与清理都按前缀圈定样本订单。
     * @var string
     */
    private const PREFIX = 'LEGACY-UPDATE-CURR-';

    /**
     * 旧 updateCurrOrderId 应为待处理且没有资金 outbox 的出金记录生成本地 OTC 订单号。
     *
     * 执行链路说明：
     * - recordId 进入旧兼容路由后解析为 withdraw_records.id。
     * - 兼容层只更新 local_order_no，不更新 status，保留旧项目“生成本地单号后再进入 OTC 下单”的分步语义。
     *
     * @return void
     */
    public function test_legacy_update_curr_order_id_generates_brotc_local_order_without_changing_status(): void
    {
        $actor = $this->ensureSuperAdmin();
        $withdraw = $this->createWithdrawal('debited', 0, 'LEGACY-WDR-BEFORE-' . uniqid());

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/updateCurrOrderId', [
                'recordId' => $withdraw->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertJsonPath('err', 'NOERR')
            ->assertJsonPath('col', 'NOCOL');

        $withdraw->refresh();
        $this->assertSame(0, (int) $withdraw->status);
        $this->assertSame('debited', (string) $withdraw->funding_status);
        $this->assertMatchesRegularExpression('/^BROTC-\d{14}-WR-' . $withdraw->user_id . '$/', (string) $withdraw->local_order_no);
        $this->assertSame((string) $actor->id, (string) $withdraw->updated_by);
    }

    /**
     * 已写入资金 outbox 的出金记录不允许重新生成本地订单号。
     *
     * 执行链路说明：
     * - outbox.local_order_no 与 withdraw_records.local_order_no 组成资金命令的幂等身份。
     * - 若旧入口覆盖订单号，后续退款或扣款任务会找不到原始身份，因此必须返回业务失败并保持原值。
     *
     * @return void
     */
    public function test_legacy_update_curr_order_id_rejects_record_with_existing_outbox_without_changing_order_no(): void
    {
        $actor = $this->ensureSuperAdmin();
        $originalOrderNo = 'LEGACY-WDR-OUTBOX-' . uniqid();
        $withdraw = $this->createWithdrawal('debited', 0, $originalOrderNo);
        $this->createWithdrawOutbox((int) $withdraw->id, $originalOrderNo);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/updateCurrOrderId', [
                'recordId' => $withdraw->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'UPDATEFAIL')
            ->assertJsonPath('col', 'NOCOL');

        $withdraw->refresh();
        $this->assertSame(0, (int) $withdraw->status);
        $this->assertSame($originalOrderNo, (string) $withdraw->local_order_no);
        $this->assertSame('', (string) $withdraw->updated_by);
    }

    /**
     * 非待处理状态不能重新生成本地订单号。
     *
     * @return void
     */
    public function test_legacy_update_curr_order_id_rejects_non_pending_withdrawal_without_writing(): void
    {
        $actor = $this->ensureSuperAdmin();
        $originalOrderNo = 'LEGACY-WDR-PROCESSING-' . uniqid();
        $withdraw = $this->createWithdrawal('debited', 1, $originalOrderNo);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/updateCurrOrderId', [
                'recordId' => $withdraw->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'UPDATEFAIL')
            ->assertJsonPath('col', 'NOCOL');

        $withdraw->refresh();
        $this->assertSame(1, (int) $withdraw->status);
        $this->assertSame($originalOrderNo, (string) $withdraw->local_order_no);
        $this->assertSame('', (string) $withdraw->updated_by);
    }

    /**
     * 缺少 recordId 时旧入口不能写入任何出金记录。
     *
     * @return void
     */
    public function test_legacy_update_curr_order_id_rejects_missing_record_id_without_writing(): void
    {
        $actor = $this->ensureSuperAdmin();
        $originalOrderNo = 'LEGACY-WDR-MISSING-' . uniqid();
        $withdraw = $this->createWithdrawal('debited', 0, $originalOrderNo);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/updateCurrOrderId', [
                'userId' => $withdraw->user_id,
                'orderId' => $withdraw->local_order_no,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'VALIDATION_FAILED')
            ->assertJsonPath('col', 'recordId');

        $withdraw->refresh();
        $this->assertSame(0, (int) $withdraw->status);
        $this->assertSame('debited', (string) $withdraw->funding_status);
        $this->assertSame($originalOrderNo, (string) $withdraw->local_order_no);
        $this->assertSame('', (string) $withdraw->updated_by);
    }

    /**
     * 创建测试后台管理员。
     *
     * @return Admin 可绑定 admin guard 的后台管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-legacy-update-curr-super',
                'email' => 'admin-legacy-update-curr-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建测试出金记录。
     *
     * @param string $fundingStatus 资金状态；debited 表示前置扣款已经成功。
     * @param int $status 出金业务状态；0=待处理，1=处理中。
     * @param string $localOrderNo 原本地订单号，用于验证旧入口是否按预期覆盖或保留。
     * @return WithdrawRecord 出金记录模型。
     */
    private function createWithdrawal(string $fundingStatus, int $status, string $localOrderNo): WithdrawRecord
    {
        return WithdrawRecord::create([
            'user_id' => 412355226,
            'user_name' => 'legacy-update-curr-user',
            'mt4_ticket' => '88226',
            'apply_amount' => '40.00',
            'actual_amount' => '39.00',
            'fee' => '1.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '7.00',
            'bank_no' => 'BANK',
            'bank_name' => 'Bank',
            'bank_addr' => 'Addr',
            'status' => $status,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $localOrderNo,
            'funding_status' => $fundingStatus,
            'funding_payload_hash' => hash('sha256', $localOrderNo),
            'created_by' => 'test',
            'updated_by' => '',
        ]);
    }

    /**
     * 创建出金资金 outbox 样本。
     *
     * @param int $withdrawId withdraw_records.id，用于建立 outbox 与出金记录的归属关系。
     * @param string $localOrderNo outbox 当前持有的幂等本地订单号。
     * @return void
     */
    private function createWithdrawOutbox(int $withdrawId, string $localOrderNo): void
    {
        DB::table('withdraw_settlement_outbox')->insert([
            'withdraw_record_id' => $withdrawId,
            'local_order_no' => $localOrderNo,
            'event_type' => 'withdraw_debit',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => hash('sha256', $localOrderNo),
            'available_at' => time(),
            'locked_at' => null,
            'processed_at' => null,
            'provider_reference' => null,
            'last_error_code' => null,
        ]);
    }
}
