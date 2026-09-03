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
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 后台仪表盘统计控制器。
 *
 * 文件功能：
 * - 为后台 Blade + Layui 首页统计卡片和趋势图提供数据。
 * - index() 返回当前总量类统计，stats() 返回按日期范围聚合的趋势统计。
 * - 当前控制器只读取用户、入金和出金数据，不修改业务表。
 *
 * 适用场景：
 * - 后台首页统计卡片（index）与趋势图（stats）。
 * - 当前统计为全量口径，不按管理员数据范围过滤。
 */
class DashboardController extends AdminBaseController
{
    /**
     * 获取仪表盘概览统计。
     *
     * index() 统计字段说明：
     * - total_users 表示用户总数，来源为 user_infos 全表数量。
     * - total_agents 表示代理账号总数，account_type=1 表示代理账号。
     * - total_customers 表示普通客户总数，account_type=2 表示普通客户账号。
     * - pending_deposits 表示待处理入金数量，deposit_records.status=01 表示待处理。
     * - pending_withdrawals 表示待处理出金数量，withdraw_records.status=0 表示待处理。
     *
     * @return \Illuminate\Http\JsonResponse 返回后台首页统计卡片所需字段。
     */
    public function index()
    {
        // 用户侧统计：total_users 为全量用户数，代理(account_type=1)与客户(account_type=2)口径互不重叠。
        $totalUsers = UserInfo::count();
        $totalAgents = UserInfo::where('account_type', 1)->count();
        $totalCustomers = UserInfo::where('account_type', 2)->count();

        // 待处理业务量：入金 status=01 为待处理，出金 status=0 为待处理。
        $pendingDeposits = DepositRecord::where('status', '01')->count();
        $pendingWithdrawals = WithdrawRecord::where('status', 0)->count();

        $stats = [
            'total_users'         => $totalUsers,
            'total_agents'        => $totalAgents,
            'total_customers'     => $totalCustomers,
            'pending_deposits'    => $pendingDeposits,
            'pending_withdrawals' => $pendingWithdrawals,
        ];

        return $this->success($stats, __('admin.dashboard_stats_fetched'));
    }

    /**
     * 获取指定日期范围内的趋势统计。
     *
     * stats() 参数说明：
     * - start_date 表示统计开始日期，格式为 YYYY-MM-DD，默认取 30 天前。
     * - end_date 表示统计结束日期，格式为 YYYY-MM-DD，默认取今天。
     * - user_stats 表示用户注册趋势，按 user_infos.created_at 日期分组统计数量。
     * - deposit_stats 表示入金金额趋势，按 deposit_records.created_at 日期分组统计 amount 汇总。
     * - withdraw_stats 表示出金金额趋势，按 withdraw_records.created_at 日期分组统计 actual_amount 汇总。
     * - status=02 表示入金已支付，仅统计已支付入金金额。
     * - status=2 表示出金已完成，仅统计已完成出金金额。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 start_date 和 end_date 日期范围参数。
     * @return \Illuminate\Http\JsonResponse 返回趋势图所需用户、入金和出金统计数组。
     */
    public function stats(Request $request)
    {
        try {
            $startDate = $request->input('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->input('end_date', date('Y-m-d'));

            // 三个趋势查询按自然日分组；入金只统计已支付(status=02)、出金只统计已完成(status=2)，避免未完成金额进入趋势。
            $userStats = UserInfo::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('date')
                ->get();

            $depositStats = DepositRecord::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total_amount'))
                ->where('status', '02')
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('date')
                ->get();

            $withdrawStats = WithdrawRecord::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(actual_amount) as total_amount'))
                ->where('status', 2)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('date')
                ->get();

            $data = [
                'user_stats'     => $userStats,
                'deposit_stats'  => $depositStats,
                'withdraw_stats' => $withdrawStats,
            ];

            return $this->success($data, __('admin.detailed_stats_fetched'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }
}
