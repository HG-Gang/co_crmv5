<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */

declare(strict_types=1);

namespace App\Models;

/**
 * 出金结算出队记录模型。
 *
 * 文件功能：
 * - 映射 withdraw_settlement_outbox 表，记录出金订单的 MT4 结算出队事件。
 * - 支持异步退款与重试机制。
 *
 * 适用场景：
 * - 后台管理员拒绝出金申请后，DispatchPendingWithdrawSettlements 定时任务消费出队记录。
 * - 将已扣除的出金金额退还到用户 MT4 账户。
 *
 * 主要字段：
 * - withdraw_record_id：关联的出金记录 ID。
 * - local_order_no：本地订单号。
 * - event_type：事件类型（withdraw_debit=出金扣减、withdraw_refund=出金退款）。
 * - status：出队状态（pending/retryable/processing/processed/rejected/unknown/refunded 等）。
 * - attempts：已重试次数。
 *
 * 消费语义：
 * - 出队记录只由 DispatchPendingWithdrawSettlements 每分钟扫描并派发队列任务，出金审核链路只负责写入 pending 记录。
 * - withdraw_debit 由 ProcessWithdrawFunding 消费，withdraw_refund 由 RefundWithdrawFunding 消费，处理前均校验 payload_hash 与订单状态。
 * - processing 且锁超 5 分钟的陈旧记录会被重新捞起，保证事件最终送达。
 *
 * 关联关系：
 * - withdrawRecord()：关联的 WithdrawRecord。
 */

class WithdrawSettlementOutbox extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 withdraw_settlement_outbox。
     */
    protected $table = 'withdraw_settlement_outbox';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - withdraw_record_id：关联的出金记录 ID。
     * - local_order_no：本地订单号，用于定位业务单。
     * - event_type：事件类型（withdraw_debit/withdraw_refund）。
     * - status：出队状态，业务侧通常只写入 pending，后续推进由消费方负责。
     * - attempts：已重试次数。
     * - payload_hash：出金单载荷指纹，消费时校验。
     * - available_at：最早可消费时间（Unix 秒，空表示立即可消费）。
     * - locked_at：领取锁时间，用于陈旧处理记录回收判断。
     * - processed_at：处理完成时间。
     * - provider_reference：网关或 MT4 返回的外部引用号。
     * - last_error_code：最近一次失败的错误码。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'withdraw_record_id',
        'local_order_no',
        'event_type',
        'status',
        'attempts',
        'payload_hash',
        'available_at',
        'locked_at',
        'processed_at',
        'provider_reference',
        'last_error_code',
    ];

    /**
     * 字段类型转换。
     *
     * 逻辑说明：
     * - withdraw_record_id、attempts 转为整数，便于精确比较。
     * - available_at/locked_at/processed_at 按日期对象处理，序列化输出统一格式（见 BaseModel::serializeDate）。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'withdraw_record_id' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * 关联出队记录对应的出金记录。
     *
     * 逻辑说明：
     * - withdraw_settlement_outbox.withdraw_record_id 对应 withdraw_records.id。
     * - 用于从出队记录反查出金单及其资金处理载荷。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 出队记录对应的出金记录。
     */
    public function withdrawRecord()
    {
        return $this->belongsTo(WithdrawRecord::class, 'withdraw_record_id');
    }
}
