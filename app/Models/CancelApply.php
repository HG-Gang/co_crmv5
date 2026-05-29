<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 注销申请模型 | Cancel Apply Model
 * 
 * 处理用户提交的账号注销申请。
 * Handles account cancellation applications submitted by users.
 */
class CancelApply extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'cancel_applies';

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
