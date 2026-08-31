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
 * 代理节点统计模型。
 *
 * 文件功能：
 * - agent_node_stats 表用于保存代理节点统计快照，通常包括直属代理、非直属代理、直属客户和非直属客户数量。
 * - 当前数据库未建表时不得在业务查询中直接依赖该模型，应继续以 agent_descendants 实时关系表为准。
 * - agent_id 表示被统计的代理业务用户 ID，对应 user_infos.user_id。
 * - direct_agent_count 表示直属下级代理数量，indirect_agent_count 表示非直属下级代理数量。
 * - direct_customer_count 表示直属客户数量，indirect_customer_count 表示非直属客户数量。
 * - last_calculated_at 表示统计最后计算时间，用于判断缓存统计是否需要刷新。
 */
class AgentNodeStats extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示代理节点统计预留表名，固定为 agent_node_stats。
     */
    protected $table = 'agent_node_stats';

    /**
     * 字段类型转换配置。
     *
     * @var array<string, string> $casts 表示统计时间字段读取时转换为日期时间对象。
     */
    protected $casts = [
        'last_calculated_at' => 'datetime',
    ];
}
