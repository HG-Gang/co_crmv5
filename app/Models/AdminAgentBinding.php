<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 21:05
 */

namespace App\Models;

/**
 * 管理员代理绑定模型。
 *
 * 文件功能：
 * - admin_agent_bindings 表保存后台管理员与代理节点的绑定关系。
 * - admin_id 表示后台管理员 ID，对应 admins.id。
 * - agent_id 表示被授权管理的代理用户 ID，对应代理体系中的 user_infos.user_id。
 * - binding_type 表示绑定类型，primary=主绑定，extra=额外绑定。
 * - status 表示绑定是否启用，禁用绑定不参与 AdminDataScopeService 的代理树数据范围计算。
 */
class AdminAgentBinding extends BaseModel
{
    /**
     * 模型对应的数据表名称。
     *
     * 参数逻辑说明：
     * - $table 指向 admin_agent_bindings 表，保存管理员与代理节点的授权绑定。
     *
     * @var string
     */
    protected $table = 'admin_agent_bindings';

    /**
     * 字段类型转换。
     *
     * 参数逻辑说明：
     * - admin_id 表示后台管理员 ID，转为整数便于和 admins.id 比较。
     * - agent_id 表示被授权管理的代理用户 ID，转为整数便于和 user_infos.user_id、agent_descendants.agent_id 比较。
     * - status 表示启停状态，转为整数后可稳定判断 1=启用、0=禁用。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'admin_id' => 'integer',
        'agent_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * admin() 返回绑定所属后台管理员。
     *
     * 关联逻辑说明：
     * - admin_agent_bindings.admin_id 对应 admins.id。
     * - 后台查看管理员数据范围配置时可通过该关系读取管理员账号信息。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 绑定所属后台管理员。
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    /**
     * agent() 返回被绑定的代理业务用户资料。
     *
     * 关联逻辑说明：
     * - admin_agent_bindings.agent_id 对应 user_infos.user_id。
     * - AdminDataScopeService 会基于该代理节点继续结合 agent_descendants 表计算可见下级用户。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 被绑定的代理业务用户资料。
     */
    public function agent()
    {
        return $this->belongsTo(UserInfo::class, 'agent_id', 'user_id');
    }
}
