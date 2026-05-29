<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 代理后代关系模型 | Agent Descendant Model
 * 
 * 维护代理与下属（代理或普通会员）之间的层级关系。
 * Maintains hierarchical relationships between agents and descendants (agents or members).
 */
class AgentDescendant extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'agent_descendants';

    /**
     * 关联代理人 | Relation: Agent
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function agent()
    {
        return $this->belongsTo(UserInfo::class, 'agent_id', 'user_id');
    }

    /**
     * 关联后代 | Relation: Descendant
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function descendant()
    {
        return $this->belongsTo(UserInfo::class, 'descendant_id', 'user_id');
    }

    /**
     * 作用域：直属下级代理 | Scope: Direct sub-agents
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $agentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDirectAgents($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 1)
            ->where('is_direct', 1);
    }

    /**
     * 作用域：所有下级代理（直属+非直属） | Scope: All sub-agents (direct + indirect)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $agentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAllAgents($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 1);
    }

    /**
     * 作用域：直属客户 | Scope: Direct customers
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $agentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDirectCustomers($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 2)
            ->where('is_direct', 1);
    }

    /**
     * 作用域：所有客户（直属+非直属） | Scope: All customers (direct + indirect)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $agentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAllCustomers($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 2);
    }
}
