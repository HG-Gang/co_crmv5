<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:34
 */

declare(strict_types=1);

/**
 * 管理员驳回已扣款提现并派发退款任务（Refund Dispatch）的闭环测试。
 *
 * 文件功能：
 * - 验证驳回已扣款提现时创建 pending 的 withdraw_refund 出站事件并派发 RefundWithdrawFunding 任务。
 * - 验证完成（complete）操作把资金状态标记为 completed 且不派发退款任务。
 * - 验证驳回未扣款提现时直接取消，不派发退款任务。
 *
 * 适用场景：
 * - 提现驳回-退款派发链路的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/withdrawReject
 *   id: {withdraw_id}
 *   reason: risk reject after debit
 *
 * 返回值：
 * - 驳回已扣款提现返回 code 为 SUCCESS，funding_status 变为 refund_pending，并派发退款任务。
 * - complete 返回 code 为 UPDATED。
 *
 * 异常或失败场景：
 * - 未扣款提现被驳回时不得派发退款任务，否则断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\WithdrawController;
use App\Jobs\RefundWithdrawFunding;
use App\Models\Admin;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Services\AdminDataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AdminWithdrawalRejectRefundDispatchClosureModuleTest extends TestCase
{
    /**
     * 本夹具订单号与幂等键的统一前缀（WD-ADMIN-REFUND-DISPATCH-）。断言与清理都按前缀圈定。
     * @var string
     */
    private const PREFIX = 'WD-ADMIN-REFUND-DISPATCH-';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 验证驳回已扣款提现时创建 pending 退款出站事件并派发退款任务。
     */
    public function test_reject_debited_withdraw_creates_pending_refund_outbox_and_dispatches_job(): void
    {
        Queue::fake();
        $withdraw = $this->createWithdrawal('debited', 0);

        $response = $this->controller()->reject(
            $this->adminRequest(['id' => $withdraw->id, 'reason' => 'risk reject after debit']),
            null
        );

        $this->assertSame(ResponseCode::SUCCESS, (int) $response->getData(true)['code']);
        $withdraw->refresh();
        $this->assertSame('refund_pending', $withdraw->funding_status);
        $this->assertSame('risk reject after debit', $withdraw->reject_reason);

        $outbox = WithdrawSettlementOutbox::where('withdraw_record_id', $withdraw->id)
            ->where('event_type', 'withdraw_refund')
            ->first();
        $this->assertNotNull($outbox);
        $this->assertSame('pending', $outbox->status);

        Queue::assertPushed(RefundWithdrawFunding::class, function ($job) use ($outbox) {
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('outboxId');
            $property->setAccessible(true);

            return (int) $property->getValue($job) === (int) $outbox->id;
        });
    }

    /**
     * 验证完成操作把资金状态标记为 completed 且不派发任何任务。
     */
    public function test_complete_marks_funding_completed_without_dispatching_job(): void
    {
        Queue::fake();
        $withdraw = $this->createWithdrawal('debited', 1);

        $response = $this->controller()->complete(
            $this->adminRequest(['id' => $withdraw->id]),
            null
        );

        $this->assertSame(ResponseCode::UPDATED, (int) $response->getData(true)['code']);
        $withdraw->refresh();
        $this->assertSame(2, (int) $withdraw->status);
        $this->assertSame('completed', $withdraw->funding_status);
        Queue::assertNothingPushed();
    }

    /**
     * 验证驳回未扣款提现时直接取消且不派发退款任务。
     */
    public function test_reject_undebited_withdraw_cancels_without_dispatching_refund_job(): void
    {
        Queue::fake();
        $withdraw = $this->createWithdrawal('pending', 0);
        WithdrawSettlementOutbox::create([
            'withdraw_record_id' => $withdraw->id,
            'local_order_no' => $withdraw->local_order_no,
            'event_type' => 'withdraw_debit',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => $withdraw->funding_payload_hash,
            'available_at' => time(),
        ]);

        $response = $this->controller()->reject(
            $this->adminRequest(['id' => $withdraw->id, 'reason' => 'cancel before debit']),
            null
        );

        $this->assertSame(ResponseCode::SUCCESS, (int) $response->getData(true)['code']);
        $this->assertSame('cancelled', $withdraw->refresh()->funding_status);
        Queue::assertNothingPushed();
    }

    private function controller(): WithdrawController
    {
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessRecord')->andReturnTrue();

        return new WithdrawController($scope);
    }

    private function adminRequest(array $input): Request
    {
        $request = Request::create('/api/admin/withdrawReject', 'POST', $input);
        $request->setUserResolver(static function (string $guard = null) {
            return (new Admin())->forceFill(['id' => 98011, 'username' => 'withdraw-refund-dispatch-admin']);
        });

        return $request;
    }

    private function createWithdrawal(string $fundingStatus, int $status): WithdrawRecord
    {
        $local = self::PREFIX . uniqid('', true);

        return WithdrawRecord::create([
            'user_id' => 412355016,
            'user_name' => 'admin-refund-dispatch-user',
            'mt4_ticket' => $fundingStatus === 'debited' ? '88001' : '',
            'apply_amount' => '30.00',
            'actual_amount' => '29.00',
            'fee' => '1.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '7.00',
            'bank_no' => 'BANK',
            'bank_name' => 'Bank',
            'bank_addr' => 'Addr',
            'status' => $status,
            'local_order_no' => $local,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $local,
            'funding_status' => $fundingStatus,
            'funding_payload_hash' => hash('sha256', $local),
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
