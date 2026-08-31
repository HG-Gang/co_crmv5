<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:45
 */

namespace App\Models;

/**
 * 用户交易模型。
 *
 * 文件功能：
 * - user_trades 表保存用户 MT4 交易订单数据，用于后台交易查询、风控持仓、前台订单列表和返佣统计。
 * - user_id 表示交易订单所属业务用户 ID，对应 user_infos.user_id。
 * - ticket 表示 MT4 订单号，symbol 表示交易品种代码，cmd 表示订单方向或交易命令类型。
 * - volume 表示交易手数，digits 表示报价小数位数。
 * - open_time 和 open_price 表示开仓时间和开仓价格。
 * - stop_loss 和 take_profit 表示止损价和止盈价。
 * - close_time 表示订单平仓时间，旧 MT4 未平仓订单固定为 1970-01-01 00:00:00。
 * - close_price 表示平仓价格，profit 表示订单盈亏，commission、commission_agent、swaps 表示手续费、代理佣金和库存费。
 * - settlement_status 表示订单返佣或结算状态，settled_at 表示订单完成结算时间。
 * - comment、internal_id、magic 等字段保留 MT4 原始订单附加信息，便于排查和对账。
 */
class UserTrade extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 user_trades。
     */
    protected $table = 'user_trades';

    /**
     * 关联交易订单所属业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 user_trades.user_id，表示该交易订单属于哪个业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回交易订单所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }

    /**
     * 查询当前持仓订单。
     *
     * 业务规则：
     * - 旧 MT4 数据中未平仓订单的 close_time 固定为 `1970-01-01 00:00:00`。
     * - 风控和交易持仓列表都依赖该规则筛选未平仓数据。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示交易订单查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加未平仓条件的查询构造器。
     */
    public function scopeOpen($query)
    {
        return $query->where('close_time', '1970-01-01 00:00:00');
    }

    /**
     * 查询历史平仓订单。
     *
     * 业务规则：
     * - close_time 不等于 `1970-01-01 00:00:00` 即视为已经平仓。
     * - 该 scope 供历史订单列表复用，避免控制器重复硬编码平仓判断。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示交易订单查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加平仓条件的查询构造器。
     */
    public function scopeClosed($query)
    {
        return $query->where('close_time', '!=', '1970-01-01 00:00:00');
    }
}
