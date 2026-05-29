<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 权限模型 | Permission Model
 * 
 * 管理后台及前台的菜单、页面和按钮权限。
 * Manages menu, page, and button permissions for both backend and frontend.
 */
class Permission extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'permissions';

    /**
     * 时间戳存储格式：标准时间格式 | Timestamp format: Standard
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 可批量赋值的字段 | Fillable attributes
     * @var array
     */
    protected $fillable = [
        'parent_id', 'name', 'slug', 'api_route', 'route', 'icon', 'type', 'guard_type', 'sort', 'status'
    ];

    /**
     * 关联父权限 | Relation: Parent Permission
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

    /**
     * 关联子权限 | Relation: Children Permissions
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Permission::class, 'parent_id')->orderBy('sort');
    }

    /**
     * 关联角色 | Relation: Roles
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }

    /**
     * 作用域：后台权限 | Scope: Admin permissions
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdmin($query)
    {
        return $query->where('guard_type', 'admin');
    }

    /**
     * 作用域：前台权限 | Scope: Front permissions
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFront($query)
    {
        return $query->where('guard_type', 'front');
    }

    /**
     * 作用域：菜单类型 | Scope: Menu type
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMenu($query)
    {
        return $query->where('type', 1);
    }

    /**
     * 作用域：页面类型 | Scope: Page type
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePage($query)
    {
        return $query->where('type', 2);
    }

    /**
     * 作用域：按钮类型 | Scope: Button type
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeButton($query)
    {
        return $query->where('type', 3);
    }

    /**
     * 作用域：启用的权限 | Scope: Active permissions
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
