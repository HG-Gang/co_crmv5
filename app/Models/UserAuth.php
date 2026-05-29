<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 用户实名认证模型 | User Auth Model
 * 
 * 管理用户的实名认证及银行卡认证信息。
 * Manages user's real-name and bank card authentication information.
 */
class UserAuth extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_auths';

    /**
     * 可批量赋值的属性 | The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'user_id', 'real_name', 'id_card', 'id_card_no', 'id_card_front', 'id_card_back',
        'id_card_hand', 'id_card_status', 'status', 'audit_time', 'audit_remark',
        'bank_no', 'bank_no_tmp', 'bank_name', 'bank_name_tmp', 'bank_addr', 'bank_addr_tmp',
        'bank_branch', 'bank_account', 'bank_user', 'bank_card_img', 'bank_card_img_tmp',
        'bank_status', 'bank_remarks', 'id_card_remarks'
    ];

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function userInfo()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
