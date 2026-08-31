<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:28
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 礼品发货模型。
 *
 * 文件功能：
 * - gift_shipments 表保存礼品兑换后的发货和物流记录，用于后台礼品发货列表和前台礼品记录展示。
 * - user_id 表示领取礼品的业务用户 ID，对应 user_infos.user_id。
 * - address_id 表示使用的收货地址 ID，对应 user_addresses.id。
 * - recipient_name、recipient_phone、recipient_address 表示发货时快照的收件人姓名、电话和地址。
 * - sender_name 表示发货人或后台处理人名称，tracking_number 表示物流单号。
 * - gift_name 表示礼品名称，gift_quantity 表示礼品数量。
 * - status 表示发货处理状态，remark 表示后台发货备注。
 * - admin_id 表示处理发货的后台管理员 ID，shipped_at 表示发货时间。
 */
class GiftShipment extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 gift_shipments。
     */
    protected $table = 'gift_shipments';

    /**
     * 关联礼品发货所属业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 gift_shipments.user_id，表示该礼品发货记录属于哪个业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回发货记录所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
