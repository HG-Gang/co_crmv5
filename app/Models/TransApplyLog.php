<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 转组申请日志模型 | Transfer Application Log Model
 * 
 * 记录用户申请更换交易组的历史记录。
 * Records the history of user applications to change trading groups.
 */
class TransApplyLog extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'trans_apply_logs';

    /**
     * 关联用户 | Relation: User
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
