<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 出金记录模型 | Withdraw Record Model
 * 
 * 记录用户的出金交易详情及状态。
 * Records the withdrawal transaction details and status of users.
 */
class WithdrawRecord extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'withdraw_records';

    /**
     * 关联用户 | Relation: User
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
