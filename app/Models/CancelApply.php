<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:37
 */

namespace App\Models;

/**
 * 注销申请模型。
 *
 * 文件功能：
 * - cancel_applies 表保存前台用户提交的账号注销申请，用于后台审核是否允许注销业务账号。
 * - user_id 表示申请注销的业务用户 ID，对应 user_infos.user_id。
 * - user_name 表示提交申请时的用户名称快照，便于后台列表直接展示。
 * - status 表示注销申请处理状态，后台审核通过或拒绝时会更新该字段。
 * - cancel_remark 表示用户提交的注销原因，reject_reason 表示后台拒绝注销的原因。
 * - created_by 和 updated_by 表示创建、更新该申请的后台管理员 ID，用于审计追踪。
 */
class CancelApply extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 cancel_applies。
     */
    protected $table = 'cancel_applies';

    /**
     * 关联注销申请所属业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 cancel_applies.user_id，表示申请注销的业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回注销申请所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
