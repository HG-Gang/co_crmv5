<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 管理员登录日志模型 | Admin Login Log Model
 * 
 * 记录管理员的登录活动。
 * Records admin login activities.
 */
class AdminLoginLog extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'admin_login_logs';

    /**
     * 可批量赋值的属性 | The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = ['admin_id', 'login_ip', 'ip_location', 'user_agent'];

    /**
     * 关联管理员 | Relation: Admin
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
