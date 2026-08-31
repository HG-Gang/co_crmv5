<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:13
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\DepositRecord;
use App\Models\Mt4Trade;
use App\Models\UserInfo;
use App\Services\AdminDataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台资金流水控制器。
 *
 * 文件功能：
 * - 旧项目 `WithdrawFlowController` 负责出金流水，核心查询来自 MT4_TRADES 出金类余额交易。
 * - 旧项目 `UnDepositAmountController` 负责未入金流水，核心查询来自待支付入金记录。
 * - 新项目当前真实表为 `mt4_trades` 与 `deposit_records`，字段口径与旧项目不完全一致，本控制器按真实字段迁移旧项目资金流水口径。
 * - 出金流水已按 MT4 COMMENT 关键字识别来源，列表和导出共用同一套筛选、数据范围与当前筛选汇总，避免两个入口金额口径不一致。
 * - 未入金流水已补充运营跟进分桶和当前筛选汇总；真实支付网关状态变更或人工跟进写链仍由后续支付/运营模块承接。
 *
 * 金额与时间口径：
 * - 金额输出统一保留两位小数（moneyNumber），时间统一为 10 位 Unix 秒级时间戳。
 * - 列表、汇总与 CSV 导出共用同一查询构造，保证两处金额与筛选口径一致。
 */
class FundFlowController extends AdminBaseController
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
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于按管理员角色和绑定代理限制可见流水。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询入金流水列表。
     *
     * 参数含义：
     * - user_id/userId：业务用户 ID 或 MT4 登录号，对应 `mt4_trades.login`。
     * - ticket/deposit_id：MT4 入金流水订单号，对应 `mt4_trades.ticket`。
     * - deposit_source/direct_deposit_source：MT4 comment 中的入金来源码，例如 DBUN。
     * - start_date/end_date 或 deposit_startdate/deposit_enddate：资金发生时间范围，对应 `mt4_trades.close_time`。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function depositFlowList(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->newDepositFlowQuery($request);
        $summary = $this->depositFlowSummary(clone $query);
        $paginator = $this->paginateQuery($query, $request, 'close_time');
        $paginator->getCollection()->transform(function (Mt4Trade $record): array {
            return $this->formatDepositFlowRecord($record);
        });

        return $this->success(
            [
                'list' => $paginator,
                'totalRow' => $this->depositFlowTotalRow($summary['total_profit']),
                'summary' => $summary,
            ],
            __('admin.deposit_flows_fetched')
        );
    }

    /**
     * 导出当前筛选条件下的入金流水 CSV。
     *
     * 参数含义：
     * - user_id/userId、ticket/deposit_id、deposit_source/direct_deposit_source 与列表接口保持一致。
     * - 导出数据来自 `mt4_trades` 正向余额流水，并补充已支付入金记录中的实际到账金额和通道信息。
     *
     * @param Request $request 当前请求对象。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse 成功返回 CSV 下载；筛选参数非法时返回统一 JSON 错误。
     */
    public function exportDepositFlows(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->newDepositFlowQuery($request);
        $summary = $this->depositFlowSummary(clone $query);

        $rows = [
            ['order_no', 'ticket', 'login', 'user_name', 'profit', 'directProfit', 'depamount', 'flow_source', 'flow_source_name', 'comment', 'channel_name', 'channel_order_no', 'modify_time', 'close_time'],
        ];

        // 导出固定上限 5000 行，超出部分直接截断，需通过筛选收窄范围。
        $query->orderByDesc('close_time')->limit(5000)->get()->each(function (Mt4Trade $record) use (&$rows) {
            $row = $this->formatDepositFlowRecord($record);
            $rows[] = [
                $row['order_no'],
                $row['ticket'],
                $row['login'],
                $row['user_name'],
                $row['profit'],
                $row['directProfit'],
                $row['depamount'],
                $row['flow_source'],
                $row['flow_source_name'],
                $row['directComment'],
                $row['dep_channel'],
                $row['dep_channel_no'],
                $row['directModifyTime'],
                $row['directCloseTime'],
            ];
        });

        $rows[] = ['total', '', '', '', $summary['total_profit'], $summary['total_profit'], '', '', '', '', '', '', '', ''];

        return $this->csvDownload('deposit_flows_export.csv', $rows);
    }

    /**
     * 查询出金流水列表。
     *
     * 参数含义：
     * - user_id：业务用户 ID，对应 `mt4_trades.login`。
     * - ticket：MT4 订单号，对应 `mt4_trades.ticket`。
     * - start_date/end_date：流水时间范围，优先按 `close_time` 过滤。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawFlowList(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->newWithdrawFlowQuery($request);
        $summary = $this->withdrawFlowSummary(clone $query);
        $paginator = $this->paginateQuery($query, $request, 'close_time');
        $paginator->getCollection()->transform(function (Mt4Trade $record): array {
            return $this->formatWithdrawFlowRecord($record);
        });

        return $this->success(
            [
                'list' => $paginator,
                'totalRow' => $this->withdrawFlowTotalRow($summary['total_profit']),
                'summary' => $summary,
            ],
            __('admin.withdraw_flows_fetched')
        );
    }

    /**
     * 导出当前筛选条件下的出金流水 CSV。
     *
     * 参数含义：
     * - user_id、ticket、start_date、end_date 与列表接口一致，会复用相同筛选和数据范围。
     * - 当前导出只输出真实 `mt4_trades` 可支撑字段，不伪造旧项目 COMMENT 深层分类。
     *
     * @param Request $request 当前请求对象。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportWithdrawFlows(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->newWithdrawFlowQuery($request);
        $summary = $this->withdrawFlowSummary(clone $query);

        $rows = [
            ['order_no', 'ticket', 'login', 'user_name', 'symbol', 'cmd', 'profit', 'directProfit', 'flow_source', 'flow_source_name', 'comment', 'commission', 'swaps', 'open_time', 'close_time'],
        ];

        // 导出固定上限 5000 行，超出部分直接截断，需通过筛选收窄范围。
        $query->orderByDesc('close_time')->limit(5000)->get()->each(function ($record) use (&$rows) {
            $row = $this->formatWithdrawFlowRecord($record);
            $rows[] = [
                $row['order_no'],
                $row['ticket'],
                $row['login'],
                $row['user_name'],
                $row['symbol'],
                $row['cmd'],
                $row['profit'],
                $row['directProfit'],
                $row['flow_source'],
                $row['flow_source_name'],
                $row['comment'],
                $row['commission'],
                $row['swaps'],
                $row['open_time'],
                $row['close_time'],
            ];
        });

        $rows[] = ['total', '', '', '', '', '', $summary['total_profit'], $summary['total_profit'], '', '', '', '', '', '', ''];

        return $this->csvDownload('withdraw_flows_export.csv', $rows);
    }

    /**
     * 查询未入金流水列表。
     *
     * 参数含义：
     * - user_id：业务用户 ID，对应 `deposit_records.user_id`。
     * - local_order_no：本地入金订单号，对应 `deposit_records.local_order_no`。
     * - channel_order_no：通道订单号，对应 `deposit_records.channel_order_no`。
     * - start_date/end_date：创建时间范围，对应 `deposit_records.created_at`。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function undepositFlowList(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->newUndepositFlowQuery($request);
        $summary = $this->undepositFlowSummary(clone $query);
        $now = time();

        $paginator = $this->paginateQuery($query, $request, 'created_at');
        $paginator->getCollection()->transform(function (DepositRecord $record) use ($now): array {
            return $this->formatUndepositFlowRecord($record, $now);
        });

        return $this->success(
            [
                'list' => $paginator,
                'totalRow' => $this->undepositFlowTotalRow($summary['total_amount']),
                'summary' => $summary,
            ],
            __('admin.undeposit_flows_fetched')
        );
    }

    /**
     * 导出当前筛选条件下的未入金流水 CSV。
     *
     * 参数含义：
     * - user_id、local_order_no、channel_order_no、start_date、end_date 与列表接口一致。
     * - 当前导出只输出待支付 `deposit_records.status=01` 记录，复杂运营跟进统计后续独立迁移。
     *
     * @param Request $request 当前请求对象。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportUndepositFlows(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $query = $this->newUndepositFlowQuery($request);
        $summary = $this->undepositFlowSummary(clone $query);
        $now = time();

        $rows = [
            ['id', 'order_no', 'user_id', 'user_name', 'mt4_ticket', 'amount', 'actual_amount', 'exchange_rate', 'channel_name', 'local_order_no', 'channel_order_no', 'status', 'follow_status', 'follow_status_name', 'pending_days', 'created_at'],
        ];

        // 导出固定上限 5000 行，超出部分直接截断，需通过筛选收窄范围。
        $query->orderByDesc('created_at')->limit(5000)->get()->each(function ($record) use (&$rows, $now) {
            $row = $this->formatUndepositFlowRecord($record, $now);
            $rows[] = [
                $row['id'],
                $row['order_no'],
                $row['user_id'],
                $row['user_name'],
                $row['mt4_ticket'],
                $row['amount'],
                $row['actual_amount'],
                $row['exchange_rate'],
                $row['channel_name'],
                $row['local_order_no'],
                $row['channel_order_no'],
                $row['status'],
                $row['follow_status'],
                $row['follow_status_name'],
                $row['pending_days'],
                $row['created_at'],
            ];
        });

        $rows[] = ['total', 'total', '', '', '', $summary['total_amount'], '', '', '', '', '', '', '', '', '', ''];

        return $this->csvDownload('undeposit_flows_export.csv', $rows);
    }

    /**
     * 查询从未成功入金的普通客户列表。
     *
     * 参数含义：
     * - user_id/user_name：按业务用户编号或用户名缩小运营跟进范围。
     * - start_date/end_date：按 user_infos.created_at 注册时间过滤。
     * - min_days：只保留注册后超过指定天数仍未成功入金的客户。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function neverDepositUserList(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        if ($minDaysError = $this->validateMinDaysFilter($request)) {
            return $minDaysError;
        }

        $query = UserInfo::query()->with('login')
            ->where('account_type', 2)
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('deposit_records')
                    ->whereColumn('deposit_records.user_id', 'user_infos.user_id')
                    ->where('deposit_records.status', '02');
            });

        $this->applyNeverDepositUserFilters($query, $request);
        $this->applyDataScope($query, $request, 'user', 'user_infos.user_id');

        $paginator = $this->paginateQuery($query, $request, 'created_at');
        $now = time();

        $paginator->getCollection()->transform(function (UserInfo $user) use ($now) {
            $createdAt = $this->timestampValue($user->created_at);

            return [
                'user_id' => $user->user_id,
                'user_name' => $user->user_name,
                'phone' => $user->phone,
                'email' => $user->login ? $user->login->email : '',
                'parent_id' => $user->parent_id,
                'register_date' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
                'never_deposit_days' => $createdAt > 0 ? (int) floor(max(0, $now - $createdAt) / 86400) : 0,
            ];
        });

        return $this->success($paginator, __('admin.never_deposit_users_fetched'));
    }

    /**
     * 追加入金流水筛选条件。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，用于读取新旧筛选参数。
     * @return void
     */
    private function applyDepositFlowFilters(Builder $query, Request $request): void
    {
        $userId = $request->input('user_id', $request->input('userId'));
        if ($userId !== null && $userId !== '') {
            $query->where('login', (int) $userId);
        }

        $ticket = $request->input('ticket', $request->input('deposit_id'));
        if ($ticket !== null && $ticket !== '') {
            $query->where('ticket', 'LIKE', '%' . $ticket . '%');
        }

        $depositSource = $request->input('deposit_source', $request->input('direct_deposit_source'));
        if ($depositSource !== null && $depositSource !== '') {
            $query->where('comment', 'LIKE', '%' . $depositSource . '%');
        } else {
            $query->whereRaw('comment REGEXP ?', [$this->depositFlowKeywordRegex()]);
        }

        $this->applyTimestampRangeWithLegacyNames($query, $request, 'close_time');
    }

    /**
     * 创建入金流水基础查询。
     *
     * 参数说明：
     * - 只读取 `cmd=6`、`open_price=0`、`profit>0` 的 MT4 余额增加记录。
     * - 未指定来源码时按旧项目入金关键字集合过滤，避免普通正向调账误入入金流水。
     *
     * @param Request $request 当前请求对象。
     * @return Builder 返回已应用筛选和后台数据范围的 MT4 交易查询。
     */
    private function newDepositFlowQuery(Request $request): Builder
    {
        $query = Mt4Trade::query()->with('user')
            ->where('cmd', 6)
            ->where('open_price', 0)
            ->where('profit', '>', 0);

        $this->applyDepositFlowFilters($query, $request);
        $this->applyDataScope($query, $request, 'trade', 'login');

        return $query;
    }

    /**
     * 格式化入金流水单行。
     *
     * 返回字段说明：
     * - order_no/userId/directProfit 对齐旧后台 Layui 入金流水表格字段。
     * - depamount 来自 `deposit_records` 中已支付记录的实际支付金额，找不到时返回 0。
     * - directTypeName 是从 MT4 comment 识别出的中文入金来源，便于 V2 表格直接展示。
     *
     * @param Mt4Trade $record MT4 余额类交易记录。
     * @return array<string, mixed> 返回前端列表行和旧兼容层共用的数据。
     */
    private function formatDepositFlowRecord(Mt4Trade $record): array
    {
        $depositRecord = DepositRecord::query()
            ->where('mt4_ticket', (int) $record->ticket)
            ->where('status', '02')
            ->orderByDesc('id')
            ->first();
        $classification = $this->classifyDepositFlowComment((string) $record->comment);
        $profit = $this->moneyNumber($record->profit);

        return [
            'id' => (int) $record->id,
            'order_no' => (int) $record->ticket,
            'ticket' => (int) $record->ticket,
            'login' => (int) $record->login,
            'userId' => (int) $record->login,
            'user_id' => (int) $record->login,
            'username' => $record->user ? $record->user->user_name : '',
            'user_name' => $record->user ? $record->user->user_name : '',
            'directProfit' => $profit,
            'profit' => $profit,
            // depamount 对应旧 deposit_record_log.dep_amount，列头为「实际支付 / RMB」。
            // 旧语义见 UserDepositController.php:198,201：dep_amount = round(USD × sys_deposit_rate, 2)，
            // 即本币实付额；USD 原额在旧库是 dep_act_amount，对应新库 amount。
            // 因此这里必须取 actual_amount（新库 = USD × 汇率），取 amount 会把 USD 当成 RMB 展示。
            'depamount' => $depositRecord ? $this->moneyNumber($depositRecord->actual_amount) : 0.0,
            // depoutTrande 对应旧 dep_outTrande，列头为「充值流水号」，是本站生成的本地单号
            // （旧 UserDepositController.php:192,200：generate_order_idV5('tg'.$userId)）。
            // 通道平台单号是另一个字段 dep_channel_no（旧 PayCallBackController.php:494 写入 trader_no），
            // 两者语义不同，若同源会让「充值流水号」与「通道单号」两列内容重复，
            // 且本地单号这个唯一追溯键在列表中彻底消失。
            'depoutTrande' => $depositRecord ? (string) $depositRecord->local_order_no : '',
            'dep_channel' => $depositRecord ? (string) $depositRecord->channel_name : '',
            'dep_channel_no' => $depositRecord ? (string) $depositRecord->channel_order_no : '',
            'directType' => (string) $record->comment,
            'directTypeName' => $classification['name'],
            'directComment' => (string) $record->comment,
            'directModifyTime' => $record->modify_time ?: $record->close_time,
            'directCloseTime' => $record->close_time,
            'flow_source' => $classification['source'],
            'flow_source_name' => $classification['name'],
        ];
    }

    /**
     * 汇总当前筛选条件下的入金流水。
     *
     * @param Builder $query 已应用筛选和数据范围的 MT4 查询对象。
     * @return array<string, int|float> 返回列表汇总数据。
     */
    private function depositFlowSummary(Builder $query): array
    {
        $countQuery = clone $query;
        $sumQuery = clone $query;

        return [
            'total_records' => (int) $countQuery->count(),
            'total_profit' => $this->moneyNumber($sumQuery->sum('profit')),
        ];
    }

    /**
     * 生成入金流水 Layui 合计行。
     *
     * @param float $totalProfit 当前筛选条件下的入金金额合计。
     * @return array<string, mixed> 返回 Layui totalRow。
     */
    private function depositFlowTotalRow(float $totalProfit): array
    {
        return [
            'order_no' => 'total',
            'userId' => '',
            'username' => '',
            'directProfit' => $totalProfit,
            'depamount' => '',
            'directType' => '',
            'directComment' => '',
            'depoutTrande' => '',
            'directCloseTime' => '',
        ];
    }

    /**
     * 获取旧项目入金 COMMENT 关键字正则。
     *
     * @return string 返回 MySQL REGEXP 可直接使用的正则片段。
     */
    private function depositFlowKeywordRegex(): string
    {
        return '(' . implode('|', array_map('preg_quote', [
            'DBAA',
            'DBAD',
            'DBCN',
            'DBCR',
            'DBCT',
            'DBGN',
            'DBMN',
            'DBPA',
            'DBPN',
            'DBSN',
            'DBTN',
            'DBUN',
            'DBZN',
            'DBZR',
            'WBIR',
            '-CZ',
            '-FY',
            '-RJ',
            '-CJTH',
            '-ZH',
            '-TH',
            'Adj',
            'Deposit',
            '入金',
            '充值',
        ])) . ')';
    }

    /**
     * 按 MT4 comment 识别入金来源。
     *
     * @param string $comment MT4 余额交易备注。
     * @return array{source: string, name: string} 返回来源码和中文来源名称。
     */
    private function classifyDepositFlowComment(string $comment): array
    {
        foreach ($this->depositFlowSourceNames() as $source => $name) {
            if (stripos($comment, $source) !== false) {
                return ['source' => $source, 'name' => $name];
            }
        }

        return ['source' => 'OTHER', 'name' => '其他'];
    }

    /**
     * 获取入金来源码与中文名称映射。
     *
     * @return array<string, string> 返回旧项目入金来源码到页面中文名称的映射。
     */
    private function depositFlowSourceNames(): array
    {
        return [
            'DBUN' => '正常存款',
            'DBAD' => '平台入金',
            'DBCN' => '返佣入金',
            'DBCT' => '佣金转户',
            'DBCR' => '佣金退回',
            'DBAA' => '会计调整',
            'DBZR' => '清零退回',
            'WBIR' => '出金退回',
            '-CZ' => '充值',
            '-FY' => '返佣',
            '-RJ' => '批量入金',
            '-CJTH' => '出金失败退回',
        ];
    }

    /**
     * 追加出金流水筛选条件。
     *
     * @param Builder $query MT4 交易查询对象。
     * @param Request $request 当前请求对象，用于读取筛选参数。
     * @return void
     */
    private function applyWithdrawFlowFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('login', (int) $request->input('user_id'));
        }

        $ticket = $request->input('ticket', $request->input('withdraw_id'));
        if ($ticket !== null && $ticket !== '') {
            $query->where('ticket', 'LIKE', '%' . $ticket . '%');
        }

        if ($request->filled('withdraw_source')) {
            $query->where('comment', 'LIKE', '%' . $request->input('withdraw_source') . '%');
        } else {
            $query->whereRaw('comment REGEXP ?', [$this->withdrawFlowKeywordRegex()]);
        }

        $this->applyTimestampRangeWithLegacyNames($query, $request, 'close_time');
    }

    /**
     * 创建出金流水基础查询。
     *
     * 参数说明：
     * - $request 提供 user_id、ticket、withdraw_source 和日期范围。
     * - 查询只保留 `cmd=6`、`open_price=0`、`profit<0` 的 MT4 余额减少记录。
     * - 未指定 withdraw_source 时使用旧项目出金 COMMENT 关键字集合，避免普通负余额调整误入出金流水。
     *
     * @param Request $request 当前请求对象。
     * @return Builder 返回已应用筛选和后台数据范围的 MT4 交易查询。
     */
    private function newWithdrawFlowQuery(Request $request): Builder
    {
        $query = Mt4Trade::query()->with('user')
            ->where('cmd', 6)
            ->where('open_price', 0)
            ->where('profit', '<', 0);

        $this->applyWithdrawFlowFilters($query, $request);
        $this->applyDataScope($query, $request, 'trade', 'login');

        return $query;
    }

    /**
     * 格式化出金流水单行。
     *
     * 返回字段说明：
     * - flow_source 表示从 MT4 comment 中识别出的机器来源码，例如 WBIN。
     * - flow_source_name/directTypeName 表示旧项目页面使用的中文来源名称。
     * - directProfit 与 profit 保持同一金额，兼容旧 Layui 表格和新 Naive 汇总。
     *
     * @param Mt4Trade $record MT4 余额类交易记录。
     * @return array<string, mixed> 返回前端列表行和 CSV 行共用的数据。
     */
    private function formatWithdrawFlowRecord(Mt4Trade $record): array
    {
        $classification = $this->classifyWithdrawFlowComment((string) $record->comment);
        $profit = $this->moneyNumber($record->profit);

        return [
            'id' => (int) $record->id,
            'order_no' => (int) $record->id,
            'ticket' => (int) $record->ticket,
            'login' => (int) $record->login,
            'user_id' => (int) $record->login,
            'user_name' => $record->user ? $record->user->user_name : '',
            'symbol' => $record->symbol,
            'cmd' => (int) $record->cmd,
            'profit' => $profit,
            'directProfit' => $profit,
            'commission' => $this->moneyNumber($record->commission),
            'swaps' => $this->moneyNumber($record->swaps),
            'open_time' => $record->open_time,
            'close_time' => $record->close_time,
            'comment' => (string) $record->comment,
            'directType' => (string) $record->comment,
            'directComment' => (string) $record->comment,
            'flow_source' => $classification['source'],
            'flow_source_name' => $classification['name'],
            'directTypeName' => $classification['name'],
        ];
    }

    /**
     * 汇总当前筛选条件下的出金流水。
     *
     * 返回字段说明：
     * - total_records 表示当前筛选条件下符合出金 COMMENT 口径的记录数。
     * - total_profit 表示当前筛选条件下 `mt4_trades.profit` 合计，出金通常为负数。
     *
     * @param Builder $query 已应用筛选和数据范围的 MT4 查询对象。
     * @return array<string, int|float> 返回列表汇总卡片和测试断言使用的汇总数据。
     */
    private function withdrawFlowSummary(Builder $query): array
    {
        $countQuery = clone $query;
        $sumQuery = clone $query;

        return [
            'total_records' => (int) $countQuery->count(),
            'total_profit' => $this->moneyNumber($sumQuery->sum('profit')),
        ];
    }

    /**
     * 生成出金流水 Layui 合计行。
     *
     * @param float $totalProfit 当前筛选条件下的出金金额合计。
     * @return array<string, mixed> 返回 Layui totalRow 和 CSV 合计含义一致的汇总行。
     */
    private function withdrawFlowTotalRow(float $totalProfit): array
    {
        return [
            'order_no' => 'total',
            'ticket' => 'total',
            'login' => '',
            'user_name' => '',
            'profit' => $totalProfit,
            'directProfit' => $totalProfit,
            'flow_source' => '',
            'flow_source_name' => '',
            'comment' => '',
        ];
    }

    /**
     * 获取旧项目出金 COMMENT 关键字正则。
     *
     * 关键字说明：
     * - WBIN、WBAD 等来自旧项目 MT4 出金流水来源码。
     * - 默认筛选必须命中这些关键字，解决单纯 profit<0 无法区分出金与其它余额调整的问题。
     *
     * @return string 返回 MySQL REGEXP 可直接使用的正则片段。
     */
    private function withdrawFlowKeywordRegex(): string
    {
        return '(' . implode('|', array_map('preg_quote', [
            'WBAA',
            'WBCN',
            'WBCR',
            'WBCT',
            'WBHN',
            'WBIN',
            'WBPN',
            'WBSN',
            'WBTN',
            'WBAD',
            'DBZR',
        ])) . ')';
    }

    /**
     * 按 MT4 comment 识别出金来源。
     *
     * @param string $comment MT4 余额交易备注。
     * @return array{source: string, name: string} 返回来源码和中文来源名称；未命中时返回 OTHER/其他。
     */
    private function classifyWithdrawFlowComment(string $comment): array
    {
        foreach ($this->withdrawFlowSourceNames() as $source => $name) {
            if (stripos($comment, $source) !== false) {
                return ['source' => $source, 'name' => $name];
            }
        }

        return ['source' => 'OTHER', 'name' => '其他'];
    }

    /**
     * 获取出金来源码与中文名称映射。
     *
     * @return array<string, string> 返回旧项目出金来源码到页面中文名称的映射。
     */
    private function withdrawFlowSourceNames(): array
    {
        return [
            'WBIN' => '账户取款',
            'WBAD' => '平台出金',
            'WBIR' => '出金退回',
            'WBCT' => '佣金转户',
            'WBCR' => '佣金退回',
            'WBAA' => '会计调整',
            'WBCN' => '账户返佣',
            'WBHN' => '手续费扣账',
            'WBPN' => '炒单盈利扣取',
            'WBSN' => '分成亏损扣除',
            'WBTN' => '转账出金',
            'DBZR' => '清零存入退回',
        ];
    }

    /**
     * 创建未入金流水基础查询。
     *
     * 参数说明：
     * - $request 提供 user_id、本地订单号、通道订单号和日期筛选。
     * - 仅查询 `deposit_records.status=01`，表示用户已提交但尚未完成的入金申请。
     *
     * @param Request $request 当前请求对象。
     * @return Builder 返回已应用筛选和数据范围的入金记录查询。
     */
    private function newUndepositFlowQuery(Request $request): Builder
    {
        $query = DepositRecord::query()->with('user')
            ->where('status', '01');

        $this->applyUndepositFlowFilters($query, $request);
        $this->applyDataScope($query, $request, 'deposit', 'user_id');

        return $query;
    }

    /**
     * 格式化未入金流水单行。
     *
     * 返回字段说明：
     * - pending_days 表示记录从创建到当前已经等待的自然天数。
     * - follow_status 表示运营处理分桶，前端可据此筛选或高亮。
     * - follow_status_name 表示中文状态含义，用于旧 Layui 表格直接展示。
     *
     * @param DepositRecord $record 入金记录模型。
     * @param int $now 当前 Unix 时间戳。
     * @return array<string, mixed> 返回前端列表行。
     */
    private function formatUndepositFlowRecord(DepositRecord $record, int $now): array
    {
        $createdAt = $this->timestampValue($record->created_at);
        $pendingDays = $createdAt > 0 ? (int) floor(max(0, $now - $createdAt) / 86400) : 0;
        $classification = $this->classifyUndepositPendingDays($pendingDays);

        return [
            'id' => (int) $record->id,
            'order_no' => (int) $record->id,
            'user_id' => (int) $record->user_id,
            'user_name' => $record->user ? $record->user->user_name : (string) $record->user_name,
            'mt4_ticket' => (int) $record->mt4_ticket,
            'amount' => $this->moneyNumber($record->amount),
            'actual_amount' => $this->moneyNumber($record->actual_amount),
            'exchange_rate' => $this->moneyNumber($record->exchange_rate),
            'channel_name' => (string) $record->channel_name,
            'local_order_no' => (string) $record->local_order_no,
            'channel_order_no' => (string) $record->channel_order_no,
            'status' => (string) $record->status,
            'follow_status' => $classification['status'],
            'follow_status_name' => $classification['name'],
            'pending_days' => $pendingDays,
            'created_at' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
        ];
    }

    /**
     * 汇总未入金流水。
     *
     * 返回字段说明：
     * - total_records 表示当前筛选条件下待支付入金记录总数。
     * - total_amount 表示待支付申请金额合计。
     * - new_pending_count、need_follow_up_count、finance_review_required_count 分别对应运营三档分桶。
     *
     * @param Builder $query 已应用筛选和数据范围的查询对象。
     * @return array<string, int|float> 返回汇总数据。
     */
    private function undepositFlowSummary(Builder $query): array
    {
        $now = time();
        // 分桶阈值：2 天内为新提交、2~6 天运营跟进、7 天及以上财务复核，与 classifyUndepositPendingDays 口径一致。
        $followUpThreshold = $now - (2 * 86400);
        $financeReviewThreshold = $now - (7 * 86400);

        $record = $query
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw('SUM(CASE WHEN created_at > ? THEN 1 ELSE 0 END) as new_pending_count', [$followUpThreshold])
            ->selectRaw('SUM(CASE WHEN created_at <= ? AND created_at > ? THEN 1 ELSE 0 END) as need_follow_up_count', [$followUpThreshold, $financeReviewThreshold])
            ->selectRaw('SUM(CASE WHEN created_at <= ? THEN 1 ELSE 0 END) as finance_review_required_count', [$financeReviewThreshold])
            ->first();

        return [
            'total_records' => (int) ($record->total_records ?? 0),
            'total_amount' => $this->moneyNumber($record->total_amount ?? 0),
            'new_pending_count' => (int) ($record->new_pending_count ?? 0),
            'need_follow_up_count' => (int) ($record->need_follow_up_count ?? 0),
            'finance_review_required_count' => (int) ($record->finance_review_required_count ?? 0),
        ];
    }

    /**
     * 生成未入金流水 Layui 合计行。
     *
     * @param float $totalAmount 当前筛选条件下的待支付金额合计。
     * @return array<string, mixed> 返回 Layui totalRow。
     */
    private function undepositFlowTotalRow(float $totalAmount): array
    {
        return [
            'order_no' => 'total',
            'local_order_no' => 'total',
            'user_id' => '',
            'user_name' => '',
            'amount' => $totalAmount,
            'actual_amount' => '',
            'follow_status_name' => '',
            'pending_days' => '',
        ];
    }

    /**
     * 按待处理天数分类未入金运营状态。
     *
     * 分类说明：
     * - 0-1 天：新提交，通常等待支付回调或用户补操作。
     * - 2-6 天：运营跟进，需要人工触达用户确认是否继续支付。
     * - 7 天及以上：财务复核，需要核对是否存在通道异常、漏回调或重复申请。
     *
     * @param int $pendingDays 待处理自然天数。
     * @return array{status: string, name: string} 返回机器状态和中文状态。
     */
    private function classifyUndepositPendingDays(int $pendingDays): array
    {
        if ($pendingDays >= 7) {
            return ['status' => 'finance_review_required', 'name' => '财务复核'];
        }

        if ($pendingDays >= 2) {
            return ['status' => 'need_follow_up', 'name' => '运营跟进'];
        }

        return ['status' => 'new_pending', 'name' => '新提交'];
    }

    /**
     * 将金额字段统一转为两位小数浮点数。
     *
     * @param mixed $value 数据库中的金额、手续费或汇率原始值。
     * @return float 返回两位小数，供 JSON 汇总、Layui totalRow 和 CSV 合计统一使用。
     */
    private function moneyNumber($value): float
    {
        return round((float) $value, 2);
    }

    /**
     * 校验列表 user_id 筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
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
     * 校验 min_days 筛选参数必须为非负整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
     */
    private function validateMinDaysFilter(Request $request)
    {
        if (!$request->filled('min_days')) {
            return null;
        }

        $validator = Validator::make(['min_days' => $request->input('min_days')], [
            'min_days' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 追加未入金流水筛选条件。
     *
     * @param Builder $query 入金记录查询对象。
     * @param Request $request 当前请求对象，用于读取筛选参数。
     * @return void
     */
    private function applyUndepositFlowFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        $localOrderNo = $request->input('local_order_no', $request->input('undeposit_id'));
        if ($localOrderNo !== null && $localOrderNo !== '') {
            $query->where(function (Builder $subQuery) use ($localOrderNo): void {
                $subQuery->where('local_order_no', 'LIKE', '%' . $localOrderNo . '%')
                    ->orWhere('channel_order_no', 'LIKE', '%' . $localOrderNo . '%');
            });
        }

        if ($request->filled('channel_order_no')) {
            $query->where('channel_order_no', 'LIKE', '%' . $request->input('channel_order_no') . '%');
        }

        $this->applyTimestampRangeWithLegacyNames($query, $request, 'created_at');
    }

    /**
     * 追加从未入金用户列表筛选条件。
     *
     * @param Builder $query 用户资料查询对象。
     * @param Request $request 当前请求对象，用于读取筛选参数。
     * @return void
     */
    private function applyNeverDepositUserFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_infos.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('user_name')) {
            $query->where('user_infos.user_name', 'LIKE', '%' . $request->input('user_name') . '%');
        }

        if ($request->filled('min_days')) {
            $query->where('user_infos.created_at', '<=', strtotime('-' . max(0, (int) $request->input('min_days')) . ' days'));
        }

        $this->applyTimestampRange($query, $request, 'user_infos.created_at');
    }

    /**
     * 按当前后台管理员的数据范围过滤资金流水。
     *
     * @param Builder $query 业务流水查询对象。
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @param string $targetType 数据范围目标类型；trade 表示交易流水，deposit 表示入金流水。
     * @param string $userIdColumn 当前查询表中的业务用户 ID 字段名。
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
     * 按日期字符串追加 10 位时间戳范围。
     *
     * @param Builder $query 业务查询对象。
     * @param Request $request 当前请求对象；读取 start_date 和 end_date。
     * @param string $column 时间戳字段名，例如 close_time 或 created_at。
     * @return void
     */
    private function applyTimestampRange(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('start_date')) {
            $query->where($column, '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where($column, '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 按新旧日期字段追加 10 位时间戳范围。
     *
     * 参数说明：
     * - 新字段 start_date/end_date 来自项目2后台 API。
     * - 旧字段 deposit_startdate/deposit_enddate 来自项目1资金流水页面，即使是出金和未入金也沿用这组名称。
     *
     * @param Builder $query 业务查询对象。
     * @param Request $request 当前请求对象。
     * @param string $column 时间戳字段名，例如 close_time 或 created_at。
     * @return void
     */
    private function applyTimestampRangeWithLegacyNames(Builder $query, Request $request, string $column): void
    {
        $startDate = $request->input('start_date', $request->input('deposit_startdate'));
        $endDate = $request->input('end_date', $request->input('deposit_enddate'));

        if ($startDate !== null && $startDate !== '') {
            $query->where($column, '>=', strtotime($startDate . ' 00:00:00'));
        }

        if ($endDate !== null && $endDate !== '') {
            $query->where($column, '<=', strtotime($endDate . ' 23:59:59'));
        }
    }

    /**
     * 分页返回查询结果。
     *
     * @param Builder $query 业务查询对象。
     * @param Request $request 当前请求对象；page 为页码，per_page/limit 为每页数量。
     * @param string $orderColumn 排序字段名。
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function paginateQuery(Builder $query, Request $request, string $orderColumn)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));

        return $query->orderByDesc($orderColumn)->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 把 Eloquent 日期或整型时间戳统一转换为 Unix 秒级时间戳。
     *
     * @param mixed $value created_at 字段当前值。
     * @return int
     */
    private function timestampValue($value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        return (int) $value;
    }

    /**
     * 生成 CSV 下载响应。
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
