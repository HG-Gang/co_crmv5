<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 16:45
 */

/**
 * 前台遗留数据兼容工具集。
 *
 * 文件功能：
 * - 统一解析前台新旧表格请求参数（per_page / limit / rows 分页，date_from / startdate、date_to / enddate 日期）。
 * - 以 parent_id 为唯一拓扑事实源计算代理树数据范围，并给查询对象追加用户、日期、品种过滤条件。
 * - 把当前模型数据转换为旧前台 Blade/Layui 表格可识别的字段别名行（交易、用户基础资料）。
 * - 汇总入金、出金、交易、返佣、品种分类成交量等财务统计，并生成旧表格所需的 totalRow 合计行。
 * - 提供邮箱、手机号、身份证脱敏与状态文案映射（充值/提现/出金结算状态）。
 *
 * 适用场景：
 * - 前台用户中心、代理后台列表页与统计页迁移后，控制器直接调用本工具保持旧前端字段与交互不变。
 *
 * 入参例子：
 * - FrontLegacyData::perPage($request, 15)
 * - FrontLegacyData::userScopeIds(600123, true, 1, null)
 * - FrontLegacyData::tradeAliasRow($trade)
 * - FrontLegacyData::financialSummaryForIds([600123, 600456], $request)
 * - FrontLegacyData::paginatedListResponse($paginator, $totalRow)
 *
 * 返回值：
 * - 分页/日期解析返回 int/string/int|null；范围与别名方法返回数组；掩码返回脱敏字符串。
 * - 财务汇总返回包含 total_yuerj、total_yuecj、total_net_worth、total_comm、total_profit、total_volume、total_swaps、fy_money、rj_money、qk_money、total_rebate 及各品种成交量的数组。
 * - 状态文案返回 front 语言包 key 对应的多语言文本。
 *
 * 异常或失败场景：
 * - 本工具均为纯计算/查询辅助方法，不主动抛业务异常；查询类方法只追加 where 条件，不修改调用方查询语义。
 */
namespace App\Support;

use App\Models\CommissionRecord;
use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FrontLegacyData
{
    /**
     * 旧协议中归类为“入金”的 MT4 COMMENT 码全集。这些码同时包含 WBIR 等特殊码，
     * 财务汇总只认库内历史 COMMENT 原值；增删任何码都会直接改变入金统计口径，必须与旧项目逐一核对。
     *
     * @var array<int, string>
     */
    private const LEGACY_DEPOSIT_COMMENT_CODES = [
        'DBAA', 'DBCT', 'DBGN', 'DBMN', 'DBPA', 'DBPN',
        'DBSN', 'DBTN', 'DBUN', 'DBZN', 'DBAD', 'WBIR',
    ];

    /**
     * 旧协议中归类为“出金”的 MT4 COMMENT 码全集。与入金码集互斥（含 DBZR 这一历史特殊码），
     * 用于把 mt4_trades COMMENT 映射回旧前台的出金统计；两族码的边界就是入金/出金报表的分界线。
     *
     * @var array<int, string>
     */
    private const LEGACY_WITHDRAWAL_COMMENT_CODES = [
        'WBAA', 'WBCN', 'WBCT', 'WBHN', 'WBIN',
        'WBPN', 'WBSN', 'WBTN', 'WBAD', 'DBZR',
    ];

    /**
     * 旧协议中归类为“返佣”的 MT4 COMMENT 码，仅 DBCN 一项。
     * 与入金/出金码集独立成第三族：佣金发放记录以该码识别，误并入其他族会重复计入资金统计。
     *
     * @var array<int, string>
     */
    private const LEGACY_REBATE_COMMENT_CODES = ['DBCN'];

    /**
     * 解析前台列表的单页条数，并同时兼容新旧表格请求参数。
     *
     * 参数优先级：
     * - `per_page` 是新接口的显式分页参数。
     * - `limit` 是现有 Layui/Naive 页面使用的兼容参数。
     * - `rows` 是旧项目 WidgetPage 发送的分页参数，不能被默认值覆盖。
     *
     * @param Request $request 当前请求对象。
     * @param int $default 未传分页参数时使用的默认条数。
     * @return int 限制在 1 至 100 的合法单页条数，避免空值、负数和超大值破坏查询。
     */
    public static function perPage(Request $request, int $default = 15): int
    {
        $perPage = (int) $request->input(
            'per_page',
            $request->input('limit', $request->input('rows', $default))
        );

        return max(1, min($perPage, 100));
    }

    /**
     * 解析起始日期：兼容新参数 date_from 与旧参数 startdate。
     *
     * @param Request $request 当前请求对象。
     * @return string|null 非空日期字符串，否则 null。
     */
    public static function dateFrom(Request $request): ?string
    {
        $value = trim((string) $request->input('date_from', ''));
        if ($value === '') {
            $value = trim((string) $request->input('startdate', ''));
        }

        return $value !== '' ? $value : null;
    }

    /**
     * 解析结束日期：兼容新参数 date_to 与旧参数 enddate。
     *
     * @param Request $request 当前请求对象。
     * @return string|null 非空日期字符串，否则 null。
     */
    public static function dateTo(Request $request): ?string
    {
        $value = trim((string) $request->input('date_to', ''));
        if ($value === '') {
            $value = trim((string) $request->input('enddate', ''));
        }

        return $value !== '' ? $value : null;
    }

    /**
     * 起始日 00:00:00 的时间戳（created_at 类过滤使用）。
     *
     * @param Request $request 当前请求对象。
     * @return int|null 无日期时为 null。
     */
    public static function timestampFrom(Request $request): ?int
    {
        $date = self::dateFrom($request);

        return $date ? strtotime($date . ' 00:00:00') : null;
    }

    /**
     * 结束日 23:59:59 的时间戳（created_at 类过滤使用）。
     *
     * @param Request $request 当前请求对象。
     * @return int|null 无日期时为 null。
     */
    public static function timestampTo(Request $request): ?int
    {
        $date = self::dateTo($request);

        return $date ? strtotime($date . ' 23:59:59') : null;
    }

    /**
     * 按 parent_id 父子树计算用户数据范围 ID；可选仅直属或仅非直属。
     * agent_descendants 是派生闭包，不参与权限、统计或当前链路的事实判断。
     *
     * @param int $userId 当前用户 ID。
     * @param bool $includeSelf 是否包含用户自身。
     * @param int|null $descendantType 限定 account_type，null 不限定。
     * @param bool|null $directOnly true 仅直属，false 仅非直属，null 不限定。
     * @return array<int, int> 去重后的用户 ID 列表。
     */
    public static function userScopeIds(int $userId, bool $includeSelf = true, int $descendantType = null, bool $directOnly = null): array
    {
        $ids = self::parentTreeScopeIds($userId, $descendantType, $directOnly);
        if ($ids === null) {
            return [];
        }

        if ($includeSelf) {
            $ids[] = $userId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * 批量计算多个代理根的完整用户范围。
     *
     * 层级关系只读取 user_infos.parent_id；数据库按层级批量加载一次，随后在内存中
     * 分别校验每个根的循环、账户类型和最大深度，避免持仓列表逐代理递归查询。
     *
     * @param array<int, int|string> $agentIds 代理根 user_id 列表。
     * @param bool $includeSelf 是否在每个范围中包含根代理。
     * @return array<int, array<int, int>> 以代理根 user_id 为键的去重范围。
     */
    public static function userScopesForAgentIds(array $agentIds, bool $includeSelf = true): array
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        $scopes = [];
        foreach ($agentIds as $agentId) {
            $scopes[$agentId] = [];
        }
        if ($agentIds === []) {
            return $scopes;
        }

        $roots = UserInfo::whereIn('user_id', $agentIds)
            ->get(['user_id', 'account_type'])
            ->keyBy('user_id');
        $validRoots = [];
        foreach ($agentIds as $agentId) {
            $root = $roots->get($agentId);
            if ($root && (int) $root->account_type === 1) {
                $validRoots[] = $agentId;
            }
        }

        $childrenByParent = [];
        $expanded = [];
        $frontier = $validRoots;
        while ($frontier !== []) {
            $parents = array_values(array_filter(array_unique($frontier), static function (int $id) use ($expanded): bool {
                return !isset($expanded[$id]);
            }));
            if ($parents === []) {
                break;
            }
            foreach ($parents as $parentId) {
                $expanded[$parentId] = true;
            }

            $frontier = [];
            foreach (UserInfo::whereIn('parent_id', $parents)->get(['user_id', 'parent_id', 'account_type']) as $child) {
                $childId = (int) $child->user_id;
                $parentId = (int) $child->parent_id;
                $accountType = (int) $child->account_type;
                $childrenByParent[$parentId][] = ['id' => $childId, 'account_type' => $accountType];
                if ($accountType === 1 && !isset($expanded[$childId])) {
                    $frontier[] = $childId;
                }
            }
        }

        foreach ($validRoots as $agentId) {
            $visited = [];
            $descendants = self::collectLoadedAgentScopeIds($agentId, $childrenByParent, 1, $visited);
            if ($descendants === null) {
                continue;
            }
            if ($includeSelf) {
                $descendants[] = $agentId;
            }
            $scopes[$agentId] = array_values(array_unique($descendants));
        }

        return $scopes;
    }

    /**
     * @param array<int, array<int, array{id:int, account_type:int}>> $childrenByParent
     * @param array<int, bool> $visited
     * @return array<int, int>|null
     */
    private static function collectLoadedAgentScopeIds(
        int $agentId,
        array $childrenByParent,
        int $depth,
        array &$visited
    ): ?array {
        if (isset($visited[$agentId])) {
            return null;
        }

        $visited[$agentId] = true;
        $ids = [];
        foreach ($childrenByParent[$agentId] ?? [] as $child) {
            if ($depth > UserInfo::MAX_HIERARCHY_DEPTH) {
                return null;
            }

            $childId = $child['id'];
            $accountType = $child['account_type'];
            if (!in_array($accountType, [1, 2], true) || isset($visited[$childId])) {
                return null;
            }

            $ids[] = $childId;
            if ($accountType === 1) {
                $descendants = self::collectLoadedAgentScopeIds($childId, $childrenByParent, $depth + 1, $visited);
                if ($descendants === null) {
                    return null;
                }
                $ids = array_merge($ids, $descendants);
            }
        }

        return $ids;
    }

    /**
     * Resolve configured agent networks with one active user_infos load.
     *
     * Every configured root must exist, be non-deleted and have account_type=1.
     * Roots are de-duplicated before traversal. A null result means any root or
     * reachable hierarchy is invalid, so callers must fail the complete scope closed.
     * When a selected agent is supplied, agent_ids still contains the complete allowed
     * agent union while customer_ids is limited to that selected agent's subtree.
     *
     * @param array<int, int|string> $rootAgentIds Configured agent root user IDs.
     * @param int|null $selectedAgentId Optional allowed agent used to narrow customers.
     * @return array{agent_ids: array<int, int>, customer_ids: array<int, int>}|null
     */
    public static function strictAgentNetworkIdsOrNull(
        array $rootAgentIds,
        int $selectedAgentId = null
    ): ?array {
        $rootAgentIds = array_values(array_unique(array_filter(array_map('intval', $rootAgentIds))));
        if ($rootAgentIds === []) {
            return ['agent_ids' => [], 'customer_ids' => []];
        }

        $nodes = [];
        $childrenByParent = [];
        foreach (UserInfo::query()->get(['user_id', 'parent_id', 'account_type']) as $user) {
            $userId = (int) $user->user_id;
            $accountType = (int) $user->account_type;
            $nodes[$userId] = $accountType;
            $childrenByParent[(int) $user->parent_id][] = [
                'id' => $userId,
                'account_type' => $accountType,
            ];
        }

        foreach ($rootAgentIds as $rootAgentId) {
            if (($nodes[$rootAgentId] ?? null) !== 1) {
                return null;
            }
        }

        $agentIds = array_fill_keys($rootAgentIds, true);
        $customerIds = [];
        $selectedCustomerIds = [];
        foreach ($rootAgentIds as $rootAgentId) {
            $visited = [];
            if (!self::collectStrictAgentNetworkFromMap(
                $rootAgentId,
                $childrenByParent,
                1,
                $selectedAgentId,
                $selectedAgentId !== null && $rootAgentId === $selectedAgentId,
                $visited,
                $agentIds,
                $customerIds,
                $selectedCustomerIds
            )) {
                return null;
            }
        }

        $resolvedAgentIds = array_map('intval', array_keys($agentIds));
        sort($resolvedAgentIds, SORT_NUMERIC);
        if ($selectedAgentId !== null) {
            $resolvedCustomerIds = isset($agentIds[$selectedAgentId])
                ? array_map('intval', array_keys($selectedCustomerIds))
                : [];
        } else {
            $resolvedCustomerIds = array_map('intval', array_keys($customerIds));
        }
        sort($resolvedCustomerIds, SORT_NUMERIC);

        return [
            'agent_ids' => $resolvedAgentIds,
            'customer_ids' => $resolvedCustomerIds,
        ];
    }

    /**
     * @param array<int, array<int, array{id:int, account_type:int}>> $childrenByParent
     * @param array<int, bool> $visited
     * @param array<int, bool> $agentIds
     * @param array<int, bool> $customerIds
     * @param array<int, bool> $selectedCustomerIds
     */
    private static function collectStrictAgentNetworkFromMap(
        int $agentId,
        array $childrenByParent,
        int $depth,
        ?int $selectedAgentId,
        bool $insideSelectedAgent,
        array &$visited,
        array &$agentIds,
        array &$customerIds,
        array &$selectedCustomerIds
    ): bool {
        if (isset($visited[$agentId])) {
            return false;
        }
        $visited[$agentId] = true;

        foreach ($childrenByParent[$agentId] ?? [] as $child) {
            if ($depth > UserInfo::MAX_HIERARCHY_DEPTH) {
                return false;
            }

            $childId = $child['id'];
            $accountType = $child['account_type'];
            if (isset($visited[$childId]) || !in_array($accountType, [1, 2], true)) {
                return false;
            }

            if ($accountType === 1) {
                $agentIds[$childId] = true;
                if (!self::collectStrictAgentNetworkFromMap(
                    $childId,
                    $childrenByParent,
                    $depth + 1,
                    $selectedAgentId,
                    $insideSelectedAgent || $childId === $selectedAgentId,
                    $visited,
                    $agentIds,
                    $customerIds,
                    $selectedCustomerIds
                )) {
                    return false;
                }
                continue;
            }

            $customerIds[$childId] = true;
            if ($insideSelectedAgent) {
                $selectedCustomerIds[$childId] = true;
            }
        }

        return true;
    }

    /**
     * Resolve an active agent root's descendants without hiding invalid hierarchy state.
     *
     * The root must be a non-deleted account_type=1 user. The returned list excludes
     * the root ID. A null result means the root or its hierarchy is invalid and callers
     * must fail closed.
     *
     * @param int $agentId Root agent user ID.
     * @param int|null $descendantType Optional descendant account type.
     * @param bool|null $directOnly true for direct descendants, false for indirect descendants.
     * @return array<int, int>|null Descendant IDs, or null when the scope is invalid.
     */
    public static function agentScopeIdsOrNull(int $agentId, int $descendantType = null, bool $directOnly = null): ?array
    {
        return self::parentTreeScopeIds($agentId, $descendantType, $directOnly, true);
    }

    /**
     * 按 parent_id 父子树查询当前权威数据范围。
     *
     * @param int $agentId 根用户 ID。
     * @param int|null $descendantType 限定 account_type。
     * @param bool|null $directOnly true 仅直属（depth=1），false 仅非直属。
     * @return array<int, int> 子树用户 ID 列表。
     */
    private static function parentTreeScopeIds(
        int $agentId,
        int $descendantType = null,
        bool $directOnly = null,
        bool $requireAgentRoot = false
    ): ?array
    {
        $root = UserInfo::where('user_id', $agentId)->first(['user_id', 'account_type']);
        if ($requireAgentRoot && (!$root || (int) $root->account_type !== 1)) {
            return null;
        }
        if (!$root && !UserInfo::where('parent_id', $agentId)->exists()) {
            return null;
        }
        if ($root && !in_array((int) $root->account_type, [1, 2], true)) {
            return null;
        }
        if ($root && (int) $root->account_type === 2) {
            return UserInfo::where('parent_id', $agentId)->exists() ? null : [];
        }

        $visited = [];

        try {
            return self::collectParentTreeScopeIds($agentId, $descendantType, $directOnly, 1, $visited);
        } catch (\InvalidArgumentException $exception) {
            return null;
        }
    }

    /**
     * 递归收集 parent_id 子树；visited 防环，depth 用于直属/非直属判定。
     *
     * @param int $agentId 当前节点用户 ID。
     * @param int|null $descendantType 限定 account_type。
     * @param bool|null $directOnly true 仅直属，false 仅非直属。
     * @param int $depth 当前深度（根为 1）。
     * @param array<int, bool> $visited 已访问节点（防循环引用）。
     * @return array<int, int> 子树用户 ID 列表。
     */
    private static function collectParentTreeScopeIds(int $agentId, int $descendantType = null, bool $directOnly = null, int $depth, array &$visited): array
    {
        if (isset($visited[$agentId])) {
            throw new \InvalidArgumentException('User hierarchy contains a cycle.');
        }

        $visited[$agentId] = true;
        $ids = [];
        $children = UserInfo::where('parent_id', $agentId)
            ->get(['user_id', 'account_type']);

        foreach ($children as $child) {
            if ($depth > UserInfo::MAX_HIERARCHY_DEPTH) {
                throw new \InvalidArgumentException('User hierarchy exceeds the safe depth limit.');
            }

            $childId = (int) $child->user_id;
            if (isset($visited[$childId])) {
                throw new \InvalidArgumentException('User hierarchy contains a cycle.');
            }

            $matchesType = $descendantType === null || (int) $child->account_type === $descendantType;
            $matchesDirect = $directOnly === null || ($directOnly ? $depth === 1 : $depth > 1);

            if ($matchesType && $matchesDirect) {
                $ids[] = $childId;
            }

            if ((int) $child->account_type === 1) {
                $ids = array_merge(
                    $ids,
                    self::collectParentTreeScopeIds($childId, $descendantType, $directOnly, $depth + 1, $visited)
                );
            } elseif ((int) $child->account_type !== 2) {
                throw new \InvalidArgumentException('User hierarchy contains an invalid account type.');
            }
        }

        return $ids;
    }

    /**
     * 解析请求中的目标用户 ID（兼容 userId / user_id 两种参数）。
     *
     * @param Request $request 当前请求对象。
     * @return int|null 空参数返回 null。
     */
    public static function requestedUserId(Request $request): ?int
    {
        $value = $request->input('userId', $request->input('user_id'));

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * 给查询追加用户权限过滤：只允许访问当前用户数据范围内的目标用户。
     *
     * 安全语义：请求指定了范围外用户 ID 时用 whereRaw('1 = 0') 强制查空（失败关闭），
     * 而不是放行或静默忽略，防止越权读取他人数据。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 目标查询。
     * @param Request $request 当前请求对象。
     * @param int $currentUserId 当前登录用户 ID。
     * @param string $column 用户 ID 所在列。
     * @param bool $includeDescendants 是否包含后代范围。
     * @return void
     */
    public static function applyAllowedUserFilter($query, Request $request, int $currentUserId, string $column = 'user_id', bool $includeDescendants = true): void
    {
        $allowedIds = $includeDescendants ? self::userScopeIds($currentUserId, true) : [$currentUserId];
        $requestedUserId = self::requestedUserId($request);

        if ($requestedUserId !== null) {
            if (in_array($requestedUserId, $allowedIds, true)) {
                $query->where($column, $requestedUserId);
                return;
            }

            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($column, $allowedIds);
    }

    /**
     * 按 created_at 时间戳区间过滤（兼容新旧日期参数）。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 目标查询。
     * @param Request $request 当前请求对象。
     * @param string $column 时间列名。
     * @return void
     */
    public static function applyCreatedAtFilter($query, Request $request, string $column = 'created_at'): void
    {
        $from = self::timestampFrom($request);
        $to = self::timestampTo($request);

        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }
    }

    /**
     * 按日期字符串区间过滤（open_time 等 DATETIME 列使用）。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 目标查询。
     * @param Request $request 当前请求对象。
     * @param string $column 时间列名。
     * @return void
     */
    public static function applyDateTimeFilter($query, Request $request, string $column): void
    {
        $from = self::dateFrom($request);
        $to = self::dateTo($request);

        if ($from) {
            $query->where($column, '>=', $from . ' 00:00:00');
        }
        if ($to) {
            $query->where($column, '<=', $to . ' 23:59:59');
        }
    }

    /**
     * 品种精确过滤：仅当请求显式携带 symbol 参数时生效。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 目标查询。
     * @param Request $request 当前请求对象。
     * @param string $column 品种列名。
     * @return void
     */
    public static function applySymbolFilter($query, Request $request, string $column = 'symbol'): void
    {
        if (!$request->filled('symbol')) {
            return;
        }

        $query->where($column, $request->input('symbol'));
    }

    /**
     * 把交易模型转换为旧前台表格字段别名行（新旧字段名并存，旧页面直接消费）。
     *
     * @param UserTrade $trade 交易记录。
     * @return array<string, mixed> 别名行。
     */
    public static function tradeAliasRow(UserTrade $trade): array
    {
        return [
            'id' => $trade->id,
            'ticket' => $trade->ticket,
            'login' => $trade->user_id,
            'user_id' => $trade->user_id,
            'symbol' => $trade->symbol,
            'digits' => $trade->digits,
            'cmd' => $trade->cmd,
            'cmd_text' => self::cmdText((int) $trade->cmd),
            'volume' => $trade->volume,
            'volume_lots' => self::lots($trade->volume),
            'sl' => $trade->stop_loss,
            'tp' => $trade->take_profit,
            'stop_loss' => $trade->stop_loss,
            'take_profit' => $trade->take_profit,
            'commission' => self::money($trade->commission),
            'profit' => self::money($trade->profit),
            'swaps' => self::money($trade->swaps),
            'open_price' => $trade->open_price,
            'close_price' => $trade->close_price,
            'open_time' => self::dateTime($trade->open_time),
            'close_time' => self::dateTime($trade->close_time),
            'comment' => $trade->comment,
            'orderComment' => $trade->comment,
            'modify_time' => self::dateTime($trade->modify_time),
            'reason' => $trade->reason,
        ];
    }

    /**
     * 把用户基础资料转换为旧前台字段别名行（含脱敏后的邮箱/手机号）。
     *
     * @param UserInfo|null $user 用户记录；null 返回空数组。
     * @return array<string, mixed> 别名行。
     */
    public static function userBasicAlias(UserInfo $user = null): array
    {
        if (!$user) {
            return [];
        }

        return [
            'userId' => $user->user_id,
            'user_id' => $user->user_id,
            'mt4_login' => $user->user_id,
            'userName' => $user->user_name,
            'user_name' => $user->user_name,
            'userSex' => ((int) $user->gender === 2) ? 'Female' : 'Male',
            'gender' => (int) $user->gender,
            'userEmail' => self::maskEmail($user->login ? (string) $user->login->email : ''),
            'email' => self::maskEmail($user->login ? (string) $user->login->email : ''),
            'last_login_ip' => $user->login ? $user->login->last_login_ip : '',
            'last_login_at' => $user->login ? self::dateTime($user->login->last_login_at) : '',
            'login_history_label' => __('common.detail'),
            'userPhone' => self::maskPhone((string) $user->phone),
            'phone' => self::maskPhone((string) $user->phone),
            'userGroupId' => $user->group_id,
            'group_id' => $user->group_id,
            'account_type' => $user->account_type,
            'parent_id' => $user->parent_id,
            // 旧大代理 Blade 使用下划线命名；保留别名避免迁移后列值为空。
            'sub_ag_parentId' => $user->parent_id,
            'userStatus' => $user->auth_status,
            'user_status' => $user->auth_status,
            'created_at' => self::dateTime($user->created_at),
            'rec_crt_date' => self::dateTime($user->created_at),
            'commprop' => (float) $user->comm_rate,
            'group_comm_prop' => (float) $user->comm_rate,
            'mt4_balance' => self::money($user->total_funds),
            'user_money' => self::money($user->total_funds),
            'cust_eqy' => self::money($user->equity),
            'mt4MarginLevel' => self::money($user->risk_ratio),
        ];
    }

    /**
     * 邮箱脱敏：保留 @ 前 2 个字符，其余用 *** 替代，域名保留。
     *
     * @param string $value 原始邮箱。
     * @return string 脱敏后邮箱；无 @ 或空串原样返回。
     */
    public static function maskEmail(string $value): string
    {
        if ($value === '' || strpos($value, '@') === false) {
            return $value;
        }

        [$name, $domain] = explode('@', $value, 2);
        $visible = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $visible . '***@' . $domain;
    }

    /**
     * 手机号脱敏：7 位以上保留前 3 后 4，其余只保留首位。
     *
     * @param string $value 原始手机号。
     * @return string 脱敏后手机号。
     */
    public static function maskPhone(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) >= 7
            ? substr($value, 0, 3) . '****' . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

    /**
     * 身份证脱敏：8 位以上保留前 4 后 4，其余只保留首位。
     *
     * @param string $value 原始身份证号。
     * @return string 脱敏后身份证号。
     */
    public static function maskIdCard(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) > 8
            ? substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4)
            : substr($value, 0, 1) . '***';
    }

    /**
     * 汇总单个用户的财务统计（可选包含后代范围）。
     *
     * @param UserInfo $user 当前用户。
     * @param Request $request 当前请求对象（日期/品种过滤）。
     * @param bool $includeDescendants 是否统计后代范围。
     * @return array<string, mixed> 财务汇总（含各品种成交量合计）。
     */
    public static function userFinancialSummary(UserInfo $user, Request $request, bool $includeDescendants = false): array
    {
        $ids = $includeDescendants ? self::userScopeIds((int) $user->user_id, true) : [(int) $user->user_id];

        return self::financialSummaryForIds($ids, $request);
    }

    /**
     * 汇总一批用户的财务统计：入金/出金/交易/佣金按时间过滤（created_at 与 open_time 两类列），
     * 交易额外支持品种过滤；成交手数按品种分组累计。
     *
     * @param array<int, int> $ids 用户 ID 列表。
     * @param Request $request 当前请求对象（日期/品种过滤）。
     * @return array<string, mixed> 财务汇总数组。
     */
    public static function financialSummaryForIds(array $ids, Request $request): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $fromTs = self::timestampFrom($request);
        $toTs = self::timestampTo($request);
        $fromDate = self::dateFrom($request);
        $toDate = self::dateTo($request);

        $depositQuery = DepositRecord::whereIn('user_id', $ids);
        $withdrawQuery = WithdrawRecord::whereIn('user_id', $ids);
        $tradeQuery = UserTrade::whereIn('user_id', $ids);
        $commissionQuery = CommissionRecord::whereIn('agent_id', $ids);

        if ($fromTs) {
            $depositQuery->where('created_at', '>=', $fromTs);
            $withdrawQuery->where('created_at', '>=', $fromTs);
            $commissionQuery->where('created_at', '>=', $fromTs);
        }
        if ($toTs) {
            $depositQuery->where('created_at', '<=', $toTs);
            $withdrawQuery->where('created_at', '<=', $toTs);
            $commissionQuery->where('created_at', '<=', $toTs);
        }
        if ($fromDate) {
            $tradeQuery->where('open_time', '>=', $fromDate . ' 00:00:00');
        }
        if ($toDate) {
            $tradeQuery->where('open_time', '<=', $toDate . ' 23:59:59');
        }
        self::applySymbolFilter($tradeQuery, $request);

        $tradeRows = (clone $tradeQuery)
            ->select('symbol', 'volume', 'profit', 'commission', 'swaps')
            ->get();
        $categoryTotals = self::categoryVolumeTotals($tradeRows);
        $equity = UserInfo::whereIn('user_id', $ids)->sum('equity');
        $summary = [
            'total_yuerj' => self::money($depositQuery->sum('amount')),
            'total_yuecj' => self::money($withdrawQuery->sum('apply_amount')),
            'total_net_worth' => self::money($equity),
            'total_comm' => self::money($tradeRows->sum('commission')),
            'total_profit' => self::money($tradeRows->sum('profit')),
            'total_volume' => self::lots($tradeRows->sum('volume')),
            'total_swaps' => self::money($tradeRows->sum('swaps')),
            'fy_money' => self::money($commissionQuery->sum('commission_amount')),
            'rj_money' => self::money((clone $depositQuery)->sum('amount')),
            'qk_money' => self::money((clone $withdrawQuery)->sum('apply_amount')),
        ];

        $summary['total_rebate'] = $summary['fy_money'];

        return array_merge($summary, $categoryTotals);
    }

    /**
     * 按旧大代理列表口径汇总代理本人的余额交易和当前资金。
     *
     * 旧列表的日期条件筛选代理开户时间，不筛选余额交易；返佣、入金和出金均从
     * user_trades 的 CMD=6 与固定 COMMENT 码读取，余额和净值读取 user_infos。
     *
     * @param array<int, int|string> $ids 已通过权限校验的代理用户 ID。
     * @return array<string, string> 旧大代理列表使用的两位小数字符串字段。
     */
    public static function legacyMt4AgentFinancialSummaryForIds(array $ids): array
    {
        $summaries = self::legacyMt4AgentFinancialSummariesByUserIds($ids);
        $totals = self::emptyLegacyMt4PositionTotals();
        foreach ($summaries as $summary) {
            foreach ($totals as $field => $value) {
                $totals[$field] = $value + (float) ($summary[$field] ?? 0);
            }
        }

        return self::formatLegacyMt4PositionTotals($totals, false);
    }

    /**
     * 批量汇总代理本人资金，避免列表按行重复查询 user_trades。
     *
     * @param array<int, int|string> $ids 已通过权限校验的代理用户 ID。
     * @return array<int, array<string, string>> 以代理 user_id 为键的资金汇总。
     */
    public static function legacyMt4AgentFinancialSummariesByUserIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $totals = [];
        foreach ($ids as $id) {
            $totals[$id] = self::emptyLegacyMt4PositionTotals();
        }
        if ($ids === []) {
            return [];
        }

        foreach (UserInfo::whereIn('user_id', $ids)->get(['user_id', 'total_funds', 'equity']) as $user) {
            $id = (int) $user->user_id;
            $totals[$id]['user_money'] = (float) $user->total_funds;
            $totals[$id]['cust_eqy'] = (float) $user->equity;
        }

        $trades = UserTrade::query()
            ->whereIn('user_id', $ids)
            ->where('cmd', 6)
            ->select(['user_id', 'profit', 'comment'])
            ->cursor();
        foreach ($trades as $trade) {
            $id = (int) $trade->user_id;
            if (isset($totals[$id])) {
                self::accumulateLegacyBalanceTrade($totals[$id], $trade);
            }
        }

        return array_map(static function (array $sum): array {
            return self::formatLegacyMt4PositionTotals($sum);
        }, $totals);
    }

    /**
     * 按旧 MT4 规则汇总指定用户范围的持仓与余额交易。
     *
     * 余额类入金、出金和返佣只认固定 COMMENT 码；交易统计只包含 CMD 0~5、
     * 已平仓且 margin_rate 非零的订单；品种分类只读取启用且未软删除的 symbol_prices。
     *
     * @param array<int, int|string> $ids 已通过权限校验的用户业务 ID。
     * @param Request $request 当前请求，支持新旧日期参数与 symbol 精确筛选。
     * @return array<string, string> 旧持仓汇总表使用的两位小数字符串字段。
     */
    public static function legacyMt4PositionSummaryForIds(array $ids, Request $request): array
    {
        $summaries = self::legacyMt4PositionSummariesForScopes(['summary' => $ids], $request);

        return $summaries['summary'];
    }

    /**
     * 按多个代理范围批量计算旧 MT4 持仓汇总。
     *
     * @param array<int|string, array<int, int|string>> $scopes 代理 ID 到可见用户 ID 的映射。
     * @param Request $request 当前请求，支持日期与 symbol 精确筛选。
     * @return array<int|string, array<string, string>> 以输入范围键为键的持仓汇总。
     */
    public static function legacyMt4PositionSummariesForScopes(array $scopes, Request $request): array
    {
        $totals = [];
        $scopeKeysByUserId = [];
        foreach ($scopes as $scopeKey => $ids) {
            $totals[$scopeKey] = self::emptyLegacyMt4PositionTotals();
            foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $id) {
                $scopeKeysByUserId[$id][] = $scopeKey;
            }
        }
        if ($totals === [] || $scopeKeysByUserId === []) {
            return array_map(static function (array $sum): array {
                return self::formatLegacyMt4PositionTotals($sum);
            }, $totals);
        }

        $userIds = array_keys($scopeKeysByUserId);
        foreach (UserInfo::whereIn('user_id', $userIds)->get(['user_id', 'total_funds', 'equity']) as $user) {
            foreach ($scopeKeysByUserId[(int) $user->user_id] as $scopeKey) {
                $totals[$scopeKey]['user_money'] += (float) $user->total_funds;
                $totals[$scopeKey]['cust_eqy'] += (float) $user->equity;
            }
        }

        $query = UserTrade::query()->whereIn('user_id', $userIds);
        $from = self::dateFrom($request) ?: '2024-01-01';
        $to = self::dateTo($request) ?: date('Y-m-d');
        $query->whereBetween('close_time', [$from . ' 00:00:00', $to . ' 23:59:59']);
        self::applySymbolFilter($query, $request);

        $groups = self::activePositionSymbolGroups();
        $trades = $query->select([
            'user_id',
            'cmd', 'symbol', 'volume', 'profit', 'commission', 'swaps',
            'close_time', 'margin_rate', 'comment',
        ])->cursor();
        foreach ($trades as $trade) {
            foreach ($scopeKeysByUserId[(int) $trade->user_id] as $scopeKey) {
                self::accumulateLegacyPositionTrade($totals[$scopeKey], $trade, $groups);
            }
        }

        return array_map(static function (array $sum): array {
            return self::formatLegacyMt4PositionTotals($sum);
        }, $totals);
    }

    /**
     * 汇总多条已格式化的旧 MT4 持仓行。
     *
     * @param array<int, array<string, mixed>> $rows 持仓汇总行。
     * @return array<string, string> 根级 footer 使用的合计行。
     */
    public static function legacyMt4PositionTotalRow(array $rows): array
    {
        $totals = self::emptyLegacyMt4PositionTotals();
        foreach ($rows as $row) {
            foreach ($totals as $field => $value) {
                $totals[$field] = $value + (float) ($row[$field] ?? 0);
            }
        }

        return array_merge([
            'user_id' => '',
            'sub_ag_id' => '',
            'user_name' => __('systemlanguage.total'),
            'sub_ag_name' => __('systemlanguage.total'),
        ], self::formatLegacyMt4PositionTotals($totals, false));
    }

    /** @return array<string, float> */
    private static function emptyLegacyMt4PositionTotals(): array
    {
        return [
            'total_yuerj' => 0.0,
            'total_yuecj' => 0.0,
            'total_rebate' => 0.0,
            'total_net_worth' => 0.0,
            'total_profit' => 0.0,
            'total_comm' => 0.0,
            'total_noble_metal' => 0.0,
            'total_for_exca' => 0.0,
            'total_crud_oil' => 0.0,
            'total_index' => 0.0,
            'total_currency' => 0.0,
            'total_stock' => 0.0,
            'total_volume' => 0.0,
            'total_swaps' => 0.0,
            'user_money' => 0.0,
            'cust_eqy' => 0.0,
        ];
    }

    /** @param array<string, float> $sum */
    private static function accumulateLegacyBalanceTrade(array &$sum, UserTrade $trade): void
    {
        $profit = (float) $trade->profit;
        $comment = (string) $trade->comment;

        if ($profit > 0 && self::matchesLegacyBalanceComment($comment, self::LEGACY_DEPOSIT_COMMENT_CODES)) {
            $sum['total_yuerj'] += $profit;
        }
        if ($profit < 0 && self::matchesLegacyBalanceComment($comment, self::LEGACY_WITHDRAWAL_COMMENT_CODES)) {
            $sum['total_yuecj'] += $profit;
        }
        if (self::matchesLegacyBalanceComment($comment, self::LEGACY_REBATE_COMMENT_CODES)) {
            $sum['total_rebate'] += $profit;
        }
    }

    /**
     * @param array<string, float> $sum
     * @param array<string, int> $groups
     */
    private static function accumulateLegacyPositionTrade(array &$sum, UserTrade $trade, array $groups): void
    {
        $cmd = (int) $trade->cmd;
        if ($cmd === 6) {
            self::accumulateLegacyBalanceTrade($sum, $trade);
            return;
        }
        if (!in_array($cmd, [0, 1, 2, 3, 4, 5], true)
            || (string) $trade->close_time <= '1970-01-01 00:00:00'
            || (float) $trade->margin_rate == 0.0) {
            return;
        }

        $volume = (float) $trade->volume;
        $sum['total_profit'] += (float) $trade->profit;
        $sum['total_comm'] += (float) $trade->commission;
        $sum['total_volume'] += $volume;
        if ((float) $trade->swaps < 0) {
            $sum['total_swaps'] += (float) $trade->swaps;
        }

        $groupId = $groups[strtoupper((string) $trade->symbol)] ?? 0;
        $field = [
            1 => 'total_noble_metal',
            2 => 'total_for_exca',
            3 => 'total_crud_oil',
            4 => 'total_index',
            5 => 'total_currency',
            6 => 'total_stock',
        ][$groupId] ?? null;
        if ($field !== null) {
            $sum[$field] += $volume;
        }
    }

    /**
     * @param array<string, float> $sum
     * @return array<string, string>
     */
    private static function formatLegacyMt4PositionTotals(array $sum, bool $rawVolume = true): array
    {
        $deposit = (float) $sum['total_yuerj'];
        $withdraw = (float) $sum['total_yuecj'];
        $rebate = (float) $sum['total_rebate'];
        $volumeDivisor = $rawVolume ? 100 : 1;

        return [
            'total_yuerj' => number_format($deposit, 2, '.', ''),
            'total_yuecj' => number_format($withdraw, 2, '.', ''),
            'total_rebate' => number_format($rebate, 2, '.', ''),
            'total_fy' => number_format($rebate, 2, '.', ''),
            'total_rj' => number_format($deposit, 2, '.', ''),
            'total_qk' => number_format($withdraw, 2, '.', ''),
            'fy_money' => number_format($rebate, 2, '.', ''),
            'rj_money' => number_format($deposit, 2, '.', ''),
            'qk_money' => number_format($withdraw, 2, '.', ''),
            'user_money' => number_format((float) $sum['user_money'], 2, '.', ''),
            'cust_eqy' => number_format((float) $sum['cust_eqy'], 2, '.', ''),
            'total_net_worth' => number_format($deposit - abs($withdraw), 2, '.', ''),
            'total_profit' => number_format((float) $sum['total_profit'], 2, '.', ''),
            'total_comm' => number_format(abs((float) $sum['total_comm']), 2, '.', ''),
            'total_noble_metal' => number_format(((float) $sum['total_noble_metal']) / $volumeDivisor, 2, '.', ''),
            'total_for_exca' => number_format(((float) $sum['total_for_exca']) / $volumeDivisor, 2, '.', ''),
            'total_crud_oil' => number_format(((float) $sum['total_crud_oil']) / $volumeDivisor, 2, '.', ''),
            'total_index' => number_format(((float) $sum['total_index']) / $volumeDivisor, 2, '.', ''),
            'total_currency' => number_format(((float) $sum['total_currency']) / $volumeDivisor, 2, '.', ''),
            'total_stock' => number_format(((float) $sum['total_stock']) / $volumeDivisor, 2, '.', ''),
            'total_volume' => number_format(((float) $sum['total_volume']) / $volumeDivisor, 2, '.', ''),
            'total_swaps' => number_format((float) $sum['total_swaps'], 2, '.', ''),
        ];
    }

    /** @return array<string, int> */
    private static function activePositionSymbolGroups(): array
    {
        if (!Schema::hasTable('symbol_prices')) {
            return [];
        }

        $symbolColumn = Schema::hasColumn('symbol_prices', 'sym_symbol') ? 'sym_symbol' : 'symbol';
        $groupColumn = Schema::hasColumn('symbol_prices', 'sym_grp_id') ? 'sym_grp_id' : 'group_id';
        $query = DB::table('symbol_prices')
            ->select($symbolColumn, $groupColumn)
            ->whereIn($groupColumn, [1, 2, 3, 4, 5, 6]);

        if (Schema::hasColumn('symbol_prices', 'voided')) {
            $query->where('voided', 1);
        } elseif (Schema::hasColumn('symbol_prices', 'status')) {
            $query->where('status', 1);
        }
        if (Schema::hasColumn('symbol_prices', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $groups = [];
        foreach ($query->get() as $row) {
            $symbol = strtoupper(trim((string) $row->{$symbolColumn}));
            if ($symbol !== '') {
                $groups[$symbol] = (int) $row->{$groupColumn};
            }
        }

        return $groups;
    }

    /**
     * @param array<int, string> $codes
     */
    private static function matchesLegacyBalanceComment(string $comment, array $codes): bool
    {
        if ($comment === '' || $codes === []) {
            return false;
        }

        $pattern = '/' . implode('|', array_map(static function (string $code): string {
            return preg_quote($code, '/');
        }, $codes)) . '/i';

        return preg_match($pattern, $comment) === 1;
    }

    /**
     * 按品种分类累计成交量：优先取 symbol_prices 配置的 group_id，未配置时按品种名模式兜底归类。
     *
     * @param \Illuminate\Support\Collection|array $trades 交易行集合（含 symbol/volume 字段）。
     * @return array<string, float> 分类成交量（total_noble_metal 等六个桶）。
     */
    public static function categoryVolumeTotals($trades): array
    {
        $symbols = $trades->pluck('symbol')->filter()->unique()->values()->all();
        $configuredGroups = [];

        if ($symbols) {
            $configuredGroups = DB::table('symbol_prices')
                ->whereIn('symbol', $symbols)
                ->select('symbol', DB::raw('MAX(group_id) as group_id'))
                ->groupBy('symbol')
                ->pluck('group_id', 'symbol')
                ->all();
        }

        $totals = [
            'total_noble_metal' => 0.0,
            'total_for_exca' => 0.0,
            'total_crud_oil' => 0.0,
            'total_index' => 0.0,
            'total_currency' => 0.0,
            'total_stock' => 0.0,
        ];

        foreach ($trades as $trade) {
            $volume = ((float) $trade->volume) / 100;
            $groupId = $configuredGroups[$trade->symbol] ?? null;
            $bucket = self::bucketForSymbol((string) $trade->symbol, $groupId ? (int) $groupId : null);
            $totals[$bucket] += $volume;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }

    /**
     * 将品种映射到统计桶：group_id 1~6 直接命中；否则按名称模式
     * （贵金属/原油/指数/外汇对/加密币）推断，匹配不到归入股票桶。
     *
     * @param string $symbol 品种名。
     * @param int|null $groupId symbol_prices 配置的分组。
     * @return string 统计桶键名。
     */
    private static function bucketForSymbol(string $symbol, int $groupId = null): string
    {
        if ($groupId === 1) return 'total_noble_metal';
        if ($groupId === 2) return 'total_for_exca';
        if ($groupId === 3) return 'total_crud_oil';
        if ($groupId === 4) return 'total_index';
        if ($groupId === 5) return 'total_currency';
        if ($groupId === 6) return 'total_stock';

        $upper = strtoupper($symbol);
        if (strpos($upper, 'XAU') !== false || strpos($upper, 'XAG') !== false || strpos($upper, 'GOLD') !== false || strpos($upper, 'SILVER') !== false) {
            return 'total_noble_metal';
        }
        if (strpos($upper, 'OIL') !== false || strpos($upper, 'WTI') !== false || strpos($upper, 'BRENT') !== false || strpos($upper, 'XTI') !== false) {
            return 'total_crud_oil';
        }
        if (preg_match('/(US30|NAS|SPX|DAX|GER|UK100|JP225|HK50|INDEX)/', $upper)) {
            return 'total_index';
        }
        if (preg_match('/^[A-Z]{6}$/', $upper)) {
            return 'total_for_exca';
        }
        if (preg_match('/(BTC|ETH|USDT|CRYPTO)/', $upper)) {
            return 'total_currency';
        }

        return 'total_stock';
    }

    /**
     * 指令码转文本（MT4 cmd 枚举：Buy/Sell/挂单）。
     *
     * @param int $cmd MT4 cmd 值。
     * @return string 文本或原数字。
     */
    public static function cmdText(int $cmd): string
    {
        $map = [
            0 => 'Buy',
            1 => 'Sell',
            2 => 'Buy Limit',
            3 => 'Sell Limit',
            4 => 'Buy Stop',
            5 => 'Sell Stop',
        ];

        return $map[$cmd] ?? (string) $cmd;
    }

    /**
     * 手数换算：volume/100（MT4 以 100 为单位存储）保留两位。
     *
     * @param mixed $volume 原始成交量。
     * @return float 手数。
     */
    public static function lots($volume): float
    {
        return round(((float) $volume) / 100, 2);
    }

    /**
     * 金额统一为两位小数浮点（仅展示用，计算应走 BCMath）。
     *
     * @param mixed $value 原始金额。
     * @return float 两位小数金额。
     */
    public static function money($value): float
    {
        return round((float) $value, 2);
    }

    /**
     * 前台银行卡号脱敏：保留前 4 位与后 4 位，中间固定替换为 `****`。
     *
     * 为什么必须脱敏：项目1 前台流水与出金列表在 CustomerFlowController.php:308
     * 以 `substr($no, 0, 4) . '****' . substr($no, -4)` 输出卡号，前台**从不下发完整卡号**。
     * 新项目若直接返回 `bank_no` 原文，等于把完整卡号暴露到前端响应与浏览器缓存中，
     * 属于数据最小化原则的倒退；即便是用户查看自己的卡号，也没有下发全量的必要。
     *
     * 边界处理：
     * - 长度不足 8 位时前 4 与后 4 会重叠，拼接结果反而可能泄露比原文更多的相邻位，
     *   因此整体替换为 `****`，宁可信息更少也不产生误导；
     * - null 与空串统一返回空串，与旧前台「无卡号则该列为空」的表现一致，
     *   不返回 `****` 以免让用户误以为已绑卡。
     *
     * @param mixed $bankNo 原始银行卡号，可能为 null、空串或任意长度数字串。
     * @return string 脱敏后的卡号；无卡号时为空串。
     */
    public static function maskBankNo($bankNo): string
    {
        $raw = trim((string) ($bankNo ?? ''));

        if ($raw === '') {
            return '';
        }

        // 用 mb_* 处理，避免卡号字段被写入非 ASCII 字符时 substr 截断出半个字符。
        if (mb_strlen($raw, 'UTF-8') < 8) {
            return '****';
        }

        return mb_substr($raw, 0, 4, 'UTF-8') . '****' . mb_substr($raw, -4, null, 'UTF-8');
    }

    /**
     * 时间格式化：时间戳或 '1970-01-01 00:00:00' 空值统一返回空串。
     *
     * @param mixed $value 时间戳或时间字符串。
     * @return string 'Y-m-d H:i:s' 或空串。
     */
    public static function dateTime($value): string
    {
        if (!$value) {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '';
        }

        $value = (string) $value;
        if ($value === '1970-01-01 00:00:00') {
            return '';
        }

        return $value;
    }

    /**
     * 包装分页结果为旧前台表格期望的结构：同时输出 list、totalRow 与 summary。
     *
     * @param mixed $paginator 已分页的列表结果。
     * @param array<string, mixed> $totalRow 合计行数据。
     * @param array<string, mixed> $extra 需要合并进响应的额外字段。
     * @return array<string, mixed> 旧前台可直接消费的列表响应结构。
     */
    public static function paginatedListResponse($paginator, array $totalRow = [], array $extra = []): array
    {
        $payload = array_merge($extra, [
            'list' => $paginator,
            'totalRow' => $totalRow,
            'summary' => $totalRow,
        ]);

        return $payload;
    }

    /**
     * 汇总一组用户的财务合计行：在财务汇总基础上补 user 标记与总资金/净值。
     *
     * @param array<int, int> $userIds 用户 ID 列表。
     * @param Request $request 当前请求对象（日期/品种过滤）。
     * @param string $labelKey 合计行标识键（默认 mt4_login）。
     * @return array<string, mixed> 合计行。
     */
    public static function financialTotalRowForUserIds(array $userIds, Request $request, string $labelKey = 'mt4_login'): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return [$labelKey => 'total'];
        }

        $summary = self::financialSummaryForIds($userIds, $request);
        $summary[$labelKey] = 'total';
        $summary['user_id'] = 'total';
        $summary['user_name'] = '';
        $summary['mt4_balance'] = self::money(UserInfo::whereIn('user_id', $userIds)->sum('total_funds'));
        $summary['cust_eqy'] = self::money(UserInfo::whereIn('user_id', $userIds)->sum('equity'));

        return $summary;
    }

    /**
     * 入金列表合计行：金额与实收（缺失实收时按申请金额）合计。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 入金查询。
     * @return array<string, mixed> 合计行。
     */
    public static function depositTotalRow($query): array
    {
        $row = self::aggregateQuery($query)
            ->selectRaw('COALESCE(SUM(amount), 0) as amount_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN actual_amount IS NULL OR actual_amount = 0 THEN amount ELSE actual_amount END), 0) as actual_sum')
            ->first();

        return [
            'order_no' => 'total',
            'userId' => 'total',
            'amount' => self::money($row->amount_sum ?? 0),
            'actual_amount' => self::money($row->actual_sum ?? 0),
            'depositActProfit' => self::money($row->actual_sum ?? 0),
            'directProfit' => self::money($row->actual_sum ?? 0),
        ];
    }

    /**
     * 出金列表合计行：申请/实扣/手续费合计。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 出金查询。
     * @return array<string, mixed> 合计行。
     */
    public static function withdrawTotalRow($query): array
    {
        $row = self::aggregateQuery($query)
            ->selectRaw('COALESCE(SUM(apply_amount), 0) as apply_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN actual_amount IS NULL OR actual_amount = 0 THEN apply_amount ELSE actual_amount END), 0) as actual_sum')
            ->selectRaw('COALESCE(SUM(fee), 0) as fee_sum')
            ->first();

        return [
            'order_no' => 'total',
            'userId' => 'total',
            'apply_amount' => self::money($row->apply_sum ?? 0),
            'actual_amount' => self::money($row->actual_sum ?? 0),
            'fee' => self::money($row->fee_sum ?? 0),
            'withdrawalActProfit' => self::money($row->actual_sum ?? 0),
            'applyamount' => self::money($row->apply_sum ?? 0),
            'actdraw' => self::money($row->actual_sum ?? 0),
            'drawpoundage' => self::money($row->fee_sum ?? 0),
            'directdrawalActProfit' => self::money($row->actual_sum ?? 0),
        ];
    }

    /**
     * 佣金列表合计行：应发/退回/实发/代理利润/代理手数合计。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 佣金查询。
     * @return array<string, mixed> 合计行。
     */
    public static function commissionTotalRow($query): array
    {
        $row = self::aggregateQuery($query)
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_sum')
            ->selectRaw('COALESCE(SUM(returned_amount), 0) as returned_sum')
            ->selectRaw('COALESCE(SUM(real_amount), 0) as real_sum')
            ->selectRaw('COALESCE(SUM(agent_profit), 0) as profit_sum')
            ->selectRaw('COALESCE(SUM(agent_volume), 0) as volume_sum')
            ->first();

        return [
            'unique_id' => 'total',
            'agent_id' => 'total',
            'commission_amount' => self::money($row->commission_sum ?? 0),
            'returned_amount' => self::money($row->returned_sum ?? 0),
            'real_amount' => self::money($row->real_sum ?? 0),
            'profit' => self::money($row->commission_sum ?? 0),
            'agent_profit' => self::money($row->profit_sum ?? 0),
            'agent_volume' => self::lots($row->volume_sum ?? 0),
        ];
    }

    /**
     * 充值状态文案：兼容新旧状态码（2/02 已完成，3/03/09 已拒绝，5/05 已退款）。
     *
     * @param mixed $status 原始状态码。
     * @return string 前端语言包文案。
     */
    public static function depositStatusText($status): string
    {
        $status = (string) $status;

        if ($status === '02' || $status === '2') {
            return __('front.status_completed');
        }
        if ($status === '05' || $status === '5') {
            return __('front.status_refunded');
        }
        if ($status === '03' || $status === '3' || $status === '09' || $status === '9') {
            return __('front.status_rejected');
        }

        return __('front.status_unpaid');
    }

    /**
     * 批量计算多个代理的下级统计（getSubAgentStats 的批量等价版）。
     *
     * 背景：
     * - 原 getSubAgentStats 对每个代理执行 4 次 parent_id 树递归，列表页每行调用一次会形成 N+1。
     * - 本方法一次性构建 parent_id 子树并在内存中按代理分组统计，把查询次数降为常数级。
     *
     * 口径说明：
     * - 与 getSubAgentStats 完全一致：direct=直属子（depth=1），indirect=非直属；
     *   account_type=1 代理、2 客户，普通客户不会继续向下展开。
     *
     * @param array<int, int> $agentIds 页面内代理 user_id 列表。
     * @return array<int, array<string, int>> 代理 ID => 六项统计（direct/indirect/total）。
     */
    public static function batchSubAgentStats(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_map('intval', $agentIds)));
        if (empty($agentIds)) {
            return [];
        }

        $sets = self::batchDescendantSets($agentIds);
        $result = [];
        foreach ($agentIds as $id) {
            $directAgents = count($sets[$id]['direct_agents']);
            $allAgents = count($sets[$id]['all_agents']);
            $directCustomers = count($sets[$id]['direct_customers']);
            $allCustomers = count($sets[$id]['all_customers']);

            $result[$id] = [
                'direct_agents' => $directAgents,
                'indirect_agents' => $allAgents - $directAgents,
                'total_agents' => $allAgents,
                'direct_customers' => $directCustomers,
                'indirect_customers' => $allCustomers - $directCustomers,
                'total_customers' => $allCustomers,
            ];
        }

        return $result;
    }

    /**
     * 批量计算多个代理的交易统计（getAgentStats 的批量等价版，无日期过滤场景）。
     *
     * 背景：
     * - 原 getAgentStats 每代理执行 1 次 userScopeIds + 3 次聚合（trades/commission/infos），
     *   列表页 N+1 明显。本方法一次查询页内全部代理的后代交易、佣金与新增用户，内存分组。
     *
     * 口径说明：
     * - 与 getAgentStats(agentId) 一致：descendantIds=userScopeIds(agentId,false)（后代不含自己）；
     *   total_volume/total_profit 为后代交易的 SUM；active_users 为有交易的去重后代数；
     *   total_commission 为该代理自己的佣金 SUM；new_registrations 为后代 user_infos 计数。
     * - 共享后代（同时属于多个代理树）按原逻辑对每个代理独立重复计入。
     *
     * @param array<int, int> $agentIds 页面内代理 user_id 列表。
     * @return array<int, array<string, float|int>> 代理 ID => 五项统计。
     */
    public static function batchAgentStats(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_map('intval', $agentIds)));
        if (empty($agentIds)) {
            return [];
        }

        $sets = self::batchDescendantSets($agentIds);

        // 初始化结果骨架，保证无数据的代理也返回全 0 结构。
        $result = [];
        foreach ($agentIds as $id) {
            $result[$id] = [
                'total_volume' => 0,
                'total_profit' => 0,
                'total_commission' => 0,
                'active_users' => 0,
                'new_registrations' => 0,
            ];
        }

        // 后代并集与归属映射（user_id => 所属代理集合，允许一对多）。
        $allDescendantIds = [];
        $belong = [];
        foreach ($sets as $id => $set) {
            foreach (array_keys($set['all_descendants']) as $did) {
                $allDescendantIds[$did] = true;
                $belong[$did][$id] = true;
            }
        }
        $allDescendantIds = array_keys($allDescendantIds);

        if (! empty($allDescendantIds)) {
            // 后代交易聚合：一次查询，内存按代理累加。
            $trades = UserTrade::whereIn('user_id', $allDescendantIds)
                ->get(['user_id', 'volume', 'profit']);
            $activeByAgent = [];
            foreach ($trades as $trade) {
                $uid = (int) $trade->user_id;
                foreach (array_keys($belong[$uid] ?? []) as $aid) {
                    $result[$aid]['total_volume'] += (float) $trade->volume;
                    $result[$aid]['total_profit'] += (float) $trade->profit;
                    $activeByAgent[$aid][$uid] = true;
                }
            }
            foreach ($activeByAgent as $aid => $users) {
                $result[$aid]['active_users'] = count($users);
            }

            // 后代新增用户计数（无日期过滤场景）。
            $newByAgent = [];
            foreach (UserInfo::whereIn('user_id', $allDescendantIds)->pluck('user_id') as $uid) {
                foreach (array_keys($belong[(int) $uid] ?? []) as $aid) {
                    $newByAgent[$aid][(int) $uid] = true;
                }
            }
            foreach ($newByAgent as $aid => $users) {
                $result[$aid]['new_registrations'] = count($users);
            }
        }

        // 各代理自己的佣金合计：一次 GROUP BY。
        foreach (CommissionRecord::whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, SUM(commission_amount) as total')
            ->groupBy('agent_id')->get() as $row) {
            $result[(int) $row->agent_id]['total_commission'] = (float) ($row->total ?? 0);
        }

        return $result;
    }

    /**
     * 批量计算多个代理的资金汇总（userFinancialSummary 含后代口径的批量等价版）。
     *
     * 背景：
     * - 原 userFinancialSummary(includeDescendants=true) 每代理执行 1 次 userScopeIds +
     *   6+ 次聚合（入金/出金/交易行/净值/佣金），列表页 N+1 明显。
     * - 本方法对页内全部代理一次性查询各资金表，内存按代理归属分组并应用与
     *   financialSummaryForIds 完全相同的过滤（created_at 时间戳、open_time 日期、
     *   symbol 过滤）与格式化（money/lots/categoryVolumeTotals）。
     *
     * 口径说明：
     * - 每个代理的统计范围为 userScopeIds(agentId, true)（后代 + 自己）；
     *   共享后代按原逻辑对每个代理独立重复计入。
     *
     * @param array<int, int> $agentIds 页面内代理 user_id 列表。
     * @param Request $request 携带与单查相同的过滤参数（时间、symbol 等）。
     * @return array<int, array<string, float>> 代理 ID => 资金汇总（含分类成交量）。
     */
    public static function batchFinancialSummaryForAgents(array $agentIds, Request $request): array
    {
        $agentIds = array_values(array_unique(array_map('intval', $agentIds)));
        if (empty($agentIds)) {
            return [];
        }

        $sets = self::batchDescendantSets($agentIds);

        // 每代理统计范围 ids（后代 + 自己）与全局并集、归属映射。
        $idsByAgent = [];
        $allIds = [];
        $belong = [];
        foreach ($agentIds as $id) {
            $ids = array_keys($sets[$id]['all_descendants']);
            $ids[] = $id;
            $idsByAgent[$id] = array_values(array_unique($ids));
            foreach ($idsByAgent[$id] as $uid) {
                $allIds[$uid] = true;
                $belong[$uid][$id] = true;
            }
        }
        $allIds = array_keys($allIds);

        // 结果骨架：字段与原 financialSummaryForIds 对齐。
        $result = [];
        foreach ($agentIds as $id) {
            $result[$id] = [
                'total_yuerj' => 0,
                'total_yuecj' => 0,
                'total_net_worth' => 0,
                'total_comm' => 0,
                'total_profit' => 0,
                'total_volume' => 0,
                'total_swaps' => 0,
                'fy_money' => 0,
                'rj_money' => 0,
                'qk_money' => 0,
                'total_rebate' => 0,
                'total_noble_metal' => 0.0,
                'total_for_exca' => 0.0,
                'total_crud_oil' => 0.0,
                'total_index' => 0.0,
                'total_currency' => 0.0,
                'total_stock' => 0.0,
            ];
        }

        if (! empty($allIds)) {
            $fromTs = self::timestampFrom($request);
            $toTs = self::timestampTo($request);
            $fromDate = self::dateFrom($request);
            $toDate = self::dateTo($request);

            // 入金：一次查询，内存按代理累加（total_yuerj 与 rj_money 同口径）。
            $depositQuery = DepositRecord::whereIn('user_id', $allIds);
            if ($fromTs) {
                $depositQuery->where('created_at', '>=', $fromTs);
            }
            if ($toTs) {
                $depositQuery->where('created_at', '<=', $toTs);
            }
            foreach ($depositQuery->get(['user_id', 'amount']) as $row) {
                $amount = (float) $row->amount;
                foreach (array_keys($belong[(int) $row->user_id] ?? []) as $aid) {
                    $result[$aid]['total_yuerj'] += $amount;
                    $result[$aid]['rj_money'] += $amount;
                }
            }

            // 出金：total_yuecj 与 qk_money 同口径。
            $withdrawQuery = WithdrawRecord::whereIn('user_id', $allIds);
            if ($fromTs) {
                $withdrawQuery->where('created_at', '>=', $fromTs);
            }
            if ($toTs) {
                $withdrawQuery->where('created_at', '<=', $toTs);
            }
            foreach ($withdrawQuery->get(['user_id', 'apply_amount']) as $row) {
                $amount = (float) $row->apply_amount;
                foreach (array_keys($belong[(int) $row->user_id] ?? []) as $aid) {
                    $result[$aid]['total_yuecj'] += $amount;
                    $result[$aid]['qk_money'] += $amount;
                }
            }

            // 交易：open_time 日期与 symbol 过滤与原单查一致；分类成交量按代理分组计算。
            $tradeQuery = UserTrade::whereIn('user_id', $allIds);
            if ($fromDate) {
                $tradeQuery->where('open_time', '>=', $fromDate . ' 00:00:00');
            }
            if ($toDate) {
                $tradeQuery->where('open_time', '<=', $toDate . ' 23:59:59');
            }
            self::applySymbolFilter($tradeQuery, $request);
            $tradeRows = $tradeQuery->get(['user_id', 'symbol', 'volume', 'profit', 'commission', 'swaps']);
            $rowsByAgent = [];
            foreach ($tradeRows as $trade) {
                $uid = (int) $trade->user_id;
                foreach (array_keys($belong[$uid] ?? []) as $aid) {
                    $rowsByAgent[$aid][] = $trade;
                    $result[$aid]['total_comm'] += (float) $trade->commission;
                    $result[$aid]['total_profit'] += (float) $trade->profit;
                    $result[$aid]['total_volume'] += (float) $trade->volume;
                    $result[$aid]['total_swaps'] += (float) $trade->swaps;
                }
            }
            foreach ($rowsByAgent as $aid => $rows) {
                foreach (self::categoryVolumeTotals(collect($rows)) as $key => $value) {
                    $result[$aid][$key] = $value;
                }
            }

            // 净值：后代 user_infos.equity 合计。
            foreach (UserInfo::whereIn('user_id', $allIds)->get(['user_id', 'equity']) as $row) {
                foreach (array_keys($belong[(int) $row->user_id] ?? []) as $aid) {
                    $result[$aid]['total_net_worth'] += (float) $row->equity;
                }
            }
        }

        // 佣金：agent_id 在任一统计范围内（后代+自己），与单查 whereIn 口径一致。
        $commissionQuery = CommissionRecord::whereIn('agent_id', $allIds);
        if (! empty($allIds)) {
            if ($fromTs = self::timestampFrom($request)) {
                $commissionQuery->where('created_at', '>=', $fromTs);
            }
            if ($toTs = self::timestampTo($request)) {
                $commissionQuery->where('created_at', '<=', $toTs);
            }
            foreach ($commissionQuery->selectRaw('agent_id, SUM(commission_amount) as total')
                ->groupBy('agent_id')->get() as $row) {
                foreach (array_keys($belong[(int) $row->agent_id] ?? []) as $aid) {
                    $result[$aid]['fy_money'] += (float) ($row->total ?? 0);
                }
            }
        }

        // 与原 financialSummaryForIds 一致的格式化：金额两位小数、手数 /100、返佣=佣金。
        foreach ($agentIds as $id) {
            $result[$id]['total_yuerj'] = self::money($result[$id]['total_yuerj']);
            $result[$id]['total_yuecj'] = self::money($result[$id]['total_yuecj']);
            $result[$id]['total_net_worth'] = self::money($result[$id]['total_net_worth']);
            $result[$id]['total_comm'] = self::money($result[$id]['total_comm']);
            $result[$id]['total_profit'] = self::money($result[$id]['total_profit']);
            $result[$id]['total_volume'] = self::lots($result[$id]['total_volume']);
            $result[$id]['total_swaps'] = self::money($result[$id]['total_swaps']);
            $result[$id]['fy_money'] = self::money($result[$id]['fy_money']);
            $result[$id]['rj_money'] = self::money($result[$id]['rj_money']);
            $result[$id]['qk_money'] = self::money($result[$id]['qk_money']);
            $result[$id]['total_rebate'] = $result[$id]['fy_money'];
        }

        return $result;
    }

    /**
     * 按 parent_id 树批量 BFS 构建多个代理的后代集合。
     *
     * 口径说明：
     * - 与 userScopeIds 一致，只认 parent_id；direct 集合按 depth==1（直属）区分。
     *
     * @param array<int, int> $agentIds 代理 user_id 列表。
     * @return array<int, array<string, array<int, bool>>> 代理 ID => 五类集合
     *         （direct_agents/all_agents/direct_customers/all_customers/all_descendants）。
     */
    private static function batchDescendantSets(array $agentIds): array
    {
        $sets = [];
        foreach ($agentIds as $id) {
            $sets[$id] = [
                'direct_agents' => [],
                'all_agents' => [],
                'direct_customers' => [],
                'all_customers' => [],
                'all_descendants' => [],
            ];
        }

        $validAgentIds = UserInfo::whereIn('user_id', $agentIds)
            ->where('account_type', 1)
            ->pluck('user_id')
            ->map(function ($userId) {
                return (int) $userId;
            })
            ->all();

        // parent_id 树批量 BFS：一次性构建 childrenMap，避免逐代理逐层查询。
        $childrenMap = [];
        $frontier = $validAgentIds;
        $visitedGlobal = [];
        while (! empty($frontier)) {
            $children = UserInfo::whereIn('parent_id', $frontier)
                ->get(['parent_id', 'user_id', 'account_type']);
            if ($children->isEmpty()) {
                break;
            }
            $next = [];
            foreach ($children as $child) {
                $pid = (int) $child->parent_id;
                $childrenMap[$pid][] = [
                    'user_id' => (int) $child->user_id,
                    'account_type' => (int) $child->account_type,
                ];
                $uid = (int) $child->user_id;
                if ((int) $child->account_type === 1 && ! isset($visitedGlobal[$uid])) {
                    $visitedGlobal[$uid] = true;
                    $next[] = $uid;
                }
            }
            $frontier = $next;
        }

        // 3. 每个代理按 collectParentTreeScopeIds 语义（visited 独立）展开并合并。
        foreach ($validAgentIds as $id) {
            $visited = [];
            try {
                $treeNodes = self::collectParentTreeFromMap($id, $childrenMap, 1, $visited);
            } catch (\InvalidArgumentException $exception) {
                continue;
            }
            foreach ($treeNodes as $node) {
                $did = $node['user_id'];
                $sets[$id]['all_descendants'][$did] = true;
                if ($node['account_type'] === 1) {
                    $sets[$id]['all_agents'][$did] = true;
                    if ($node['depth'] === 1) {
                        $sets[$id]['direct_agents'][$did] = true;
                    }
                } else {
                    $sets[$id]['all_customers'][$did] = true;
                    if ($node['depth'] === 1) {
                        $sets[$id]['direct_customers'][$did] = true;
                    }
                }
            }
        }

        return $sets;
    }

    /**
     * 基于预构建的 childrenMap 在内存中展开某代理的 parent_id 树（含 visited 防环）。
     *
     * @param int $agentId 起始代理 user_id。
     * @param array<int, array<int, array{user_id: int, account_type: int}>> $childrenMap 父=>子列表。
     * @param int $depth 当前深度（根子节点为 1）。
     * @param array<int, bool> $visited 已访问集合（引用传递，防环）。
     * @return array<int, array{user_id: int, account_type: int, depth: int}> 展开节点列表。
     */
    private static function collectParentTreeFromMap(int $agentId, array $childrenMap, int $depth, array &$visited): array
    {
        if (isset($visited[$agentId])) {
            throw new \InvalidArgumentException('User hierarchy contains a cycle.');
        }
        $visited[$agentId] = true;

        $out = [];
        foreach ($childrenMap[$agentId] ?? [] as $child) {
            if ($depth > UserInfo::MAX_HIERARCHY_DEPTH) {
                throw new \InvalidArgumentException('User hierarchy exceeds the safe depth limit.');
            }

            $childId = $child['user_id'];
            if (isset($visited[$childId])) {
                throw new \InvalidArgumentException('User hierarchy contains a cycle.');
            }
            $out[] = [
                'user_id' => $childId,
                'account_type' => $child['account_type'],
                'depth' => $depth,
            ];
            if ($child['account_type'] === 1) {
                $out = array_merge($out, self::collectParentTreeFromMap($childId, $childrenMap, $depth + 1, $visited));
            } elseif ($child['account_type'] !== 2) {
                throw new \InvalidArgumentException('User hierarchy contains an invalid account type.');
            }
        }

        return $out;
    }

    /**
     * 出金审核状态文案：2 已通过、3 已拒绝，其余按待处理。
     *
     * @param mixed $status 原始状态码。
     * @return string 前端语言包文案。
     */
    public static function withdrawStatusText($status): string
    {
        $status = (string) $status;

        if ($status === '2') {
            return __('front.status_approved');
        }
        if ($status === '3') {
            return __('front.status_rejected');
        }

        return __('front.status_pending');
    }

    /**
     * 出金结算（扣款）状态文案：按 funding_status 枚举映射，未识别状态按未知展示。
     *
     * @param mixed $status funding_status 原始值。
     * @return string 前端语言包文案。
     */
    public static function withdrawFundingStatusText($status): string
    {
        $status = trim((string) $status);
        $map = [
            'pending' => 'front.funding_status_pending',
            'processing' => 'front.funding_status_processing',
            'retryable' => 'front.funding_status_retryable',
            'debited' => 'front.funding_status_debited',
            'unknown' => 'front.funding_status_unknown',
            'rejected' => 'front.funding_status_rejected',
            'cancelled' => 'front.funding_status_cancelled',
            'refund_pending' => 'front.funding_status_refund_pending',
            'refunded' => 'front.funding_status_refunded',
            'refund_unknown' => 'front.funding_status_refund_unknown',
            'refund_rejected' => 'front.funding_status_refund_rejected',
            'completed' => 'front.funding_status_completed',
        ];

        if (isset($map[$status])) {
            return __($map[$status]);
        }

        return __('front.funding_status_unknown');
    }

    /**
     * 克隆查询并剥离聚合无关子句（columns/orders/limit/offset/groups/havings），
     * 供合计行复用一个查询骨架而不相互干扰。
     *
     * @param \Illuminate\Database\Eloquent\Builder|mixed $query 原查询。
     * @return mixed 剥离后的查询克隆。
     */
    private static function aggregateQuery($query)
    {
        $clone = clone $query;
        $base = method_exists($clone, 'getQuery') ? $clone->getQuery() : $clone;

        foreach (['columns', 'orders', 'limit', 'offset', 'groups', 'havings'] as $property) {
            if (property_exists($base, $property)) {
                $base->{$property} = null;
            }
        }

        return $clone;
    }

    /**
     * 生成持仓/历史订单列表合计行（旧 Layui totalRow 格式）。
     *
     * @param mixed $query 订单查询对象。
     * @return array<string, mixed> 手数、手续费、盈亏、库存费合计行。
     */
    public static function tradeOrderTotalRow($query): array
    {
        $rows = (clone $query)->get(['volume', 'commission', 'profit', 'swaps']);

        return [
            'ticket' => 'total',
            'symbol' => 'total',
            'volume' => self::lots($rows->sum('volume')),
            'volume_lots' => self::lots($rows->sum('volume')),
            'commission' => self::money($rows->sum('commission')),
            'profit' => self::money($rows->sum('profit')),
            'swaps' => self::money($rows->sum('swaps')),
        ];
    }

    /**
     * 生成实时返佣列表合计行（旧 Layui totalRow 格式）。
     *
     * @param mixed $query 已过滤的交易查询对象。
     * @return array<string, mixed> 盈亏、总佣金、总手数合计行。
     */
    public static function rebateTotalRow($query): array
    {
        return [
            'ticket' => 'total',
            'profit' => self::money((clone $query)->sum('profit')),
            'total_commission' => self::money((clone $query)->sum('commission_agent')),
            'total_volume' => self::lots((clone $query)->sum('volume')),
        ];
    }
}
