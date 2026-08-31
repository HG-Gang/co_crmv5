<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 18:37
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessAdminAuthReview;
use App\Models\AdminAuthReviewOutbox;
use App\Services\Mt4SyncGate;
use Illuminate\Console\Command;

/**
 * 实名审核出箱投递命令。
 *
 * 文件功能：
 * - 定时扫描 admin_auth_review_outboxes 中到期（available_at）的待处理审核意图，
 *   经 Mt4SyncGate 判定后分发 ProcessAdminAuthReview 队列任务。
 * - 恢复超过认领时限仍处于 processing 的 stale 记录，避免任务丢失后审核意图永久滞留；
 *   本命令只负责投递与恢复，不直接处理审核业务。
 */
final class DispatchPendingAdminAuthReviews extends Command
{
    /**
     * Artisan 命令签名契约：调度器与运维通过 `php artisan mt4:dispatch-admin-auth-reviews`
     * 触发本命令；签名一旦变更，crontab/调度注册须同步修改，否则管理员实名审核 outbox 事件将积压。
     *
     * @var string
     */
    protected $signature = 'mt4:dispatch-admin-auth-reviews';

    /**
     * Artisan 命令描述契约：仅用于 `artisan list` 展示与文档，不影响执行逻辑；
     * 语义上必须与签名一致——只负责投递到期或已过期的审核 outbox 事件，不直接执行业务。
     *
     * @var string
     */
    protected $description = 'Dispatch due administrator authentication review outbox events';

    public function handle(): int
    {
        $remoteSyncEnabled = Mt4SyncGate::remoteUserSyncEnabled();
        $dispatched = 0;

        AdminAuthReviewOutbox::query()
            ->select(['id'])
            ->where(function ($query) use ($remoteSyncEnabled): void {
                $staleClaim = function ($stale): void {
                    $stale->where('status', 'processing')
                        ->where(function ($locked): void {
                            $locked->whereNull('locked_at')
                                ->orWhere('locked_at', '<=', now()->subMinutes(5)->timestamp);
                        });
                };

                if ($remoteSyncEnabled) {
                    $query->where(function ($ready): void {
                        $ready->whereIn('status', ['pending', 'retryable'])
                            ->where(function ($available): void {
                                $available->whereNull('available_at')
                                    ->orWhere('available_at', '<=', now()->timestamp);
                            });
                    })->orWhere($staleClaim);

                    return;
                }

                $query->where($staleClaim);
            })
            ->orderBy('id')
            ->chunkById(100, function ($outboxes) use (&$dispatched): void {
                foreach ($outboxes as $outbox) {
                    ProcessAdminAuthReview::dispatch((int) $outbox->id);
                    $dispatched++;
                }
            });

        $this->info('Dispatched administrator authentication reviews: ' . $dispatched);

        return self::SUCCESS;
    }
}
