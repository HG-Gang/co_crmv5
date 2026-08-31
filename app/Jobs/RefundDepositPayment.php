<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:50
 */

/**
 * 入金退款队列任务。
 *
 * 文件功能：
 * - 消费 payment_settlement_outbox 中 event_type=deposit_refund 的出队记录，向 MT4 发起入金退款。
 * - 根据网关返回结果把入金单与出队记录推进到 refunded / retryable / unknown / rejected 等状态。
 *
 * 适用场景：
 * - 后台对已结算入金执行退款操作后由业务侧 dispatch 本任务。
 * - 定时任务 DispatchPendingDepositSettlements 兜底消费 pending / retryable 的退款出队记录。
 *
 * 入参例子：
 * - new RefundDepositPayment(31) 处理 payment_settlement_outbox.id=31 的入金退款事件。
 *
 * 方法功能：
 * - __construct(int $outboxId)：保存待处理的退款出队记录 ID。
 * - handle(DepositRefundGateway $gateway)：声明任务后调用网关退款，并按结果状态落库。
 * - backoff()：返回失败重试退避秒数 [60, 300]。
 *
 * 幂等要点：
 * - claim() 通过行锁把出队记录置为 processing，仅允许 pending/retryable 状态被声明，防止并发重复退款；
 * - 入金单已处于 refunded 时直接同步出队记录终态，不做二次退款。
 *
 * 异常或失败场景：
 * - 网关返回未知状态或 MT4 退款凭证号非数字时抛出 RuntimeException。
 * - 入金单缺失、退款前置状态不满足或 payload_hash 不一致时出队记录被置为 rejected。
 * - 处理中声明超过 5 分钟未完成时按 stale_refund_processing_claim 置为 unknown。
 */
declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\DepositRefundGateway;
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

final class RefundDepositPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 最大重试次数固定为 3：配合 backoff()=[60,300] 覆盖 MT4 退款短暂失败；
     * 退款是真实资金回退操作，超限后进入失败队列转人工核对 outbox 的 unknown 终态，
     * 防止队列无限重试造成重复退款。记录层幂等由行锁认领 + 状态校验兜底。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 待处理的入金退款 outbox 记录主键（payment_settlement_outbox.id，event_type=deposit_refund）。
     * 任务只携带 ID，退款金额与 MT4 登录号等参数由处理器按记录认领后读取，
     * 保证同一记录只被行锁声明的唯一任务消费一次。
     *
     * @var int
     */
    private $outboxId;

    /**
     * 保存待处理的退款出队记录 ID。
     *
     * @param int $outboxId payment_settlement_outbox 主键,handle() 只处理该条 event_type=deposit_refund 的记录。
     */
    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
    }

    /**
     * 声明任务并调用 MT4 网关执行入金退款，按网关结果落库。
     *
     * @param DepositRefundGateway $gateway MT4 入金退款网关（容器注入）。
     * @return void 无返回值；网关结果无法归类时抛 RuntimeException，由队列重试。
     */
    public function handle(DepositRefundGateway $gateway): void
    {
        // 阶段1 认领：行锁把出队记录置为 processing；不可认领时直接退出。
        $claim = $this->claim();
        if ($claim === null) {
            return;
        }

        // 阶段2 外部退款：settled 表示 MT4 侧退款成功。
        $result = $gateway->refund($claim['user_id'], $claim['amount'], $claim['comment']);
        if ($result->status() === 'settled') {
            // 阶段3 标记终态：本地落库失败时置 unknown 等人工核对，禁止伪造成功或自动重发。
            try {
                $this->recordRefunded($result);
            } catch (Throwable $exception) {
                $this->recordLocalCommitFailure();
            }

            return;
        }
        if ($result->status() === 'retryable_not_sent') {
            // 网关确认未发出退款指令：回退状态并延迟重试。
            $this->recordRetryable($result);

            return;
        }
        if (in_array($result->status(), ['unknown', 'rejected'], true)) {
            // 网关无法确认结果或明确拒绝：写入终态，禁止自动重发。
            $this->recordTerminal($result);

            return;
        }

        throw new RuntimeException('Unsupported deposit refund result.');
    }

    /**
     * 返回失败重试的退避秒数序列。
     *
     * 重试间隔先 60 秒后 300 秒;任务最多尝试 3 次(tries=3),超过后由队列标记失败。
     *
     * @return array<int, int> 依次等待的秒数。
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * 认领入金退款任务：事务内行锁校验状态并推进，返回网关退款所需参数。
     *
     * @return array{user_id: int, amount: string, comment: string}|null
     *         可执行退款的参数；不可认领（终态/未到期/记录缺失/前置状态不满足）时返回 null。
     */
    private function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            // 行锁读取：仅 event_type=deposit_refund 的退款事件可被认领。
            $outbox = PaymentSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->first();
            if (!$outbox || $outbox->event_type !== 'deposit_refund') {
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
                // 终态记录不再重复退款。
                return null;
            }
            if ($outbox->available_at !== null && $outbox->available_at->isFuture()) {
                // 重试退避期未到，本次扫描跳过。
                return null;
            }

            // 锁定入金单：不存在、前置状态不满足或 payload 不同源时拒绝，不做二次退款。
            $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->first();
            if (!$order) {
                $this->rejectOutbox($outbox, 'deposit_record_missing');

                return null;
            }
            if ($order->settlement_status === 'refunded') {
                // 入金单已退款成功：仅同步出队记录终态（复用订单的 ticket 与时间），不重复退款。
                $outbox->status = 'refunded';
                $outbox->provider_reference = (string) $order->refund_mt4_ticket;
                $outbox->processed_at = $order->refund_time ?? now();
                $outbox->locked_at = null;
                $outbox->last_error_code = null;
                $outbox->saveOrFail();

                return null;
            }
            if ($order->payment_status !== 'refund_pending' || $order->settlement_status !== 'settled') {
                // 退款前置状态不满足（如尚未审核通过），拒绝本次退款。
                $this->rejectOutbox($outbox, 'refund_not_ready');

                return null;
            }
            if (!hash_equals((string) $order->provider_payload_hash, (string) $outbox->payload_hash)) {
                // payload 哈希不一致说明出队记录与入金单不同源，拒绝退款。
                $this->rejectOutbox($outbox, 'payload_hash_mismatch');

                return null;
            }

            // 正式认领：attempts 递增并记录 locked_at，作为并发排他与结果校验依据。
            $outbox->status = 'processing';
            $outbox->attempts = (int) $outbox->attempts + 1;
            $outbox->locked_at = now();
            $outbox->saveOrFail();

            $order->payment_status = 'refund_processing';
            $order->settlement_status = 'refund_processing';
            $order->saveOrFail();

            return [
                'user_id' => (int) $order->user_id,
                'amount' => (string) $order->amount,
                'comment' => \App\Constants\Mt4RemarkCodes::DBRF . $order->user_id . '-#' . $order->local_order_no,
            ];
        }, 3);
    }

    /**
     * 记录网关确认退款成功：写 MT4 ticket 与 refunded 终态。
     *
     * @param DepositSettlementResult $result 网关退款结果。
     * @return void 无返回值；引用号非数字时抛 RuntimeException。
     */
    private function recordRefunded(DepositSettlementResult $result): void
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
                // MT4 退款凭证号必须是纯数字，否则抛异常重试，不落坏数据。
                throw new RuntimeException('MT4 refund reference must be numeric.');
            }

            $order->refund_mt4_ticket = (int) $reference;
            $order->payment_status = 'refunded';
            $order->settlement_status = 'refunded';
            $order->status = '05';
            $refundTime = now();
            $order->refund_time = $refundTime;
            $order->saveOrFail();

            $outbox->status = 'refunded';
            $outbox->provider_reference = $reference;
            $outbox->processed_at = $refundTime;
            $outbox->locked_at = null;
            $outbox->last_error_code = null;
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 记录网关确认未发出退款：入金单回退 refund_pending/settled，出队记录延迟重试。
     *
     * @param DepositSettlementResult $result 网关退款结果。
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
            $order->payment_status = 'refund_pending';
            $order->settlement_status = 'settled';
            $order->saveOrFail();

            // 未发出退款：回退 retryable，退避时长随 attempts 递增（上限 600 秒）。
            $outbox->status = 'retryable';
            $outbox->available_at = now()->addSeconds(60 * max(1, min((int) $outbox->attempts, 10)));
            $outbox->locked_at = null;
            $outbox->last_error_code = $result->errorCode();
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 记录网关结果未知或拒绝：入金单与出队记录直接落终态，禁止自动重发。
     *
     * @param DepositSettlementResult $result 网关退款结果（unknown/rejected）。
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
            $order->payment_status = $result->status() === 'unknown' ? 'refund_unknown' : 'refund_rejected';
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
     * 前置校验失败时关闭出队记录：置 rejected 终态，不再被调度命令捞起。
     *
     * @param PaymentSettlementOutbox $outbox 出队记录。
     * @param string $errorCode 失败原因码（写入 last_error_code 供人工排查）。
     * @return void 无返回值。
     */
    private function rejectOutbox(PaymentSettlementOutbox $outbox, string $errorCode): void
    {
        $outbox->status = 'rejected';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->last_error_code = $errorCode;
        $outbox->saveOrFail();
    }

    /**
     * 处理中声明超过 5 分钟未完成时回收：入金单与出队记录均置 unknown 等待人工核对。
     *
     * @param PaymentSettlementOutbox $outbox 已锁定且处于 processing 的出队记录。
     * @return void 无返回值。
     */
    private function markStaleClaimUnknown(PaymentSettlementOutbox $outbox): void
    {
        // 入金单处于退款相关状态时才联动转 unknown；已终态（如 refunded）保持原状。
        $order = DepositRecord::whereKey($outbox->deposit_record_id)->lockForUpdate()->first();
        if ($order && in_array($order->payment_status, ['refund_pending', 'refund_processing'], true)) {
            $order->payment_status = 'refund_unknown';
            $order->settlement_status = 'unknown';
            $order->saveOrFail();
        }

        $outbox->status = 'unknown';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->last_error_code = 'stale_refund_processing_claim';
        $outbox->saveOrFail();
    }

    /**
     * 外部退款成功但本地落库失败时的失败关闭：置 unknown 等人工核对，禁止自动重发。
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
            if ($order && $order->payment_status === 'refund_processing') {
                $order->payment_status = 'refund_unknown';
                $order->settlement_status = 'unknown';
                $order->saveOrFail();
            }

            $outbox->status = 'unknown';
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->provider_reference = null;
            $outbox->last_error_code = 'local_commit_after_external_refund_success_failed';
            $outbox->saveOrFail();
        }, 3);
    }
}
