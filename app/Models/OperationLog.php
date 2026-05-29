<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 操作日志模型 | Operation Log Model
 * 
 * 记录后台管理员的操作行为。
 * Records operation behaviors of back-office admins.
 */
class OperationLog extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'operation_logs';

    /**
     * 关联管理员 | Relation: Admin
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 关联目标用户 | Relation: Target User
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function targetUser()
    {
        return $this->belongsTo(UserInfo::class, 'target_user_id', 'user_id');
    }
}
