<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:27
 */

namespace App\Services;

use App\Models\Admin;
use App\Models\WithdrawRecord;
use Illuminate\Database\Eloquent\Builder;

/**
 * 统一组合后台出金列表、旧版搜索和导出使用的只读查询。
 *
 * 文件功能：
 *
 * 筛选字段只使用新库 withdraw_records；管理员存在时始终追加数据范围，
 * 避免不同入口各自拼接条件后产生列表、汇总和导出不一致。
 */
class WithdrawRecordQueryService
{
    /**
     * 后台数据范围服务：出金查询（列表/搜索/导出共用）在传入管理员时始终追加 withdraw.user_id 范围条件；
     * 缺失时导出与列表会各自拼条件，口径分裂且会把数据范围外出金记录暴露给越权管理员。
     *
     * @var AdminDataScopeService
     */
    private $dataScope;

    public function __construct(AdminDataScopeService $dataScope)
    {
        $this->dataScope = $dataScope;
    }

    /**
     * 构建出金记录查询。传入的日期必须已由调用方按 Y-m-d 严格校验。
     *
     * @param Admin|null $admin 当前后台管理员；存在时按 withdraw/user_id 应用数据范围。
     * @param array<string, mixed> $filters 现代字段筛选集合。
     */
    public function query(?Admin $admin, array $filters): Builder
    {
        $query = WithdrawRecord::query()->with('user');

        if ($admin) {
            $query = $this->dataScope->apply($query, $admin, 'withdraw', 'user_id');
        }

        if ($this->hasFilter($filters, 'user_id')) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if ($this->hasFilter($filters, 'status')) {
            $query->where('status', (int) $filters['status']);
        }

        if ($this->hasFilter($filters, 'local_order_no')) {
            $query->where('local_order_no', 'like', '%' . trim((string) $filters['local_order_no']) . '%');
        }

        if ($this->hasFilter($filters, 'mt4_ticket')) {
            $query->where('mt4_ticket', trim((string) $filters['mt4_ticket']));
        }

        if ($this->hasFilter($filters, 'start_date')) {
            $query->where('created_at', '>=', strtotime((string) $filters['start_date'] . ' 00:00:00'));
        }

        if ($this->hasFilter($filters, 'end_date')) {
            $query->where('created_at', '<=', strtotime((string) $filters['end_date'] . ' 23:59:59'));
        }

        return $query;
    }

    /**
     * 计算与列表完全相同条件下的金额汇总，不改变原查询对象。
     *
     * 同源同口径契约：本方法产出的每一项都必须等于「对应逐行显示值之和」。
     * - apply_amount / actual_amount / fee / rmb_fee 直接汇总 DECIMAL(18,2) 列，
     *   逐行不再二次换算，故直接 SUM 即已同源；
     * - actual_draw 是唯一带换算的项（金额 × 汇率），逐行会先舍入到分，
     *   因此合计必须 SUM(ROUND(..., 2)) 而非 ROUND(SUM(...))，否则行与合计对不上账。
     * 该契约由 AdminLegacyWithdrawSearchParityClosureModuleTest
     * ::test_legacy_footer_actual_draw_equals_the_sum_of_displayed_rows 锁定。
     *
     * @param Builder $query 与列表完全同条件的查询；本方法克隆后使用，不污染调用方。
     * @return array{apply_amount: string, actual_amount: string, actual_draw: string, fee: string, rmb_fee: string}
     */
    public function summarize(Builder $query): array
    {
        $summaryQuery = clone $query;
        $summaryQuery->setEagerLoads([]);
        $summary = $summaryQuery
            ->selectRaw('COALESCE(SUM(apply_amount), 0) AS total_apply_amount')
            ->selectRaw('COALESCE(SUM(actual_amount), 0) AS total_actual_amount')
            // 必须逐行 ROUND 后再累加，不能先累加乘积再舍入一次。
            // 原因：逐行 actdraw 由 multiplyMoneyByRate() 乘完立刻舍入到分，
            // 合计行若先累加再舍入，只要各行分位以下还有尾数，两者就会分叉——
            // 4 行「1.00 × 1.005」会让页面显示 4 个 1.01 而合计打印 4.02。
            // 项目1 的基准是 sum(act_draw)，act_draw 是已按分存储的列，
            // 即旧口径本身就是「逐行先舍、再求和」，这里复刻同一顺序。
            // 舍入方向一致性：MySQL 对 DECIMAL 的 ROUND 是四舍五入（远离零），
            // formatMoney() 对绝对值 bcadd('0.005') 同样是按量值四舍五入，
            // 因此正负金额两侧口径都对齐，不存在符号方向上的偏差。
            ->selectRaw('COALESCE(SUM(ROUND(actual_amount * exchange_rate, 2)), 0) AS total_actual_draw')
            ->selectRaw('COALESCE(SUM(fee), 0) AS total_fee')
            ->selectRaw('COALESCE(SUM(rmb_fee), 0) AS total_rmb_fee')
            ->first();

        return [
            'apply_amount' => $this->formatMoney($summary ? $summary->total_apply_amount : 0),
            'actual_amount' => $this->formatMoney($summary ? $summary->total_actual_amount : 0),
            'actual_draw' => $this->formatMoney($summary ? $summary->total_actual_draw : 0),
            'fee' => $this->formatMoney($summary ? $summary->total_fee : 0),
            'rmb_fee' => $this->formatMoney($summary ? $summary->total_rmb_fee : 0),
        ];
    }

    /**
     * 将查询结果映射为旧出金导出的字段，避免导出控制器重新拼接查询条件。
     *
     * @return array<int, array{record: WithdrawRecord, username: string, bank_no_info: string, actual_draw: string}>
     */
    public function exportRecords(Builder $query, int $limit = 5000): array
    {
        return $query->orderByDesc('created_at')->limit($limit)->get()->map(function (WithdrawRecord $record): array {
            return [
                'record' => $record,
                'username' => (string) ($record->user_name ?: optional($record->user)->user_name),
                'bank_no_info' => (string) ($record->bank_name ?? '') . (string) ($record->bank_addr ?? ''),
                'actual_draw' => $this->multiplyMoneyByRate(
                    (string) $record->actual_amount,
                    (string) $record->exchange_rate
                ),
            ];
        })->all();
    }

    private function hasFilter(array $filters, string $key): bool
    {
        return array_key_exists($key, $filters)
            && $filters[$key] !== null
            && $filters[$key] !== '';
    }

    /**
     * 把数据库 DECIMAL 或精确计算结果按两位小数四舍五入，全程不经过 float。
     *
     * @param mixed $value 普通十进制字符串或数据库 DECIMAL 值。
     */
    public function formatMoney($value): string
    {
        $decimal = trim((string) $value);
        if (!preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $decimal)) {
            throw new \InvalidArgumentException('Withdrawal amount must be a plain decimal string.');
        }
        if (!function_exists('bcadd') || !function_exists('bccomp')) {
            throw new \LogicException('BCMath is required for exact withdrawal amount calculation.');
        }

        $negative = isset($decimal[0]) && $decimal[0] === '-';
        $absolute = $negative ? substr($decimal, 1) : $decimal;
        $rounded = bcadd($absolute, '0.005', 2);

        return $negative && bccomp($rounded, '0.00', 2) !== 0 ? '-' . $rounded : $rounded;
    }

    /**
     * 精确计算出金金额与八位汇率的乘积，再按金额两位小数四舍五入。
     */
    public function multiplyMoneyByRate(string $amount, string $rate): string
    {
        if (!function_exists('bcmul')) {
            throw new \LogicException('BCMath is required for exact withdrawal amount calculation.');
        }
        if (!preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', trim($amount))
            || !preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', trim($rate))) {
            throw new \InvalidArgumentException('Withdrawal amount and exchange rate must be plain decimals.');
        }

        return $this->formatMoney(bcmul(trim($amount), trim($rate), 10));
    }
}
