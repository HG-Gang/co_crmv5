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
 * 用户组兼容模型。
 *
 * 文件功能：
 * - user_groups 表曾用于保存旧项目用户组和交易费率配置。
 * - 当前数据库未建 user_groups 表时不得在业务查询中直接依赖该模型，应优先使用 group_configs 表承载代理组和客户交易组配置。
 * - 本模型仅作为历史代码兼容入口保留，后续如要重新启用必须先补迁移、种子数据和回归测试。
 */
class UserGroup extends BaseModel
{
    /**
     * 模型绑定的历史数据表名称。
     *
     * @var string $table 表示历史兼容表名，当前数据库未建表时不能直接查询。
     */
    protected $table = 'user_groups';
}
