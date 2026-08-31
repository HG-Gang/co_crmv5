<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 03:27
 */

/**
 * 旧后台代理统计列表服务。
 *
 * 文件功能：
 * - 从当前 schema（user_infos / user_logins / user_trades / symbol_prices / agent_descendants / big_agents）构建旧后台代理相关表格的响应载荷。
 * - 提供代理列表（agentList）、代理审核列表（agentExamineList）、大代理统计列表（bigAgentList）与大代理下级列表（bigAgentSubList）四类查询。
 * - 统一套用 AdminDataScopeService 数据范围过滤，保证不同管理员只能看到自己权限内的代理与客户数据。
 *
 * 适用场景：
 * - 旧后台“代理管理 / 代理列表 / 代理审核 / 大代理统计”页面迁移到新项目后，接口层直接复用本服务生成旧 Layui 表格兼容载荷。
 *
 * 入参例子：
 * - agentList(request: {page, rows, searchtype: 'autoSearch', userId, userstatus, username}, admin, includeV2Aliases=false)
 * - agentExamineList(request: {page, rows, startdate, enddate, userId}, admin)
 * - bigAgentList(request: {big_id, startdate, enddate}, admin)
 * - bigAgentSubList(request: {big_id, user_pid}, admin)
 *
 * 返回值：
 * - 统一返回 ['rows' => 行数组, 'total' => 总数, 'footer' => [合计行]]；
 * - agentList 的 includeV2Aliases 为 true 时额外附带 data / count / totalRow 兼容别名。
 * - 行内字段沿用旧后台命名（userId、userName、userEmail、mt4grp、rec_crt_date、fy_money、rj_money、qk_money 等）。
 *
 * 异常或失败场景：
 * - 日期区间非法或查询条件不合法时不抛异常，而是返回空行集或空载荷，避免旧页面报错。
 * - 大代理统计在树内无可见用户时会跳过该行，保证数据范围外的数据不会泄露。
 */
namespace App\Services;

use App\Models\Admin;
use App\Models\BigAgent;
use App\Models\UserInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyAdminAgentStatisticsService
{
    /**
     * 后台数据范围服务：旧代理列表/大代理统计的每行都按管理员的代理树可见范围裁剪；
     * 缺失或绕过时旧页面会泄露数据范围外代理与客户的资金统计（fy_money/rj_money/qk_money 等敏感口径）。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 注入数据范围服务。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围过滤服务,保证不同管理员只能查询自己权限内的代理与客户数据。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 旧后台“代理列表”搜索响应。
     *
     * 阶段：解析搜索类型与日期区间 → 套用数据范围 → 旧字段筛选 → 分页 → 批量统计直属下级与资金流动 → 组装行与页脚。
     *
     * @param Request $request 旧后台请求，支持 searchtype/page/rows/userId/userstatus/username 等参数。
     * @param Admin $admin 当前后台管理员，用于套用数据范围。
     * @param bool $includeV2Aliases true 时额外附带 data/count/totalRow 兼容别名。
     * @return array{rows: array<int, array<string, mixed>>, total: int, footer: array<int, array<string, mixed>>}
     */
    public function agentList(Request $request, Admin $admin, bool $includeV2Aliases = false): array
    {
        // 日期过滤只在 autoSearch/clickSearch 模式下生效；日期非法时失败关闭为空结果。
        $searchType = (string) $request->input('searchtype', '');
        $usesDateFilter = in_array($searchType, ['autoSearch', 'clickSearch'], true);
        [$startDate, $endDate, $datesValid] = $this->dateRange($request, $usesDateFilter);
        $query = UserInfo::query()
            ->select('user_infos.*', 'user_logins.is_cancelled')
            ->leftJoin('user_logins', 'user_logins.id', '=', 'user_infos.login_id')
            ->where('user_infos.account_type', 1);
        // 套用数据范围后，total、全部ID与分页行都基于同一过滤条件，避免越权与计数口径不一致。
        $query = $this->adminDataScopeService->apply(
            $query,
            $admin,
            'agent',
            'user_infos.user_id'
        );

        if ($usesDateFilter) {
            if (!$datesValid) {
                $query->whereRaw('1 = 0');
            } else {
                $this->applyCreatedAtRange($query, $startDate, $endDate);
            }
        }

        $this->applyLegacyAgentFilters($query, $request);

        // 全部ID用于页脚合计，不参与分页。
        $total = (clone $query)->count('user_infos.id');
        $allAgentIds = (clone $query)
            ->orderBy('user_infos.user_id')
            ->pluck('user_infos.user_id')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->all();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = $this->perPage($request);
        $agents = $query
            ->orderByDesc('user_infos.created_at')
            ->orderByDesc('user_infos.user_id')
            ->forPage($page, $perPage)
            ->get();
        $pageIds = $agents->pluck('user_id')->map(static function ($id): int {
            return (int) $id;
        })->all();
        // 直属计数与资金流动统计按页内 ID 批量查询，避免逐行 N+1。
        $visibleBusinessIds = $this->visibleBusinessUserIds($admin);
        $directCounts = $this->directCounts($pageIds, $visibleBusinessIds);
        $pageMoneyStats = $this->moneyMovementStats($pageIds);

        $rows = $agents->map(function (UserInfo $agent) use ($directCounts, $pageMoneyStats): array {
            $userId = (int) $agent->user_id;
            $money = $pageMoneyStats[$userId] ?? $this->emptyMoneyMovementStats();

            return $this->legacyAgentRow(
                $agent,
                $directCounts['agents'][$userId] ?? 0,
                $directCounts['customers'][$userId] ?? 0,
                $money
            );
        })->values()->all();

        // 页脚合计覆盖全部过滤后的代理，不局限于当前页。
        $footer = $this->legacyAgentFooter($allAgentIds);
        $payload = [
            'rows' => $rows,
            'total' => $total,
            'footer' => [$footer],
        ];

        if ($includeV2Aliases) {
            // 兼容新版表格组件的别名键，旧页面只认 rows/total/footer。
            $payload['data'] = $rows;
            $payload['count'] = $total;
            $payload['totalRow'] = $footer;
        }

        return $payload;
    }

    /**
     * 生成旧后台代理审核列表搜索响应。
     *
     * 参数逻辑说明：
     * - 项目1 `agentsExamineListSearch` 默认查询 2024-01-01 到当天的待确认代理。
     * - `is_agent_confirmed=0` 对应旧表 `is_confirm_agents_lvg=0`，表示代理等级仍待后台确认。
     * - 返回值保持旧 Layui 表格的 `rows/total/footer` 结构和 userId、userName、userEmail 等旧字段名。
     *
     * @param Request $request 旧后台请求，支持 page、rows、userId、startdate、enddate。
     * @param Admin $admin 当前后台管理员，用于套用数据范围。
     * @return array{rows: array<int, array<string, mixed>>|string, total: int|string, footer?: array<int, array<int, mixed>>}
     */
    public function agentExamineList(Request $request, Admin $admin): array
    {
        [$startDate, $endDate, $datesValid] = $this->dateRange($request, true);
        // 只列出认证状态为待审核（0/1/2/4）且 is_agent_confirmed=0 的代理。
        $query = UserInfo::query()
            ->select('user_infos.*', 'user_logins.email as login_email')
            ->leftJoin('user_logins', 'user_logins.id', '=', 'user_infos.login_id')
            ->where('user_infos.account_type', 1)
            ->whereIn('user_infos.auth_status', [0, 1, 2, 4])
            ->where('user_infos.is_agent_confirmed', 0);
        $query = $this->adminDataScopeService->apply(
            $query,
            $admin,
            'agent',
            'user_infos.user_id'
        );

        // 日期非法时失败关闭，避免默认区间之外的数据进入列表。
        if (!$datesValid) {
            $query->whereRaw('1 = 0');
        } else {
            $this->applyCreatedAtRange($query, $startDate, $endDate);
        }

        if ($request->filled('userId') || $request->filled('user_id')) {
            // 用户ID参数非法时失败关闭，避免弱过滤放行。
            $userId = $this->positiveInteger($request->input('userId', $request->input('user_id')));
            $userId === null
                ? $query->whereRaw('1 = 0')
                : $query->where('user_infos.user_id', $userId);
        }

        // 空结果保持旧表格兼容的空串载荷。
        $total = (clone $query)->count('user_infos.id');
        if ($total <= 0) {
            return ['rows' => '', 'total' => ''];
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = $this->perPage($request);
        $rows = $query
            ->orderByDesc('user_infos.created_at')
            ->orderByDesc('user_infos.user_id')
            ->forPage($page, $perPage)
            ->get()
            ->map(function (UserInfo $agent): array {
                return $this->legacyAgentExamineRow($agent);
            })
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'footer' => [[]],
        ];
    }

    /**
     * 旧后台“大代理统计”列表。
     *
     * 阶段：解析区间与目标大代理 → 对每个大代理分配的子代理根节点求可见交集 → 展开子树统计 → 页脚汇总。
     *
     * @param Request $request 旧后台请求，支持 big_id/startdate/enddate。
     * @param Admin $admin 当前后台管理员，用于可见范围过滤。
     * @return array{rows: array<int, array<string, mixed>>, total: int, footer: array<int, array<string, mixed>>}
     */
    public function bigAgentList(Request $request, Admin $admin): array
    {
        [$startDate, $endDate, $datesValid] = $this->dateRange($request, true);
        if (!$datesValid) {
            return $this->emptyBigAgentPayload();
        }

        $bigAgents = $this->requestedBigAgents($request, $startDate, $endDate);
        if ($bigAgents->isEmpty()) {
            return $this->emptyBigAgentPayload();
        }

        $visibleAgentIds = $this->visibleAgentIds($admin);
        $visibleBusinessIds = $this->visibleBusinessUserIds($admin);
        $rows = [];
        $footerIds = [];
        $visibleBigAgentTotal = 0;

        foreach ($bigAgents as $bigAgent) {
            $assignedRootIds = $this->assignedAgentIds($bigAgent);
            // 只统计分配给大代理且当前管理员可见的根节点，防止跨权限数据进入统计。
            $rowRootIds = array_values(array_intersect($assignedRootIds, $visibleAgentIds));
            sort($rowRootIds);
            $hasVisibleRow = false;

            foreach ($rowRootIds as $rootId) {
                // 子树同样按管理员可见范围裁剪后再统计。
                $treeIds = array_values(array_intersect(
                    $this->treeUserIds($rootId),
                    $visibleBusinessIds
                ));
                // 根节点自身不在可见范围内时跳过该行。
                if (!in_array($rootId, $treeIds, true)) {
                    continue;
                }

                $agent = UserInfo::query()
                    ->where('account_type', 1)
                    ->where('user_id', $rootId)
                    ->first();
                if (!$agent) {
                    continue;
                }

                $rows[] = $this->bigAgentStatisticsRow(
                    (int) $bigAgent->id,
                    (string) $bigAgent->username,
                    $agent,
                    $treeIds,
                    $request,
                    true
                );
                $footerIds = array_merge($footerIds, $treeIds);
                $hasVisibleRow = true;
            }

            if ($hasVisibleRow) {
                $visibleBigAgentTotal++;
            }
        }

        // 页脚只汇总本页可见行涉及的树。
        $footer = $this->bigAgentFooter(
            array_values(array_unique($footerIds)),
            $request,
            true
        );

        return [
            'rows' => $rows,
            'total' => $visibleBigAgentTotal,
            'footer' => [$footer],
        ];
    }

    /**
     * 旧后台“大代理下级列表”。
     *
     * @param Request $request 旧后台请求，支持 big_id/user_pid。
     * @param Admin $admin 当前后台管理员，用于可见范围过滤。
     * @return array{rows: array<int, array<string, mixed>>, total: int, footer: array<int, array<string, mixed>>}
     */
    public function bigAgentSubList(Request $request, Admin $admin): array
    {
        $bigAgent = $this->requestedBigAgent($request);
        $parentId = $this->positiveInteger(
            $request->input('user_pid', $request->input('userPId', $request->input('user_pId')))
        );
        if (!$bigAgent || $parentId === null) {
            return $this->emptyBigAgentPayload();
        }

        // 先展开该大代理全部子树中的代理 ID。
        $bigAgentTreeAgentIds = [];
        foreach ($this->assignedAgentIds($bigAgent) as $assignedRootId) {
            $bigAgentTreeAgentIds = array_merge(
                $bigAgentTreeAgentIds,
                $this->treeAgentIds($assignedRootId)
            );
        }
        $bigAgentTreeAgentIds = array_values(array_unique($bigAgentTreeAgentIds));
        $visibleAgentIds = $this->visibleAgentIds($admin);

        // 目标节点必须同时属于该大代理的树和当前管理员可见范围，否则返回空载荷。
        if (!in_array($parentId, $bigAgentTreeAgentIds, true)
            || !in_array($parentId, $visibleAgentIds, true)) {
            return $this->emptyBigAgentPayload();
        }

        // 只列直属下级代理。
        $directAgents = UserInfo::query()
            ->where('account_type', 1)
            ->where('parent_id', $parentId)
            ->whereIn('user_id', $visibleAgentIds)
            ->orderBy('user_id')
            ->get();
        $visibleBusinessIds = $this->visibleBusinessUserIds($admin);
        $rows = [];
        $footerIds = [];

        foreach ($directAgents as $agent) {
            $treeIds = array_values(array_intersect(
                $this->treeUserIds((int) $agent->user_id),
                $visibleBusinessIds
            ));
            $rows[] = $this->bigAgentStatisticsRow(
                (int) $bigAgent->id,
                (string) $bigAgent->username,
                $agent,
                $treeIds,
                $request,
                true
            );
            $footerIds = array_merge($footerIds, $treeIds);
        }

        $footer = $this->bigAgentFooter(
            array_values(array_unique($footerIds)),
            $request,
            true
        );

        return [
            'rows' => $rows,
            'total' => count($rows),
            'footer' => [$footer],
        ];
    }

    /**
     * 按 big_id 与创建区间读取大代理记录。
     *
     * @param Request $request 旧后台请求，支持 big_id。
     * @param string|null $startDate 创建区间开始日期 Y-m-d。
     * @param string|null $endDate 创建区间结束日期 Y-m-d。
     * @return \Illuminate\Support\Collection<int, BigAgent> 匹配的大代理集合；big_id 非法时返回空集合。
     */
    private function requestedBigAgents(Request $request, string $startDate = null, string $endDate = null)
    {
        $query = BigAgent::query();
        if ($request->filled('big_id')) {
            $bigAgentId = $this->positiveInteger($request->input('big_id'));
            if ($bigAgentId === null) {
                return collect();
            }

            $query->where('id', $bigAgentId);
        }

        if ($startDate !== null) {
            $query->where('created_at', '>=', strtotime($startDate . ' 00:00:00'));
        }
        if ($endDate !== null) {
            $query->where('created_at', '<=', strtotime($endDate . ' 23:59:59'));
        }

        return $query->orderBy('id')->get();
    }

    /**
     * 套用旧后台代理列表的附加筛选条件。
     *
     * 筛选语义：autoSearch=只看顶层代理；showSubAgents/subSearch=按父代理过滤；
     * clickSearch=按 user_id 精确定位；非法参数一律失败关闭为空结果。
     *
     * @param Builder $query 已套用数据范围的代理查询。
     * @param Request $request 旧后台请求。
     * @return void
     */
    private function applyLegacyAgentFilters(Builder $query, Request $request): void
    {
        $searchType = (string) $request->input('searchtype', '');
        $userId = $this->positiveInteger($request->input('userId', $request->input('user_id')));
        $parentId = $this->positiveInteger(
            $request->input('userPid', $request->input('userPId', $request->input('user_pid')))
        );

        if ($searchType === 'autoSearch') {
            $query->where('user_infos.parent_id', 0);
        } elseif (in_array($searchType, ['showSubAgents', 'subSearch'], true)) {
            // 查看下级代理时父代理参数非法则失败关闭。
            $parentId === null
                ? $query->whereRaw('1 = 0')
                : $query->where('user_infos.parent_id', $parentId);
        } elseif ($searchType === 'clickSearch') {
            if ($request->filled('userId') || $request->filled('user_id')) {
                $userId === null
                    ? $query->whereRaw('1 = 0')
                    : $query->where('user_infos.user_id', $userId);
            }
        }

        if (in_array($searchType, ['autoSearch', 'clickSearch'], true)) {
            $this->applyIntegerFilter($query, $request, 'userstatus', 'user_infos.auth_status');
            $this->applyIntegerFilter($query, $request, 'transmode', 'user_infos.trading_mode');
            $this->applyIntegerFilter($query, $request, 'is_confirm_agents', 'user_infos.is_agent_confirmed');

            if ($request->filled('user_cancel')) {
                // 旧页面 user_cancel 只允许 0/1；非法值失败关闭，避免绕过状态筛选。
                $cancelled = (string) $request->input('user_cancel');
                if (!in_array($cancelled, ['0', '1'], true)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('user_logins.is_cancelled', $cancelled === '0' ? 1 : 0);
                }
            }
        }

        if ($request->filled('username') || $request->filled('user_name')) {
            $name = trim((string) $request->input('username', $request->input('user_name')));
            $query->where('user_infos.user_name', 'like', '%' . $name . '%');
        }

    }

    /**
     * 把请求中的整数筛选参数应用到查询。
     *
     * @param Builder $query 目标查询。
     * @param Request $request 旧后台请求。
     * @param string $requestKey 请求参数名。
     * @param string $column 数据库列名。
     * @return void 参数非法时追加恒假条件，避免弱过滤放行数据。
     */
    private function applyIntegerFilter(
        Builder $query,
        Request $request,
        string $requestKey,
        string $column
    ): void {
        if (!$request->filled($requestKey)) {
            return;
        }

        $value = filter_var($request->input($requestKey), FILTER_VALIDATE_INT);
        if ($value === false) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($column, (int) $value);
    }

    /**
     * 按 created_at 区间追加筛选。
     *
     * @param Builder $query 目标查询。
     * @param string|null $startDate 开始日期 Y-m-d。
     * @param string|null $endDate 结束日期 Y-m-d。
     * @return void
     */
    private function applyCreatedAtRange(Builder $query, string $startDate = null, string $endDate = null): void
    {
        if ($startDate !== null) {
            $query->where('user_infos.created_at', '>=', strtotime($startDate . ' 00:00:00'));
        }
        if ($endDate !== null) {
            $query->where('user_infos.created_at', '<=', strtotime($endDate . ' 23:59:59'));
        }
    }

    /**
     * 解析旧后台日期区间参数。
     *
     * legacyDefaults=true 且未提供区间时使用 2024-01-01 至今天（旧项目默认）。
     *
     * @param Request $request 旧后台请求。
     * @param bool $legacyDefaults 是否应用旧项目默认区间。
     * @return array{0: string|null, 1: string|null, 2: bool} [开始日期, 结束日期, 是否合法]。
     */
    private function dateRange(Request $request, bool $legacyDefaults = false): array
    {
        $startDate = $this->dateValue($request->input('startdate', $request->input('start_date')));
        $endDate = $this->dateValue($request->input('enddate', $request->input('end_date')));
        $startProvided = $request->filled('startdate') || $request->filled('start_date');
        $endProvided = $request->filled('enddate') || $request->filled('end_date');
        if ($legacyDefaults) {
            if (!$startProvided) {
                $startDate = '2024-01-01';
            }
            if (!$endProvided) {
                $endDate = date('Y-m-d');
            }
        }
        $valid = (!$startProvided || $startDate !== null)
            && (!$endProvided || $endDate !== null)
            && ($startDate === null || $endDate === null || $startDate <= $endDate);

        return [$startDate, $endDate, $valid];
    }

    /**
     * 校验并规范单个日期参数，仅接受 Y-m-d 格式。
     *
     * @param mixed $value 请求参数。
     * @return string|null 规范化日期；缺失或格式非法返回 null。
     */
    private function dateValue($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        // 严格按 Y-m-d 解析并回写比较，拒绝日期越界等宽松解析结果。
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * 读取分页大小，限制在 1~100。
     *
     * @param Request $request 旧后台请求，兼容 rows/limit/per_page 三种参数名。
     * @return int 分页大小。
     */
    private function perPage(Request $request): int
    {
        $value = (int) $request->input(
            'rows',
            $request->input('limit', $request->input('per_page', 15))
        );

        return max(1, min($value, 100));
    }

    /**
     * 统计代理页内每个代理的直属下级数与直属客户数。
     *
     * 只统计管理员可见范围内且账号状态正常的用户。
     *
     * @param array<int, int> $agentIds 页内代理 ID。
     * @param array<int, int> $visibleBusinessIds 管理员可见业务用户 ID。
     * @return array{agents: array<int, int>, customers: array<int, int>}
     */
    private function directCounts(array $agentIds, array $visibleBusinessIds): array
    {
        $counts = ['agents' => [], 'customers' => []];
        if ($agentIds === [] || $visibleBusinessIds === []) {
            return $counts;
        }

        $rows = UserInfo::query()
            ->whereIn('parent_id', $agentIds)
            ->whereIn('user_id', $visibleBusinessIds)
            ->whereIn('auth_status', [0, 1, 2, 4])
            ->whereIn('account_type', [1, 2])
            ->select('parent_id', 'account_type')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('parent_id', 'account_type')
            ->get();

        foreach ($rows as $row) {
            $bucket = (int) $row->account_type === 1 ? 'agents' : 'customers';
            $counts[$bucket][(int) $row->parent_id] = (int) $row->aggregate_count;
        }

        return $counts;
    }

    /**
     * 按用户聚合资金流动统计（返佣/入金/出金）。
     *
     * 口径：cmd=6 的余额调整按备注关键词归类，返回格式化后的金额字符串。
     *
     * @param array<int, int> $userIds 目标用户 ID。
     * @return array<int, array{total_fy: string, total_rj: string, total_qk: string}>
     */
    private function moneyMovementStats(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('user_trades')
            ->whereNull('deleted_at')
            ->whereIn('user_id', $userIds)
            ->select('user_id')
            ->selectRaw($this->moneyMovementSelectSql(), $this->moneyMovementBindings())
            ->groupBy('user_id')
            ->get();
        $stats = [];

        foreach ($rows as $row) {
            $stats[(int) $row->user_id] = [
                'total_fy' => $this->money($row->total_fy ?? 0),
                'total_rj' => $this->money($row->total_rj ?? 0),
                'total_qk' => $this->money($row->total_qk ?? 0),
            ];
        }

        return $stats;
    }

    /**
     * 资金流动汇总 SQL。
     *
     * 三个占位符分别绑定返佣/入金/出金关键词正则；cmd=6 的余额调整按备注命中与 profit 正负区分入金与出金。
     *
     * @return string 带 ? 占位符的 SELECT 片段。
     */
    private function moneyMovementSelectSql(): string
    {
        return "
            COALESCE(SUM(CASE WHEN cmd = 6 AND LOWER(COALESCE(comment, '')) REGEXP ? THEN profit ELSE 0 END), 0) as total_fy,
            COALESCE(SUM(CASE WHEN cmd = 6 AND profit > 0 AND LOWER(COALESCE(comment, '')) REGEXP ? THEN profit ELSE 0 END), 0) as total_rj,
            COALESCE(SUM(CASE WHEN cmd = 6 AND profit < 0 AND LOWER(COALESCE(comment, '')) REGEXP ? THEN profit ELSE 0 END), 0) as total_qk
        ";
    }

    /**
     * 资金流动关键词绑定：依次对应 total_fy/total_rj/total_qk 三个占位符。
     *
     * @return array<int, string> 返佣、入金、出金三组关键词正则。
     */
    private function moneyMovementBindings(): array
    {
        return [
            '-fy|commission|rebate|dbcn|返佣',
            'deposit|recharge|dbaa|dbct|dbgn|dbmn|dbpa|dbpn|dbsn|dbtn|dbun|dbzn|dbad|wbir|入金|充值',
            'withdrawal|withdraw|wbaa|wbcn|wbct|wbhn|wbin|wbpn|wbsn|wbtn|wbad|dbzr|出金|提现|取款',
        ];
    }

    /** @return array{total_fy: string, total_rj: string, total_qk: string} */
    private function emptyMoneyMovementStats(): array
    {
        return ['total_fy' => '0.00', 'total_rj' => '0.00', 'total_qk' => '0.00'];
    }

    /**
     * 把代理记录映射为旧“代理列表”行。
     *
     * 字段同时输出新旧两套命名（userId/user_id、BALANCE/usermoney 等），满足旧表格与新版接口共用。
     *
     * @param UserInfo $agent 代理资料。
     * @param int $directAgentCount 直属代理数。
     * @param int $directCustomerCount 直属客户数。
     * @param array{total_fy: string, total_rj: string, total_qk: string} $money 资金流动统计。
     * @return array<string, mixed> 旧表格可识别的行字段。
     */
    private function legacyAgentRow(
        UserInfo $agent,
        int $directAgentCount,
        int $directCustomerCount,
        array $money
    ): array {
        $createdAt = (int) $agent->getRawOriginal('created_at');
        $balance = $this->money($agent->total_funds);
        $equity = $this->money($agent->equity);

        return [
            'user_id' => (int) $agent->user_id,
            'username' => (string) $agent->user_name,
            'user_name' => (string) $agent->user_name,
            'parentId' => (int) $agent->parent_id,
            'parent_id' => (int) $agent->parent_id,
            'groupId' => (int) $agent->level_id,
            'group_id' => (int) $agent->level_id,
            'usermoney' => $balance,
            'BALANCE' => $balance,
            'custeqy' => $equity,
            'EQUITY' => $equity,
            'userstatus' => (int) $agent->auth_status,
            'user_status' => (int) $agent->auth_status,
            'idcardstatus' => (int) $agent->auth_status,
            'bankstatus' => (int) $agent->auth_status,
            'mt4grp' => (string) $agent->mt4_group,
            'transmode' => (int) $agent->trading_mode,
            'trans_mode' => (int) $agent->trading_mode,
            'rights' => $agent->comm_rate,
            'commprop' => $agent->comm_rate,
            'isconfirmagtlvg' => (int) $agent->is_agent_confirmed,
            'settlementmodel' => (int) $agent->settle_method,
            'rec_crt_date' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
            'usergrp_id' => (int) $agent->group_id,
            'usergrp_name' => (string) $agent->mt4_group,
            'mt4Login' => (int) $agent->user_id,
            'mt4MarginLevel' => $this->money($agent->risk_ratio),
            'agentsTotal' => $directAgentCount,
            'accountTotal' => $directCustomerCount,
            'mun' => $directAgentCount,
            'user_mun' => $directCustomerCount,
            'fy_money' => $money['total_fy'],
            'rj_money' => $money['total_rj'],
            'qk_money' => $money['total_qk'],
            'group_comm_prop' => $agent->comm_rate,
        ];
    }

    /**
     * 把当前 user_infos 代理记录映射为旧代理审核列表行。
     *
     * @param UserInfo $agent 已从 user_infos 与 user_logins 读取出的代理记录。
     * @return array<string, mixed> 旧 Blade 表格可识别的行字段。
     */
    private function legacyAgentExamineRow(UserInfo $agent): array
    {
        $createdAt = (int) $agent->getRawOriginal('created_at');

        return [
            'userId' => (int) $agent->user_id,
            'parentId' => (int) $agent->parent_id,
            'userName' => (string) $agent->user_name,
            'userSex' => (int) $agent->gender,
            'mt4grp' => (string) $agent->mt4_group,
            'userStatus' => (int) $agent->auth_status,
            'IdCardStatus' => (int) $agent->auth_status,
            'bankStatus' => (int) $agent->auth_status,
            'userEmail' => (string) ($agent->login_email ?? ''),
            'userPhone' => (string) $agent->phone,
            'userGroupId' => (int) $agent->group_id,
            'userRights' => $agent->comm_rate,
            'rec_crt_date' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
        ];
    }

    /**
     * 代理列表页脚合计行。
     *
     * @param array<int, int> $agentIds 全部过滤后代理 ID（含未分页部分）。
     * @return array<string, mixed> 合计行，含余额、净值与资金流动汇总。
     */
    private function legacyAgentFooter(array $agentIds): array
    {
        $balance = 0;
        $equity = 0;
        $money = $this->emptyMoneyMovementStats();

        if ($agentIds !== []) {
            $funds = UserInfo::query()
                ->whereIn('user_id', $agentIds)
                ->selectRaw('COALESCE(SUM(total_funds), 0) as balance')
                ->selectRaw('COALESCE(SUM(equity), 0) as equity')
                ->first();
            $balance = $funds->balance ?? 0;
            $equity = $funds->equity ?? 0;
            $movement = DB::table('user_trades')
                ->whereNull('deleted_at')
                ->whereIn('user_id', $agentIds)
                ->selectRaw($this->moneyMovementSelectSql(), $this->moneyMovementBindings())
                ->first();
            $money = [
                'total_fy' => $this->money($movement->total_fy ?? 0),
                'total_rj' => $this->money($movement->total_rj ?? 0),
                'total_qk' => $this->money($movement->total_qk ?? 0),
            ];
        }

        return [
            'user_id' => __('front.total'),
            'username' => '',
            'groupId' => '',
            'userstatus' => '',
            'isconfirmagtlvg' => '',
            'parentId' => '',
            'agentsTotal' => '',
            'accountTotal' => '',
            'mt4MarginLevel' => '',
            'usermoney' => $this->money($balance),
            'BALANCE' => $this->money($balance),
            'custeqy' => $this->money($equity),
            'EQUITY' => $this->money($equity),
            'fy_money' => $money['total_fy'],
            'rj_money' => $money['total_rj'],
            'qk_money' => $money['total_qk'],
            'all_total_fy' => $money['total_fy'],
            'all_total_rj' => $money['total_rj'],
            'all_total_qk' => $money['total_qk'],
            'rec_crt_date' => '',
            'options' => '',
        ];
    }

    /**
     * 按 big_id 读取单个大代理。
     *
     * @param Request $request 旧后台请求，支持 big_id。
     * @return BigAgent|null big_id 缺失或非法时返回 null。
     */
    private function requestedBigAgent(Request $request): ?BigAgent
    {
        $bigAgentId = $this->positiveInteger($request->input('big_id'));
        if ($bigAgentId === null) {
            return null;
        }

        return BigAgent::query()->where('id', $bigAgentId)->first();
    }

    /**
     * 解析大代理配置的子代理根节点 ID。
     *
     * @param BigAgent $bigAgent 大代理记录。
     * @return array<int, int> 去重后的合法子代理 ID。
     */
    private function assignedAgentIds(BigAgent $bigAgent): array
    {
        $ids = array_filter(array_map('trim', explode(',', (string) $bigAgent->sub_agent_ids)), static function ($id): bool {
            return ctype_digit($id) && (int) $id > 0;
        });

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * 当前管理员可见的全部代理 ID。
     *
     * @param Admin $admin 当前后台管理员。
     * @return array<int, int> 可见代理业务 user_id。
     */
    private function visibleAgentIds(Admin $admin): array
    {
        $query = UserInfo::query()->where('account_type', 1);
        $query = $this->adminDataScopeService->apply($query, $admin, 'agent', 'user_id');

        return $query->pluck('user_id')->map(static function ($id): int {
            return (int) $id;
        })->unique()->values()->all();
    }

    /**
     * 当前管理员可见的全部业务用户（代理+客户）ID。
     *
     * @param Admin $admin 当前后台管理员。
     * @return array<int, int> 去重后的可见用户 ID。
     */
    private function visibleBusinessUserIds(Admin $admin): array
    {
        $agentIds = $this->visibleAgentIds($admin);
        $customerQuery = UserInfo::query()->where('account_type', 2);
        $customerQuery = $this->adminDataScopeService->apply(
            $customerQuery,
            $admin,
            'user',
            'user_id'
        );
        $customerIds = $customerQuery->pluck('user_id')->map(static function ($id): int {
            return (int) $id;
        })->all();

        return array_values(array_unique(array_merge($agentIds, $customerIds)));
    }

    /**
     * 根节点子树内的代理 ID。
     *
     * @param int $rootId 子树根代理。
     * @return array<int, int> 子树内代理 ID。
     */
    private function treeAgentIds(int $rootId): array
    {
        $treeIds = $this->treeUserIds($rootId);

        return UserInfo::query()
            ->where('account_type', 1)
            ->whereIn('user_id', $treeIds)
            ->pluck('user_id')
            ->map(static function ($id): int {
                return (int) $id;
            })->all();
    }

    /**
     * 展开子树全部用户 ID。
     *
     * 只沿 parent_id 实时拓扑展开，visited 集合防环；普通客户作为叶子节点停止递归。
     *
     * @param int $rootId 子树根用户 ID。
     * @return array<int, int> 包含根节点在内的全部节点 ID。
     */
    private function treeUserIds(int $rootId): array
    {
        $ids = [$rootId];
        $visited = [$rootId => true];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $children = UserInfo::query()
                ->whereIn('parent_id', $frontier)
                ->get(['user_id', 'account_type']);
            $frontier = [];

            foreach ($children as $child) {
                $childId = (int) $child->user_id;
                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $ids[] = $childId;
                if ((int) $child->account_type === 1) {
                    $frontier[] = $childId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 组装大代理统计行。
     *
     * @param int $bigAgentId 大代理主键。
     * @param string $bigAgentName 大代理用户名。
     * @param UserInfo $agent 子代理根资料。
     * @param array<int, int> $treeIds 子代理可见子树用户 ID。
     * @param Request $request 旧后台请求。
     * @param bool $legacyDefaults 是否应用旧项目默认日期区间。
     * @return array<string, mixed> 统计字段与大代理/子代理标识字段。
     */
    private function bigAgentStatisticsRow(
        int $bigAgentId,
        string $bigAgentName,
        UserInfo $agent,
        array $treeIds,
        Request $request,
        bool $legacyDefaults = false
    ): array {
        $stats = $this->statisticsForIds($treeIds, $request, $legacyDefaults);

        return array_merge($stats, [
            'id' => $bigAgentId,
            'big_ag_name' => $bigAgentName,
            'sub_ag_id' => (int) $agent->user_id,
            'sub_ag_name' => (string) $agent->user_name,
            'sub_ag_groupId' => (int) $agent->level_id,
            'sub_ag_parentId' => (int) $agent->parent_id,
        ]);
    }

    /**
     * 对一组用户计算交易、资金与品种分类统计。
     *
     * 金额字段格式化为两位小数，交易量按手数（volume/100）输出。
     *
     * @param array<int, int> $userIds 目标用户 ID。
     * @param Request $request 旧后台请求。
     * @param bool $legacyDefaults 是否应用旧项目默认日期区间。
     * @return array<string, string> 统计键值。
     */
    private function statisticsForIds(array $userIds, Request $request, bool $legacyDefaults = false): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return $this->emptyStatistics();
        }

        [$startDate, $endDate, $datesValid] = $this->dateRange($request, $legacyDefaults);
        // 日期非法时返回全零统计，不抛出异常，保持旧页面可用。
        if (!$datesValid) {
            return $this->emptyStatistics();
        }

        $funds = UserInfo::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw('COALESCE(SUM(total_funds), 0) as balance')
            ->selectRaw('COALESCE(SUM(equity), 0) as equity')
            ->first();
        $symbolGroups = DB::table('symbol_prices')
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->select('symbol')
            ->selectRaw('MAX(group_id) as group_id')
            ->groupBy('symbol');
        $trades = DB::table('user_trades as trades')
            ->leftJoinSub($symbolGroups, 'symbol_groups', 'symbol_groups.symbol', '=', 'trades.symbol')
            ->whereNull('trades.deleted_at')
            ->whereIn('trades.user_id', $userIds);
        if ($startDate !== null) {
            $trades->where('trades.close_time', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== null) {
            $trades->where('trades.close_time', '<=', $endDate . ' 23:59:59');
        }

        $row = $trades->selectRaw("
            COALESCE(SUM(CASE WHEN trades.cmd = 6 AND LOWER(COALESCE(trades.comment, '')) REGEXP ? THEN trades.profit ELSE 0 END), 0) as total_fy,
            COALESCE(SUM(CASE WHEN trades.cmd = 6 AND trades.profit > 0 AND LOWER(COALESCE(trades.comment, '')) REGEXP ? THEN trades.profit ELSE 0 END), 0) as total_rj,
            COALESCE(SUM(CASE WHEN trades.cmd = 6 AND trades.profit < 0 AND LOWER(COALESCE(trades.comment, '')) REGEXP ? THEN trades.profit ELSE 0 END), 0) as total_qk,
            COALESCE(SUM(CASE WHEN trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.commission ELSE 0 END), 0) as total_comm,
            COALESCE(SUM(CASE WHEN trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.profit ELSE 0 END), 0) as total_profit,
            COALESCE(SUM(CASE WHEN symbol_groups.group_id = 1 AND trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.volume ELSE 0 END), 0) as total_noble_metal,
            COALESCE(SUM(CASE WHEN symbol_groups.group_id = 2 AND trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.volume ELSE 0 END), 0) as total_for_exca,
            COALESCE(SUM(CASE WHEN symbol_groups.group_id = 3 AND trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.volume ELSE 0 END), 0) as total_crud_oil,
            COALESCE(SUM(CASE WHEN symbol_groups.group_id = 4 AND trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.volume ELSE 0 END), 0) as total_index,
            COALESCE(SUM(CASE WHEN symbol_groups.group_id = 5 AND trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.volume ELSE 0 END), 0) as total_currency,
            COALESCE(SUM(CASE WHEN symbol_groups.group_id = 6 AND trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.volume ELSE 0 END), 0) as total_stock,
            COALESCE(SUM(CASE WHEN trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.volume ELSE 0 END), 0) as total_volume,
            COALESCE(SUM(CASE WHEN trades.swaps < 0 AND trades.cmd IN (0,1,2,3,4,5) AND trades.close_time > '1970-01-02 00:00:00' AND trades.margin_rate <> 0 THEN trades.swaps ELSE 0 END), 0) as total_swaps
        ", $this->moneyMovementBindings())->first();

        $deposit = (float) ($row->total_rj ?? 0);
        $withdrawal = (float) ($row->total_qk ?? 0);

        return [
            'total_fy' => $this->money($row->total_fy ?? 0),
            'total_rj' => $this->money($deposit),
            'total_qk' => $this->money($withdrawal),
            'user_money' => $this->money($funds->balance ?? 0),
            'cust_eqy' => $this->money($funds->equity ?? 0),
            'total_yuerj' => $this->money($deposit),
            'total_yuecj' => $this->money($withdrawal),
            'total_net_worth' => $this->money($deposit - abs($withdrawal)),
            'total_comm' => $this->money($row->total_comm ?? 0),
            'total_profit' => $this->money($row->total_profit ?? 0),
            'total_noble_metal' => $this->lots($row->total_noble_metal ?? 0),
            'total_for_exca' => $this->lots($row->total_for_exca ?? 0),
            'total_crud_oil' => $this->lots($row->total_crud_oil ?? 0),
            'total_index' => $this->lots($row->total_index ?? 0),
            'total_currency' => $this->lots($row->total_currency ?? 0),
            'total_stock' => $this->lots($row->total_stock ?? 0),
            'total_volume' => $this->lots($row->total_volume ?? 0),
            'total_swaps' => $this->money($row->total_swaps ?? 0),
        ];
    }

    /**
     * 大代理统计页脚合计行。
     *
     * @param array<int, int> $userIds 合计覆盖的用户 ID。
     * @param Request $request 旧后台请求。
     * @param bool $legacyDefaults 是否应用旧项目默认日期区间。
     * @return array<string, mixed> 合计行字段。
     */
    private function bigAgentFooter(array $userIds, Request $request, bool $legacyDefaults = false): array
    {
        return array_merge($this->statisticsForIds($userIds, $request, $legacyDefaults), [
            'id' => '',
            'big_ag_name' => '',
            'sub_ag_id' => '',
            'sub_ag_name' => __('front.total'),
            'sub_ag_groupId' => '',
            'sub_ag_parentId' => '',
        ]);
    }

    /**
     * 空大代理载荷，保持 rows/total/footer 三键结构。
     *
     * @return array{rows: array<int, mixed>, total: int, footer: array<int, array<string, mixed>>}
     */
    private function emptyBigAgentPayload(): array
    {
        return [
            'rows' => [],
            'total' => 0,
            'footer' => [$this->bigAgentFooter([], new Request())],
        ];
    }

    /**
     * 全零统计结构，保证调用方无需判空即可取值。
     *
     * @return array<string, string> 全零统计键值。
     */
    private function emptyStatistics(): array
    {
        return [
            'total_fy' => '0.00',
            'total_rj' => '0.00',
            'total_qk' => '0.00',
            'user_money' => '0.00',
            'cust_eqy' => '0.00',
            'total_yuerj' => '0.00',
            'total_yuecj' => '0.00',
            'total_net_worth' => '0.00',
            'total_comm' => '0.00',
            'total_profit' => '0.00',
            'total_noble_metal' => '0.00',
            'total_for_exca' => '0.00',
            'total_crud_oil' => '0.00',
            'total_index' => '0.00',
            'total_currency' => '0.00',
            'total_stock' => '0.00',
            'total_volume' => '0.00',
            'total_swaps' => '0.00',
        ];
    }

    /**
     * 把请求值解析为正整数。
     *
     * @param mixed $value 请求参数。
     * @return int|null 合法正整数；缺失、非数字或小于 1 时返回 null。
     */
    private function positiveInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? null : (int) $validated;
    }

    /**
     * 数字格式化为两位小数金额字符串。
     *
     * @param mixed $value 数值。
     * @return string 金额字符串，例如 1234.56。
     */
    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * MT4 原始 volume 转手数（除以 100）并格式化为两位小数。
     *
     * @param mixed $value MT4 原始成交量。
     * @return string 手数字符串。
     */
    private function lots($value): string
    {
        return $this->money(((float) $value) / 100);
    }
}
