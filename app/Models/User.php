<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:36
 */

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Laravel 默认前台用户兼容模型。
 *
 * 文件功能：
 * - 当前业务登录主体优先使用 UserLogin，对应真实业务表 user_logins。
 * - 该模型保留 Laravel 默认用户体系兼容能力，避免旧依赖或框架功能引用 User 时失效。
 * - role_id 表示绑定的 roles.id，权限判断仍委托给 Role::hasPermission。
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - name 表示兼容用户名称。
     * - email 表示兼容登录邮箱。
     * - password 表示密码哈希，禁止对外序列化。
     * - role_id 表示绑定的 roles.id。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * 序列化隐藏字段。
     *
     * 逻辑说明：
     * - password 和 remember_token 都属于认证敏感信息，不能出现在接口响应中。
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 字段类型转换。
     *
     * 字段含义：
     * - email_verified_at 转为日期时间对象，保持 Laravel 默认认证能力兼容。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * 关联角色。
     *
     * 逻辑说明：
     * - users.role_id 对应 roles.id。
     * - 该关系仅用于兼容默认 User 模型的角色权限判断。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 兼容角色。
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * 检查用户是否拥有权限。
     *
     * 参数含义：
     * - $slug 表示 permissions.slug，是菜单、按钮和接口共用的稳定权限字符串。
     *
     * 逻辑说明：
     * - 如果当前兼容用户未绑定角色，直接返回 false。
     * - 具体判断委托给 Role::hasPermission，避免在 User 模型中维护第二套权限逻辑。
     *
     * @param string $slug 权限字符串。
     * @return bool true=拥有权限，false=没有权限。
     */
    public function hasPermission($slug)
    {
        if ($this->role) {
            return $this->role->hasPermission($slug);
        }
        return false;
    }
}
