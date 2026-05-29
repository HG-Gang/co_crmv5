<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserTrade;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Risk Management Controller
 * 风险管理控制器
 */
class RiskController extends AdminBaseController
{
    /**
     * Get current open positions with risk metrics
     * 获取当前持仓及风险指标
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function positions(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $positions = UserTrade::open()
            ->with('user')
            ->orderByDesc('open_time')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success($positions, __('admin.risk_positions_fetched'));
    }

    /**
     * Get users near margin call threshold
     * 获取接近强平线的用户
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function marginCalls()
    {
        try {
            // Margin call logic: User balance vs required margin
            // 强平预警逻辑：用户余额 vs 所需保证金
            
            $riskUsers = []; // Placeholder for risk analysis results

            return $this->success($riskUsers, __('admin.margin_calls_fetched'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Force close a position
     * 强制平仓
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceClose(Request $request, $id)
    {
        try {
            $trade = UserTrade::find($id);
            if (!$trade || $trade->close_time != '1970-01-01 00:00:00') {
                return $this->error(__('admin.position_not_found_or_closed'), ResponseCode::DATA_NOT_FOUND);
            }

            // Logic to communicate with MT4 server to close position
            // 与 MT4 服务器通信平仓的逻辑

            return $this->success([], __('admin.force_close_signal_sent'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
