<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

/**
 * 后台客户资料统计控制器。
 *
 * 文件功能：
 * - 为后台「点击用户名查看用户信息」详情页提供真实 DB 口径的资金与交易统计。
 * - 输出出入金金额、返佣金额、返佣比例、开/关订单数，以及近 7/15/30 天盈亏的按天序列（供 ECharts 渲染）。
 *
 * 数据来源（全部真实表，无任何模拟数据）：
 * - deposit_records.amount，status='02' 表示入金审核通过。
 * - withdraw_records.apply_amount，status=2 表示出金已完成。
 * - commission_records.commission_amount，agent_id 表示获得返佣的代理。
 * - user_infos.comm_rate 表示返佣比例。
 *
 * 返佣比例口径说明（真实库存在历史不一致，必须显式处理，不能猜）：
 * - `user_infos.comm_rate` 的真实列类型是 `int(11)`，旧库迁移把 `commprop` 原样搬过来，
 *   因此现网数据里保存的是百分数（例如 85 表示 85%）。
 * - 另一方面 AgentController::updateCommission 与 UserController 的写入校验是 `min:0|max:1`
 *   （0~1 小数口径），但小数写进 int 列会被截断成 0 或 1。
 * - 本接口因此同时输出两个字段：
 *   `rebate_ratio` 原样返回库里存的值（不做任何猜测的十进制字符串），
 *   `rebate_ratio_percent` 是给页面展示用的百分数：值大于 1 时按"已经是百分数"处理，
 *   否则按 0~1 小数乘 100。两种历史口径都能得到正确的百分比展示。
 * - user_trades 通过 scopeOpen()/scopeClosed() 区分持仓与已平仓订单，profit 为平仓盈亏。
 *
 * 金额精度：
 * - 所有金额一律走 SQL `CAST(... AS DECIMAL(18,2))` 聚合 + PHP BCMath 字符串运算，
 *   全程不经过浮点，返回值为两位小数字符串，避免 double 累加误差。
 *
 * 安全边界：
 * - 目标用户必须通过 AdminDataScopeService::canAccessUser() 校验，普通管理员不能越权查看数据范围外的客户。
 * - 路由挂载 jwt.auth:admin + sso:admin + check.permission:admin，权限由 permissions.api_route 驱动。
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\CommissionRecord;
use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use App\Services\AdminDataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerStatisticsController extends AdminBaseController
{
    /**
     * 入金审核通过状态。
     *
     * @var string
     */
    private const DEPOSIT_STATUS_APPROVED = '02';

    /**
     * 出金已完成状态。
     *
     * @var int
     */
    private const WITHDRAW_STATUS_COMPLETED = 2;

    /**
     * 盈亏统计支持的天数窗口。
     *
     * @var array<int, int>
     */
    private const PROFIT_WINDOWS = [7, 15, 30];

    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于校验管理员是否可以查看目标客户。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 返回指定客户的资金与交易统计。
     *
     * 参数逻辑说明：
     * - user_id 表示目标业务用户 ID，对应 user_infos.user_id；兼容旧字段名 userId。
     *
     * 返回字段说明：
     * - total_deposit / total_withdraw：入金与出金金额合计（两位小数字符串）。
     * - net_flow：入金减出金的净流入。
     * - total_rebate：返佣金额合计。
     * - rebate_ratio：返佣比例原值（0~1 小数字符串）；rebate_ratio_percent 为百分比展示值。
     * - open_order_count / closed_order_count：持仓与已平仓订单数。
     * - profit_7d / profit_15d / profit_30d：近 7/15/30 天平仓盈亏合计。
     * - profit_series：按天盈亏序列，labels/values 长度一致，供图表直接渲染。
     *
     * @param Request $request 当前后台请求对象，承载 user_id 与 admin guard 登录管理员。
     * @return JsonResponse 客户统计响应。
     */
    public function customerStatistics(Request $request): JsonResponse
    {
        $targetUserId = (int) $request->input('user_id', $request->input('userId'));

        $validator = Validator::make(['user_id' => $targetUserId], [
            'user_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $customer = UserInfo::query()->where('user_id', $targetUserId)->first();
        if ($customer === null) {
            return $this->error(__('response.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $admin = $request->user('admin');
        if ($admin !== null && !$this->adminDataScopeService->canAccessUser(
            $admin,
            $targetUserId,
            (int) $customer->account_type === 1 ? 'agent' : 'user'
        )) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $totalDeposit = $this->sumDecimal(
            DepositRecord::query()
                ->where('user_id', $targetUserId)
                ->where('status', self::DEPOSIT_STATUS_APPROVED),
            'amount'
        );
        $totalWithdraw = $this->sumDecimal(
            WithdrawRecord::query()
                ->where('user_id', $targetUserId)
                ->where('status', self::WITHDRAW_STATUS_COMPLETED),
            'apply_amount'
        );
        $totalRebate = $this->sumDecimal(
            CommissionRecord::query()->where('agent_id', $targetUserId),
            'commission_amount'
        );

        $rebateRatio = $this->decimalString((string) ($customer->comm_rate ?? '0'), 4);
        $rebateRatioPercent = $this->rebateRatioPercent($rebateRatio);

        return $this->success(array_merge([
            'user_id' => $targetUserId,
            'user_name' => (string) $customer->user_name,
            'account_type' => (int) $customer->account_type,
            'total_deposit' => $totalDeposit,
            'total_withdraw' => $totalWithdraw,
            'net_flow' => bcsub($totalDeposit, $totalWithdraw, 2),
            'total_rebate' => $totalRebate,
            'rebate_ratio' => $rebateRatio,
            'rebate_ratio_percent' => $rebateRatioPercent,
            'open_order_count' => UserTrade::query()->where('user_id', $targetUserId)->open()->count(),
            'closed_order_count' => UserTrade::query()->where('user_id', $targetUserId)->closed()->count(),
        ], $this->profitWindows($targetUserId)), __('admin.customer_statistics_fetched'));
    }

    /**
     * 把库里存的返佣比例换算成用于展示的百分数。
     *
     * 逻辑说明（对应文件头的历史口径不一致）：
     * - 大于 1：视为库里已经存的是百分数（旧库 commprop 迁移结果，例如 85 -> 85.00%）。
     * - 小于等于 1：视为 0~1 小数口径（新写入校验的语义，例如 0.25 -> 25.00%）。
     * - 全程 BCMath 字符串运算，不经过浮点。
     *
     * @param string $rebateRatio 库里存的比例值（十进制字符串）。
     * @return string 两位小数的百分数字符串。
     */
    private function rebateRatioPercent(string $rebateRatio): string
    {
        if (bccomp($rebateRatio, '1', 4) > 0) {
            return bcadd($rebateRatio, '0', 2);
        }

        return bcmul($rebateRatio, '100', 2);
    }

    /**
     * 计算各天数窗口的盈亏合计与按天序列。
     *
     * 逻辑说明：
     * - 只执行一次 GROUP BY 查询取最长窗口（30 天）的按天盈亏，再在 PHP 内用 BCMath 累加出 7/15/30 天合计，
     *   避免为每个窗口各执行一次 SUM 查询造成重复扫描。
     * - 缺失交易的日期补 0.00，保证 labels 与 values 长度一致、图表 X 轴连续无断点。
     * - 全程使用 DECIMAL(18,2) 聚合与 BCMath 字符串运算，不经过浮点数，避免累加误差。
     *
     * @param int $targetUserId 目标业务用户 ID，对应 user_trades.user_id。
     * @return array<string, mixed> profit_7d/profit_15d/profit_30d 三个窗口合计与 profit_series 按天序列。
     */
    private function profitWindows(int $targetUserId): array
    {
        $maxWindow = max(self::PROFIT_WINDOWS);
        $startDate = now()->subDays($maxWindow - 1)->startOfDay();

        $rows = UserTrade::query()
            ->where('user_id', $targetUserId)
            ->closed()
            ->where('close_time', '>=', $startDate)
            ->selectRaw("DATE(close_time) AS profit_day, CAST(SUM(CAST(profit AS DECIMAL(18,2))) AS DECIMAL(18,2)) AS day_profit")
            ->groupByRaw('profit_day')
            ->pluck('day_profit', 'profit_day');

        $dailyProfit = [];
        foreach ($rows as $day => $profit) {
            $dailyProfit[(string) $day] = $this->decimalString((string) $profit, 2);
        }

        $labels = [];
        $values = [];
        for ($offset = $maxWindow - 1; $offset >= 0; $offset--) {
            $day = now()->subDays($offset)->format('Y-m-d');
            $labels[] = $day;
            $values[] = $dailyProfit[$day] ?? '0.00';
        }

        $result = ['profit_series' => ['labels' => $labels, 'values' => $values]];
        foreach (self::PROFIT_WINDOWS as $window) {
            $result['profit_' . $window . 'd'] = $this->sumTail($values, $window);
        }

        return $result;
    }

    /**
     * 用 BCMath 累加序列末尾指定天数的盈亏。
     *
     * @param array<int, string> $values 按天升序排列的盈亏字符串序列。
     * @param int $window 参与累加的末尾天数。
     * @return string 两位小数字符串。
     */
    private function sumTail(array $values, int $window): string
    {
        $total = '0.00';
        foreach (array_slice($values, -$window) as $value) {
            $total = bcadd($total, $value, 2);
        }

        return $total;
    }

    /**
     * 以 DECIMAL 精度聚合金额列。
     *
     * 逻辑说明：
     * - 先把每行值 CAST 成 DECIMAL(18,2) 再 SUM，让 MySQL 在十进制域内累加，
     *   PHP 侧拿到的是精确十进制字符串，不会引入 double 误差。
     *
     * @param Builder $query 已套用筛选条件的查询对象。
     * @param string $column 需要聚合的金额列名。
     * @return string 两位小数字符串。
     */
    private function sumDecimal(Builder $query, string $column): string
    {
        $value = $query
            ->selectRaw('CAST(COALESCE(SUM(CAST(' . $column . ' AS DECIMAL(18,2))), 0) AS DECIMAL(18,2)) AS aggregate_total')
            ->value('aggregate_total');

        return $this->decimalString((string) ($value ?? '0'), 2);
    }

    /**
     * 规范化十进制字符串。
     *
     * 逻辑说明：
     * - 使用 BCMath 而不是 number_format：避免中间态转成 float。
     * - 非法或空输入统一收敛为 0，保证前端图表拿到的一定是可解析数字字符串。
     *
     * @param string $value 原始值，可能带科学计数法或空串。
     * @param int $scale 目标小数位数。
     * @return string 规范化后的十进制字符串。
     */
    private function decimalString(string $value, int $scale): string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/', $value)) {
            return bcadd('0', '0', $scale);
        }

        return bcadd($value, '0', $scale);
    }
}
