<?php
namespace App\Models;

use App\Models\BaseModel;

/**
 * 代理节点统计模型 | Agent Node Stats Model
 * 
 * 存储代理层级的统计数据，如直属/非直属代理和会员数量。
 * Stores statistical data for agent hierarchies, such as direct/indirect agent and member counts.
 */
class AgentNodeStats extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'agent_node_stats';

    /**
     * 字段类型转换 | Attribute Casting
     * @var array
     */
    protected $casts = [
        'last_calculated_at' => 'datetime',
    ];
}
