<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:07
 */

namespace App\Models;

/**
 * 入金记录模型。
 *
 * 文件功能：
 * - deposit_records 表保存前台用户入金申请和后台审核结果，是后台入金审核、资金流水展示和用户资金追踪的数据来源。
 * - user_id 表示入金所属业务用户 ID，对应 user_infos.user_id，用于按代理层级、管理员数据范围和用户维度过滤记录。
 * - user_name 表示入金用户展示名称，便于后台列表快速识别申请人。
 * - mt4_ticket 表示关联的 MT4 交易账号或资金系统票据号，用于和外部交易系统核对。
 * - amount 表示用户申请入金金额，actual_amount 表示实际到账金额，exchange_rate 表示入金折算汇率。
 * - channel_name 表示支付渠道名称，channel_order_no 表示渠道侧订单号，local_order_no 表示本地入金订单号。
 * - status 表示入金审核状态，后台列表和审核接口应按该字段判断待审、通过、拒绝等处理流转。
 * - payment_time 表示付款时间，remarks 表示后台或导入流程保留的补充说明。
 * - created_by 和 updated_by 表示创建、更新该记录的后台管理员 ID，用于审计追踪。
 */
class DepositRecord extends BaseModel
{
    /**
     * 退款时间写入拦截器。
     *
     * 逻辑说明：
     * - refund_time 列按 datetime 类型存储，手动赋值时统一格式化为 Y-m-d H:i:s，避免入库格式漂移。
     * - 传入 null 时保持 null，表示未发生退款。
     *
     * @param mixed $value 传入的退款时间，可为日期对象、可解析字符串或 null。
     * @return void
     */
    public function setRefundTimeAttribute($value): void
    {
        $this->attributes['refund_time'] = $value === null
            ? null
            : $this->asDateTime($value)->format('Y-m-d H:i:s');
    }

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - user_id 表示入金所属业务用户 ID，user_name 表示用户展示名称快照。
     * - mt4_ticket 表示关联 MT4 票据号，refund_mt4_ticket 表示退款关联票据号。
     * - amount 表示申请入金金额，actual_amount 表示实际到账金额，exchange_rate 表示折算汇率。
     * - channel_name、channel_order_no、local_order_no 表示渠道与本地订单标识。
     * - idempotency_key 表示幂等键，防止支付回调或重试重复处理。
     * - gateway_code、merchant_id 表示支付网关与商户标识，currency 表示结算币种。
     * - provider_amount 表示渠道侧入账金额，provider_payload_hash 表示请求载荷哈希，provider_order_result 表示渠道返回的原始结果。
     * - provider_create_started_at、provider_create_attempts 表示渠道订单创建进度与尝试次数。
     * - payment_status、settlement_status 表示支付与结算阶段状态，status 表示入金审核状态。
     * - payment_time、refund_time 表示支付与退款时间，remarks 表示备注。
     * - created_by、updated_by 表示创建、更新该记录的后台管理员 ID。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'mt4_ticket',
        'refund_mt4_ticket',
        'amount',
        'actual_amount',
        'exchange_rate',
        'channel_name',
        'channel_order_no',
        'local_order_no',
        'idempotency_key',
        'gateway_code',
        'merchant_id',
        'currency',
        'provider_amount',
        'payment_status',
        'settlement_status',
        'provider_payload_hash',
        'provider_order_result',
        'provider_create_started_at',
        'provider_create_attempts',
        'status',
        'payment_time',
        'refund_time',
        'remarks',
        'created_by',
        'updated_by',
    ];

    /**
     * 字段类型转换。
     *
     * 字段含义：
     * - 金额字段保留两位小数，exchange_rate 保留 8 位小数。
     * - provider_order_result 读取为数组，用于展示渠道返回的原始结果。
     * - provider_create_started_at、refund_time 读取为 Carbon 对象。
     * - provider_create_attempts、refund_mt4_ticket 转为整数。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'provider_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'provider_order_result' => 'array',
        'provider_create_started_at' => 'datetime',
        'provider_create_attempts' => 'integer',
        'refund_mt4_ticket' => 'integer',
        'refund_time' => 'datetime',
    ];

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 deposit_records。
     */
    protected $table = 'deposit_records';

    /**
     * 关联入金记录所属业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 deposit_records.user_id，表示本条入金记录所属的业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保证与旧项目业务用户编号保持兼容。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回入金记录所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
