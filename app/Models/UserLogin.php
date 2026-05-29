<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 用户登录模型 | User Login Model
 * 
 * 负责用户的登录认证及账户状态。
 * Responsible for user authentication and account status.
 */
class UserLogin extends Authenticatable
{
    use SoftDeletes;

    /**
     * 日期时间格式 | Date Format
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_logins';

    /**
     * 可批量赋值的字段 | Mass Assignable Fields
     * @var array
     */
    protected $fillable = [
        'user_id', 'email', 'password', 'account_type', 'role_id',
        'is_enabled', 'is_cancelled', 'source_type', 'jwt_token_id',
        'last_login_ip', 'last_login_at',
    ];

    /**
     * 序列化时隐藏的字段 | Hidden Fields for Serialization
     * @var array
     */
    protected $hidden = ['password'];

    /**
     * 字段类型转换 | Attribute Casting
     * @var array
     */
    protected $casts = [
        'last_login_at' => 'string',
        'is_enabled' => 'integer',
        'is_cancelled' => 'integer',
        'role_id' => 'integer',
    ];

    /**
     * 为数组/JSON序列化准备日期。
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 关联角色 | Relation: Role
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function userInfo()
    {
        return $this->hasOne(UserInfo::class, 'login_id', 'id');
    }

    /**
     * 关联登录日志 | Relation: Login Logs
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function loginLogs()
    {
        return $this->hasMany(UserLoginLog::class, 'login_id');
    }

    /**
     * 是否为代理 | Is Agent
     * @return bool
     */
    public function isAgent()
    {
        return $this->account_type === 1;
    }

    /**
     * 是否为普通客户 | Is Customer
     * @return bool
     */
    public function isCustomer()
    {
        return $this->account_type === 2;
    }

    /**
     * 是否启用且未注销 | Is Active
     * @return bool
     */
    public function isActive()
    {
        return $this->is_enabled === 1 && $this->is_cancelled === 0;
    }
}
