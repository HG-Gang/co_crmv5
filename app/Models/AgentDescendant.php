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
 * 代理后代关系模型。
 *
 * 文件功能：
 * - agent_descendants 表保存代理与下级代理或客户之间的层级闭包关系，是后台数据范围、前台代理客户列表和返佣统计的基础表。
 * - agent_id 表示上级代理业务用户 ID，对应 user_infos.user_id。
 * - descendant_id 表示下级业务用户 ID，可以是下级代理，也可以是普通客户。
 * - descendant_type 表示后代类型：1=代理，2=普通客户。
 * - is_direct 表示是否直属关系：1=直属，0=非直属。
 * - depth 表示上级代理到后代节点的层级距离，直属通常为 1。
 */
class AgentDescendant extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 agent_descendants。
     */
    protected $table = 'agent_descendants';

    /**
     * 关联上级代理的业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 agent_id 来自 agent_descendants.agent_id，表示拥有该后代节点的上级代理。
     * - 目标键 user_id 来自 user_infos.user_id，兼容旧项目业务用户编号。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回上级代理 UserInfo 关系。
     */
    public function agent()
    {
        return $this->belongsTo(UserInfo::class, 'agent_id', 'user_id');
    }

    /**
     * 关联下级代理或客户的业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 descendant_id 来自 agent_descendants.descendant_id，表示当前关系中的下级业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保证代理和客户都使用同一业务用户编号。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回下级业务用户 UserInfo 关系。
     */
    public function descendant()
    {
        return $this->belongsTo(UserInfo::class, 'descendant_id', 'user_id');
    }

    /**
     * 限定某个代理的直属下级代理。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 代理后代关系查询构造器。
     * @param int $agentId 表示当前查询的上级代理业务用户 ID。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 descendant_type=1 和 is_direct=1 条件的查询构造器。
     */
    public function scopeDirectAgents($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 1)
            ->where('is_direct', 1);
    }

    /**
     * 限定某个代理的全部下级代理，包含直属和非直属。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 代理后代关系查询构造器。
     * @param int $agentId 表示当前查询的上级代理业务用户 ID。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 descendant_type=1 条件的查询构造器。
     */
    public function scopeAllAgents($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 1);
    }

    /**
     * 限定某个代理的直属普通客户。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 代理后代关系查询构造器。
     * @param int $agentId 表示当前查询的上级代理业务用户 ID。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 descendant_type=2 和 is_direct=1 条件的查询构造器。
     */
    public function scopeDirectCustomers($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 2)
            ->where('is_direct', 1);
    }

    /**
     * 限定某个代理的全部普通客户，包含直属和非直属。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 代理后代关系查询构造器。
     * @param int $agentId 表示当前查询的上级代理业务用户 ID。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 descendant_type=2 条件的查询构造器。
     */
    public function scopeAllCustomers($query, $agentId)
    {
        return $query->where('agent_id', $agentId)
            ->where('descendant_type', 2);
    }
}
