<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:39
 */

/**
 * MT4 用户建仓队列任务。
 *
 * 文件功能：
 * - 按出队记录 ID 驱动 UserMt4ProvisioningProcessor::process() 完成新用户在 MT4 端的建仓。
 * - 建仓内容包括创建交易账户、设置杠杆、分配交易组等开户后置动作。
 *
 * 适用场景：
 * - 定时任务 DispatchPendingUserMt4Provisioning 消费 user_mt4_provisioning_outbox 中的待处理记录并派发本任务。
 * - 远端同步开启时注册服务也可直接调用同一处理器；两条入口共享状态机，任务不携带明文密码。
 *
 * 入参例子：
 * - new ProcessUserMt4Provisioning(88) 处理 user_mt4_provisioning_outbox.id=88 的建仓出队记录。
 *
 * 方法功能：
 * - __construct(int $outboxId)：保存待处理的建仓出队记录 ID。
 * - handle(UserMt4ProvisioningProcessor $processor)：调用处理器按出队记录执行 MT4 建仓。
 * - backoff()：返回失败重试退避秒数 [60, 300]。
 *
 * 幂等要点：
 * - 任务只携带出队记录 ID，处理器内部以 status=processing 加锁声明任务，
 *   并基于 payload_hash 与对账重试机制避免重复建仓或重复发放。
 *
 * 异常或失败场景：
 * - 处理器抛出异常时任务失败，最多重试 3 次（$tries=3），
 *   第 3 次仍失败即进入失败队列，交由人工核对建仓结果，不自动重发。
 * - 出队记录已被其他进程处理（status=processing 且锁未过期）时，处理器会安全跳过。
 */
declare(strict_types=1);

namespace App\Jobs;

use App\Services\Registration\UserMt4ProvisioningProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessUserMt4Provisioning implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 最大重试次数固定为 3：与 backoff()=[60,300] 的退避节奏配套，覆盖 MT4 短暂抖动；
     * 建仓会真实创建 MT4 账号，超出 3 次即进入失败队列转人工核对，由处理器基于
     * payload_hash 对账保证不重复建仓，队列层面不再放大重试次数。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 待处理的 MT4 建仓 outbox 记录主键（user_mt4_provisioning_outbox.id）。
     * 任务只携带 ID：注册表单里的明文密码等载荷永远留在库内（已加密），
     * 处理器按该 ID 认领并读取，避免敏感数据随任务序列化进入队列后端。
     *
     * @var int
     */
    private $outboxId;

    /**
     * 绑定待处理的 MT4 建仓出队记录 ID。
     *
     * @param int $outboxId user_mt4_provisioning_outbox.id。
     */
    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
    }

    /**
     * 按出队记录执行 MT4 建仓。
     *
     * @param UserMt4ProvisioningProcessor $processor 建仓处理器（容器注入）。
     * @return void 无返回值；失败以抛异常表达，由队列按 tries/backoff 重试。
     */
    public function handle(UserMt4ProvisioningProcessor $processor): void
    {
        // 认领、加锁与幂等判定（status=processing 声明 + payload_hash 对账）全部在处理器内完成，
        // 任务只做 ID 转发，避免重复建仓或重复发放。
        $processor->process((int) $this->outboxId);
    }

    /**
     * 失败重试退避：第 1 次失败后 60 秒重试，第 2 次失败后 300 秒重试。
     *
     * @return array<int, int> 各次重试前的等待秒数。
     */
    public function backoff(): array
    {
        return [60, 300];
    }
}
