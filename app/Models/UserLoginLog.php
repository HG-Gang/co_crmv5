<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 用户登录日志模型 | User Login Log Model
 * 
 * 记录用户的登录活动。
 * Records login activities of users.
 */
class UserLoginLog extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_login_logs';

    /**
     * 可批量赋值的属性 | The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = ['login_id', 'user_id', 'login_ip', 'ip_location', 'user_agent'];

    /**
     * 关联登录认证信息 | Relation: User Login
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function userLogin()
    {
        return $this->belongsTo(UserLogin::class, 'login_id');
    }
}
