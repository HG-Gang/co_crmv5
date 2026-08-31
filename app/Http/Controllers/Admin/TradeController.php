<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:55
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Mt4Trade;
use App\Services\AdminDataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台交易管理控制器。
 *
 * 文件功能：
 * - 旧项目后台持仓和平仓列表主要读取 MT4_TRADES，新项目第一阶段统一改为读取当前真实表 `mt4_trades`。
 * - 当前真实表已补齐 `comment` 与 `modify_time`，因此历史平仓恢复旧项目 COMMENT 强平筛选和 MODIFY_TIME 展示/排序口径。
 * - `margin_rate` 尚未进入当前 mt4_trades 迁移，本控制器不伪造该过滤，避免把不存在字段包装成已闭环能力。
 * - 控制器同时兼容新项目 snake_case 参数和旧项目 Blade 的 userId、orderId、sym_symbol、startdate/enddate 参数。
 * - 业务 user_id 先通过 `user_infos.mt4_code = mt4_trades.login` 映射，避免把业务 ID 误当成 MT4 登录号。
 * - orderType=real_disk/test_disk 使用映射用户的 `user_infos.mt4_group` 后缀承接旧 `data_list.mt4_grp` 实盘/测试盘口径。
 * - 历史平仓导出复用列表查询链路，保证 CSV 与当前筛选结果一致。
 */
class TradeController extends AdminBaseController
{
    /**
     * 旧项目测试盘 MT4 分组后缀。
     */
    private const TEST_DISK_GROUP_SUFFIXES = ['-TEST', '-TEST-P'];

    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于按后台管理员角色和代理绑定限制可见 MT4 交易记录。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 获取全部 MT4 交易类订单列表。
     *
     * 参数含义：
     * - user_id：CRM 业务用户 ID，对应 `user_infos.user_id`，再通过 mt4_code 映射交易账号。
     * - userId：旧项目后台参数名，与 user_id 含义一致，不直接解释为 MT4 login。
     * - ticket：MT4 订单号，对应 `mt4_trades.ticket`，支持模糊搜索。
     * - orderId：旧项目后台订单号参数名，与 ticket 含义一致。
     * - symbol：交易品种，对应 `mt4_trades.symbol`。
     * - sym_symbol：旧项目后台品种参数名，与 symbol 含义一致。
     * - start_date/end_date 或 startdate/enddate：开仓时间范围，对应 `mt4_trades.open_time` 的 10 位时间戳。
     * - orderType：旧项目实盘/测试盘筛选，real_disk 排除测试组，test_disk 只取测试组。
     * - page/per_page/limit：分页页码和每页条数。
     *
     * @param Request $request 当前请求对象，用于读取筛选和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->baseMt4TradeQuery();
        $this->applyTradeFilters($query, $request, 'open_time');
        $this->applyDataScope($query, $request);

        return $this->success([
            'records' => $this->paginatedTradeRecords($query, $request, 'open_time'),
            'summary' => $this->summaryFor(clone $query),
        ], __('admin.trade_list_fetched'));
    }

    /**
     * 获取当前 MT4 持仓列表。
     *
     * 参数含义：
     * - user_id：CRM 业务用户 ID，对应 `user_infos.user_id`，再通过 mt4_code 映射交易账号。
     * - userId：旧项目后台参数名，与 user_id 含义一致，不直接解释为 MT4 login。
     * - ticket：MT4 订单号，对应 `mt4_trades.ticket`。
     * - orderId：旧项目后台订单号参数名，与 ticket 含义一致。
     * - symbol：交易品种，对应 `mt4_trades.symbol`。
     * - sym_symbol：旧项目后台品种参数名，与 symbol 含义一致。
     * - start_date/end_date 或 startdate/enddate：开仓时间范围，对应 `mt4_trades.open_time`。
     * - orderType：旧项目实盘/测试盘筛选，real_disk 排除测试组，test_disk 只取测试组。
     * - close_time：当前真实表中为空或 0 表示未平仓，本方法固定按该口径筛选。
     *
     * @param Request $request 当前请求对象，用于读取筛选和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function openPositions(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->baseMt4TradeQuery()
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('close_time')
                    ->orWhere('close_time', 0);
            });
        $this->applyTradeFilters($query, $request, 'open_time');
        $this->applyDataScope($query, $request);

        return $this->success([
            'records' => $this->paginatedTradeRecords($query, $request, 'open_time'),
            'summary' => $this->summaryFor(clone $query),
        ], __('admin.open_positions_fetched'));
    }

    /**
     * 获取 MT4 已平仓记录列表。
     *
     * 参数含义：
     * - user_id：CRM 业务用户 ID，对应 `user_infos.user_id`，再通过 mt4_code 映射交易账号。
     * - userId：旧项目后台参数名，与 user_id 含义一致，不直接解释为 MT4 login。
     * - ticket：MT4 订单号，对应 `mt4_trades.ticket`。
     * - orderId：旧项目后台订单号参数名，与 ticket 含义一致。
     * - symbol：交易品种，对应 `mt4_trades.symbol`。
     * - sym_symbol：旧项目后台品种参数名，与 symbol 含义一致。
     * - is_coercion：旧项目强平筛选，Yes 匹配 comment 以 so 开头，No 排除该类强平单。
     * - orderType：旧项目实盘/测试盘筛选，real_disk 排除测试组，test_disk 只取测试组。
     * - start_date/end_date 或 startdate/enddate：平仓时间范围，对应 `mt4_trades.close_time`。
     * - close_time：当前真实表中大于 0 表示已经平仓，本方法固定按该口径筛选。
     *
     * @param Request $request 当前请求对象，用于读取筛选和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function closedPositions(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->closedPositionsQuery($request);

        return $this->success([
            'records' => $this->paginatedTradeRecords($query, $request, 'modify_time'),
            'summary' => $this->summaryFor(clone $query),
        ], __('admin.closed_positions_fetched'));
    }

    /**
     * 导出当前筛选条件下的 MT4 历史平仓记录。
     *
     * 参数含义：
     * - 复用 `closedPositions` 的 user_id/userId、ticket/orderId、symbol/sym_symbol、start_date/startdate、end_date/enddate、is_coercion 和 orderType。
     * - limit 固定最多导出 5000 行，避免一次性拉取过多 MT4 历史记录拖慢后台。
     *
     * 返回结果：
     * - 成功时返回 `closed_positions_export.csv`，字段包含旧项目核对所需的 ticket、login、symbol、comment、ordercomment、modify_time。
     * - user_id 非严格整数时返回统一校验失败响应，不输出 CSV。
     *
     * @param Request $request 当前请求对象，承载筛选参数和后台登录管理员。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportClosedPositions(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $rows = [
            ['ticket', 'login', 'symbol', 'cmd', 'volume', 'commission', 'swaps', 'profit', 'comment', 'ordercomment', 'open_time', 'close_time', 'modify_time'],
        ];

        $query = $this->closedPositionsQuery($request);
        $this->orderByTradeTime($query, 'modify_time');

        $query->limit(5000)
            ->get()
            ->each(function (Mt4Trade $record) use (&$rows): void {
                $row = $this->formatTradeRecord($record);
                $rows[] = [
                    $row['ticket'],
                    $row['login'],
                    $row['symbol'],
                    $row['cmd'],
                    $row['volume'],
                    $row['commission'],
                    $row['swaps'],
                    $row['profit'],
                    $row['comment'],
                    $row['ordercomment'],
                    $row['open_time'],
                    $row['close_time'],
                    $row['modify_time'],
                ];
            });

        return $this->csvDownload('closed_positions_export.csv', $rows);
    }

    /**
     * 获取当前持仓按交易品种分组的概览统计。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $summary = $this->baseMt4TradeQuery()
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('close_time')
                    ->orWhere('close_time', 0);
            });

        $this->applyDataScope($summary, $request);

        $summary = $summary
            ->select('symbol', DB::raw('SUM(volume) as total_volume'), DB::raw('count(*) as count'))
            ->groupBy('symbol')
            ->get();

        return $this->success($summary, __('admin.trade_summary_fetched'));
    }

    /**
     * 构造 MT4 交易类订单基础查询。
     *
     * 字段说明：
     * - cmd：MT4 订单类型，0 到 5 属于交易/挂单类，6 属于余额类资金流水，不进入持仓和平仓列表。
     * - user：通过 `mt4_trades.login = user_infos.mt4_code` 关联业务用户资料，便于按业务用户筛选和授权。
     *
     * @return Builder
     */
    private function baseMt4TradeQuery(): Builder
    {
        return Mt4Trade::query()
            ->leftJoin('user_infos', function ($join) {
                $join->on('user_infos.mt4_code', '=', 'mt4_trades.login')
                    ->whereNull('user_infos.deleted_at');
            })
            ->with('user')
            ->select('mt4_trades.*')
            ->whereIn('mt4_trades.cmd', [0, 1, 2, 3, 4, 5]);
    }

    /**
     * 构造历史平仓查询对象。
     *
     * 统一封装原因：
     * - 列表和导出都必须使用完全一致的旧项目筛选口径。
     * - `close_time > 0` 表示已平仓，`applyForceCloseFilter` 承接旧 COMMENT 强平筛选，`applyDataScope` 继续限制后台可见范围。
     *
     * @param Request $request 当前请求对象，读取筛选参数和后台登录管理员。
     * @return Builder 已追加平仓、筛选、强平和数据范围条件的查询对象。
     */
    private function closedPositionsQuery(Request $request): Builder
    {
        $query = $this->baseMt4TradeQuery()
            ->where('close_time', '>', 0);

        $this->applyTradeFilters($query, $request, 'close_time');
        $this->applyForceCloseFilter($query, $request);
        $this->applyDataScope($query, $request);

        return $query;
    }

    /**
     * 校验业务用户 ID 筛选参数必须是整数。
     *
     * @param Request $request 当前请求对象，读取 user_id 或 userId。
     * @return \Illuminate\Http\JsonResponse|null 参数非法时返回统一校验失败响应，否则返回 null。
     */
    private function validateUserIdFilter(Request $request)
    {
        $userId = $this->firstFilledInput($request, ['user_id', 'userId']);
        if ($userId === null) {
            return null;
        }

        $validator = Validator::make(['user_id' => $userId], [
            'user_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 追加后台交易列表筛选条件。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，读取新旧用户、订单、品种和日期筛选参数。
     * @param string $timeColumn 时间字段名；持仓按 open_time，平仓按 close_time。
     * @return void
     */
    private function applyTradeFilters(Builder $query, Request $request, string $timeColumn): void
    {
        $userId = $this->firstFilledInput($request, ['user_id', 'userId']);
        if ($userId !== null) {
            // user_id 是 CRM 业务用户 ID；真实订单账号必须由 user_infos.mt4_code 映射。
            $query->where('user_infos.user_id', (int) $userId);
        }

        $ticket = $this->firstFilledInput($request, ['ticket', 'order_id', 'orderId']);
        if ($ticket !== null) {
            $query->where('mt4_trades.ticket', 'LIKE', '%' . $ticket . '%');
        }

        $symbol = $this->firstFilledInput($request, ['symbol', 'sym_symbol']);
        if ($symbol !== null) {
            $query->where('mt4_trades.symbol', $symbol);
        }

        $this->applyTimestampRange($query, $request, $timeColumn);
        $this->applyOrderTypeFilter($query, $request);
    }

    /**
     * 追加旧项目历史平仓强平筛选。
     *
     * @param Builder $query MT4 平仓查询对象。
     * @param Request $request 当前请求对象，读取 is_coercion 字段。
     * @return void
     */
    private function applyForceCloseFilter(Builder $query, Request $request): void
    {
        $isCoercion = $this->firstFilledInput($request, ['is_coercion']);
        if ($isCoercion === 'Yes') {
            $query->where('comment', 'LIKE', 'so%');
            return;
        }

        if ($isCoercion === 'No') {
            $query->where('comment', 'NOT LIKE', 'so%');
        }
    }

    /**
     * 追加旧项目实盘/测试盘筛选。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，读取 orderType 或 order_type。
     * @return void
     */
    private function applyOrderTypeFilter(Builder $query, Request $request): void
    {
        $orderType = $this->firstFilledInput($request, ['orderType', 'order_type']);
        if ($orderType === 'test_disk') {
            $this->applyTestDiskGroupWhere($query);
            return;
        }

        if ($orderType === 'real_disk') {
            $query->where(function (Builder $scope): void {
                $scope->whereNull('user_infos.user_id')
                    ->orWhereNull('user_infos.mt4_group')
                    ->orWhere(function (Builder $where): void {
                        foreach (self::TEST_DISK_GROUP_SUFFIXES as $suffix) {
                            $where->where('user_infos.mt4_group', 'NOT LIKE', '%' . $suffix);
                        }
                    });
            });
        }
    }

    /**
     * 追加测试盘 MT4 分组后缀条件。
     *
     * @param Builder $query 已关联 user_infos 的 MT4 交易查询对象。
     * @return void
     */
    private function applyTestDiskGroupWhere(Builder $query): void
    {
        $query->where(function (Builder $where): void {
            foreach (self::TEST_DISK_GROUP_SUFFIXES as $suffix) {
                $where->orWhere('user_infos.mt4_group', 'LIKE', '%' . $suffix);
            }
        });
    }

    /**
     * 追加 10 位时间戳范围筛选。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，读取 start_date 和 end_date。
     * @param string $column 时间戳字段名，例如 open_time 或 close_time。
     * @return void
     */
    private function applyTimestampRange(Builder $query, Request $request, string $column): void
    {
        $startDate = $this->firstFilledInput($request, ['start_date', 'startdate']);
        if ($startDate !== null) {
            $query->where($column, '>=', strtotime($startDate . ' 00:00:00'));
        }

        $endDate = $this->firstFilledInput($request, ['end_date', 'enddate']);
        if ($endDate !== null) {
            $query->where($column, '<=', strtotime($endDate . ' 23:59:59'));
        }
    }

    /**
     * 追加后台管理员数据范围过滤。
     *
     * 参数含义：
     * - $query：当前 MT4 交易查询对象，调用方已追加列表/持仓/平仓基础条件。
     * - $request：当前请求对象，用于从 admin guard 读取已登录管理员。
     * - 查询已经通过 user_infos.mt4_code 关联业务用户，因此数据范围必须约束 user_infos.user_id。
     * - targetType=user 保持 custom_users、custom_agents 与 agent_tree 都按 CRM 业务用户集合计算。
     *
     * @param Builder $query MT4 交易查询对象。
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
     * 计算当前筛选条件下的交易汇总。
     *
     * 返回字段含义：
     * - total_orders：订单数量。
     * - total_volume：交易手数/成交量合计，直接沿用当前真实表 `volume` 数值。
     * - total_profit：盈亏合计。
     * - total_swaps：库存费合计。
     * - total_commission：手续费合计。
     *
     * @param Builder $query 已追加业务筛选条件的 MT4 交易查询对象。
     * @return array<string, float|int>
     */
    private function summaryFor(Builder $query): array
    {
        // 列表基础查询已选择 mt4_trades.*；汇总必须重置列和排序，避免 ONLY_FULL_GROUP_BY 拒绝明细列与聚合列混用。
        $summary = $query
            ->reorder()
            ->select([
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(mt4_trades.volume), 0) as total_volume'),
                DB::raw('COALESCE(SUM(mt4_trades.profit), 0) as total_profit'),
                DB::raw('COALESCE(SUM(mt4_trades.swaps), 0) as total_swaps'),
                DB::raw('COALESCE(SUM(mt4_trades.commission), 0) as total_commission'),
            ])
            ->first();

        return [
            'total_orders' => (int) ($summary->total_orders ?? 0),
            'total_volume' => (float) ($summary->total_volume ?? 0),
            'total_profit' => (float) ($summary->total_profit ?? 0),
            'total_swaps' => (float) ($summary->total_swaps ?? 0),
            'total_commission' => (float) ($summary->total_commission ?? 0),
        ];
    }

    /**
     * 按请求参数分页返回查询结果。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象；page 为页码，per_page/limit 为每页数量。
     * @param string $orderColumn 排序字段名；持仓按 open_time，平仓按 close_time。
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function paginateQuery(Builder $query, Request $request, string $orderColumn)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));

        $this->orderByTradeTime($query, $orderColumn);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 分页并格式化交易记录。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，用于读取分页参数。
     * @param string $orderColumn 排序字段，历史平仓传入 modify_time。
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function paginatedTradeRecords(Builder $query, Request $request, string $orderColumn)
    {
        $records = $this->paginateQuery($query, $request, $orderColumn);
        $records->getCollection()->transform(function (Mt4Trade $record): array {
            return $this->formatTradeRecord($record);
        });

        return $records;
    }

    /**
     * 按旧项目交易时间口径追加排序。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param string $orderColumn 排序字段；平仓传入 modify_time 时会回退 close_time。
     * @return void
     */
    private function orderByTradeTime(Builder $query, string $orderColumn): void
    {
        if ($orderColumn === 'modify_time') {
            $query->orderByRaw('COALESCE(NULLIF(mt4_trades.modify_time, 0), mt4_trades.close_time) DESC');
            return;
        }

        $query->orderByDesc('mt4_trades.' . $orderColumn);
    }

    /**
     * 格式化 MT4 交易记录，统一新旧前端字段名。
     *
     * @param Mt4Trade $record MT4 交易记录。
     * @return array<string, mixed> 前端表格可直接渲染的交易字段。
     */
    private function formatTradeRecord(Mt4Trade $record): array
    {
        $row = $record->toArray();
        $comment = (string) ($record->comment ?? '');

        $row['id'] = (int) ($record->id ?? 0);
        $row['ticket'] = (int) $record->ticket;
        $row['login'] = (int) $record->login;
        $row['cmd'] = (int) $record->cmd;
        $row['volume'] = (float) $record->volume;
        $row['commission'] = (float) $record->commission;
        $row['swaps'] = (float) $record->swaps;
        $row['profit'] = (float) $record->profit;
        $row['comment'] = $comment;
        $row['ordercomment'] = $comment;
        $row['modify_time'] = $record->modify_time === null ? null : (int) $record->modify_time;

        return $row;
    }

    /**
     * 读取第一个有值的请求参数。
     *
     * @param Request $request 当前请求对象。
     * @param array<int, string> $keys 按优先级排列的新旧参数名。
     * @return string|null 找到时返回字符串值，未传或为空时返回 null。
     */
    private function firstFilledInput(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                return (string) $request->input($key);
            }
        }

        return null;
    }

    /**
     * 生成流式 CSV 下载响应。
     *
     * @param string $fileName 下载文件名。
     * @param array<int, array<int, mixed>> $rows CSV 行数据，第一行为表头。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function csvDownload(string $fileName, array $rows)
    {
        return response()->streamDownload(function () use ($rows): void {
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
