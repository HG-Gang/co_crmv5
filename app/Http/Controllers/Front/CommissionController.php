<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\CommissionRecord;
use App\Models\UserInfo;
use App\Services\CommissionService;
use App\Services\CommissionTransfer\CommissionTransferService;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use DomainException;
use Throwable;

/**
 * 前台返佣管理控制器。
 *
 * 文件功能：
 * - 处理实时返佣计算、返佣历史、返佣统计分析、旧前台返佣详情和佣金转账。
 * - 实时返佣从当前代理可见的已平仓订单计算，历史返佣从 commission_records 表读取。
 * - 佣金转账只允许转给当前代理的直属下级代理，并通过事务同时写入出账和入账流水。
 *
 * 金额与精度口径：
 * - 返佣金额以 USD 为单位，展示统一经 FrontLegacyData::money() 保留两位小数。
 * - 佣金转账金额必须匹配两位小数格式（0.01 步进），整数部分最多 16 位，与 DECIMAL(18,2) 对齐。
 *
 * 安全边界：
 * - 实时返佣、历史返佣与转账入口都要求当前账号为代理（account_type=1），普通客户直接拒绝。
 * - 佣金转账目标必须是当前代理的直属下级代理，直属关系由共享代理树作用域判定，不能凭请求参数指定任意账号。
 * - 转账依赖幂等键与事务型出账/入账流水；DomainException 映射为明确业务错误，未知异常不向用户暴露细节。
 */
class CommissionController extends FrontBaseController
{
    /**
     * 佣金服务。
     *
     * @var CommissionService
     */
    protected $commissionService;

    /**
     * 佣金互转服务。
     *
     * @var CommissionTransferService
     */
    protected $commissionTransferService;

    /**
     * 构造前台返佣控制器。
     *
     * @param CommissionService $commissionService 返佣计算服务，用于计算订单逐级返佣明细和当前代理可见返佣。
     */
    public function __construct(
        CommissionService $commissionService,
        CommissionTransferService $commissionTransferService
    )
    {
        $this->commissionService = $commissionService;
        $this->commissionTransferService = $commissionTransferService;
    }

    /**
     * realTime 用于返回当前代理可见的实时返佣订单列表。
     *
     * 参数逻辑说明：
     * - userId 表示被筛选的下级客户或代理业务用户 ID，对应 user_trades.user_id。
     * - orderId 表示 MT4 订单号筛选字段，对应 user_trades.ticket。
     * - detail_commission 表示是否返回逐级返佣明细；1=返回 commission_details，0=只返回当前代理摘要字段。
     * - current_commission_amount 表示当前代理在该订单中的返佣金额。
     * - current_commission_status 表示当前代理该笔返佣结算状态。
     * - current_commission_status_text 表示当前代理该笔返佣状态文案。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选参数、分页参数和当前代理登录身份。
     * @return JsonResponse 当前代理可见的实时返佣订单分页与汇总响应。
     */
    public function realTime(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $userLogin->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $userLogin->user_id;
        $descendantIds = FrontLegacyData::userScopeIds((int) $agentId, false);
        $query = \App\Models\UserTrade::whereIn('user_id', $descendantIds)
            ->with(['user.login', 'user.level'])
            ->closed();

        FrontLegacyData::applyDateTimeFilter($query, $request, 'close_time');
        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }
        if ($request->filled('orderId')) {
            $query->where('ticket', $request->input('orderId'));
        }

        $totalQuery = clone $query;
        $totalRow = FrontLegacyData::rebateTotalRow($totalQuery);

        $commissionDetails = (bool) $request->boolean('detail_commission', false);
        $list = $query->orderBy('close_time', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function ($trade) use ($agentId, $commissionDetails) {
                $row = FrontLegacyData::tradeAliasRow($trade);
                $currentCommission = $this->currentAgentOrderCommission($trade, (int) $agentId);
                $row['modify_time'] = $row['modify_time'] ?: $row['open_time'];
                $profit = (float) ($row['profit'] ?? 0);
                $row['profit_gain'] = FrontLegacyData::money(max($profit, 0));
                $row['profit_loss'] = FrontLegacyData::money(abs(min($profit, 0)));
                $row['profit_net'] = FrontLegacyData::money($row['profit_gain'] - $row['profit_loss']);
                $row['user_info'] = $this->userDetail($trade->user);
                $row['order_chain'] = $this->orderChain($trade->user, (int) $agentId);
                $row['commission_details'] = $commissionDetails ? $this->commissionService->orderCommissionDetails($trade, (int) $agentId) : [];
                $row['current_commission_amount'] = $currentCommission['current_commission_amount'];
                $row['current_commission_status'] = $currentCommission['current_commission_status'];
                $row['current_commission_status_text'] = $currentCommission['current_commission_status_text'];
                $row['rebate_ratio'] = $currentCommission['rebate_ratio'];
                $row['rebate_ratio_value'] = $currentCommission['rebate_ratio_value'];
                $row['commission_updated_at'] = $currentCommission['commission_updated_at'];
                $row['order_created_at'] = FrontLegacyData::dateTime($trade->open_time);
                $row['order_closed_at'] = FrontLegacyData::dateTime($trade->close_time);

                return $row;
            });

        $rows = $list->getCollection();
        $profitGain = FrontLegacyData::money($rows->sum('profit_gain'));
        $profitLoss = FrontLegacyData::money($rows->sum('profit_loss'));
        $profitNet = FrontLegacyData::money($profitGain - $profitLoss);

        $comm = [
            'total' => $totalRow['total_commission'] ?? 0,
        ];
        $comm['total_commission'] = $totalRow['total_commission'] ?? 0;
        $comm['total_volume'] = $rows->sum('volume_lots');
        $comm['profit_gain'] = $profitGain;
        $comm['profit_loss'] = $profitLoss;
        $comm['profit_net'] = $profitNet;
        $comm['total_profit'] = $profitNet;
        $comm['list'] = $list;
        $comm['totalRow'] = array_merge($totalRow, [
            'total_commission' => $comm['total_commission'] ?? 0,
            'total_volume' => $comm['total_volume'] ?? 0,
            'profit_gain' => $comm['profit_gain'] ?? 0,
            'profit_loss' => $comm['profit_loss'] ?? 0,
            'profit_net' => $comm['profit_net'] ?? 0,
        ]);
        $comm['summary'] = $comm['totalRow'];

        return $this->success($comm, 'response.query_success', ResponseCode::SUCCESS);
    }

    /**
     * realtimeRebateSearch 用于兼容旧前台实时返佣搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，参数与 realTime 保持一致。
     * @return JsonResponse 复用 realTime 的实时返佣响应。
     */
    public function realtimeRebateSearch(Request $request): JsonResponse
    {
        return $this->realTime($request);
    }

    /**
     * realtimeRebateDetail 用于兼容旧前台实时返佣详情弹层。
     *
     * 参数逻辑说明：
     * - orderNo 表示旧前台传入的 MT4 订单号，对应 user_trades.ticket。
     * - role 表示旧前台详情弹层展示的查看角色，仅用于页面展示，不参与权限判断。
     * - 当前代理身份来自登录 Token，详情只展示当前代理可见代理链路内的返佣明细。
     *
     * @param Request $request 当前 HTTP 请求对象，用于读取当前代理登录身份。
     * @param mixed $orderNo 旧前台传入的 MT4 订单号。
     * @param mixed $role 旧前台弹层展示角色文本。
     * @return \Illuminate\Http\Response 旧前台可直接渲染的返佣详情 HTML。
     */
    public function realtimeRebateDetail(Request $request, $orderNo, $role)
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin || (int) $userLogin->account_type !== 1) {
            abort(403);
        }

        $agentId = $this->legacyFrontUserId($request);
        if ($agentId <= 0) {
            abort(403);
        }

        $trade = \App\Models\UserTrade::with(['user.login', 'user.level'])
            ->where('ticket', $orderNo)
            ->whereIn('user_id', FrontLegacyData::userScopeIds($agentId, false))
            ->first();

        if (!$trade) {
            abort(404);
        }

        $currentCommission = $this->currentAgentOrderCommission($trade, (int) $agentId);
        $rebates = collect($this->commissionService->orderCommissionDetails($trade, $agentId))
            ->map(function (array $rebate) {
                return (object) [
                    'agent_id' => $rebate['agent_id'] ?? '',
                    'agent_level' => $rebate['agent_level'] ?? '',
                    'commission_amount' => $rebate['commission_amount'] ?? 0,
                    'rebate_ratio' => $rebate['rebate_ratio'] ?? '',
                    'settle_status_text' => ($rebate['settle_status_text'] ?? '') ?: '',
                    'rebate_time' => $rebate['rebate_time'] ?? '',
                ];
            });

        $row = FrontLegacyData::tradeAliasRow($trade);
        $html = '<div class="crm-legacy-rebate">';
        $html .= '<style>.crm-legacy-rebate{font-family:Arial,"Microsoft YaHei",sans-serif;padding:16px;color:#243042}.crm-legacy-rebate h3{margin:0 0 12px;font-size:18px}.crm-legacy-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.crm-legacy-item{border:1px solid #e7edf3;border-radius:6px;padding:10px;background:#fff}.crm-legacy-label{font-size:12px;color:#708196}.crm-legacy-value{margin-top:4px;font-size:15px;font-weight:600;color:#1f2a37}.crm-legacy-rebate table{width:100%;border-collapse:collapse;margin-top:14px}.crm-legacy-rebate th,.crm-legacy-rebate td{border:1px solid #e7edf3;padding:8px;text-align:left;font-size:13px}.crm-legacy-rebate th{background:#f6f8fb;color:#526173}</style>';
        $html .= '<h3>实时返佣详情</h3>';
        $html .= '<div class="crm-legacy-grid">';
        $html .= $this->legacyDetailItem('订单号', $row['ticket']);
        $html .= $this->legacyDetailItem('用户ID', $row['user_id']);
        $html .= $this->legacyDetailItem('交易品种', $row['symbol']);
        $html .= $this->legacyDetailItem('方向', $row['cmd_text']);
        $html .= $this->legacyDetailItem('手数', $row['volume_lots']);
        $html .= $this->legacyDetailItem('订单盈亏', $row['profit']);
        $html .= $this->legacyDetailItem('当前账户返佣', $currentCommission['current_commission_amount']);
        $html .= $this->legacyDetailItem('当前账户返佣比例', $currentCommission['rebate_ratio']);
        $html .= $this->legacyDetailItem('当前账户返佣状态', $currentCommission['current_commission_status_text']);
        $html .= $this->legacyDetailItem('返佣更新时间', $currentCommission['commission_updated_at']);
        $html .= $this->legacyDetailItem('角色', $role);
        $html .= '</div>';
        $html .= '<table><thead><tr><th>代理ID</th><th>代理级别</th><th>返佣金额</th><th>返佣比例</th><th>结算状态</th><th>返佣时间</th></tr></thead><tbody>';

        if ($rebates->isEmpty()) {
            $html .= '<tr><td colspan="6">暂无返佣明细</td></tr>';
        } else {
            foreach ($rebates as $rebate) {
                $html .= '<tr>';
                $html .= '<td>' . e((string) $rebate->agent_id) . '</td>';
                $html .= '<td>' . e((string) $rebate->agent_level) . '</td>';
                $html .= '<td>' . e((string) FrontLegacyData::money($rebate->commission_amount)) . '</td>';
                $html .= '<td>' . e((string) $rebate->rebate_ratio) . '</td>';
                $html .= '<td>' . e((string) $rebate->settle_status_text) . '</td>';
                $html .= '<td>' . e((string) $rebate->rebate_time) . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';

        return response($html);
    }

    /**
     * userDetail 用于返回订单所属用户基础资料。
     *
     * 参数逻辑说明：
     * - user 表示订单所属业务用户资料，可能是代理账号或普通客户账号。
     * - account_type_text 表示账号类型文案，1=代理，2=普通客户。
     * - agent_level_name 只用于代理账号；普通客户没有代理级别字段。
     *
     * @param UserInfo|null $user 订单所属用户资料；为空时返回空数组。
     * @return array<string, mixed> 前台可展示的用户基础资料。
     */
    private function userDetail(UserInfo $user = null): array
    {
        if (!$user) {
            return [];
        }

        $detail = array_merge(FrontLegacyData::userBasicAlias($user), [
            'account_type_text' => (int) $user->account_type === 1 ? __('register.agent') : __('register.customer'),
        ]);

        if ((int) $user->account_type !== 1) {
            return $detail;
        }

        $level = $user->relationLoaded('level') ? $user->level : $user->level;
        $rank = (int) ($level->level_code ?? $user->level_id ?? 5);
        if ($rank < 1 || $rank > 5) {
            $rank = 5;
        }

        return array_merge($detail, [
            'agent_level_rank' => $rank,
            'agent_level_name' => $level->name ?? ('Level ' . $rank),
        ]);
    }

    /**
     * orderChain 用于返回当前代理可见的订单用户代理链路。
     *
     * 参数逻辑说明：
     * - user 表示订单所属用户资料，family_tree 中保存从根代理到当前用户的代理链路。
     * - viewerAgentId 表示当前查看详情的代理业务用户 ID，链路会从该代理开始截断。
     *
     * @param UserInfo|null $user 订单所属用户资料。
     * @param int $viewerAgentId 当前登录代理的业务用户 ID。
     * @return array<int, array<string, mixed>> 当前代理可见的订单用户链路节点。
     */
    private function orderChain(UserInfo $user = null, int $viewerAgentId): array
    {
        if (!$user || $viewerAgentId <= 0) {
            return [];
        }

        $ids = $this->orderChainIds($user);

        $viewerIndex = array_search($viewerAgentId, $ids, true);
        if ($viewerIndex === false) {
            return (int) $user->user_id === $viewerAgentId ? [[
                'user_id' => (int) $user->user_id,
                'user_name' => (string) $user->user_name,
                'account_type_text' => __('register.agent'),
            ]] : [];
        }

        $visibleIds = array_slice($ids, $viewerIndex);
        $users = UserInfo::with('level')
            ->whereIn('user_id', $visibleIds)
            ->get()
            ->keyBy('user_id');

        return array_values(array_filter(array_map(function (int $id) use ($users) {
            $node = $users->get($id);
            if (!$node) {
                return null;
            }

            return [
                'user_id' => (int) $node->user_id,
                'user_name' => (string) $node->user_name,
                'account_type_text' => (int) $node->account_type === 1 ? __('register.agent') : __('register.customer'),
                'agent_level_name' => (int) $node->account_type === 1 ? ($node->level->name ?? ('Level ' . $node->level_id)) : '',
            ];
        }, $visibleIds)));
    }

    /**
     * 从 family_tree 解析订单用户的代理链 ID；为空时回退到按 parent_id 逐级回溯。
     *
     * @param UserInfo $user 订单所属用户资料。
     * @return array<int, int> 从根代理到当前用户排列的 ID 数组。
     */
    private function orderChainIds(UserInfo $user): array
    {
        return $this->parentOrderChainIds($user);
    }

    /**
     * 按 parent_id 逐级回溯构建代理链，visited 集合阻断历史脏数据成环。
     *
     * @param UserInfo $user 订单所属用户资料。
     * @return array<int, int> 从根代理到当前用户排列的 ID 数组。
     */
    private function parentOrderChainIds(UserInfo $user): array
    {
        $ids = [(int) $user->user_id];
        $visited = [(int) $user->user_id => true];
        $parentId = (int) $user->parent_id;

        while ($parentId > 0 && !isset($visited[$parentId])) {
            array_unshift($ids, $parentId);
            $visited[$parentId] = true;
            $parentId = (int) UserInfo::where('user_id', $parentId)->value('parent_id');
        }

        return $ids;
    }

    /**
     * 构造旧前台返佣详情弹层中的单个字段块。
     *
     * @param string $label 字段名称，例如订单号、当前账户返佣、返佣比例。
     * @param mixed $value 字段展示值，会经过 e() 转义后输出。
     * @return string 旧前台详情弹层 HTML 片段。
     */
    private function legacyDetailItem(string $label, $value): string
    {
        return '<div class="crm-legacy-item"><div class="crm-legacy-label">' . e($label) . '</div><div class="crm-legacy-value">' . e((string) $value) . '</div></div>';
    }

    /**
     * currentAgentOrderCommission 用于计算当前代理在单笔订单中的返佣状态。
     *
     * 参数逻辑说明：
     * - trade 表示已平仓交易订单，ticket 用于匹配 commission_records.mt4_order_id。
     * - agentId 表示当前登录代理业务用户 ID，只返回该代理自身在订单中的返佣金额和状态。
     *
     * @param \App\Models\UserTrade $trade 已平仓交易订单模型。
     * @param int $agentId 当前代理业务用户 ID。
     * @return array<string, mixed> 当前代理单笔订单返佣金额、返佣比例、状态和更新时间。
     */
    private function currentAgentOrderCommission(\App\Models\UserTrade $trade, int $agentId): array
    {
        $record = CommissionRecord::where('agent_id', $agentId)
            ->where('mt4_order_id', (int) $trade->ticket)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();

        $detail = collect($this->commissionService->orderCommissionDetails($trade, $agentId))
            ->first(function ($item) use ($agentId) {
                return (int) ($item['agent_id'] ?? 0) === $agentId;
            });

        $status = (int) ($record->settle_status ?? ($detail['settle_status'] ?? 0));

        return [
            'current_commission_amount' => FrontLegacyData::money($record ? $record->commission_amount : ($detail['commission_amount'] ?? 0)),
            'current_commission_status' => $status,
            'current_commission_status_text' => $status > 0 ? $this->settleStatusText($status) : __('front.no_rebate'),
            'rebate_ratio' => (string) ($detail['rebate_ratio'] ?? ''),
            'rebate_ratio_value' => (float) ($detail['rebate_ratio_value'] ?? 0),
            'commission_updated_at' => $record ? FrontLegacyData::dateTime($record->updated_at ?: $record->created_at) : (string) ($detail['rebate_time'] ?? ''),
        ];
    }

    /**
     * 将返佣结算状态码转换为前端文案。
     *
     * @param int $status 结算状态码，2=已结算，其余按待结算处理。
     * @return string 前端语言包文案。
     */
    private function settleStatusText(int $status): string
    {
        return $status === 2
            ? __('front.status_settled')
            : __('front.status_pending_settle');
    }

    /**
     * history 用于返回当前代理已结算或待结算的返佣历史。
     *
     * 参数逻辑说明：
     * - date_from 表示返佣历史开始日期，会转换为 commission_records.created_at 时间戳下限。
     * - date_to 表示返佣历史结束日期，会转换为 commission_records.created_at 时间戳上限。
     * - orderId 表示 MT4 订单号筛选字段，对应 commission_records.mt4_order_id。
     * - dataType 表示返佣记录类型筛选字段，对应 commission_records.data_type，例如 transfer。
     *
     * @param Request $request 当前 HTTP 请求对象，承载历史筛选、分页参数和当前代理身份。
     * @return JsonResponse 当前代理返佣历史分页、汇总与统计分析响应。
     */
    public function history(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $userLogin->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $userLogin->user_id;

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = CommissionRecord::where('agent_id', $agentId);

        // commission_records.created_at 在重建表中保存 10 位 Unix 时间戳，日期筛选必须先转换为时间戳再查询。
        if (!$dateFrom) $dateFrom = FrontLegacyData::dateFrom($request);
        if (!$dateTo) $dateTo = FrontLegacyData::dateTo($request);
        if ($dateFrom) $query->where('created_at', '>=', strtotime($dateFrom . ' 00:00:00'));
        if ($dateTo) $query->where('created_at', '<=', strtotime($dateTo . ' 23:59:59'));
        if ($request->filled('orderId')) {
            $query->where('mt4_order_id', (int) $request->input('orderId'));
        }
        if ($request->filled('dataType')) {
            $query->where('data_type', $request->input('dataType'));
        }

        $analytics = $this->commissionHistoryAnalytics(clone $query, $agentId);
        $totalRow = FrontLegacyData::commissionTotalRow($query);

        $records = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (CommissionRecord $record) {
                $row = $record->toArray();
                $row['profit'] = FrontLegacyData::money($record->commission_amount);
                $row['commission_amount'] = FrontLegacyData::money($record->commission_amount);
                $row['returned_amount'] = FrontLegacyData::money($record->returned_amount);
                $row['real_amount'] = FrontLegacyData::money($record->real_amount);
                $row['agent_profit'] = FrontLegacyData::money($record->agent_profit);
                $row['agent_volume'] = FrontLegacyData::lots($record->agent_volume);
                $row['comment'] = $record->remarks;
                $row['order_no'] = $record->mt4_order_id ?: '';
                $row['settle_status_text'] = (int) $record->settle_status === 2
                    ? __('front.status_settled')
                    : __('front.status_pending_settle');
                $row['created_time'] = FrontLegacyData::dateTime($record->created_at);
                $row['modify_time'] = FrontLegacyData::dateTime($record->updated_at ?: $record->created_at);

                return $row;
            });

        $response = FrontLegacyData::paginatedListResponse($records, $totalRow);
        $response['analytics'] = $analytics;

        return $this->success(
            $response,
            __('response.query_success'),
            ResponseCode::SUCCESS
        );
    }

    /**
     * commissionHistoryAnalytics 用于返回返佣趋势和性别维度统计。
     *
     * 参数逻辑说明：
     * - baseQuery 表示已经按当前代理、日期、订单号和 dataType 过滤后的返佣记录查询对象。
     * - agentId 表示当前代理业务用户 ID，会原样返回给前端用于核对统计归属。
     * - ranges 表示近 3、7、15、30 天返佣趋势统计。
     * - gender 表示订单所属客户性别维度的数量占比和返佣金额占比。
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery 已套用基础筛选的返佣记录查询。
     * @param int $agentId 当前代理业务用户 ID。
     * @return array<string, mixed> 返佣趋势和性别维度统计数据。
     */
    private function commissionHistoryAnalytics($baseQuery, int $agentId): array
    {
        $now = time();
        $ranges = [];

        $rangeConfigs = [
            ['days' => 3],
            ['days' => 7],
            ['days' => 15],
            ['days' => 30],
        ];

        foreach ($rangeConfigs as $rangeConfig) {
            $days = (int) $rangeConfig['days'];
            $from = $now - ($days * 86400);
            $row = (clone $baseQuery)
                ->where('created_at', '>=', $from)
                ->selectRaw('COUNT(*) as records_count')
                ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_sum')
                ->selectRaw('COALESCE(SUM(real_amount), 0) as real_sum')
                ->selectRaw('COALESCE(SUM(agent_volume), 0) as volume_sum')
                ->first();

            $ranges[$days] = [
                'days' => $days,
                'records_count' => (int) ($row->records_count ?? 0),
                'commission_amount' => FrontLegacyData::money($row->commission_sum ?? 0),
                'real_amount' => FrontLegacyData::money($row->real_sum ?? 0),
                'agent_volume' => FrontLegacyData::lots($row->volume_sum ?? 0),
            ];
        }

        $records = (clone $baseQuery)->get(['mt4_order_id', 'commission_amount']);
        $orderIds = $records->pluck('mt4_order_id')->filter()->unique()->map(function ($id) {
            return (int) $id;
        })->values()->all();
        $trades = \App\Models\UserTrade::whereIn('ticket', $orderIds)
            ->get(['ticket', 'user_id'])
            ->keyBy('ticket');
        $userIds = $trades->pluck('user_id')->filter()->unique()->values()->all();
        $users = UserInfo::whereIn('user_id', $userIds)
            ->get(['user_id', 'gender'])
            ->keyBy('user_id');
        $gender = [
            'male' => ['label' => __('register.male'), 'count' => 0, 'commission_amount' => 0],
            'female' => ['label' => __('register.female'), 'count' => 0, 'commission_amount' => 0],
            'unknown' => ['label' => __('response.unknown'), 'count' => 0, 'commission_amount' => 0],
        ];

        foreach ($records as $record) {
            $trade = $trades->get((int) $record->mt4_order_id);
            $user = $trade ? $users->get((int) $trade->user_id) : null;
            $key = $user && (int) $user->gender === 1 ? 'male' : ($user && (int) $user->gender === 2 ? 'female' : 'unknown');
            $gender[$key]['count']++;
            $gender[$key]['commission_amount'] += (float) $record->commission_amount;
        }

        $totalGenderCount = array_sum(array_column($gender, 'count'));
        $totalGenderCommission = array_sum(array_column($gender, 'commission_amount'));
        foreach ($gender as $key => $value) {
            $gender[$key]['count_percentage'] = $totalGenderCount > 0
                ? round(((int) $value['count'] / $totalGenderCount) * 100, 2)
                : 0;
            $gender[$key]['commission_percentage'] = $totalGenderCommission > 0
                ? round(((float) $value['commission_amount'] / $totalGenderCommission) * 100, 2)
                : 0;
            $gender[$key]['commission_amount'] = FrontLegacyData::money($value['commission_amount']);
        }

        return [
            'agent_id' => $agentId,
            'ranges' => $ranges,
            'gender' => $gender,
        ];
    }

    /**
     * transferAgentOptions 用于返回当前代理可转账的直属下级代理选项。
     *
     * 参数逻辑说明：
     * - 直属下级代理范围复用 FrontLegacyData::userScopeIds，兼容 agent_descendants 与 user_infos.parent_id 两种来源。
     * - account_type=1 表示只返回代理账号，不返回普通客户。
     * - value/user_id 表示下级代理业务用户 ID，会作为 transfer 的 sub_agent_id。
     *
     * @param Request $request 当前 HTTP 请求对象，用于读取当前代理登录身份。
     * @return JsonResponse 当前代理直属下级代理下拉选项。
     */
    public function transferAgentOptions(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $userLogin->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $userLogin->user_id;
        $directAgentIds = FrontLegacyData::userScopeIds($agentId, false, 1, true);
        $options = UserInfo::with('level')
            ->whereIn('user_id', $directAgentIds)
            ->where('account_type', 1)
            ->orderBy('user_id')
            ->get()
            ->map(function (UserInfo $agent) {
                $level = $agent->level;
                $levelName = $level->name ?? ($agent->level_id ? ('Level ' . $agent->level_id) : '');
                $name = (string) ($agent->user_name ?? '');
                $labelParts = array_filter([
                    (string) $agent->user_id,
                    $name,
                    $levelName,
                ]);

                return [
                    'value' => (int) $agent->user_id,
                    'label' => implode(' / ', $labelParts),
                    'user_id' => (int) $agent->user_id,
                    'user_name' => $name,
                    'agent_level_name' => $levelName,
                ];
            })
            ->values()
            ->all();

        return $this->success($options, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * transfer 用于把当前代理返佣余额转给直属下级代理。
     *
     * 参数逻辑说明：
     * - sub_agent_id 表示接收佣金转账的直属下级代理 ID，必须存在于共享直属代理作用域中。
     * - amount 表示佣金转账金额，必须大于 0 且不能超过当前代理 total_funds。
     * - remark 表示佣金转账备注，会写入 commission_records.manual_reason 和 remarks。
     * - DBCT 表示下级代理入账流水，commission_amount、returned_amount、real_amount 均为正数。
     * - WBCT 表示当前代理出账流水，commission_amount、returned_amount、real_amount 均为负数。
     *
     * @param Request $request 当前 HTTP 请求对象，承载转账目标、金额、备注和当前代理身份。
     * @return JsonResponse 转账成功或失败响应。
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_agent_id' => 'required|integer',
            'amount'       => ['required', 'numeric', 'min:0.01', 'max:9999999999999999.99', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/'],
            'password'     => 'nullable|string|max:128',
            'remark'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $userLogin->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $userLogin->user_id;
        $subAgentId = (int) $request->input('sub_agent_id');
        // 金额统一为两位小数 USD 字符串（0.01 步进），与校验正则和 DECIMAL(18,2) 保持同一精度口径。
        [$amountWhole, $amountFraction] = array_pad(explode('.', (string) $request->input('amount'), 2), 2, '');
        $amount = $amountWhole . '.' . str_pad($amountFraction, 2, '0');

        // sub_agent_id 必须是当前代理直属下级代理，避免代理把佣金转给无授权关系的账号。
        $directAgentIds = FrontLegacyData::userScopeIds($agentId, false, 1, true);
        $isSubAgent = in_array((int) $subAgentId, $directAgentIds, true);
        
        if (!$isSubAgent) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $password = (string) $request->input('password', '');
        if ($password === '') {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $idempotencyKey)) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        try {
            $result = $this->commissionTransferService->createOrRetrieve(
                $agentId,
                $subAgentId,
                $amount,
                $password,
                trim((string) $request->input('remark', '')),
                'front_commission_transfer',
                $idempotencyKey
            );
        } catch (DomainException $exception) {
            return $this->commissionTransferDomainError($exception);
        } catch (Throwable $exception) {
            return $this->error(__('response.server_error'), ResponseCode::SERVER_ERROR);
        }

        $transfer = $result['transfer'];
        $data = [
            'id' => (int) $transfer->id,
            'local_order_no' => (string) $transfer->local_order_no,
            'status' => (string) $transfer->status,
            'current_step' => (string) $transfer->current_step,
            'created' => (bool) $result['created'],
            'last_error_code' => (string) $transfer->last_error_code,
        ];
        if ((string) $transfer->status === 'completed') {
            return $this->success($data, 'response.success');
        }

        return $this->commissionTransferStateError((string) $transfer->status, (string) $transfer->last_error_code, $data);
    }

    /**
     * 把佣金转账业务异常映射为统一业务错误响应。
     *
     * 参数逻辑说明：
     * - exception 表示 CommissionTransferService 抛出的业务异常，getMessage() 返回标准错误码。
     * - transfer_target_not_allowed 表示接收方不在直属下级代理范围，映射为 PERMISSION_DENIED。
     * - transfer_user_not_found 表示转账目标用户资料缺失，映射为 USER_NOT_FOUND。
     * - transfer_not_allowed 表示转账被业务规则拒绝，例如账号状态异常，映射为 OPERATION_NOT_ALLOWED。
     * - 其余未知错误码统一按参数校验失败处理，避免暴露内部业务异常细节。
     *
     * @param DomainException $exception 转账服务抛出的业务异常。
     * @return JsonResponse 映射后的统一错误响应。
     */
    private function commissionTransferDomainError(DomainException $exception): JsonResponse
    {
        $error = $exception->getMessage();
        if ($error === 'transfer_target_not_allowed') {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }
        if ($error === 'transfer_user_not_found') {
            return $this->error(__('response.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }
        if ($error === 'transfer_not_allowed') {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
    }

    /**
     * 把转账进行中或终态的失败状态码映射为统一业务错误响应。
     *
     * 错误码语义：invalid_trade_password 表示交易密码错误，small_transfer_daily_limit 表示当日小额转账超限，
     * status=rejected 表示转账被拒绝；其余状态按 MT4 同步失败处理，响应均携带转账记录详情供前端展示。
     *
     * @param string $status 转账状态。
     * @param string $error 转账错误码。
     * @param array<string, mixed> $data 转账记录详情。
     * @return JsonResponse 映射后的统一错误响应。
     */
    private function commissionTransferStateError(string $status, string $error, array $data): JsonResponse
    {
        if ($error === 'invalid_trade_password') {
            return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED, $data);
        }
        if ($error === 'small_transfer_daily_limit') {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED, $data);
        }
        if ($status === 'rejected') {
            return $this->error(__('response.operation_failed'), ResponseCode::OPERATION_NOT_ALLOWED, $data);
        }

        return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, $data);
    }
}
