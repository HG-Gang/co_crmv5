<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:57
 */

declare(strict_types=1);

namespace App\Services\Payment;

use App\Jobs\RefundDepositPayment;
use App\Jobs\SettleDepositPayment;
use App\Models\DepositRecord;
use App\Models\PaymentSettlementOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * 支付回调处理服务。
 *
 * 文件功能：
 * - 接收支付网关回调，更新充值订单状态（pending / success / failed / refunded）。
 * - 成功时创建结算出箱记录，失败时处理退款或标记退款流程。
 * - 校验回调数据与订单信息的一致性（网关码、商户号、币种、金额）。
 *
 * 适用场景：
 * - 支付网关异步通知回调到达后，由回调控制器调用本服务处理。
 * - 支持 success、failed、refunded 三种回调状态的状态机推进。
 *
 * 入参例子：
 * - callback: PaymentCallback 值对象，包含 gatewayCode / localOrderNumber / providerOrderNumber / status / amount / currency / merchantId / payloadHash。
 *
 * 返回值：
 * - DepositRecord：更新后的订单记录。
 *
 * 异常或失败场景：
 * - InvalidArgumentException：订单不存在、回调标识不匹配、状态转换不支持。
 * - 队列投递失败时记录日志但不影响主流程返回。
 */
final class PaymentCallbackService
{
    /**
     * 处理支付回调并推进订单状态。
     *
     * 根据当前订单状态和回调传入的新状态，按状态机规则推进：
     * - pending -> success：更新支付成功，投递结算队列。
     * - pending -> failed：标记支付失败。
     * - success -> refunded：创建退款出箱记录并投递退款队列。
     * - 已终态（failed / refunded）直接返回当前订单。
     *
     * @param PaymentCallback $callback 支付网关回调值对象，包含订单标识和交易结果。
     *
     * @return DepositRecord 更新后的充值订单。
     *
     * @throws InvalidArgumentException 订单不存在、回调标识不匹配、状态转换非法时抛出。
     */
    public function handle(PaymentCallback $callback): DepositRecord
    {
        $dispatch = null;
        // 事务 + 行锁：同一订单的并发回调串行化，配合幂等状态机保证回调最多推进一次状态。
        $order = DB::transaction(function () use ($callback, &$dispatch): DepositRecord {
            $dispatch = null;
            // 按本地订单号行锁取单；取不到说明订单不存在，直接拒绝而非新建，防止凭空入账。
            $order = DepositRecord::where('local_order_no', $callback->localOrderNumber())
                ->lockForUpdate()
                ->first();
            if (!$order) {
                throw new InvalidArgumentException('Payment callback order was not found.');
            }

            // 回调身份必须与订单一致（网关码/商户号/币种/金额/供应商单号），防止串单。
            $this->assertIdentity($order, $callback);
            $current = strtolower(trim((string) $order->payment_status));
            $incoming = $callback->status();

            // success 终态只接受 refunded 推进，其余回调（含重复 success）直接幂等返回。
            if ($current === 'success') {
                if ($incoming === 'refunded') {
                    $outboxId = $this->startRefund($order, $callback);
                    if ($outboxId !== null) {
                        $dispatch = ['id' => $outboxId, 'event_type' => 'deposit_refund'];
                    }
                }

                return $order;
            }
            // 退款流程中（pending/processing/unknown）与退款终态、失败终态均幂等返回，等待退款扫描器推进。
            if (in_array($current, ['refund_pending', 'refund_processing', 'refund_unknown'], true)) {
                return $order;
            }
            if ($current === 'refunded') {
                return $order;
            }
            if ($current === 'failed') {
                return $order;
            }
            // 未支付成功前不允许退款，防止绕过支付直接触发退款链路。
            if ($incoming === 'refunded') {
                throw new InvalidArgumentException('Payment cannot be refunded before success.');
            }
            // pending 回调不改状态：仅作为网关通知确认，等待最终成功/失败回调。
            if ($incoming === 'pending') {
                return $order;
            }

            // 首次推进的终态回调才落供应商单号与回调摘要，供对账溯源。
            $order->channel_order_no = $callback->providerOrderNumber();
            $order->provider_payload_hash = $callback->payloadHash();
            // pending -> failed 直接置终态，不创建任何出箱记录。
            if ($incoming === 'failed') {
                $order->payment_status = 'failed';
                $order->saveOrFail();

                return $order;
            }
            if ($incoming !== 'success') {
                throw new InvalidArgumentException('Unsupported payment transition.');
            }

            // pending -> success：落成功状态并标记结算待执行，事务提交后由结算扫描器/队列处理。
            $order->payment_status = 'success';
            $order->settlement_status = 'pending';
            $order->payment_time = now();
            $order->saveOrFail();

            // 出箱表以 (event_type, deposit_record_id) 唯一键幂等：并发重放只创建一个结算任务，
            // 仅当本次新建时才投递队列，重复回调不会重复结算。
            $outbox = PaymentSettlementOutbox::firstOrCreate(
                ['event_type' => 'deposit_settlement', 'deposit_record_id' => $order->id],
                [
                    'local_order_no' => (string) $order->local_order_no,
                    'status' => 'pending',
                    'attempts' => 0,
                    'payload_hash' => $callback->payloadHash(),
                    'available_at' => now(),
                ]
            );
            if ($outbox->wasRecentlyCreated) {
                $dispatch = ['id' => (int) $outbox->id, 'event_type' => 'deposit_settlement'];
            }

            return $order;
        }, 3);

        // 队列投递放在事务外且 afterCommit：确保订单与出箱记录已提交，job 执行时一定能读到数据。
        if ($dispatch !== null) {
            try {
                if ($dispatch['event_type'] === 'deposit_refund') {
                    RefundDepositPayment::dispatch($dispatch['id'])->afterCommit();
                } else {
                    SettleDepositPayment::dispatch($dispatch['id'])->afterCommit();
                }
            } catch (Throwable $exception) {
                // 投递失败不阻塞回调主流程：出箱扫描器会按 available_at 兜底重试发布。
                Log::error('Deposit settlement queue dispatch failed; scheduled outbox scan will retry publication.', [
                    'outbox_id' => $dispatch['id'],
                    'exception_class' => get_class($exception),
                ]);
            }
        }

        return $order;
    }

    /**
     * 启动退款流程：按当前结算状态决定是直接终态、标记对账还是创建退款出箱任务。
     *
     * @param DepositRecord $order 已锁定并校验身份的成功订单。
     * @param PaymentCallback $callback 已验签的退款回调。
     * @return int|null 需要立即投递退款队列时返回出箱记录 ID，否则返回 null（幂等或已就地终态）。
     */
    private function startRefund(DepositRecord $order, PaymentCallback $callback): ?int
    {
        $settlementStatus = (string) $order->settlement_status;
        // 结算尚未执行：无反向操作可做，直接置退款终态，并停用待结算的入金出箱任务（防入金/退款双跑）。
        if ($settlementStatus === 'pending') {
            $depositOutbox = PaymentSettlementOutbox::where('deposit_record_id', $order->id)
                ->where('event_type', 'deposit_settlement')
                ->lockForUpdate()
                ->first();
            if ($depositOutbox && in_array($depositOutbox->status, ['pending', 'retryable'], true)) {
                $depositOutbox->status = 'refunded';
                $depositOutbox->processed_at = now();
                $depositOutbox->locked_at = null;
                $depositOutbox->last_error_code = 'refunded_before_deposit_settlement';
                $depositOutbox->saveOrFail();
            }

            $order->payment_status = 'refunded';
            $order->settlement_status = 'refunded';
            $order->status = '05';
            $order->provider_payload_hash = $callback->payloadHash();
            $order->saveOrFail();

            return null;
        }

        // 结算结果未知/被拒：unknown 必须登记对账（等人工确认是否需反向扣款），rejected 无需反向操作直接终态。
        if (in_array($settlementStatus, ['unknown', 'rejected'], true)) {
            $needsReconciliation = $settlementStatus === 'unknown';
            PaymentSettlementOutbox::firstOrCreate(
                ['event_type' => 'deposit_refund', 'deposit_record_id' => $order->id],
                [
                    'local_order_no' => (string) $order->local_order_no,
                    'status' => $needsReconciliation ? 'unknown' : 'processed',
                    'attempts' => 0,
                    'payload_hash' => $callback->payloadHash(),
                    'available_at' => null,
                    'processed_at' => now(),
                    'last_error_code' => $needsReconciliation
                        ? 'deposit_result_unknown'
                        : 'deposit_not_settled_no_reverse_needed',
                ]
            );
            $order->payment_status = $needsReconciliation ? 'refund_unknown' : 'refunded';
            if (!$needsReconciliation) {
                $order->settlement_status = 'refunded';
                $order->status = '05';
            }
            $order->provider_payload_hash = $callback->payloadHash();
            $order->saveOrFail();

            return null;
        }

        // 结算状态超出已知集合（数据异常）：标记 refund_unknown 等人工介入，不伪造终态。
        if (!in_array($settlementStatus, ['processing', 'settled'], true)) {
            $order->payment_status = 'refund_unknown';
            $order->provider_payload_hash = $callback->payloadHash();
            $order->saveOrFail();

            return null;
        }

        // 结算进行中：退款出箱置 blocked 等结算结束再处理，避免与入金写冲突；已结算则创建 pending 退款任务并投递。
        $blocked = $settlementStatus === 'processing';
        $refund = PaymentSettlementOutbox::firstOrCreate(
            ['event_type' => 'deposit_refund', 'deposit_record_id' => $order->id],
            [
                'local_order_no' => (string) $order->local_order_no,
                'status' => $blocked ? 'blocked' : 'pending',
                'attempts' => 0,
                'payload_hash' => $callback->payloadHash(),
                'available_at' => $blocked ? null : now(),
                'last_error_code' => $blocked ? 'deposit_settlement_in_progress' : null,
            ]
        );
        $order->payment_status = 'refund_pending';
        $order->provider_payload_hash = $callback->payloadHash();
        $order->saveOrFail();

        return !$blocked && $refund->wasRecentlyCreated ? (int) $refund->id : null;
    }

    /**
     * 校验回调身份与订单一致：网关码/商户号/币种/金额逐一常量时间比对，已有供应商单号也必须一致。
     *
     * @param DepositRecord $order 本地订单。
     * @param PaymentCallback $callback 已验签的回调。
     * @throws InvalidArgumentException 任一身份字段不匹配时抛出，防止串单入账。
     */
    private function assertIdentity(DepositRecord $order, PaymentCallback $callback): void
    {
        $expected = [
            'gateway' => trim((string) $order->gateway_code),
            'merchant' => trim((string) $order->merchant_id),
            'currency' => strtoupper(trim((string) $order->currency)),
            'amount' => $this->decimal((string) $order->provider_amount),
        ];
        $actual = [
            'gateway' => $callback->gatewayCode(),
            'merchant' => $callback->merchantId(),
            'currency' => $callback->currency(),
            'amount' => $this->decimal($callback->amount()),
        ];
        // 订单侧字段为空说明数据不完整，同样拒绝；金额先归一化再比对，避免 '100.0' vs '100.00' 误判。
        foreach ($expected as $key => $value) {
            if ($value === '' || !hash_equals($value, $actual[$key])) {
                throw new InvalidArgumentException('Payment callback ' . $key . ' mismatch.');
            }
        }
        // 首次回调后单号已固定：后续回调单号不同即视为异常（可能是另一笔交易的回调），拒绝。
        $existingProviderOrder = trim((string) $order->channel_order_no);
        if ($existingProviderOrder !== '' && !hash_equals($existingProviderOrder, $callback->providerOrderNumber())) {
            throw new InvalidArgumentException('Payment callback provider order mismatch.');
        }
    }

    /**
     * 金额归一化为两位小数的纯十进制字符串，格式非法即失败关闭。
     *
     * @param string $amount 原始金额字符串。
     * @return string 归一化金额。
     * @throws InvalidArgumentException 金额格式非法时抛出。
     */
    private function decimal(string $amount): string
    {
        if (!preg_match('/^(0|[1-9]\d{0,15})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Payment callback amount is invalid.');
        }
        $whole = ltrim($matches[1], '0');

        return ($whole === '' ? '0' : $whole) . '.' . str_pad((string) ($matches[2] ?? ''), 2, '0');
    }
}
