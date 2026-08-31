<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:39
 */

declare(strict_types=1);

/**
 * MT4 用户开户事件分发命令。
 *
 * 文件功能：
 * - 扫描 MT4 开户 outbox 表（user_mt4_provisioning_outbox）中到期可处理的
 *   事件，将每条记录派发为 ProcessUserMt4Provisioning 任务到队列执行。
 *
 * 适用场景：
 * - 由 Kernel::schedule 每分钟自动执行（mt4:dispatch-user-provisioning），
 *   也可手动执行：php artisan mt4:dispatch-user-provisioning。
 *
 * 入参例子：
 * - php artisan mt4:dispatch-user-provisioning
 *
 * 返回值：
 * - 命令成功返回 0（self::SUCCESS）；
 * - 控制台输出本次派发的事件数量。
 *
 * 异常或失败场景：
 * - 队列派发失败由 Laravel 队列机制处理，命令本身不捕获异常；
 * - 两个 MT4 开关未同时开启时直接返回成功且零派发，所有记录保持原状；
 * - 开关开启后，pending/retryable/unknown 且到期的记录，以及 processing 且锁超 5 分钟的
 *   陈旧记录都会被重新派发，保证开户事件最终送达。
 */
namespace App\Console\Commands;

use App\Jobs\ProcessUserMt4Provisioning;
use App\Models\UserMt4ProvisioningOutbox;
use App\Services\Mt4SyncGate;
use Illuminate\Console\Command;

final class DispatchPendingUserMt4Provisioning extends Command
{
    /** @var string 命令签名（无参数）。 */
    protected $signature = 'mt4:dispatch-user-provisioning';

    /** @var string 命令说明。 */
    protected $description = 'Dispatch due MT4 user provisioning outbox events';

    /**
     * 执行命令：派发所有到期的 MT4 开户事件。
     *
     * @return int 成功返回 0。
     */
    public function handle(): int
    {
        if (!Mt4SyncGate::remoteUserSyncEnabled()) {
            $this->info('MT4 user synchronization is disabled; no provisioning events were dispatched.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        UserMt4ProvisioningOutbox::query()
            ->select(['id'])
            ->where(function ($query): void {
                $query->where(function ($ready): void {
                    $ready->whereIn('status', ['pending', 'retryable', 'unknown'])
                        ->where(function ($available): void {
                            $available->whereNull('available_at')
                                ->orWhere('available_at', '<=', now()->timestamp);
                        });
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'processing')
                        ->where(function ($locked): void {
                            $locked->whereNull('locked_at')
                                ->orWhere('locked_at', '<=', now()->subMinutes(5)->timestamp);
                        });
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($outboxes) use (&$dispatched): void {
                foreach ($outboxes as $outbox) {
                    ProcessUserMt4Provisioning::dispatch((int) $outbox->id);
                    $dispatched++;
                }
            });

        $this->info('Dispatched MT4 user provisioning events: ' . $dispatched);

        return self::SUCCESS;
    }
}
