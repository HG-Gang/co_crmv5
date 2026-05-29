<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 系统配置模型 | System Config Model
 * 
 * 管理系统的各项全局配置参数。
 * Manages various global configuration parameters of the system.
 */
class SystemConfig extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'system_configs';

    /**
     * 获取配置值 | Get Config Value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getVal($key, $default = null)
    {
        $config = self::where('key', $key)->first();
        return $config ? $config->value : $default;
    }
}
