<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 代理等级模型 | Agent Level Model
 * 
 * 定义不同的代理等级及其佣金比例范围。
 * Defines different agent levels and their commission rate ranges.
 */
class AgentLevel extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'agent_levels';
}
