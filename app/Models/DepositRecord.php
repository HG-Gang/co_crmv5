<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 充值记录模型 | Deposit Record Model
 * 
 * 维护用户的充值交易历史。
 * Maintains user deposit transaction history.
 */
class DepositRecord extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'deposit_records';

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
