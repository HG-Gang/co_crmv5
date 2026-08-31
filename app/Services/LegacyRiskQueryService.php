<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:18
 */

namespace App\Services;

use App\Models\Admin;
use App\Models\UserTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 文件功能：旧风控只读查询服务，统一承载现代 API 与旧兼容入口的数据口径。
 */
class LegacyRiskQueryService
{
    /**
     * MT4 “平衡交易”（balance operation）的时间占位值：cmd=6 的出入金/佣金记录 close_time 统一为 Unix 纪元。
     * 查询用它识别未平仓的真实交易（close_time 不等于该值）与历史余额类记录；
     * 换成其他时间戳会把出入金误计入盈亏/持仓统计，风险口径随之失真。
     *
     * @var string
     */
    private const OPEN_CLOSE_TIME = '1970-01-01 00:00:00';

    /**
     * 旧协议入金类 COMMENT 码的 REGEXP 交替串（与 FrontLegacyData 入金码集一一对应）。
     * 盈利榜的 total_yuerj 只认 cmd=6 且 COMMENT 命中该串的负/正利润记录；增删码会直接改变入金统计口径。
     *
     * @var string
     */
    private const DEPOSIT_COMMENT_PATTERN = 'DBAA|DBCT|DBGN|DBMN|DBPA|DBPN|DBSN|DBTN|DBUN|DBZN|DBAD|WBIR';

    /**
     * 旧协议出金类 COMMENT 码的 REGEXP 交替串（与 FrontLegacyData 出金码集一一对应，含历史特殊码 DBZR）。
     * total_yuecj 只统计命中该串的记录；与入金串共同构成资金进出统计的分界。
     *
     * @var string
     */
    private const WITHDRAW_COMMENT_PATTERN = 'WBAA|WBCN|WBCT|WBHN|WBIN|WBPN|WBSN|WBTN|WBAD|DBZR';

    /**
     * 后台数据范围服务：风控盈利榜按管理员的可见代理树裁剪用户集合；
     * 缺失时任何管理员可查看全量用户的盈亏与出入金汇总，属敏感资金数据越权。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询按业务用户聚合的盈利风险。
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, summary: array<string, mixed>}
     */
    public function profitPage(Request $request, ?Admin $admin): array
    {
        $filters = $this->profitFilters($request);
        $query = $this->profitQuery($filters, $admin);
        $total = (int) (clone $query)->count('user_infos.id');

        $rows = (clone $query)
            ->orderByDesc('user_infos.created_at')
            ->orderByDesc('user_infos.user_id')
            ->forPage($filters['page'], $filters['per_page'])
            ->get()
            ->map(function ($row): array {
                $item = (array) $row;
                foreach ([
                    'user_id',
                    'parent_id',
                    'trans_mode',
                    'mt4_code',
                    'user_status',
                    'voided',
                    'IDcard_status',
                    'bank_status',
                    'mt4_login',
                ] as $integerField) {
                    $item[$integerField] = (int) ($item[$integerField] ?? 0);
                }

                $deposit = (string) ($item['total_yuerj_decimal'] ?? '0');
                $withdraw = (string) ($item['total_yuecj_decimal'] ?? '0');
                $commission = (string) ($item['total_comm_decimal'] ?? '0');
                $profit = (string) ($item['total_profit_decimal'] ?? '0');
                $volume = bcdiv((string) ($item['total_volume_decimal'] ?? '0'), '100', 10);
                $absoluteWithdraw = bccomp($withdraw, '0', 10) < 0
                    ? bcsub('0', $withdraw, 10)
                    : $withdraw;

                $item['cust_eqy'] = $this->decimal((string) ($item['cust_eqy_decimal'] ?? '0'));
                $item['mt4_balance'] = $this->decimal((string) ($item['mt4_balance_decimal'] ?? '0'));
                $item['mt4_equity'] = $this->decimal((string) ($item['mt4_equity_decimal'] ?? '0'));
                $registeredAt = (int) ($item['mt4_regdate_timestamp'] ?? 0);
                $item['mt4_regdate'] = $registeredAt > 0
                    ? date('Y-m-d H:i:s', $registeredAt)
                    : '';
                $item['total_comm'] = $this->decimal($commission);
                $item['total_yuerj'] = $this->decimal($deposit);
                $item['total_yuecj'] = $this->decimal($withdraw);
                $item['total_volume'] = $this->decimal($volume);
                $item['total_swaps'] = $this->decimal((string) ($item['total_swaps_decimal'] ?? '0'));
                $item['total_profit'] = $this->decimal($profit);
                $item['total_net_worth'] = $this->decimal(bcsub($deposit, $absoluteWithdraw, 10));
                $item['feng_xian_val'] = bccomp($deposit, '0', 10) === 0
                    ? '0.00'
                    : $this->decimal(
                        bcmul(
                            bcdiv(bcsub($profit, $commission, 10), $deposit, 10),
                            '100',
                            10
                        )
                    );

                foreach ([
                    'cust_eqy_decimal',
                    'mt4_balance_decimal',
                    'mt4_equity_decimal',
                    'mt4_regdate_timestamp',
                    'total_comm_decimal',
                    'total_yuerj_decimal',
                    'total_yuecj_decimal',
                    'total_volume_decimal',
                    'total_swaps_decimal',
                    'total_profit_decimal',
                ] as $internalField) {
                    unset($item[$internalField]);
                }

                return $item;
            })
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'summary' => $this->profitSummary(clone $query),
        ];
    }

    /** @return array{field: string, message: string}|null */
    public function validateProfitFilters(Request $request): ?array
    {
        $filters = $this->profitFilters($request);
        $payload = [
            'user_id' => $filters['user_id'],
            'user_name' => $filters['user_name'],
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
            'page' => $request->input('page', 1),
            'rows' => $request->input('rows'),
            'limit' => $request->input('limit'),
            'per_page' => $request->input('per_page'),
        ];
        $validator = Validator::make($payload, [
            'user_id' => 'nullable|integer|min:1',
            'user_name' => 'nullable|string|max:200',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'rows' => 'nullable|integer|min:1|max:500',
            'limit' => 'nullable|integer|min:1|max:500',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);
        if ($validator->fails()) {
            $field = (string) array_key_first($validator->errors()->toArray());

            return [
                'field' => $field,
                'message' => $validator->errors()->first($field),
            ];
        }
        if ($filters['start_date'] > $filters['end_date']) {
            return [
                'field' => 'date_range',
                'message' => __('validation.after_or_equal', [
                    'attribute' => 'end_date',
                    'date' => 'start_date',
                ]),
            ];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function profitFilters(Request $request): array
    {
        $startDate = $request->input('start_date', $request->input('startdate'));
        $endDate = $request->input('end_date', $request->input('enddate'));

        return [
            'user_id' => $request->input('user_id', $request->input('userId')),
            'user_name' => $request->input('user_name', $request->input('username')),
            'start_date' => $startDate === null || $startDate === '' ? '2024-01-01' : $startDate,
            'end_date' => $endDate === null || $endDate === '' ? date('Y-m-d') : $endDate,
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => $this->positionPerPage($request),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function profitQuery(array $filters, ?Admin $admin)
    {
        $query = DB::table('user_infos')
            ->join('mt4_users', function ($join): void {
                $join->on('mt4_users.login', '=', 'user_infos.mt4_code')
                    ->whereNull('mt4_users.deleted_at');
            })
            ->leftJoin('user_auths', function ($join): void {
                $join->on('user_auths.user_id', '=', 'user_infos.user_id')
                    ->whereNull('user_auths.deleted_at');
            })
            ->joinSub($this->profitTradeAggregateQuery(), 'profit_totals', function ($join): void {
                $join->on('profit_totals.user_id', '=', 'user_infos.user_id');
            })
            ->whereNull('user_infos.deleted_at')
            ->whereIn('user_infos.auth_status', [0, 1, 2])
            ->where('user_infos.mt4_code', '>', 0)
            ->whereRaw('(
                profit_totals.total_profit_decimal
                - profit_totals.total_comm_decimal
            ) > 0')
            ->select([
                'user_infos.user_id',
                'user_infos.user_name',
                'user_infos.parent_id',
                'user_infos.trading_mode as trans_mode',
                'user_infos.mt4_code',
                'user_infos.mt4_group as mt4_grp',
                'user_infos.auth_status as user_status',
                'mt4_users.login as mt4_login',
                'mt4_users.name as mt4_name',
            ])
            ->selectRaw('CASE WHEN user_infos.is_mt4_synced = 0 THEN 2 ELSE 1 END as voided')
            ->selectRaw('COALESCE(user_auths.id_card_status, 0) as IDcard_status')
            ->selectRaw('COALESCE(user_auths.bank_status, 0) as bank_status')
            ->selectRaw('CAST(user_infos.equity AS DECIMAL(65, 10)) as cust_eqy_decimal')
            ->selectRaw('CAST(mt4_users.balance AS DECIMAL(65, 10)) as mt4_balance_decimal')
            ->selectRaw('CAST(mt4_users.equity AS DECIMAL(65, 10)) as mt4_equity_decimal')
            ->selectRaw('mt4_users.created_at as mt4_regdate_timestamp')
            ->addSelect([
                'profit_totals.total_comm_decimal',
                'profit_totals.total_yuerj_decimal',
                'profit_totals.total_yuecj_decimal',
                'profit_totals.total_volume_decimal',
                'profit_totals.total_swaps_decimal',
                'profit_totals.total_profit_decimal',
            ]);

        if ($filters['user_id'] !== null && $filters['user_id'] !== '') {
            $userId = (int) $filters['user_id'];
            $query->where(function ($subQuery) use ($userId): void {
                $subQuery->where('user_infos.user_id', $userId)
                    ->orWhere('mt4_users.login', $userId);
            });
        }
        if ($filters['user_name'] !== null && $filters['user_name'] !== '') {
            $query->where('mt4_users.name', 'like', '%' . $filters['user_name'] . '%');
        }
        $query->whereBetween('mt4_users.created_at', [
            strtotime($filters['start_date'] . ' 00:00:00'),
            strtotime($filters['end_date'] . ' 23:59:59'),
        ]);

        if ($admin) {
            $this->adminDataScopeService->apply($query, $admin, 'user', 'user_infos.user_id');
        }

        return $query;
    }

    private function profitTradeAggregateQuery()
    {
        $closedTrade = "user_trades.cmd IN (0, 1, 2, 3, 4, 5)
            AND user_trades.close_time > ?
            AND user_trades.margin_rate <> 0";

        return DB::table('user_trades')
            ->whereNull('user_trades.deleted_at')
            ->select('user_trades.user_id')
            ->selectRaw("ABS(COALESCE(SUM(CASE WHEN {$closedTrade}
                THEN CAST(user_trades.commission AS DECIMAL(65, 10)) ELSE 0 END), 0))
                as total_comm_decimal", [self::OPEN_CLOSE_TIME])
            ->selectRaw("COALESCE(SUM(CASE WHEN user_trades.cmd = 6
                    AND user_trades.profit > 0 AND user_trades.comment REGEXP ?
                THEN CAST(user_trades.profit AS DECIMAL(65, 10)) ELSE 0 END), 0)
                as total_yuerj_decimal", [self::DEPOSIT_COMMENT_PATTERN])
            ->selectRaw("COALESCE(SUM(CASE WHEN user_trades.cmd = 6
                    AND user_trades.profit < 0 AND user_trades.comment REGEXP ?
                THEN CAST(user_trades.profit AS DECIMAL(65, 10)) ELSE 0 END), 0)
                as total_yuecj_decimal", [self::WITHDRAW_COMMENT_PATTERN])
            ->selectRaw("COALESCE(SUM(CASE WHEN {$closedTrade}
                THEN CAST(user_trades.volume AS DECIMAL(65, 10)) ELSE 0 END), 0)
                as total_volume_decimal", [self::OPEN_CLOSE_TIME])
            ->selectRaw("ABS(COALESCE(SUM(CASE WHEN {$closedTrade} AND user_trades.swaps < 0
                THEN CAST(user_trades.swaps AS DECIMAL(65, 10)) ELSE 0 END), 0))
                as total_swaps_decimal", [self::OPEN_CLOSE_TIME])
            ->selectRaw("COALESCE(SUM(CASE WHEN {$closedTrade}
                THEN CAST(user_trades.profit AS DECIMAL(65, 10)) ELSE 0 END), 0)
                as total_profit_decimal", [self::OPEN_CLOSE_TIME])
            ->groupBy('user_trades.user_id');
    }

    /** @return array<string, int|string> */
    private function profitSummary($query): array
    {
        $summary = DB::query()
            ->fromSub($query, 'risk_profit_users')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('CAST(COALESCE(SUM(total_comm_decimal), 0) AS CHAR) as total_comm')
            ->selectRaw('CAST(COALESCE(SUM(total_yuerj_decimal), 0) AS CHAR) as total_yuerj')
            ->selectRaw('CAST(COALESCE(SUM(total_yuecj_decimal), 0) AS CHAR) as total_yuecj')
            ->selectRaw('CAST(COALESCE(SUM(total_swaps_decimal), 0) AS CHAR) as total_swaps')
            ->selectRaw('CAST(COALESCE(SUM(total_profit_decimal), 0) AS CHAR) as total_profit')
            ->selectRaw('CAST(COALESCE(SUM(total_volume_decimal), 0) AS CHAR) as total_volume')
            ->selectRaw('CAST(COALESCE(SUM(total_yuerj_decimal + total_yuecj_decimal), 0) AS CHAR) as total_net_worth')
            ->selectRaw('CAST(COALESCE(SUM(total_profit_decimal - total_comm_decimal), 0) AS CHAR) as total_risk_value')
            ->first();

        return [
            'total_records' => (int) ($summary->total_records ?? 0),
            'total_comm' => $this->decimal((string) ($summary->total_comm ?? '0')),
            'total_yuerj' => $this->decimal((string) ($summary->total_yuerj ?? '0')),
            'total_yuecj' => $this->decimal((string) ($summary->total_yuecj ?? '0')),
            'total_swaps' => $this->decimal((string) ($summary->total_swaps ?? '0')),
            'total_profit' => $this->decimal((string) ($summary->total_profit ?? '0')),
            'total_volume' => $this->decimal(
                bcdiv((string) ($summary->total_volume ?? '0'), '100', 10)
            ),
            'total_net_worth' => $this->decimal((string) ($summary->total_net_worth ?? '0')),
            'total_risk_value' => $this->decimal((string) ($summary->total_risk_value ?? '0')),
            'total_margin' => '0.00',
        ];
    }

    /**
     * 查询旧持仓风险分页，返回现代和旧入口都可直接格式化的稳定结构。
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, summary: array<string, mixed>}
     */
    public function positionPage(Request $request, ?Admin $admin, bool $useLegacyDefaultDates = false): array
    {
        $filters = $this->positionFilters($request, $useLegacyDefaultDates);
        $query = $this->positionQuery($filters, $admin);
        $total = (int) (clone $query)->count('user_trades.id');

        $rows = (clone $query)
            ->orderByDesc('user_trades.open_time')
            ->orderByDesc('user_trades.ticket')
            ->forPage($filters['page'], $filters['per_page'])
            ->get()
            ->map(function ($row): array {
                $item = $row->getAttributes();
                $profit = (string) ($item['profit_decimal'] ?? '0');
                $commission = (string) ($item['commission_decimal'] ?? '0');
                $absoluteCommission = bccomp($commission, '0', 10) < 0
                    ? bcsub('0', $commission, 10)
                    : $commission;
                $riskValue = bcsub($profit, $absoluteCommission, 10);

                $item['commission'] = $this->decimal($commission);
                $item['profit'] = $this->decimal($profit);
                $item['swaps'] = $this->decimal((string) ($item['swaps'] ?? '0'));
                $item['risk_value'] = $this->decimal($riskValue);
                $item['abs_comm'] = $this->decimal($absoluteCommission);
                $item['feng_xian_positionval'] = bccomp($absoluteCommission, '0', 10) === 0
                    ? $this->decimal($profit)
                    : $this->decimal(
                        bcmul(
                            bcdiv(bcsub($profit, $absoluteCommission, 10), $absoluteCommission, 10),
                            '100',
                            10
                        )
                    );
                foreach (['ticket', 'user_id', 'cmd', 'volume', 'login', 'force_close_id'] as $integerField) {
                    if (array_key_exists($integerField, $item) && $item[$integerField] !== null) {
                        $item[$integerField] = (int) $item[$integerField];
                    }
                }
                unset($item['profit_decimal'], $item['commission_decimal'], $item['risk_value_decimal']);

                return $item;
            })
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'summary' => $this->positionSummary(clone $query),
        ];
    }

    /**
     * 校验旧/现代持仓筛选；返回 null 表示通过，否则返回稳定字段和消息。
     *
     * @return array{field: string, message: string}|null
     */
    public function validatePositionFilters(Request $request, bool $useLegacyDefaultDates = false): ?array
    {
        $dates = $this->positionDates($request, $useLegacyDefaultDates);
        $payload = [
            'user_id' => $request->input('user_id', $request->input('userId')),
            'ticket' => $request->input('ticket', $request->input('orderId')),
            'symbol' => $request->input('symbol'),
            'order_type' => $request->input('order_type', $request->input('orderType')),
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
            'page' => $request->input('page', 1),
            'rows' => $request->input('rows', null),
            'limit' => $request->input('limit', null),
            'per_page' => $request->input('per_page', null),
        ];
        $rules = [
            'user_id' => 'nullable|integer|min:1',
            'ticket' => 'nullable|integer|min:1',
            'symbol' => 'nullable|string|max:16',
            'order_type' => 'nullable|in:real_disk,test_disk',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'rows' => 'nullable|integer|min:1|max:500',
            'limit' => 'nullable|integer|min:1|max:500',
            'per_page' => 'nullable|integer|min:1|max:500',
        ];
        $validator = Validator::make($payload, $rules);
        if ($validator->fails()) {
            $field = (string) array_key_first($validator->errors()->toArray());

            return [
                'field' => $field,
                'message' => $validator->errors()->first($field),
            ];
        }

        if ($payload['start_date'] !== null && $payload['end_date'] !== null
            && $payload['start_date'] > $payload['end_date']) {
            return [
                'field' => 'date_range',
                'message' => __('validation.after_or_equal', [
                    'attribute' => 'end_date',
                    'date' => 'start_date',
                ]),
            ];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function positionFilters(Request $request, bool $useLegacyDefaultDates): array
    {
        $dates = $this->positionDates($request, $useLegacyDefaultDates);

        return [
            'user_id' => $request->input('user_id', $request->input('userId')),
            'ticket' => $request->input('ticket', $request->input('orderId')),
            'symbol' => $request->input('symbol'),
            'order_type' => $request->input('order_type', $request->input('orderType')),
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => $this->positionPerPage($request),
        ];
    }

    private function positionPerPage(Request $request): int
    {
        foreach (['rows', 'limit', 'per_page'] as $field) {
            $value = $request->input($field);
            if ($value !== null && $value !== '') {
                return max(1, (int) $value);
            }
        }

        return 15;
    }

    /** @return array{start_date: mixed, end_date: mixed} */
    private function positionDates(Request $request, bool $useLegacyDefaults): array
    {
        $startDate = $request->input('start_date', $request->input('startdate'));
        $endDate = $request->input('end_date', $request->input('enddate'));

        return [
            'start_date' => $startDate === null || $startDate === ''
                ? ($useLegacyDefaults ? '2024-01-01' : null)
                : $startDate,
            'end_date' => $endDate === null || $endDate === ''
                ? ($useLegacyDefaults ? date('Y-m-d') : null)
                : $endDate,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function positionQuery(array $filters, ?Admin $admin)
    {
        $query = UserTrade::query()
            ->join('user_infos', function ($join): void {
                $join->on('user_infos.user_id', '=', 'user_trades.user_id')
                    ->whereNull('user_infos.deleted_at');
            })
            ->leftJoin('mt4_trades as force_close_trades', function ($join): void {
                $join->on('force_close_trades.ticket', '=', 'user_trades.ticket')
                    ->on('force_close_trades.login', '=', 'user_infos.mt4_code')
                    ->where('user_infos.mt4_code', '>', 0)
                    ->whereIn('force_close_trades.cmd', [0, 1, 2, 3, 4, 5])
                    ->where(function ($openTrade): void {
                        $openTrade->whereNull('force_close_trades.close_time')
                            ->orWhere('force_close_trades.close_time', 0);
                    });
            })
            ->whereNull('user_trades.deleted_at')
            ->whereIn('user_trades.cmd', [0, 1, 2, 3, 4, 5])
            ->where('user_trades.close_time', self::OPEN_CLOSE_TIME)
            ->where('user_trades.margin_rate', '<>', 0)
            ->whereRaw('(
                CAST(user_trades.profit AS DECIMAL(65, 10))
                - ABS(CAST(user_trades.commission AS DECIMAL(65, 10)))
            ) > 0')
            ->select([
                'user_trades.ticket',
                'user_trades.user_id',
                'user_trades.symbol',
                'user_trades.cmd',
                'user_trades.volume',
                'user_trades.commission',
                'user_trades.profit',
                'user_trades.open_time',
                'user_infos.user_name',
                'user_infos.parent_id',
                'user_infos.account_type',
                'user_infos.mt4_group',
                'force_close_trades.id as force_close_id',
            ])
            ->selectRaw('CASE WHEN user_infos.mt4_code > 0 THEN user_infos.mt4_code ELSE NULL END as login')
            ->selectRaw('CAST(user_trades.stop_loss AS CHAR) as sl')
            ->selectRaw('CAST(user_trades.take_profit AS CHAR) as tp')
            ->selectRaw('CAST(user_trades.open_price AS CHAR) as open_price')
            ->selectRaw('CAST(user_trades.swaps AS DECIMAL(65, 10)) as swaps')
            ->selectRaw('CAST(user_trades.profit AS DECIMAL(65, 10)) as profit_decimal')
            ->selectRaw('CAST(user_trades.commission AS DECIMAL(65, 10)) as commission_decimal')
            ->selectRaw('(
                CAST(user_trades.profit AS DECIMAL(65, 10))
                - ABS(CAST(user_trades.commission AS DECIMAL(65, 10)))
            ) as risk_value_decimal')
            ->selectRaw('(user_trades.profit - ABS(user_trades.commission)) as risk_value')
            ->selectRaw('0 as margin');

        if ($filters['user_id'] !== null && $filters['user_id'] !== '') {
            $query->where('user_trades.user_id', (int) $filters['user_id']);
        }
        if ($filters['ticket'] !== null && $filters['ticket'] !== '') {
            $query->where('user_trades.ticket', (int) $filters['ticket']);
        }
        if ($filters['symbol'] !== null && $filters['symbol'] !== '') {
            $query->where('user_trades.symbol', (string) $filters['symbol']);
        }
        if ($filters['start_date'] !== null && $filters['start_date'] !== '') {
            $query->where('user_trades.open_time', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if ($filters['end_date'] !== null && $filters['end_date'] !== '') {
            $query->where('user_trades.open_time', '<=', $filters['end_date'] . ' 23:59:59');
        }

        if (in_array($filters['order_type'], ['real_disk', 'test_disk'], true)) {
            $query->whereNotNull('user_infos.mt4_group')
                ->where('user_infos.mt4_group', '<>', '');
        }

        if ($filters['order_type'] === 'test_disk') {
            $query->where(function ($subQuery): void {
                $subQuery->where('user_infos.mt4_group', 'LIKE', '%-TEST')
                    ->orWhere('user_infos.mt4_group', 'LIKE', '%-TEST-P');
            });
        } elseif ($filters['order_type'] === 'real_disk') {
            $query->where(function ($subQuery): void {
                $subQuery->where('user_infos.mt4_group', 'NOT LIKE', '%-TEST')
                    ->where('user_infos.mt4_group', 'NOT LIKE', '%-TEST-P');
            });
        }

        if ($admin) {
            $this->adminDataScopeService->apply($query, $admin, 'trade', 'user_trades.user_id');
        }

        return $query;
    }

    /** @return array<string, int|string> */
    private function positionSummary($query): array
    {
        $summary = DB::query()
            ->fromSub($query->toBase(), 'risk_positions')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('CAST(COALESCE(SUM(risk_positions.profit_decimal), 0) AS CHAR) as total_profit')
            ->selectRaw('CAST(COALESCE(SUM(risk_positions.volume), 0) AS CHAR) as total_volume')
            ->selectRaw('CAST(COALESCE(SUM(risk_positions.risk_value_decimal), 0) AS CHAR) as total_risk_value')
            ->first();

        return [
            'total_records' => (int) ($summary->total_records ?? 0),
            'total_profit' => $this->decimal((string) ($summary->total_profit ?? '0')),
            'total_volume' => $this->decimal((string) ($summary->total_volume ?? '0')),
            'total_risk_value' => $this->decimal((string) ($summary->total_risk_value ?? '0')),
            'total_margin' => '0.00',
        ];
    }

    private function decimal(string $value, int $scale = 2): string
    {
        $adjustment = '0.' . str_repeat('0', $scale) . '5';
        $rounded = bccomp($value, '0', $scale + 4) < 0
            ? bcsub($value, $adjustment, $scale + 1)
            : bcadd($value, $adjustment, $scale + 1);

        return bcadd($rounded, '0', $scale);
    }
}
