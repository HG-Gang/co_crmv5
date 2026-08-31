<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

namespace App\Http\Controllers\Front;

use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 前台客户控制器。
 *
 * 文件功能：
 * - 处理当前代理可见客户列表、直属客户筛选、客户名称筛选、客户交易统计和客户汇总统计。
 * - 查询范围来自 FrontLegacyData::userScopeIds，同时合并 agent_descendants 与 user_infos.parent_id，避免代理查看非自己网络内的客户数据。
 * - 当前项目前台主客户列表路由已迁移到 AgentController@customerList，本控制器保留为前台客户统计/兼容能力的独立实现。
 *
 * 数据范围与安全边界：
 * - 客户列表与统计的可见范围全部来自 FrontLegacyData::userScopeIds，请求参数只能在该范围内收窄筛选，不能扩大可见客户集合。
 * - 代理身份由 user guard 提供 user_id 作为作用域根，非代理账号不能通过本控制器读取客户数据。
 */
class CustomerController extends FrontBaseController
{
    /**
     * 返回当前代理可见的客户列表。
     *
     * 业务逻辑说明：
     * - myCustomers 用于返回当前代理可见的客户列表，包含直属客户和间接客户。
     * - userLogin 表示当前 user guard 登录记录，来源于 jwt.auth:user 中间件。
     * - agentId 表示当前代理业务用户 ID，用于计算共享代理树客户范围。
     * - descendant_type=2 表示只查询客户节点，避免把下级代理混入客户列表。
     * - direct_only 表示是否只查询直属客户，值为 1 时只保留共享作用域内的直属客户。
     * - user_name 表示客户姓名模糊筛选关键字，作用于 user_infos.user_name。
     * - per_page 表示每页客户数量，未传时默认 15 条。
     * - trade_stats 表示追加到每个客户节点上的交易统计，便于前台列表直接展示客户交易概览。
     * - total_volume 表示客户交易总手数，total_profit 表示客户交易总盈亏，trade_count 表示客户交易订单数量。
     *
     * @param Request $request HTTP 请求对象，承载 direct_only、user_name、per_page 和当前登录用户。
     * @return JsonResponse 当前代理可见客户分页列表，每条记录可能附带 trade_stats 交易统计。
     */
    public function myCustomers(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;
        $directOnly = $request->input('direct_only') == 1;
        $customerIds = FrontLegacyData::userScopeIds($agentId, false, 2, $directOnly ? true : null);

        // 客户范围来自共享代理树作用域；user_name 等筛选只在此范围内生效，无法越权查看其他代理的客户。
        $query = UserInfo::whereIn('user_id', $customerIds)
            ->where('account_type', 2)
            ->select('user_id', 'user_name', 'account_type', 'parent_id', 'created_at');

        if ($request->has('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }

        $customers = $query->paginate($request->input('per_page', 15));

        // 为每个客户追加真实交易统计，只读取当前客户 user_id 对应的 UserTrade 汇总。
        $customers->through(function ($customer) use ($agentId) {
            $descendant = clone $customer;
            $descendant->setRelations([]);

            $customer->descendant_id = (int) $customer->user_id;
            $customer->descendant_type = 2;
            $customer->is_direct = ((int) $customer->parent_id === (int) $agentId) ? 1 : 0;
            $customer->setRelation('descendant', $descendant);

            $customer->trade_stats = UserTrade::where('user_id', $customer->user_id)
                ->selectRaw('SUM(volume) as total_volume, SUM(profit) as total_profit, COUNT(*) as trade_count')
                ->first();

            return $customer;
        });

        return $this->success($customers, __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 返回当前代理客户统计摘要。
     *
     * 业务逻辑说明：
     * - stats 用于返回当前代理客户统计摘要，统计范围同样来自共享代理树作用域中的客户节点。
     * - descendantIds 表示当前代理名下全部客户 ID 集合，只包含 descendant_type=2 的客户。
     * - totalCustomers 表示客户总数。
     * - activeCount 表示最近一个月有交易的活跃客户数，以 UserTrade.close_time 大于当前时间前一个月作为判断条件。
     * - inactive_customers 表示未活跃客户数，由 totalCustomers - activeCount 计算得到。
     * - total_volume 表示当前代理名下全部客户交易手数合计。
     *
     * @param Request $request HTTP 请求对象，只使用当前登录用户识别代理范围。
     * @return JsonResponse 当前代理客户统计摘要，包含总客户数、活跃客户数、未活跃客户数和总手数。
     */
    public function stats(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $agentId = $userLogin->user_id;

        $descendantIds = FrontLegacyData::userScopeIds($agentId, false, 2);

        $totalCustomers = count($descendantIds);

        $activeCount = UserTrade::whereIn('user_id', $descendantIds)
            ->where('close_time', '>', now()->subMonth())
            ->distinct('user_id')
            ->count();

        $totalVolume = UserTrade::whereIn('user_id', $descendantIds)->sum('volume');

        return $this->success([
            'total_customers'    => $totalCustomers,
            'active_customers'   => $activeCount,
            'inactive_customers' => $totalCustomers - $activeCount,
            'total_volume'       => $totalVolume,
        ], __('response.query_success'), ResponseCode::SUCCESS);
    }
}
