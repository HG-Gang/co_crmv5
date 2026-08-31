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
 * 用户认证信息备份模型。
 *
 * 文件功能：
 * - user_auth_info 表曾用于保存用户认证历史快照或旧项目认证备份数据。
 * - 当前数据库未建 user_auth_info 表时不得在业务查询中直接依赖该模型，应以 user_auths 表作为实名认证和银行卡认证的真实数据源。
 * - 本模型仅作为历史代码兼容入口保留，后续如要重新启用必须先补迁移、数据迁移和回归测试。
 */
class UserAuthInfo extends BaseModel
{
    /**
     * 模型绑定的历史数据表名称。
     *
     * @var string $table 表示历史兼容表名，当前数据库未建表时不能直接查询。
     */
    protected $table = 'user_auth_info';
}
