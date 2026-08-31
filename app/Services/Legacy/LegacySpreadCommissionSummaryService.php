<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 05:00
 */

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Models\GroupConfig;
use App\Models\SpreadConfig;
use App\Models\SymbolPrice;
use App\Models\UserInfo;
use App\Models\UserTrade;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * 旧前台 /user/position/comm_summaryv2 点差返佣结算服务。
 *
 * 文件功能：
 * - 按旧项目的已平仓交易筛选条件扫描待返佣订单，并限制每批最多处理 30 条。
 * - 使用品种点差、代理组点差比例和客户组返佣标志，计算每层代理的点差返佣金额。
 * - 复用父类的 MT4 出账状态机、幂等账本和源交易完成条件，避免重复入金。
 * - 缺失品种或点差配置时缓存隔离该订单 24 小时，保持源交易待结算以等待人工修复。
 *
 * 计算规则：
 * - 点差为 0 时使用代理佣金比例差；点差为 10、20、30 时使用代理组点差比例差。
 * - 点差为 13、23、33、53 时在比例差和手数乘积上额外乘以 0.1。
 * - 客户组 has_commission=0 对应旧 group_id=0，直属代理比例需要先扣除 50。
 */
class LegacySpreadCommissionSummaryService extends LegacyCommissionSummaryService
{
    /** @var string 批量缓存中记录被配置异常隔离的交易单号及其独立过期时间。 */
    private const BLOCKED_TICKETS_CACHE_KEY = 'legacy_spread_commission_blocked_tickets';

    /** @var int 配置异常订单的隔离时长，和旧项目的 1440 分钟保持一致。 */
    private const CONFIGURATION_FAILURE_TTL_SECONDS = 86400;

    /** @var array<int, int> 旧算法支持的品种整数点差。 */
    private const SUPPORTED_SPREADS = [0, 10, 20, 30, 13, 23, 33, 53];

    /** @var array<int, int> 需要使用 0.1 特殊手数倍率的旧点差。 */
    private const SPECIAL_MULTIPLIER_SPREADS = [13, 23, 33, 53];

    /** @var string V2 账本算法类型，用于阻止不同旧算法复用同一条出账快照。 */
    private const CALCULATION_TYPE = 'legacy_spread_comm_summary';

    /**
     * 处理一批旧 V2 点差返佣交易。
     *
     * @param int $limit 本批最多读取的交易数；非法值会被限制为旧逻辑的 30 条上限。
     * @return array<string, int> 返回扫描、成功、可重试、失败、跳过和完成源交易数量。
     */
    public function settleBatch(int $limit = self::BATCH_LIMIT): array
    {
        $this->assertBcmathAvailable();
        $limit = max(1, min($limit, self::BATCH_LIMIT));
        $summary = $this->newSummary();
        $blockedTickets = $this->activeBlockedTickets();

        $trades = UserTrade::query()
            ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
            ->where('close_time', '>', '1970-01-01 00:00:00')
            ->where('volume', '<>', 0)
            ->where('margin_rate', '<>', 0)
            ->where('comment', 'not like', '%delete%')
            ->where('settlement_status', 0)
            ->when($blockedTickets !== [], function ($query) use ($blockedTickets): void {
                $query->whereNotIn('ticket', $blockedTickets);
            })
            ->orderByDesc('close_time')
            ->limit($limit)
            ->get();

        foreach ($trades as $trade) {
            $summary['scanned_count']++;
            $this->settleSpreadTrade((int) $trade->id, $summary);
        }

        return $summary;
    }

    /**
     * 结算一条交易的完整上级代理点差返佣链。
     *
     * @param int $tradeId user_trades 主键，必须仍处于未结算状态。
     * @param array<string, int> $summary 当前批次的统计计数器，会按处理结果原地累计。
     * @return void 配置或外部状态未完成时保留源交易为待结算，不返回伪成功。
     */
    protected function settleSpreadTrade(int $tradeId, array &$summary): void
    {
        $this->assertBcmathAvailable();
        $trade = UserTrade::query()->whereKey($tradeId)->where('settlement_status', 0)->first();
        if (!$trade) {
            return;
        }

        $trader = UserInfo::query()->where('user_id', $trade->user_id)->first();
        if (!$trader) {
            $summary['failed_count']++;

            return;
        }
        $traderGroup = GroupConfig::query()
            ->whereKey($trader->group_id)
            ->where('is_enabled', 1)
            ->first();
        if (!$traderGroup) {
            $this->rememberConfigurationFailure('missing_trader_group', (int) $trade->ticket);
            $summary['failed_count']++;

            return;
        }

        $symbol = SymbolPrice::query()
            ->where('symbol', (string) $trade->symbol)
            ->where('status', 1)
            ->first();
        if (!$symbol) {
            // 缺少品种时不能把点差当成 0，否则会以错误公式发放真实资金。
            $this->rememberConfigurationFailure('missing_symbol', (int) $trade->ticket);
            $summary['failed_count']++;

            return;
        }

        $spread = (int) $symbol->spread;
        if (!in_array($spread, self::SUPPORTED_SPREADS, true)) {
            $this->rememberConfigurationFailure('unsupported_spread', (int) $trade->ticket);
            $summary['failed_count']++;

            return;
        }

        // 没有上级代理代表不存在可收款方，可以安全完成源交易而无需调用 MT4。
        if ((int) $trader->parent_id <= 0) {
            if ($this->completeSourceTradeWithoutPayout((int) $trade->id)) {
                $summary['skipped_count']++;
                $summary['completed_trade_count']++;
            }

            return;
        }

        $plans = $this->buildSpreadPayoutPlans($trade, $trader, $traderGroup, $spread);
        if ($plans === null) {
            $summary['failed_count']++;

            return;
        }

        foreach ($plans as $plan) {
            try {
                $payout = $this->createOrRetrievePayout($plan);
            } catch (Throwable $exception) {
                // 同一业务键的快照冲突表示无法证明资金归属，必须停止并人工核对。
                $summary['failed_count']++;

                return;
            }

            if ($payout->status === 'not_payable') {
                $summary['skipped_count']++;

                continue;
            }

            $result = $this->processPayout((int) $payout->id);
            if ($result === 'settled') {
                $summary['settled_count']++;
            } elseif ($result === 'retryable') {
                $summary['retryable_count']++;
            } elseif (in_array($result, ['rejected', 'unknown'], true)) {
                $summary['failed_count']++;
            }
        }

        if ($this->completeSourceTradeWhenAllPayoutsFinished((int) $trade->id)) {
            $summary['completed_trade_count']++;
        }
    }

    /**
     * 根据旧点差返佣规则构建每层代理的不可变出账快照。
     *
     * @param UserTrade $trade 已平仓且尚未结算的源交易。
     * @param UserInfo $trader 产生交易的客户或下级代理。
     * @param GroupConfig $traderGroup 交易用户组，has_commission 映射旧 group_id 的 1/0 语义。
     * @param int $spread 品种配置的旧整数点差。
     * @return array<int, array<string, int|string>>|null 返回每层代理计划；资料或点差配置异常时返回 null。
     */
    private function buildSpreadPayoutPlans(
        UserTrade $trade,
        UserInfo $trader,
        GroupConfig $traderGroup,
        int $spread
    ): ?array {
        $plans = [];
        $visited = [];
        $agentId = (int) $trader->parent_id;
        $lowerCommissionRate = (string) $trader->comm_rate;
        $lowerSpreadRatio = '0';
        $isDirectAgent = true;
        $customerHasCommission = (int) $traderGroup->has_commission === 1;
        $volumeMultiplier = in_array($spread, self::SPECIAL_MULTIPLIER_SPREADS, true) ? '0.1' : '1';

        while ($agentId > 0) {
            if (isset($visited[$agentId]) || count($visited) >= UserInfo::MAX_HIERARCHY_DEPTH) {
                $this->rememberConfigurationFailure('agent_cycle_or_depth', (int) $trade->ticket);

                return null;
            }
            $visited[$agentId] = true;

            $agent = UserInfo::query()->where('user_id', $agentId)->first();
            if (!$agent || (int) $agent->account_type !== 1) {
                $this->rememberConfigurationFailure('invalid_agent_chain', (int) $trade->ticket);

                return null;
            }
            $agentGroup = GroupConfig::query()
                ->whereKey($agent->group_id)
                ->where('category', 1)
                ->where('is_enabled', 1)
                ->first();
            if (!$agentGroup) {
                $this->rememberConfigurationFailure('missing_agent_group', (int) $trade->ticket);

                return null;
            }

            if ($spread === 0) {
                $rateDifference = $this->commissionRateDifference(
                    (string) $agent->comm_rate,
                    $lowerCommissionRate,
                    $customerHasCommission,
                    $isDirectAgent
                );
            } else {
                $spreadConfig = SpreadConfig::query()
                    ->where('spread', $spread)
                    ->where('agent_group_id', $agent->group_id)
                    ->where('status', 1)
                    ->first();
                if (!$spreadConfig) {
                    $this->rememberConfigurationFailure('missing_spread_config', (int) $trade->ticket);

                    return null;
                }
                $currentSpreadRatio = (string) $spreadConfig->spread_ratio;
                $rateDifference = $this->spreadRatioDifference(
                    $currentSpreadRatio,
                    $lowerSpreadRatio,
                    $customerHasCommission,
                    $isDirectAgent
                );
                $lowerSpreadRatio = $currentSpreadRatio;
            }

            $amount = $this->calculateSpreadCommissionAmount(
                (int) $trade->volume,
                $rateDifference,
                $volumeMultiplier
            );
            $onlineSettlement = (int) $agent->settle_method === 1;
            $status = $onlineSettlement && bccomp($amount, '0.00', 2) > 0 ? 'pending' : 'not_payable';
            $plans[] = [
                'source_trade_id' => (int) $trade->id,
                'source_ticket' => (int) $trade->ticket,
                'trader_user_id' => (int) $trader->user_id,
                'agent_id' => (int) $agent->user_id,
                'parent_id' => (int) $agent->parent_id,
                'volume' => (int) $trade->volume,
                'rate_difference' => $this->normalizeDecimal($rateDifference, 4),
                'group_radix' => $this->normalizeDecimal((string) $agentGroup->radix, 8),
                'amount' => $status === 'pending' ? $amount : '0.00',
                'comment' => \App\Constants\Mt4RemarkCodes::DBCN . (int) $trader->user_id . '-#' . (int) $trade->ticket,
                'calculation_type' => self::CALCULATION_TYPE,
                'spread' => $this->normalizeDecimal((string) $spread, 4),
                'volume_multiplier' => $this->normalizeDecimal($volumeMultiplier, 4),
                'status' => $status,
            ];

            $lowerCommissionRate = (string) $agent->comm_rate;
            $agentId = (int) $agent->parent_id;
            $isDirectAgent = false;
        }

        return $plans;
    }

    /**
     * 计算点差为 0 时使用的旧佣金比例差。
     *
     * @param string $currentRate 当前收款代理的佣金比例。
     * @param string $lowerRate 直属下级的佣金比例。
     * @param bool $customerHasCommission 客户组是否对应旧 group_id=1。
     * @param bool $isDirectAgent 是否为交易用户的直属代理。
     * @return string 返回可用于 BCMath 的比例差；无手续费组直属层会扣除旧规则中的 50。
     */
    private function commissionRateDifference(
        string $currentRate,
        string $lowerRate,
        bool $customerHasCommission,
        bool $isDirectAgent
    ): string {
        if ($isDirectAgent && !$customerHasCommission) {
            return bcsub($currentRate, '50', 8);
        }

        return $isDirectAgent ? bcadd($currentRate, '0', 8) : bcsub($currentRate, $lowerRate, 8);
    }

    /**
     * 计算非零点差使用的代理组点差比例差。
     *
     * @param string $currentRatio 当前代理组的点差比例。
     * @param string $lowerRatio 下级代理组的点差比例。
     * @param bool $customerHasCommission 客户组是否对应旧 group_id=1。
     * @param bool $isDirectAgent 是否为交易用户的直属代理。
     * @return string 返回点差比例差；无手续费组直属层会扣除 50。
     */
    private function spreadRatioDifference(
        string $currentRatio,
        string $lowerRatio,
        bool $customerHasCommission,
        bool $isDirectAgent
    ): string {
        if ($isDirectAgent && !$customerHasCommission) {
            return bcsub($currentRatio, '50', 8);
        }

        return $isDirectAgent ? bcadd($currentRatio, '0', 8) : bcsub($currentRatio, $lowerRatio, 8);
    }

    /**
     * 按旧 V2 点差公式计算单层代理返佣金额。
     *
     * @param int $volume MT4 原始成交量，100 表示 1 手。
     * @param string $rateDifference 当前层与下级的点差或佣金比例差。
     * @param string $volumeMultiplier 标准点差为 1，特殊点差为 0.1。
     * @return string 返回四舍五入到两位小数的正金额；非正差额返回 0.00。
     */
    private function calculateSpreadCommissionAmount(
        int $volume,
        string $rateDifference,
        string $volumeMultiplier
    ): string {
        if ($volume <= 0 || bccomp($rateDifference, '0', 8) <= 0 || bccomp($volumeMultiplier, '0', 8) <= 0) {
            return '0.00';
        }

        $lots = bcdiv((string) $volume, '100', 8);
        $amount = bcmul(bcmul($rateDifference, $lots, 8), $volumeMultiplier, 8);

        return $this->roundMoney($amount);
    }

    /**
     * 返回统一的批处理统计结构。
     *
     * @return array<string, int> 所有计数器初始为 0，供单批处理稳定累加。
     */
    private function newSummary(): array
    {
        return [
            'scanned_count' => 0,
            'settled_count' => 0,
            'retryable_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'completed_trade_count' => 0,
        ];
    }

    /**
     * 读取仍处于 24 小时隔离期内的配置异常交易单号。
     *
     * @return array<int, int> 返回需要从本批扫描中排除的 MT4 交易单号。
     */
    private function activeBlockedTickets(): array
    {
        $now = time();
        $blocked = Cache::get(self::BLOCKED_TICKETS_CACHE_KEY, []);
        if (!is_array($blocked)) {
            return [];
        }

        $active = [];
        foreach ($blocked as $ticket => $expiresAt) {
            if (is_numeric($ticket) && is_numeric($expiresAt) && (int) $expiresAt > $now) {
                $active[(int) $ticket] = (int) $expiresAt;
            }
        }
        if ($active !== $blocked) {
            Cache::put(self::BLOCKED_TICKETS_CACHE_KEY, $active, now()->addDay());
        }

        return array_keys($active);
    }

    /**
     * 缓存配置异常订单，避免定时任务持续重复处理不可安全结算的交易。
     *
     * @param string $reason 缺少品种、点差配置或代理链异常的机器可读原因。
     * @param int $ticket 旧 MT4 交易单号。
     * @return void 失败只会隔离订单，绝不修改源交易结算状态或触发 MT4 入金。
     */
    private function rememberConfigurationFailure(string $reason, int $ticket): void
    {
        $expiresAt = time() + self::CONFIGURATION_FAILURE_TTL_SECONDS;
        Cache::put('legacy_spread_commission_' . $reason . ':' . $ticket, true, now()->addDay());

        $blocked = Cache::get(self::BLOCKED_TICKETS_CACHE_KEY, []);
        $blocked = is_array($blocked) ? $blocked : [];
        $blocked[$ticket] = $expiresAt;
        Cache::put(self::BLOCKED_TICKETS_CACHE_KEY, $blocked, now()->addDay());
    }
}
