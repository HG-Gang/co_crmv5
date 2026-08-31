<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:14
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\OperationLog;
use App\Models\SymbolPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * 后台产品/交易品种控制器。
 *
 * 文件功能：
 * - 提供产品/交易品种列表查询、CSV 导出，以及新增、编辑、删除维护入口。
 *
 * 功能逻辑说明：
 * - 旧项目 `AdminProductionController` 以 `symbol_prices` 和 `MT4_TRADES` 汇总交易品种持仓。
 * - 新项目第一阶段以当前真实表 `symbol_prices` 和 `mt4_trades` 为准，提供只读列表和当前持仓汇总。
 * - 本控制器提供新增、编辑、删除维护入口，写入 `symbol_prices` 时同步记录 `operation_logs`，旧项目真实 MT4 同步仍需单独迁移。
 *
 * 业务边界：
 * - 品种汇总的持仓口径：只统计 mt4_trades 中 cmd=0/1 且未平仓（close_time 为空或 0）的记录。
 * - 写入和删除动作都在事务内记录 operation_logs，供后台审计追踪。
 */
class ProductionController extends AdminBaseController
{
    /**
     * 查询后台产品/交易品种列表。
     *
     * 参数逻辑说明：
     * - symbol：交易品种编码，对应 `symbol_prices.symbol`，支持模糊筛选。
     * - group_id：品种分组 ID，对应 `symbol_prices.group_id`，用于按贵金属、能源、外汇等分组筛选。
     * - status：品种状态，对应 `symbol_prices.status`，用于区分启用或停用。
     * - page/per_page/limit：分页参数，兼容 Layui 表格默认提交的 `page` 和 `limit`。
     *
     * @param Request $request 当前请求对象，承载筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function productionList(Request $request)
    {
        if ($response = $this->validateNumericFilters($request)) {
            return $response;
        }

        $query = $this->baseProductionQuery();
        $this->applyFilters($query, $request);

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));

        $records = $query
            ->orderByDesc('symbol_prices.id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'records' => $records,
            'summary' => $this->summaryFor($records->items()),
        ], __('admin.productions_fetched'));
    }

    /**
     * 导出当前筛选条件下的产品/交易品种 CSV。
     *
     * 复用列表同一查询链路，导出固定最多 5000 行。
     *
     * @param Request $request 当前请求对象，承载筛选参数。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportProductions(Request $request)
    {
        if ($response = $this->validateNumericFilters($request)) {
            return $response;
        }

        $query = $this->baseProductionQuery();
        $this->applyFilters($query, $request);

        // 表头与下方行数据必须严格同序；avg_buy_price / avg_sell_price 紧随对应方向的手数列，
        // 与旧后台产量报表「均价—手数」成对出现的阅读顺序保持一致。
        $rows = [
            ['id', 'symbol', 'bid', 'ask', 'low', 'high', 'digits', 'spread', 'group_id', 'status', 'avg_buy_price', 'total_buy_volume', 'avg_sell_price', 'total_sell_volume', 'net_volume', 'float_profit_loss', 'modify_time', 'updated_at'],
        ];

        $query->orderByDesc('symbol_prices.id')
            ->limit(5000)
            ->get()
            ->each(function ($record) use (&$rows) {
                $rows[] = [
                    $record->id,
                    $record->symbol,
                    $record->bid,
                    $record->ask,
                    $record->low,
                    $record->high,
                    $record->digits,
                    $record->spread,
                    $record->group_id,
                    $record->status,
                    $record->avg_buy_price,
                    $record->total_buy_volume,
                    $record->avg_sell_price,
                    $record->total_sell_volume,
                    $record->net_volume,
                    $record->float_profit_loss,
                    $record->modify_time,
                    $record->updated_at,
                ];
            });

        return $this->csvDownload('productions_export.csv', $rows);
    }

    /**
     * 创建交易品种。
     *
     * 事务内写入 symbol_prices 并记录操作日志，任一失败整体回滚。
     *
     * @param Request $request 当前请求对象，承载品种字段。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新品种记录。
     */
    public function createProduction(Request $request)
    {
        $symbol = DB::transaction(function () use ($request) {
            $symbol = SymbolPrice::create($this->productionPayload($request));

            $this->writeProductionOperationLog(
                $request,
                'production:' . $symbol->id,
                sprintf(
                    'Create production symbol:%s; bid:%s; ask:%s; group_id:%s; status:%s',
                    $symbol->symbol,
                    $this->formatProductionValue($symbol->bid),
                    $this->formatProductionValue($symbol->ask),
                    (int) $symbol->group_id,
                    (int) $symbol->status
                )
            );

            return $symbol;
        });

        return $this->success($symbol, __('admin.production_created'));
    }

    /**
     * 更新交易品种。
     *
     * 事务内更新并记录变更前后快照日志；记录不存在时 findOrFail 抛出 404。
     *
     * @param Request $request 当前请求对象，承载待更新字段。
     * @param int|string $id 路由中的 symbol_prices.id。
     * @return \Illuminate\Http\JsonResponse 更新成功返回最新品种记录。
     */
    public function updateProduction(Request $request, $id)
    {
        $symbol = SymbolPrice::findOrFail($id);
        $before = $symbol->only(['bid', 'ask', 'group_id', 'status']);

        DB::transaction(function () use ($request, $symbol, $before) {
            $symbol->update($this->productionPayload($request, $symbol->id));
            $fresh = $symbol->fresh();

            $this->writeProductionOperationLog(
                $request,
                'production:' . $fresh->id,
                sprintf(
                    'Update production symbol:%s; bid:%s->%s; ask:%s->%s; group_id:%s->%s; status:%s->%s',
                    $fresh->symbol,
                    $this->formatProductionValue($before['bid']),
                    $this->formatProductionValue($fresh->bid),
                    $this->formatProductionValue($before['ask']),
                    $this->formatProductionValue($fresh->ask),
                    (int) $before['group_id'],
                    (int) $fresh->group_id,
                    (int) $before['status'],
                    (int) $fresh->status
                )
            );
        });

        return $this->success($symbol->fresh(), __('admin.production_updated'));
    }

    /**
     * 删除交易品种。
     *
     * 事务内先记录删除审计日志再删除记录。
     *
     * @param int|string $id 路由中的 symbol_prices.id。
     * @return \Illuminate\Http\JsonResponse 删除成功返回空数据。
     */
    public function deleteProduction($id)
    {
        $symbol = SymbolPrice::findOrFail($id);
        $request = request();

        DB::transaction(function () use ($request, $symbol) {
            $this->writeProductionOperationLog(
                $request,
                'production:' . $symbol->id,
                sprintf(
                    'Delete production symbol:%s; group_id:%s; status:%s',
                    $symbol->symbol,
                    (int) $symbol->group_id,
                    (int) $symbol->status
                )
            );

            $symbol->delete();
        });

        return $this->success([], __('admin.production_deleted'));
    }

    /**
     * 构建产品/交易品种基础查询。
     *
     * 字段逻辑说明：
     * - total_buy_volume：当前未平仓买入方向总手数，来源 `mt4_trades.volume`。
     * - total_sell_volume：当前未平仓卖出方向总手数，来源 `mt4_trades.volume`。
     * - net_volume：买入总手数减卖出总手数，用于延续旧项目产品净持仓展示口径。
     * - float_profit_loss：当前未平仓订单浮动盈亏合计，来源 `mt4_trades.profit`。
     * - avg_buy_price：买入方向均价，等价旧 AdminProductionController::get_mt4_trades_production_summary()
     *   的 `round(totalBuyOpenPrice / buyRecord, 2)`，即买单开仓价合计除以买单笔数。
     * - avg_sell_price：卖出方向均价，等价旧同一方法的 `round(totalSellClosePrice / sellRecord, 2)`，
     *   即卖单**平仓价**合计除以卖单笔数。
     *
     * 关于两个均价的口径不对称（买单取 open_price、卖单取 close_price）：
     * 这不是笔误，而是旧项目 AdminProductionController.php:229 与 :237 的既有行为，
     * 为保持列值与旧后台逐位一致而原样复刻；若改为同源字段会与旧报表产生数值差异。
     *
     * 关于旧查询中的 `MARGIN_RATE <> 0` 过滤：新库 `mt4_trades` 表结构没有 margin_rate 列
     * （见 2026_04_01_000014_create_mt4_trades_table.php），因此该条件在新项目无对应字段，
     * 两个均价与同行的 volume/profit 聚合共用同一份 join 结果，保证同一行内各列口径自洽。
     *
     * @return Builder
     */
    private function baseProductionQuery(): Builder
    {
        return SymbolPrice::query()
            ->leftJoin('mt4_trades', function ($join) {
                $join->on('symbol_prices.symbol', '=', 'mt4_trades.symbol')
                    ->whereIn('mt4_trades.cmd', [0, 1])
                    ->where(function ($query) {
                        $query->whereNull('mt4_trades.close_time')
                            ->orWhere('mt4_trades.close_time', 0);
                    });
            })
            ->select([
                'symbol_prices.id',
                'symbol_prices.symbol',
                'symbol_prices.bid',
                'symbol_prices.ask',
                'symbol_prices.low',
                'symbol_prices.high',
                'symbol_prices.digits',
                'symbol_prices.spread',
                'symbol_prices.group_id',
                'symbol_prices.status',
                'symbol_prices.modify_time',
                'symbol_prices.updated_at',
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN mt4_trades.cmd = 0 THEN mt4_trades.volume ELSE 0 END), 0) as total_buy_volume')
            ->selectRaw('COALESCE(SUM(CASE WHEN mt4_trades.cmd = 1 THEN mt4_trades.volume ELSE 0 END), 0) as total_sell_volume')
            ->selectRaw('COALESCE(SUM(CASE WHEN mt4_trades.cmd = 0 THEN mt4_trades.volume ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN mt4_trades.cmd = 1 THEN mt4_trades.volume ELSE 0 END), 0) as net_volume')
            ->selectRaw('COALESCE(SUM(mt4_trades.profit), 0) as float_profit_loss')
            // 买入均价：NULLIF 把「无买单」的分母置为 NULL 触发除法返回 NULL，再由 COALESCE 归零，
            // 与旧逻辑「buyRecord 为空则保持初始 0.00」一致，同时避免除零错误。
            ->selectRaw(
                'ROUND(COALESCE('
                . 'SUM(CASE WHEN mt4_trades.cmd = 0 THEN mt4_trades.open_price ELSE 0 END)'
                . ' / NULLIF(SUM(CASE WHEN mt4_trades.cmd = 0 THEN 1 ELSE 0 END), 0)'
                . ', 0), 2) as avg_buy_price'
            )
            // 卖出均价按旧逻辑取 close_price；新库该列可为 NULL（未平仓单未回填当前价），
            // 用 COALESCE 归零以复刻旧 SQL `else 0` 的意图，避免 SUM 跳过 NULL 导致分子偏小而均价被低估。
            ->selectRaw(
                'ROUND(COALESCE('
                . 'SUM(CASE WHEN mt4_trades.cmd = 1 THEN COALESCE(mt4_trades.close_price, 0) ELSE 0 END)'
                . ' / NULLIF(SUM(CASE WHEN mt4_trades.cmd = 1 THEN 1 ELSE 0 END), 0)'
                . ', 0), 2) as avg_sell_price'
            )
            ->groupBy(
                'symbol_prices.id',
                'symbol_prices.symbol',
                'symbol_prices.bid',
                'symbol_prices.ask',
                'symbol_prices.low',
                'symbol_prices.high',
                'symbol_prices.digits',
                'symbol_prices.spread',
                'symbol_prices.group_id',
                'symbol_prices.status',
                'symbol_prices.modify_time',
                'symbol_prices.updated_at'
            );
    }

    /**
     * 追加产品/交易品种筛选条件。
     *
     * @param Builder $query 产品/交易品种查询对象，用于追加 where 条件。
     * @param Request $request 当前请求对象，用于读取 symbol、group_id、status 参数。
     * @return void
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('symbol')) {
            $query->where('symbol_prices.symbol', 'LIKE', '%' . $request->input('symbol') . '%');
        }

        if ($request->filled('group_id')) {
            $query->where('symbol_prices.group_id', (int) $request->input('group_id'));
        }

        if ($request->filled('status')) {
            $query->where('symbol_prices.status', (int) $request->input('status'));
        }
    }

    /**
     * 校验可选数字筛选参数，避免非严格数字进入 SQL 前被强转。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，全部合法时返回 null。
     */
    private function validateNumericFilters(Request $request)
    {
        $data = [];
        $rules = [];

        foreach (['group_id', 'status'] as $field) {
            if ($request->filled($field)) {
                $data[$field] = $request->input($field);
                $rules[$field] = 'integer';
            }
        }

        if ($rules === []) {
            return null;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验并归一化品种写入字段。
     *
     * 默认值语义：
     * - symbol 统一转大写；time/modify_time 缺省取当前时间。
     * - low/high 缺省取 bid/ask 的较小/较大值；spread 缺省取 ask-bid 绝对值，保证报价区间自洽。
     *
     * @param Request $request 当前请求对象。
     * @param int|null $ignoreId 更新时排除的当前记录 ID，用于 symbol 唯一校验。
     * @return array<string, mixed> 可写入 symbol_prices 的字段。
     */
    private function productionPayload(Request $request, int $ignoreId = null): array
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:16', Rule::unique('symbol_prices', 'symbol')->ignore($ignoreId)->whereNull('deleted_at')],
            'time' => ['nullable', 'date'],
            'bid' => ['required', 'numeric'],
            'ask' => ['required', 'numeric'],
            'low' => ['nullable', 'numeric'],
            'high' => ['nullable', 'numeric'],
            'direction' => ['nullable', 'integer'],
            'digits' => ['nullable', 'integer', 'min:0', 'max:8'],
            'spread' => ['nullable', 'numeric'],
            'group_id' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
            'modify_time' => ['nullable', 'date'],
        ]);

        $now = now()->format('Y-m-d H:i:s');
        $data['symbol'] = strtoupper(trim($data['symbol']));
        $data['time'] = $data['time'] ?? $now;
        $data['low'] = $data['low'] ?? min((float) $data['bid'], (float) $data['ask']);
        $data['high'] = $data['high'] ?? max((float) $data['bid'], (float) $data['ask']);
        $data['direction'] = $data['direction'] ?? 0;
        $data['digits'] = $data['digits'] ?? 2;
        $data['spread'] = $data['spread'] ?? abs((float) $data['ask'] - (float) $data['bid']);
        $data['modify_time'] = $data['modify_time'] ?? $now;

        return $data;
    }

    /**
     * 记录品种维护操作日志。
     *
     * 未登录管理员会话时 admin_id/admin_name 置空，避免日志写入依赖登录态。
     *
     * @param Request $request 当前请求对象，读取管理员与 IP。
     * @param string $orderNo 业务单号，用于关联审计。
     * @param string $content 日志内容。
     * @return void
     */
    private function writeProductionOperationLog(Request $request, string $orderNo, string $content): void
    {
        $admin = $request->user('admin');

        OperationLog::create([
            'admin_id' => $admin ? (int) $admin->id : 0,
            'admin_name' => $admin ? (string) $admin->username : '',
            'target_user_id' => null,
            'order_no' => $orderNo,
            'content' => $content,
            'ip' => $request->ip() ?: '',
            'action_type' => 0,
        ]);
    }

    /**
     * 格式化品种数值展示，去除多余小数位。
     *
     * @param mixed $value 数值或字符串。
     * @return string 格式化后的字符串；非数字返回原值。
     */
    private function formatProductionValue($value): string
    {
        if (is_numeric($value)) {
            $formatted = rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');

            return $formatted === '' ? '0' : $formatted;
        }

        return (string) $value;
    }

    /**
     * 计算当前分页数据的页面汇总。
     *
     * @param array<int, mixed> $items 当前页产品/交易品种记录集合。
     * @return array<string, float|int> 返回总品种数、净持仓、浮动盈亏等页面汇总。
     */
    private function summaryFor(array $items): array
    {
        $totalNetVolume = 0;
        $totalProfit = 0;

        foreach ($items as $item) {
            $totalNetVolume += (float) ($item->net_volume ?? 0);
            $totalProfit += (float) ($item->float_profit_loss ?? 0);
        }

        return [
            'total_symbols' => count($items),
            'total_net_volume' => $totalNetVolume,
            'total_float_profit_loss' => $totalProfit,
        ];
    }

    /**
     * 生成流式 CSV 下载响应。
     *
     * @param string $fileName 下载文件名。
     * @param array<int, array<int, mixed>> $rows CSV 数据行，首行为表头。
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
