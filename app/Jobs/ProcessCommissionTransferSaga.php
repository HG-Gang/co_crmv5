<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

/**
 * 返佣转账 Saga 队列任务。
 *
 * 文件功能：
 * - 消费佣金转账出箱表 commission_transfer_outboxes（event_type=process）派生的任务，
 *   按返佣转账记录 ID 驱动 CommissionTransferService::process() 推进 Saga 状态机。
 * - 负责把佣金从上级代理账号转账到下级代理或客户账号的异步执行。
 *
 * 适用场景：
 * - 代理发起返佣转账申请后由业务侧 dispatch 本任务。
 * - 定时任务或对账流程需要重放某笔转账时再次入队。
 *
 * 入参例子：
 * - new ProcessCommissionTransferSaga(1024) 处理 commission_transfers.id=1024 的转账。
 *
 * 方法功能：
 * - __construct(int $transferId)：保存待处理的返佣转账记录 ID。
 * - handle(CommissionTransferService $service)：调用服务层 process() 推进转账状态。
 * - transferId()：返回当前任务绑定的转账记录 ID，供测试与日志使用。
 * - backoff()：返回失败重试退避秒数 [60, 300]，即第 1 次失败后 60 秒重试，第 2 次失败后 300 秒重试。
 *
 * 幂等要点：
 * - 任务只持有转账记录 ID，具体幂等由服务层基于 commission_transfers 记录状态与幂等键控制；
 *   重复入队同一 ID 时，服务层会跳过已完成的转账步骤。
 *
 * 异常或失败场景：
 * - 服务层抛出异常时任务失败，最多重试 3 次（$tries=3），重试间隔由 backoff() 控制；
 *   第 3 次仍失败即进入失败队列，交由人工对账，不再自动重试。
 * - 转账记录不存在或状态不允许推进时，服务层应安全返回而不抛异常（终态不重试）。
 */
declare(strict_types=1);

namespace App\Jobs;

use App\Services\CommissionTransfer\CommissionTransferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessCommissionTransferSaga implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 最大重试次数固定为 3：与 Saga 的两段退避 backoff()=[60,300] 配套，
     * 保证一次转账故障（MT4 抖动等）最多经过 3 次尝试后进入失败队列转人工对账，
     * 避免无限重试在下游账号上反复产生转账副作用。真实幂等由服务层状态机兜底。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 待推进的返佣转账记录主键（commission_transfers.id）。任务只携带 ID，
     * 转账金额、目标账号、幂等键等敏感与可变信息始终以该记录为唯一事实来源读取，
     * 防止任务序列化数据与库内状态漂移导致重复转账。
     *
     * @var int
     */
    private $transferId;

    /**
     * 绑定待处理的返佣转账记录 ID。
     *
     * @param int $transferId commission_transfers.id，由业务侧或调度命令 dispatch 时传入。
     */
    public function __construct(int $transferId)
    {
        $this->transferId = $transferId;
    }

    /**
     * 驱动 Saga 状态机推进一笔返佣转账。
     *
     * @param CommissionTransferService $service 返佣转账服务（容器注入）。
     * @return void 无返回值；失败以抛异常表达，由队列按 tries/backoff 重试。
     */
    public function handle(CommissionTransferService $service): void
    {
        // 只负责按 ID 转发：认领加锁、幂等与终态判定都在服务层 process() 内完成，
        // 任务自身不感知当前 Saga 步骤，重复入队同一 ID 不会重复划款。
        $service->process($this->transferId);
    }

    /**
     * 返回本任务绑定的转账记录 ID，供测试与日志追溯使用。
     *
     * @return int commission_transfers.id。
     */
    public function transferId(): int
    {
        return $this->transferId;
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
