<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 用户收货地址模型 | User Address Model
 * 
 * 管理用户的收货地址信息。
 * Manages user's shipping address information.
 */
class UserAddress extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_addresses';

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
