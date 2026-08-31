<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

/**
 * 出金退款队列任务。
 *
 * 文件功能：
 * - 消费 withdraw_settlement_outbox 中 event_type=withdraw_refund 的出队记录，把已扣除的出金金额退还到用户 MT4 账户。
 * - 根据网关返回结果把出金单与出队记录推进到 refunded / retryable / refund_unknown / refund_rejected 等状态。
 *
 * 适用场景：
 * - 后台拒绝出金申请后由业务侧 dispatch 本任务。
 * - 定时任务 DispatchPendingWithdrawSettlements 兜底消费 pending / retryable 的退款出队记录。
 *
 * 入参例子：
 * - new RefundWithdrawFunding(15) 处理 withdraw_settlement_outbox.id=15 的出金退款事件。
 *
 * 方法功能：
 * - __construct(int $outboxId)：保存待处理的出金退款出队记录 ID。
 * - handle(WithdrawalRefundGateway $gateway)：声明任务后调用网关退款，并按结果状态落库；传输异常转 unknown 结果。
 * - reconcileAbandonedClaim()：把超过 5 分钟未完成且仍处于 processing 的声明置为 refund_unknown，返回是否已处理。
 * - backoff()：返回失败重试退避秒数 [60, 300]。
 *
 * 幂等要点：
 * - claim() 以行锁 + attempts/locked_at 声明任务，锁定后仅 owner 可写结果；
 * - lockClaimForResult() 校验出队记录与出金单的 ID、订单号、attempts、locked_at、payload_hash 全部匹配才允许落库，
 *   防止并发进程或延迟结果覆盖他人声明。
 *
 * 异常或失败场景：
 * - 网关返回未知状态时抛出 RuntimeException。
 * - 出金单缺失、非 refund_pending 状态或 payload_hash 不一致时出队记录被置为 refund_rejected 或按终态对账。
 * - 本地提交失败时置为 refund_unknown 等待人工核对。
 */
declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WithdrawalRefundGateway;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Services\Withdrawal\WithdrawalFundingResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RefundWithdrawFunding implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 最大重试次数固定为 3：配合 backoff()=[60,300] 覆盖 MT4 短暂抖动；
     * 出金退款是把已扣金额退回用户 MT4 账户的真实资金操作，超限后进入失败队列
     * 转人工核对 refund_unknown 状态，禁止队列无限重试放大资金副作用。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 待处理的出金退款 outbox 记录主键（withdraw_settlement_outbox.id，event_type=withdraw_refund）。
     * 任务只携带 ID，退款金额、MT4 登录号等参数由处理器按记录认领后读取，
     * 同一记录只能被行锁声明的唯一任务消费。
     *
     * @var int
     */
    private $outboxId;

    /**
     * 构造函数保存待处理的出金退款出队记录 ID。
     *
     * @param int $outboxId withdraw_settlement_outbox 主键，handle() 与 reconcileAbandonedClaim() 均以此为操作对象。
     */
    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
    }

    /**
     * 声明任务并调用 MT4 网关执行出金退款，按网关结果落库。
     *
     * @param WithdrawalRefundGateway $gateway MT4 出金退款网关（容器注入）。
     * @return void 无返回值；网关结果无法归类时抛 RuntimeException，由队列重试。
     */
    public function handle(WithdrawalRefundGateway $gateway): void
    {
        // 阶段1 认领：行锁把出队记录置为 processing；不可认领时直接退出。
        $claim = $this->claim();
        if ($claim === null) {
            return;
        }

        // 阶段2 外部退款：传输异常无法确认结果时统一转 unknown 结果，禁止猜测后重发。
        try {
            $result = $gateway->refund($claim['user_id'], $claim['amount'], $claim['comment']);
        } catch (Throwable $exception) {
            $result = WithdrawalFundingResult::unknown('transport_exception');
        }

        if ($result->status() === 'debited') {
            // 阶段3 标记终态：本地落库失败时转 refund_unknown 等人工核对，禁止自动重发。
            try {
                $this->recordRefunded($claim, $result);
            } catch (Throwable $exception) {
                $this->recordLocalCommitFailure($claim);
            }

            return;
        }
        if ($result->status() === 'retryable_not_sent') {
            // 网关确认未发出退款指令：回退状态并延迟重试。
            $this->recordRetryable($claim, $result);

            return;
        }
        if (in_array($result->status(), ['unknown', 'rejected'], true)) {
            // 网关无法确认结果或明确拒绝：写入终态，禁止自动重发。
            $this->recordTerminal($claim, $result);

            return;
        }

        throw new RuntimeException('Unsupported withdrawal refund result.');
    }

    /**
     * 返回失败重试退避秒数序列。
     *
     * 第 1 次失败等 60 秒、第 2 次等 300 秒后进入第 3 次尝试，配合 $tries=3 使用。
     *
     * @return array<int, int> 退避秒数列表，单位秒。
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * 对账回收被放弃的退款认领：processing 且锁超 5 分钟时置 refund_unknown。
     *
     * 由调度命令 DispatchPendingWithdrawSettlements 定期调用，防止陈旧声明卡死队列。
     *
     * @return bool true=已回收（记录被置为 refund_unknown）；false=无需处理。
     */
    public function reconcileAbandonedClaim(): bool
    {
        return DB::transaction(function (): bool {
            // 仅当事件类型匹配、仍为 processing 且已陈旧时才回收，避免误伤进行中的任务。
            $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)
                ->lockForUpdate()
                ->first();
            if (!$outbox
                || $outbox->event_type !== 'withdraw_refund'
                || $outbox->status !== 'processing'
                || !$this->isAbandoned($outbox)) {
                return false;
            }

            $this->markAbandonedClaimUnknown($outbox);

            return true;
        }, 3);
    }

    /**
     * 认领出金退款任务：事务内行锁校验状态并推进，返回网关退款所需参数。
     *
     * 返回参数携带 outbox 与出金单的关键标识、attempts、locked_at 与 payload_hash，
     * 后续结果落库前必须与库内现状完全一致（见 lockClaimForResult），
     * 防止并发进程或延迟结果覆盖他人声明。
     *
     * @return array{
     *     user_id: int,
     *     amount: string,
     *     comment: string,
     *     withdraw_record_id: int,
     *     local_order_no: string,
     *     attempts: int,
     *     locked_at: int,
     *     payload_hash: string
     * }|null
     */
    private function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            // 行锁读取：仅 event_type=withdraw_refund 的退款事件可被认领。
            $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)
                ->lockForUpdate()
                ->first();
            if (!$outbox || $outbox->event_type !== 'withdraw_refund') {
                return null;
            }
            if ($outbox->status === 'processing') {
                // 陈旧声明回收为 refund_unknown 交人工核对；有效声明直接跳过。
                if ($this->isAbandoned($outbox)) {
                    $this->markAbandonedClaimUnknown($outbox);
                }

                return null;
            }
            if (!in_array($outbox->status, ['pending', 'retryable'], true)) {
                // 终态记录不再重复退款。
                return null;
            }
            if ($outbox->available_at !== null && $outbox->available_at->isFuture()) {
                // 重试退避期未到，本次扫描跳过。
                return null;
            }

            // 锁定出金单：不存在或资金状态不是 refund_pending 时对账收尾，不发起退款。
            $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)
                ->lockForUpdate()
                ->first();
            if (!$order) {
                $this->rejectOutbox($outbox, 'withdraw_record_missing');

                return null;
            }
            if ($order->funding_status !== 'refund_pending') {
                $this->reconcileNonPendingOrder($order, $outbox);

                return null;
            }
            if (!$this->payloadMatches($order, $outbox)) {
                // payload 哈希不同源（缺失/不一致）：不退款，置 refund_unknown 待人工核对。
                $this->markUnknown($order, $outbox, 'payload_hash_mismatch');

                return null;
            }

            // 正式认领：attempts 递增并记录 locked_at，作为并发排他与结果校验依据。
            $outbox->status = 'processing';
            $outbox->attempts = (int) $outbox->attempts + 1;
            $outbox->locked_at = now();
            $outbox->saveOrFail();

            return [
                'user_id' => (int) $order->user_id,
                'amount' => (string) $order->apply_amount,
                'comment' => \App\Constants\Mt4RemarkCodes::WDRF . $order->local_order_no,
                'withdraw_record_id' => (int) $order->id,
                'local_order_no' => (string) $order->local_order_no,
                'attempts' => (int) $outbox->attempts,
                'locked_at' => (int) $outbox->getRawOriginal('locked_at'),
                'payload_hash' => (string) $outbox->payload_hash,
            ];
        }, 3);
    }

    /**
     * 记录网关确认退款成功：写 MT4 ticket 与 refunded 终态。
     *
     * @param array $claim 认领时返回的参数（含所有权凭证）。
     * @param WithdrawalFundingResult $result 网关退款结果。
     * @return void 无返回值；凭证号非法时置 refund_unknown，不落坏数据。
     */
    private function recordRefunded(array $claim, WithdrawalFundingResult $result): void
    {
        DB::transaction(function () use ($claim, $result): void {
            // 结果落库前校验声明所有权与 payload 完全一致，防止覆盖他人声明。
            $locked = $this->lockClaimForResult($claim);
            if ($locked === null) {
                return;
            }
            [$order, $outbox] = $locked;

            $ticket = (string) $result->providerReference();
            if (!ctype_digit($ticket) || (int) $ticket <= 0) {
                // MT4 凭证号非法：不落坏数据，转 refund_unknown 交人工核对。
                $this->markUnknown($order, $outbox, 'invalid_provider_reference');

                return;
            }

            $refundTime = now();
            $order->refund_mt4_ticket = $ticket;
            $order->refund_time = $refundTime;
            $order->status = 3;
            $order->funding_status = 'refunded';
            $order->funding_error_code = null;
            $order->saveOrFail();

            $outbox->status = 'refunded';
            $outbox->provider_reference = $ticket;
            $outbox->processed_at = $refundTime;
            $outbox->locked_at = null;
            $outbox->last_error_code = null;
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 记录网关确认未发出退款：保留 refund_pending，出队记录延迟重试。
     *
     * @param array $claim 认领时返回的参数。
     * @param WithdrawalFundingResult $result 网关退款结果。
     * @return void 无返回值。
     */
    private function recordRetryable(array $claim, WithdrawalFundingResult $result): void
    {
        DB::transaction(function () use ($claim, $result): void {
            $locked = $this->lockClaimForResult($claim);
            if ($locked === null) {
                return;
            }
            [$order, $outbox] = $locked;

            $order->funding_error_code = $result->errorCode();
            $order->saveOrFail();

            // 未发出退款：回退 retryable，退避时长随 attempts 递增（上限 600 秒）；
            // 同时清空 processed_at/provider_reference，避免上次尝试的痕迹被误认为已处理。
            $outbox->status = 'retryable';
            $outbox->available_at = now()->addSeconds(
                60 * max(1, min((int) $outbox->attempts, 10))
            );
            $outbox->locked_at = null;
            $outbox->processed_at = null;
            $outbox->provider_reference = null;
            $outbox->last_error_code = $result->errorCode();
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 记录网关结果未知或拒绝：出金单与出队记录直接落终态（refund_unknown/refund_rejected）。
     *
     * @param array $claim 认领时返回的参数。
     * @param WithdrawalFundingResult $result 网关退款结果（unknown/rejected）。
     * @return void 无返回值。
     */
    private function recordTerminal(array $claim, WithdrawalFundingResult $result): void
    {
        DB::transaction(function () use ($claim, $result): void {
            $locked = $this->lockClaimForResult($claim);
            if ($locked === null) {
                return;
            }
            [$order, $outbox] = $locked;

            $status = $result->status() === 'rejected'
                ? 'refund_rejected'
                : 'refund_unknown';
            $errorCode = (string) $result->errorCode();
            $order->funding_status = $status;
            $order->funding_error_code = $errorCode;
            $order->saveOrFail();

            $outbox->status = $status;
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->provider_reference = null;
            $outbox->last_error_code = $errorCode;
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 结果落库前重新锁定并全面校验声明归属：事件、状态、订单号、attempts、locked_at、payload 全部一致。
     *
     * @param array $claim 认领时返回的参数。
     * @return array{0: WithdrawRecord, 1: WithdrawSettlementOutbox}|null
     *         校验通过返回已锁定的出金单与出队记录；任何一项不匹配返回 null（放弃落库）。
     */
    private function lockClaimForResult(array $claim): ?array
    {
        $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)
            ->lockForUpdate()
            ->first();
        if (!$outbox
            || $outbox->event_type !== 'withdraw_refund'
            || $outbox->status !== 'processing'
            || (int) $outbox->withdraw_record_id !== $claim['withdraw_record_id']
            || (string) $outbox->local_order_no !== $claim['local_order_no']
            || (int) $outbox->attempts !== $claim['attempts']
            || (int) $outbox->getRawOriginal('locked_at') !== $claim['locked_at']
            || !hash_equals($claim['payload_hash'], (string) $outbox->payload_hash)) {
            return null;
        }

        $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)
            ->lockForUpdate()
            ->first();
        if (!$order
            || $order->funding_status !== 'refund_pending'
            || (int) $order->id !== $claim['withdraw_record_id']
            || (string) $order->local_order_no !== $claim['local_order_no']
            || !hash_equals($claim['payload_hash'], (string) $order->funding_payload_hash)) {
            return null;
        }

        return [$order, $outbox];
    }

    /**
     * 对比出金单与出队记录的 payload 哈希：双方均为 64 位十六进制且一致才允许退款。
     *
     * @param WithdrawRecord $order 出金单。
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @return bool true=同源且完整。
     */
    private function payloadMatches(
        WithdrawRecord $order,
        WithdrawSettlementOutbox $outbox
    ): bool {
        $orderHash = (string) $order->funding_payload_hash;
        $outboxHash = (string) $outbox->payload_hash;

        return strlen($orderHash) === 64
            && strlen($outboxHash) === 64
            && hash_equals($orderHash, $outboxHash);
    }

    /**
     * 判定处理中声明是否陈旧：locked_at 缺失或超过 5 分钟即视为放弃。
     *
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @return bool true=声明已放弃。
     */
    private function isAbandoned(WithdrawSettlementOutbox $outbox): bool
    {
        return $outbox->locked_at === null
            || $outbox->locked_at->lte(now()->subMinutes(5));
    }

    /**
     * 陈旧声明回收：优先按出金单终态对账，否则联动置 refund_unknown 等待人工核对。
     *
     * @param WithdrawSettlementOutbox $outbox 已锁定且陈旧的出队记录。
     * @return void 无返回值。
     */
    private function markAbandonedClaimUnknown(WithdrawSettlementOutbox $outbox): void
    {
        // 出金单已是终态（refunded/refund_unknown/refund_rejected）时按终态对账，
        // 防止把已完成退款的订单错误降级为 unknown。
        $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)
            ->lockForUpdate()
            ->first();
        if ($order && $this->reconcileKnownTerminalOrder($order, $outbox)) {
            return;
        }
        if ($order && $order->funding_status === 'refund_pending') {
            // 出金单仍在退款前置态：联动置 refund_unknown，交人工核对实际退款情况。
            $order->funding_status = 'refund_unknown';
            $order->funding_error_code = 'stale_processing_claim';
            $order->saveOrFail();
        }

        $outbox->status = 'refund_unknown';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->provider_reference = null;
        $outbox->last_error_code = 'stale_processing_claim';
        $outbox->saveOrFail();
    }

    /**
     * 出金单非 refund_pending 时收尾：可对账终态则同步，否则拒绝本次退款。
     *
     * @param WithdrawRecord $order 出金单。
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @return void 无返回值。
     */
    private function reconcileNonPendingOrder(
        WithdrawRecord $order,
        WithdrawSettlementOutbox $outbox
    ): void {
        if (!$this->reconcileKnownTerminalOrder($order, $outbox)) {
            $this->rejectOutbox($outbox, 'refund_not_ready');
        }
    }

    /**
     * 按出金单终态对账出队记录：已退款补凭证，其他终态直接同步。
     *
     * @param WithdrawRecord $order 出金单。
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @return bool true=出金单是已识别终态并完成对账。
     */
    private function reconcileKnownTerminalOrder(
        WithdrawRecord $order,
        WithdrawSettlementOutbox $outbox
    ): bool {
        if ($order->funding_status === 'refunded') {
            $this->reconcileRefundedOrder($order, $outbox);

            return true;
        }
        if (in_array($order->funding_status, ['refund_unknown', 'refund_rejected'], true)) {
            // 出金单终态即出队记录终态，同步 last_error_code 便于追溯。
            $outbox->status = (string) $order->funding_status;
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->provider_reference = null;
            $outbox->last_error_code = $order->funding_error_code
                ?: 'order_already_' . $order->funding_status;
            $outbox->saveOrFail();

            return true;
        }

        return false;
    }

    /**
     * 出金单已退款时的对账：凭证合法则补记 processed，否则 refund_unknown 待人工。
     *
     * @param WithdrawRecord $order 出金单。
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @return void 无返回值。
     */
    private function reconcileRefundedOrder(
        WithdrawRecord $order,
        WithdrawSettlementOutbox $outbox
    ): void {
        $ticket = (string) $order->refund_mt4_ticket;
        if (!ctype_digit($ticket) || (int) $ticket <= 0) {
            // 订单标记 refunded 却无合法凭证：视为数据异常，转 refund_unknown 待人工核对。
            $outbox->status = 'refund_unknown';
            $outbox->provider_reference = null;
            $outbox->last_error_code = 'refunded_order_missing_ticket';
        } else {
            // 凭证合法：按订单退款时间补记终态。
            $outbox->status = 'refunded';
            $outbox->provider_reference = $ticket;
            $outbox->last_error_code = null;
        }
        $outbox->processed_at = $order->refund_time ?? now();
        $outbox->locked_at = null;
        $outbox->saveOrFail();
    }

    /**
     * 失败关闭：出金单与出队记录同时置 refund_unknown 并记录原因码，等待人工核对。
     *
     * @param WithdrawRecord $order 出金单。
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @param string $errorCode 失败原因码。
     * @return void 无返回值。
     */
    private function markUnknown(
        WithdrawRecord $order,
        WithdrawSettlementOutbox $outbox,
        string $errorCode
    ): void {
        $order->funding_status = 'refund_unknown';
        $order->funding_error_code = $errorCode;
        $order->saveOrFail();

        $outbox->status = 'refund_unknown';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->provider_reference = null;
        $outbox->last_error_code = $errorCode;
        $outbox->saveOrFail();
    }

    /**
     * 前置校验失败时关闭出队记录：置 refund_rejected 终态，不再被调度命令捞起。
     *
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @param string $errorCode 失败原因码。
     * @return void 无返回值。
     */
    private function rejectOutbox(
        WithdrawSettlementOutbox $outbox,
        string $errorCode
    ): void {
        $outbox->status = 'refund_rejected';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->provider_reference = null;
        $outbox->last_error_code = $errorCode;
        $outbox->saveOrFail();
    }

    /**
     * 外部退款成功但本地落库失败时的失败关闭：置 refund_unknown 等人工核对，禁止自动重发。
     *
     * @param array $claim 认领时返回的参数。
     * @return void 无返回值。
     */
    private function recordLocalCommitFailure(array $claim): void
    {
        DB::transaction(function () use ($claim): void {
            $locked = $this->lockClaimForResult($claim);
            if ($locked === null) {
                return;
            }
            [$order, $outbox] = $locked;

            $this->markUnknown(
                $order,
                $outbox,
                'local_commit_after_external_success_failed'
            );
        }, 3);
    }
}
