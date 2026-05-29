<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Statistics Controller
 * 仪表盘统计控制器
 */
class DashboardController extends AdminBaseController
{
    /**
     * Dashboard overview statistics
     * 仪表盘概览统计
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Total users (agents + customers)
        // 总用户数 (代理 + 客户)
        $totalUsers = UserInfo::count();
        
        // Total agents (account_type = 1)
        // 总代理数 (account_type = 1)
        $totalAgents = UserInfo::where('account_type', 1)->count();
        
        // Total customers (account_type = 2)
        // 总客户数 (account_type = 2)
        $totalCustomers = UserInfo::where('account_type', 2)->count();
        
        // Pending deposits (status = 01)
        // 待处理入金 (status = 01)
        $pendingDeposits = DepositRecord::where('status', '01')->count();
        
        // Pending withdrawals (status = 0)
        // 待处理提现 (status = 0)
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
     * Detailed statistics with date range
     * 详细统计（带日期范围过滤）
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        try {
            $startDate = $request->input('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->input('end_date', date('Y-m-d'));

            // User registration stats
            // 用户注册统计
            $userStats = UserInfo::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('date')
                ->get();

            // Deposit amount stats
            // 入金金额统计
            $depositStats = DepositRecord::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total_amount'))
                ->where('status', '02') // Paid
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('date')
                ->get();

            // Withdraw amount stats
            // 提现金额统计
            $withdrawStats = WithdrawRecord::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(actual_amount) as total_amount'))
                ->where('status', 2) // Completed
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
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
