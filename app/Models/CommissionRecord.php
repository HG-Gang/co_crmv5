<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:58
 */

namespace App\Models;

/**
 * 佣金记录模型。
 *
 * 文件功能：
 * - commission_records 表保存代理返佣结算和人工调整记录，是实时返佣、结算审核和后台返佣报表的数据来源。
 * - unique_id 表示返佣记录唯一业务编号，用于避免重复结算或重复导入。
 * - agent_id 表示获得返佣的代理业务用户 ID，对应 user_infos.user_id。
 * - parent_id 表示该代理的父级代理业务用户 ID，用于按代理链路汇总返佣。
 * - agent_profit 和 agent_volume 表示代理维度的盈利和交易手数统计。
 * - equity_value 和 equity_diff 表示权益值及权益差额，用于结算校验。
 * - settle_cycle 表示结算周期，date_range 表示本次统计覆盖的时间范围。
 * - mt4_order_id 表示关联的 MT4 订单号或资金系统订单号。
 * - settle_status 表示返佣结算状态，后台结算按钮和报表筛选应以该字段为准。
 * - fee、swap、deposit 表示手续费、库存费和入金相关金额。
 * - commission_amount 表示本次应返佣金额，returned_amount 表示已返还金额，real_amount 表示最终实际金额。
 * - data_type 表示记录来源类型，manual_reason 表示人工调整原因，remarks 表示补充备注。
 * - created_by 和 updated_by 表示创建、更新该返佣记录的后台管理员 ID，用于审计追踪。
 */
class CommissionRecord extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 commission_records。
     */
    protected $table = 'commission_records';

    /**
     * 关联获得返佣的代理业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 agent_id 来自 commission_records.agent_id，表示获得返佣的代理。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目业务用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回返佣所属代理的 UserInfo 关系。
     */
    public function agent()
    {
        return $this->belongsTo(UserInfo::class, 'agent_id', 'user_id');
    }

    /**
     * 关联父级代理用户资料。
     *
     * 参数逻辑说明：
     * - 外键 parent_id 来自 commission_records.parent_id，表示返佣所属代理的父级代理。
     * - 目标键 user_id 来自 user_infos.user_id，用于后台按上级代理维度汇总返佣。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回父级代理的 UserInfo 关系。
     */
    public function parent()
    {
        return $this->belongsTo(UserInfo::class, 'parent_id', 'user_id');
    }
}
