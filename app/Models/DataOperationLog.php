<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 数据操作日志模型 | Data Operation Log Model
 * 
 * 记录数据的变更细节（修改前后的数据）。
 * Records data change details (before and after data).
 */
class DataOperationLog extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'data_operation_logs';

    /**
     * 字段类型转换 | Attribute Casting
     * @var array
     */
    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
    ];

    /**
     * 关联操作员 | Relation: Operator (Admin)
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function operator()
    {
        return $this->belongsTo(Admin::class, 'operator_id');
    }
}
