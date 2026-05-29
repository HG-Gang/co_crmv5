<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 基础模型类 | Base Model Class
 * 
 * 所有业务模型继承此类，统一配置：
 * - $guarded = [] 允许批量赋值所有字段
 * - $hidden = ['deleted_at'] 隐藏软删除字段
 * - SoftDeletes 软删除
 * - 时间戳使用10位Unix时间戳（int类型）
 * 
 * All business models extend this class with unified configuration:
 * - $guarded = [] allows mass assignment of all fields
 * - $hidden = ['deleted_at'] hides soft delete field
 * - SoftDeletes trait for soft deletion
 * - Timestamps stored as 10-digit Unix timestamps (integer)
 */
class BaseModel extends Model
{
    use SoftDeletes;

    /**
     * 不使用 $fillable 白名单，而是通过 $guarded 黑名单控制
     * Use $guarded blacklist instead of $fillable whitelist
     * 
     * @var array
     */
    protected $guarded = [];

    /**
     * 默认隐藏的字段
     * Fields hidden by default in serialization
     * 
     * @var array
     */
    protected $hidden = ['deleted_at'];

    /**
     * 主键字段名
     * Primary key column name
     * 
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 时间戳存储格式：10位Unix时间戳(int)
     * Timestamp storage format: 10-digit Unix timestamp (integer)
     * 
     * 所有迁移文件使用 unsignedInteger 存储 created_at / updated_at / deleted_at
     * All migration files use unsignedInteger for created_at / updated_at / deleted_at
     * 
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * 序列化日期为ISO格式（JSON输出时）
     * Serialize dates to ISO format for JSON output
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
