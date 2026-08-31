<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/22
 * Time: 02:05
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 余额信用清零模型。
 *
 * 文件功能：
 * - whs_exp_zeros 表保存用户余额或信用额度清零操作记录，用于后台资金清零、风控处理和审计追踪。
 * - user_id 表示被清零的业务用户 ID，对应 user_infos.user_id。
 * - user_name 表示清零时记录的用户名称快照，便于后台列表展示。
 * - balance 表示清零前或清零目标余额，credit 表示清零前或清零目标信用额度。
 * - status 表示清零处理状态：0=处理中、1=待处理、2=已完成、3=失败。
 * - md5_key 表示清零请求校验签名的历史字段名；当前实现只在创建记录时生成审计快照指纹，不再参与请求鉴权或签名校验。
 * - created_by 和 updated_by 表示创建、更新该清零记录的后台管理员 ID，用于审计追踪。
 */
class WhsExpZero extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 whs_exp_zeros。
     */
    protected $table = 'whs_exp_zeros';

    /**
     * 关联清零记录所属业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 whs_exp_zeros.user_id，表示本次清零操作作用于哪个业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回清零记录所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
