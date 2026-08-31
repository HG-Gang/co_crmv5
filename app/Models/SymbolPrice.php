<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:45
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 交易品种价格模型。
 *
 * 文件功能：
 * - symbol_prices 表保存交易品种实时或历史报价，用于产品行情展示、风控和报价同步检查。
 * - symbol 表示交易品种代码，例如 XAUUSD 或 EURUSD。
 * - time 表示报价时间，modify_time 表示报价在外部系统中的更新时间。
 * - bid 表示买价，ask 表示卖价，low 表示周期最低价，high 表示周期最高价。
 * - direction 表示价格方向，digits 表示报价小数位数，spread 表示点差。
 * - group_id 表示报价所属交易组 ID，status 表示该报价记录是否启用或有效。
 */
class SymbolPrice extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 symbol_prices。
     */
    protected $table = 'symbol_prices';
}
