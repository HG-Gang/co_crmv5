<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:50
 */

namespace App\Models;

/**
 * 出金记录模型。
 *
 * 文件功能：
 * - withdraw_records 表保存前台用户出金申请和后台处理结果，是后台出金审核、资金风控和用户提现查询的数据来源。
 * - user_id 表示出金所属业务用户 ID，对应 user_infos.user_id，用于按代理层级、管理员数据范围和用户维度过滤记录。
 * - user_name 表示出金用户展示名称，mt4_ticket 表示关联的 MT4 交易账号或资金系统票据号。
 * - apply_amount 表示出金申请金额，actual_amount 表示实际出金金额，fee 表示手续费，rmb_fee 表示人民币手续费。
 * - exchange_rate 表示出金折算汇率，bank_no、bank_name、bank_addr 分别表示收款银行卡号、开户行名称和开户地址。
 * - status 表示出金处理状态，local_order_no 表示本地出金订单号，third_order_no 表示第三方支付或资金系统订单号。
 * - reject_reason 表示拒绝原因，后台驳回出金申请时必须写入可追溯说明。
 * - mt4_return_status 表示 MT4 或外部资金系统返回状态，用于判断后续同步是否成功。
 * - created_by 和 updated_by 表示创建、更新该记录的后台管理员 ID，用于审计追踪。
 */
class WithdrawRecord extends BaseModel
{
    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - 申请字段：user_id、user_name、mt4_ticket、apply_amount、actual_amount、fee、exchange_rate、rmb_fee。
     * - 收款信息：bank_no、bank_name、bank_addr。
     * - 状态与订单：status、local_order_no、third_order_no、reject_reason、mt4_return_status。
     * - 幂等与资金：idempotency_key（按 (idempotency_key, user_id) 唯一防重）、funding_status、funding_payload_hash、funding_error_code。
     * - 退款字段：refund_mt4_ticket、refund_time。
     * - 审计字段：created_by、updated_by。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'mt4_ticket',
        'apply_amount',
        'actual_amount',
        'fee',
        'exchange_rate',
        'rmb_fee',
        'bank_no',
        'bank_name',
        'bank_addr',
        'status',
        'local_order_no',
        'third_order_no',
        'reject_reason',
        'mt4_return_status',
        'idempotency_key',
        'funding_status',
        'funding_payload_hash',
        'refund_mt4_ticket',
        'refund_time',
        'funding_error_code',
        'created_by',
        'updated_by',
    ];

    /**
     * 字段类型转换。
     *
     * 逻辑说明：
     * - MySQL DECIMAL 金额与汇率保持驱动返回的原始字符串，避免 Laravel 8 decimal cast
     *   内部经过浮点格式化后在大额边界丢失精度。
     * - user_id、refund_mt4_ticket 转为整数，refund_time 按日期对象处理。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'refund_mt4_ticket' => 'integer',
        'refund_time' => 'datetime',
    ];

    /**
     * 写入 refund_time 时统一格式化。
     *
     * 逻辑说明：
     * - 该列在迁移中为 DATETIME，而 BaseModel 的 $dateFormat=U 会把日期类型值转成 Unix 秒，
     *   因此此处绕过默认转换，直接按 Y-m-d H:i:s 字符串落库，与既有行数据格式保持一致。
     * - $value 为 null 时保持 null，避免把空值写成 '0000-00-00 00:00:00' 影响查询语义。
     *
     * @param mixed $value 退款时间，null 或可被 Eloquent 解析的日期值。
     * @return void
     */
    public function setRefundTimeAttribute($value): void
    {
        $this->attributes['refund_time'] = $value === null
            ? null
            : $this->asDateTime($value)->format('Y-m-d H:i:s');
    }

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 withdraw_records。
     */
    protected $table = 'withdraw_records';

    /**
     * 关联出金记录所属业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 withdraw_records.user_id，表示本条出金记录所属的业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保证与旧项目业务用户编号保持兼容。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回出金记录所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }

    /**
     * 关联该出金记录产生的资金结算出队记录。
     *
     * 逻辑说明：
     * - withdraw_settlement_outbox.withdraw_record_id 对应 withdraw_records.id。
     * - 出金扣减/退款链路通过该关系写入或检查出队事件。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 出金结算出队记录集合。
     */
    public function settlementOutboxes()
    {
        return $this->hasMany(WithdrawSettlementOutbox::class, 'withdraw_record_id');
    }
}
