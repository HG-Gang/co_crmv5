<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 礼品发货模型 | Gift Shipment Model
 * 
 * 管理礼品兑换的发货流程及物流信息。
 * Manages the shipping process and logistics information for gift redemptions.
 */
class GiftShipment extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'gift_shipments';

    /**
     * 关联用户信息 | Relation: User Info
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
