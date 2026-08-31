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
 * 管理员提现资金状态机（Funding State Machine）闭环测试。
 *
 * 文件功能：
 * - 验证提现处理（process）在 MT4 扣款未确认前拒绝流转。
 * - 验证 process/complete/reject 均在锁定提现记录之后才做数据范围（Scope）校验。
 * - 验证驳回理由的 Unicode 字符长度边界（500 字内通过、超过拒绝）。
 * - 验证已完成的扣款提现不能再被驳回；管理员驳回后可重试扣款会取消整条资金链并生成退款事件。
 *
 * 适用场景：
 * - 提现资金状态机在并发与异常路径下的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/withdrawProcess   id: {withdraw_id}
 * - POST /api/admin/withdrawReject    id: {withdraw_id}, reason: 拒 x 500
 *
 * 返回值：
 * - 正常流转返回 code 1000（SUCCESS），状态机字段按预期迁移。
 * - 非法操作返回非 1000 的错误码，且记录状态不变。
 *
 * 异常或失败场景：
 * - 未知扣款结果会使退款事件保持 blocked；无管理员身份时返回 PERMISSION_DENIED。
 */

namespace Tests\Feature;

use App\Contracts\WithdrawalFundingGateway;
use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\WithdrawController;
use App\Jobs\ProcessWithdrawFunding;
use App\Models\Admin;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Services\AdminDataScopeService;
use App\Services\Withdrawal\WithdrawalFundingResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AdminWithdrawalFundingStateMachineClosureModuleTest extends TestCase
{
    /**
     * 本夹具订单号与幂等键的统一前缀（WD-ADMIN-TASK4-）。setUp/tearDown 按前缀清理，避免撞既有数据。
     * @var string
     */
    private const PREFIX = 'WD-ADMIN-TASK4-';
    protected function setUp(): void { parent::setUp(); $this->cleanup(); }
    protected function tearDown(): void { $this->cleanup(); Mockery::close(); parent::tearDown(); }

    /**
     * 验证 MT4 扣款未确认前，提现处理接口拒绝流转且状态保持 0。
     */
    public function test_process_rejects_transition_before_mt4_debit_confirmed(): void
    {
        $withdraw = $this->createWithdrawal('pending', 0);
        $response = $this->controller()->process($this->adminRequest(['id' => $withdraw->id]), null);
        $this->assertNotSame(1000, (int) $response->getData(true)['code']);
        $this->assertSame(0, (int) $withdraw->refresh()->status);
    }

    /**
     * 验证缺少管理员身份时提现处理失败关闭并返回 PERMISSION_DENIED。
     */
    public function test_process_returns_permission_denied_without_admin_identity(): void
    {
        $withdraw = $this->createWithdrawal('debited', 0);
        $request = Request::create('/api/admin/withdrawProcess', 'POST', ['id' => $withdraw->id]);

        $response = $this->controller()->process($request, null);

        $this->assertSame(ResponseCode::PERMISSION_DENIED, (int) $response->getData(true)['code']);
        $this->assertSame(0, (int) $withdraw->refresh()->status);
    }

    /**
     * 验证驳回理由恰好 500 个 Unicode 字符时可通过并完整保存。
     */
    public function test_reject_reason_exactly_500_unicode_characters_accepted(): void
    {
        $withdraw = $this->createWithdrawal('pending', 0);
        $reason = str_repeat('拒', 500);

        $response = $this->controller()->reject(
            $this->adminRequest(['id' => $withdraw->id, 'reason' => $reason]),
            null
        );

        $this->assertSame(1000, (int) $response->getData(true)['code']);
        $this->assertSame($reason, $withdraw->refresh()->reject_reason);
    }

    /**
     * 验证驳回理由超过 500 个 Unicode 字符时被拒绝且不保存。
     */
    public function test_reject_reason_over_500_unicode_characters_rejected(): void
    {
        $withdraw = $this->createWithdrawal('pending', 0);
        $reason = str_repeat('拒', 501);

        $response = $this->controller()->reject(
            $this->adminRequest(['id' => $withdraw->id, 'reason' => $reason]),
            null
        );

        $this->assertNotSame(1000, (int) $response->getData(true)['code']);
        $this->assertSame('', (string) $withdraw->refresh()->reject_reason);
    }

    /**
     * 验证 process 在锁定提现记录之后才执行数据范围校验。
     */
    public function test_process_locks_withdraw_record_before_scope_check(): void
    {
        $withdraw = $this->createWithdrawal('debited', 0);
        $events = [];
        $transactionLevels = [];
        DB::listen(static function ($query) use (&$events): void {
            $sql = strtolower((string) $query->sql);
            if (strpos($sql, 'withdraw_records') !== false
                && strpos($sql, 'for update') !== false) {
                $events[] = 'locked';
            }
        });
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessRecord')->once()->andReturnUsing(
            static function () use (&$events, &$transactionLevels): bool {
                $events[] = 'scope';
                $transactionLevels[] = DB::transactionLevel();

                return true;
            }
        );

        $response = (new WithdrawController($scope))->process(
            $this->adminRequest(['id' => $withdraw->id]),
            null
        );

        $this->assertSame(1000, (int) $response->getData(true)['code']);
        $this->assertSame(['locked', 'scope'], $events);
        $this->assertSame([1], $transactionLevels);
        $this->assertSame(1, (int) $withdraw->refresh()->status);
    }

    /**
     * 验证 complete 在锁定提现记录之后才执行数据范围校验。
     */
    public function test_complete_locks_withdraw_record_before_scope_check(): void
    {
        $withdraw = $this->createWithdrawal('debited', 1);
        $events = [];
        $transactionLevels = [];
        DB::listen(static function ($query) use (&$events): void {
            $sql = strtolower((string) $query->sql);
            if (strpos($sql, 'withdraw_records') !== false
                && strpos($sql, 'for update') !== false) {
                $events[] = 'locked';
            }
        });
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessRecord')->once()->andReturnUsing(
            static function () use (&$events, &$transactionLevels): bool {
                $events[] = 'scope';
                $transactionLevels[] = DB::transactionLevel();

                return true;
            }
        );

        (new WithdrawController($scope))->complete(
            $this->adminRequest(['id' => $withdraw->id]),
            null
        );

        $this->assertSame(['locked', 'scope'], $events);
        $this->assertSame([1], $transactionLevels);
        $this->assertSame(2, (int) $withdraw->refresh()->status);
    }

    /**
     * 验证 reject 在锁定提现记录之后才执行数据范围校验。
     */
    public function test_reject_locks_withdraw_record_before_scope_check(): void
    {
        $withdraw = $this->createWithdrawal('pending', 0);
        $events = [];
        $transactionLevels = [];
        DB::listen(static function ($query) use (&$events): void {
            $sql = strtolower((string) $query->sql);
            if (strpos($sql, 'withdraw_records') !== false
                && strpos($sql, 'for update') !== false) {
                $events[] = 'locked';
            }
        });
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessRecord')->once()->andReturnUsing(
            static function () use (&$events, &$transactionLevels): bool {
                $events[] = 'scope';
                $transactionLevels[] = DB::transactionLevel();

                return true;
            }
        );

        (new WithdrawController($scope))->reject(
            $this->adminRequest(['id' => $withdraw->id, 'reason' => 'manual review']),
            null
        );

        $this->assertSame(['locked', 'scope'], $events);
        $this->assertSame([1], $transactionLevels);
        $this->assertSame('cancelled', $withdraw->refresh()->funding_status);
    }

    /**
     * 验证已扣款且已完成的提现被驳回时失败关闭：状态、资金状态、驳回理由均不变。
     */
    public function test_debited_completed_withdraw_reject_fails_closed(): void
    {
        $withdraw = $this->createWithdrawal('debited', 2);

        $response = $this->controller()->reject(
            $this->adminRequest(['id' => $withdraw->id, 'reason' => 'late rejection']),
            null
        );

        $withdraw->refresh();
        $this->assertNotSame(1000, (int) $response->getData(true)['code']);
        $this->assertSame(2, (int) $withdraw->status);
        $this->assertSame('debited', $withdraw->funding_status);
        $this->assertSame('', (string) $withdraw->reject_reason);
        $this->assertFalse(
            WithdrawSettlementOutbox::where('withdraw_record_id', $withdraw->id)
                ->where('event_type', 'withdraw_refund')
                ->exists()
        );
    }

    /**
     * 验证管理员驳回后重试扣款（retryable）会取消整条资金链并生成退款事件。
     */
    public function test_admin_reject_retryable_debit_cancels_chain_and_creates_refund(): void
    {
        $withdraw = $this->createWithdrawal('pending', 0);
        $debit = $this->createDebitOutbox($withdraw);
        $controller = $this->controller();
        $response = null;
        $reject = function () use ($controller, $withdraw, &$response): void {
            $response = $controller->reject(
                $this->adminRequest(['id' => $withdraw->id, 'reason' => 'risk rejected']),
                null
            );
        };
        $gateway = new class($reject) implements WithdrawalFundingGateway {
            /**
             * 出金网关替身的拒绝回调。withdraw() 调用它生成动态拒绝结果，驱动状态机各拒绝分支。
             * @var callable
             */
            private $reject;
            public function __construct(callable $reject) { $this->reject = $reject; }
            public function withdraw(int $userId, string $amount, string $comment): WithdrawalFundingResult
            {
                ($this->reject)();

                return WithdrawalFundingResult::retryableNotSent('connection_failed');
            }
        };

        (new ProcessWithdrawFunding($debit->id))->handle($gateway);

        $refund = WithdrawSettlementOutbox::where('withdraw_record_id', $withdraw->id)
            ->where('event_type', 'withdraw_refund')
            ->firstOrFail();
        $withdraw->refresh();
        $debit->refresh();
        $this->assertSame(1000, (int) $response->getData(true)['code']);
        $this->assertSame(3, (int) $withdraw->status);
        $this->assertSame('cancelled', $withdraw->funding_status);
        $this->assertSame('cancelled', $debit->status);
        $this->assertNotNull($debit->processed_at);
        $this->assertSame('cancelled', $refund->status);
        $this->assertNotNull($refund->processed_at);
    }

    /**
     * 验证管理员驳回后扣款结果为未知时，退款事件保持 blocked 不自动放行。
     */
    public function test_unknown_debit_result_keeps_refund_event_blocked(): void
    {
        $withdraw = $this->createWithdrawal('pending', 0);
        $debit = $this->createDebitOutbox($withdraw);
        $controller = $this->controller();
        $reject = function () use ($controller, $withdraw): void {
            $controller->reject(
                $this->adminRequest(['id' => $withdraw->id, 'reason' => 'risk rejected']),
                null
            );
        };
        $gateway = new class($reject) implements WithdrawalFundingGateway {
            /**
             * 出金网关替身的拒绝回调。withdraw() 调用它生成动态拒绝结果，驱动另一状态机分支。
             * @var callable
             */
            private $reject;
            public function __construct(callable $reject) { $this->reject = $reject; }
            public function withdraw(int $userId, string $amount, string $comment): WithdrawalFundingResult
            {
                ($this->reject)();

                return WithdrawalFundingResult::unknown('read_timeout');
            }
        };

        (new ProcessWithdrawFunding($debit->id))->handle($gateway);

        $refund = WithdrawSettlementOutbox::where('withdraw_record_id', $withdraw->id)
            ->where('event_type', 'withdraw_refund')
            ->firstOrFail();
        $this->assertSame('unknown', $withdraw->refresh()->funding_status);
        $this->assertSame('unknown', $debit->refresh()->status);
        $this->assertSame('blocked', $refund->status);
        $this->assertNull($refund->processed_at);
    }

    private function controller(): WithdrawController
    {
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessRecord')->andReturnTrue();
        return new WithdrawController($scope);
    }

    private function adminRequest(array $input): Request
    {
        $request = Request::create('/api/admin/withdrawProcess', 'POST', $input);
        $request->setUserResolver(static function (string $guard = null) { return (new Admin())->forceFill(['id' => 98001]); });
        return $request;
    }

    private function createWithdrawal(string $fundingStatus, int $status): WithdrawRecord
    {
        $local = self::PREFIX . uniqid('', true);
        return WithdrawRecord::create([
            'user_id' => 412355006, 'user_name' => 'admin-task4-user', 'mt4_ticket' => '',
            'apply_amount' => '25.00', 'actual_amount' => '24.00', 'fee' => '1.00', 'exchange_rate' => '7.00000000', 'rmb_fee' => '7.00',
            'bank_no' => 'BANK', 'bank_name' => 'Bank', 'bank_addr' => 'Addr', 'status' => $status, 'local_order_no' => $local,
            'third_order_no' => '', 'reject_reason' => '', 'mt4_return_status' => '', 'idempotency_key' => $local,
            'funding_status' => $fundingStatus, 'funding_payload_hash' => hash('sha256', $local), 'created_by' => 'test', 'updated_by' => '',
        ]);
    }

    private function createDebitOutbox(WithdrawRecord $withdraw): WithdrawSettlementOutbox
    {
        return WithdrawSettlementOutbox::create([
            'withdraw_record_id' => $withdraw->id,
            'local_order_no' => $withdraw->local_order_no,
            'event_type' => 'withdraw_debit',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => $withdraw->funding_payload_hash,
            'available_at' => time(),
        ]);
    }

    private function cleanup(): void
    {
        DB::table('withdraw_settlement_outbox')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
        DB::table('withdraw_records')->where('local_order_no', 'like', self::PREFIX . '%')->delete();
    }
}
