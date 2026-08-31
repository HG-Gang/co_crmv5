<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:38
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Mt4Trade;
use App\Services\AdminDataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * 后台实时返佣控制器。
 *
 * 文件功能：
 * - 提供实时返佣列表查询、汇总统计（图表用）与 CSV 导出。
 *
 * 功能逻辑说明：
 * - 旧项目实时返佣来自 MT4_TRADES，并依赖 COMMENT 关键字识别返佣记录。
 * - 新项目当前真实表 `mt4_trades` 已补齐 comment 与 modify_time，因此按旧 `DBCN`、`-FY` 返佣关键词过滤。
 * - 当前口径使用 `cmd=6`、`profit>0` 和 COMMENT 关键词三层条件，避免普通入金或调账类正向余额记录混入返佣列表。
 *
 * 性能边界（旧实现的卡顿根因与本文件的修复方式）：
 * - 旧实现每次请求跑 4 趟全表查询：汇总 count、汇总 sum、paginator count、列表 select *。
 *   生产 `mt4_trades` 87 万行时每趟约 0.44~0.57 秒，单次请求仅数据库耗时就接近 2 秒。
 * - 旧实现 `ORDER BY COALESCE(NULLIF(modify_time,0), close_time)` 是表达式排序，永远走 filesort；
 *   `comment LIKE '%DBCN%'` 前置通配符无法用索引，EXPLAIN 固定 `type=ALL`。
 * - 旧实现 `with('user')` 预加载在输出里从未被使用，却每次请求都对 `user_infos` 再做一次全表扫描
 *   （`user_infos.mt4_code` 无索引）。
 * - 本实现：单趟聚合同时取 count 与 sum；手工构造 paginator 复用已知总数，去掉 paginator 的重复 count；
 *   去掉无用预加载；只 select 真正输出的列；`per_page` 上限收敛；
 *   `is_rebate` / `rebate_time` 生成列存在时改走 `mt4_trades_rebate_lookup_index` 索引区间扫描。
 *
 * 安全边界：
 * - 所有列表仍通过 AdminDataScopeService 套用后台角色数据范围，避免普通管理员越权查看其他代理或客户交易数据。
 * - 导出复用列表同一查询链路，CSV 口径与页面列表一致。
 */
class RealtimeCommissionController extends AdminBaseController
{
    /**
     * 旧项目用于识别实时返佣的 MT4 COMMENT 关键词。
     *
     * @var array<int, string>
     */
    public const REBATE_COMMENT_KEYWORDS = ['DBCN', '-FY'];

    /**
     * 列表每页条数默认值。
     *
     * @var int
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * 列表每页条数硬上限。
     *
     * 逻辑说明：
     * - 旧实现直接使用请求里的 per_page/limit，客户端可以请求十万行，
     *   既让 MySQL 传输巨量数据，也让 Layui 一次性渲染上万个 DOM 行造成页面冻结。
     *
     * @var int
     */
    private const MAX_PER_PAGE = 100;

    /**
     * 导出行数硬上限。
     *
     * @var int
     */
    private const EXPORT_ROW_LIMIT = 5000;

    /**
     * 列表与导出真正需要的列。
     *
     * 逻辑说明：
     * - 旧实现 `select *` 会把 87 万行的全部列读进内存；这里只取 formatRealtimeCommissionRecord 用到的字段。
     *
     * @var array<int, string>
     */
    private const SELECT_COLUMNS = [
        'id', 'ticket', 'login', 'symbol', 'cmd', 'volume', 'profit', 'commission',
        'swaps', 'comment', 'open_time', 'close_time', 'modify_time', 'created_at', 'updated_at',
    ];

    /**
     * 生成列可用性缓存。
     *
     * 逻辑说明：
     * - null 表示尚未探测；true/false 表示本进程内已确认 `is_rebate` 与 `rebate_time` 是否存在。
     * - 避免每次查询都打 information_schema。
     *
     * @var bool|null
     */
    private static $indexedRebateColumnsAvailable = null;

    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于按管理员角色、绑定代理和数据范围配置过滤交易记录。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询后台实时返佣列表。
     *
     * 参数说明：
     * - user_id：业务用户或 MT4 登录号，对应 `mt4_trades.login`。
     * - ticket/order_id：MT4 订单号，对应 `mt4_trades.ticket`，`order_id` 用于兼容旧项目参数命名。
     * - start_date/end_date：平仓时间范围，对应 `mt4_trades.close_time` 的 10 位时间戳。
     * - page：当前页码，兼容 Laravel paginator 与 Layui table。
     * - per_page/limit：每页条数，`limit` 用于兼容 Layui 默认参数名。
     *
     * @param Request $request 当前 HTTP 请求对象，读取筛选参数和当前 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse
     */
    public function realtimeCommissionList(Request $request)
    {
        if ($response = $this->validateUserIdFilter($request)) {
            return $response;
        }

        // 汇总与列表共用同一份筛选条件，但只跑一趟聚合：一次 SQL 同时拿 count 与 sum，
        // 再把已知总数交给手工 paginator，避免 Laravel paginate() 再补一次 count。
        $aggregate = $this->aggregateRealtimeCommissions($request);
        $totalRecords = (int) $aggregate['total_records'];
        $totalProfit = (float) $aggregate['total_profit'];

        $records = $this->paginateRealtimeCommissions($request, $totalRecords);

        return $this->success([
            'records' => $records,
            'summary' => [
                'total_records' => $totalRecords,
                'total_profit' => round($totalProfit, 2),
                'total_commission' => round($totalProfit, 2),
            ],
        ], __('admin.realtime_commissions_fetched'));
    }

    /**
     * 返回实时返佣统计模块（图表）所需的聚合数据。
     *
     * 参数说明：
     * - user_id、ticket/order_id、start_date、end_date 与列表接口共用同一筛选链路，
     *   保证图表口径和表格行、汇总卡片完全一致。
     *
     * 性能说明：
     * - 按天分组只做一趟 GROUP BY 聚合，不返回明细行，因此响应体固定为天数级别（不随返佣总量增长）。
     * - 分组维度直接使用 `rebate_time` 生成列（存在时），可命中 `mt4_trades_rebate_lookup_index`。
     *
     * @param Request $request 当前 HTTP 请求对象，承载与列表相同的筛选条件。
     * @return \Illuminate\Http\JsonResponse 图表用的按天序列与来源分布。
     */
    public function realtimeCommissionStatistics(Request $request)
    {
        if ($response = $this->validateUserIdFilter($request)) {
            return $response;
        }

        $timeExpression = $this->rebateTimeExpression();
        $rows = $this->baseRealtimeCommissionQuery($request)
            ->selectRaw(
                'FROM_UNIXTIME(' . $timeExpression . ', \'%Y-%m-%d\') AS rebate_day,'
                . ' COUNT(*) AS day_records,'
                . ' COALESCE(SUM(profit), 0) AS day_profit'
            )
            ->whereRaw($timeExpression . ' IS NOT NULL')
            ->groupByRaw('rebate_day')
            ->orderByRaw('rebate_day ASC')
            ->limit(180)
            ->get();

        $labels = [];
        $recordSeries = [];
        $profitSeries = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row->rebate_day;
            $recordSeries[] = (int) $row->day_records;
            $profitSeries[] = round((float) $row->day_profit, 2);
        }

        // 直接 SELECT 分组表达式本身（而不是 comment 原文），既满足 only_full_group_by，
        // 也保证结果集恒定只有三类来源。
        $sourceRows = $this->baseRealtimeCommissionQuery($request)
            ->selectRaw(
                $this->rebateSourceGroupExpression() . ' AS rebate_source,'
                . ' COUNT(*) AS source_records,'
                . ' COALESCE(SUM(profit), 0) AS source_profit'
            )
            ->groupByRaw($this->rebateSourceGroupExpression())
            ->limit(8)
            ->get();

        $sources = [];
        foreach ($sourceRows as $row) {
            $sourceKey = (string) $row->rebate_source;
            $sources[] = [
                'key' => $sourceKey,
                'name' => $this->rebateSourceName($sourceKey),
                'records' => (int) $row->source_records,
                'profit' => round((float) $row->source_profit, 2),
            ];
        }

        return $this->success([
            'labels' => $labels,
            'records' => $recordSeries,
            'profit' => $profitSeries,
            'sources' => $sources,
        ], __('admin.realtime_commissions_fetched'));
    }

    /**
     * 单趟聚合出实时返佣总条数与 profit 合计。
     *
     * 逻辑说明：
     * - 旧实现分别调用 count() 与 sum()，等于两次全表扫描；这里合并成一条 SQL。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选条件。
     * @return array{total_records: int, total_profit: float}
     */
    private function aggregateRealtimeCommissions(Request $request): array
    {
        $row = $this->baseRealtimeCommissionQuery($request)
            ->selectRaw('COUNT(*) AS total_records, COALESCE(SUM(profit), 0) AS total_profit')
            ->first();

        return [
            'total_records' => $row === null ? 0 : (int) $row->total_records,
            'total_profit' => $row === null ? 0.0 : (float) $row->total_profit,
        ];
    }

    /**
     * 使用已知总数构造实时返佣分页结果。
     *
     * 逻辑说明：
     * - 总数已由 aggregateRealtimeCommissions 得到，这里用 LengthAwarePaginator 复用它，
     *   避免 Laravel paginate() 再跑一次 `SELECT COUNT(*)`。
     * - 只取 SELECT_COLUMNS，且 per_page 收敛到 MAX_PER_PAGE 以内。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param int $totalRecords 当前筛选条件下的总条数。
     * @return LengthAwarePaginator 与旧响应结构一致的分页对象。
     */
    private function paginateRealtimeCommissions(Request $request, int $totalRecords): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);
        $page = $this->resolvePage($request);

        $items = [];
        if ($totalRecords > 0 && ($page - 1) * $perPage < $totalRecords) {
            $items = $this->orderByRebateTime($this->baseRealtimeCommissionQuery($request))
                ->select(self::SELECT_COLUMNS)
                ->forPage($page, $perPage)
                ->get()
                ->map(function (Mt4Trade $record): array {
                    return $this->formatRealtimeCommissionRecord($record);
                })
                ->all();
        }

        $paginator = new LengthAwarePaginator($items, $totalRecords, $perPage, $page, [
            'path' => $request->url(),
            'pageName' => 'page',
        ]);

        return $paginator->withQueryString();
    }

    /**
     * 解析并收敛每页条数。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 per_page，兼容 Layui 的 limit。
     * @return int 落在 1..MAX_PER_PAGE 区间内的每页条数。
     */
    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', $request->input('limit', self::DEFAULT_PER_PAGE));

        if ($perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }

    /**
     * 解析当前页码。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 page。
     * @return int 最小为 1 的页码。
     */
    private function resolvePage(Request $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    /**
     * 导出当前筛选条件下的实时返佣 CSV。
     *
     * 参数逻辑说明：
     * - user_id、ticket/order_id、start_date、end_date 与列表接口共用同一筛选链路。
     * - 导出只输出已通过 COMMENT 关键词识别的返佣记录，避免 CSV 绕过页面列表口径。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选条件。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportRealtimeCommissions(Request $request)
    {
        if ($response = $this->validateUserIdFilter($request)) {
            return $response;
        }

        $query = $this->baseRealtimeCommissionQuery($request);
        $rows = [
            ['id', 'ticket', 'login', 'symbol', 'cmd', 'volume', 'profit', 'commission', 'swaps', 'rebate_source', 'rebate_source_name', 'comment', 'open_time', 'close_time', 'modify_time', 'created_at', 'updated_at'],
        ];

        // 导出固定最多 EXPORT_ROW_LIMIT 行，避免一次性拉取过多 MT4 返佣记录拖慢后台。
        $this->orderByRebateTime($query)
            ->select(self::SELECT_COLUMNS)
            ->limit(self::EXPORT_ROW_LIMIT)
            ->get()
            ->each(function (Mt4Trade $record) use (&$rows) {
                $row = $this->formatRealtimeCommissionRecord($record);
                $rows[] = [
                    $row['id'],
                    $row['ticket'],
                    $row['login'],
                    $row['symbol'],
                    $row['cmd'],
                    $row['volume'],
                    $row['profit'],
                    $row['commission'],
                    $row['swaps'],
                    $row['rebate_source'],
                    $row['rebate_source_name'],
                    $row['comment'],
                    $row['open_time'],
                    $row['close_time'],
                    $row['modify_time'],
                    $row['created_at'],
                    $row['updated_at'],
                ];
            });

        return $this->csvDownload('realtime_commissions_export.csv', $rows);
    }

    /**
     * 构造实时返佣基础查询。
     *
     * @param Request $request 当前请求对象，承载筛选参数和后台登录管理员。
     * @return Builder 只包含旧 COMMENT 返佣关键词、正向余额记录和数据范围过滤后的查询对象。
     */
    private function baseRealtimeCommissionQuery(Request $request): Builder
    {
        // 不再预加载 user 关系：formatRealtimeCommissionRecord 的输出完全不含用户字段，
        // 旧的 with('user') 只会让每次请求额外全表扫描一次 user_infos（mt4_code 无索引）。
        $query = Mt4Trade::query()
            ->where('cmd', 6)
            ->where('profit', '>', 0);

        $this->applyRebateCommentFilter($query);
        $this->applyFilters($query, $request);
        $this->applyDataScope($query, $request, 'trade', 'login');

        return $query;
    }

    /**
     * 校验 user_id 筛选参数，避免非整数输入进入交易查询。
     *
     * @param Request $request 当前请求对象，用于读取 user_id。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回错误响应，校验通过返回 null。
     */
    private function validateUserIdFilter(Request $request)
    {
        if (!$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 追加旧项目 COMMENT 返佣关键词过滤。
     *
     * @param Builder $query MT4 交易查询对象，用于追加 comment LIKE 条件。
     * @return void
     */
    private function applyRebateCommentFilter(Builder $query): void
    {
        // 生成列可用时改用 `is_rebate = 1`：口径与关键词 LIKE 完全等价，但能命中
        // mt4_trades_rebate_lookup_index，把全表扫描换成索引区间扫描。
        if ($this->hasIndexedRebateColumns()) {
            $query->where('is_rebate', 1);

            return;
        }

        $query->where(function (Builder $where): void {
            foreach (self::REBATE_COMMENT_KEYWORDS as $keyword) {
                $where->orWhere('comment', 'LIKE', '%' . $keyword . '%');
            }
        });
    }

    /**
     * 判断 `mt4_trades` 是否已具备可索引的返佣生成列。
     *
     * 逻辑说明：
     * - 由 2026_08_28_000001_add_mt4_trades_rebate_lookup_index 迁移创建。
     * - 迁移未落库的环境自动回退到旧表达式口径，业务结果不变，只是没有索引加速。
     * - 结果缓存在静态属性里，避免每次查询都读 information_schema。
     *
     * @return bool 生成列齐备时返回 true。
     */
    private function hasIndexedRebateColumns(): bool
    {
        if (self::$indexedRebateColumnsAvailable === null) {
            self::$indexedRebateColumnsAvailable = Schema::hasColumn('mt4_trades', 'is_rebate')
                && Schema::hasColumn('mt4_trades', 'rebate_time');
        }

        return self::$indexedRebateColumnsAvailable;
    }

    /**
     * 重置生成列探测缓存。
     *
     * 适用场景：
     * - 功能测试在同一进程内先后验证「有生成列」和「无生成列」两条分支时调用。
     *
     * @return void
     */
    public static function resetIndexedRebateColumnCache(): void
    {
        self::$indexedRebateColumnsAvailable = null;
    }

    /**
     * 追加实时返佣查询筛选条件。
     *
     * @param Builder $query MT4 交易查询对象，用于追加 where 条件。
     * @param Request $request 当前请求对象，用于读取 user_id、ticket、order_id、start_date、end_date 参数。
     * @return void
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('login', (int) $request->input('user_id'));
        }

        $ticket = $request->input('ticket', $request->input('order_id'));
        if ($ticket !== null && $ticket !== '') {
            $query->where(function (Builder $where) use ($ticket): void {
                $where->where('ticket', 'LIKE', '%' . $ticket . '%')
                    ->orWhere('comment', 'LIKE', '%' . $ticket . '%');
            });
        }

        $this->applyTimestampRange($query, $request);
    }

    /**
     * 按当前后台管理员的数据范围过滤交易记录。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，用于读取 admin guard 登录管理员。
     * @param string $targetType 数据范围目标类型，实时返佣读取交易表，因此传入 trade。
     * @param string $userIdColumn 查询表中代表业务用户或 MT4 登录号的字段名，当前为 login。
     * @return void
     */
    private function applyDataScope(Builder $query, Request $request, string $targetType, string $userIdColumn): void
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return;
        }

        $this->adminDataScopeService->apply($query, $admin, $targetType, $userIdColumn);
    }

    /**
     * 按日期字符串追加 10 位时间戳范围过滤。
     *
     * @param Builder $query 业务查询对象。
     * @param Request $request 当前请求对象，读取 start_date 与 end_date。
     * @return void
     */
    private function applyTimestampRange(Builder $query, Request $request): void
    {
        $timeExpression = $this->rebateTimeExpression();

        if ($request->filled('start_date')) {
            $query->whereRaw($timeExpression . ' >= ?', [strtotime($request->input('start_date') . ' 00:00:00')]);
        }

        if ($request->filled('end_date')) {
            $query->whereRaw($timeExpression . ' <= ?', [strtotime($request->input('end_date') . ' 23:59:59')]);
        }
    }

    /**
     * 按返佣确认时间倒序排序。
     *
     * @param Builder $query MT4 交易查询对象。
     * @return Builder 已追加排序条件的查询对象。
     */
    private function orderByRebateTime(Builder $query): Builder
    {
        return $query->orderByRaw($this->rebateTimeExpression() . ' DESC');
    }

    /**
     * 生成返佣时间表达式。
     *
     * @return string SQL 片段，优先使用 modify_time，缺失或为 0 时回退到 close_time。
     */
    private function rebateTimeExpression(): string
    {
        // 旧项目确认返佣的时间以 MODIFY_TIME 为准；当前真实表为 0 或空时回退 close_time，避免排序和筛选漏单。
        // 生成列可用时直接引用 rebate_time 列：语义等价，但 ORDER BY / 范围过滤都能走索引，不再 filesort。
        if ($this->hasIndexedRebateColumns()) {
            return 'rebate_time';
        }

        return 'COALESCE(NULLIF(modify_time, 0), close_time)';
    }

    /**
     * 生成返佣来源分组表达式。
     *
     * 逻辑说明：
     * - 统计模块只需要按来源类型分布，不需要按每条 COMMENT 原文分组，
     *   因此把 COMMENT 折叠成 DBCN / -FY / 其他三类再分组，保证结果集恒定很小。
     *
     * @return string SQL 片段。
     */
    private function rebateSourceGroupExpression(): string
    {
        return "CASE WHEN comment LIKE '%DBCN%' THEN 'legacy_dbcn'"
            . " WHEN comment LIKE '%-FY%' THEN 'legacy_fy'"
            . " ELSE 'legacy_unknown' END";
    }

    /**
     * 格式化实时返佣记录。
     *
     * @param Mt4Trade $record MT4 返佣交易记录。
     * @return array<string, mixed> 前端列表和 CSV 导出共用的字段结构。
     */
    private function formatRealtimeCommissionRecord(Mt4Trade $record): array
    {
        $source = $this->rebateSource((string) $record->comment);

        return [
            'id' => (int) $record->id,
            'ticket' => (int) $record->ticket,
            'login' => (int) $record->login,
            'symbol' => $record->symbol,
            'cmd' => (int) $record->cmd,
            'volume' => (float) $record->volume,
            'profit' => (float) $record->profit,
            'commission' => (float) $record->commission,
            'swaps' => (float) $record->swaps,
            'rebate_source' => $source['key'],
            'rebate_source_name' => $source['name'],
            'comment' => (string) $record->comment,
            'open_time' => $record->open_time === null ? null : (int) $record->open_time,
            'close_time' => $record->close_time === null ? null : (int) $record->close_time,
            'modify_time' => $this->rebateTimeValue($record),
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ];
    }

    /**
     * 判断返佣来源类型。
     *
     * @param string $comment MT4 COMMENT 原文。
     * @return array{key: string, name: string} 来源编码和中文名称。
     */
    private function rebateSource(string $comment): array
    {
        if (stripos($comment, \App\Constants\Mt4RemarkCodes::DBCN) !== false) {
            return ['key' => 'legacy_dbcn', 'name' => '账户返佣'];
        }

        if (stripos($comment, '-FY') !== false) {
            return ['key' => 'legacy_fy', 'name' => '旧 FY 返佣'];
        }

        return ['key' => 'legacy_unknown', 'name' => '返佣记录'];
    }

    /**
     * 按来源编码返回来源名称。
     *
     * 逻辑说明：
     * - 与 rebateSource() 共用同一份名称，保证列表行和统计图表的来源命名完全一致。
     *
     * @param string $sourceKey 来源编码，取值为 legacy_dbcn、legacy_fy 或 legacy_unknown。
     * @return string 来源中文名称。
     */
    private function rebateSourceName(string $sourceKey): string
    {
        $names = [
            'legacy_dbcn' => '账户返佣',
            'legacy_fy' => '旧 FY 返佣',
        ];

        return $names[$sourceKey] ?? '返佣记录';
    }

    /**
     * 读取返佣时间值。
     *
     * @param Mt4Trade $record MT4 返佣交易记录。
     * @return int|null 优先返回 modify_time；缺失时返回 close_time；两者都缺失时返回 null。
     */
    private function rebateTimeValue(Mt4Trade $record): ?int
    {
        $modifyTime = (int) ($record->modify_time ?: 0);
        if ($modifyTime > 0) {
            return $modifyTime;
        }

        $closeTime = (int) ($record->close_time ?: 0);
        return $closeTime > 0 ? $closeTime : null;
    }

    /**
     * 生成流式 CSV 下载响应。
     *
     * @param string $fileName 下载文件名。
     * @param array<int, array<int, mixed>> $rows CSV 行数据。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
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
