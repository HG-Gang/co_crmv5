<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:09
 */

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * 后台系统统计控制器。
 *
 * 功能逻辑说明：
 * - 为后台 Blade + Layui 仪表盘提供用户、代理、客户、待审核入金、待处理出金和今日新增用户统计。
 * - 本控制器只读取统计数据，不修改业务表；接口权限仍由 routes/admin.php 的命名路由和 check.permission:admin 中间件控制。
 * - 统计字段必须保持稳定，前端仪表盘卡片依赖 total_users、total_agents、total_customers 等字段名渲染。
 *
 * 文件功能：
 * - 输出六项只读统计：用户/代理/客户总数、待审核入金、待处理出金、今日新增用户。
 * - 输入：无（暂无筛选参数，保留 $request 供后续按角色或数据范围扩展统计口径）；输出：固定字段名的统计集合。
 *
 * 适用场景：
 * - 后台 Dashboard 首页加载统计卡片；只读统计，不修改任何业务表。
 */
class AdminDashboardController extends AdminBaseController
{
    /**
     * 获取后台系统统计数据。
     *
     * dashboardData() 参数说明：
     * - $request 当前 HTTP 请求对象，当前接口暂无筛选参数，保留该参数用于后续按管理员角色或数据范围扩展统计口径。
     * - total_users 表示用户总数，来源为 user_infos 全表数量。
     * - total_agents 表示代理账号总数，account_type=1 表示代理账号。
     * - total_customers 表示普通客户总数，account_type=2 表示普通客户账号。
     * - pending_deposits 表示待审核入金数量，deposit_records.status=01 表示待审核。
     * - pending_withdrawals 表示待处理出金数量，withdraw_records.status=0 表示待处理。
     * - today_new_users 表示今日新增用户数量，created_at 为 10 位时间戳。
     *
     * @param Request $request 当前 HTTP 请求对象，承载后台管理员上下文和后续可扩展的统计筛选条件。
     * @return \Illuminate\Http\JsonResponse 返回统计字段集合，message 从 admin.system_statistics_fetched 语言包读取。
     */
    public function dashboardData(Request $request)
    {
        // total_users：用户总数，直接统计 user_infos 表记录数。
        $totalUsers = UserInfo::count();

        // total_agents / total_customers：当前新项目用户类型字段为 account_type，1=代理账号，2=普通客户账号。
        $totalAgents = UserInfo::where('account_type', 1)->count();
        $totalCustomers = UserInfo::where('account_type', 2)->count();

        // pending_deposits：入金待审核数量，status=01 对应待审核状态。
        $pendingDeposits = DepositRecord::where('status', '01')->count();

        // pending_withdrawals：出金待处理数量，status=0 对应待处理状态。
        $pendingWithdrawals = WithdrawRecord::where('status', 0)->count();

        // today_new_users：今日新增用户数量；user_infos.created_at 为 10 位 Unix 时间戳。
        $todayStart = Carbon::today()->timestamp;
        $todayNewUsers = UserInfo::where('created_at', '>=', $todayStart)->count();

        $stats = [
            'totalUsers'         => $totalUsers,
            'totalAgents'        => $totalAgents,
            'totalCustomers'     => $totalCustomers,
            'pendingDeposits'    => $pendingDeposits,
            'pendingWithdraws'   => $pendingWithdrawals,
            'todayNew'           => $todayNewUsers,
        ];

        return $this->success($stats, __('admin.system_statistics_fetched'));
    }
}
