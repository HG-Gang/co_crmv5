<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 点差配置模型 | Spread Config Model
 * 
 * 管理不同交易产品的点差配置。
 * Manages spread configurations for different trading products.
 */
class SpreadConfig extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'spread_configs';
}
