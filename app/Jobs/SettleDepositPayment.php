<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

/**
 * 入金结算队列任务。
 *
 * 文件功能：
 * - 消费 payment_settlement_outbox 中 event_type=deposit_settlement 的出队记录，向 MT4 写入入金信用或余额。
 * - 根据网关返回结果把入金单与出队记录推进到 settled / retryable / unknown / rejected 等状态。
 *
 * 适用场景：
 * - 后台审核通过入金申请后由业务侧 dispatch 本任务。
 * - 定时任务 DispatchPendingDepositSettlements 兜底消费 pending / retryable 的结算出队记录。
 *
 * 入参例子：
 * - new SettleDepositPayment(9) 处理 payment_settlement_outbox.id=9 的入金结算事件。
 *
 * 方法功能：
 * - __construct(int $outboxId)：保存待处理的入金结算出队记录 ID。
 * - handle(DepositSettlementGateway $gateway)：声明任务后调用网关入金，并按结果状态落库。
 * - backoff()：返回失败重试退避秒数 [60, 300]。
 *
 * 幂等要点：
 * - claim() 通过行锁把出队记录置为 processing，仅允许 pending/retryable 状态被声明，防止并发重复入金；
 * - 入金单已处于 settled 等终态时按 reconcileTerminalOrderState() 对账出队记录，不做二次结算；
 * - 存在 blocked 退款出队记录时，结算成功才激活退款、结算失败则联动处理，避免资金重复流转。
 *
 * 异常或失败场景：
 * - 网关返回未知状态或 MT4 结算凭证号非数字时抛出 RuntimeException。
 * - 入金单缺失、支付未成功或 payload_hash 不一致时出队记录被置为 rejected。
 * - 处理中声明超过 5 分钟未完成时按 stale_processing_claim 置为 unknown。
 */
declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\DepositSettlementGateway;
use App\Models\DepositRecord;
use App\Models\PaymentSettlementOutbox;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SettleDepositPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 最大重试次数固定为 3：配合 backoff()=[60,300] 覆盖 MT4 结算短暂失败；
     * 入金结算是向 MT4 写入真实余额/信用的资金操作，超限后进入失败队列转人工核对
     * unknown 终态，避免队列无限重试导致重复入金。幂等由记录层行锁认领兜底。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 待处理的入金结算 outbox 记录主键（payment_settlement_outbox.id，event_type=deposit_settlement）。
     * 任务只携带 ID，结算金额、MT4 登录号等参数由处理器按记录认领后读取，
     * 同一记录只能被行锁声明的唯一任务消费。
     *
     * @var int
     */
    private $outboxId;

    /**
     * 构造入金结算任务。
     *
     * @param int $outboxId payment_settlement_outbox 中待结算的出队记录主键。
     */
    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
    }

    /**
     * 声明任务并调用 MT4 网关执行入金结算，按网关结果落库。
     *
     * @param DepositSettlementGateway $gateway MT4 入金结算网关（容器注入）。
     * @return void 无返回值；网关结果无法归类时抛 RuntimeException，由队列重试。
     */
    public function handle(DepositSettlementGateway $gateway): void
    {
        // 阶段1 认领：行锁把出队记录置为 processing；不可认领时直接退出。
        $claim = $this->claim();
        if ($claim === null) {
            return;
        }

        // 阶段2 外部入金：settled 表示 MT4 侧结算成功。
        $result = $gateway->deposit($claim['user_id'], $claim['amount'], $claim['comment']);
        if ($result->status() === 'settled') {
            // 阶段3 标记终态：本地落库失败时置 unknown 等人工核对，禁止伪造成功或自动重发。
            try {
                $this->recordSettled($result);
            } catch (Throwable $exception) {
                $this->recordLocalCommitFailure();
            }

            return;
        }
        if ($result->status() === 'retryable_not_sent') {
            // 网关确认未发出结算指令：回退状态并延迟重试。
            $this->recordRetryable($result);

            return;
        }
        if (in_array($result->status(), ['unknown', 'rejected'], true)) {
            // 网关无法确认结果或明确拒绝：写入终态，禁止自动重发。
            $this->recordTerminal($result);

            return;
        }

        throw new RuntimeException('Unsupported deposit settlement result.');
    }

    /**
     * 队列失败重试退避时间表（秒）。
     *
     * 第 1 次重试等 60 秒，第 2 次等 300 秒；尝试次数由队列系统按 $tries 控制。
     *
     * @return array<int, int> 各次重试的等待秒数。
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * 认领入金结算任务：事务内行锁校验状态并推进，返回网关入金所需参数。
     *
     * @return array{user_id: int, amount: string, comment: string}|null
     *         可执行结算的参数；不可认领（终态/未到期/记录缺失/前置状态不满足）时返回 null。
     */
    private function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            // 行锁读取：仅 event_type=deposit_settlement 的结算事件可被认领。
            $outbox = PaymentSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->first();
            if (!$outbox) {
                return null;
            }
            if ($outbox->event_type !== 'deposit_settlement') {
                return null;
            }
            if ($outbox->status === 'processing') {
                // 处理中声明超 5 分钟视为陈旧：置 unknown 交人工核对，防止卡死队列。
                if ($outbox->locked_at === null || $outbox->locked_at->lte(now()->subMinutes(5))) {
                    $this->markStaleClaimUnknown($outbox);
                }

                return null;
            }
            if (!in_array($outbox->status, ['pending', 'retryable'], true)) {
                // 终态记录不再重复结算。
                return null;
            }
            if ($outbox->available_at !== null && $outbox->available_at->isFuture()) {
                // 重试退避期未到，本次扫描跳过。
                return null;
            }

            // 锁定入金单：不存在、支付未成功或 payload 不同源时拒绝结算。
            $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->first();
            if (!$order) {
                $outbox->status = 'rejected';
                $outbox->processed_at = now();
                $outbox->locked_at = null;
                $outbox->last_error_code = 'deposit_record_missing';
                $outbox->saveOrFail();

                return null;
            }
            if (!in_array($order->payment_status, ['success', 'refund_pending'], true)) {
                // 支付未成功不允许结算入金，拒绝并落 rejected。
                $outbox->status = 'rejected';
                $outbox->processed_at = now();
                $outbox->locked_at = null;
                $outbox->last_error_code = 'payment_not_success';
                $outbox->saveOrFail();

                return null;
            }
            if ($order->settlement_status !== 'pending') {
                // 入金单已非 pending：按终态对账出队记录，避免二次结算。
                $this->reconcileTerminalOrderState($order, $outbox);

                return null;
            }
            if (!hash_equals((string) $order->provider_payload_hash, (string) $outbox->payload_hash)) {
                // payload 哈希不一致说明不同源：入金单与出队记录均拒绝。
                $order->settlement_status = 'rejected';
                $order->saveOrFail();

                $outbox->status = 'rejected';
                $outbox->processed_at = now();
                $outbox->locked_at = null;
                $outbox->last_error_code = 'payload_hash_mismatch';
                $outbox->saveOrFail();

                return null;
            }

            // 正式认领：attempts 递增并记录 locked_at，作为并发排他与结果校验依据。
            $outbox->status = 'processing';
            $outbox->attempts = (int) $outbox->attempts + 1;
            $outbox->locked_at = now();
            $outbox->saveOrFail();

            $order->settlement_status = 'processing';
            $order->saveOrFail();

            return [
                'user_id' => (int) $order->user_id,
                'amount' => (string) $order->amount,
                'comment' => \App\Constants\Mt4RemarkCodes::DBUN . $order->user_id . '-#' . $order->local_order_no,
            ];
        }, 3);
    }

    /**
     * 记录网关确认结算成功：写 MT4 ticket 与 settled 终态，并激活被阻塞的退款事件。
     *
     * @param DepositSettlementResult $result 网关结算结果。
     * @return void 无返回值；引用号非数字时抛 RuntimeException。
     */
    private function recordSettled(DepositSettlementResult $result): void
    {
        DB::transaction(function () use ($result): void {
            // 仅处理仍处于 processing 声明的记录，防止并发覆盖。
            $outbox = PaymentSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->firstOrFail();
            if ($outbox->status !== 'processing') {
                return;
            }

            $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->firstOrFail();
            $reference = (string) $result->providerReference();
            if (!ctype_digit($reference)) {
                // MT4 结算凭证号必须是纯数字，否则抛异常重试，不落坏数据。
                throw new RuntimeException('MT4 settlement reference must be numeric.');
            }

            $order->mt4_ticket = (int) $reference;
            $order->settlement_status = 'settled';
            $order->status = '02';
            $order->saveOrFail();

            $outbox->status = 'processed';
            $outbox->provider_reference = $reference;
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->last_error_code = null;
            $outbox->saveOrFail();

            // 结算成功后才允许退款被激活，避免"资金未入账就退款"的资金重复流转。
            $this->activateBlockedRefund($order);
        }, 3);
    }

    /**
     * 记录网关确认未发出结算：存在被阻塞退款时按"无需冲正"直接关闭，否则回退延迟重试。
     *
     * @param DepositSettlementResult $result 网关结算结果。
     * @return void 无返回值。
     */
    private function recordRetryable(DepositSettlementResult $result): void
    {
        DB::transaction(function () use ($result): void {
            $outbox = PaymentSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->firstOrFail();
            if ($outbox->status !== 'processing') {
                return;
            }

            $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->firstOrFail();
            if ($this->completeBlockedRefundWithoutReverse($order, $outbox)) {
                // 有被阻塞的退款：说明该笔入金已决定退款，结算未完成即视为整体完成（无需冲正）。
                return;
            }
            // 未发出结算且无阻塞退款：回退 pending，退避时长随 attempts 递增（上限 600 秒）。
            $order->settlement_status = 'pending';
            $order->saveOrFail();

            $outbox->status = 'retryable';
            $outbox->available_at = now()->addSeconds(60 * max(1, min((int) $outbox->attempts, 10)));
            $outbox->locked_at = null;
            $outbox->last_error_code = $result->errorCode();
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 记录网关结果未知或拒绝：优先联动被阻塞的退款事件，否则落终态。
     *
     * @param DepositSettlementResult $result 网关结算结果（unknown/rejected）。
     * @return void 无返回值。
     */
    private function recordTerminal(DepositSettlementResult $result): void
    {
        DB::transaction(function () use ($result): void {
            $outbox = PaymentSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->firstOrFail();
            if ($outbox->status !== 'processing') {
                return;
            }

            $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->firstOrFail();
            // 存在被阻塞的退款时，结算结果直接影响退款去向：拒绝则按退款关闭，未知则双方转 unknown。
            if ($this->coordinateBlockedRefundTerminal($order, $outbox, $result)) {
                return;
            }
            $order->settlement_status = $result->status();
            $order->saveOrFail();

            $outbox->status = $result->status();
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->last_error_code = $result->errorCode();
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 处理中声明超过 5 分钟未完成时回收：入金单与出队记录均置 unknown 等待人工核对。
     *
     * @param PaymentSettlementOutbox $outbox 已锁定且处于 processing 的出队记录。
     * @return void 无返回值。
     */
    private function markStaleClaimUnknown(PaymentSettlementOutbox $outbox): void
    {
        // 入金单结算状态尚未终态时才联动转 unknown；已终态（如 settled）保持原状。
        $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->first();
        if ($order && in_array($order->settlement_status, ['pending', 'processing'], true)) {
            $order->settlement_status = 'unknown';
            if ($this->markBlockedRefundUnknown($order)) {
                $order->payment_status = 'refund_unknown';
            }
            $order->saveOrFail();
        }

        $outbox->status = 'unknown';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->last_error_code = 'stale_processing_claim';
        $outbox->saveOrFail();
    }

    /**
     * 入金单已处于终态时，按结算状态对账出队记录终态，避免二次结算。
     *
     * @param DepositRecord $order 已锁定入金单。
     * @param PaymentSettlementOutbox $outbox 出队记录。
     * @return void 无返回值。
     */
    private function reconcileTerminalOrderState(
        DepositRecord $order,
        PaymentSettlementOutbox $outbox
    ): void {
        $status = (string) $order->settlement_status;
        if ($status === 'settled') {
            $ticket = (int) $order->mt4_ticket;
            if ($ticket > 0) {
                // 已结算且有凭证：补记 ticket 并把出队记录同步为 processed。
                $outbox->status = 'processed';
                $outbox->provider_reference = (string) $ticket;
                $outbox->last_error_code = null;
            } else {
                // 已结算却无凭证：数据异常，置 unknown 待人工核对。
                $outbox->status = 'unknown';
                $outbox->last_error_code = 'settled_order_missing_ticket';
            }
        } elseif (in_array($status, ['unknown', 'rejected', 'refunded'], true)) {
            // 入金单终态即出队记录终态。
            $outbox->status = $status;
            $outbox->last_error_code = 'order_already_' . $status;
        } else {
            // 意外状态不能乱猜：置 unknown 交人工处理。
            $outbox->status = 'unknown';
            $outbox->last_error_code = 'unexpected_order_settlement_status';
        }

        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->saveOrFail();
    }

    /**
     * 外部结算成功但本地落库失败时的失败关闭：置 unknown 等人工核对，禁止自动重发。
     *
     * @return void 无返回值。
     */
    private function recordLocalCommitFailure(): void
    {
        DB::transaction(function (): void {
            // 仅处理仍处于 processing 声明的记录，防止覆盖已被回收的声明。
            $outbox = PaymentSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->first();
            if (!$outbox || $outbox->status !== 'processing') {
                return;
            }

            $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->first();
            if ($order && $order->settlement_status === 'processing') {
                $order->settlement_status = 'unknown';
                if ($this->markBlockedRefundUnknown($order)) {
                    $order->payment_status = 'refund_unknown';
                }
                $order->saveOrFail();
            }

            $outbox->status = 'unknown';
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->provider_reference = null;
            $outbox->last_error_code = 'local_commit_after_external_success_failed';
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 结算成功后解冻被阻塞的退款事件：置 pending 等待退款流程消费。
     *
     * @param DepositRecord $order 已锁定入金单。
     * @return void 无返回值。
     */
    private function activateBlockedRefund(DepositRecord $order): void
    {
        $refund = PaymentSettlementOutbox::where('deposit_record_id', $order->id)
            ->where('event_type', 'deposit_refund')
            ->where('status', 'blocked')
            ->lockForUpdate()
            ->first();
        if (!$refund) {
            return;
        }

        $refund->status = 'pending';
        $refund->available_at = now();
        $refund->locked_at = null;
        $refund->last_error_code = null;
        $refund->saveOrFail();
    }

    /**
     * 结算未完成但退款已决意执行时整体关闭：入金单、结算出队记录与退款事件均落 refunded 终态。
     *
     * @param DepositRecord $order 已锁定入金单。
     * @param PaymentSettlementOutbox $depositOutbox 结算出队记录。
     * @return bool true=存在被阻塞退款并已按此路径关闭。
     */
    private function completeBlockedRefundWithoutReverse(
        DepositRecord $order,
        PaymentSettlementOutbox $depositOutbox
    ): bool {
        $refund = $this->blockedRefund($order);
        if (!$refund) {
            return false;
        }

        // 资金未入账即进入退款：无需 MT4 冲正，直接把三处状态落为退款终态。
        $order->payment_status = 'refunded';
        $order->settlement_status = 'refunded';
        $order->status = '05';
        $order->saveOrFail();

        $depositOutbox->status = 'refunded';
        $depositOutbox->processed_at = now();
        $depositOutbox->locked_at = null;
        $depositOutbox->last_error_code = 'deposit_not_settled';
        $depositOutbox->saveOrFail();

        $refund->status = 'processed';
        $refund->processed_at = now();
        $refund->locked_at = null;
        $refund->last_error_code = 'deposit_not_settled_no_reverse_needed';
        $refund->saveOrFail();

        return true;
    }

    /**
     * 结算结果未知/拒绝时联动退款：拒绝则按退款关闭，未知则双方转 unknown 等人工核对。
     *
     * @param DepositRecord $order 已锁定入金单。
     * @param PaymentSettlementOutbox $depositOutbox 结算出队记录。
     * @param DepositSettlementResult $result 网关结算结果。
     * @return bool true=已联动处理完成。
     */
    private function coordinateBlockedRefundTerminal(
        DepositRecord $order,
        PaymentSettlementOutbox $depositOutbox,
        DepositSettlementResult $result
    ): bool {
        $refund = $this->blockedRefund($order);
        if (!$refund) {
            return false;
        }
        // 结算被拒绝且存在被阻塞退款：直接按"无需冲正"关闭。
        if ($result->status() === 'rejected') {
            return $this->completeBlockedRefundWithoutReverse($order, $depositOutbox);
        }

        // 结算结果未知：退款既不能执行也不能关闭，双方置 unknown 等人工核对，防止资金双重流转。
        $order->payment_status = 'refund_unknown';
        $order->settlement_status = 'unknown';
        $order->saveOrFail();

        $depositOutbox->status = 'unknown';
        $depositOutbox->processed_at = now();
        $depositOutbox->locked_at = null;
        $depositOutbox->last_error_code = $result->errorCode();
        $depositOutbox->saveOrFail();

        $refund->status = 'unknown';
        $refund->processed_at = now();
        $refund->locked_at = null;
        $refund->last_error_code = 'deposit_result_unknown';
        $refund->saveOrFail();

        return true;
    }

    /**
     * 查找同一入金单下被阻塞的退款出队事件。
     *
     * 仅返回结算未完成期间存在的 status=blocked 退款事件，用于联动退款去向。
     *
     * @param DepositRecord $order 已锁定入金单。
     * @return PaymentSettlementOutbox|null 被阻塞的退款出队记录；不存在时返回 null。
     */
    private function blockedRefund(DepositRecord $order): ?PaymentSettlementOutbox
    {
        return PaymentSettlementOutbox::where('deposit_record_id', $order->id)
            ->where('event_type', 'deposit_refund')
            ->where('status', 'blocked')
            ->lockForUpdate()
            ->first();
    }

    /**
     * 把被阻塞的退款事件置为 unknown，防止结算结果未知时退款被误激活或丢失。
     *
     * @param DepositRecord $order 已锁定入金单。
     * @return bool true=存在被阻塞退款且已转 unknown；无退款事件时返回 false。
     */
    private function markBlockedRefundUnknown(DepositRecord $order): bool
    {
        $refund = $this->blockedRefund($order);
        if (!$refund) {
            return false;
        }

        $refund->status = 'unknown';
        $refund->processed_at = now();
        $refund->locked_at = null;
        $refund->last_error_code = 'deposit_result_unknown';
        $refund->saveOrFail();

        return true;
    }
}
