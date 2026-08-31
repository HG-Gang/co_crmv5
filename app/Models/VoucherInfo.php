<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:54
 */

namespace App\Models;

/**
 * 凭证信息模型。
 *
 * 文件功能：
 * - voucher_infos 表保存前台用户上传的入金或审核凭证，是后台凭证审核和前台凭证上传链路的基础模型。
 * - user_id 表示上传凭证的前台业务用户 ID，对应 user_infos.user_id。
 * - images 表示凭证图片路径或 JSON 图片列表，控制器或展示层负责按业务场景解析。
 * - remarks 表示用户或后台填写的凭证备注。
 * - review_status 表示凭证审核状态，review_message 表示审核说明或拒绝原因。
 * - created_by 和 updated_by 表示创建、更新该凭证记录的后台管理员 ID，用于审计追踪。
 */
class VoucherInfo extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 voucher_infos。
     */
    protected $table = 'voucher_infos';

    /**
     * user() 关联上传凭证所属前台业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 voucher_infos.user_id，表示上传凭证的前台业务用户 ID。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回上传凭证所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
