<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 凭证信息模型 | Voucher Info Model
 * 
 * 管理用户上传的各类交易或认证凭证。
 * Manages various trading or authentication vouchers uploaded by users.
 */
class VoucherInfo extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'voucher_infos';

    /**
     * 关联用户 | Relation: User
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
