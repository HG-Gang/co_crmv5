<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:58
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 支付通道模型。
 *
 * 文件功能：
 * - payment_channels 表保存后台可用支付通道配置，用于前台入金渠道展示和后台通道管理。
 * - name 表示通道显示名称，channel_code 表示支付通道唯一编码。
 * - exchange_rate 表示通道入金汇率，前台入金金额折算和后台通道列表会读取该字段。
 * - is_enabled 表示通道是否启用，sort 表示通道展示排序值。
 * - config 表示通道扩展配置，通常保存商户号、回调参数、限额等 JSON 配置。
 */
class PaymentChannel extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 payment_channels。
     */
    protected $table = 'payment_channels';

    /**
     * 字段类型转换配置。
     *
     * @var array<string, string> $casts 表示 config JSON 字段读取时自动转换为数组。
     */
    protected $casts = [
        'config' => 'array',
    ];

    /**
     * 限定启用支付通道。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示支付通道查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 is_enabled=1 条件的查询构造器。
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', 1);
    }
}
