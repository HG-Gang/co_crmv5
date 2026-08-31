<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 17:15
 */

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAgentBinding;
use App\Models\RoleDataScope;
use App\Support\FrontLegacyData;

/**
 * 后台数据范围服务。
 *
 * 文件功能：
 * - 统一处理不同管理员、不同代理、普通用户之间的数据可见范围。
 * - 控制器只负责传入业务查询和业务ID，不在各控制器里重复拼代理树 SQL。
 * - 数据范围完全来自 role_data_scopes 与 admin_agent_bindings 数据表配置。
 *
 * 数据范围类型（role_data_scopes.scope_type）：
 * - all=全部数据；custom_users=自定义业务用户白名单；custom_agents=自定义代理及其后代；
 *   agent_tree=管理员绑定代理及其后代；created=仅本人创建的数据；其它未枚举值失败关闭。
 *
 * 安全边界：
 * - 无角色范围配置、起始代理为空、代理树 root 无效或解析返回 null 时失败关闭（whereRaw('1 = 0')）；
 *   合法空代理树仍允许保留起始 root，不允许未配置的管理员看到任何业务数据。
 * - 带 created_by 的单条业务记录必须走 canAccessRecord()；纯用户/代理对象走 canAccessUser()。
 */
class AdminDataScopeService
{
    /**
     * 给业务查询追加当前管理员可见的数据范围条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query 业务查询对象，用于追加 where / whereIn 条件。
     * @param Admin $admin 当前登录后台管理员，用于读取角色和代理绑定配置。
     * @param string $targetType 数据对象类型：user=统一业务用户，agent=仅代理，deposit=入金，withdraw=出金，trade=交易。
     * @param string $userIdColumn 查询表中的用户ID字段名，例如 user_id。
     * @param string|null $agentIdColumn 查询表中的代理ID字段名；无代理字段时传 null。
     * @param string $createdOwnerColumn created 范围使用的记录 owner 列，默认保持旧调用的 created_by。
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder 已追加数据范围条件的查询对象。
     */
    public function apply(
        $query,
        Admin $admin,
        $targetType,
        $userIdColumn = 'user_id',
        $agentIdColumn = null,
        $createdOwnerColumn = 'created_by'
    )
    {
        // 超级管理员不受数据范围限制，直接放行原始查询。
        if ($this->isSuperAdmin($admin)) {
            return $query;
        }

        // 角色没有启用的数据范围配置时失败关闭：追加恒假条件，确保不泄露任何业务数据。
        $scope = $this->getRoleScope($admin);
        if (!$scope) {
            return $query->whereRaw('1 = 0');
        }

        if ($scope->scope_type === 'all') {
            return $query;
        }

        // 自定义用户集合直接按 user_id 白名单过滤，不展开任何代理树。
        if ($scope->scope_type === 'custom_users') {
            return $query->whereIn($userIdColumn, $this->normalizeIds($scope->user_ids));
        }

        // 自定义代理集合先展开代理树后代，再按可见 ID 过滤。
        if ($scope->scope_type === 'custom_agents') {
            return $this->applyAgentTreeScope($query, $this->normalizeIds($scope->agent_ids), $targetType, $userIdColumn, $agentIdColumn);
        }

        // 管理员绑定代理：先读绑定表，再展开代理树后代。
        if ($scope->scope_type === 'agent_tree') {
            return $this->applyAgentTreeScope($query, $this->getBoundAgentIds($admin), $targetType, $userIdColumn, $agentIdColumn);
        }

        // created=仅本人创建的数据；业务表可显式传入自己的 owner 列。
        if ($scope->scope_type === 'created') {
            return $query->where($createdOwnerColumn, (string) $admin->id);
        }

        // 未知范围类型失败关闭；业务表不一定存在 admin_id，不能拼接不存在的列。
        return $query->whereRaw('1 = 0');
    }

    /**
     * 根据角色读取启用的数据范围配置。
     *
     * @param Admin $admin 当前登录后台管理员。
     * @return RoleDataScope|null 当前角色的数据范围配置；不存在时返回 null。
     */
    public function getRoleScope(Admin $admin)
    {
        if (!$admin->role_id) {
            return null;
        }

        return RoleDataScope::where('role_id', (int) $admin->role_id)
            ->where('status', 1)
            ->first();
    }

    /**
     * 读取管理员绑定的代理ID集合。
     *
     * @param Admin $admin 当前登录后台管理员。
     * @return array 代理业务用户ID数组。
     */
    public function getBoundAgentIds(Admin $admin)
    {
        return AdminAgentBinding::where('admin_id', $admin->id)
            ->where('status', 1)
            ->pluck('agent_id')
            ->map(function ($agentId) {
                return (int) $agentId;
            })
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 判断当前管理员是否允许访问某个单条业务用户或代理。
     *
     * 业务边界：
     * - 用于详情、更新、审核、入金处理、出金处理、返佣结算等单条记录动作。
     * - 列表接口继续使用 apply() 追加查询条件，单条接口用本方法避免通过 ID 越权访问。
     * - 权限来源仍然只读取 role_data_scopes 与 admin_agent_bindings 数据表配置。
     *
     * @param Admin $admin 当前登录后台管理员，用于读取角色与管理员绑定代理配置。
     * @param int|string $userId 业务用户ID或代理用户ID；注意不是后台管理员ID。
     * @param string $targetType 目标对象类型：agent 仅取代理；其它业务类型覆盖代理与普通客户。
     * @return bool true=允许访问该业务ID，false=拒绝访问。
     */
    public function canAccessUser(Admin $admin, $userId, $targetType = 'user')
    {
        $userId = (int) $userId;
        // 非法业务 ID 直接拒绝，避免零值或负数绕过范围判断。
        if ($userId <= 0) {
            return false;
        }

        if ($this->isSuperAdmin($admin)) {
            return true;
        }

        // 无范围配置时失败关闭：单条记录一律拒绝访问。
        $scope = $this->getRoleScope($admin);
        if (!$scope) {
            return false;
        }

        if ($scope->scope_type === 'all') {
            return true;
        }

        // 自定义用户集合直接做白名单包含判断。
        if ($scope->scope_type === 'custom_users') {
            return in_array($userId, $this->normalizeIds($scope->user_ids), true);
        }

        // 代理类范围先展开可见 ID 集合，再判断目标是否在其中。
        if ($scope->scope_type === 'custom_agents') {
            $visibleIds = $this->resolveAgentTreeUserIds($this->normalizeIds($scope->agent_ids), $targetType);

            return in_array($userId, $visibleIds, true);
        }

        if ($scope->scope_type === 'agent_tree') {
            $visibleIds = $this->resolveAgentTreeUserIds($this->getBoundAgentIds($admin), $targetType);

            return in_array($userId, $visibleIds, true);
        }

        // 未匹配任何已知范围类型时拒绝，未知配置不得放行。
        return false;
    }

    /**
     * 判断管理员是否允许访问一条带创建人归属的业务记录。
     *
     * created 范围必须比较记录自身的 created_by；仅凭 user_id 无法区分同一用户下由不同
     * 管理员创建的记录。其它范围继续复用 canAccessUser()，保持列表与单条入口的规则一致。
     *
     * @param Admin $admin 当前登录后台管理员。
     * @param int|string $userId 记录所属业务用户 ID。
     * @param int|string|null $createdBy 记录的 created_by 审计字段。
     * @param string $targetType 目标对象类型。
     * @return bool true=允许访问，false=拒绝访问。
     */
    public function canAccessRecord(Admin $admin, $userId, $createdBy, $targetType = 'user')
    {
        if ($this->isSuperAdmin($admin)) {
            return true;
        }

        $scope = $this->getRoleScope($admin);
        if (!$scope) {
            return false;
        }

        if ($scope->scope_type === 'created') {
            return (string) $createdBy === (string) $admin->id;
        }

        return $this->canAccessUser($admin, $userId, $targetType);
    }

    /**
     * 将代理树范围追加到业务查询对象。
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query 业务查询对象，用于追加 whereIn 或 whereRaw 条件。
     * @param array $agentIds 起始代理业务用户ID集合，通常来自管理员绑定代理或角色自定义代理配置。
     * @param string $targetType 数据对象类型：agent=仅代理后代，deposit/withdraw/trade/user=代理与客户统一后代。
     * @param string $userIdColumn 当前业务表中的用户ID字段名，例如 user_id。
     * @param string|null $agentIdColumn 当前业务表中的代理ID字段名；无独立代理字段时传 null。
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder 已追加数据范围条件的查询对象。
     */
    private function applyAgentTreeScope($query, array $agentIds, $targetType, $userIdColumn, $agentIdColumn)
    {
        // 无起始代理时失败关闭，避免 whereIn 空数组产生无法预期的查询结果。
        if (empty($agentIds)) {
            return $query->whereRaw('1 = 0');
        }

        $visibleIds = $this->resolveAgentTreeUserIds($agentIds, $targetType);
        // 代理树 root 无效/解析返回 null，或最终可见集合为空时失败关闭；合法空代理树由 root ID 组成。
        if (empty($visibleIds)) {
            return $query->whereRaw('1 = 0');
        }

        // 业务表存在独立代理ID列且目标类型含代理语义时按该列过滤，否则统一按用户ID列过滤。
        if ($agentIdColumn && in_array($targetType, ['agent', 'deposit', 'withdraw', 'trade'], true)) {
            return $query->whereIn($agentIdColumn, $visibleIds);
        }

        return $query->whereIn($userIdColumn, $visibleIds);
    }

    /**
     * 根据代理树解析可见业务用户ID集合。
     *
     * @param array $agentIds 起始代理业务用户ID集合。
     * @param string $targetType 数据对象类型：agent 时只取代理后代，其它类型取代理与客户全部后代。
     * @return array 可见业务用户ID数组，包含起始代理ID和下级后代ID。
     */
    private function resolveAgentTreeUserIds(array $agentIds, $targetType)
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds), function ($agentId) {
            return $agentId > 0;
        })));
        // 旧项目 data_list.family_tree 同时覆盖代理与客户；只有明确查询代理列表时才限定 type=1。
        $descendantType = $targetType === 'agent' ? 1 : null;
        $descendantIds = [];

        // 每个起始代理分别展开后代并合并去重，最终集合包含起始代理自身。
        foreach ($agentIds as $agentId) {
            $resolvedIds = FrontLegacyData::agentScopeIdsOrNull($agentId, $descendantType);
            if ($resolvedIds === null) {
                return [];
            }

            $descendantIds = array_merge(
                $descendantIds,
                $resolvedIds
            );
        }

        return array_values(array_unique(array_merge($agentIds, $descendantIds)));
    }

    /**
     * 判断管理员是否为超级管理员。
     *
     * @param Admin $admin 当前登录后台管理员。
     * @return bool true=超级管理员，不受数据范围限制；false=普通管理员，必须读取表配置。
     */
    private function isSuperAdmin(Admin $admin)
    {
        return (int) $admin->id === 1 || ($admin->role && $admin->role->name === 'super_admin');
    }

    /**
     * 规范化 JSON 配置中的 ID 数组。
     *
     * @param mixed $ids 数据库 JSON 字段读取出的数组或空值。
     * @return array 去重后的整数 ID 数组。
     */
    private function normalizeIds($ids)
    {
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
