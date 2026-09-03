<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 后台大数据统计控制器。
 *
 * 文件功能：
 * - 本控制器负责Dashboard首页的关键业务数据统计。
 * - 统计维度包括：用户总数、交易总量、入金出金总额、盈亏统计等。
 * - 从旧项目BigNumberController迁移核心统计逻辑。
 */
class BigNumberController extends AdminBaseController
{
    /**
     * 获取Dashboard大数据统计。
     *
     * dashboard() 参数说明：
     * - 无需传入参数，直接统计全平台数据。
     * - 如需要按日期筛选，可以扩展start_date和end_date参数。
     *
     * 返回数据说明：
     * - total_users：总用户数（普通客户）
     * - total_agents：总代理数
     * - total_deposit：总入金金额
     * - total_withdraw：总出金金额
     * - total_profit：总盈亏
     * - total_commission：总手续费
     * - total_volume：总交易量（手数）
     * - active_users_today：今日活跃用户数
     * - new_users_today：今日新增用户数
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回Dashboard统计数据。
     */
    public function dashboard(Request $request)
    {
        try {
            // 步骤1：统计总用户数（普通客户）
            $totalUsers = UserInfo::where('account_type', 2)->count();

            // 步骤2：统计总代理数
            $totalAgents = UserInfo::where('account_type', 1)->count();

            // 步骤3：统计总入金金额（从user_trades表）
            $totalDeposit = UserTrade::where('cmd', 6)
                ->where('profit', '>', 0)
                ->whereRaw("comment REGEXP '(Deposit|入金|充值|DBUN|DBAD|-CZ|-RJ)'")
                ->sum('profit');

            // 步骤4：统计总出金金额（从user_trades表）
            $totalWithdraw = UserTrade::where('cmd', 6)
                ->where('profit', '<', 0)
                ->whereRaw("comment REGEXP '(Withdrawal|出金|取款|WBIN|WBAD|-QK)'")
                ->sum('profit');

            // 步骤5：统计总盈亏（只统计已平仓订单）
            $totalProfit = UserTrade::whereIn('cmd', [0, 1, 2, 3, 4, 5])
                ->where('close_time', '!=', '1970-01-01 00:00:00')
                ->where('margin_rate', '<>', 0)
                ->sum('profit');

            // 步骤6：统计总手续费
            $totalCommission = UserTrade::whereIn('cmd', [0, 1, 2, 3, 4, 5])
                ->where('close_time', '!=', '1970-01-01 00:00:00')
                ->where('margin_rate', '<>', 0)
                ->sum('commission');

            // 步骤7：统计总交易量（手数）
            $totalVolume = UserTrade::whereIn('cmd', [0, 1, 2, 3, 4, 5])
                ->where('close_time', '!=', '1970-01-01 00:00:00')
                ->where('margin_rate', '<>', 0)
                ->sum('volume');

            // 步骤8：统计今日活跃用户数（今日有交易记录的用户）
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');

            $activeUsersToday = UserTrade::whereIn('cmd', [0, 1, 2, 3, 4, 5])
                ->whereBetween('open_time', [$todayStart, $todayEnd])
                ->distinct('user_id')
                ->count('user_id');

            // 步骤9：统计今日新增用户数
            $newUsersToday = UserInfo::where('account_type', 2)
                ->whereBetween('created_at', [strtotime($todayStart), strtotime($todayEnd)])
                ->count();

            // 步骤10：统计当前持仓订单数
            $openPositions = UserTrade::whereIn('cmd', [0, 1, 2, 3, 4, 5])
                ->where('close_time', '1970-01-01 00:00:00')
                ->count();

            // 步骤11：返回统计数据
            return $this->success([
                'total_users' => $totalUsers,
                'total_agents' => $totalAgents,
                'total_deposit' => number_format($totalDeposit, 2, '.', ''),
                'total_withdraw' => number_format(abs($totalWithdraw), 2, '.', ''),
                'net_deposit' => number_format($totalDeposit - abs($totalWithdraw), 2, '.', ''),
                'total_profit' => number_format($totalProfit, 2, '.', ''),
                'total_commission' => number_format(abs($totalCommission), 2, '.', ''),
                'total_volume' => $totalVolume / 100, // MT4的VOLUME需要除以100
                'active_users_today' => $activeUsersToday,
                'new_users_today' => $newUsersToday,
                'open_positions' => $openPositions,
            ], __('admin.dashboard_stats_fetched'));

        } catch (\Exception $e) {
            \Log::error('BigNumberController.dashboard error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取按日期分组的统计数据（用于图表展示）。
     *
     * trend() 参数说明：
     * - start_date：开始日期，默认最近30天。
     * - end_date：结束日期，默认今天。
     * - type：统计类型，deposit=入金趋势，withdraw=出金趋势，profit=盈亏趋势，volume=交易量趋势。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回趋势统计数据。
     */
    public function trend(Request $request)
    {
        try {
            $type = $request->input('type', 'deposit');
            $startDate = $request->input('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->input('end_date', date('Y-m-d'));

            $startAt = $startDate . ' 00:00:00';
            $endAt = $endDate . ' 23:59:59';

            $data = [];

            switch ($type) {
                case 'deposit':
                    // 入金趋势
                    $data = DB::table('user_trades')
                        ->selectRaw('DATE(close_time) as date, SUM(profit) as amount')
                        ->where('cmd', 6)
                        ->where('profit', '>', 0)
                        ->whereRaw("comment REGEXP '(Deposit|入金|充值|DBUN|DBAD|-CZ|-RJ)'")
                        ->whereBetween('close_time', [$startAt, $endAt])
                        ->groupBy(DB::raw('DATE(close_time)'))
                        ->orderBy('date')
                        ->get();
                    break;

                case 'withdraw':
                    // 出金趋势
                    $data = DB::table('user_trades')
                        ->selectRaw('DATE(open_time) as date, ABS(SUM(profit)) as amount')
                        ->where('cmd', 6)
                        ->where('profit', '<', 0)
                        ->whereRaw("comment REGEXP '(Withdrawal|出金|取款|WBIN|WBAD|-QK)'")
                        ->whereBetween('open_time', [$startAt, $endAt])
                        ->groupBy(DB::raw('DATE(open_time)'))
                        ->orderBy('date')
                        ->get();
                    break;

                case 'profit':
                    // 盈亏趋势
                    $data = DB::table('user_trades')
                        ->selectRaw('DATE(close_time) as date, SUM(profit) as amount')
                        ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
                        ->where('close_time', '!=', '1970-01-01 00:00:00')
                        ->where('margin_rate', '<>', 0)
                        ->whereBetween('close_time', [$startAt, $endAt])
                        ->groupBy(DB::raw('DATE(close_time)'))
                        ->orderBy('date')
                        ->get();
                    break;

                case 'volume':
                    // 交易量趋势
                    $data = DB::table('user_trades')
                        ->selectRaw('DATE(open_time) as date, SUM(volume) / 100 as amount')
                        ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
                        ->whereBetween('open_time', [$startAt, $endAt])
                        ->groupBy(DB::raw('DATE(open_time)'))
                        ->orderBy('date')
                        ->get();
                    break;
            }

            return $this->success([
                'type' => $type,
                'data' => $data,
            ], __('admin.trend_stats_fetched'));

        } catch (\Exception $e) {
            \Log::error('BigNumberController.trend error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }
}
