<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 前台持仓汇总备用控制器。
 *
 * 文件功能：
 * - 处理当前代理直属节点持仓概览、持仓筛选汇总、下级代理查询和指定用户交易明细。
 * - 当前前台主持仓汇总路由已经迁移到 PositionController，本控制器保留为旧版/备用持仓汇总能力。
 * - 所有查询都以当前登录代理 user_id 为数据边界，点击明细会校验目标用户是否属于当前代理网络。
 *
 * 安全边界：
 * - 用户身份一律来自 user guard 登录态（$request->user('user')），请求体 user_id 只作为查询目标，不能覆盖当前登录代理。
 * - 所有用户集合都通过 FrontLegacyData::userScopeIds() 按代理树生成，targetUserId 不在当前代理网络内时返回 PERMISSION_DENIED。
 */
class PositionSummaryController extends FrontBaseController
{
    /**
     * 返回当前代理直属下级节点的持仓概览。
     *
     * 业务逻辑说明：
     * - index 用于返回当前代理直属下级节点的持仓概览。
     * - userLogin 表示当前 user guard 登录记录，来源于 jwt.auth:user 中间件。
     * - agentId 表示当前代理业务用户 ID，用于读取 agent_descendants.agent_id。
     * - is_direct=1 表示只读取当前代理的直属下级，直属下级可能是代理，也可能是客户。
     * - subDescendantIds 表示当前直属节点自己的全部后代 ID。
     * - allNodeIds 表示本次汇总需要统计的用户 ID 集合，包含直属节点自身和它的后代。
     * - open_positions_count 表示未平仓订单数量，当前方法只统计 UserTrade::open() 范围。
     *
     * @param Request $request HTTP 请求对象，只使用当前登录用户识别代理范围。
     * @return JsonResponse 当前代理直属节点持仓概览数组。
     */
    public function index(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $directChildIds = FrontLegacyData::userScopeIds($agentId, false, null, true);
        $children = UserInfo::whereIn('user_id', $directChildIds)
            ->select('user_id', 'user_name', 'account_type')
            ->orderBy('user_id')
            ->get();

        $summary = [];
        foreach ($children as $child) {
            $allNodeIds = FrontLegacyData::userScopeIds((int) $child->user_id, true);

            $stats = UserTrade::whereIn('user_id', $allNodeIds)
                ->selectRaw('SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as open_positions_count')
                ->open()
                ->first();

            $summary[] = [
                'user_id'      => $child->user_id,
                'user_name'    => $child->user_name,
                'account_type' => $child->account_type,
                'total_volume' => $stats->total_volume ?? 0,
                'total_profit' => $stats->total_profit ?? 0,
                'open_count'   => $stats->open_positions_count ?? 0,
            ];
        }

        return $this->success($summary, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 按日期和交易品种筛选持仓汇总。
     *
     * 业务逻辑说明：
     * - search 用于按日期和交易品种筛选持仓汇总。
     * - date_from 表示平仓时间开始日期，会扩展为当天 00:00:00。
     * - date_to 表示平仓时间结束日期，会扩展为当天 23:59:59。
     * - symbol 表示交易品种筛选值，传入时按 UserTrade.symbol 精确匹配。
     * - allDescendantIds 表示当前代理全部后代加自身 ID 集合，确保汇总范围不越出当前代理网络。
     * - per_page 表示每页返回记录数量，未传时默认 15 条。
     *
     * @param Request $request HTTP 请求对象，承载 date_from、date_to、symbol、per_page 和当前登录用户。
     * @return JsonResponse 按用户维度聚合的持仓汇总分页对象。
     */
    public function search(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $symbol = $request->input('symbol');

        $allDescendantIds = FrontLegacyData::userScopeIds($agentId, true);

        $query = UserTrade::whereIn('user_trades.user_id', $allDescendantIds)
            ->join('user_infos', 'user_trades.user_id', '=', 'user_infos.user_id')
            ->selectRaw('user_trades.user_id, user_infos.user_name, SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as count')
            ->groupBy('user_trades.user_id', 'user_infos.user_name');

        if ($dateFrom) {
            $query->where('close_time', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('close_time', '<=', $dateTo . ' 23:59:59');
        }
        if ($symbol) {
            $query->where('symbol', $symbol);
        }

        $results = $query->paginate($request->input('per_page', 15));

        return $this->success($results, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 查询当前代理名下的下级代理。
     *
     * 业务逻辑说明：
     * - subSearch 用于查询当前代理名下的下级代理。
     * - descendant_type=1 表示只查询代理节点，避免把普通客户混入下级代理列表。
     * - user_name 表示下级代理名称模糊筛选关键字，作用于 user_infos.user_name。
     * - per_page 表示每页返回记录数量，未传时默认 15 条。
     *
     * @param Request $request HTTP 请求对象，承载 user_name、per_page 和当前登录用户。
     * @return JsonResponse 当前代理网络内的下级代理分页对象。
     */
    public function subSearch(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $subAgents = FrontLegacyData::userScopeIds($agentId, false, 1);

        $query = UserInfo::whereIn('user_id', $subAgents);

        if ($request->has('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }

        $results = $query->paginate($request->input('per_page', 15));

        return $this->success($results, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 查询指定用户交易明细。
     *
     * 业务逻辑说明：
     * - clickSearch 用于查询指定用户交易明细。
     * - targetUserId 表示被查看交易明细的用户 ID，来自请求参数 user_id。
     * - isDescendant 表示目标用户是否属于当前代理网络；目标不是当前代理本人且不是其后代时直接返回无权限。
     * - symbol 表示交易品种筛选值，ticket 表示订单号筛选值。
     * - status=1 表示查询已平仓订单，调用 UserTrade::closed()。
     * - status=0 表示查询未平仓订单，调用 UserTrade::open()。
     * - per_page 表示每页返回交易明细数量，未传时默认 15 条。
     *
     * @param Request $request HTTP 请求对象，承载 user_id、symbol、ticket、status、per_page 和当前登录用户。
     * @return JsonResponse 指定用户交易明细分页对象；越权时返回 PERMISSION_DENIED。
     */
    public function clickSearch(Request $request): JsonResponse
    {
        $targetUserId = $request->input('user_id');
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        if (!$targetUserId) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $isDescendant = in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true);
        if (!$isDescendant && (int) $targetUserId !== (int) $agentId) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $query = UserTrade::where('user_id', $targetUserId);

        if ($request->has('symbol')) {
            $query->where('symbol', $request->input('symbol'));
        }
        if ($request->has('ticket')) {
            $query->where('ticket', $request->input('ticket'));
        }
        if ($request->has('status')) {
            if ($request->status == 1) {
                $query->closed();
            } else {
                $query->open();
            }
        }

        $trades = $query->orderBy('close_time', 'desc')
            ->orderBy('open_time', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->success($trades, __('response.query_success'), ResponseCode::SUCCESS);
    }
}
