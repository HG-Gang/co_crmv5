<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\UserTrade;
use App\Models\UserInfo;
use App\Constants\ResponseCode;
use App\Services\CommissionService;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 前台订单管理控制器。
 *
 * 文件功能：
 * - 处理当前持仓订单、历史平仓订单、旧前台订单搜索入口和订单详情弹层。
 * - 订单数据来自 user_trades 表，并通过 FrontLegacyData 按当前登录用户或代理树过滤可见范围。
 * - 代理账号查看订单时会附带订单链路和返佣拆分明细，普通客户只查看自身订单数据。
 *
 * 安全边界：
 * - 列表与详情查询都先经过 FrontLegacyData::applyAllowedUserFilter() 限定可见范围，orderId/ticket 参数不能绕过该过滤。
 * - 详情弹层（openOrderDetail/closeOrderDetail）查询不到授权范围内的订单时返回 404，不暴露订单是否存在。
 */
class OrderController extends FrontBaseController
{
    /**
     * 返佣计算服务：为代理视角的订单列表/详情补充每笔订单的逐级返佣拆分明细；
     * 客户视角不需要该依赖，但注入是统一的。缺失时代理订单页将无法展示返佣构成。
     *
     * @var CommissionService
     */
    protected $commissionService;

    /**
     * 注入返佣拆分服务。
     *
     * @param CommissionService $commissionService 订单返佣明细计算服务,供订单列表/详情附带返佣拆分。
     */
    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * 返回当前用户可见的持仓订单列表。
     *
     * 参数与逻辑说明：
     * - openOrders 用于返回当前用户可见的持仓订单列表。
     * - orderId 表示旧前台和新版页面提交的订单号筛选字段，对应 user_trades.ticket。
     * - symbol 表示交易品种筛选字段，由 FrontLegacyData::applySymbolFilter() 统一兼容新旧字段。
     * - date_from/date_to 等日期字段会按 open_time 过滤持仓订单开仓时间。
     * - commission_details 表示代理账号查看订单时附带的返佣拆分明细，普通客户返回空数组。
     * 
     * @param Request $request 当前 HTTP 请求对象，承载订单号、交易品种、日期和分页筛选参数。
     * @return JsonResponse 持仓订单分页列表、汇总行和兼容旧前台字段的响应。
     */
    public function openOrders(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        // 查询阶段：先限定当前用户或其代理树可见范围，再叠加日期、品种与订单号筛选，避免订单号参数绕过数据边界。
        $query = UserTrade::query()
            ->with(['user.login', 'user.level'])
            ->open();

        FrontLegacyData::applyAllowedUserFilter($query, $request, (int) $userInfo->user_id);
        FrontLegacyData::applyDateTimeFilter($query, $request, 'open_time');
        FrontLegacyData::applySymbolFilter($query, $request);

        if ($request->filled('orderId')) {
            $query->where('ticket', $request->input('orderId'));
        }

        $totalRow = FrontLegacyData::tradeOrderTotalRow($query);

        $orders = $query->orderBy('open_time', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserTrade $trade) use ($userInfo) {
                $row = FrontLegacyData::tradeAliasRow($trade);
                $row['user_info'] = $this->userDetail($trade->user);
                $row['order_chain'] = $this->orderChain($trade->user, (int) $userInfo->user_id);
                $row['commission_details'] = (int) $userInfo->account_type === 1
                    ? $this->commissionService->orderCommissionDetails($trade, (int) $userInfo->user_id)
                    : [];

                return $row;
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($orders, $totalRow),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }

    /**
     * 兼容旧前台持仓订单搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧前台持仓订单筛选字段。
     * @return JsonResponse 复用 openOrders() 的持仓订单列表响应。
     */
    public function openOrderSearch(Request $request): JsonResponse
    {
        return $this->openOrders($request);
    }

    /**
     * 兼容旧前台第二套持仓订单搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧前台持仓订单筛选字段。
     * @return JsonResponse 复用 openOrders() 的持仓订单列表响应。
     */
    public function openOrder2Search(Request $request): JsonResponse
    {
        return $this->openOrders($request);
    }

    /**
     * 返回当前用户可见的历史平仓订单列表。
     *
     * 参数与逻辑说明：
     * - closedOrders 用于返回当前用户可见的历史平仓订单列表。
     * - orderId 表示旧前台和新版页面提交的订单号筛选字段，对应 user_trades.ticket。
     * - symbol 表示交易品种筛选字段，由 FrontLegacyData::applySymbolFilter() 统一兼容新旧字段。
     * - date_from/date_to 等日期字段会按 close_time 过滤平仓订单时间。
     * - is_coercion 表示旧前台强平筛选字段，Yes 匹配 reason 不等于 0，No 匹配 reason 等于 0。
     * - commission_details 表示代理账号查看订单时附带的返佣拆分明细，普通客户返回空数组。
     * 
     * @param Request $request 当前 HTTP 请求对象，承载订单号、交易品种、强平、日期和分页筛选参数。
     * @return JsonResponse 历史订单分页列表、汇总行和兼容旧前台字段的响应。
     */
    public function closedOrders(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        // 查询阶段：可见范围过滤与持仓订单共用同一套规则，只追加平仓时间与强平筛选。
        $query = UserTrade::query()
            ->with(['user.login', 'user.level'])
            ->closed();

        FrontLegacyData::applyAllowedUserFilter($query, $request, (int) $userInfo->user_id);
        FrontLegacyData::applyDateTimeFilter($query, $request, 'close_time');
        FrontLegacyData::applySymbolFilter($query, $request);

        if ($request->filled('orderId')) {
            $query->where('ticket', $request->input('orderId'));
        }
        if ($request->filled('is_coercion')) {
            if ($request->input('is_coercion') === 'Yes') {
                $query->where('reason', '!=', 0);
            } elseif ($request->input('is_coercion') === 'No') {
                $query->where('reason', 0);
            }
        }

        $totalRow = FrontLegacyData::tradeOrderTotalRow($query);

        $orders = $query->orderBy('close_time', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserTrade $trade) use ($userInfo) {
                $row = FrontLegacyData::tradeAliasRow($trade);
                $row['user_info'] = $this->userDetail($trade->user);
                $row['order_chain'] = $this->orderChain($trade->user, (int) $userInfo->user_id);
                $row['commission_details'] = (int) $userInfo->account_type === 1
                    ? $this->commissionService->orderCommissionDetails($trade, (int) $userInfo->user_id)
                    : [];

                return $row;
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($orders, $totalRow),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }

    /**
     * 兼容旧前台历史订单搜索入口。
     *
     * 逻辑说明：
     * - closeOrderSearch 用于兼容旧前台历史订单搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧前台历史订单筛选字段。
     * @return JsonResponse 复用 closedOrders() 的历史订单列表响应。
     */
    public function closeOrderSearch(Request $request): JsonResponse
    {
        return $this->closedOrders($request);
    }

    /**
     * 兼容旧前台历史订单搜索 V2 入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧前台历史订单筛选字段。
     * @return JsonResponse 复用 closedOrders() 的历史订单列表响应。
     */
    public function closeOrder2Search(Request $request): JsonResponse
    {
        return $this->closedOrders($request);
    }

    /**
     * 兼容旧前台第二套历史订单搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧前台历史订单筛选字段。
     * @return JsonResponse 复用 closedOrders() 的历史订单列表响应。
     */
    public function closeOrderSearchV2(Request $request): JsonResponse
    {
        return $this->closedOrders($request);
    }

    /**
     * 返回旧前台持仓订单详情弹层 HTML。
     *
     * 逻辑说明：
     * - openOrderDetail 用于兼容旧前台持仓订单详情弹层。
     * - orderId 对应 user_trades.ticket，orderType 和 role 仅用于旧详情弹层展示文案。
     * - 详情查询仍会调用 applyAllowedUserFilter()，确保代理或客户只能查看自己可见范围内的订单。
     *
     * @param Request $request 当前 HTTP 请求对象，用于解析登录用户和数据可见范围。
     * @param mixed $orderId 订单号，对应 user_trades.ticket。
     * @param mixed $orderType 旧前台传入的订单类型展示值。
     * @param mixed $role 旧前台传入的角色展示值。
     * @return \Illuminate\Http\Response 持仓订单详情 HTML 响应。
     */
    public function openOrderDetail(Request $request, $orderId, $orderType, $role)
    {
        $userInfo = $this->legacyFrontUserInfo($request);
        if (!$userInfo) {
            abort(403);
        }

        $query = UserTrade::with(['user.login', 'user.level'])
            ->where('ticket', $orderId)
            ->open();
        FrontLegacyData::applyAllowedUserFilter($query, $request, (int) $userInfo->user_id);

        $trade = $query->first();

        if (!$trade) {
            abort(404);
        }

        return response($this->legacyOrderDetailHtml($trade, $userInfo, (string) $orderType, (string) $role, '持仓订单详情'));
    }

    /**
     * 返回旧前台历史订单详情弹层 HTML。
     *
     * 逻辑说明：
     * - closeOrderDetail 用于兼容旧前台历史订单详情弹层。
     * - orderId 对应 user_trades.ticket，且只查询 closed() 历史平仓订单。
     * - 详情查询仍会调用 applyAllowedUserFilter()，确保代理或客户只能查看自己可见范围内的订单。
     *
     * @param Request $request 当前 HTTP 请求对象，用于解析登录用户和数据可见范围。
     * @param mixed $orderId 订单号，对应 user_trades.ticket。
     * @param mixed $orderType 旧前台传入的订单类型展示值。
     * @param mixed $role 旧前台传入的角色展示值。
     * @return \Illuminate\Http\Response 历史订单详情 HTML 响应。
     */
    public function closeOrderDetail(Request $request, $orderId, $orderType, $role)
    {
        $userInfo = $this->legacyFrontUserInfo($request);
        if (!$userInfo) {
            abort(403);
        }

        $query = UserTrade::with(['user.login', 'user.level'])
            ->where('ticket', $orderId)
            ->closed();
        FrontLegacyData::applyAllowedUserFilter($query, $request, (int) $userInfo->user_id);

        $trade = $query->first();

        if (!$trade) {
            abort(404);
        }

        return response($this->legacyOrderDetailHtml($trade, $userInfo, (string) $orderType, (string) $role, '平仓订单详情'));
    }

    /**
     * 组装订单所属用户的展示资料。
     *
     * 逻辑说明：
     * - userDetail 用于组装订单所属用户的展示资料。
     * - account_type=1 表示代理账号，会额外返回代理等级名称和等级排序。
     * - account_type=2 表示普通客户，只返回基础用户别名字段。
     *
     * @param UserInfo|null $user 订单所属用户资料模型，允许为空以兼容历史脏数据。
     * @return array<string, mixed> 兼容旧前台展示字段的用户资料数组。
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
        if ($rank < 1) {
            $rank = 5;
        }
        if ($rank > 5) {
            $rank = 5;
        }

        return array_merge($detail, [
            'agent_level_rank' => $rank,
            'agent_level_name' => $level->name ?? ('Level ' . $rank),
        ]);
    }

    /**
     * 组装当前查看代理可见的订单用户链路。
     *
     * 逻辑说明：
     * - orderChain 用于按 family_tree 返回当前查看代理可见的用户链路。
     * - family_tree 表示用户所属代理链路，当前查看代理只应该看到自己节点之后的下级链路。
     * - viewerAgentId 表示当前登录查看者的代理用户 ID，普通客户或无效代理 ID 返回空链路。
     *
     * @param UserInfo|null $user 订单所属用户资料模型。
     * @param int $viewerAgentId 当前查看者代理 ID。
     * @return array<int, array<string, mixed>> 当前查看者可见的用户链路数组。
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
     * @return array<int, int>
     */
    /**
     * 解析用户 family_tree 为链路用户 ID 数组(根代理在前,本人收尾)。
     *
     * family_tree 为空或无法解析时回退 parentOrderChainIds() 沿 parent_id 向上回溯;
     * 尾部缺少本人 ID 时补上,保证链路始终以本人结束。
     *
     * @param UserInfo $user 订单所属用户。
     * @return array<int, int> 从根到本人的链路用户 ID 数组。
     */
    private function orderChainIds(UserInfo $user): array
    {
        return $this->parentOrderChainIds($user);
    }

    /**
     * @return array<int, int>
     */
    /**
     * 沿 parent_id 逐级向上回溯构建链路 ID 数组(根代理在前,本人收尾)。
     *
     * visited 集合防止脏数据成环导致死循环;任一祖先缺失时停止回溯。
     *
     * @param UserInfo $user 订单所属用户。
     * @return array<int, int> 从根到本人的链路用户 ID 数组。
     */
    private function parentOrderChainIds(UserInfo $user): array
    {
        $ids = [(int) $user->user_id];
        $visited = [(int) $user->user_id => true];
        $parentId = (int) $user->parent_id;
        $depth = 0;

        while ($parentId > 0) {
            if (isset($visited[$parentId]) || $depth >= UserInfo::MAX_HIERARCHY_DEPTH) {
                return [];
            }

            $parent = UserInfo::where('user_id', $parentId)
                ->first(['user_id', 'parent_id', 'account_type']);
            if (!$parent || (int) $parent->account_type !== 1) {
                return [];
            }

            array_unshift($ids, $parentId);
            $visited[$parentId] = true;
            $parentId = (int) $parent->parent_id;
            $depth++;
        }

        return $ids;
    }

    /**
     * 生成旧前台订单详情 HTML。
     *
     * 逻辑说明：
     * - legacyOrderDetailHtml 用于生成旧前台订单详情 HTML。
     * - trade 表示当前订单模型，viewer 表示当前登录查看者。
     * - orderType、role、title 仅用于旧弹层展示，不参与权限判断。
     * - 订单详情中会附带订单链路 HTML 和返佣明细 HTML，便于旧前台弹层一次性展示完整信息。
     *
     * @param UserTrade $trade 订单模型，来自 user_trades 表。
     * @param UserInfo $viewer 当前登录查看者资料模型。
     * @param string $orderType 旧前台订单类型展示值。
     * @param string $role 旧前台角色展示值。
     * @param string $title 详情弹层标题。
     * @return string 旧前台订单详情 HTML。
     */
    private function legacyOrderDetailHtml(UserTrade $trade, UserInfo $viewer, string $orderType, string $role, string $title): string
    {
        $row = FrontLegacyData::tradeAliasRow($trade);
        $user = $this->userDetail($trade->user);

        $html = '<div class="crm-legacy-order">';
        $html .= '<style>.crm-legacy-order{font-family:Arial,"Microsoft YaHei",sans-serif;padding:16px;color:#243042}.crm-legacy-order h3{margin:0 0 12px;font-size:18px}.crm-legacy-order h4{margin:18px 0 8px;font-size:15px}.crm-legacy-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.crm-legacy-item{border:1px solid #e7edf3;border-radius:6px;padding:10px;background:#fff}.crm-legacy-label{font-size:12px;color:#708196}.crm-legacy-value{margin-top:4px;font-size:15px;font-weight:600;color:#1f2a37}.crm-legacy-order table{width:100%;border-collapse:collapse;margin-top:8px}.crm-legacy-order th,.crm-legacy-order td{border:1px solid #e7edf3;padding:8px;text-align:left;font-size:13px}.crm-legacy-order th{background:#f6f8fb;color:#526173}</style>';
        $html .= '<h3>' . e($title) . '</h3>';
        $html .= '<div class="crm-legacy-grid">';
        $html .= $this->legacyDetailItem('订单号', $row['ticket']);
        $html .= $this->legacyDetailItem('用户ID', $row['user_id']);
        $html .= $this->legacyDetailItem('用户名', $user['userName'] ?? '');
        $html .= $this->legacyDetailItem('交易品种', $row['symbol']);
        $html .= $this->legacyDetailItem('方向', $row['cmd_text']);
        $html .= $this->legacyDetailItem('手数', $row['volume_lots']);
        $html .= $this->legacyDetailItem('开仓价', $row['open_price']);
        $html .= $this->legacyDetailItem('平仓价', $row['close_price']);
        $html .= $this->legacyDetailItem('止损', $row['stop_loss']);
        $html .= $this->legacyDetailItem('止盈', $row['take_profit']);
        $html .= $this->legacyDetailItem('佣金', $row['commission']);
        $html .= $this->legacyDetailItem('库存费', $row['swaps']);
        $html .= $this->legacyDetailItem('盈亏', $row['profit']);
        $html .= $this->legacyDetailItem('开仓时间', $row['open_time']);
        $html .= $this->legacyDetailItem('平仓时间', $row['close_time']);
        $html .= $this->legacyDetailItem('备注', $row['comment']);
        $html .= $this->legacyDetailItem('订单类型', $orderType);
        $html .= $this->legacyDetailItem('角色', $role);
        $html .= '</div>';
        $html .= $this->legacyOrderChainHtml($this->orderChain($trade->user, (int) $viewer->user_id));
        $html .= $this->legacyCommissionDetailsHtml($this->commissionService->orderCommissionDetails($trade, (int) $viewer->user_id));
        $html .= '</div>';

        return $html;
    }

    /**
     * 生成旧前台订单链路 HTML。
     *
     * 逻辑说明：
     * - legacyOrderChainHtml 用于生成旧前台订单链路 HTML。
     * - chain 来自 orderChain()，包含当前查看者可见的代理/客户链路节点。
     *
     * @param array<int, array<string, mixed>> $chain 当前查看者可见的用户链路。
     * @return string 旧前台订单链路 HTML。
     */
    private function legacyOrderChainHtml(array $chain): string
    {
        if (!$chain) {
            return '';
        }

        $html = '<h4>当前链路</h4><table><thead><tr><th>用户ID</th><th>用户名</th><th>账户类型</th><th>代理级别</th></tr></thead><tbody>';
        foreach ($chain as $node) {
            $html .= '<tr>';
            $html .= '<td>' . e((string) ($node['user_id'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($node['user_name'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($node['account_type_text'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($node['agent_level_name'] ?? '')) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * 生成旧前台订单返佣明细 HTML。
     *
     * 逻辑说明：
     * - legacyCommissionDetailsHtml 用于生成旧前台订单返佣明细 HTML。
     * - details 来自 CommissionService::orderCommissionDetails()，包含代理 ID、代理名称、返佣金额、返佣比例和结算状态。
     *
     * @param array<int, array<string, mixed>> $details 订单返佣拆分明细。
     * @return string 旧前台订单返佣明细 HTML。
     */
    private function legacyCommissionDetailsHtml(array $details): string
    {
        $html = '<h4>返佣明细</h4><table><thead><tr><th>代理ID</th><th>代理名称</th><th>代理级别</th><th>返佣金额</th><th>返佣比例</th><th>结算状态</th><th>返佣时间</th></tr></thead><tbody>';

        if (!$details) {
            return $html . '<tr><td colspan="7">暂无返佣明细</td></tr></tbody></table>';
        }

        foreach ($details as $detail) {
            $html .= '<tr>';
            $html .= '<td>' . e((string) ($detail['agent_id'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($detail['agent_name'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($detail['agent_level'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($detail['commission_amount'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($detail['rebate_ratio'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($detail['settle_status_text'] ?? '')) . '</td>';
            $html .= '<td>' . e((string) ($detail['rebate_time'] ?? '')) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * 生成旧前台详情字段块。
     *
     * 逻辑说明：
     * - legacyDetailItem 用于生成旧前台详情字段块。
     * - label 表示字段中文标题，value 表示字段展示值，两者都会经过 e() 转义后输出。
     *
     * @param string $label 字段标题。
     * @param mixed $value 字段展示值。
     * @return string 单个详情字段块 HTML。
     */
    private function legacyDetailItem(string $label, $value): string
    {
        return '<div class="crm-legacy-item"><div class="crm-legacy-label">' . e($label) . '</div><div class="crm-legacy-value">' . e((string) $value) . '</div></div>';
    }
}
