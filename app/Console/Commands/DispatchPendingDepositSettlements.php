<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:25
 */

declare(strict_types=1);

/**
 * 入金结算事件分发命令。
 *
 * 文件功能：
 * - 扫描入金结算 outbox 表（payment_settlement_outboxes）中到期可处理的事件，
 *   按事件类型派发任务到队列：
 *   - deposit_settlement -> SettleDepositPayment（入金结算）
 *   - deposit_refund    -> RefundDepositPayment（入金退款）
 *
 * 适用场景：
 * - 由 Kernel::schedule 每分钟自动执行（payments:dispatch-deposit-settlements），
 *   也可手动执行：php artisan payments:dispatch-deposit-settlements。
 *
 * 入参例子：
 * - php artisan payments:dispatch-deposit-settlements
 *
 * 返回值：
 * - 命令成功返回 0（self::SUCCESS）；
 * - 控制台输出本次派发的事件数量。
 *
 * 异常或失败场景：
 * - 队列派发失败由 Laravel 队列机制处理，命令本身不捕获异常；
 * - processing 且锁超 5 分钟的陈旧事件会被重新捞起派发，保证不丢事件。
 */
namespace App\Console\Commands;

use App\Jobs\SettleDepositPayment;
use App\Jobs\RefundDepositPayment;
use App\Models\PaymentSettlementOutbox;
use Illuminate\Console\Command;

final class DispatchPendingDepositSettlements extends Command
{
    /** @var string 命令签名（无参数）。 */
    protected $signature = 'payments:dispatch-deposit-settlements';

    /** @var string 命令说明。 */
    protected $description = 'Dispatch due deposit settlement outbox events to the queue';

    /**
     * 执行命令：派发所有到期的入金结算/退款事件。
     *
     * @return int 成功返回 0。
     */
    public function handle(): int
    {
        $dispatched = 0;
        PaymentSettlementOutbox::query()
            ->select(['id', 'event_type'])
            ->whereIn('event_type', ['deposit_settlement', 'deposit_refund'])
            ->where(function ($query): void {
                $query->where(function ($ready): void {
                    $ready->whereIn('status', ['pending', 'retryable'])
                        ->where(function ($available): void {
                            $available->whereNull('available_at')
                                ->orWhere('available_at', '<=', now()->timestamp);
                        });
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'processing')
                        ->where(function ($lock): void {
                            $lock->whereNull('locked_at')
                                ->orWhere('locked_at', '<=', now()->subMinutes(5)->timestamp);
                        });
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($outboxes) use (&$dispatched): void {
                foreach ($outboxes as $outbox) {
                    if ($outbox->event_type === 'deposit_refund') {
                        RefundDepositPayment::dispatch((int) $outbox->id);
                    } else {
                        SettleDepositPayment::dispatch((int) $outbox->id);
                    }
                    $dispatched++;
                }
            });

        $this->info('Dispatched deposit settlements: ' . $dispatched);

        return self::SUCCESS;
    }
}
