<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:07
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 批量入金导入模型。
 *
 * 文件功能：
 * - deposit_imports 表保存后台批量入金导入记录，用于 Excel/CSV 导入后生成或同步用户入金数据。
 * - user_id 表示导入记录所属业务用户 ID，对应 user_infos.user_id，用于校验导入用户是否存在。
 * - user_name 表示导入文件中的用户展示名称，用于后台人工核对。
 * - amount 表示本条导入记录的入金金额，remarks 表示导入备注或人工补充说明。
 * - mt4_order_id 表示导入后关联的 MT4 订单号或外部资金系统订单号。
 * - batch_no 表示批次号，用于定位同一次 Excel/CSV 或手工批量导入的数据集合。
 * - is_synced 表示后续资金系统同步状态：0=待处理，1=成功，2=失败。
 * - fail_reason 表示同步失败原因，便于后台重试导入或人工修复数据。
 * - created_by 和 updated_by 表示创建、更新该导入记录的后台管理员 ID，用于审计追踪。
 */
class DepositImport extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 deposit_imports。
     */
    protected $table = 'deposit_imports';

    /**
     * 字段类型转换。
     *
     * 逻辑说明：
     * - 数值字段统一转为整数，amount 保留两位小数，保证重试接口的 JSON payload 类型稳定，各前端栈读取一致。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'decimal:2',
        'mt4_order_id' => 'integer',
        'is_synced' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * 关联批量入金导入记录所属业务用户资料。
     *
     * 参数逻辑说明：
     * - 外键 user_id 来自 deposit_imports.user_id，表示导入记录归属的业务用户。
     * - 目标键 user_id 来自 user_infos.user_id，保持与旧项目用户编号一致。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回导入记录所属的 UserInfo 用户资料关系。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
