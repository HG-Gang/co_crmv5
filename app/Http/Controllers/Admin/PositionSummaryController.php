<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Mt4Trade;
use App\Models\UserInfo;
use App\Services\AdminDataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台持仓汇总控制器。
 *
 * 文件功能：
 * - 提供后台持仓汇总列表查询与 CSV 导出，按代理/用户维度展示交易量、盈亏、手续费、库存费和品种分组手数。
 *
 * 功能逻辑说明：
 * - 旧项目后台持仓汇总按代理/用户维度聚合 MT4_TRADES、代理树和品种分组数据。
 * - 新项目当前使用 user_infos.parent_id、mt4_trades、mt4_users、symbol_prices；代理行会汇总自己和下级客户/代理的交易，普通客户行只汇总自己。
 * - MT4 账户快照通过 user_infos.mt4_code = mt4_users.login 关联，只展示当前行账号的余额、净值、保证金和杠杆，不把代理下级账号资金误合并为代理本人资金。
 * - 当前 mt4_trades 暂无旧项目 COMMENT、MARGIN_RATE、MODIFY_TIME 字段，因此本控制器不伪造入金、出金、返佣精确分类，只输出交易量、盈亏、手续费、库存费和品种分组手数。
 *
 * 安全边界：
 * - 数据查看范围由 AdminDataScopeService 读取 role_data_scopes 与 admin_agent_bindings 表配置后追加到查询中，列表与导出共用同一范围，避免越权查看他人持仓数据。
 */
class PositionSummaryController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于限制不同后台管理员可查看的代理和用户数据。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询后台持仓汇总分页列表。
     *
     * 参数逻辑说明：
     * - user_id：业务用户 ID，对应 user_infos.user_id 与 mt4_trades.login。
     * - user_name：业务用户名称，对应 user_infos.user_name，支持模糊查询。
     * - parent_id：直属上级代理 ID，对应 user_infos.parent_id。
     * - userPId/user_pid：旧后台下级代理持仓汇总的父级代理 ID，仅在 searchtype=subAgentsSearch 时生效。
     * - searchtype=subAgentsSearch：旧后台直属代理汇总模式，返回 userPId 自身与直属下级代理行。
     * - account_type：账户类型，1=代理，2=普通客户，对应 user_infos.account_type。
     * - start_date/end_date：交易时间范围，按 mt4_trades.close_time 过滤已平仓记录，同时保留未平仓 close_time=0 的记录。
     * - page/per_page/limit：分页参数，兼容 Layui table 默认传入的 page 与 limit。
     *
     * 返回结构说明：
     * - records：分页后的持仓汇总记录，每行包含用户基础信息、MT4 账户快照与交易聚合统计。
     * - summary：当前筛选条件下的总汇总，包含总账户数、总余额、总盈亏、总手数等。
     *
     * @param Request $request 当前请求对象，承载筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse 持仓汇总列表与总汇总响应。
     */
    public function positionSummaryList(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $tradeSummary = $this->buildTradeSummarySubquery($request);
        $query = $this->buildUserSummaryQuery($tradeSummary);

        $this->applyUserFilters($query, $request);
        $this->applyDataScope($query, $request);

        return $this->success([
            'records' => $this->paginateQuery($query, $request),
            'summary' => $this->summaryFor(clone $query),
        ], __('admin.position_summary_fetched'));
    }

    /**
     * 导出当前筛选条件下的后台持仓汇总 CSV。
     *
     * 逻辑说明：
     * - 导出复用列表筛选、代理树汇总和后台数据范围，避免页面看到的数据与 CSV 不一致。
     * - CSV 只输出当前真实表能支撑的字段，不补造旧项目尚无字段。
     *
     * @param Request $request 当前请求对象，读取 user_id、user_name、parent_id、account_type 和日期筛选。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse 返回 CSV 文件流。
     */
    public function exportPositionSummary(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $tradeSummary = $this->buildTradeSummarySubquery($request);
        $query = $this->buildUserSummaryQuery($tradeSummary);

        $this->applyUserFilters($query, $request);
        $this->applyDataScope($query, $request);

        $rows = [
            [
                'user_id',
                'user_name',
                'parent_id',
                'account_type',
                'group_id',
                'level_id',
                'mt4_group',
                'mt4_login',
                'mt4_name',
                'mt4_account_group',
                'mt4_balance',
                'mt4_equity',
                'mt4_margin',
                'mt4_margin_free',
                'mt4_leverage',
                'mt4_registered_at',
                'mt4_snapshot_at',
                'total_orders',
                'total_volume',
                'total_profit',
                'total_comm',
                'total_swaps',
                'total_noble_metal',
                'total_crud_oil',
                'total_for_exca',
                'total_index',
                'total_currency',
                'total_stock',
                'created_at',
            ],
        ];

        // 导出固定最多 5000 行，避免一次性导出全量持仓统计拖慢后台。
        $query->orderByDesc('user_infos.user_id')->limit(5000)->get()->each(function ($record) use (&$rows) {
            $rows[] = [
                $record->user_id,
                $record->user_name,
                $record->parent_id,
                $record->account_type,
                $record->group_id,
                $record->level_id,
                $record->mt4_group,
                $record->mt4_login,
                $record->mt4_name,
                $record->mt4_account_group,
                $record->mt4_balance,
                $record->mt4_equity,
                $record->mt4_margin,
                $record->mt4_margin_free,
                $record->mt4_leverage,
                $record->mt4_registered_at,
                $record->mt4_snapshot_at,
                $record->total_orders,
                $record->total_volume,
                $record->total_profit,
                $record->total_comm,
                $record->total_swaps,
                $record->total_noble_metal,
                $record->total_crud_oil,
                $record->total_for_exca,
                $record->total_index,
                $record->total_currency,
                $record->total_stock,
                $record->created_at,
            ];
        });

        return $this->csvDownload('position_summary_export.csv', $rows);
    }

    /**
     * 构建 MT4 交易聚合子查询。
     *
     * 逻辑说明：
     * - Mt4Trade::query() 明确表示数据源来自真实 mt4_trades 表。
     * - symbol_prices 用于按 group_id 将交易手数归类到贵金属、能源、外汇、指数、货币和股票。
     * - cmd in (0..5) 表示交易/挂单类订单；cmd=6 是余额类流水，不进入持仓汇总。
     *
     * @param Request $request 当前请求对象，用于读取 start_date/end_date 时间筛选。
     * @return Builder MT4 交易聚合查询，按 mt4_trades.login 分组。
     */
    private function buildTradeSummarySubquery(Request $request): Builder
    {
        $query = Mt4Trade::query()
            ->leftJoin('symbol_prices', 'mt4_trades.symbol', '=', 'symbol_prices.symbol')
            ->whereIn('mt4_trades.cmd', [0, 1, 2, 3, 4, 5]);

        $this->applyTradeDateRange($query, $request);

        return $query
            ->selectRaw('mt4_trades.login as login')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(mt4_trades.volume), 0) as total_volume')
            ->selectRaw('COALESCE(SUM(mt4_trades.profit), 0) as total_profit')
            ->selectRaw('COALESCE(SUM(mt4_trades.commission), 0) as total_comm')
            ->selectRaw('COALESCE(SUM(mt4_trades.swaps), 0) as total_swaps')
            ->selectRaw('COALESCE(SUM(CASE WHEN symbol_prices.group_id = 1 THEN mt4_trades.volume ELSE 0 END), 0) as total_noble_metal')
            ->selectRaw('COALESCE(SUM(CASE WHEN symbol_prices.group_id = 2 THEN mt4_trades.volume ELSE 0 END), 0) as total_crud_oil')
            ->selectRaw('COALESCE(SUM(CASE WHEN symbol_prices.group_id = 3 THEN mt4_trades.volume ELSE 0 END), 0) as total_for_exca')
            ->selectRaw('COALESCE(SUM(CASE WHEN symbol_prices.group_id = 4 THEN mt4_trades.volume ELSE 0 END), 0) as total_index')
            ->selectRaw('COALESCE(SUM(CASE WHEN symbol_prices.group_id = 5 THEN mt4_trades.volume ELSE 0 END), 0) as total_currency')
            ->selectRaw('COALESCE(SUM(CASE WHEN symbol_prices.group_id = 6 THEN mt4_trades.volume ELSE 0 END), 0) as total_stock')
            ->groupBy('mt4_trades.login');
    }

    /**
     * 构建用户维度汇总主查询。
     *
     * @param Builder $tradeSummary 按 login 聚合后的 MT4 交易子查询。
     * @return Builder 用户维度查询对象。
     */
    private function buildUserSummaryQuery(Builder $tradeSummary): Builder
    {
        $ownerTradeSummary = $this->buildOwnerTradeSummarySubquery($tradeSummary);

        return UserInfo::query()
            ->leftJoinSub($ownerTradeSummary, 'trade_stats', function ($join) {
                $join->on('user_infos.user_id', '=', 'trade_stats.user_id');
            })
            ->leftJoin('mt4_users', function ($join) {
                // mt4_code 才是业务用户对应的真实 MT4 登录号；不能用 user_id 猜测账号映射。
                $join->on('user_infos.mt4_code', '=', 'mt4_users.login')
                    ->whereNull('mt4_users.deleted_at');
            })
            ->select([
                'user_infos.user_id',
                'user_infos.user_name',
                'user_infos.parent_id',
                'user_infos.account_type',
                'user_infos.group_id',
                'user_infos.level_id',
                'user_infos.mt4_group',
                'user_infos.created_at',
            ])
            ->selectRaw('mt4_users.login as mt4_login')
            ->selectRaw('mt4_users.name as mt4_name')
            ->selectRaw('mt4_users.`group` as mt4_account_group')
            ->selectRaw('COALESCE(mt4_users.balance, 0) as mt4_balance')
            ->selectRaw('COALESCE(mt4_users.equity, 0) as mt4_equity')
            ->selectRaw('COALESCE(mt4_users.margin, 0) as mt4_margin')
            ->selectRaw('COALESCE(mt4_users.margin_free, 0) as mt4_margin_free')
            ->selectRaw('COALESCE(mt4_users.leverage, 0) as mt4_leverage')
            ->selectRaw('mt4_users.created_at as mt4_registered_at')
            ->selectRaw('mt4_users.updated_at as mt4_snapshot_at')
            ->selectRaw('COALESCE(trade_stats.total_orders, 0) as total_orders')
            ->selectRaw('COALESCE(trade_stats.total_volume, 0) as total_volume')
            ->selectRaw('COALESCE(trade_stats.total_profit, 0) as total_profit')
            ->selectRaw('COALESCE(trade_stats.total_comm, 0) as total_comm')
            ->selectRaw('COALESCE(trade_stats.total_swaps, 0) as total_swaps')
            ->selectRaw('COALESCE(trade_stats.total_noble_metal, 0) as total_noble_metal')
            ->selectRaw('COALESCE(trade_stats.total_crud_oil, 0) as total_crud_oil')
            ->selectRaw('COALESCE(trade_stats.total_for_exca, 0) as total_for_exca')
            ->selectRaw('COALESCE(trade_stats.total_index, 0) as total_index')
            ->selectRaw('COALESCE(trade_stats.total_currency, 0) as total_currency')
            ->selectRaw('COALESCE(trade_stats.total_stock, 0) as total_stock');
    }

    /**
     * 构建代理树维度交易汇总子查询。
     *
     * 逻辑说明：
     * - position_scope.owner_user_id 表示最终展示行的业务用户 ID。
     * - position_scope.member_mt4_login 表示应计入该展示行的真实 MT4 登录号，来源用户必须先通过 user_infos.mt4_code 映射。
     * - 这样能恢复旧后台按代理树汇总的行为：代理行统计团队交易，普通客户行统计自己交易。
     *
     * @param Builder $tradeSummary 按 mt4_trades.login 聚合后的交易子查询。
     * @return \Illuminate\Database\Query\Builder 按展示用户 ID 聚合后的代理树交易统计。
     */
    private function buildOwnerTradeSummarySubquery(Builder $tradeSummary)
    {
        return DB::query()
            ->fromSub($this->buildPositionScopeSubquery(), 'position_scope')
            ->leftJoinSub($tradeSummary, 'member_trade_stats', function ($join) {
                $join->on('position_scope.member_mt4_login', '=', 'member_trade_stats.login');
            })
            ->selectRaw('position_scope.owner_user_id as user_id')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_orders), 0) as total_orders')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_volume), 0) as total_volume')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_profit), 0) as total_profit')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_comm), 0) as total_comm')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_swaps), 0) as total_swaps')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_noble_metal), 0) as total_noble_metal')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_crud_oil), 0) as total_crud_oil')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_for_exca), 0) as total_for_exca')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_index), 0) as total_index')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_currency), 0) as total_currency')
            ->selectRaw('COALESCE(SUM(member_trade_stats.total_stock), 0) as total_stock')
            ->groupBy('position_scope.owner_user_id');
    }

    /**
     * 构建持仓汇总主体与成员账号映射。
     *
     * 返回值说明：
     * - owner_user_id=展示行用户 ID。
     * - member_mt4_login=实际参与 MT4 交易统计的 MT4 登录号，唯一来源是成员 user_infos.mt4_code。
     * - 第一段 union 表示每个用户自己的交易。
     * - 第二段 union 表示按 parent_id 递归拓扑得到的代理下级交易。
     * - 两段 union 会去重，避免重复计入同一成员账号。
     *
     * @return \Illuminate\Database\Query\Builder 展示用户到成员账号的映射查询。
     */
    private function buildPositionScopeSubquery()
    {
        $selfScope = DB::table('user_infos as scope_self')
            ->selectRaw('scope_self.user_id as owner_user_id')
            ->selectRaw('scope_self.mt4_code as member_mt4_login')
            ->where('scope_self.mt4_code', '>', 0)
            ->whereNull('scope_self.deleted_at');

        $parentTopologySql = <<<'SQL'
(
    WITH RECURSIVE agent_tree AS (
        SELECT
            root.user_id AS owner_user_id,
            root.user_id AS member_user_id,
            root.account_type AS member_type,
            CAST(CONCAT(',', root.user_id, ',') AS CHAR(2048)) AS path,
            0 AS depth
        FROM user_infos AS root
        WHERE root.account_type = 1
          AND root.deleted_at IS NULL

        UNION ALL

        SELECT
            tree.owner_user_id,
            child.user_id AS member_user_id,
            child.account_type AS member_type,
            CONCAT(tree.path, child.user_id, ',') AS path,
            tree.depth + 1 AS depth
        FROM agent_tree AS tree
        INNER JOIN user_infos AS child
            ON child.parent_id = tree.member_user_id
           AND child.deleted_at IS NULL
        WHERE tree.member_type = 1
          AND tree.depth < 128
          AND FIND_IN_SET(child.user_id, tree.path) = 0
    )
    SELECT
        tree.owner_user_id,
        member.mt4_code AS member_mt4_login
    FROM agent_tree AS tree
    INNER JOIN user_infos AS member
        ON member.user_id = tree.member_user_id
       AND member.deleted_at IS NULL
    WHERE tree.owner_user_id <> tree.member_user_id
      AND member.mt4_code > 0
)
SQL;

        $parentTopologyScope = DB::query()
            ->from(DB::raw($parentTopologySql . ' AS parent_scope'))
            ->select('parent_scope.owner_user_id', 'parent_scope.member_mt4_login');

        return $selfScope->union($parentTopologyScope);
    }

    /**
     * 追加用户维度筛选条件。
     *
     * @param Builder $query 用户汇总查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyUserFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_infos.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('user_name')) {
            $query->where('user_infos.user_name', 'LIKE', '%' . $request->input('user_name') . '%');
        }

        if ($request->filled('parent_id')) {
            $query->where('user_infos.parent_id', (int) $request->input('parent_id'));
        }

        $legacyParentId = $this->legacySubAgentsParentId($request);
        if ($legacyParentId !== null) {
            // 旧 subAgentsListSearchV2 展示“当前代理 + 直属下级代理”，每一行再按代理树汇总自己的下级交易。
            $query->where('user_infos.account_type', 1)
                ->where(function (Builder $subQuery) use ($legacyParentId) {
                    $subQuery->where('user_infos.user_id', $legacyParentId)
                        ->orWhere('user_infos.parent_id', $legacyParentId);
                });
        }

        if ($request->filled('account_type')) {
            $query->where('user_infos.account_type', (int) $request->input('account_type'));
        }
    }

    /**
     * 读取旧后台下级代理持仓汇总的父级代理 ID。
     *
     * 参数说明：
     * - searchtype=subAgentsSearch 表示旧后台正在请求直属代理持仓汇总。
     * - userPId/user_pid 是旧 Blade 传入的父级代理 ID，命中后用于过滤当前代理和直属下级代理。
     *
     * @param Request $request 当前请求对象。
     * @return int|null 返回父级代理 ID；不是旧下级代理模式时返回 null。
     */
    private function legacySubAgentsParentId(Request $request): ?int
    {
        if ((string) $request->input('searchtype') !== 'subAgentsSearch') {
            return null;
        }

        foreach (['userPId', 'user_pid'] as $legacyKey) {
            if ($request->filled($legacyKey)) {
                return (int) $request->input($legacyKey);
            }
        }

        return null;
    }

    /**
     * 校验可选数字筛选，避免非严格数字在进入 SQL 前被 PHP 强制转型。
     *
     * @param Request $request 当前后台请求，承载列表或导出筛选参数。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回参数错误；校验通过返回 null。
     */
    private function validateNumericFilters(Request $request)
    {
        $rules = [];

        foreach (['user_id', 'parent_id', 'account_type', 'userPId', 'user_pid'] as $field) {
            if ($request->filled($field)) {
                $rules[$field] = 'integer';
            }
        }

        if ($rules === []) {
            return null;
        }

        $validator = Validator::make($request->only(array_keys($rules)), $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 给 MT4 交易聚合查询追加日期范围。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，读取 start_date/end_date。
     * @return void 只修改查询条件，不直接返回数据。
     */
    private function applyTradeDateRange(Builder $query, Request $request): void
    {
        if ($request->filled('start_date')) {
            $query->where(function (Builder $subQuery) use ($request) {
                $subQuery->where('mt4_trades.close_time', '>=', strtotime($request->input('start_date') . ' 00:00:00'))
                    ->orWhereNull('mt4_trades.close_time')
                    ->orWhere('mt4_trades.close_time', 0);
            });
        }

        if ($request->filled('end_date')) {
            $query->where(function (Builder $subQuery) use ($request) {
                $subQuery->where('mt4_trades.close_time', '<=', strtotime($request->input('end_date') . ' 23:59:59'))
                    ->orWhereNull('mt4_trades.close_time')
                    ->orWhere('mt4_trades.close_time', 0);
            });
        }
    }

    /**
     * 按后台管理员角色和代理绑定追加数据范围。
     *
     * @param Builder $query 用户汇总查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyDataScope(Builder $query, Request $request): void
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return;
        }

        $this->adminDataScopeService->apply($query, $admin, 'user', 'user_infos.user_id');
    }

    /**
     * 计算当前筛选条件下的总汇总。
     *
     * @param Builder $query 已经追加筛选和数据范围的用户汇总查询。
     * @return array<string, float|int> 汇总字段。
     */
    private function summaryFor(Builder $query): array
    {
        // $baseSql：保留用户筛选和数据范围后的列表 SQL，再作为派生表做总汇总，避免列表字段与聚合字段混选导致 SQL 分组问题。
        $baseSql = $query->toBase();
        $summary = DB::query()
            ->fromSub($baseSql, 'position_summary_rows')
            ->selectRaw('COUNT(*) as total_accounts')
            ->selectRaw('COUNT(mt4_login) as total_mt4_accounts')
            ->selectRaw('COALESCE(SUM(mt4_balance), 0) as total_balance')
            ->selectRaw('COALESCE(SUM(mt4_equity), 0) as total_equity')
            ->selectRaw('COALESCE(SUM(mt4_margin), 0) as total_margin')
            ->selectRaw('COALESCE(SUM(mt4_margin_free), 0) as total_margin_free')
            ->selectRaw('COALESCE(SUM(total_orders), 0) as total_orders')
            ->selectRaw('COALESCE(SUM(total_volume), 0) as total_volume')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as total_profit')
            ->selectRaw('COALESCE(SUM(total_comm), 0) as total_comm')
            ->selectRaw('COALESCE(SUM(total_swaps), 0) as total_swaps')
            ->first();

        return [
            'total_accounts' => (int) ($summary->total_accounts ?? 0),
            'total_mt4_accounts' => (int) ($summary->total_mt4_accounts ?? 0),
            'total_balance' => (float) ($summary->total_balance ?? 0),
            'total_equity' => (float) ($summary->total_equity ?? 0),
            'total_margin' => (float) ($summary->total_margin ?? 0),
            'total_margin_free' => (float) ($summary->total_margin_free ?? 0),
            'total_orders' => (int) ($summary->total_orders ?? 0),
            'total_volume' => (float) ($summary->total_volume ?? 0),
            'total_profit' => (float) ($summary->total_profit ?? 0),
            'total_comm' => (float) ($summary->total_comm ?? 0),
            'total_swaps' => (float) ($summary->total_swaps ?? 0),
        ];
    }

    /**
     * 按 Layui 分页参数返回列表。
     *
     * @param Builder $query 用户汇总查询对象。
     * @param Request $request 当前请求对象，读取 page、per_page 或 limit。
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function paginateQuery(Builder $query, Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));

        return $query->orderByDesc('user_infos.user_id')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 构建 CSV 文件流响应。
     *
     * @param string $fileName 下载文件名。
     * @param array<int, array<int, mixed>> $rows 已格式化的 CSV 行数据。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse 返回可下载的 CSV 响应。
     */
    private function csvDownload(string $fileName, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
