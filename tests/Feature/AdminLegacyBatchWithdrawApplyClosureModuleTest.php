<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:27
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 旧后台批量出金申请兼容闭环测试。
 *
 * 文件功能：
 * - 验证项目1 `index/admin/amount/batchWithdrawApply` 旧入口在项目2中不是占位关闭。
     * - 验证旧 payload.orderList 字段会被逐条转换为现代 withdraw_records 主键并复用现代状态机。
     * - 验证处理中分支只允许从待处理且资金已扣减的记录进入 status=1，避免绕过现有权限与状态边界。
     * - 验证完成、拒绝、部分失败和参数校验均返回可解释的批量结果，避免旧页面获得假成功。
     */
class AdminLegacyBatchWithdrawApplyClosureModuleTest extends TestCase
{
    /**
     * 本夹具批量出金申请单号前缀（WD-LEGACY-BATCH-APPLY-）。断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'WD-LEGACY-BATCH-APPLY-';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    /**
     * 旧批量处理中入口应逐条复用现代出金 process 状态机。
     *
     * 执行链路：
     * - 旧路由读取 payload.status=1，表示批量标记为处理中。
     * - orderList.recordId 对应项目2 withdraw_records.id。
     * - 成功记录返回 code=1000，并把 status 从 0 更新为 1。
     */
    public function test_legacy_batch_withdraw_apply_processes_pending_debited_records(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $first = $this->createWithdrawal('debited', 0);
        $second = $this->createWithdrawal('debited', 0);

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/amount/batchWithdrawApply', [
            'payload' => [
                'status' => 1,
                'remark' => 'legacy batch process',
                'orderList' => [
                    [
                        'recordId' => $first->id,
                        'userId' => $first->user_id,
                        't4Ticket' => $first->mt4_ticket,
                    ],
                    [
                        'recordId' => $second->id,
                        'userId' => $second->user_id,
                        't4Ticket' => $second->mt4_ticket,
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame(1, (int) $first->refresh()->status);
        $this->assertSame(1, (int) $second->refresh()->status);
        $this->assertSame((string) $admin->getKey(), (string) $first->updated_by);
        $this->assertSame((string) $admin->getKey(), (string) $second->updated_by);
    }

    /**
     * 旧批量完成入口应逐条复用现代出金 complete 状态机。
     *
     * 执行链路：
     * - 旧路由读取 payload.status=2，表示批量标记为完成。
     * - 只有 status=1 且 funding_status=debited 的记录可以进入完成态。
     * - 成功后现代状态机把 status 置为 2，并把 funding_status 置为 completed。
     */
    public function test_legacy_batch_withdraw_apply_completes_processing_debited_records(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $withdraw = $this->createWithdrawal('debited', 1);

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/amount/batchWithdrawApply', [
            'payload' => [
                'status' => 2,
                'remark' => 'legacy batch complete',
                'orderList' => [
                    ['recordId' => $withdraw->id],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.status', 2)
            ->assertJsonPath('data.results.0.code', ResponseCode::UPDATED);
        $withdraw->refresh();
        $this->assertSame(2, (int) $withdraw->status);
        $this->assertSame('completed', (string) $withdraw->funding_status);
        $this->assertSame((string) $admin->getKey(), (string) $withdraw->updated_by);
    }

    /**
     * 旧批量拒绝入口应复用现代出金 reject 状态机并生成退款 outbox。
     *
     * 执行链路：
     * - 旧路由读取 payload.status=3，表示批量拒绝。
     * - remark 为空时使用固定中文拒绝原因，避免 reject 接口因空 reason 校验失败。
     * - 已扣款记录先进入 refund_pending，等待退款任务确认后再终结为已拒绝。
     */
    public function test_legacy_batch_withdraw_apply_rejects_debited_records_with_default_reason_and_refund_outbox(): void
    {
        Queue::fake();
        $admin = Admin::query()->findOrFail(1);
        $withdraw = $this->createWithdrawal('debited', 0);

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/amount/batchWithdrawApply', [
            'payload' => [
                'status' => 3,
                'remark' => '   ',
                'orderList' => [
                    ['recordId' => $withdraw->id],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.status', 3)
            ->assertJsonPath('data.results.0.code', ResponseCode::SUCCESS);
        $withdraw->refresh();
        $expectedReason = __('admin.legacy_batch_withdraw_reject_reason');
        $this->assertNotSame('admin.legacy_batch_withdraw_reject_reason', $expectedReason);
        $this->assertSame('refund_pending', (string) $withdraw->funding_status);
        $this->assertSame($expectedReason, (string) $withdraw->reject_reason);
        $this->assertSame((string) $admin->getKey(), (string) $withdraw->updated_by);

        $outbox = WithdrawSettlementOutbox::query()
            ->where('withdraw_record_id', $withdraw->id)
            ->where('event_type', 'withdraw_refund')
            ->first();
        $this->assertNotNull($outbox);
        $this->assertSame('pending', (string) $outbox->status);
    }

    /**
     * 旧批量入口出现部分行失败时必须返回部分失败而不是整体假成功。
     *
     * 执行链路：
     * - 第一行 recordId 合法，进入现代 process 状态机并成功。
     * - 第二行缺少 recordId，仅返回该行参数校验失败，不影响第一行。
     * - 汇总 code=3006 表示批量部分失败，旧页面可据此提示用户检查失败行。
     */
    public function test_legacy_batch_withdraw_apply_reports_partial_failure_without_rolling_back_successful_rows(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $withdraw = $this->createWithdrawal('debited', 0);

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/amount/batchWithdrawApply', [
            'payload' => [
                'status' => 1,
                'remark' => 'legacy partial process',
                'orderList' => [
                    ['recordId' => $withdraw->id],
                    ['userId' => $withdraw->user_id],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::BATCH_PARTIAL_FAILED)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.results.0.code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.results.1.code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(1, (int) $withdraw->refresh()->status);
    }

    /**
     * 旧批量入口参数缺失时应直接返回统一参数错误。
     *
     * 执行链路：
     * - payload 不是数组、status 不在 1/2/3、orderList 为空都无法映射现代状态机。
     * - 适配层返回 code=4005，避免构造 id=0 或未知状态进入资金逻辑。
     *
     * @dataProvider invalidPayloads
     */
    public function test_legacy_batch_withdraw_apply_rejects_invalid_payloads(array $payload): void
    {
        $admin = Admin::query()->findOrFail(1);

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/amount/batchWithdrawApply', $payload);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): array
    {
        return [
            'missing payload' => [[]],
            'invalid status' => [['payload' => ['status' => 9, 'orderList' => [['recordId' => 1]]]]],
            'empty order list' => [['payload' => ['status' => 1, 'orderList' => []]]],
        ];
    }

    private function createWithdrawal(string $fundingStatus, int $status): WithdrawRecord
    {
        $localOrderNo = self::PREFIX . uniqid('', true);

        return WithdrawRecord::create([
            'user_id' => 412355126,
            'user_name' => 'legacy-batch-withdraw-user',
            'mt4_ticket' => '88126',
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

    private function cleanup(): void
    {
        DB::table('withdraw_settlement_outbox')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
        DB::table('withdraw_records')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
    }
}
