<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:07
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 礼品配置模型。
 *
 * 文件功能：
 * - gift_items 表保存后台可配置的可兑换礼品目录，用于前台 available_gifts 展示。
 * - points_cost 表示兑换该礼品需要的积分，stock_quantity 表示当前可兑换库存。
 * - status=1 且 stock_quantity>0 的记录才会进入前台可兑换列表。
 */
class GiftItem extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 gift_items。
     */
    protected $table = 'gift_items';

    /**
     * 字段类型转换。
     *
     * 字段含义：
     * - points_cost 表示兑换所需积分，stock_quantity 表示当前库存，status 表示启停状态，均转为整数便于比较。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'points_cost' => 'integer',
        'stock_quantity' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 限定当前可兑换的礼品。
     *
     * 逻辑说明：
     * - 仅 status=1（启用）且 stock_quantity>0（有库存）的礼品进入前台可兑换列表，与文件级描述保持一致。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 礼品查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加启用与库存条件限定的查询构造器。
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 1)->where('stock_quantity', '>', 0);
    }
}
