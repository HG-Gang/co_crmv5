<?php

namespace App\Http\Controllers\Front;

use App\Models\UserTrade;
use App\Models\UserInfo;
use App\Constants\ResponseCode;
use App\Services\CommissionService;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Front Order Management Controller
 * 前台订单管理控制器
 * 
 * Handles open and closed trading orders for users.
 * 处理用户的持仓订单和已平仓历史订单。
 */
class OrderController extends FrontBaseController
{
    protected $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * List current open orders
     * 获取当前持仓订单列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function openOrders(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = UserInfo::where('user_id', $userLogin->user_id)->first();

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $query = UserTrade::query()
            ->with(['user.login', 'user.level'])
            ->where('close_time', '1970-01-01 00:00:00');

        FrontLegacyData::applyAllowedUserFilter($query, $request, (int) $userInfo->user_id);
        FrontLegacyData::applyDateTimeFilter($query, $request, 'open_time');

        if ($request->filled('symbol')) {
            $query->where('symbol', 'like', '%' . $request->input('symbol') . '%');
        }
        if ($request->filled('orderId')) {
            $query->where('ticket', $request->input('orderId'));
        }

        $totalRow = FrontLegacyData::tradeOrderTotalRow($query);

        $orders = $query->orderBy('open_time', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserTrade $trade) use ($userInfo) {
                $row = FrontLegacyData::tradeAliasRow($trade);
                $row['user_info'] = $this->userDetail($trade->user);
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
     * List historical closed orders
     * 获取已平仓历史订单列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function closedOrders(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = UserInfo::where('user_id', $userLogin->user_id)->first();

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $query = UserTrade::query()
            ->with(['user.login', 'user.level'])
            ->where('close_time', '>', '1970-01-01 00:00:00');

        FrontLegacyData::applyAllowedUserFilter($query, $request, (int) $userInfo->user_id);
        FrontLegacyData::applyDateTimeFilter($query, $request, 'close_time');

        if ($request->filled('symbol')) {
            $query->where('symbol', 'like', '%' . $request->input('symbol') . '%');
        }
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

    private function userDetail(?UserInfo $user): array
    {
        if (!$user) {
            return [];
        }

        $level = $user->relationLoaded('level') ? $user->level : $user->level;
        $rank = (int) ($level->level_code ?? $user->level_id ?? 5);
        if ($rank < 1) {
            $rank = 5;
        }
        if ($rank > 5) {
            $rank = 5;
        }

        return array_merge(FrontLegacyData::userBasicAlias($user), [
            'account_type_text' => (int) $user->account_type === 1 ? __('register.agent') : __('register.customer'),
            'agent_level_rank' => $rank,
            'agent_level_name' => $level->name ?? ('Level ' . $rank),
        ]);
    }
}
