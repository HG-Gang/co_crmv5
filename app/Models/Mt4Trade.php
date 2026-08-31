<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * MT4 交易记录模型。
 *
 * 文件功能：
 * - mt4_trades 表保存从 MT4 同步的交易订单，用于交易列表、持仓、历史平仓和资金流水统计。
 * - ticket 表示 MT4 订单号，login 表示 MT4 登录账号，symbol 表示交易品种。
 * - cmd 表示 MT4 交易命令类型，其中 cmd=6 表示余额类交易，可用于识别入金、出金或调账流水。
 * - profit、commission、swaps 表示订单盈亏、手续费和库存费，是后台风控与权益统计的基础字段。
 * - open_time 和 close_time 表示开仓与平仓时间；未平仓订单通常由业务查询按时间或状态规则筛选。
 */
class Mt4Trade extends BaseModel
{
    use HasFactory;

    /**
     * mt4_trades 是外部 MT4 同步快照表，真实表结构没有 deleted_at 字段。
     *
     * BaseModel 默认启用 SoftDeletes，但本模型必须禁用软删除全局作用域，
     * 否则所有查询都会追加 `mt4_trades.deleted_at is null` 并在真实库中报错。
     *
     * @return void
     */
    public static function bootSoftDeletes()
    {
        // 故意留空：mt4_trades 真实表没有 deleted_at，必须跳过 BaseModel 默认的软删除全局作用域。
    }

    /**
     * 模型对应的数据表名称。
     *
     * 参数逻辑说明：
     * - $table 指向 mt4_trades 表，保存从 MT4 同步的订单记录。
     *
     * @var string
     */
    protected $table = 'mt4_trades';

    /**
     * user() 通过 login 字段关联业务用户。
     *
     * 关联逻辑说明：
     * - mt4_trades.login 是 MT4 登录号，必须对应 user_infos.mt4_code，不能把业务 user_id 猜成交易账号。
     * - 交易列表、实盘/测试盘筛选和后台数据范围通过该关系回到业务用户维度。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 交易订单所属业务用户。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'login', 'mt4_code');
    }
}
