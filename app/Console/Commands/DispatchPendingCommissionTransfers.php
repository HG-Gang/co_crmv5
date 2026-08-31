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
 * 佣金转账事件分发命令。
 *
 * 文件功能：
 * - 扫描佣金转账 outbox 表（commission_transfer_outboxes）中到期可处理的事件，
 *   将 process 事件派发为 ProcessCommissionTransferSaga 任务到队列执行。
 *
 * 适用场景：
 * - 由 Kernel::schedule 每分钟自动执行（commission:dispatch-transfers），
 *   也可手动执行：php artisan commission:dispatch-transfers。
 *
 * 入参例子：
 * - php artisan commission:dispatch-transfers
 *
 * 返回值：
 * - 命令成功返回 0（self::SUCCESS）；
 * - 控制台输出本次派发的事件数量。
 *
 * 异常或失败场景：
 * - 队列派发失败由 Laravel 队列机制处理，命令本身不捕获异常；
 * - 超时未完成（processing 且锁超 5 分钟）的事件会被重新捞起派发，
 *   但已进入资金划拨步骤（withdraw/deposit/compensate）的陈旧事件
 *   留给人工对账路径处理，避免重复划款。
 */
namespace App\Console\Commands;

use App\Jobs\ProcessCommissionTransferSaga;
use App\Models\CommissionTransferOutbox;
use Illuminate\Console\Command;

final class DispatchPendingCommissionTransfers extends Command
{
    /** @var string 命令签名（无参数）。 */
    protected $signature = 'commission:dispatch-transfers';

    /** @var string 命令说明。 */
    protected $description = 'Dispatch due commission transfer saga events';

    /**
     * 执行命令：派发所有到期的佣金转账事件。
     *
     * @return int 成功返回 0。
     */
    public function handle(): int
    {
        $dispatched = 0;
        CommissionTransferOutbox::query()
            ->select(['id', 'commission_transfer_id'])
            ->where('event_type', 'process')
            ->where(function ($query): void {
                $query->where(function ($ready): void {
                    $ready->whereIn('status', ['pending', 'retryable'])
                        ->where(function ($available): void {
                            $available->whereNull('available_at')
                                ->orWhere('available_at', '<=', now()->timestamp);
                        });
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'processing')
                        ->where(function ($locked): void {
                            $locked->whereNull('locked_at')
                                ->orWhere('locked_at', '<=', now()->subMinutes(5)->timestamp);
                        })
                        ->whereHas('transfer', function ($transfer): void {
                            // A stale financial command may already have moved money;
                            // the worker must leave it for the manual-reconcile path.
                            $transfer->whereNotIn('current_step', ['withdraw', 'deposit', 'compensate']);
                        });
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($outboxes) use (&$dispatched): void {
                foreach ($outboxes as $outbox) {
                    ProcessCommissionTransferSaga::dispatch((int) $outbox->commission_transfer_id);
                    $dispatched++;
                }
            });

        $this->info('Dispatched commission transfer events: ' . $dispatched);

        return self::SUCCESS;
    }
}
