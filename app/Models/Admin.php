<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 管理员模型 | Admin Model
 *
 * 负责管理后台管理员的认证、权限及基本信息。
 * Responsible for admin authentication, permissions, and basic information.
 */
class Admin extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'admins';
    protected $guard = 'admin';

    /**
     * 时间戳存储格式：Unix时间戳 | Timestamp format: Unix
     */
    protected $dateFormat = 'U';

    protected $fillable = [
        'username', 'email', 'password', 'mobile',
        'role_id', 'status', 'last_login_ip', 'last_login_at',
        'last_login_address', 'login_count', 'created_by', 'jwt_token_id'
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'status' => 'integer',
        'login_count' => 'integer',
    ];

    /**
     * 序列化日期为可读格式 | Serialize dates to readable format
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 关联角色 | Relation: Role
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * 检查权限 | Check Permission
     */
    public function hasPermission($slug)
    {
        if (!$this->role) return false;
        return $this->role->hasPermission($slug);
    }

    /**
     * 获取所有权限 | Get All Permissions
     */
    public function getAllPermissions()
    {
        return $this->role ? ($this->role->permissions ?? []) : [];
    }

    /**
     * 关联登录日志 | Relation: Login Logs
     */
    public function loginLogs()
    {
        return $this->hasMany(AdminLoginLog::class, 'admin_id');
    }

    /**
     * 是否启用 | Is Active
     */
    public function isActive()
    {
        return (int)$this->status === 1;
    }
}
