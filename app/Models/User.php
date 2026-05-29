<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 前台用户模型 | User Model
 * 
 * 负责前台用户的认证及基本信息。
 * Responsible for front-end user authentication and basic information.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 可批量赋值的字段 | Mass Assignable Fields
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * 序列化时隐藏的字段 | Hidden Fields for Serialization
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 字段类型转换 | Attribute Casting
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * 关联角色 | Relation: Role
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * 检查用户是否拥有权限 | Check if user has permission
     * @param string $slug
     * @return bool
     */
    public function hasPermission($slug)
    {
        if ($this->role) {
            return $this->role->hasPermission($slug);
        }
        return false;
    }
}
