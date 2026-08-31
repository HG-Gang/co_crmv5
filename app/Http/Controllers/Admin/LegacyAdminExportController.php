<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 13:58
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Services\AdminDataScopeService;
use App\Services\WithdrawRecordQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

/**
 * 旧版后台导出控制器。
 *
 * 文件功能：
 * - 为旧版后台管理界面提供 CSV 格式的入金记录和出金记录导出。
 * - 应用管理员数据范围过滤，确保不同角色只能导出权限范围内的数据。
 *
 * 适用场景：
 * - 旧版后台管理界面通过 POST 请求下载入金/出金 CSV 导出文件。
 *
 * 入参例子：
 * - exportDeposits: {userId=600001, status=1, deposit_startdate="2026-01-01", deposit_enddate="2026-06-30"}
 * - exportWithdrawals: {userId=600001, withdraw_source=0, withdraw_startdate="2026-01-01", withdraw_enddate="2026-06-30"}
 *
 * 返回值：
 * - 成功时返回 CSV 文件流下载（Content-Type: text/csv）。
 * - 过滤参数非法时返回 JSON {code:1001, msg: 错误描述}。
 *
 * 异常或失败场景：
 * - 日期格式非法时拒绝导出（防止 SQL 注入风险）。
 * - 用户 ID 非数字时拒绝导出。
 *
 * 安全边界：
 * - 导出数据范围与列表口径一致：有登录管理员时先套用 AdminDataScopeService 数据范围再取数。
 * - 导出上限固定 5000 行，避免一次性拉取过多流水拖慢后台。
 */

class LegacyAdminExportController extends AdminBaseController
{
    private AdminDataScopeService $dataScopeService;

    private WithdrawRecordQueryService $withdrawQueryService;

    /**
     * 构造旧版导出控制器。
     *
     * @param AdminDataScopeService $dataScopeService 数据范围服务，保证导出与列表使用同一管理员权限口径。
     */
    public function __construct(
        AdminDataScopeService $dataScopeService,
        WithdrawRecordQueryService $withdrawQueryService
    )
    {
        $this->dataScopeService = $dataScopeService;
        $this->withdrawQueryService = $withdrawQueryService;
    }

    /**
     * 导出入金记录 CSV。
     *
     * 参数说明：
     * - status：旧页面入金状态，0/1/2 会先经 depositStatus() 映射为 01/02/09 存储值。
     * - user_id、start_date、end_date、local_order_no：与列表共用同一筛选链路。
     *
     * 失败语义：
     * - 筛选参数非法时返回 VALIDATION_FAILED，不输出 CSV。
     *
     * @param Request $request 当前请求对象，承载筛选参数和登录管理员。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportDeposits(Request $request)
    {
        if ($error = $this->validateFilters($request, true)) {
            return $error;
        }

        $status = $this->depositStatus($request->input('status'));
        $query = DepositRecord::query()->with('user');
        if ($request->user('admin')) {
            $query = $this->dataScopeService->apply(
                $query,
                $request->user('admin'),
                'deposit',
                'user_id'
            );
        }

        if ($status !== null) {
            $query->where('status', $status);
        }
        $this->applyCommonFilters($query, $request);

        $rows = [[
            'id', 'local_order_no', 'channel_order_no', 'mt4_ticket', 'user_id',
            'user_name', 'amount', 'actual_amount', 'exchange_rate', 'channel_name',
            'status', 'payment_time', 'remarks', 'created_at',
        ]];

        $query->orderByDesc('created_at')->limit(5000)->get()->each(function (DepositRecord $record) use (&$rows): void {
            $rows[] = [
                $record->id,
                $record->local_order_no,
                $record->channel_order_no,
                $record->mt4_ticket,
                $record->user_id,
                $record->user_name ?: optional($record->user)->user_name,
                $record->amount,
                $record->actual_amount,
                $record->exchange_rate,
                $record->channel_name,
                $record->status,
                $record->payment_time,
                $record->remarks,
                $record->created_at,
            ];
        });

        return $this->csvDownload('deposits_export.csv', $rows);
    }

    /**
     * 导出出金记录 CSV。
     *
     * 参数说明：
     * - status：旧页面出金状态，直接透传 0/1/2/3 到 withdraw_records.status。
     * - user_id、start_date、end_date、local_order_no：与列表共用同一筛选链路。
     *
     * 失败语义：
     * - 筛选参数非法时返回 VALIDATION_FAILED，不输出 CSV。
     *
     * @param Request $request 当前请求对象，承载筛选参数和登录管理员。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportWithdrawals(Request $request)
    {
        if ($error = $this->validateFilters($request, false)) {
            return $error;
        }

        $query = $this->withdrawQueryService->query($request->user('admin'), [
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
            'local_order_no' => $request->input('local_order_no'),
            'mt4_ticket' => $request->input('mt4_ticket'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);

        $rows = [[
            'id', 'local_order_no', 'third_order_no', 'mt4_ticket', 'user_id',
            'user_name', 'apply_amount', 'actual_amount', 'fee', 'exchange_rate',
            'rmb_fee', 'bank_no', 'bank_name', 'bank_addr', 'status',
            'funding_status', 'reject_reason', 'created_at',
        ]];

        foreach ($this->withdrawQueryService->exportRecords($query) as $export) {
            /** @var WithdrawRecord $record */
            $record = $export['record'];
            $rows[] = [
                $record->id,
                $this->sanitizeCsvCell($record->local_order_no),
                $this->sanitizeCsvCell($record->third_order_no),
                $this->sanitizeCsvCell($record->mt4_ticket),
                $record->user_id,
                $this->sanitizeCsvCell($export['username']),
                $record->apply_amount,
                $record->actual_amount,
                $record->fee,
                $record->exchange_rate,
                $record->rmb_fee,
                $this->sanitizeCsvCell($record->bank_no),
                $this->sanitizeCsvCell($record->bank_name),
                $this->sanitizeCsvCell($record->bank_addr),
                $record->status,
                $this->sanitizeCsvCell($record->funding_status),
                $this->sanitizeCsvCell($record->reject_reason),
                $record->created_at,
            ];
        }

        return $this->csvDownload('withdrawals_export.csv', $rows);
    }

    /**
     * 准备旧版出金导出文件。旧页面的两阶段契约要求先返回下载 URI，
     * 下载请求不得再次查询业务数据或写入业务表。
     */
    public function prepareLegacyWithdrawals(Request $request)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
        }

        $legacy = $request->input('data', []);
        if (!is_array($legacy)) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        foreach (['userId', 'withdraw_id', 'withdraw_source', 'withdraw_startdate', 'withdraw_enddate'] as $field) {
            $value = $legacy[$field] ?? null;
            if (is_array($value) || is_object($value)) {
                return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
            }
        }

        $filters = [
            'user_id' => $this->legacyFilterValue($legacy, 'userId'),
            'mt4_ticket' => $this->legacyFilterValue($legacy, 'withdraw_id'),
            'status' => $this->legacyFilterValue($legacy, 'withdraw_source'),
            'start_date' => $this->legacyFilterValue($legacy, 'withdraw_startdate') ?: '2024-01-01',
            'end_date' => $this->legacyFilterValue($legacy, 'withdraw_enddate') ?: date('Y-m-d'),
        ];

        $validator = Validator::make($filters, [
            'user_id' => ['nullable', 'integer', 'min:1'],
            'mt4_ticket' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'in:0,1,2,3'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $query = $this->withdrawQueryService->query($admin, $filters);
        $records = $this->withdrawQueryService->exportRecords($query);
        if ($records === []) {
            return response()->json(['msg' => 'FAIL']);
        }

        $adminId = (int) $admin->id;
        $directory = storage_path('app/legacy-admin-exports/admin/' . $adminId);
        File::ensureDirectoryExists($directory, 0750);
        $filename = 'withdrawals_' . $adminId . '_' . bin2hex(random_bytes(8)) . '.csv';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return $this->serverErrorResponse();
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            '订单号', '交易账号', '账户名', '银行卡号', '开户行名称',
            '申请金额 / USD', '实际金额 / USD', '申请汇率', '实际出金 / RMB',
            '手续费 / USD', '出金状态', '申请时间',
        ]);
        foreach ($records as $export) {
            /** @var WithdrawRecord $record */
            $record = $export['record'];
            fputcsv($handle, [
                $this->sanitizeCsvCell($record->mt4_ticket),
                $record->user_id,
                $this->sanitizeCsvCell($export['username']),
                $this->sanitizeCsvCell($record->bank_no),
                $this->sanitizeCsvCell($export['bank_no_info']),
                $record->apply_amount,
                $record->actual_amount,
                $record->exchange_rate,
                $export['actual_draw'],
                $record->fee,
                $this->legacyWithdrawStatus($record->status),
                $this->formatCreatedAt($record->created_at),
            ]);
        }
        fclose($handle);

        return response()->json([
            'msg' => 'index/admin/amount/withdraw_downloadfile/' . $filename . '/admin',
        ]);
    }

    /**
     * 校验导出筛选参数，避免脏参数进入 SQL。
     *
     * @param Request $request 当前请求对象。
     * @param bool $deposit true=入金导出（status 允许 0/1/2/01/02/09 旧值），false=出金导出（仅允许 0/1/2/3）。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateFilters(Request $request, bool $deposit)
    {
        foreach (['user_id', 'status', 'start_date', 'end_date', 'local_order_no', 'mt4_ticket'] as $field) {
            $value = $request->input($field);
            if (is_array($value) || is_object($value)) {
                return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
            }
        }

        $statusRule = $deposit
            ? ['nullable', 'in:0,1,2,01,02,09']
            : ['nullable', 'integer', 'in:0,1,2,3'];
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|min:1',
            'status' => $statusRule,
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'local_order_no' => 'nullable|string|max:100',
            'mt4_ticket' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 把旧页面入金状态码映射为 deposit_records 存储状态。
     *
     * @param mixed $status 旧页面提交的入金状态。
     * @return string|null 01=待处理、02=已支付、09=失败；未传或空时返回 null 表示不过滤。
     */
    private function depositStatus($status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        // 旧页面 0/1/2 与存储值 01/02/09 混用，统一收敛到存储状态，避免重复的等价分支。
        return [
            '0' => '01',
            '1' => '02',
            '2' => '09',
            '01' => '01',
            '02' => '02',
            '09' => '09',
        ][(string) $status];
    }

    /**
     * 追加入金/出金导出的公共筛选条件。
     *
     * @param mixed $query 查询构造器。
     * @param Request $request 当前请求对象，读取 user_id、local_order_no、start_date、end_date。
     * @return void
     */
    private function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('local_order_no')) {
            $query->where('local_order_no', 'like', '%' . trim((string) $request->input('local_order_no')) . '%');
        }
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    private function legacyFilterValue(array $filters, string $key)
    {
        if (!array_key_exists($key, $filters) || $filters[$key] === null) {
            return null;
        }

        $value = is_scalar($filters[$key]) ? trim((string) $filters[$key]) : '';

        return $value === '' ? null : $value;
    }

    private function legacyWithdrawStatus($status): string
    {
        return [
            0 => '待处理',
            1 => '正在处理',
            2 => '已处理',
            3 => '处理失败',
        ][(int) $status] ?? '未知状态';
    }

    private function sanitizeCsvCell($value)
    {
        $text = (string) $value;

        return preg_match('/^[=+\-@\t\r\n]/u', $text) ? "'" . $text : $value;
    }

    private function formatCreatedAt($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', (int) $value);
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
            $handle = fopen('php://output', 'wb');
            // 写入 UTF-8 BOM，保证旧版 Excel 直接打开 CSV 时中文不乱码。
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
