<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:54
 */

namespace App\Models;

/**
 * 系统配置模型。
 *
 * 文件功能：
 * - system_configs 表保存后台全局配置项，用于汇率、下载地址、出入金限制、开关项等系统级参数。
 * - key 表示配置唯一键，控制器和服务层应使用稳定 key 读取配置。
 * - value 表示配置值，按业务场景保存字符串、数字字符串或 JSON 字符串。
 * - group 表示配置分组，例如 general、risk、deposit 等，用于后台页面归类展示。
 * - description 表示配置说明，便于后台管理员理解该配置项用途。
 */
class SystemConfig extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 system_configs。
     */
    protected $table = 'system_configs';

    /**
     * 获取指定 key 的配置值。
     *
     * 参数逻辑说明：
     * - $key 表示 system_configs.key，用于定位唯一配置项。
     * - $default 表示配置不存在时返回的默认值，避免调用方重复写空值判断。
     *
     * @param string $key 配置唯一键。
     * @param mixed $default 配置不存在时返回的默认值。
     * @return mixed 返回配置值；配置不存在时返回 $default。
     */
    public static function getVal($key, $default = null)
    {
        $config = self::where('key', $key)->first();
        return $config ? $config->value : $default;
    }
}
