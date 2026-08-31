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
 * 批量信用导入模型。
 *
 * 文件功能：
 * - credit_imports 表保存后台批量信用额度导入记录。
 * - user_id 和 user_name 表示本次信用调整对应的业务用户。
 * - credit_type 表示信用调整类型，当前后台约定 1=临时信用、2=永久信用、3=奖励信用、4=其他信用。
 * - amount 表示信用调整金额，batch_no 表示批次号，用于把同一次导入的多条记录归组。
 * - is_synced 表示是否已同步到 MT4，fail_reason 表示同步失败原因，便于失败重试和后台排查。
 */
class CreditImport extends BaseModel
{
    use HasFactory;

    /**
     * 模型对应的数据表名称。
     *
     * 参数逻辑说明：
     * - $table 指向 credit_imports 表，保存批量信用导入明细和同步状态。
     *
     * @var string
     */
    protected $table = 'credit_imports';

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
        'credit_type' => 'integer',
        'amount' => 'decimal:2',
        'mt4_order_id' => 'integer',
        'is_synced' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * user() 返回信用导入记录所属业务用户。
     *
     * 关联逻辑说明：
     * - credit_imports.user_id 对应 user_infos.user_id。
     * - 后台列表展示用户姓名、代理归属或数据权限过滤时可通过该关系读取业务用户资料。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 信用导入记录所属业务用户。
     */
    public function user()
    {
        return $this->belongsTo(UserInfo::class, 'user_id', 'user_id');
    }
}
