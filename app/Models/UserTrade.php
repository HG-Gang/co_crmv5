<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 用户交易模型 | User Trade Model
 * 
 * 记录用户的交易订单信息。
 * Records user's trading order information.
 */
class UserTrade extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_trades';

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
