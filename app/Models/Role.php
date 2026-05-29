<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 角色模型 | Role Model
 * 
 * 定义用户的角色及其对应的权限集合。
 * Defines user roles and their corresponding set of permissions.
 */
class Role extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'roles';

    /**
     * 可批量赋值的属性 | The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = ['name', 'guard_type', 'description', 'permissions', 'status'];

    /**
     * 字段类型转换 | Attribute Casting
     * @var array
     */
    protected $casts = [
        'permissions' => 'array',
        'status' => 'integer',
    ];

    /**
     * 关联权限 | Relation: Permissions
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    public function permissionsRelation()
    {
        return $this->permissions();
    }

    /**
     * 检查是否拥有特定权限 | Check if has a specific permission
     * 
     * @param string $slug
     * @return bool
     */
    public function hasPermission($slug)
    {
        if ($slug === '*') return true;
        $permissions = is_array($this->permissions) ? $this->permissions : [];
        return in_array($slug, $permissions);
    }

    /**
     * 关联管理员 | Relation: Admins
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function admins()
    {
        return $this->hasMany(Admin::class, 'role_id');
    }
}
