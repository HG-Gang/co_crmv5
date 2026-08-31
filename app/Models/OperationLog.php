<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:49
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * 操作日志模型。
 *
 * 文件功能：
 * - operation_logs 表保存后台管理员业务操作审计记录，用于追踪用户管理、资金审核、订单处理等敏感操作。
 * - admin_id 表示执行操作的后台管理员 ID，对应 admins.id。
 * - admin_name 表示操作时记录的管理员名称快照，避免管理员改名后历史日志不可读。
 * - target_user_id 表示被操作的业务用户 ID，对应 user_infos.user_id，可为空。
 * - order_no 表示关联的业务订单号，例如入金、出金或交易订单编号。
 * - content 表示操作内容说明，ip 表示操作来源 IP。
 * - action_type 表示操作类型，用于后台审计页面筛选创建、更新、删除、审核等行为。
 */
class OperationLog extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 operation_logs。
     */
    protected $table = 'operation_logs';

    /**
     * 关联执行操作的后台管理员。
     *
     * 参数逻辑说明：
     * - 外键 admin_id 来自 operation_logs.admin_id，表示本条日志由哪个后台管理员产生。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回日志所属 Admin 管理员关系。
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 关联被操作的业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 target_user_id 来自 operation_logs.target_user_id，表示被本次操作影响的业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回被操作的 UserInfo 用户资料关系。
     */
    public function targetUser()
    {
        return $this->belongsTo(UserInfo::class, 'target_user_id', 'user_id');
    }
}
