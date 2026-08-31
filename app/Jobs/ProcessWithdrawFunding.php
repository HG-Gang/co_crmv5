<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:59
 */

/**
 * 出金扣款队列任务。
 *
 * 文件功能：
 * - 消费 withdraw_settlement_outbox 中 event_type=withdraw_debit 的出队记录，向 MT4 发起出金扣款。
 * - 根据网关返回结果把出金单与出队记录推进到 debited / retryable / unknown / rejected 等终态或可重试状态。
 *
 * 适用场景：
 * - 后台审核通过出金申请后由业务侧 dispatch 本任务。
 * - 定时任务 DispatchPendingWithdrawSettlements 兜底消费 pending / retryable 的出队记录。
 *
 * 入参例子：
 * - new ProcessWithdrawFunding(7) 处理 withdraw_settlement_outbox.id=7 的出金扣款事件。
 *
 * 方法功能：
 * - __construct(int $outboxId)：保存待处理的出金结算出队记录 ID。
 * - handle(WithdrawalFundingGateway $gateway)：声明任务后调用网关扣款，并按结果状态落库。
 * - backoff()：返回失败重试退避秒数 [60, 300]。
 *
 * 幂等要点：
 * - claim() 通过行锁把出队记录置为 processing 并记录 attempts/locked_at，防止并发重复扣款；
 * - recordDebited/recordRetryable/recordTerminal 均校验 ownsClaim()（状态+attempts 匹配）后才落库，
 *   本地提交失败时置为 unknown 等待人工核对，禁止自动重发。
 *
 * 异常或失败场景：
 * - 网关返回未知状态或 MT4 凭证号非法时抛出 RuntimeException。
 * - 出金单缺失、payload_hash 不一致时出队记录被置为 rejected。
 * - 处理中声明超过 5 分钟未完成时按 stale_processing_claim 置为 unknown。
 */
declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WithdrawalFundingGateway;
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

final class ProcessWithdrawFunding implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 最大重试次数固定为 3：配合 backoff()=[60,300] 覆盖 MT4 扣款短暂失败；
     * 出金扣款是对真实资金账户的写操作，超限后进入失败队列由人工核对 outbox 的
     * unknown/terminal 状态，禁止队列无限重试放大扣款副作用。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 待处理的出金扣款 outbox 记录主键（withdraw_settlement_outbox.id，event_type=withdraw_debit）。
     * 任务只携带 ID，扣款金额、MT4 登录号等参数由处理器按记录认领后读取，
     * 保证同一记录只会被行锁声明的唯一任务消费一次。
     *
     * @var int
     */
    private $outboxId;

    /**
     * 构造出金扣款任务。
     *
     * @param int $outboxId withdraw_settlement_outbox 中待处理的出金扣款事件主键（event_type=withdraw_debit）。
     */
    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
    }

    /**
     * 声明任务并调用 MT4 网关执行出金扣款，按网关结果落库。
     *
     * @param WithdrawalFundingGateway $gateway MT4 出金网关（容器注入）。
     * @return void 无返回值；网关结果无法归类时抛 RuntimeException，由队列重试。
     */
    public function handle(WithdrawalFundingGateway $gateway): void
    {
        // 阶段1 认领：行锁把出队记录置为 processing 并返回扣款参数；不可认领（终态/未到期/已被处理）时直接退出。
        $claim = $this->claim();
        if ($claim === null) {
            return;
        }

        // 阶段2 外部扣款：网关返回 debited 表示 MT4 侧已扣款成功。
        $result = $gateway->withdraw($claim['user_id'], $claim['amount'], $claim['comment']);
        if ($result->status() === 'debited') {
            // 阶段3 标记终态：写回 MT4 ticket 与 debited；本地提交失败时不得伪造成功，
            // 转 unknown 等人工核对，防止"钱已扣但单未记"被自动重发。
            try {
                $this->recordDebited($result, $claim['attempt']);
            } catch (Throwable $exception) {
                $this->recordLocalCommitFailure($claim['attempt']);
            }

            return;
        }
        if ($result->status() === 'retryable_not_sent') {
            // 网关确认未发出扣款指令：回退状态并延迟重试（退避随 attempts 递增）。
            $this->recordRetryable($result, $claim['attempt']);

            return;
        }
        if (in_array($result->status(), ['unknown', 'rejected'], true)) {
            // 网关无法确认结果或明确拒绝：直接写入终态，禁止自动重发。
            $this->recordTerminal($result, $claim['attempt']);

            return;
        }

        throw new RuntimeException('Unsupported withdrawal funding result.');
    }

    /**
     * 返回队列失败重试的退避秒数，随尝试次数递增。
     *
     * @return array<int, int> 重试间隔数组，第 1 次失败等 60 秒，第 2 次失败等 300 秒。
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * 认领出金扣款任务：事务内行锁校验并推进状态，返回网关扣款所需参数。
     *
     * 认领成功后出队记录与出金单均置为 processing，attempts/locked_at 作为
     * 本次声明的所有权凭证，后续结果落库必须匹配该凭证（见 ownsClaim）。
     *
     * @return array{user_id: int, amount: string, comment: string, attempt: int}|null
     *         可执行扣款的参数；不可认领（终态/未到期/记录缺失/已被处理）时返回 null。
     */
    private function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            // 行锁读取出队记录：仅事件类型匹配的扣款事件才可被认领。
            $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->first();
            if (!$outbox || $outbox->event_type !== 'withdraw_debit') {
                return null;
            }
            if ($outbox->status === 'processing') {
                // 处理中声明已超 5 分钟视为陈旧：置 unknown 交人工核对，防止卡死队列。
                if ($outbox->locked_at === null || $outbox->locked_at->lte(now()->subMinutes(5))) {
                    $this->markStaleClaimUnknown($outbox);
                }

                return null;
            }
            if (!in_array($outbox->status, ['pending', 'retryable'], true)) {
                // 终态（processed/unknown/rejected/cancelled 等）不再重复扣款。
                return null;
            }
            if ($outbox->available_at !== null && $outbox->available_at->isFuture()) {
                // 重试退避期未到，本次扫描跳过。
                return null;
            }

            // 锁定出金单：不存在或资金状态不允许时按对账逻辑收尾，不发起扣款。
            $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)->lockForUpdate()->first();
            if (!$order) {
                $this->rejectOutbox($outbox, 'withdraw_record_missing');

                return null;
            }
            if (!in_array($order->funding_status, ['pending', 'retryable'], true)) {
                $this->reconcileTerminalOrderState($order, $outbox);

                return null;
            }
            if (!hash_equals((string) $order->funding_payload_hash, (string) $outbox->payload_hash)) {
                // payload 哈希不一致说明出队记录与出金单不同源，禁止扣款并双双置为 rejected。
                $order->funding_status = 'rejected';
                $order->funding_error_code = 'payload_hash_mismatch';
                $order->status = 3;
                $order->saveOrFail();
                $this->rejectOutbox($outbox, 'payload_hash_mismatch');

                return null;
            }

            // 正式认领：attempts 递增、locked_at 记本次声明时间，作为并发排他与结果校验依据。
            $outbox->status = 'processing';
            $outbox->attempts = (int) $outbox->attempts + 1;
            $outbox->locked_at = now();
            $outbox->saveOrFail();

            $order->funding_status = 'processing';
            $order->funding_error_code = null;
            $order->saveOrFail();

            return [
                'user_id' => (int) $order->user_id,
                'amount' => (string) $order->apply_amount,
                'comment' => \App\Constants\Mt4RemarkCodes::WDUN . $order->user_id . '-#' . $order->local_order_no,
                'attempt' => (int) $outbox->attempts,
            ];
        }, 3);
    }

    /**
     * 记录网关确认扣款成功：写 MT4 ticket 与 debited 终态，并联动解冻被阻塞的退款。
     *
     * @param WithdrawalFundingResult $result 网关扣款结果。
     * @param int $claimAttempt 认领时的 attempts 值，用于确认仍持有本次声明。
     * @return void 无返回值；引用号非法时抛 RuntimeException。
     */
    private function recordDebited(WithdrawalFundingResult $result, int $claimAttempt): void
    {
        DB::transaction(function () use ($result, $claimAttempt): void {
            // 结果落库前再次校验声明所有权，防止延迟结果覆盖他人新的声明。
            $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->firstOrFail();
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return;
            }
            $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)->lockForUpdate()->firstOrFail();
            $reference = (string) $result->providerReference();
            if (!ctype_digit($reference) || (int) $reference <= 0) {
                // MT4 扣款凭证号必须是正整数；非法则抛异常触发重试，而不是落库坏数据。
                throw new RuntimeException('MT4 withdrawal reference must be a positive integer.');
            }

            $order->mt4_ticket = $reference;
            $order->funding_status = 'debited';
            $order->funding_error_code = null;
            $order->saveOrFail();

            // 若有因扣款失败而阻塞的退款事件，扣款成功即解冻为 pending，等待退款流程消费。
            $refund = WithdrawSettlementOutbox::where('withdraw_record_id', $order->id)
                ->where('event_type', 'withdraw_refund')->lockForUpdate()->first();
            if ($refund && $refund->status === 'blocked') {
                $refund->status = 'pending';
                $refund->available_at = time();
                $refund->saveOrFail();
                $order->funding_status = 'refund_pending';
                $order->saveOrFail();
            }

            $outbox->status = 'processed';
            $outbox->provider_reference = $reference;
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->last_error_code = null;
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 记录网关确认未发出扣款：出金单回退 pending，出队记录延迟重试。
     *
     * 若存在因扣款失败而阻塞的退款，说明该出金单已决定不扣款，
     * 改为同时取消扣款与退款并落 cancelled 终态。
     *
     * @param WithdrawalFundingResult $result 网关扣款结果。
     * @param int $claimAttempt 认领时的 attempts 值。
     * @return void 无返回值。
     */
    private function recordRetryable(WithdrawalFundingResult $result, int $claimAttempt): void
    {
        DB::transaction(function () use ($result, $claimAttempt): void {
            $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->firstOrFail();
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return;
            }
            $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)->lockForUpdate()->firstOrFail();
            $refund = WithdrawSettlementOutbox::where('withdraw_record_id', $order->id)
                ->where('event_type', 'withdraw_refund')->lockForUpdate()->first();
            if ($refund && $refund->status === 'blocked') {
                // 有被阻塞的退款表示该出金不再执行扣款：出金单取消，退款与出队记录落 cancelled。
                $order->status = 3;
                $order->funding_status = 'cancelled';
                $order->funding_error_code = $result->errorCode();
                $order->saveOrFail();

                $refund->status = 'cancelled';
                $refund->processed_at = now();
                $refund->locked_at = null;
                $refund->last_error_code = 'debit_not_completed';
                $refund->saveOrFail();

                $outbox->status = 'cancelled';
                $outbox->processed_at = now();
                $outbox->locked_at = null;
                $outbox->last_error_code = $result->errorCode();
                $outbox->saveOrFail();

                return;
            }

            // 未发出扣款且无阻塞退款：回退到 pending，退避时长随 attempts 递增（上限 600 秒）再重试。
            $order->funding_status = 'pending';
            $order->funding_error_code = $result->errorCode();
            $order->saveOrFail();

            $outbox->status = 'retryable';
            $outbox->available_at = now()->addSeconds(60 * max(1, min((int) $outbox->attempts, 10)));
            $outbox->locked_at = null;
            $outbox->last_error_code = $result->errorCode();
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 记录网关结果未知或拒绝：出金单与出队记录直接落终态，禁止自动重发。
     *
     * @param WithdrawalFundingResult $result 网关扣款结果（unknown/rejected）。
     * @param int $claimAttempt 认领时的 attempts 值。
     * @return void 无返回值。
     */
    private function recordTerminal(WithdrawalFundingResult $result, int $claimAttempt): void
    {
        DB::transaction(function () use ($result, $claimAttempt): void {
            $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->firstOrFail();
            if (!$this->ownsClaim($outbox, $claimAttempt)) {
                return;
            }
            $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)->lockForUpdate()->firstOrFail();
            $order->funding_status = $result->status();
            $order->funding_error_code = $result->errorCode();
            if ($result->status() === 'rejected') {
                // 被网关拒绝等同于出金不成立，出金单整体关闭。
                $order->status = 3;
            }
            $order->saveOrFail();
            if ($result->status() === 'rejected') {
                $this->cancelBlockedRefund($order);
            }

            $outbox->status = $result->status();
            $outbox->processed_at = now();
            $outbox->locked_at = null;
            $outbox->last_error_code = $result->errorCode();
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 处理中声明超过 5 分钟未完成时回收：出金单与出队记录均置 unknown 等待人工核对。
     *
     * @param WithdrawSettlementOutbox $outbox 已锁定且处于 processing 的出队记录。
     * @return void 无返回值。
     */
    private function markStaleClaimUnknown(WithdrawSettlementOutbox $outbox): void
    {
        $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)->lockForUpdate()->first();
        if ($order && in_array($order->funding_status, ['pending', 'retryable', 'processing'], true)) {
            // 出金单资金状态尚未终态时才转 unknown；已终态（如 debited）保持原状。
            $order->funding_status = 'unknown';
            $order->funding_error_code = 'stale_processing_claim';
            $order->saveOrFail();
        }
        $outbox->status = 'unknown';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->last_error_code = 'stale_processing_claim';
        $outbox->saveOrFail();
    }

    /**
     * 出金单已处于终态时，按资金状态对账出队记录终态，避免重复扣款。
     *
     * @param WithdrawRecord $order 已锁定出金单。
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @return void 无返回值。
     */
    private function reconcileTerminalOrderState(WithdrawRecord $order, WithdrawSettlementOutbox $outbox): void
    {
        $status = (string) $order->funding_status;
        if ($status === 'debited' && ctype_digit((string) $order->mt4_ticket) && (int) $order->mt4_ticket > 0) {
            // 出金单已扣款成功：补记 ticket 并把出队记录同步为 processed。
            $outbox->status = 'processed';
            $outbox->provider_reference = (string) $order->mt4_ticket;
            $outbox->last_error_code = null;
        } elseif (in_array($status, ['unknown', 'rejected', 'cancelled'], true)) {
            // 出金单终态即出队记录终态。
            $outbox->status = $status;
            $outbox->last_error_code = 'order_already_' . $status;
        } else {
            // 意外状态不能乱猜：置 unknown 交人工处理。
            $outbox->status = 'unknown';
            $outbox->last_error_code = 'unexpected_order_funding_status';
        }
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->saveOrFail();
    }

    /**
     * 前置校验失败时关闭出队记录：置 rejected 终态，不再被调度命令捞起。
     *
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @param string $errorCode 失败原因码（写入 last_error_code 供人工排查）。
     * @return void 无返回值。
     */
    private function rejectOutbox(WithdrawSettlementOutbox $outbox, string $errorCode): void
    {
        $outbox->status = 'rejected';
        $outbox->processed_at = now();
        $outbox->locked_at = null;
        $outbox->last_error_code = $errorCode;
        $outbox->saveOrFail();
    }

    /**
     * 外部扣款成功但本地落库失败时的失败关闭：置 unknown 等人工核对，禁止自动重发。
     *
     * @param int $claimAttempt 认领时的 attempts 值。
     * @return void 无返回值。
     */
    private function recordLocalCommitFailure(int $claimAttempt): void
    {
        DB::transaction(function () use ($claimAttempt): void {
            // 仍须校验声明所有权，避免覆盖已被他人处理或回收的记录。
            $outbox = WithdrawSettlementOutbox::whereKey($this->outboxId)->lockForUpdate()->first();
            if (!$outbox || !$this->ownsClaim($outbox, $claimAttempt)) {
                return;
            }
            $order = WithdrawRecord::whereKey($outbox->withdraw_record_id)->lockForUpdate()->first();
            if ($order && $order->funding_status === 'processing') {
                // 外部已扣款成功而本地状态未知：必须以 unknown 呈现，防止系统误判"未扣款"而重发。
                $order->funding_status = 'unknown';
                $order->funding_error_code = 'local_commit_after_external_success_failed';
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
     * 校验出队记录是否仍属于本次声明：status=processing 且 attempts 与认领时一致。
     *
     * 防止慢任务返回结果覆盖已被回收或重新认领的记录。
     *
     * @param WithdrawSettlementOutbox $outbox 出队记录。
     * @param int $claimAttempt 认领时的 attempts 值。
     * @return bool true=仍持有本次声明。
     */
    private function ownsClaim(WithdrawSettlementOutbox $outbox, int $claimAttempt): bool
    {
        return $outbox->status === 'processing'
            && (int) $outbox->attempts === $claimAttempt;
    }

    /**
     * 出金被拒后取消被阻塞的退款事件，避免资金流向不确定时退款被激活。
     *
     * @param WithdrawRecord $order 已锁定出金单。
     * @return void 无返回值。
     */
    private function cancelBlockedRefund(WithdrawRecord $order): void
    {
        $refund = WithdrawSettlementOutbox::where('withdraw_record_id', $order->id)
            ->where('event_type', 'withdraw_refund')->lockForUpdate()->first();
        if ($refund && $refund->status === 'blocked') {
            $refund->status = 'cancelled';
            $refund->processed_at = now();
            $refund->last_error_code = 'debit_not_completed';
            $refund->saveOrFail();
        }
    }
}
