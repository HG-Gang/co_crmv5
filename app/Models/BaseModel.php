<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 20:42
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 基础模型类。
 *
 * 文件功能：
 * - 所有继承该类的业务模型共享软删除、主键、批量赋值和时间格式约定。
 * - SoftDeletes 表示所有继承模型默认支持软删除，删除时写入 deleted_at 而不是物理删除记录。
 * - $guarded 表示批量赋值黑名单；当前为空数组，表示字段写入边界由控制器、服务层或单独模型的 $fillable 继续约束。
 * - $hidden 表示序列化时隐藏字段；默认隐藏 deleted_at，避免接口响应暴露软删除时间。
 * - $dateFormat 表示 Eloquent 日期字段保存为 Unix 时间戳，兼容当前迁移中 created_at、updated_at、deleted_at 的整数时间戳设计。
 */
class BaseModel extends Model
{
    use SoftDeletes;

    /**
     * 批量赋值黑名单。
     *
     * 参数逻辑说明：
     * - $guarded 表示禁止批量赋值的字段列表。
     * - 空数组表示基础模型不统一拦截字段，具体业务字段安全边界由各控制器验证规则、服务层写入白名单或子模型 $fillable 决定。
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * 序列化时隐藏字段。
     *
     * 参数逻辑说明：
     * - $hidden 表示模型转数组或 JSON 响应时不输出的字段列表。
     * - deleted_at 是软删除时间，隐藏后可避免普通列表接口暴露内部删除状态细节。
     *
     * @var array<int, string>
     */
    protected $hidden = ['deleted_at'];

    /**
     * 主键字段名称。
     *
     * 参数逻辑说明：
     * - $primaryKey 表示 Eloquent 查询、更新和关联时使用的主键字段。
     * - 当前统一使用 id，和项目内绝大多数迁移表结构保持一致。
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 日期字段保存格式。
     *
     * 参数逻辑说明：
     * - $dateFormat 表示 Eloquent 写入 created_at、updated_at、deleted_at 时使用的格式。
     * - U 表示 10 位 Unix 时间戳，兼容当前迁移中的 unsignedInteger 时间字段。
     *
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * serializeDate() 用于把日期序列化为后台接口统一展示格式。
     *
     * 参数逻辑说明：
     * - $date 表示需要输出到 JSON 或数组响应中的日期对象。
     * - 返回值使用 Y-m-d H:i:s，方便 Blade 页面、Layui 表格和 Naive 风格页面直接展示。
     *
     * @param \DateTimeInterface $date 需要输出到 JSON 或数组响应中的日期对象。
     * @return string 格式化后的日期时间字符串。
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
