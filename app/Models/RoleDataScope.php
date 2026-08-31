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
 * 角色数据范围模型。
 *
 * 文件功能：
 * - role_data_scopes 表保存角色级数据查看范围配置，是“进入接口后能看哪些数据”的真实数据表来源。
 * - role_id 表示角色 ID，对应 roles.id；当前表通过唯一约束保证每个角色最多维护一条数据范围配置。
 * - scope_type 表示数据范围类型，例如 all=全部数据、self=本人数据、agent_tree=绑定代理树、custom_users=指定用户集合。
 * - agent_ids 表示指定代理 ID 数组，仅在 custom_agents 等代理集合范围中参与计算。
 * - user_ids 表示指定用户 ID 数组，仅在 custom_users 范围中参与计算。
 * - status 表示配置是否启用，禁用配置不应参与后台业务列表或单条动作的数据范围判断。
 */
class RoleDataScope extends BaseModel
{
    /**
     * 模型对应的数据表名称。
     *
     * 参数逻辑说明：
     * - $table 指向 role_data_scopes 表，避免 Laravel 按类名自动推断错误表名。
     *
     * @var string
     */
    protected $table = 'role_data_scopes';

    /**
     * 字段类型转换。
     *
     * 参数逻辑说明：
     * - role_id、status 转为整数，便于权限服务做精确比较。
     * - agent_ids、user_ids 转为数组，便于后台授权页面回显和 AdminDataScopeService 直接计算可见数据集合。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'role_id' => 'integer',
        'agent_ids' => 'array',
        'user_ids' => 'array',
        'status' => 'integer',
    ];

    /**
     * role() 返回当前数据范围所属角色。
     *
     * 关联逻辑说明：
     * - role_data_scopes.role_id 对应 roles.id。
     * - 后台角色授权页和数据范围服务通过该关系确认范围配置属于哪个管理员角色。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 当前数据范围所属角色。
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
}
