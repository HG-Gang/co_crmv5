<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 18:22
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AdminAuthReviewProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 实名审核出箱处理队列任务。
 *
 * 文件功能：
 * - 每次消费一条 admin_auth_review_outboxes 审核意图：调用 AdminAuthReviewProcessor::process()
 *   完成状态落库与 MT4 交付；失败时按 outbox 状态机进入可重试或终态，不吞异常静默成功。
 */
final class ProcessAdminAuthReview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 最大重试次数固定为 1（不自动重试）。管理员实名审核会向 MT4 发起真实的账号信息变更，
     * 出队记录自带 attempts/retryable 状态机，重试语义由命令层显式控制；
     * 队列层面的静默重试会让同一次审核被多次下发，因此失败一次即进入失败队列交给人工排查。
     *
     * @var int
     */
    public $tries = 1;

    /**
     * 待处理的管理员实名审核 outbox 记录主键（admin_auth_review_outboxes.id）。
     * 任务只携带 ID 以保证序列化最小化；真正的审核载荷与状态推进全部由 AdminAuthReviewProcessor 读取该记录完成。
     *
     * @var int
     */
    private $outboxId;

    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
    }

    public function handle(AdminAuthReviewProcessor $processor): void
    {
        $processor->process((int) $this->outboxId);
    }
}
