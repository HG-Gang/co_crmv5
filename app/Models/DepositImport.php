<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 充值导入模型 | Deposit Import Model
 * 
 * 处理充值记录的批量导入及同步状态。
 * Handles batch import of deposit records and synchronization status.
 */
class DepositImport extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'deposit_imports';

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
