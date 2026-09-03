<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Models\CommissionRecord;
use App\Models\UserInfo;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 前台账户流水控制器。
 *
 * 文件功能：
 * - 处理入金流水、出金流水、返佣流水、直属客户流水、直属代理流水、旧前台流水搜索和导出下载。
 * - 新版 Layui/Naive 页面统一读取 accountFlow，旧前台 depositFlowSearch 等入口继续通过 legacyTypedFlow 复用同一套查询逻辑。
 * - 数据可见范围由 flowScopeUserIds 按当前登录用户和流水类型计算，避免代理、直属客户、直属代理之间串看数据。
 *
 * 金额与单位口径：
 * - 金额字段以 USD 为单位：入金取 amount/actual_amount，出金取 apply_amount/actual_amount，返佣取 commission_amount。
 * - 展示金额经 FrontLegacyData::money() 统一为两位小数浮点（仅展示用）；日期与时间统一为整数时间戳。
 * - 三张来源表（deposit_records / withdraw_records / commission_records）都按整数时间戳过滤日期范围。
 *
 * 数据范围：
 * - 本人流水只查询当前用户；直属客户/直属代理流水必须先在 flowScopeUserIds 中命中。
 * - 请求中的 userId 只能在该可见集合内收窄筛选，越界时查询结果强制为空。
 * - 代理类流水类型（direct_*）对非代理账号直接返回 PERMISSION_DENIED。
 */
class FlowController extends FrontBaseController
{
    /**
     * accountFlow 用于返回当前用户账户流水汇总列表。
     *
     * 参数含义：
     * - flow_type 表示流水类型，all=入金/出金/返佣聚合，deposit=本人入金，withdraw=本人出金，direct_deposit=直属客户入金。
     * - flowType 表示旧前台提交的驼峰流水类型别名，读取后统一合并为 flow_type。
     * - date_from 表示流水开始日期，未传时兼容旧前台 startdate。
     * - date_to 表示流水结束日期，未传时兼容旧前台 enddate。
     *
     * 数据来源：
     * - deposit_records 表示入金流水来源表，local_order_no 表示本地订单号。
     * - withdraw_records 表示出金流水来源表，third_order_no 表示第三方订单号。
     * - commission_records 表示返佣流水来源表，commission_amount 会别名为 amount，保持前端列表字段统一。
     * - flow_type_text 表示前端展示的流水类型文案，totalRow 表示当前筛选条件下的汇总行。
     *
     * @param Request $request HTTP 请求对象，承载流水类型、日期范围、分页和旧前台兼容筛选参数。
     * @return JsonResponse 统一 JSON 响应，data.list 为分页流水，data.totalRow 为汇总行。
     */
    public function accountFlow(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $agentId = (int) $userInfo->user_id;
        $flowType = $request->input('flow_type', $request->input('flowType', 'all'));
        if (in_array($flowType, ['direct_deposit', 'direct_withdraw', 'direct_agents_deposit', 'direct_agents_withdraw'], true)
            && (int) $userInfo->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $dateFrom = $request->filled('date_from') ? strtotime($request->input('date_from') . ' 00:00:00') : null;
        $dateTo = $request->filled('date_to') ? strtotime($request->input('date_to') . ' 23:59:59') : null;

        if (!$dateFrom) {
            $dateFrom = FrontLegacyData::timestampFrom($request);
        }
        if (!$dateTo) {
            $dateTo = FrontLegacyData::timestampTo($request);
        }

        if ($flowType !== 'all') {
            return $this->success($this->typedFlow($request, $agentId, $flowType), 'response.query_success');
        }

        // 入金流水子查询：deposit_records 表按当前用户读取，local_order_no 作为前台展示订单号来源。
        $deposits = DB::table('deposit_records')
            ->select('user_id', 'user_name', 'amount', 'actual_amount', 'remarks', 'created_at', DB::raw("'deposit' as type"), 'local_order_no', DB::raw("NULL as third_order_no"), 'status', DB::raw("NULL as bank_name"))
            ->where('user_id', $agentId);

        // 出金流水子查询：withdraw_records 表同时保留 local_order_no 与 third_order_no，展示时由 withdrawDisplayOrderNo 统一兜底。
        $withdrawals = DB::table('withdraw_records')
            ->select('user_id', 'user_name', 'apply_amount as amount', 'actual_amount', 'reject_reason as remarks', 'created_at', DB::raw("'withdraw' as type"), 'local_order_no', 'third_order_no', 'status', 'bank_name')
            ->where('user_id', $agentId);

        // 返佣流水子查询：commission_records 使用新表字段 commission_amount，这里别名为 amount 以兼容同一张资金流水表格。
        $commissions = DB::table('commission_records')
            ->select('agent_id as user_id', DB::raw("'' as user_name"), 'commission_amount as amount', 'real_amount as actual_amount', 'remarks', 'created_at', DB::raw("'commission' as type"), DB::raw("NULL as local_order_no"), DB::raw("NULL as third_order_no"), DB::raw("'02' as status"), DB::raw("NULL as bank_name"))
            ->where('agent_id', $agentId);

        // 三张来源表都使用整数时间戳，先分别套用同一日期范围，再 union 聚合成最终流水列表。
        if ($dateFrom) {
            $deposits->where('created_at', '>=', $dateFrom);
            $withdrawals->where('created_at', '>=', $dateFrom);
            $commissions->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $deposits->where('created_at', '<=', $dateTo);
            $withdrawals->where('created_at', '<=', $dateTo);
            $commissions->where('created_at', '<=', $dateTo);
        }

        $depositTotal = FrontLegacyData::depositTotalRow($deposits);
        $withdrawTotal = FrontLegacyData::withdrawTotalRow($withdrawals);
        $commissionTotal = FrontLegacyData::commissionTotalRow($commissions);
        $totalRow = [
            'order_no' => 'total',
            'user_id' => 'total',
            'amount' => FrontLegacyData::money(($depositTotal['amount'] ?? 0) + ($withdrawTotal['apply_amount'] ?? 0) + ($commissionTotal['commission_amount'] ?? 0)),
            'actual_amount' => FrontLegacyData::money(($depositTotal['actual_amount'] ?? 0) + ($withdrawTotal['actual_amount'] ?? 0) + ($commissionTotal['real_amount'] ?? 0)),
        ];

        // 合并三类流水后统一分页，保证 Layui/Naive 看到同一套排序、分页和汇总规则。
        $query = $deposits->union($withdrawals)->union($commissions);

        $results = DB::query()->fromSub($query, 'flows')
            ->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function ($row) {
                if ((string) $row->type === 'withdraw') {
                    $row->order_no = $this->withdrawDisplayOrderNo($row);
                    $row->flow_type_text = $this->withdrawSourceText($row);
                    return $row;
                }

                if ((string) $row->type === 'deposit') {
                    $row->order_no = trim((string) ($row->local_order_no ?? ''));
                    $row->flow_type_text = __('front.deposit');
                    return $row;
                }

                $row->order_no = '';
                $row->flow_type_text = __('front.commission');

                return $row;
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($results, $totalRow),
            'response.query_success'
        );
    }

    /**
     * typedFlow 用于按指定流水类型查询单类流水。
     *
     * 参数含义：
     * - request 表示当前 HTTP 请求，承载分页、日期、用户 ID、withdraw_source 等筛选条件。
     * - agentId 表示当前登录代理或用户的业务用户 ID，用于计算可见数据范围。
     * - flowType 表示单类流水类型，例如 deposit、withdraw、direct_deposit、direct_agents_withdraw。
     * - withdraw_source 表示出金来源筛选字段，支持 bank_transfer、crypto_currency 或具体银行名称模糊搜索。
     *
     * @param Request $request HTTP 请求对象。
     * @param int $agentId 当前登录业务用户 ID。
     * @param string $flowType 流水类型标识。
     * @return array<string, mixed> 分页列表与汇总行结构。
     */
    private function typedFlow(Request $request, int $agentId, string $flowType)
    {
        $scope = $this->flowScopeUserIds($agentId, $flowType);
        $isWithdraw = in_array($flowType, ['withdraw', 'withdraw_apply', 'direct_withdraw', 'direct_agents_withdraw'], true);
        $query = $isWithdraw ? DB::table('withdraw_records') : DB::table('deposit_records');

        if ($scope) {
            $query->whereIn('user_id', $scope);
        } else {
            $query->whereRaw('1 = 0');
        }

        $requestedUserId = FrontLegacyData::requestedUserId($request);
        if ($requestedUserId !== null) {
            // 请求中的 userId 只能收窄作用域，不能扩大；不在 scope 内时查询结果强制为空。
            if (in_array($requestedUserId, $scope, true)) {
                $query->where('user_id', $requestedUserId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        FrontLegacyData::applyCreatedAtFilter($query, $request);

        if ($isWithdraw && $request->filled('withdraw_source')) {
            $this->applyWithdrawSourceFilter($query, (string) $request->input('withdraw_source'));
        }

        $totalRow = $isWithdraw
            ? FrontLegacyData::withdrawTotalRow($query)
            : FrontLegacyData::depositTotalRow($query);

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request));

        $paginator->getCollection()->transform(function ($row) use ($isWithdraw, $flowType) {
            if ($isWithdraw) {
                return [
                    'order_no' => $this->withdrawDisplayOrderNo($row),
                    'userId' => $row->user_id,
                    'userName' => $row->user_name,
                    'withdrawalType' => $this->withdrawSourceText($row),
                    'withdrawalType2' => FrontLegacyData::withdrawStatusText($row->status),
                    'withdrawalActProfit' => FrontLegacyData::money($row->actual_amount ?: $row->apply_amount),
                    'withdrawalDate' => FrontLegacyData::dateTime($row->created_at),
                    'applyamount' => FrontLegacyData::money($row->apply_amount),
                    'actdraw' => FrontLegacyData::money($row->actual_amount),
                    'drawpoundage' => FrontLegacyData::money($row->fee),
                    'drawrate' => $row->exchange_rate,
                    // 卡号必须脱敏后下发：项目1 前台在 CustomerFlowController.php:308 只输出
                    // 前 4 + **** + 后 4，前台从不下发完整卡号；这里保持同一口径。
                    'drawbankno' => FrontLegacyData::maskBankNo($row->bank_no),
                    'drawbankclass' => $row->bank_name,
                    'applystatus' => FrontLegacyData::withdrawStatusText($row->status),
                    'applyremark' => $row->reject_reason,
                    'rec_crt_date' => FrontLegacyData::dateTime($row->created_at),
                    'directdrawalComment' => $this->withdrawSourceText($row),
                    'directdrawalActProfit' => FrontLegacyData::money($row->actual_amount ?: $row->apply_amount),
                    'directdrawalModifyTime' => FrontLegacyData::dateTime($row->updated_at ?: $row->created_at),
                    'flow_type' => $flowType,
                ];
            }

            return [
                'order_no' => $row->local_order_no,
                'userId' => $row->user_id,
                'depositType' => $row->channel_name,
                'depositComment' => $row->remarks,
                'depositActProfit' => FrontLegacyData::money($row->actual_amount ?: $row->amount),
                'modify_time' => FrontLegacyData::dateTime($row->payment_time ?: $row->updated_at ?: $row->created_at),
                'directType' => $row->channel_name,
                'directProfit' => FrontLegacyData::money($row->actual_amount ?: $row->amount),
                'directComment' => $row->remarks,
                'directModifyTime' => FrontLegacyData::dateTime($row->payment_time ?: $row->updated_at ?: $row->created_at),
                'flow_type' => $flowType,
            ];
        });

        return FrontLegacyData::paginatedListResponse($paginator, $totalRow);
    }

    /**
     * applyWithdrawSourceFilter 用于按银行转账或数字货币过滤出金流水。
     *
     * 参数含义：
     * - query 表示 withdraw_records 查询构造器，会在本方法内追加 bank_name 条件。
     * - withdrawSource 表示出金来源筛选值，bank_transfer=银行卡出金，crypto_currency=数字货币出金。
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query 出金流水查询构造器。
     * @param string $withdrawSource 出金来源筛选值。
     * @return void
     */
    private function applyWithdrawSourceFilter($query, string $withdrawSource): void
    {
        $withdrawSource = trim($withdrawSource);
        if ($withdrawSource === '') {
            return;
        }

        if ($withdrawSource === __('front.bank_transfer') || $withdrawSource === 'bank_transfer') {
            $query->whereNotNull('bank_name')
                ->where('bank_name', '<>', '');
            return;
        }

        if ($withdrawSource === __('front.crypto_currency') || $withdrawSource === 'crypto_currency') {
            $query->where(function ($inner) {
                $inner->orWhereNull('bank_name')
                    ->orWhere('bank_name', '');
            });
            return;
        }

        $query->where('bank_name', 'like', '%' . $withdrawSource . '%');
    }

    /**
     * depositExport 用于导出直属客户入金流水 CSV。
     *
     * 参数含义：
     * - direct_deposit_userId 表示旧前台提交的直属客户用户 ID。
     * - direct_user_id 表示部分旧模板提交的直属用户 ID 别名。
     * - direct_deposit_id 表示旧前台提交的入金订单号筛选，匹配 local_order_no 或 channel_order_no。
     * - direct_deposit_startdate 表示直属客户入金导出开始日期，会合并到旧通用 startdate。
     * - direct_deposit_enddate 表示直属客户入金导出结束日期，会合并到旧通用 enddate。
     * - CSV 文件保存到 storage/app/front_exports，响应 msg 返回不含扩展名的下载文件标识。
     *
     * @param Request $request HTTP 请求对象，承载直属客户、订单号、日期和旧前台兼容字段。
     * @return JsonResponse 导出成功返回文件标识，失败返回旧前台兼容的 FAIL。
     */
    public function depositExport(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);
        if (!$userInfo || (int) $userInfo->account_type !== 1) {
            return response()->json(['msg' => 'FAIL']);
        }

        $agentId = (int) $userInfo->user_id;
        $scope = FrontLegacyData::userScopeIds($agentId, false, 2, true);
        if (!$scope) {
            return response()->json(['msg' => 'FAIL']);
        }

        $query = DepositRecord::query()->whereIn('user_id', $scope);

        $requestedUserId = FrontLegacyData::requestedUserId($request)
            ?? (int) $request->input('direct_deposit_userId', $request->input('direct_user_id', 0));
        if ($requestedUserId > 0) {
            in_array($requestedUserId, $scope, true)
                ? $query->where('user_id', $requestedUserId)
                : $query->whereRaw('1 = 0');
        }

        if ($request->filled('direct_deposit_id')) {
            $orderNo = trim((string) $request->input('direct_deposit_id'));
            $query->where(function ($inner) use ($orderNo) {
                $inner->where('local_order_no', 'like', '%' . $orderNo . '%')
                    ->orWhere('channel_order_no', 'like', '%' . $orderNo . '%');
            });
        }
        if ($request->filled('direct_deposit_startdate') && !$request->filled('startdate')) {
            $request->merge(['startdate' => $request->input('direct_deposit_startdate')]);
        }
        if ($request->filled('direct_deposit_enddate') && !$request->filled('enddate')) {
            $request->merge(['enddate' => $request->input('direct_deposit_enddate')]);
        }

        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $rows = $query->orderBy('created_at', 'desc')
            ->limit(5000)
            ->get();
        if ($rows->isEmpty()) {
            return response()->json(['msg' => 'FAIL']);
        }

        $dir = storage_path('app/front_exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $baseName = 'direct_deposit_transactions_' . date('YmdHis') . '_' . Str::lower(Str::random(6));
        $path = $dir . DIRECTORY_SEPARATOR . $baseName . '.csv';
        $handle = fopen($path, 'wb');
        // 写 UTF-8 BOM 使 Excel 正确识别中文表头；金额统一为两位小数 USD 展示。
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['订单号', '交易账号', '入金类别', '入金来源', '入金金额/USD', '入金时间']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->local_order_no,
                $row->user_id,
                $row->channel_name,
                $row->remarks,
                FrontLegacyData::money($row->actual_amount ?: $row->amount),
                FrontLegacyData::dateTime($row->payment_time ?: $row->updated_at ?: $row->created_at),
            ]);
        }

        fclose($handle);

        $metadata = json_encode([
            'user_id' => $agentId,
            'file' => $baseName . '.csv',
            'created_at' => time(),
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($metadata) || file_put_contents($path . '.meta.json', $metadata) === false) {
            @unlink($path);

            return response()->json(['msg' => 'FAIL']);
        }

        return response()->json(['msg' => $baseName]);
    }

    /**
     * downloadFile 用于下载前台流水导出文件。
     *
     * 参数含义：
     * - file 表示待下载的导出文件名，服务端只保留安全字符并强制落在 front_exports 目录。
     * - role 表示旧前台下载路由携带的角色标识，当前仅用于兼容路由签名，不参与文件路径计算。
     *
     * @param string $file 导出文件名或文件标识。
     * @param string $role 旧前台角色标识。
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse 文件下载响应。
     */
    public function downloadFile(Request $request, string $file, string $role)
    {
        $userInfo = $this->legacyFrontUserInfo($request);
        if (!$userInfo || (int) $userInfo->account_type !== 1) {
            abort(403);
        }

        $safeName = basename($file);
        $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '', $safeName);
        $fileName = Str::endsWith($safeName, '.csv') ? $safeName : $safeName . '.csv';
        $path = storage_path('app/front_exports' . DIRECTORY_SEPARATOR . $fileName);

        if (!is_file($path)) {
            abort(404);
        }

        $metadataPath = $path . '.meta.json';
        if (is_file($metadataPath)) {
            $metadata = json_decode((string) file_get_contents($metadataPath), true);
            if (!is_array($metadata) || (int) ($metadata['user_id'] ?? 0) !== (int) $userInfo->user_id) {
                abort(403);
            }
        }

        return response()->download($path);
    }

    /**
     * depositFlowSearch 用于兼容旧前台入金流水搜索入口。
     *
     * @param Request $request HTTP 请求对象，承载旧前台入金流水筛选参数。
     * @return JsonResponse 入金流水分页响应。
     */
    public function depositFlowSearch(Request $request): JsonResponse
    {
        return $this->legacyTypedFlow($request, 'deposit');
    }

    /**
     * withdrawalFlowSearch 用于兼容旧前台出金流水搜索入口。
     *
     * @param Request $request HTTP 请求对象，承载旧前台出金流水筛选参数。
     * @return JsonResponse 出金流水分页响应。
     */
    public function withdrawalFlowSearch(Request $request): JsonResponse
    {
        return $this->legacyTypedFlow($request, 'withdraw');
    }

    /**
     * withdrawApplyFlowSearch 用于兼容旧前台出金申请流水搜索入口。
     *
     * @param Request $request HTTP 请求对象，承载旧前台出金申请筛选参数。
     * @return JsonResponse 出金申请流水分页响应。
     */
    public function withdrawApplyFlowSearch(Request $request): JsonResponse
    {
        return $this->legacyTypedFlow($request, 'withdraw_apply');
    }

    /**
     * directDepositFlowSearch 用于兼容旧前台直属客户入金流水搜索入口。
     *
     * @param Request $request HTTP 请求对象，承载直属客户入金流水筛选参数。
     * @return JsonResponse 直属客户入金流水分页响应。
     */
    public function directDepositFlowSearch(Request $request): JsonResponse
    {
        return $this->legacyTypedFlow(
            $request,
            $this->isDirectAgentFlowRequest($request) ? 'direct_agents_deposit' : 'direct_deposit'
        );
    }

    /**
     * directWithdrawalFlowSearch 用于兼容旧前台直属客户出金流水搜索入口。
     *
     * @param Request $request HTTP 请求对象，承载直属客户出金流水筛选参数。
     * @return JsonResponse 直属客户出金流水分页响应。
     */
    public function directWithdrawalFlowSearch(Request $request): JsonResponse
    {
        return $this->legacyTypedFlow(
            $request,
            $this->isDirectAgentFlowRequest($request) ? 'direct_agents_withdraw' : 'direct_withdraw'
        );
    }

    /**
     * legacyTypedFlow 用于兼容旧前台独立流水搜索入口。
     *
     * 参数含义：
     * - request 表示旧前台搜索请求，原始字段会继续保留给 FrontLegacyData 解析。
     * - flowType 表示旧接口对应的新流水类型，写入 flow_type 后复用 accountFlow。
     *
     * @param Request $request HTTP 请求对象。
     * @param string $flowType 新版统一流水类型。
     * @return JsonResponse 统一账户流水响应。
     */
    private function legacyTypedFlow(Request $request, string $flowType): JsonResponse
    {
        // 旧项目前台把每个流水页拆成独立 Search 接口；新项目统一由 accountFlow 根据 flow_type 分流。
        // 这里仅注入旧接口对应的流水类型，复用同一套权限、日期和汇总逻辑，避免维护两份查询实现。
        $request->merge(['flow_type' => $flowType]);

        return $this->accountFlow($request);
    }

    /**
     * 判断当前请求是否来自直属代理流水入口。
     *
     * 通过路由名或请求路径中的 direct-agent/directAgents 标识区分直属代理与直属客户入口，
     * 决定旧搜索接口复用 direct_agents_* 还是 direct_* 流水类型。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return bool true 表示直属代理流水入口，false 表示直属客户或其他入口。
     */
    private function isDirectAgentFlowRequest(Request $request): bool
    {
        $route = $request->route();
        $routeName = $route ? (string) $route->getName() : '';

        if (str_contains($routeName, 'direct_agent') || str_contains($routeName, 'direct_agents')) {
            return true;
        }

        $path = $request->path();

        return str_contains($path, 'direct-agent') || str_contains($path, 'directAgents');
    }

    /**
     * withdrawDisplayOrderNo 用于标准化出金展示订单号。
     *
     * 参数含义：
     * - row 表示 withdraw_records 查询结果行，local_order_no 优先，third_order_no 作为兜底。
     *
     * @param object $row 出金流水行对象。
     * @return string 前端展示订单号。
     */
    private function withdrawDisplayOrderNo($row): string
    {
        $localOrderNo = trim((string) ($row->local_order_no ?? ''));
        if ($localOrderNo !== '') {
            return $localOrderNo;
        }

        return trim((string) ($row->third_order_no ?? ''));
    }

    /**
     * withdrawSourceText 用于返回出金来源文案。
     *
     * 参数含义：
     * - row 表示 withdraw_records 查询结果行，bank_name 有值时展示银行转账，否则展示数字货币。
     *
     * @param object $row 出金流水行对象。
     * @return string 多语言出金来源文案。
     */
    private function withdrawSourceText($row): string
    {
        return trim((string) ($row->bank_name ?? '')) !== ''
            ? __('front.bank_transfer')
            : __('front.crypto_currency');
    }

    /**
     * flowScopeUserIds 用于按流水类型计算可见用户范围。
     *
     * 参数含义：
     * - agentId 表示当前登录代理或用户的业务用户 ID。
     * - flowType 表示流水类型；本人流水只返回当前用户，直属客户/直属代理流水按 agent_descendants 计算。
     *
     * @param int $agentId 当前登录业务用户 ID。
     * @param string $flowType 流水类型标识。
     * @return array<int, int> 可见业务用户 ID 列表。
     */
    private function flowScopeUserIds(int $agentId, string $flowType): array
    {
        if (in_array($flowType, ['deposit', 'withdraw', 'withdraw_apply'], true)) {
            return [$agentId];
        }

        if (in_array($flowType, ['direct_deposit', 'direct_withdraw'], true)) {
            return FrontLegacyData::userScopeIds($agentId, false, 2, true);
        }

        if (in_array($flowType, ['direct_agents_deposit', 'direct_agents_withdraw'], true)) {
            return FrontLegacyData::userScopeIds($agentId, false, 1, true);
        }

        return FrontLegacyData::userScopeIds($agentId, true);
    }
}
