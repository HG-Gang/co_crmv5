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
 * 点差配置模型。
 *
 * 文件功能：
 * - spread_configs 表保存交易产品或代理组点差配置，用于后台点差管理和交易报价规则。
 * - spread 表示固定点差值。
 * - agent_group_id 表示代理组 ID，用于按代理组匹配点差规则。
 * - spread_ratio 表示点差比例，用于按比例计算或调整交易点差。
 * - status 表示点差配置状态，后台列表和业务读取应只使用启用配置。
 */
class SpreadConfig extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 spread_configs。
     */
    protected $table = 'spread_configs';
}
