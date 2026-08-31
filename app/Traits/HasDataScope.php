<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:57
 */

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Support\FrontLegacyData;

/**
 * 数据权限过滤Trait。
 *
 * 文件功能：
 * - 该Trait用于在查询时自动过滤数据范围，确保不同角色只能看到自己权限范围内的数据。
 * - 数据权限分为：全部数据、本级及下级、仅本级、仅本人、自定义规则五种类型。
 * - scope_type 语义：1=全部数据；2=本级及下级（含全部间接下级）；3=仅本级（仅直属下级）；
 *   4=仅本人（created_by 为自己）；5=自定义规则（按 data_scopes.scope_rule 的 JSON 条件过滤）。
 * - 适用场景：用户管理、代理管理、订单管理、佣金记录等需要按角色过滤数据的业务。
 * - 使用方式：在Model中 use HasDataScope，然后在查询时调用 ->withDataScope('resource_name')。
 *
 * 使用示例：
 * ```php
 * // 在Model中
 * class UserInfo extends Model
 * {
 *     use HasDataScope;
 * }
 *
 * // 在Controller中
 * $users = UserInfo::query()
 *     ->withDataScope('user', 'admin')
 *     ->paginate(15);
 * ```
 */
trait HasDataScope
{
    /**
     * 应用数据权限过滤的查询作用域。
     *
     * 参数含义：
     * - $query：Eloquent查询构建器对象，用于添加where条件。
     * - $resource：资源名称，例如 user、agent、order、commission 等，对应业务模块。
     * - $guard：守卫类型，admin=后台管理员，front=前台用户。
     *
     * 逻辑边界：
     * - 未登录用户直接返回空结果集（whereRaw('1 = 0')），避免数据泄露。
     * - 超级管理员（role_id=1）查看全部数据，不应用任何过滤条件。
     * - 未配置数据权限的角色，默认只能看自己创建的数据（created_by = user_id）。
     * - 代理场景下，"本级及下级"沿 user_infos.parent_id 实时计算所有下级ID。
     *
     * @param Builder $query Eloquent查询构建器对象。
     * @param string $resource 资源名称，用于匹配数据权限配置。
     * @param string $guard 守卫类型，默认 admin。
     * @return Builder 添加数据权限过滤条件后的查询构建器。
     */
    public function scopeWithDataScope(Builder $query, string $resource = 'user', string $guard = 'admin')
    {
        // 取当前守卫下的登录用户；未登录一律失败关闭（whereRaw('1 = 0')），
        // 保证匿名请求永远查不到任何数据，而不是放行后靠业务层兜底。
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // 超级管理员（id=1 或 role=super_admin）不参与数据权限过滤，直接放行全部数据。
        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        // 按资源名查该角色在 data_scopes 表中的数据权限配置；取不到配置时不报错，走默认值。
        $dataScope = $user->role && $user->role->dataScopes
            ? $user->role->dataScopes()->where('resource_name', $resource)->first()
            : null;

        // 未配置数据权限时按最小权限处理（仅本人创建的记录），避免越权暴露。
        if (!$dataScope) {
            return $query->where('created_by', $user->id);
        }

        // 按 scope_type 应用对应过滤条件；未知类型同样收敛到最小权限（仅本人），不因配置异常放大可见范围。
        switch ($dataScope->scope_type) {
            case 1: // 全部数据权限
                return $query;

            case 2: // 本级及下级数据权限
                return $this->applySelfAndDescendantsScope($query, $user, $resource);

            case 3: // 仅本级数据权限
                return $this->applySelfLevelScope($query, $user, $resource);

            case 4: // 仅本人数据权限
                return $query->where('created_by', $user->id);

            case 5: // 自定义规则
                return $this->applyCustomScope($query, $dataScope->scope_rule);

            default:
                // 未知类型，默认只能看自己创建的
                return $query->where('created_by', $user->id);
        }
    }

    /**
     * 应用"本级及下级"数据权限。
     *
     * 参数含义：
     * - $query：查询构建器对象。
     * - $user：当前登录用户。
     * - $resource：资源名称。
     *
     * 逻辑说明：
     * - 针对代理场景：可以查看自己及所有下级代理的数据。
     * - 通过 user_infos.parent_id 查询所有下级代理ID（包括直属和间接下级）。
     * - 针对普通用户场景：按 parent_id 或 created_by 过滤。
     *
     * @param Builder $query 查询构建器。
     * @param mixed $user 当前用户。
     * @param string $resource 资源名称。
     * @return Builder 添加过滤条件后的查询构建器。
     */
    private function applySelfAndDescendantsScope(Builder $query, $user, string $resource)
    {
        // 代理/客户类资源有 parent_id 层级，按实时拓扑查出全部下级（含间接），
        // 并始终包含自己，保证“本级及下级”覆盖自身数据。
        if (in_array($resource, ['agent', 'user', 'customer'])) {
            $descendantIds = $this->getDescendantIds($user->id);
            return $query->where(function ($q) use ($user, $descendantIds) {
                $q->where('parent_id', $user->id)
                  ->orWhereIn('parent_id', $descendantIds)
                  ->orWhere('id', $user->id); // 包含自己
            });
        }

        // 其余资源没有 parent_id 层级语义，退化为只包含当前用户创建的数据。
        return $query->where('created_by', $user->id);
    }

    /**
     * 应用"仅本级"数据权限。
     *
     * 参数含义：
     * - $query：查询构建器对象。
     * - $user：当前登录用户。
     * - $resource：资源名称。
     *
     * 逻辑说明：
     * - 只能查看直属下级的数据，不包括间接下级。
     * - 例如：A代理只能看到直属B代理的数据，看不到B的下级C代理的数据。
     *
     * @param Builder $query 查询构建器。
     * @param mixed $user 当前用户。
     * @param string $resource 资源名称。
     * @return Builder 添加过滤条件后的查询构建器。
     */
    private function applySelfLevelScope(Builder $query, $user, string $resource)
    {
        // 仅包含直属下级：parent_id 直接等于当前用户 ID，不经过 agent_descendants，
        // 从而排除间接下级。
        return $query->where('parent_id', $user->id);
    }

    /**
     * 应用自定义数据权限规则。
     *
     * 参数含义：
     * - $query：查询构建器对象。
     * - $rules：自定义规则，JSON格式存储在 data_scopes.scope_rule 字段。
     *
     * 规则格式示例：
     * ```json
     * [
     *   {"field": "department_id", "operator": "in", "value": [1, 2, 3]},
     *   {"field": "status", "operator": "=", "value": 1}
     * ]
     * ```
     *
     * 逻辑说明：
     * - 支持多个条件组合，目前支持的操作符：=、in、>、<、>=、<=、like。
     * - 多个条件之间是AND关系。
     *
     * @param Builder $query 查询构建器。
     * @param mixed $rules 自定义规则，可以是JSON字符串或数组。
     * @return Builder 添加过滤条件后的查询构建器。
     */
    private function applyCustomScope(Builder $query, $rules)
    {
        // scope_rule 落库为 JSON 字符串，先解析成数组；解析后仍非数组视为配置损坏。
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }

        // 规则格式损坏时失败关闭，返回空结果集，避免把损坏规则当作“无限制”放行。
        if (!is_array($rules) || empty($rules)) {
            return $query->whereRaw('1 = 0');
        }

        // 逐条应用规则（多条件之间为 AND 关系）；缺字段的规则跳过，避免空值比较产生误放行。
        foreach ($rules as $rule) {
            if (!isset($rule['field']) || !isset($rule['operator']) || !isset($rule['value'])) {
                continue;
            }

            $field = $rule['field'];
            $operator = strtolower($rule['operator']);
            $value = $rule['value'];

            switch ($operator) {
                case 'in':
                    $query->whereIn($field, (array) $value);
                    break;
                case '=':
                case '>':
                case '<':
                case '>=':
                case '<=':
                    $query->where($field, $operator, $value);
                    break;
                case 'like':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;
                default:
                    // 未注册的操作符忽略，不拼接任何条件，保证查询可用性。
                    break;
            }
        }

        return $query;
    }

    /**
     * 获取当前用户的所有下级代理ID列表。
     *
     * 参数含义：
     * - $userId：当前用户ID。
     *
     * 逻辑说明：
     * - 从 user_infos.parent_id 拓扑查询所有下级代理ID，包括直属和间接下级。
     * - agent_descendants 是派生闭包表，不参与权限事实判断。
     *
     * @param int $userId 当前用户ID。
     * @return array 下级代理ID数组。
     */
    private function getDescendantIds(int $userId): array
    {
        return FrontLegacyData::userScopeIds($userId, false);
    }

    /**
     * 判断是否为超级管理员。
     *
     * 参数含义：
     * - $user：当前登录用户对象。
     *
     * 逻辑说明：
     * - admins.id=1 或 role.name='super_admin' 视为超级管理员。
     * - 超级管理员拥有全部数据权限，不应用任何过滤条件。
     *
     * @param mixed $user 当前用户。
     * @return bool true=超级管理员，false=普通用户。
     */
    private function isSuperAdmin($user): bool
    {
        return (int) $user->id === 1 || ($user->role && $user->role->name === 'super_admin');
    }
}
