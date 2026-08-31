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
 * 返佣转账记录模型。
 *
 * 文件功能：
 * - 映射 commission_transfers 表，存储代理之间的返佣转账请求。
 * - 支持 Saga 模式的状态流转（pending→processing→completed/failed）。
 *
 * 适用场景：
 * - 上级代理给下级客户或下级代理转返佣。
 * - 后台管理员对返佣转账进行对账（reconciliation）。
 *
 * 主要字段：
 * - source_user_id：转出方用户ID（上级代理）。
 * - target_user_id：接收方用户ID（下级代理或客户）。
 * - amount：转账金额。
 * - status：转账状态（pending/processing/completed/failed/compensated）。
 * - idempotency_key：幂等键，防止重复提交。
 * - reconcile_decision：后台对账决策（confirmed_completed/confirmed_compensated/confirmed_rejected）。
 *
 * 关联关系：
 * - source()：转出方 UserInfo。
 * - target()：接收方 UserInfo。
 * - outbox()：对应的出队记录。
 */

class CommissionTransfer extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 commission_transfers。
     */
    protected $table = 'commission_transfers';

    /**
     * 可批量写入的转账字段。
     *
     * 字段含义：
     * - local_order_no 表示本地订单号，用于和外部资金系统对账。
     * - source_user_id 表示转出方业务用户 ID（上级代理），target_user_id 表示接收方业务用户 ID（下级代理或客户），均对应 user_infos.user_id。
     * - request_purpose 表示转账用途，remark 表示备注。
     * - idempotency_key 表示幂等键，防止同一请求重复提交；payload_hash、payload_ciphertext 表示请求载荷哈希与密文。
     * - amount 表示转账金额。
     * - status 表示转账状态（pending/processing/completed/failed/compensated）。
     * - current_step 表示当前 Saga 步骤，manual_origin_step 表示人工干预前的原始步骤。
     * - reservation_status 表示资金占用状态，small_limit_day、small_limit_key 用于小额限额控制。
     * - withdraw_ticket、deposit_ticket、compensation_ticket 表示 MT4 出金、入金、补偿票据号。
     * - source_balance_after、target_balance_after 表示转账后双方余额快照，用于对账。
     * - attempts 表示已尝试次数，available_at 表示下次可执行时间（延迟重试），locked_at、processed_at 表示声明处理锁和处理完成时间。
     * - provider_reference 表示外部资金系统引用号，last_error_code、last_error_message 表示最近一次失败信息。
     * - reconcile_decision 表示后台对账决策，reconcile_external_reference、reconcile_evidence 表示对账外部引用与证据。
     * - reconciled_by、reconciled_at 表示执行对账的后台管理员与时间。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'local_order_no', 'source_user_id', 'target_user_id', 'request_purpose',
        'idempotency_key', 'payload_hash', 'payload_ciphertext', 'amount', 'remark',
        'status', 'current_step', 'manual_origin_step', 'reservation_status', 'small_limit_day', 'small_limit_key',
        'withdraw_ticket', 'deposit_ticket', 'compensation_ticket', 'source_balance_after',
        'target_balance_after', 'attempts', 'available_at', 'locked_at', 'processed_at',
        'provider_reference', 'last_error_code', 'last_error_message',
        'reconcile_decision', 'reconcile_external_reference', 'reconcile_evidence',
        'reconciled_by', 'reconciled_at',
    ];

    /**
     * 序列化隐藏字段。
     *
     * 逻辑说明：
     * - payload_ciphertext 为加密后的请求载荷，含敏感转账信息，禁止序列化到接口响应。
     *
     * @var array<int, string>
     */
    protected $hidden = ['payload_ciphertext'];

    /**
     * 字段类型转换。
     *
     * 字段含义：
     * - 数值字段转为整数，便于服务层直接比较。
     * - 时间字段读取为 Carbon 对象，供重试调度和对账逻辑使用。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'source_user_id' => 'integer',
        'target_user_id' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
        'reconciled_by' => 'integer',
        'reconciled_at' => 'datetime',
    ];

    /**
     * 关联转出方业务用户资料。
     *
     * 逻辑说明：
     * - commission_transfers.source_user_id 对应 user_infos.user_id。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 转出方 UserInfo 关系。
     */
    public function source()
    {
        return $this->belongsTo(UserInfo::class, 'source_user_id', 'user_id');
    }

    /**
     * 关联接收方业务用户资料。
     *
     * 逻辑说明：
     * - commission_transfers.target_user_id 对应 user_infos.user_id。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 接收方 UserInfo 关系。
     */
    public function target()
    {
        return $this->belongsTo(UserInfo::class, 'target_user_id', 'user_id');
    }

    /**
     * 关联本笔转账对应的出队记录。
     *
     * 逻辑说明：
     * - commission_transfer_outbox.commission_transfer_id 对应本表 id，一笔转账只对应一条出队事件。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne 对应的出队记录。
     */
    public function outbox()
    {
        return $this->hasOne(CommissionTransferOutbox::class, 'commission_transfer_id');
    }
}
