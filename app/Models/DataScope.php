<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 数据权限范围配置模型。
 *
 * 文件功能：
 * - 该模型用于存储角色的数据权限配置，决定不同角色在查询数据时能看到哪些数据范围。
 * - 通过 role_id + resource_name 唯一确定一条配置，每个角色对每个资源只能有一条数据权限配置。
 * - scope_type 定义数据权限类型：1=全部 2=本级及下级 3=仅本级 4=仅本人 5=自定义。
 * - scope_rule 存储自定义规则的JSON配置，当 scope_type=5 时启用。
 *
 * 表结构：data_scopes
 * - id: 主键ID
 * - role_id: 角色ID，关联 roles.id
 * - resource_name: 资源名称，例如 user、agent、order 等
 * - scope_type: 数据权限类型
 * - scope_rule: 自定义规则JSON
 * - created_at: 创建时间
 * - updated_at: 更新时间
 *
 * 使用示例：
 * ```php
 * // 配置角色2对用户资源的数据权限为"本级及下级"
 * DataScope::updateOrCreate(
 *     ['role_id' => 2, 'resource_name' => 'user'],
 *     ['scope_type' => 2]
 * );
 *
 * // 查询角色的数据权限配置
 * $dataScope = DataScope::where('role_id', 2)
 *     ->where('resource_name', 'user')
 *     ->first();
 * ```
 */
class DataScope extends Model
{
    use SoftDeletes;

    /**
     * 关联的数据表名称。
     *
     * @var string
     */
    protected $table = 'data_scopes';

    /**
     * 可批量赋值的属性。
     *
     * 字段含义：
     * - role_id：角色ID，关联 roles.id。
     * - resource_name：资源名称，例如 user、agent、order、commission 等业务模块。
     * - scope_type：数据权限类型，1=全部 2=本级及下级 3=仅本级 4=仅本人 5=自定义。
     * - scope_rule：自定义规则JSON，格式示例：[{"field":"department_id","operator":"in","value":[1,2,3]}]。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'resource_name',
        'scope_type',
        'scope_rule',
    ];

    /**
     * 属性类型转换。
     *
     * 字段类型说明：
     * - role_id：整型，角色ID。
     * - scope_type：整型，数据权限类型。
     * - scope_rule：数组，自动将JSON字符串转为数组，方便代码中使用。
     * - created_at：整型，10位时间戳。
     * - updated_at：整型，10位时间戳。
     * - deleted_at：整型，10位时间戳，软删除标记。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'role_id' => 'integer',
        'scope_type' => 'integer',
        'scope_rule' => 'array',
        'created_at' => 'integer',
        'updated_at' => 'integer',
        'deleted_at' => 'integer',
    ];

    /**
     * 时间戳自动维护开关。
     *
     * 逻辑说明：
     * - 保持 Laravel 默认 true，由模型自动写入和更新 created_at、updated_at。
     * - 时间戳字段按下方 casts 以 10 位整型时间戳读写，与项目其他表的时间口径保持一致。
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * 定义与 Role 模型的反向关联关系。
     *
     * 关系说明：
     * - 一条数据权限配置属于一个角色。
     * - 外键：data_scopes.role_id。
     * - 主键：roles.id。
     *
     * 使用示例：
     * ```php
     * $dataScope = DataScope::find(1);
     * $role = $dataScope->role; // 获取该数据权限所属的角色
     * ```
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /**
     * 获取数据权限类型的中文名称。
     *
     * 返回值说明：
     * - 1：全部数据
     * - 2：本级及下级
     * - 3：仅本级
     * - 4：仅本人
     * - 5：自定义
     *
     * 使用示例：
     * ```php
     * $dataScope = DataScope::find(1);
     * echo $dataScope->getScopeTypeName(); // 输出：本级及下级
     * ```
     *
     * @return string 数据权限类型的中文名称。
     */
    public function getScopeTypeName(): string
    {
        $types = [
            1 => '全部数据',
            2 => '本级及下级',
            3 => '仅本级',
            4 => '仅本人',
            5 => '自定义',
        ];

        return $types[$this->scope_type] ?? '未知';
    }

    /**
     * 获取数据权限类型的英文标识。
     *
     * 返回值说明：
     * - 1：all
     * - 2：self_and_descendants
     * - 3：self_level
     * - 4：self_only
     * - 5：custom
     *
     * 使用示例：
     * ```php
     * $dataScope = DataScope::find(1);
     * echo $dataScope->getScopeTypeSlug(); // 输出：self_and_descendants
     * ```
     *
     * @return string 数据权限类型的英文标识。
     */
    public function getScopeTypeSlug(): string
    {
        $slugs = [
            1 => 'all',
            2 => 'self_and_descendants',
            3 => 'self_level',
            4 => 'self_only',
            5 => 'custom',
        ];

        return $slugs[$this->scope_type] ?? 'unknown';
    }

    /**
     * 验证自定义规则格式是否正确。
     *
     * 逻辑说明：
     * - 自定义规则必须是数组格式。
     * - 每个规则必须包含 field、operator、value 三个字段。
     * - operator 必须是支持的操作符之一。
     *
     * 使用示例：
     * ```php
     * $dataScope = DataScope::find(1);
     * if ($dataScope->isValidCustomRule()) {
     *     echo '自定义规则格式正确';
     * }
     * ```
     *
     * @return bool true=格式正确，false=格式错误。
     */
    public function isValidCustomRule(): bool
    {
        if ($this->scope_type !== 5) {
            return true; // 非自定义规则不需要验证
        }

        if (!is_array($this->scope_rule) || empty($this->scope_rule)) {
            return false;
        }

        $validOperators = ['=', '>', '<', '>=', '<=', 'in', 'like'];

        foreach ($this->scope_rule as $rule) {
            if (!is_array($rule)) {
                return false;
            }

            if (!isset($rule['field']) || !isset($rule['operator']) || !isset($rule['value'])) {
                return false;
            }

            if (!in_array(strtolower($rule['operator']), $validOperators)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 保存前自动验证数据权限配置。
     *
     * 逻辑说明：
     * - scope_type=5 时必须提供有效的 scope_rule。
     * - 自动验证自定义规则格式是否正确。
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // 自定义规则时必须提供 scope_rule
            if ($model->scope_type === 5 && empty($model->scope_rule)) {
                throw new \InvalidArgumentException('自定义规则不能为空');
            }

            // 验证自定义规则格式
            if ($model->scope_type === 5 && !$model->isValidCustomRule()) {
                throw new \InvalidArgumentException('自定义规则格式错误');
            }
        });
    }
}
