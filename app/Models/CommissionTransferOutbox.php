<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:07
 */

declare(strict_types=1);

namespace App\Models;

/**
 * 返佣转账出队记录模型。
 *
 * 文件功能：
 * - 映射 commission_transfer_outbox 表，记录返佣转账的 MT4 出队事件。
 * - 支持异步处理与重试机制（attempts 字段 + available_at 延迟重试）。
 *
 * 适用场景：
 * - CommissionTransferSagaService 执行转账步骤时创建出队记录。
 * - DispatchPendingCommissionTransfers 定时任务消费出队记录。
 *
 * 主要字段：
 * - commission_transfer_id：关联的返佣转账记录 ID。
 * - event_type：事件类型（deposit/withdraw/compensation）。
 * - status：出队状态（pending/processing/completed/failed）。
 * - attempts：已重试次数。
 * - available_at：下次可执行时间（延迟重试控制）。
 *
 * 关联关系：
 * - transfer()：关联的 CommissionTransfer 记录。
 */

class CommissionTransferOutbox extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 commission_transfer_outbox。
     */
    protected $table = 'commission_transfer_outbox';

    /**
     * 可批量写入的出队字段。
     *
     * 字段含义：
     * - commission_transfer_id 表示关联的返佣转账记录 ID。
     * - event_type 表示事件类型：deposit=入金、withdraw=出金、compensation=补偿。
     * - status 表示出队状态：pending/processing/completed/failed。
     * - attempts 表示已尝试次数，available_at 表示下次可执行时间（延迟重试控制）。
     * - payload_hash 表示事件载荷哈希，用于校验步骤数据未被篡改。
     * - locked_at、processed_at 表示声明处理锁和处理完成时间。
     * - provider_reference 表示外部资金系统引用号，last_error_code 表示最近一次失败错误码。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'commission_transfer_id', 'event_type', 'status', 'attempts', 'payload_hash',
        'available_at', 'locked_at', 'processed_at', 'provider_reference', 'last_error_code',
    ];

    /**
     * 字段类型转换。
     *
     * 字段含义：
     * - 数值字段转为整数，时间字段读取为 Carbon 对象，供消费任务和重试调度直接使用。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'commission_transfer_id' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * 关联出队记录所属的返佣转账单据。
     *
     * 逻辑说明：
     * - commission_transfer_outbox.commission_transfer_id 对应 commission_transfers.id。
     * - 消费完成后可通过该关系反查原始转账单据与对账信息。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 所属 CommissionTransfer 关系。
     */
    public function transfer()
    {
        return $this->belongsTo(CommissionTransfer::class, 'commission_transfer_id');
    }
}
