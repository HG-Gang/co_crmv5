<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:24
 */

namespace App\Models;

/**
 * 权限模型。
 *
 * 文件功能：
 * - permissions 表保存前后台菜单、页面、按钮和接口权限字典。
 * - slug 表示稳定权限字符串，是前端按钮和后端接口共同使用的权限标识，例如 admin_role_assign_permissions。
 * - api_route 表示 Laravel 命名路由，例如 admin_api_assignPermissions，供权限中间件匹配接口访问权限。
 * - guard_type 用于区分 admin 和 front，避免后台管理员权限和前台代理/客户菜单权限混用。
 * - type=1 表示菜单，type=2 表示页面，type=3 表示按钮或接口动作。
 */
class Permission extends BaseModel
{
    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'permissions';

    /**
     * 时间戳保存格式。
     *
     * 逻辑说明：
     * - permissions 表使用标准日期时间字符串，保持与菜单、权限管理页面展示一致。
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - parent_id 表示父级 permissions.id，0 表示顶级节点。
     * - name 表示权限展示名称。
     * - slug 表示稳定权限字符串，供前端 data-permission 和后端授权判断使用。
     * - api_route 表示 Laravel 命名路由，供 check.permission:admin 匹配接口权限。
     * - route 表示 Blade 页面或前端页面路径，供菜单点击跳转。
     * - icon 表示菜单图标类名。
     * - type 表示权限类型，1=菜单，2=页面，3=按钮或接口动作。
     * - guard_type 表示权限守卫类型，admin=后台，front=前台。
     * - sort 表示同级排序值。
     * - status 表示启停状态，1=启用，0=停用。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'api_route',
        'route',
        'icon',
        'type',
        'guard_type',
        'sort',
        'status',
    ];

    /**
     * 关联父级权限。
     *
     * 逻辑说明：
     * - 当前节点 parent_id 对应父级 permissions.id。
     * - 菜单树、权限树和按钮挂载关系都依赖该字段构建层级。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 父级权限节点。
     */
    public function parent()
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

    /**
     * 关联子权限集合。
     *
     * 逻辑说明：
     * - 子节点的 parent_id 对应当前 permissions.id。
     * - 默认按 sort 排序，保证菜单和权限树展示稳定。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 子权限节点集合。
     */
    public function children()
    {
        return $this->hasMany(Permission::class, 'parent_id')->orderBy('sort');
    }

    /**
     * 关联拥有该权限的角色集合。
     *
     * 逻辑说明：
     * - role_permissions.permission_id 对应 permissions.id。
     * - role_permissions.role_id 对应 roles.id。
     * - 授权关系通过该中间表维护，不从角色 JSON 字段计算。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany 拥有该权限的角色集合。
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }

    /**
     * 限定后台权限。
     *
     * 参数含义：
     * - $query 表示 Eloquent 查询构造器，将追加 guard_type=admin 条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 权限查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 后台权限查询构造器。
     */
    public function scopeAdmin($query)
    {
        return $query->where('guard_type', 'admin');
    }

    /**
     * 限定前台权限。
     *
     * 参数含义：
     * - $query：Eloquent 查询构造器，将追加 guard_type=front 条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 权限查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 前台权限查询构造器。
     */
    public function scopeFront($query)
    {
        return $query->where('guard_type', 'front');
    }

    /**
     * 限定菜单类型权限。
     *
     * 参数含义：
     * - $query：Eloquent 查询构造器，将追加 type=1 条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 权限查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 菜单权限查询构造器。
     */
    public function scopeMenu($query)
    {
        return $query->where('type', 1);
    }

    /**
     * 限定页面类型权限。
     *
     * 参数含义：
     * - $query：Eloquent 查询构造器，将追加 type=2 条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 权限查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 页面权限查询构造器。
     */
    public function scopePage($query)
    {
        return $query->where('type', 2);
    }

    /**
     * 限定按钮或接口动作权限。
     *
     * 参数含义：
     * - $query：Eloquent 查询构造器，将追加 type=3 条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 权限查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 按钮或接口动作权限查询构造器。
     */
    public function scopeButton($query)
    {
        return $query->where('type', 3);
    }

    /**
     * 限定启用权限。
     *
     * 参数含义：
     * - $query：Eloquent 查询构造器，将追加 status=1 条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 权限查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 启用状态权限查询构造器。
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
