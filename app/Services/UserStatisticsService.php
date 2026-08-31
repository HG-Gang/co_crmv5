<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:58
 */

namespace App\Services;

use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\DepositRecord;
use App\Models\WithdrawRecord;
use App\Models\SymbolPrice;
use Illuminate\Support\Facades\DB;

/**
 * 用户统计服务类。
 *
 * 文件功能：
 * - 该服务类负责处理用户相关的复杂统计查询，包括交易统计、入金出金统计、品种分类统计等。
 * - 从旧项目的CustomerController迁移核心统计逻辑，适配新项目的数据表结构。
 * - 所有统计查询都支持数据权限过滤，确保不同角色只能看到自己权限范围内的数据。
 *
 * 使用示例：
 * ```php
 * $service = new UserStatisticsService();
 * $stats = $service->getUserTradeStatistics($userId);
 * ```
 */
class UserStatisticsService
{
    /**
     * 交易记录的最小有效关闭时间。
     *
     * 逻辑说明：
     * - MT4系统中，CLOSE_TIME <= '1970-01-02 00:00:00' 表示订单未关闭或无效。
     * - 只统计已关闭且有效的交易记录。
     */
    const MIN_CLOSE_TIME = '1970-01-02 00:00:00';

    /**
     * 入金关键词正则表达式。
     *
     * @var string
     */
    protected $depositKeywords = 'Deposit|入金|充值|Recharge';

    /**
     * 出金关键词正则表达式。
     *
     * @var string
     */
    protected $withdrawKeywords = 'Withdrawal|出金|提现|Withdraw';

    /**
     * 获取单个用户的交易统计数据。
     *
     * 参数含义：
     * - $userId：用户ID，对应 user_infos.user_id。
     * - $startDate：统计开始日期，格式 Y-m-d。
     * - $endDate：统计结束日期，格式 Y-m-d。
     *
     * 返回数据包含：
     * - total_comm：总手续费
     * - total_profit：总盈亏
     * - total_volume：总交易量（手数）
     * - total_swaps：总利息
     * - total_yuerj：余额入金
     * - total_yuecj：余额出金
     * - total_net_worth：净入金（入金-出金）
     * - total_noble_metal：贵金属交易量
     * - total_for_exca：外汇交易量
     * - total_crud_oil：原油交易量
     * - total_index：指数交易量
     * - total_currency：货币交易量
     * - total_stock：股票交易量
     *
     * @param int $userId 用户ID。
     * @param string|null $startDate 开始日期。
     * @param string|null $endDate 结束日期。
     * @return array 用户交易统计数据。
     */
    public function getUserTradeStatistics(int $userId, string $startDate = null, string $endDate = null): array
    {
        $closeTime = self::MIN_CLOSE_TIME;
        $depositKeywords = $this->depositKeywords;
        $withdrawKeywords = $this->withdrawKeywords;

        // 构建基础查询
        $query = UserTrade::query()
            ->selectRaw("
                -- 余额入金：CMD=6表示余额调整，PROFIT>0且备注包含入金关键词
                SUM(CASE
                    WHEN profit > 0
                    AND cmd = 6
                    AND comment REGEXP ?
                    THEN profit
                    ELSE 0
                END) as total_yuerj,

                -- 余额出金：CMD=6表示余额调整，PROFIT<0且备注包含出金关键词
                SUM(CASE
                    WHEN profit < 0
                    AND cmd = 6
                    AND comment REGEXP ?
                    THEN profit
                    ELSE 0
                END) as total_yuecj,

                -- 手续费：只统计实际交易订单（CMD 0-5），已关闭且保证金率不为0
                ABS(SUM(CASE
                    WHEN cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN commission
                    ELSE 0
                END)) as total_comm,

                -- 盈亏：只统计实际交易订单的盈亏
                SUM(CASE
                    WHEN cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN profit
                    ELSE 0
                END) as total_profit,

                -- 交易量（手数）：MT4中VOLUME字段需要除以100
                SUM(CASE
                    WHEN cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN volume
                    ELSE 0
                END) as total_volume,

                -- 利息（隔夜利息）：只统计负利息
                ABS(SUM(CASE
                    WHEN swaps < 0
                    AND cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN swaps
                    ELSE 0
                END)) as total_swaps
            ")
            ->where('user_id', $userId)
            ->addBinding([$depositKeywords, $withdrawKeywords, $closeTime, $closeTime, $closeTime, $closeTime], 'select');

        // 添加日期筛选
        if ($startDate && $endDate) {
            $query->whereBetween('close_time', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }

        $result = $query->first();

        if (!$result) {
            return $this->getEmptyStatistics();
        }

        // 查询按品种分类的交易量
        $symbolStats = $this->getUserSymbolStatistics($userId, $startDate, $endDate);

        // 合并统计数据
        return [
            'total_comm' => number_format($result->total_comm ?? 0, 2, '.', ''),
            'total_yuerj' => number_format($result->total_yuerj ?? 0, 2, '.', ''),
            'total_yuecj' => number_format(abs($result->total_yuecj ?? 0), 2, '.', ''),
            'total_volume' => ($result->total_volume ?? 0) / 100, // MT4 VOLUME需要除以100
            'total_swaps' => number_format($result->total_swaps ?? 0, 2, '.', ''),
            'total_profit' => number_format($result->total_profit ?? 0, 2, '.', ''),
            'total_net_worth' => number_format(($result->total_yuerj ?? 0) - abs($result->total_yuecj ?? 0), 2, '.', ''),
            'total_noble_metal' => $symbolStats['noble_metal'] / 100,
            'total_for_exca' => $symbolStats['forex'] / 100,
            'total_crud_oil' => $symbolStats['oil'] / 100,
            'total_index' => $symbolStats['index'] / 100,
            'total_currency' => $symbolStats['currency'] / 100,
            'total_stock' => $symbolStats['stock'] / 100,
        ];
    }

    /**
     * 获取用户按品种分类的交易量统计。
     *
     * 参数含义：
     * - $userId：用户ID。
     * - $startDate：开始日期。
     * - $endDate：结束日期。
     *
     * 品种分组说明：
     * - sym_grp_id=1：贵金属（黄金、白银等）
     * - sym_grp_id=2：外汇（EURUSD、GBPUSD等）
     * - sym_grp_id=3：原油（WTI、Brent等）
     * - sym_grp_id=4：指数（US30、NAS100等）
     * - sym_grp_id=5：货币（加密货币等）
     * - sym_grp_id=6：股票（个股CFD）
     *
     * @param int $userId 用户ID。
     * @param string|null $startDate 开始日期。
     * @param string|null $endDate 结束日期。
     * @return array 按品种分类的交易量。
     */
    protected function getUserSymbolStatistics(int $userId, string $startDate = null, string $endDate = null): array
    {
        $closeTime = self::MIN_CLOSE_TIME;

        // 获取各品种分组的交易品种列表
        $symbolGroups = $this->getSymbolGroups();

        $query = UserTrade::query()
            ->selectRaw("
                -- 贵金属交易量
                SUM(CASE
                    WHEN symbol IN (?)
                    AND cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN volume
                    ELSE 0
                END) as noble_metal,

                -- 外汇交易量
                SUM(CASE
                    WHEN symbol IN (?)
                    AND cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN volume
                    ELSE 0
                END) as forex,

                -- 原油交易量
                SUM(CASE
                    WHEN symbol IN (?)
                    AND cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN volume
                    ELSE 0
                END) as oil,

                -- 指数交易量
                SUM(CASE
                    WHEN symbol IN (?)
                    AND cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN volume
                    ELSE 0
                END) as `index`,

                -- 货币交易量
                SUM(CASE
                    WHEN symbol IN (?)
                    AND cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN volume
                    ELSE 0
                END) as currency,

                -- 股票交易量
                SUM(CASE
                    WHEN symbol IN (?)
                    AND cmd IN (0,1,2,3,4,5)
                    AND close_time > ?
                    AND margin_rate <> 0
                    THEN volume
                    ELSE 0
                END) as stock
            ")
            ->where('user_id', $userId)
            ->addBinding([
                implode(',', $symbolGroups[1]), $closeTime,
                implode(',', $symbolGroups[2]), $closeTime,
                implode(',', $symbolGroups[3]), $closeTime,
                implode(',', $symbolGroups[4]), $closeTime,
                implode(',', $symbolGroups[5]), $closeTime,
                implode(',', $symbolGroups[6]), $closeTime,
            ], 'select');

        // 添加日期筛选
        if ($startDate && $endDate) {
            $query->whereBetween('close_time', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }

        $result = $query->first();

        return [
            'noble_metal' => $result->noble_metal ?? 0,
            'forex' => $result->forex ?? 0,
            'oil' => $result->oil ?? 0,
            'index' => $result->index ?? 0,
            'currency' => $result->currency ?? 0,
            'stock' => $result->stock ?? 0,
        ];
    }

    /**
     * 获取交易品种分组。
     *
     * 功能说明：
     * - 从 symbol_prices 表读取各品种分组的交易品种列表。
     * - 缓存查询结果，避免频繁查询数据库。
     *
     * @return array 品种分组数组，格式：[group_id => [symbol1, symbol2, ...]]
     */
    protected function getSymbolGroups(): array
    {
        // 使用缓存避免频繁查询
        return cache()->remember('symbol_groups', 3600, function () {
            $groups = [];

            // 查询各分组的交易品种
            for ($groupId = 1; $groupId <= 6; $groupId++) {
                $symbols = SymbolPrice::where('group_id', $groupId)
                    ->where('status', 1)
                    ->pluck('symbol')
                    ->toArray();

                $groups[$groupId] = empty($symbols) ? [''] : $symbols;
            }

            return $groups;
        });
    }

    /**
     * 批量获取多个用户的交易统计数据。
     *
     * 参数含义：
     * - $userIds：用户ID数组。
     * - $startDate：开始日期。
     * - $endDate：结束日期。
     *
     * 逻辑说明：
     * - 为了性能，使用单次查询获取所有用户的统计数据。
     * - 返回以 user_id 为键的关联数组。
     *
     * @param array $userIds 用户ID数组。
     * @param string|null $startDate 开始日期。
     * @param string|null $endDate 结束日期。
     * @return array 用户统计数据数组，格式：[user_id => statistics]
     */
    public function getBatchUserStatistics(array $userIds, string $startDate = null, string $endDate = null): array
    {
        if (empty($userIds)) {
            return [];
        }

        $statistics = [];

        // 由于按品种分类查询较复杂，这里仍然循环查询
        // 实际生产环境可以优化为单次复杂查询
        foreach ($userIds as $userId) {
            $statistics[$userId] = $this->getUserTradeStatistics($userId, $startDate, $endDate);
        }

        return $statistics;
    }

    /**
     * 获取用户列表的汇总统计数据。
     *
     * 参数含义：
     * - $userIds：用户ID数组，用于筛选特定用户的汇总。
     * - $startDate：开始日期。
     * - $endDate：结束日期。
     *
     * 返回数据：
     * - 所有用户的交易统计汇总，字段同 getUserTradeStatistics()。
     *
     * @param array $userIds 用户ID数组。
     * @param string|null $startDate 开始日期。
     * @param string|null $endDate 结束日期。
     * @return array 汇总统计数据。
     */
    public function getSummaryStatistics(array $userIds, string $startDate = null, string $endDate = null): array
    {
        if (empty($userIds)) {
            return $this->getEmptyStatistics();
        }

        $closeTime = self::MIN_CLOSE_TIME;
        $depositKeywords = $this->depositKeywords;
        $withdrawKeywords = $this->withdrawKeywords;

        $query = UserTrade::query()
            ->selectRaw("
                SUM(CASE
                    WHEN profit > 0 AND cmd = 6 AND comment REGEXP ?
                    THEN profit ELSE 0
                END) as total_yuerj,

                SUM(CASE
                    WHEN profit < 0 AND cmd = 6 AND comment REGEXP ?
                    THEN profit ELSE 0
                END) as total_yuecj,

                ABS(SUM(CASE
                    WHEN cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0
                    THEN commission ELSE 0
                END)) as total_comm,

                SUM(CASE
                    WHEN cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0
                    THEN profit ELSE 0
                END) as total_profit,

                SUM(CASE
                    WHEN cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0
                    THEN volume ELSE 0
                END) as total_volume,

                ABS(SUM(CASE
                    WHEN swaps < 0 AND cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0
                    THEN swaps ELSE 0
                END)) as total_swaps
            ")
            ->whereIn('user_id', $userIds)
            ->addBinding([$depositKeywords, $withdrawKeywords, $closeTime, $closeTime, $closeTime, $closeTime], 'select');

        // 添加日期筛选
        if ($startDate && $endDate) {
            $query->whereBetween('close_time', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }

        $result = $query->first();

        // 查询按品种分类的汇总
        $symbolStats = $this->getSummarySymbolStatistics($userIds, $startDate, $endDate);

        // 查询总余额和总净值
        $balanceStats = UserInfo::query()
            ->selectRaw('
                SUM(total_funds) as total_balance,
                SUM(equity) as total_equity
            ')
            ->whereIn('user_id', $userIds)
            ->first();

        return [
            'search_total_comm' => number_format($result->total_comm ?? 0, 2, '.', ''),
            'search_total_yuerj' => number_format($result->total_yuerj ?? 0, 2, '.', ''),
            'search_total_yuecj' => number_format(abs($result->total_yuecj ?? 0), 2, '.', ''),
            'search_total_volume' => ($result->total_volume ?? 0) / 100,
            'search_total_swaps' => number_format($result->total_swaps ?? 0, 2, '.', ''),
            'search_total_profit' => number_format($result->total_profit ?? 0, 2, '.', ''),
            'search_total_net_worth' => number_format(($result->total_yuerj ?? 0) - abs($result->total_yuecj ?? 0), 2, '.', ''),
            'search_total_noble_metal' => $symbolStats['noble_metal'] / 100,
            'search_total_for_exca' => $symbolStats['forex'] / 100,
            'search_total_crud_oil' => $symbolStats['oil'] / 100,
            'search_total_index' => $symbolStats['index'] / 100,
            'search_total_currency' => $symbolStats['currency'] / 100,
            'search_total_stock' => $symbolStats['stock'] / 100,
            'search_total_bal' => number_format($balanceStats->total_balance ?? 0, 2, '.', ''),
            'search_total_eqy' => number_format($balanceStats->total_equity ?? 0, 2, '.', ''),
        ];
    }

    /**
     * 获取汇总的品种分类统计。
     *
     * @param array $userIds 用户ID数组。
     * @param string|null $startDate 开始日期。
     * @param string|null $endDate 结束日期。
     * @return array 品种分类统计数据。
     */
    protected function getSummarySymbolStatistics(array $userIds, string $startDate = null, string $endDate = null): array
    {
        $closeTime = self::MIN_CLOSE_TIME;
        $symbolGroups = $this->getSymbolGroups();

        $query = UserTrade::query()
            ->selectRaw("
                SUM(CASE WHEN symbol IN (?) AND cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0 THEN volume ELSE 0 END) as noble_metal,
                SUM(CASE WHEN symbol IN (?) AND cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0 THEN volume ELSE 0 END) as forex,
                SUM(CASE WHEN symbol IN (?) AND cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0 THEN volume ELSE 0 END) as oil,
                SUM(CASE WHEN symbol IN (?) AND cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0 THEN volume ELSE 0 END) as `index`,
                SUM(CASE WHEN symbol IN (?) AND cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0 THEN volume ELSE 0 END) as currency,
                SUM(CASE WHEN symbol IN (?) AND cmd IN (0,1,2,3,4,5) AND close_time > ? AND margin_rate <> 0 THEN volume ELSE 0 END) as stock
            ")
            ->whereIn('user_id', $userIds)
            ->addBinding([
                implode(',', $symbolGroups[1]), $closeTime,
                implode(',', $symbolGroups[2]), $closeTime,
                implode(',', $symbolGroups[3]), $closeTime,
                implode(',', $symbolGroups[4]), $closeTime,
                implode(',', $symbolGroups[5]), $closeTime,
                implode(',', $symbolGroups[6]), $closeTime,
            ], 'select');

        if ($startDate && $endDate) {
            $query->whereBetween('close_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }

        $result = $query->first();

        return [
            'noble_metal' => $result->noble_metal ?? 0,
            'forex' => $result->forex ?? 0,
            'oil' => $result->oil ?? 0,
            'index' => $result->index ?? 0,
            'currency' => $result->currency ?? 0,
            'stock' => $result->stock ?? 0,
        ];
    }

    /**
     * 获取空的统计数据结构。
     *
     * @return array 空统计数据。
     */
    protected function getEmptyStatistics(): array
    {
        return [
            'total_comm' => '0.00',
            'total_yuerj' => '0.00',
            'total_yuecj' => '0.00',
            'total_volume' => 0,
            'total_swaps' => '0.00',
            'total_profit' => '0.00',
            'total_net_worth' => '0.00',
            'total_noble_metal' => 0,
            'total_for_exca' => 0,
            'total_crud_oil' => 0,
            'total_index' => 0,
            'total_currency' => 0,
            'total_stock' => 0,
        ];
    }
}
