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
 * 数据操作日志模型。
 *
 * 文件功能：
 * - data_operation_logs 表保存模型数据变更前后的审计快照，用于追踪后台敏感数据修改过程。
 * - model_type 表示被修改的数据模型类型，例如 UserInfo、DepositRecord 或 WithdrawRecord。
 * - model_id 表示被修改数据的主键 ID。
 * - before_data 表示变更前数据快照，after_data 表示变更后数据快照，均按数组结构读取。
 * - operator_id 表示执行变更的后台管理员 ID，对应 admins.id。
 */
class DataOperationLog extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 data_operation_logs。
     */
    protected $table = 'data_operation_logs';

    /**
     * 字段类型转换配置。
     *
     * @var array<string, string> $casts 表示 JSON 快照字段读取时自动转换为数组。
     */
    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
    ];

    /**
     * 关联执行数据变更的后台管理员。
     *
     * 参数逻辑说明：
     * - 外键 operator_id 来自 data_operation_logs.operator_id，表示谁执行了本次数据变更。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回执行变更的 Admin 管理员关系。
     */
    public function operator()
    {
        return $this->belongsTo(Admin::class, 'operator_id');
    }
}
