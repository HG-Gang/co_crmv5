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
 * 提现结算事件分发命令。
 *
 * 文件功能：
 * - 扫描提现结算 outbox 表（withdraw_settlement_outboxes）中到期可处理的
 *   事件并派发任务到队列：
 *   - withdraw_debit  -> ProcessWithdrawFunding（出金扣减）
 *   - withdraw_refund -> RefundWithdrawFunding（出金退款）
 * - 额外处理：对 processing 超时（锁超 5 分钟）的 withdraw_refund 事件，
 *   尝试 reconcileAbandonedClaim() 对账回收被放弃的退款认领。
 *
 * 适用场景：
 * - 由 Kernel::schedule 每分钟自动执行（payments:dispatch-withdraw-settlements），
 *   也可手动执行：php artisan payments:dispatch-withdraw-settlements。
 *
 * 入参例子：
 * - php artisan payments:dispatch-withdraw-settlements
 *
 * 返回值：
 * - 命令成功返回 0（self::SUCCESS）；
 * - 控制台输出派发事件数与被对账回收的退款数。
 *
 * 异常或失败场景：
 * - 队列派发失败由 Laravel 队列机制处理，命令本身不捕获异常；
 * - pending/retryable 且到期的记录，以及 processing 且锁超 5 分钟的陈旧记录
 *   都会被重新捞起处理，保证事件最终送达。
 */
namespace App\Console\Commands;

use App\Jobs\ProcessWithdrawFunding;
use App\Jobs\RefundWithdrawFunding;
use App\Models\WithdrawSettlementOutbox;
use Illuminate\Console\Command;

final class DispatchPendingWithdrawSettlements extends Command
{
    /** @var string 命令签名（无参数）。 */
    protected $signature = 'payments:dispatch-withdraw-settlements';

    /** @var string 命令说明。 */
    protected $description = 'Dispatch due withdrawal funding outbox events to the queue';

    /**
     * 执行命令：对账回收陈旧退款、派发到期的出金/退款事件。
     *
     * @return int 成功返回 0。
     */
    public function handle(): int
    {
        $dispatched = 0;
        $reconciled = 0;
        WithdrawSettlementOutbox::query()
            ->select(['id'])
            ->where('event_type', 'withdraw_refund')
            ->where('status', 'processing')
            ->where(function ($query): void {
                $query->whereNull('locked_at')
                    ->orWhere('locked_at', '<=', now()->subMinutes(5)->timestamp);
            })
            ->orderBy('id')
            ->chunkById(100, function ($outboxes) use (&$reconciled): void {
                foreach ($outboxes as $outbox) {
                    if ((new RefundWithdrawFunding((int) $outbox->id))
                        ->reconcileAbandonedClaim()) {
                        $reconciled++;
                    }
                }
            });
        WithdrawSettlementOutbox::query()
            ->select(['id'])
            ->where('event_type', 'withdraw_debit')
            ->whereIn('status', ['pending', 'retryable'])
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now()->timestamp);
            })
            ->orderBy('id')
            ->chunkById(100, function ($outboxes) use (&$dispatched): void {
                foreach ($outboxes as $outbox) {
                    ProcessWithdrawFunding::dispatch((int) $outbox->id);
                    $dispatched++;
                }
            });
        WithdrawSettlementOutbox::query()
            ->select(['id'])
            ->where('event_type', 'withdraw_refund')
            ->whereIn('status', ['pending', 'retryable'])
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now()->timestamp);
            })
            ->orderBy('id')
            ->chunkById(100, function ($outboxes) use (&$dispatched): void {
                foreach ($outboxes as $outbox) {
                    RefundWithdrawFunding::dispatch((int) $outbox->id);
                    $dispatched++;
                }
            });

        $this->info('Dispatched withdrawal settlements: ' . $dispatched);
        $this->info('Reconciled abandoned withdrawal refunds: ' . $reconciled);

        return self::SUCCESS;
    }
}
