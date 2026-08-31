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
 * 支付结算出队记录模型。
 *
 * 文件功能：
 * - 映射 payment_settlement_outbox 表，记录入金订单的 MT4 结算出队事件。
 * - 支持异步结算与重试机制。
 *
 * 适用场景：
 * - 后台管理员审核通过入金后，DispatchPendingDepositSettlements 定时任务消费出队记录。
 * - 入金审核通过后向 MT4 写入信用/余额。
 *
 * 主要字段：
 * - deposit_record_id：关联的入金记录 ID。
 * - local_order_no：本地订单号。
 * - event_type：事件类型（deposit_settlement=入金结算、deposit_refund=入金退款）。
 * - status：出队状态（pending/retryable/processing/processed/rejected/unknown/refunded）。
 * - attempts：已重试次数。
 *
 * 消费语义：
 * - 出队记录只由 DispatchPendingDepositSettlements 每分钟扫描并派发队列任务，业务审核链路只负责写入 pending 记录。
 * - 消费任务先把记录置为 processing 防止并发重复入金；pending/retryable 可被声明，锁超 5 分钟的陈旧 processing 记录会被重新捞起。
 * - payload_hash 用于消费时校验载荷未被篡改，不匹配时记录被置为 rejected 并写入 last_error_code。
 *
 * 关联关系：
 * - depositRecord()：关联的 DepositRecord。
 */

class PaymentSettlementOutbox extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 payment_settlement_outbox。
     */
    protected $table = 'payment_settlement_outbox';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - deposit_record_id：关联的入金记录 ID。
     * - local_order_no：本地订单号，用于定位业务单。
     * - event_type：事件类型（deposit_settlement/deposit_refund）。
     * - status：出队状态，业务侧通常只写入 pending，后续推进由消费方负责。
     * - attempts：已重试次数。
     * - payload_hash：入金单载荷指纹，消费时校验。
     * - available_at：最早可消费时间（Unix 秒，空表示立即可消费）。
     * - locked_at：领取锁时间，用于陈旧处理记录回收判断。
     * - processed_at：处理完成时间。
     * - provider_reference：网关或 MT4 返回的外部引用号。
     * - last_error_code：最近一次失败的错误码。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'deposit_record_id',
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
     * - deposit_record_id、attempts 转为整数，便于精确比较。
     * - available_at/locked_at/processed_at 按日期对象处理，序列化输出统一格式（见 BaseModel::serializeDate）。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deposit_record_id' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * 关联出队记录对应的入金记录。
     *
     * 逻辑说明：
     * - payment_settlement_outbox.deposit_record_id 对应 deposit_records.id。
     * - 用于从出队记录反查入金单及其结算载荷。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 出队记录对应的入金记录。
     */
    public function depositRecord()
    {
        return $this->belongsTo(DepositRecord::class, 'deposit_record_id');
    }
}
