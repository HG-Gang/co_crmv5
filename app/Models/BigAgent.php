<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 大代理模型 | Big Agent Model
 * 
 * 负责顶级大代理的管理与子代理关联。
 * Responsible for management of top-level big agents and sub-agent associations.
 */
class BigAgent extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'big_agents';

    /**
     * 关联登录日志 | Relation: Login Logs
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function loginLogs()
    {
        return $this->hasMany(BigAgentLoginLog::class, 'big_agent_id');
    }
}
