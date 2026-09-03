<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Services\AdminDataScopeService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台权益汇总控制器。
 *
 * 文件功能：
 * - 旧项目 `RightsSummaryController` 包含权益汇总、出入金确认、导出等复杂财务逻辑。
 * - 新项目第一阶段先实现权益列表、汇总和手动确认权益结算，保证后台管理员可以按数据表权限处理可验证记录。
 * - 数据来源为真实表 `mt4_users`，并通过 `user_infos.mt4_code = mt4_users.login` 关联业务用户。
 * - 接口权限仍由 `permissions.api_route` + `check.permission:admin` 控制，数据范围由 `AdminDataScopeService` 控制。
 */
class RightsSummaryController extends AdminBaseController
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
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务；用于按当前管理员角色限制可见业务用户。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询权益汇总列表。
     *
     * 参数逻辑说明：
     * - page：当前页码；来自 Layui 表格分页参数，默认第 1 页。
     * - limit/per_page：每页数量；Layui 默认传 `limit`，兼容后端旧接口常用的 `per_page`。
     * - user_id：业务用户 ID；对应 `user_infos.user_id`，用于按客户或代理业务编号筛选。
     * - login：MT4 登录账号；对应 `mt4_users.login`，用于定位交易账号。
     * - user_name：业务用户名；对应 `user_infos.user_name`，用于模糊搜索。
     * - mt4_group：MT4 分组；对应 `mt4_users.group`，用于按交易组筛选。
     * - min_equity/max_equity：净值区间；对应 `mt4_users.equity`，用于财务风险筛选。
     *
     * @param Request $request 当前 HTTP 请求对象，包含筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse 权益列表、分页信息和当前筛选条件下的汇总数据。
     */
    public function rightsSummaryList(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('limit', $request->input('per_page', 15));
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = $this->baseRightsQuery();
        $this->applyFilters($query, $request);

        if ($request->user('admin')) {
            $this->adminDataScopeService->apply($query, $request->user('admin'), 'user', 'user_infos.user_id');
        }

        $summary = $this->summaryFor(clone $query);
        $records = $query
            ->orderByDesc('mt4_users.updated_at')
            ->orderBy('mt4_users.login')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'records' => $records,
            'summary' => $summary,
        ], __('admin.rights_summary_fetched'));
    }

    /**
     * 导出当前筛选条件下的权益汇总 CSV。
     *
     * 导出固定最多 5000 行；只读导出，不触发旧项目 MT4 自动确认或同步副作用。
     *
     * @param Request $request 当前 HTTP 请求对象，包含筛选条件。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportRightsSummary(Request $request)
    {
        if ($filterError = $this->validateNumericFilters($request)) {
            return $filterError;
        }

        $query = $this->baseRightsQuery();
        $this->applyFilters($query, $request);

        if ($request->user('admin')) {
            $this->adminDataScopeService->apply($query, $request->user('admin'), 'user', 'user_infos.user_id');
        }

        $rows = [
            [
                'user_id',
                'user_name',
                'account_type',
                'parent_id',
                'login',
                'name',
                'group',
                'balance',
                'equity',
                'margin',
                'margin_free',
                'leverage',
                'settlement_id',
                'settlement_amount',
                'settlement_status',
                'settlement_remark',
                'updated_at',
            ],
        ];

        $query->orderByDesc('mt4_users.updated_at')
            ->orderBy('mt4_users.login')
            ->limit(5000)
            ->get()
            ->each(function ($record) use (&$rows) {
                $rows[] = [
                    $record->user_id,
                    $record->user_name,
                    $record->account_type,
                    $record->parent_id,
                    $record->login,
                    $record->name,
                    $record->group,
                    $record->balance,
                    $record->equity,
                    $record->margin,
                    $record->margin_free,
                    $record->leverage,
                    $record->settlement_id,
                    $record->settlement_amount,
                    $record->settlement_status,
                    $record->settlement_remark,
                    $record->updated_at,
                ];
            });

        return $this->csvDownload('rights_summary_export.csv', $rows);
    }

    /**
     * 手动确认权益结算记录。
     *
     * 业务边界说明：
     * - 旧项目自动确认会调用 MT4 入出金接口，迁移风险较高；当前方法只处理人工确认，不伪造 MT4 成功。
     * - 数据来源为真实表 `rights_settlements`，仅允许把 status=0 的待处理记录更新为 status=1。
     * - 管理员数据范围仍通过 `AdminDataScopeService::canAccessUser` 校验，避免越权确认其它代理或客户数据。
     *
     * 参数逻辑说明：
     * - $id：`rights_settlements.id` 主键，来自路由参数，用于定位单条待确认权益结算记录。
     * - manual_confirm_reason：人工确认原因或备注，写入 `rights_settlements.remark`，用于财务审计追踪。
     *
     * @param Request $request 当前 HTTP 请求对象，包含当前管理员和确认备注。
     * @param int|string $id 权益结算记录主键。
     * @return \Illuminate\Http\JsonResponse 手动确认结果。
     */
    public function manualConfirmRightsSettlement(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'manual_confirm_reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        if ($routeIdError = $this->validateRightsSettlementRouteId($id)) {
            return $routeIdError;
        }

        $settlement = DB::table('rights_settlements')
            ->where('id', (int) $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$settlement) {
            return $this->error(__('admin.rights_settlement_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $admin = $request->user('admin');
        if ($admin && !$this->adminDataScopeService->canAccessUser($admin, (int) $settlement->user_id, 'user')) {
            return $this->error(__('admin.rights_settlement_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        if ((int) $settlement->status !== 0) {
            return $this->error(__('admin.rights_settlement_only_pending'), ResponseCode::VALIDATION_FAILED);
        }

        $reason = trim((string) $request->input('manual_confirm_reason'));
        DB::table('rights_settlements')
            ->where('id', (int) $settlement->id)
            ->where('status', 0)
            ->update([
                'status' => 1,
                'remark' => $reason,
                'updated_at' => time(),
            ]);

        return $this->success([], __('admin.rights_settlement_confirmed'), ResponseCode::UPDATED);
    }

    /**
     * 校验权益结算记录路由 ID。
     *
     * @param mixed $id 路由中的 rights_settlements.id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateRightsSettlementRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验可选数字筛选参数，避免非严格数字进入 SQL 前被强转。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，全部合法时返回 null。
     */
    private function validateNumericFilters(Request $request)
    {
        $rules = [];

        if ($request->filled('user_id')) {
            $rules['user_id'] = 'integer';
        }

        if ($request->filled('login')) {
            $rules['login'] = 'integer';
        }

        if ($request->filled('min_equity')) {
            $rules['min_equity'] = 'numeric';
        }

        if ($request->filled('max_equity')) {
            $rules['max_equity'] = 'numeric';
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
     * 构建权益汇总基础查询。
     *
     * 字段逻辑说明：
     * - `mt4_users` 提供 MT4 资金字段，是权益汇总的主表。
     * - `user_infos` 提供业务用户 ID、用户名、代理关系和账号类型，用于数据范围过滤和页面展示。
     * - `latest_rights_settlements` 子查询取每个用户最新一条权益结算记录，再关联回详情，保证结算信息取的是最新状态。
     *
     * @return \Illuminate\Database\Query\Builder 权益汇总基础查询对象。
     */
    private function baseRightsQuery()
    {
        $latestSettlementQuery = DB::table('rights_settlements')
            ->selectRaw('MAX(id) as id, user_id')
            ->whereNull('deleted_at')
            ->groupBy('user_id');

        return DB::table('mt4_users')
            ->leftJoin('user_infos', 'user_infos.mt4_code', '=', 'mt4_users.login')
            ->leftJoinSub($latestSettlementQuery, 'latest_rights_settlements', function ($join) {
                $join->on('latest_rights_settlements.user_id', '=', 'user_infos.user_id');
            })
            ->leftJoin('rights_settlements', function ($join) {
                $join->on('rights_settlements.id', '=', 'latest_rights_settlements.id');
            })
            ->select([
                'mt4_users.id',
                'mt4_users.login',
                'mt4_users.name',
                'mt4_users.group',
                'mt4_users.balance',
                'mt4_users.equity',
                'mt4_users.margin',
                'mt4_users.margin_free',
                'mt4_users.leverage',
                'mt4_users.updated_at',
                'user_infos.user_id',
                'user_infos.user_name',
                'user_infos.account_type',
                'user_infos.parent_id',
                'rights_settlements.id as settlement_id',
                'rights_settlements.amount as settlement_amount',
                'rights_settlements.status as settlement_status',
                'rights_settlements.remark as settlement_remark',
            ]);
    }

    /**
     * 给权益汇总查询追加筛选条件。
     *
     * @param \Illuminate\Database\Query\Builder $query 权益汇总查询对象，会被直接追加 where 条件。
     * @param Request $request 当前请求对象，读取页面筛选参数。
     * @return void
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->filled('user_id')) {
            $query->where('user_infos.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('login')) {
            $query->where('mt4_users.login', (int) $request->input('login'));
        }

        if ($request->filled('user_name')) {
            $query->where('user_infos.user_name', 'like', '%' . $request->input('user_name') . '%');
        }

        if ($request->filled('mt4_group')) {
            $query->where('mt4_users.group', 'like', '%' . $request->input('mt4_group') . '%');
        }

        if ($request->filled('min_equity')) {
            $query->where('mt4_users.equity', '>=', (float) $request->input('min_equity'));
        }

        if ($request->filled('max_equity')) {
            $query->where('mt4_users.equity', '<=', (float) $request->input('max_equity'));
        }
    }

    /**
     * 计算当前筛选条件下的权益汇总。
     *
     * @param \Illuminate\Database\Query\Builder $query 已经追加筛选和数据范围的查询对象副本。
     * @return array<string, float|int> 页面顶部统计卡片使用的聚合结果。
     */
    private function summaryFor($query)
    {
        $depositAmount = $this->sumScopedOnlineDepositAmount(clone $query);
        $withdrawAmount = $this->sumScopedOnlineWithdrawAmount(clone $query);
        $commissionAmount = $this->sumScopedOnlineCommissionAmount(clone $query);

        $summary = DB::query()
            ->fromSub($query, 'rights_scope')
            ->selectRaw('COUNT(*) as total_accounts')
            ->selectRaw('COALESCE(SUM(rights_scope.balance), 0) as total_balance')
            ->selectRaw('COALESCE(SUM(rights_scope.equity), 0) as total_equity')
            ->selectRaw('COALESCE(SUM(rights_scope.margin), 0) as total_margin')
            ->selectRaw('COALESCE(SUM(rights_scope.margin_free), 0) as total_margin_free')
            ->first();

        return [
            'total_accounts' => (int) ($summary->total_accounts ?? 0),
            'total_balance' => (float) ($summary->total_balance ?? 0),
            'total_equity' => (float) ($summary->total_equity ?? 0),
            'total_margin' => (float) ($summary->total_margin ?? 0),
            'total_margin_free' => (float) ($summary->total_margin_free ?? 0),
            'online_settlement_deposit_amount' => $depositAmount,
            'online_settlement_withdraw_amount' => $withdrawAmount,
            'online_settlement_commission_amount' => $commissionAmount,
            'online_settlement_net_amount' => $depositAmount - $withdrawAmount + $commissionAmount,
        ];
    }

    /**
     * 生成当前权益筛选范围内的业务用户 ID 子查询。
     *
     * 逻辑说明：
     * - 权益列表以 `mt4_users` 为主表，但入金、出金和返佣都按业务用户 ID 归属。
     * - 这里复用已经追加筛选条件和管理员数据范围的权益查询，保证汇总金额和页面列表看到的是同一批用户。
     * - 仅返回非空 `user_id`，避免未绑定 CRM 用户的 MT4 快照误参与资金聚合。
     *
     * @param \Illuminate\Database\Query\Builder $query 已经带有筛选条件和数据范围的权益查询副本。
     * @return \Illuminate\Database\Query\Builder 可传入 whereIn 的用户 ID 子查询。
     */
    private function scopedUserIdQuery($query)
    {
        return DB::query()
            ->fromSub($query, 'rights_scope')
            ->select('rights_scope.user_id')
            ->whereNotNull('rights_scope.user_id')
            ->distinct();
    }

    /**
     * 汇总当前范围内已支付入金金额。
     *
     * @param \Illuminate\Database\Query\Builder $query 当前权益筛选范围查询副本。
     * @return float 已支付入金金额；status=02 表示支付完成，actual_amount 为 0 时回退申请金额。
     */
    private function sumScopedOnlineDepositAmount($query): float
    {
        return (float) (DB::table('deposit_records')
            ->whereIn('user_id', $this->scopedUserIdQuery($query))
            ->where('status', '02')
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(CASE WHEN actual_amount IS NULL OR actual_amount = 0 THEN amount ELSE actual_amount END), 0) as aggregate_amount')
            ->value('aggregate_amount') ?? 0);
    }

    /**
     * 汇总当前范围内已完成出金金额。
     *
     * @param \Illuminate\Database\Query\Builder $query 当前权益筛选范围查询副本。
     * @return float 已完成出金金额；status=2 表示出金完成，actual_amount 为 0 时回退出金申请金额。
     */
    private function sumScopedOnlineWithdrawAmount($query): float
    {
        return (float) (DB::table('withdraw_records')
            ->whereIn('user_id', $this->scopedUserIdQuery($query))
            ->where('status', 2)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(CASE WHEN actual_amount IS NULL OR actual_amount = 0 THEN apply_amount ELSE actual_amount END), 0) as aggregate_amount')
            ->value('aggregate_amount') ?? 0);
    }

    /**
     * 汇总当前范围内已结算返佣金额。
     *
     * @param \Illuminate\Database\Query\Builder $query 当前权益筛选范围查询副本。
     * @return float 已结算返佣金额；settle_status=2 表示返佣已结算，real_amount 为 0 时回退应返金额。
     */
    private function sumScopedOnlineCommissionAmount($query): float
    {
        return (float) (DB::table('commission_records')
            ->whereIn('agent_id', $this->scopedUserIdQuery($query))
            ->where('settle_status', 2)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(CASE WHEN real_amount IS NULL OR real_amount = 0 THEN commission_amount ELSE real_amount END), 0) as aggregate_amount')
            ->value('aggregate_amount') ?? 0);
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
