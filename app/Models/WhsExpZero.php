<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 余额信用清零模型 | Balance and Credit Reset Model
 * 
 * 记录余额或信用额度清零的操作及结果。
 * Records the operation and results of balancing or credit limit resetting.
 */
class WhsExpZero extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'whs_exp_zeros';

    /**
     * 关联用户 | Relation: User
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
