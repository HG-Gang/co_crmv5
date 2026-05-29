<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserTrade;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Trade Management Controller
 * 交易管理控制器
 */
class TradeController extends AdminBaseController
{
    /**
     * List all trades with filters
     * 获取所有交易列表（带过滤）
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = UserTrade::query()->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('ticket')) {
            $query->where('ticket', $request->ticket);
        }

        if ($request->filled('symbol')) {
            $query->where('symbol', $request->symbol);
        }

        $trades = $query->orderByDesc('open_time')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($trades, __('admin.trade_list_fetched'));
    }

    /**
     * List open positions
     * 获取当前持仓列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function openPositions(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $positions = UserTrade::open()
            ->with('user')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success($positions, __('admin.open_positions_fetched'));
    }

    /**
     * List closed positions
     * 获取已平仓记录列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function closedPositions(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $positions = UserTrade::closed()
            ->with('user')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success($positions, __('admin.closed_positions_fetched'));
    }

    /**
     * Position summary statistics
     * 持仓概览统计
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary()
    {
        $summary = UserTrade::open()
            ->select('symbol', DB::raw('SUM(volume) as total_volume'), DB::raw('count(*) as count'))
            ->groupBy('symbol')
            ->get();

        return $this->success($summary, __('admin.trade_summary_fetched'));
    }
}
