<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:27
 */

namespace App\Models;

/**
 * 角色模型。
 *
 * 文件功能：
 * - roles 表保存后台管理员、前台代理商和普通客户可绑定的角色。
 * - guard_type 用于区分 admin 和 front，admin 表示后台管理员角色，front 表示前台用户角色。
 * - role_permissions 是唯一生效的权限授权来源，菜单、按钮和接口是否可用都应从该中间表计算。
 * - roles.permissions JSON 只保留兼容字段，不再作为真实鉴权来源，避免形成双数据源。
 * - role_data_scopes.role_id 通过 dataScope() 关联，用于后台管理员的数据查看范围配置。
 */
class Role extends BaseModel
{
    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - name 表示角色稳定名称，例如 super_admin、agent_role、customer_role。
     * - guard_type 表示角色守卫类型，admin=后台角色，front=前台角色。
     * - description 表示角色用途说明，供后台页面识别。
     * - permissions 表示历史 JSON 兼容字段，不作为当前鉴权来源。
     * - status 表示角色启停状态，1=启用，0=停用。
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'guard_type', 'description', 'permissions', 'status'];

    /**
     * 字段类型转换。
     *
     * 参数含义：
     * - permissions：把历史 JSON 字段转成数组，仅用于兼容旧数据展示。
     * - status：把启停状态转成整数，便于接口和页面稳定判断。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permissions' => 'array',
        'status' => 'integer',
    ];

    /**
     * 关联角色拥有的权限集合。
     *
     * 逻辑说明：
     * - role_permissions.role_id 对应 roles.id。
     * - role_permissions.permission_id 对应 permissions.id。
     * - 该关联是菜单、按钮和接口权限分配的唯一生效来源。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany 角色拥有的权限集合。
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    /**
     * 兼容旧调用名。
     *
     * 逻辑说明：
     * - permissionsRelation 保留给旧控制器、菜单服务和测试使用。
     * - 实际仍返回 permissions() 关系，不引入第二套授权数据来源。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany 角色拥有的权限集合。
     */
    public function permissionsRelation()
    {
        return $this->permissions();
    }

    /**
     * 关联角色数据范围配置。
     *
     * 逻辑说明：
     * - role_data_scopes.role_id 对应 roles.id。
     * - 数据范围只决定“能看哪些数据”，不决定“能访问哪些菜单或接口”。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne 当前角色的数据范围配置。
     */
    public function dataScope()
    {
        return $this->hasOne(RoleDataScope::class, 'role_id', 'id');
    }

    /**
     * 检查当前角色是否拥有指定权限。
     *
     * 参数含义：
     * - $slug 表示 permissions.slug，前端菜单、前端按钮和后端接口共同使用的稳定权限标识。
     *
     * 逻辑边界：
     * - role_permissions 是唯一生效的权限授权来源。
     * - roles.permissions JSON 只保留兼容字段，不参与当前权限判断。
     * - 传入 * 只用于判断超级权限，实际超级管理员仍由角色名 super_admin 控制。
     *
     * @param string $slug 权限唯一标识，例如 admin_user_update 或 admin_deposit_approve。
     * @return bool true=角色拥有该权限，false=角色未拥有该权限。
     */
    public function hasPermission($slug)
    {
        if ($slug === '*') {
            return $this->name === 'super_admin';
        }

        return $this->permissionsRelation()
            ->where('permissions.slug', $slug)
            ->where('permissions.status', 1)
            ->exists();
    }

    /**
     * 关联后台管理员。
     *
     * 逻辑说明：
     * - roles.id 对应 admins.role_id。
     * - 后台管理员登录后通过该关系读取角色，再由 role_permissions 计算菜单、按钮和接口权限。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 绑定当前角色的后台管理员集合。
     */
    public function admins()
    {
        return $this->hasMany(Admin::class, 'role_id');
    }
}
