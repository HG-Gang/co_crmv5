<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 支付通道模型 | Payment Channel Model
 * 
 * 管理系统中可用的支付通道及其配置。
 * Manages available payment channels and their configurations in the system.
 */
class PaymentChannel extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'payment_channels';

    /**
     * 字段类型转换 | Attribute Casting
     * @var array
     */
    protected $casts = [
        'config' => 'array',
    ];

    /**
     * 作用域：启用的通道 | Scope: Enabled channels
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', 1);
    }
}
