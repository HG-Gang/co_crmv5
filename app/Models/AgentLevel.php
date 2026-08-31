<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:53
 */

namespace App\Models;

/**
 * 代理等级模型。
 *
 * 文件功能：
 * - agent_levels 表保存代理等级与返佣比例配置，前台代理资料、后台代理等级管理和返佣计算都会读取该表。
 * - level_code 表示代理等级编码，迁移旧项目等级时用于保持稳定映射。
 * - name 表示代理等级展示名称。
 * - max_commission 表示代理最大返佣比例，min_commission 表示代理最小返佣比例。
 * - user_commission 表示普通客户默认返佣比例，用于代理给客户开户或调级时的默认值。
 */
class AgentLevel extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 agent_levels。
     */
    protected $table = 'agent_levels';
}
