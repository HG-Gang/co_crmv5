<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 出金导入模型 | Withdraw Import Model
 * 
 * 处理出金记录的批量导入及同步状态。
 * Handles batch import of withdrawal records and synchronization status.
 */
class WithdrawImport extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'withdraw_imports';

    /**
     * 关联用户 | Relation: User
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
