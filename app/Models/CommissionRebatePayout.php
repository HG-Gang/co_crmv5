<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 18:23
 */

declare(strict_types=1);

namespace App\Models;

/**
 * 旧 comm_summary 单代理返佣出账模型。
 *
 * 文件功能：
 * - 记录一笔源交易向一名上级代理发放返佣的完整状态。
 * - source_trade_id + agent_id 是幂等业务键，防止重复访问旧路由导致重复 MT4 入金。
 * - status 区分可安全重试的未发送、不可重发的未知结果和已结算结果。
 * - calculation_type、spread、volume_multiplier 固化手续费或点差返佣的计算依据。
 *
 * 状态含义：
 * - pending：已写入本地意图，尚未向 MT4 发送。
 * - processing：已被一个进程声明，不能由并发请求再次发送。
 * - settled：MT4 已确认入账并已写入本地返佣账本。
 * - retryable：MT4 明确未接收命令，可以在 available_at 后重试。
 * - rejected：MT4 明确拒绝，本地保留失败证据，等待业务处理。
 * - unknown：命令可能已发送或本地提交失败，禁止自动重发，等待人工核对。
 * - not_payable：线下结算或非正差额，不需要调用 MT4。
 */
class CommissionRebatePayout extends BaseModel
{
    /**
     * 模型绑定的真实表名。
     *
     * @var string $table 返佣出账审计表名称。
     */
    protected $table = 'commission_rebate_payouts';

    /**
     * 可批量写入的返佣出账字段。
     *
     * @var array<int, string> 字段只包含服务层构建的业务快照和状态字段。
     */
    protected $fillable = [
        'source_trade_id', 'source_ticket', 'trader_user_id', 'agent_id', 'parent_id', 'volume',
        'rate_difference', 'group_radix', 'amount', 'comment', 'calculation_type', 'spread', 'volume_multiplier', 'status', 'attempts', 'available_at',
        'locked_at', 'processed_at', 'provider_reference', 'last_error_code',
    ];

    /**
     * 时间和数值字段的类型转换定义。
     *
     * @var array<string, string> 让服务层读取 Unix 时间戳时得到 Carbon 对象，读取数值时得到整数。
     */
    protected $casts = [
        'source_trade_id' => 'integer',
        'source_ticket' => 'integer',
        'trader_user_id' => 'integer',
        'agent_id' => 'integer',
        'parent_id' => 'integer',
        'volume' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * 关联产生返佣的原始 MT4 交易。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回源 user_trades 交易关系。
     */
    public function sourceTrade()
    {
        return $this->belongsTo(UserTrade::class, 'source_trade_id');
    }

    /**
     * 关联产生交易的普通客户或下级代理资料。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回 trader_user_id 对应的用户资料。
     */
    public function trader()
    {
        return $this->belongsTo(UserInfo::class, 'trader_user_id', 'user_id');
    }

    /**
     * 关联收取返佣的上级代理资料。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回 agent_id 对应的代理资料。
     */
    public function agent()
    {
        return $this->belongsTo(UserInfo::class, 'agent_id', 'user_id');
    }
}
