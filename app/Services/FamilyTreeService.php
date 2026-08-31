<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 05:14
 */

namespace App\Services;

use App\Models\CommissionRecord;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Support\FrontLegacyData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 代理家族链服务。
 *
 * 文件功能：
 * - 读取 user_infos.family_tree 保存的代理链路，提供祖先、直属下级、全部下级和网络树查询。
 * - agent_descendants 表保存代理与所有下级用户的祖先后代关系，用于团队统计、返佣汇总和数据范围过滤。
 * - 提供 family_tree 与 agent_descendants 的重建能力，便于旧项目数据迁移或代理关系修复后重新生成链路。
 *
 * 输入输出：
 * - 输入为用户 user_id / agent_id，输出为资料数组、统计数组或闭包表写入。
 * - 本服务不负责管理员数据范围过滤，数据范围见 AdminDataScopeService。
 *
 * 一致性与失败关闭：
 * - 任何变更入口（resolveCustomerHierarchy / syncCustomerDescendantRelations / reassignParent）
 *   遇到缺失节点、非代理节点或代理链循环时立即抛异常，禁止提交半套关系。
 * - 闭包表重建先物理删除旧行再写入，避免唯一键（agent_id + descendant_id）冲突。
 */
class FamilyTreeService
{
    /**
     * 获取目标用户的完整上级代理链。
     *
     * 参数含义：
     * - $userId 表示目标业务用户 ID，用于读取 user_infos.parent_id。
     * - $userInfo 表示目标用户资料记录，缺失、孤儿父级、非代理父级或循环链路时失败关闭。
     * - $ancestorIds 表示沿 parent_id 回溯得到的代理祖先链，按根到直属顺序返回。
     *
     * @param int $userId 目标业务用户 ID。
     * @return array<int, array<string, mixed>> 按 parent_id 拓扑顺序返回的上级代理资料列表。
     */
    public function getAncestors(int $userId): array
    {
        $userInfo = UserInfo::where('user_id', $userId)->first();
        if (!$userInfo) {
            return [];
        }

        // parent_id 是当前拓扑的唯一事实源，不能用陈旧 family_tree 快照决定祖先链。
        $ancestorIds = [];
        $visited = [$userId => true];
        $parentId = (int) $userInfo->parent_id;
        while ($parentId > 0) {
            if (isset($visited[$parentId]) || count($ancestorIds) >= UserInfo::MAX_HIERARCHY_DEPTH) {
                return [];
            }
            $parent = UserInfo::where('user_id', $parentId)
                ->first(['user_id', 'parent_id', 'account_type']);
            if (!$parent || (int) $parent->account_type !== 1) {
                return [];
            }

            $visited[$parentId] = true;
            array_unshift($ancestorIds, $parentId);
            $parentId = (int) $parent->parent_id;
        }

        if (empty($ancestorIds)) {
            return [];
        }

        return UserInfo::whereIn('user_id', $ancestorIds)
            ->orderByRaw("FIELD(user_id, " . implode(',', $ancestorIds) . ")")
            ->get()
            ->toArray();
    }

    /**
     * 获取代理商的全部直属下级。
     *
     * @param int $agentId 代理商业务用户 ID，对应 user_infos.parent_id。
     * @return array<int, array<string, mixed>> 直属下级用户资料列表。
     */
    public function getDirectChildren(int $agentId): array
    {
        return UserInfo::where('parent_id', $agentId)
            ->get()
            ->toArray();
    }

    /**
     * 获取代理商的全部下级关系，包含直属和间接下级。
     *
     * @param int $agentId 代理商业务用户 ID，对应 agent_descendants.agent_id。
     * @return array<int, array<string, mixed>> 携带 descendant 资料的下级关系列表。
     */
    public function getAllDescendants(int $agentId): array
    {
        $relations = $this->buildDescendantRelationsFromParentTopology($agentId);
        if ($relations === []) {
            return [];
        }

        $descendantIds = array_column($relations, 'descendant_id');
        $descendants = UserInfo::whereIn('user_id', $descendantIds)
            ->get()
            ->keyBy('user_id');

        return array_values(array_filter(array_map(function (array $relation) use ($descendants): ?array {
            $descendant = $descendants->get($relation['descendant_id']);
            if (!$descendant) {
                return null;
            }

            return array_merge($relation, ['descendant' => $descendant->toArray()]);
        }, $relations)));
    }

    /**
     * 获取代理商下级代理和客户数量统计。
     *
     * 参数含义：
     * - $agentId 表示代理商业务用户 ID。
     * - $directAgents 表示直属代理数量，来自 agent_descendants.is_direct=1 且 descendant_type=1。
     * - $allAgents 表示全部代理数量，包含直属和间接代理。
     * - $directCustomers 表示直属普通客户数量，来自 descendant_type=2。
     * - $allCustomers 表示全部普通客户数量，包含直属和间接客户。
     *
     * @param int $agentId 代理商业务用户 ID。
     * @return array<string, int> 团队代理和客户数量统计。
     */
    public function getSubAgentStats(int $agentId): array
    {
        $directAgents = count(FrontLegacyData::userScopeIds($agentId, false, 1, true));
        $allAgents = count(FrontLegacyData::userScopeIds($agentId, false, 1));
        $directCustomers = count(FrontLegacyData::userScopeIds($agentId, false, 2, true));
        $allCustomers = count(FrontLegacyData::userScopeIds($agentId, false, 2));

        return [
            'direct_agents' => $directAgents,
            'indirect_agents' => $allAgents - $directAgents,
            'total_agents' => $allAgents,
            'direct_customers' => $directCustomers,
            'indirect_customers' => $allCustomers - $directCustomers,
            'total_customers' => $allCustomers,
        ];
    }

    /**
     * 获取代理商在指定日期范围内的团队交易、返佣和新增用户统计。
     *
     * 参数含义：
     * - $agentId 表示代理商业务用户 ID。
     * - $dateFrom 表示统计开始时间，为空时不限制开始时间。
     * - $dateTo 表示统计结束时间，为空时不限制结束时间。
     * - $descendantIds 表示当前代理可见的全部下级用户 ID，兼容闭包表与 parent_id 导入关系。
     * - $tradeQuery 表示下级用户交易统计查询。
     * - $commissionQuery 表示当前代理返佣统计查询。
     * - $userQuery 表示下级新增用户统计查询。
     *
     * @param int $agentId 代理商业务用户 ID。
     * @param string|null $dateFrom 统计开始时间。
     * @param string|null $dateTo 统计结束时间。
     * @return array<string, mixed> 团队交易量、盈亏、返佣、活跃用户和新增注册统计。
     */
    public function getAgentStats(int $agentId, string $dateFrom = null, string $dateTo = null): array
    {
        $descendantIds = FrontLegacyData::userScopeIds($agentId, false);

        $tradeQuery = UserTrade::whereIn('user_id', $descendantIds);
        $commissionQuery = CommissionRecord::where('agent_id', $agentId);
        $userQuery = UserInfo::whereIn('user_id', $descendantIds);

        if ($dateFrom) {
            $tradeQuery->where('created_at', '>=', $dateFrom);
            $commissionQuery->where('created_at', '>=', $dateFrom);
            $userQuery->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $tradeQuery->where('created_at', '<=', $dateTo);
            $commissionQuery->where('created_at', '<=', $dateTo);
            $userQuery->where('created_at', '<=', $dateTo);
        }

        $tradeStats = $tradeQuery->selectRaw('SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(DISTINCT user_id) as active_users')->first();
        // commission_records 新表字段是 commission_amount，旧 commission 字段不存在；统一按新字段汇总。
        $totalCommission = $commissionQuery->sum('commission_amount');
        $newUsers = $userQuery->count();

        return [
            'total_volume' => $tradeStats->total_volume ?? 0,
            'total_profit' => $tradeStats->total_profit ?? 0,
            'total_commission' => $totalCommission ?? 0,
            'active_users' => $tradeStats->active_users ?? 0,
            'new_registrations' => $newUsers,
        ];
    }

    /**
     * 获取包含子节点的完整代理网络树结构。
     *
     * 参数含义：
     * - $agentId 表示代理商业务用户 ID，作为树根节点。
     * - $root 表示树根代理资料。
     * - $allDescendants 表示 agent_descendants 表中的全部下级关系。
     * - $lookup 表示按 user_id 建立的节点索引，用于把子节点挂到父节点下。
     *
     * @param int $agentId 代理商业务用户 ID。
     * @return array<int, array<string, mixed>> 以当前代理为根的团队树。
     */
    public function getNetworkTree(int $agentId): array
    {
        $root = UserInfo::where('user_id', $agentId)->first();
        if (!$root) {
            return [];
        }

        $relations = $this->buildDescendantRelationsFromParentTopology($agentId);
        $descendantIds = array_column($relations, 'descendant_id');
        $descendants = UserInfo::whereIn('user_id', $descendantIds)
            ->get()
            ->keyBy('user_id');

        $tree = [];
        $lookup = [];

        // 根节点先入树并登记引用，后续后代节点通过引用挂接。
        $rootNode = [
            'user_id' => $root->user_id,
            'user_name' => $root->user_name,
            'account_type' => $root->account_type,
            'children' => [],
            'children_count' => 0,
        ];

        $lookup[$agentId] = &$rootNode;
        $tree[] = &$rootNode;

        // 第一遍：为根与所有后代建立 user_id → 节点引用索引。
        foreach ($relations as $relation) {
            $descendant = $descendants->get($relation['descendant_id']);
            if (!$descendant) {
                continue;
            }
            $node = [
                'user_id' => $descendant->user_id,
                'user_name' => $descendant->user_name,
                'account_type' => $descendant->account_type,
                'children' => [],
                'children_count' => 0,
            ];
            $lookup[$descendant->user_id] = $node;
        }

        // 第二遍：把每个后代挂到其直属上级节点下；上级不在树内（孤儿数据）时跳过。
        foreach ($relations as $relation) {
            $descendant = $descendants->get($relation['descendant_id']);
            if (!$descendant) {
                continue;
            }
            $parentId = $descendant->parent_id;
            if (isset($lookup[$parentId]) && isset($lookup[$descendant->user_id])) {
                $lookup[$parentId]['children'][] = &$lookup[$descendant->user_id];
                $lookup[$parentId]['children_count']++;
            }
        }

        return $tree;
    }

    /**
     * 解析普通客户变更上级后应使用的完整层级快照。
     *
     * 业务逻辑说明：
     * - 从目标直属上级开始沿 user_infos.parent_id 回溯到平台根节点，不信任可能过期的 family_tree。
     * - 链路中的每个节点都必须存在且 account_type=1，遇到普通客户、缺失节点或循环立即抛出异常并关闭变更。
     * - ancestor_ids 按“最上层代理到直属代理”排序，用于生成 family_tree 和 agent_descendants 深度。
     * - relationship_code 复用旧项目五段等级槽位规则：1 至 4 级各占一段，5 级及以上占最后一段，空槽为 0000。
     *
     * @param int $userId 当前被编辑的普通客户业务用户 ID，用于检测把客户挂到自身下方的循环。
     * @param int $parentId 目标直属上级代理业务用户 ID；0 表示平台根节点。
     * @return array{ancestor_ids: array<int, int>, family_tree: string, relationship_code: string} 可同时用于 MT4 和本地事务的目标层级快照。
     *
     * @throws \InvalidArgumentException 目标链路不存在、包含非代理节点、等级无效或形成循环时抛出。
     */
    public function resolveCustomerHierarchy(int $userId, int $parentId): array
    {
        if ($userId <= 0 || $parentId < 0) {
            throw new \InvalidArgumentException('客户或上级代理 ID 不合法。');
        }

        // 平台直属客户：无祖先链，relationship_code 使用全空槽位。
        if ($parentId === 0) {
            return [
                'ancestor_ids' => [],
                'family_tree' => (string) $userId,
                'relationship_code' => str_repeat('0000', 5),
            ];
        }

        $cursor = $parentId;
        // visited 从被编辑用户自身开始，防止把客户挂到自身或自身后代形成循环。
        $visited = [$userId => true];
        $reversedAncestors = [];

        // 沿 parent_id 逐级回溯；链路任一节点缺失、非代理或成环都失败关闭。
        while ($cursor > 0) {
            if (isset($visited[$cursor])) {
                throw new \InvalidArgumentException('目标上级代理链形成循环。');
            }
            if (count($reversedAncestors) >= UserInfo::MAX_HIERARCHY_DEPTH) {
                throw new \InvalidArgumentException('目标上级代理链超过安全深度限制。');
            }

            $ancestor = UserInfo::query()
                ->where('user_id', $cursor)
                ->first(['user_id', 'parent_id', 'account_type', 'level_id']);
            if (!$ancestor) {
                throw new \InvalidArgumentException('目标上级代理链存在缺失节点。');
            }
            if ((int) $ancestor->account_type !== 1) {
                throw new \InvalidArgumentException('目标上级链包含非代理账号。');
            }

            $visited[$cursor] = true;
            $reversedAncestors[] = [
                'user_id' => (int) $ancestor->user_id,
                'level_id' => (int) $ancestor->level_id,
            ];
            $cursor = (int) $ancestor->parent_id;
        }

        // 祖先按根到直属排序，family_tree 追加自身作为当前用户。
        $ancestorNodes = array_reverse($reversedAncestors);
        $ancestorIds = array_column($ancestorNodes, 'user_id');

        return [
            'ancestor_ids' => $ancestorIds,
            'family_tree' => implode(',', array_merge($ancestorIds, [$userId])),
            'relationship_code' => $this->legacyRelationshipCode($ancestorNodes),
        ];
    }

    /**
     * 同步普通客户在代理闭包表中的全部祖先关系。
     *
     * 这样做解决的问题：
     * - 客户换上级后，旧代理不能继续通过 agent_descendants 看到该客户，新代理链必须立即获得正确关系。
     * - 表的唯一键是 agent_id + descendant_id；updateOrInsert 会恢复命中的软删除行并把 deleted_at 清空，避免重复键错误。
     * - depth 从客户向上计算，直属代理为 1，越靠近根节点数字越大；is_direct 只对 parentId 对应代理记为 1。
     *
     * @param int $userId 目标普通客户业务用户 ID。
     * @param int $accountType 目标账号类型，当前入口只允许 2=普通客户。
     * @param array<int, int> $ancestorIds 按最上层代理到直属代理排列的新祖先 ID。
     * @param int $parentId 新直属上级代理 ID；0 表示平台根节点。
     * @return void 成功时闭包表与新祖先链一致；数据库异常由调用方事务回滚并执行 MT4 补偿。
     *
     * @throws \InvalidArgumentException 账号类型或祖先参数不符合普通客户闭包规则时抛出。
     */
    public function syncCustomerDescendantRelations(
        int $userId,
        int $accountType,
        array $ancestorIds,
        int $parentId
    ): void {
        if ($accountType !== 2) {
            throw new \InvalidArgumentException('该闭包同步入口只允许普通客户。');
        }

        $ancestorIds = array_values(array_unique(array_map('intval', $ancestorIds)));
        if (count($ancestorIds) > UserInfo::MAX_HIERARCHY_DEPTH) {
            throw new \InvalidArgumentException('目标上级代理链超过安全深度限制。');
        }
        // 前置一致性校验：平台直属客户不能带祖先；有上级时祖先链末尾必须是该上级。
        if ($parentId === 0 && !empty($ancestorIds)) {
            throw new \InvalidArgumentException('平台直属客户不能保留代理祖先。');
        }
        if ($parentId > 0 && (empty($ancestorIds) || end($ancestorIds) !== $parentId)) {
            throw new \InvalidArgumentException('直属上级与祖先链末节点不一致。');
        }

        // 先删除旧闭包行（全部或不在新链中的），再按新链写入，避免残留旧代理关系。
        $oldRelations = DB::table('agent_descendants')->where('descendant_id', $userId);
        if (empty($ancestorIds)) {
            $oldRelations->delete();
        } else {
            $oldRelations->whereNotIn('agent_id', $ancestorIds)->delete();
        }

        $now = time();
        $ancestorCount = count($ancestorIds);
        // depth 从直属代理(1)向上递增；is_direct 只对直属上级记为 1。
        foreach ($ancestorIds as $index => $agentId) {
            DB::table('agent_descendants')->updateOrInsert(
                [
                    'agent_id' => $agentId,
                    'descendant_id' => $userId,
                ],
                [
                    'descendant_type' => $accountType,
                    'is_direct' => $agentId === $parentId ? 1 : 0,
                    'depth' => $ancestorCount - $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * 按旧项目代理等级槽位生成 MT4 cny 五段关系码。
     *
     * @param array<int, array{user_id: int, level_id: int}> $ancestorNodes 从根代理到直属代理排列的祖先节点。
     * @return string 五段拼接结果；未使用槽位返回 0000。
     *
     * @throws \InvalidArgumentException 代理未配置等级或等级编码小于 1 时抛出。
     */
    private function legacyRelationshipCode(array $ancestorNodes): string
    {
        $levelIds = array_values(array_unique(array_column($ancestorNodes, 'level_id')));
        $levelCodes = DB::table('agent_levels')
            ->whereIn('id', $levelIds)
            ->pluck('level_code', 'id')
            ->all();
        $segments = array_fill(0, 5, '0000');

        // 旧算法从直属代理向根节点递归；同一等级重复时更上层代理最终覆盖该槽位。
        foreach (array_reverse($ancestorNodes) as $ancestor) {
            $levelId = (int) $ancestor['level_id'];
            if (!array_key_exists($levelId, $levelCodes)) {
                throw new \InvalidArgumentException('代理未配置有效等级。');
            }

            $levelCode = (int) $levelCodes[$levelId];
            if ($levelCode < 1) {
                throw new \InvalidArgumentException('代理等级编码不合法。');
            }

            $segmentIndex = $levelCode >= 5 ? 4 : $levelCode - 1;
            // 5 级及以上共用最后一段槽位，与旧项目五段关系码规则一致。
            $segments[$segmentIndex] = (string) (int) $ancestor['user_id'];
        }

        return implode('', $segments);
    }

    /**
     * 调整用户直属上级并重建目标子树的代理闭包关系。
     *
     * 业务逻辑说明：
     * - $userId 表示被移动的业务用户，可以是代理，也可以是普通客户。
     * - $newParentId 表示新的直属上级代理业务用户 ID；0 表示移动到平台根节点。
     * - 本方法只负责一致性写入，目标上级是否存在、是否代理、是否形成循环由控制器在调用前校验。
     * - 更新顺序是先改 parent_id，再重建目标用户及其全部下级的 family_tree，最后按新链路重建 agent_descendants。
     *
     * @param int $userId 被调整上级的业务用户 ID。
     * @param int $newParentId 新直属上级代理业务用户 ID，0 表示平台根节点。
     * @return void
     */
    public function reassignParent(int $userId, int $newParentId): void
    {
        DB::transaction(function () use ($userId, $newParentId) {
            $subtreeUserIds = $this->collectSubtreeUserIds($userId);
            $userInfo = UserInfo::where('user_id', $userId)->lockForUpdate()->first();
            if (!$userInfo) {
                return;
            }

            $userInfo->update(['parent_id' => $newParentId]);

            $visited = [];
            $this->rebuildFamilyTreeForSubtree($userId, $visited);
            $this->rebuildDescendantRowsForUsers($subtreeUserIds);
        });
    }

    /**
     * 为目标用户及其全部后代重建 family_tree。
     *
     * 参数含义：
     * - $userId 表示目标业务用户 ID。
     * - $userInfo 表示目标用户资料。
     * - $parentInfo 表示目标用户的上级资料，用于拼接新的 family_tree。
     * - $newTree 表示重新计算后的代理家族链。
     * - $children 表示目标用户的直属下级，会递归重建。
     *
     * @param int $userId 目标业务用户 ID。
     * @return void
     */
    public function rebuildFamilyTree(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $visited = [];
            $this->rebuildFamilyTreeForSubtree($userId, $visited);
        });
    }

    /**
     * 为指定代理商重建 agent_descendants 表关系。
     *
     * 参数含义：
     * - $agentId 表示代理商业务用户 ID。
     * - $descendants 表示 family_tree 中包含当前代理的所有下级用户。
     * - $treeIds 表示从 family_tree 拆分出的用户链路。
     * - $agentPos 表示当前代理在链路中的位置。
     * - $descPos 表示下级用户在链路中的位置。
     * - $depth 表示下级用户相对代理商的层级深度。
     * - $isDirect 表示下级用户是否为当前代理的直属下级。
     *
     * @param int $agentId 代理商业务用户 ID。
     * @return void
     */
    public function rebuildDescendants(int $agentId): void
    {
        DB::transaction(function () use ($agentId) {
            $agent = UserInfo::where('user_id', $agentId)
                ->where('account_type', 1)
                ->first();
            if (!$agent) {
                throw new \InvalidArgumentException('重建目标必须是有效代理。');
            }

            // 表有 (agent_id, descendant_id) 唯一键，必须物理删除软删除行。
            DB::table('agent_descendants')->where('agent_id', $agentId)->delete();

            $relations = $this->buildDescendantRelationsFromParentTopology($agentId);
            $now = time();
            foreach ($relations as $relation) {
                DB::table('agent_descendants')->insert([
                    'agent_id' => $relation['agent_id'],
                    'descendant_id' => $relation['descendant_id'],
                    'descendant_type' => $relation['descendant_type'],
                    'is_direct' => $relation['is_direct'],
                    'depth' => $relation['depth'],
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            }
        });
    }

    /**
     * 按当前直属拓扑原子重建全部 family_tree 与 agent_descendants。
     * 所有拓扑校验在任何写入前完成，异常时事务不会留下半套关系。
     *
     * @return array{users:int, relations:int}
     */
    public function rebuildAllHierarchy(): array
    {
        return DB::transaction(function (): array {
            $users = UserInfo::query()
                ->whereNull('deleted_at')
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get(['user_id', 'parent_id', 'account_type']);

            [$familyTrees, $relations] = $this->buildHierarchySnapshot($users);
            $now = time();

            foreach ($familyTrees as $userId => $familyTree) {
                DB::table('user_infos')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->update([
                        'family_tree' => $familyTree,
                        'updated_at' => $now,
                    ]);
            }

            // 同时清理软删除关系，避免唯一键阻塞下一次重建和残留越权范围。
            DB::table('agent_descendants')->delete();
            foreach (array_chunk($relations, 500) as $chunk) {
                DB::table('agent_descendants')->insert(array_map(function (array $relation) use ($now): array {
                    return array_merge($relation, [
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                }, $chunk));
            }

            return [
                'users' => $users->count(),
                'relations' => count($relations),
            ];
        });
    }

    /**
     * 只读比较当前派生数据与 parent_id 权威拓扑，不执行任何写入。
     *
     * @return array<string, int|bool|array<int, string>>
     */
    public function auditHierarchy(): array
    {
        $users = UserInfo::query()
            ->whereNull('deleted_at')
            ->orderBy('user_id')
            ->get(['user_id', 'parent_id', 'account_type', 'family_tree']);

        try {
            [$familyTrees, $expectedRelations] = $this->buildHierarchySnapshot($users);
        } catch (\InvalidArgumentException $exception) {
            return [
                'valid' => false,
                'users' => $users->count(),
                'expected_relations' => 0,
                'actual_relations' => (int) DB::table('agent_descendants')->whereNull('deleted_at')->count(),
                'missing' => 0,
                'mismatch' => 0,
                'extra' => 0,
                'family_tree_mismatch' => 0,
                'soft_deleted_relations' => (int) DB::table('agent_descendants')->whereNotNull('deleted_at')->count(),
                'errors' => [$exception->getMessage()],
            ];
        }

        $expected = [];
        foreach ($expectedRelations as $relation) {
            $expected[$relation['agent_id'] . ':' . $relation['descendant_id']] = $relation;
        }

        $actual = [];
        $actualRows = DB::table('agent_descendants')
            ->whereNull('deleted_at')
            ->get(['agent_id', 'descendant_id', 'descendant_type', 'is_direct', 'depth']);
        foreach ($actualRows as $row) {
            $relation = [
                'agent_id' => (int) $row->agent_id,
                'descendant_id' => (int) $row->descendant_id,
                'descendant_type' => (int) $row->descendant_type,
                'is_direct' => (int) $row->is_direct,
                'depth' => (int) $row->depth,
            ];
            $actual[$relation['agent_id'] . ':' . $relation['descendant_id']] = $relation;
        }

        $missing = 0;
        $mismatch = 0;
        foreach ($expected as $key => $relation) {
            if (!isset($actual[$key])) {
                $missing++;
            } elseif ($actual[$key] !== $relation) {
                $mismatch++;
            }
        }

        $extra = 0;
        foreach ($actual as $key => $_relation) {
            if (!isset($expected[$key])) {
                $extra++;
            }
        }

        $familyTreeMismatch = 0;
        foreach ($users as $user) {
            if ((string) $user->family_tree !== $familyTrees[(int) $user->user_id]) {
                $familyTreeMismatch++;
            }
        }
        $softDeletedRelations = (int) DB::table('agent_descendants')->whereNotNull('deleted_at')->count();

        return [
            'valid' => $missing === 0
                && $mismatch === 0
                && $extra === 0
                && $familyTreeMismatch === 0
                && $softDeletedRelations === 0,
            'users' => $users->count(),
            'expected_relations' => count($expected),
            'actual_relations' => count($actual),
            'missing' => $missing,
            'mismatch' => $mismatch,
            'extra' => $extra,
            'family_tree_mismatch' => $familyTreeMismatch,
            'soft_deleted_relations' => $softDeletedRelations,
            'errors' => [],
        ];
    }

    /**
     * @param Collection<int, UserInfo> $users
     * @return array{0: array<int, string>, 1: array<int, array{agent_id:int, descendant_id:int, descendant_type:int, is_direct:int, depth:int}>}
     */
    private function buildHierarchySnapshot(Collection $users): array
    {
        $byId = [];
        foreach ($users as $user) {
            $userId = (int) $user->user_id;
            if ($userId <= 0 || !in_array((int) $user->account_type, [1, 2], true)) {
                throw new \InvalidArgumentException('用户层级包含无效用户或账户类型。');
            }
            if (isset($byId[$userId])) {
                throw new \InvalidArgumentException('用户层级包含重复用户。');
            }
            $byId[$userId] = $user;
        }

        $familyTrees = [];
        $relations = [];
        foreach ($users as $user) {
            $userId = (int) $user->user_id;
            $parentId = (int) $user->parent_id;
            $cursor = $parentId;
            $visited = [$userId => true];
            $reversedAncestors = [];

            while ($cursor > 0) {
                if (isset($visited[$cursor])) {
                    throw new \InvalidArgumentException('用户层级存在循环关系。');
                }
                $visited[$cursor] = true;

                $ancestor = $byId[$cursor] ?? null;
                if (!$ancestor) {
                    throw new \InvalidArgumentException('用户层级存在孤儿父级。');
                }
                if ((int) $ancestor->account_type !== 1) {
                    throw new \InvalidArgumentException('普通客户不能作为任何用户的直属上级。');
                }

                $reversedAncestors[] = $cursor;
                $cursor = (int) $ancestor->parent_id;
                if (count($reversedAncestors) >= UserInfo::MAX_HIERARCHY_DEPTH && $cursor > 0) {
                    throw new \InvalidArgumentException('用户层级超过安全深度限制。');
                }
            }

            $ancestorIds = array_reverse($reversedAncestors);
            $familyTrees[$userId] = implode(',', array_merge($ancestorIds, [$userId]));
            $ancestorCount = count($ancestorIds);
            foreach ($ancestorIds as $index => $agentId) {
                $relations[] = [
                    'agent_id' => (int) $agentId,
                    'descendant_id' => $userId,
                    'descendant_type' => (int) $user->account_type,
                    'is_direct' => $agentId === $parentId ? 1 : 0,
                    'depth' => $ancestorCount - $index,
                ];
            }
        }

        return [$familyTrees, $relations];
    }

    /**
     * 从当前 parent_id 拓扑生成单个代理的完整闭包关系。
     * family_tree 只作为展示快照，不能作为关系范围的事实源。
     *
     * @return array<int, array{agent_id:int, descendant_id:int, descendant_type:int, is_direct:int, depth:int}>
     */
    private function buildDescendantRelationsFromParentTopology(int $agentId): array
    {
        $agent = UserInfo::where('user_id', $agentId)
            ->where('account_type', 1)
            ->first();
        if (!$agent) {
            return [];
        }

        $relations = [];
        $frontier = [[$agentId, 0]];
        $visited = [$agentId => true];

        while ($frontier !== []) {
            [$parentId, $parentDepth] = array_shift($frontier);
            $children = UserInfo::where('parent_id', $parentId)
                ->orderBy('user_id')
                ->get(['user_id', 'parent_id', 'account_type']);

            foreach ($children as $child) {
                $childId = (int) $child->user_id;
                $accountType = (int) $child->account_type;
                if (!in_array($accountType, [1, 2], true)) {
                    throw new \RuntimeException('User hierarchy contains an invalid account type.');
                }
                if ($accountType === 2 && UserInfo::where('parent_id', $childId)->exists()) {
                    throw new \RuntimeException('User hierarchy contains a customer parent.');
                }
                if (isset($visited[$childId])) {
                    throw new \RuntimeException('代理层级存在循环关系。');
                }
                $visited[$childId] = true;
                $depth = $parentDepth + 1;
                if ($depth > UserInfo::MAX_HIERARCHY_DEPTH) {
                    throw new \RuntimeException('代理层级超过安全深度限制。');
                }

                $relations[] = [
                    'agent_id' => $agentId,
                    'descendant_id' => $childId,
                    'descendant_type' => $accountType,
                    'is_direct' => $depth === 1 ? 1 : 0,
                    'depth' => $depth,
                ];

                if ($accountType === 1) {
                    $frontier[] = [$childId, $depth];
                }
            }
        }

        return $relations;
    }

    /**
     * 兼容旧项目 `,0,祖先...,自身,` 与新项目无包围逗号的 family_tree 格式。
     *
     * @return array<int, int>
     */
    private function parseFamilyTreeIds(string $familyTree): array
    {
        $ids = [];
        foreach (explode(',', $familyTree) as $token) {
            $id = (int) trim($token);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * 收集目标用户以及所有直接、间接下级用户 ID。
     *
     * @param int $rootUserId 子树根节点业务用户 ID。
     * @return array<int, int> 子树内全部业务用户 ID，包含根节点自身。
     */
    private function collectSubtreeUserIds(int $rootUserId): array
    {
        $result = [];
        $frontier = [$rootUserId];

        // BFS 按 parent_id 逐层收集子树，已访问集合防止脏数据成环。
        while (!empty($frontier)) {
            $currentId = (int) array_shift($frontier);
            if (isset($result[$currentId])) {
                continue;
            }

            $result[$currentId] = $currentId;
            $children = UserInfo::where('parent_id', $currentId)
                ->pluck('user_id')
                ->map(function ($childId) {
                    return (int) $childId;
                })
                ->all();

            foreach ($children as $childId) {
                if (!isset($result[$childId])) {
                    $frontier[] = $childId;
                }
            }
        }

        return array_values($result);
    }

    /**
     * 按当前 parent_id 递归重建目标子树 family_tree。
     *
     * @param int $userId 当前正在重建的业务用户 ID。
     * @param array<int, bool> $visited 已处理用户标记，用于阻断历史脏循环。
     * @return void
     */
    private function rebuildFamilyTreeForSubtree(int $userId, array &$visited): void
    {
        if (isset($visited[$userId])) {
            return;
        }
        $visited[$userId] = true;

        $userInfo = UserInfo::where('user_id', $userId)->first();
        if (!$userInfo) {
            return;
        }

        $familyTree = $this->familyTreeFromParentTopology($userInfo);
        $userInfo->update(['family_tree' => $familyTree]);

        $children = UserInfo::where('parent_id', $userId)->pluck('user_id');
        foreach ($children as $childId) {
            $this->rebuildFamilyTreeForSubtree((int) $childId, $visited);
        }
    }

    /**
     * Build a canonical family_tree snapshot from the current parent_id chain.
     *
     * The snapshot is derived data; a missing/non-agent parent or a cycle is a
     * topology error and must stop the rebuild instead of silently creating a
     * root node.
     */
    private function familyTreeFromParentTopology(UserInfo $userInfo): string
    {
        $userId = (int) $userInfo->user_id;
        $ids = [$userId];
        $visited = [$userId => true];
        $parentId = (int) $userInfo->parent_id;

        while ($parentId > 0) {
            if (isset($visited[$parentId])) {
                throw new \InvalidArgumentException('User hierarchy contains a cycle.');
            }
            if (count($ids) - 1 >= UserInfo::MAX_HIERARCHY_DEPTH) {
                throw new \InvalidArgumentException('User hierarchy exceeds the safe depth limit.');
            }
            $visited[$parentId] = true;

            $parent = UserInfo::where('user_id', $parentId)
                ->whereNull('deleted_at')
                ->first(['user_id', 'parent_id', 'account_type']);
            if (!$parent || (int) $parent->account_type !== 1) {
                throw new \InvalidArgumentException('User hierarchy contains an invalid parent.');
            }

            array_unshift($ids, $parentId);
            $parentId = (int) $parent->parent_id;
        }

        return implode(',', $ids);
    }

    /**
     * 为一组后代用户按当前 family_tree 重建 agent_descendants 闭包行。
     *
     * 这样做的原因：
     * - agent_descendants 使用唯一键 agent_id + descendant_id，软删除旧行后再插入会撞唯一键。
     * - 因此这里用查询构造器物理删除受影响后代旧闭包，再按新 family_tree 重建。
     * - 只为 account_type=1 的祖先写 agent_id，避免历史脏数据把普通客户写成代理节点。
     *
     * @param array<int, int> $userIds 需要重建闭包关系的后代用户 ID。
     * @return void
     */
    private function rebuildDescendantRowsForUsers(array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }

        DB::table('agent_descendants')->whereIn('descendant_id', $userIds)->delete();

        $users = UserInfo::whereIn('user_id', $userIds)->get();
        foreach ($users as $userInfo) {
            $this->createDescendantRowsFromFamilyTree($userInfo);
        }
    }

    /**
     * 按单个用户当前 family_tree 生成祖先代理闭包行。
     *
     * @param UserInfo $userInfo 当前需要重建后代关系的业务用户资料。
     * @return void
     */
    private function createDescendantRowsFromFamilyTree(UserInfo $userInfo): void
    {
        $treeIds = array_values(array_filter(array_map('intval', explode(',', (string) $userInfo->family_tree))));
        $selfIndex = array_search((int) $userInfo->user_id, $treeIds, true);
        if ($selfIndex === false || $selfIndex === 0) {
            return;
        }

        $ancestorIds = array_slice($treeIds, 0, $selfIndex);
        $agentTypes = UserInfo::whereIn('user_id', $ancestorIds)->pluck('account_type', 'user_id')->all();
        $now = time();

        foreach ($ancestorIds as $index => $agentId) {
            // 只有代理节点能作为 agent_id 写入闭包，普通客户祖先直接跳过。
            if ((int) ($agentTypes[$agentId] ?? 0) !== 1) {
                continue;
            }

            DB::table('agent_descendants')->updateOrInsert(
                [
                    'agent_id' => $agentId,
                    'descendant_id' => (int) $userInfo->user_id,
                ],
                [
                    'descendant_type' => (int) $userInfo->account_type,
                    'is_direct' => ((int) $userInfo->parent_id === (int) $agentId) ? 1 : 0,
                    'depth' => $selfIndex - $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
