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

use App\Contracts\DepositSettlementGateway;
use App\Models\CommissionRebatePayout;
use App\Models\CommissionRecord;
use App\Models\GroupConfig;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * 旧 /user/position/comm_summary 实时返佣结算服务。
 *
 * 文件功能：
 * - 扫描旧项目规则要求的已平仓、非零手数、非零手续费交易。
 * - 沿 user_infos.parent_id 向上遍历代理链，按相邻返佣比例差和代理组基数计算每层金额。
 * - 为每层代理建立唯一返佣出账意图，在 MT4 入金确认后写入 commission_records 账本。
 * - 只有所有上级返佣都已确认或明确无需在线入账时，才将源 user_trades 标记为已结算。
 *
 * 旧字段映射：
 * - user_infos.comm_rate 对应旧 agents/user 的 comm_prop。
 * - group_configs.radix 对应旧 user_groups.user_grup_radix。
 * - group_configs.has_commission 对应旧组别是否参与返佣。
 * - user_infos.settle_method=1 对应旧 settlement_model=1 的在线 MT4 入账。
 *
 * 返回结果：
 * - 返回本批次扫描、成功、可重试、失败、跳过和完成交易数量。
 * - 外部结果不确定时返回 unknown 状态且禁止自动重发，避免重复入账。
 */
class LegacyCommissionSummaryService
{
    /** @var int 单次兼容入口最多扫描的交易数，与旧代码 limit(30) 保持一致。 */
    protected const BATCH_LIMIT = 30;

    /** @var int processing 状态超过该分钟数后视为结果不确定，禁止自动重发。 */
    private const STALE_PROCESSING_MINUTES = 5;

    /** @var DepositSettlementGateway 统一 MT4 入金网关，负责区分成功、未发送、拒绝和未知结果。 */
    private $gateway;

    /**
     * 注入 MT4 入金网关。
     *
     * @param DepositSettlementGateway $gateway 发送旧 MT4 deposit 命令并返回标准化结果的服务。
     */
    public function __construct(DepositSettlementGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * 处理一批旧实时返佣交易。
     *
     * @param int $limit 最大扫描数量；未传或不合法时固定使用旧逻辑的 30 条上限。
     * @return array<string, int> 返回 scanned、settled_count、retryable_count、failed_count、skipped_count、completed_trade_count。
     */
    public function settleBatch(int $limit = self::BATCH_LIMIT): array
    {
        $this->assertBcmathAvailable();
        $limit = max(1, min($limit, self::BATCH_LIMIT));
        $summary = [
            'scanned_count' => 0,
            'settled_count' => 0,
            'retryable_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'completed_trade_count' => 0,
        ];

        $trades = UserTrade::query()
            ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
            ->where('close_time', '>', '1970-01-01 00:00:00')
            ->where('volume', '<>', 0)
            ->where('commission', '<>', 0)
            ->where('settlement_status', 0)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($trades as $trade) {
            $summary['scanned_count']++;
            $this->settleTrade((int) $trade->id, $summary);
        }

        return $summary;
    }

    /**
     * 处理一条源交易的完整上级代理链。
     *
     * @param int $tradeId user_trades 主键，必须是当前仍未结算的源交易。
     * @param array<string, int> $summary 当前批次汇总计数器，会按处理结果原地累加。
     * @return void 交易已完成时更新 settlement_status；任一外部失败时保持源交易未结算。
     */
    private function settleTrade(int $tradeId, array &$summary): void
    {
        $trade = UserTrade::whereKey($tradeId)->where('settlement_status', 0)->first();
        if (!$trade) {
            return;
        }
        $trader = UserInfo::where('user_id', $trade->user_id)->first();
        if (!$trader) {
            $summary['failed_count']++;

            return;
        }
        $traderGroup = GroupConfig::find($trader->group_id);
        if (!$traderGroup) {
            $summary['failed_count']++;

            return;
        }

        // 旧逻辑对非返佣客户组写入 NOFY 已处理记录；新实现直接完成源交易，不创建虚假的 MT4 入金。
        if ((int) $traderGroup->has_commission !== 1 || (int) $trader->parent_id <= 0) {
            if ($this->completeSourceTradeWithoutPayout((int) $trade->id)) {
                $summary['skipped_count']++;
                $summary['completed_trade_count']++;
            }

            return;
        }

        $plans = $this->buildPayoutPlans($trade, $trader);
        if ($plans === null) {
            $summary['failed_count']++;

            return;
        }

        foreach ($plans as $plan) {
            try {
                $payout = $this->createOrRetrievePayout($plan);
            } catch (Throwable $exception) {
                // 金融业务身份快照冲突不能被吞掉或覆盖，保留源交易等待人工核对。
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
     * 根据上级链构建每位代理的返佣意图快照。
     *
     * @param UserTrade $trade 已平仓源交易。
     * @param UserInfo $trader 产生交易的客户或下级代理资料。
     * @return array<int, array<string, int|string>>|null 返回每层代理的计划；资料或代理链异常时返回 null 且不进行外部入账。
     */
    private function buildPayoutPlans(UserTrade $trade, UserInfo $trader): ?array
    {
        $plans = [];
        $visited = [];
        $agentId = (int) $trader->parent_id;
        $lowerRate = (string) $trader->comm_rate;

        while ($agentId > 0) {
            if (isset($visited[$agentId]) || count($visited) >= UserInfo::MAX_HIERARCHY_DEPTH) {
                // 代理链出现环时无法证明金额归属，必须停止并保持源交易待处理。
                return null;
            }
            $visited[$agentId] = true;
            $agent = UserInfo::where('user_id', $agentId)->first();
            if (!$agent || (int) $agent->account_type !== 1) {
                return null;
            }
            $agentGroup = GroupConfig::find($agent->group_id);
            if (!$agentGroup) {
                return null;
            }

            $rateDifference = bcsub((string) $agent->comm_rate, $lowerRate, 8);
            $amount = $this->calculateCommissionAmount((int) $trade->volume, $rateDifference, (string) $agentGroup->radix);
            $onlineSettlement = (int) $agent->settle_method === 1;
            $status = $onlineSettlement && bccomp($amount, '0.00', 2) > 0 ? 'pending' : 'not_payable';
            $plans[] = [
                'source_trade_id' => (int) $trade->id,
                'source_ticket' => (int) $trade->ticket,
                'trader_user_id' => (int) $trader->user_id,
                'agent_id' => (int) $agent->user_id,
                'parent_id' => (int) $agent->parent_id,
                'volume' => (int) $trade->volume,
                'rate_difference' => $rateDifference,
                'group_radix' => $this->normalizeDecimal((string) $agentGroup->radix, 8),
                'amount' => $status === 'pending' ? $amount : '0.00',
                'comment' => \App\Constants\Mt4RemarkCodes::DBCN . (int) $trader->user_id . '-#' . (int) $trade->ticket,
                // 记录计算来源，确保后续点差返佣不能与本手续费返佣的金额快照混用。
                'calculation_type' => 'legacy_comm_summary',
                'spread' => '0.0000',
                'volume_multiplier' => '1.0000',
                'status' => $status,
            ];

            $lowerRate = (string) $agent->comm_rate;
            $agentId = (int) $agent->parent_id;
        }

        return $plans;
    }

    /**
     * 以旧项目公式计算某层代理的返佣金额。
     *
     * 公式：volume / 100 * (当前代理比例 - 直属下级比例) / 100 * 当前代理组基数。
     *
     * @param int $volume MT4 原始成交量，100 表示 1 手。
     * @param string $rateDifference 上级与直属下级的比例差。
     * @param string $radix 收款代理组基数。
     * @return string 返回四舍五入到两位小数的正金额；非正差额返回 0.00。
     */
    private function calculateCommissionAmount(int $volume, string $rateDifference, string $radix): string
    {
        if ($volume <= 0 || bccomp($rateDifference, '0', 8) <= 0 || bccomp($radix, '0', 8) <= 0) {
            return '0.00';
        }
        $lots = bcdiv((string) $volume, '100', 8);
        $rate = bcdiv($rateDifference, '100', 8);
        $amount = bcmul(bcmul($lots, $rate, 8), $radix, 8);

        return $this->roundMoney($amount);
    }

    /**
     * 创建或读取单代理返佣出账意图。
     *
     * @param array<string, int|string> $plan 当前代理返佣的不可变业务快照。
     * @return CommissionRebatePayout 返回唯一的 source_trade_id + agent_id 出账记录。
     *
     * @throws RuntimeException 当同一业务键已存在但金额或归属快照不一致时抛出异常。
     */
    protected function createOrRetrievePayout(array $plan): CommissionRebatePayout
    {
        try {
            return DB::transaction(function () use ($plan): CommissionRebatePayout {
                $payout = CommissionRebatePayout::where('source_trade_id', $plan['source_trade_id'])
                    ->where('agent_id', $plan['agent_id'])
                    ->lockForUpdate()
                    ->first();
                if ($payout) {
                    $this->assertPayoutSnapshot($payout, $plan);

                    return $payout;
                }

                return CommissionRebatePayout::create($plan + [
                    'attempts' => 0,
                    'available_at' => now(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $payout = CommissionRebatePayout::where('source_trade_id', $plan['source_trade_id'])
                ->where('agent_id', $plan['agent_id'])
                ->first();
            if (!$payout) {
                throw $exception;
            }
            $this->assertPayoutSnapshot($payout, $plan);

            return $payout;
        }
    }

    /**
     * 声明一笔待执行返佣并在数据库事务外调用 MT4。
     *
     * @param int $payoutId commission_rebate_payouts 主键。
     * @return string 返回 settled、retryable、rejected、unknown 或 ignored，供批次汇总计数。
     */
    protected function processPayout(int $payoutId): string
    {
        $claim = $this->claimPayout($payoutId);
        if ($claim === null) {
            return 'ignored';
        }

        try {
            $result = $this->gateway->deposit($claim['agent_id'], $claim['amount'], $claim['comment']);
        } catch (Throwable $exception) {
            // 调用层异常不能证明 MT4 未收到命令；冻结为 unknown，避免下一次任务重复给代理入金。
            $this->recordUnknownPayout($claim, 'gateway_exception:' . get_class($exception));

            return 'unknown';
        }
        if ($result->status() === 'settled') {
            try {
                $this->recordSettledPayout($claim, $result);

                return 'settled';
            } catch (Throwable $exception) {
                // 外部已经确认成功而本地落账失败时绝不能重发，只能进入人工核对状态。
                $this->recordUnknownPayout($claim, 'local_commit_after_external_success_failed');

                return 'unknown';
            }
        }
        if ($result->status() === 'retryable_not_sent') {
            $this->recordRetryablePayout($claim, (string) $result->errorCode());

            return 'retryable';
        }
        if ($result->status() === 'rejected') {
            $this->recordTerminalPayout($claim, 'rejected', (string) $result->errorCode());

            return 'rejected';
        }

        $this->recordUnknownPayout($claim, (string) $result->errorCode());

        return 'unknown';
    }

    /**
     * 锁定并声明一笔可执行的返佣出账。
     *
     * @param int $payoutId 返佣出账主键。
     * @return array<string, int|string>|null 返回可安全发送给 MT4 的参数；不可执行或已被其他进程声明时返回 null。
     */
    private function claimPayout(int $payoutId): ?array
    {
        return DB::transaction(function () use ($payoutId): ?array {
            $payout = CommissionRebatePayout::whereKey($payoutId)->lockForUpdate()->first();
            if (!$payout) {
                return null;
            }
            if ($payout->status === 'processing') {
                if ($payout->locked_at === null || $payout->locked_at->lte(now()->subMinutes(self::STALE_PROCESSING_MINUTES))) {
                    $payout->status = 'unknown';
                    $payout->processed_at = now();
                    $payout->locked_at = null;
                    $payout->last_error_code = 'stale_processing_claim';
                    $payout->saveOrFail();
                }

                return null;
            }
            if (!in_array($payout->status, ['pending', 'retryable'], true)) {
                return null;
            }
            if ($payout->available_at !== null && $payout->available_at->isFuture()) {
                return null;
            }

            $payout->status = 'processing';
            $payout->attempts = (int) $payout->attempts + 1;
            $payout->locked_at = now();
            $payout->last_error_code = null;
            $payout->saveOrFail();

            return [
                'id' => (int) $payout->id,
                'attempt' => (int) $payout->attempts,
                'agent_id' => (int) $payout->agent_id,
                'amount' => (string) $payout->amount,
                'comment' => (string) $payout->comment,
            ];
        }, 3);
    }

    /**
     * 记录 MT4 已确认入账的返佣，并在同一事务内写入返佣账本。
     *
     * @param array<string, int|string> $claim 当前处理声明。
     * @param DepositSettlementResult $result MT4 成功结果，必须携带数字票据号。
     * @return void 完成返佣出账状态与 commission_records 账本写入。
     *
     * @throws RuntimeException 当 MT4 成功结果缺少可审计票据号时抛出异常。
     */
    private function recordSettledPayout(array $claim, DepositSettlementResult $result): void
    {
        DB::transaction(function () use ($claim, $result): void {
            $payout = $this->lockedClaimedPayout($claim);
            if (!$payout) {
                return;
            }
            $reference = trim((string) $result->providerReference());
            if ($reference === '' || !ctype_digit($reference)) {
                throw new RuntimeException('legacy_commission_provider_reference_invalid');
            }

            $this->writeCommissionLedger($payout, $reference);
            $payout->status = 'settled';
            $payout->provider_reference = $reference;
            $payout->processed_at = now();
            $payout->locked_at = null;
            $payout->available_at = null;
            $payout->last_error_code = null;
            $payout->saveOrFail();
        }, 3);
    }

    /**
     * 记录 MT4 明确未发送的可重试返佣。
     *
     * @param array<string, int|string> $claim 当前处理声明。
     * @param string $errorCode MT4 返回的明确未发送失败码，例如 connection_failed。
     * @return void 将出账恢复为 retryable，源交易仍保持未结算。
     */
    private function recordRetryablePayout(array $claim, string $errorCode): void
    {
        DB::transaction(function () use ($claim, $errorCode): void {
            $payout = $this->lockedClaimedPayout($claim);
            if (!$payout) {
                return;
            }
            $payout->status = 'retryable';
            $payout->available_at = now()->addSeconds(60 * max(1, min((int) $payout->attempts, 10)));
            $payout->locked_at = null;
            $payout->last_error_code = $errorCode !== '' ? $errorCode : 'connection_failed';
            $payout->saveOrFail();
        }, 3);
    }

    /**
     * 记录 MT4 明确拒绝的终态返佣。
     *
     * @param array<string, int|string> $claim 当前处理声明。
     * @param string $status 固定为 rejected，表示 MT4 明确未入账。
     * @param string $errorCode MT4 拒绝码。
     * @return void 将出账置为 rejected，源交易保持未结算以便后台追踪。
     */
    private function recordTerminalPayout(array $claim, string $status, string $errorCode): void
    {
        DB::transaction(function () use ($claim, $status, $errorCode): void {
            $payout = $this->lockedClaimedPayout($claim);
            if (!$payout) {
                return;
            }
            $payout->status = $status;
            $payout->processed_at = now();
            $payout->available_at = null;
            $payout->locked_at = null;
            $payout->last_error_code = $errorCode !== '' ? $errorCode : 'provider_rejected';
            $payout->saveOrFail();
        }, 3);
    }

    /**
     * 记录结果不确定或外部成功后的本地提交失败。
     *
     * @param array<string, int|string> $claim 当前处理声明。
     * @param string $errorCode 结果不确定原因。
     * @return void 将出账置为 unknown，禁止后续自动重发同一笔 MT4 入金。
     */
    private function recordUnknownPayout(array $claim, string $errorCode): void
    {
        DB::transaction(function () use ($claim, $errorCode): void {
            $payout = $this->lockedClaimedPayout($claim);
            if (!$payout) {
                return;
            }
            $payout->status = 'unknown';
            $payout->processed_at = now();
            $payout->available_at = null;
            $payout->locked_at = null;
            $payout->last_error_code = $errorCode !== '' ? $errorCode : 'provider_result_unknown';
            $payout->saveOrFail();
        }, 3);
    }

    /**
     * 按处理声明重新锁定出账，确认没有被其他并发流程篡改。
     *
     * @param array<string, int|string> $claim 当前处理声明。
     * @return CommissionRebatePayout|null 声明仍有效时返回锁定模型，否则返回 null。
     */
    private function lockedClaimedPayout(array $claim): ?CommissionRebatePayout
    {
        $payout = CommissionRebatePayout::whereKey($claim['id'])->lockForUpdate()->first();
        if (!$payout
            || $payout->status !== 'processing'
            || (int) $payout->attempts !== (int) $claim['attempt']) {
            return null;
        }

        return $payout;
    }

    /**
     * 将已确认的 MT4 返佣同步写入统一返佣账本。
     *
     * @param CommissionRebatePayout $payout 已锁定且即将进入 settled 的出账记录。
     * @param string $reference MT4 入金票据号。
     * @return void 同一出账业务键只会创建一条 data_type=legacy_comm_summary 账本记录。
     */
    private function writeCommissionLedger(CommissionRebatePayout $payout, string $reference): void
    {
        [$ledgerPrefix, $dataType] = $this->ledgerMetadataForPayout($payout);
        $uniqueId = hash('sha256', $ledgerPrefix . ':' . (int) $payout->source_trade_id . ':' . (int) $payout->agent_id);
        $record = CommissionRecord::withTrashed()->where('unique_id', $uniqueId)->lockForUpdate()->first();
        if ($record) {
            if ($record->trashed()
                || (int) $record->agent_id !== (int) $payout->agent_id
                || (int) $record->mt4_order_id !== (int) $reference
                || bccomp((string) $record->commission_amount, (string) $payout->amount, 2) !== 0
                || (string) $record->data_type !== $dataType) {
                throw new RuntimeException('legacy_commission_ledger_identity_conflict');
            }

            return;
        }

        CommissionRecord::create([
            'unique_id' => $uniqueId,
            'agent_id' => (int) $payout->agent_id,
            'parent_id' => (int) $payout->parent_id,
            'agent_volume' => bcdiv((string) $payout->volume, '100', 2),
            'mt4_order_id' => (int) $reference,
            'settle_status' => 2,
            'commission_amount' => (string) $payout->amount,
            'returned_amount' => (string) $payout->amount,
            'real_amount' => (string) $payout->amount,
            'data_type' => $dataType,
            'remarks' => (string) $payout->comment,
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);
    }

    /**
     * 根据返佣算法类型生成账本数据类型与唯一键前缀。
     *
     * @param CommissionRebatePayout $payout 已锁定的返佣出账记录。
     * @return array{0: string, 1: string} 返回唯一键前缀和 commission_records.data_type。
     *
     * @throws RuntimeException 出账算法类型不受支持时抛出异常，禁止以错误账本类型落账。
     */
    private function ledgerMetadataForPayout(CommissionRebatePayout $payout): array
    {
        if ((string) $payout->calculation_type === 'legacy_spread_comm_summary') {
            return ['legacy-spread-comm-summary', 'legacy_spread_comm_summary'];
        }
        if ((string) $payout->calculation_type === 'legacy_comm_summary') {
            return ['legacy-comm-summary', 'legacy_comm_summary'];
        }

        throw new RuntimeException('legacy_commission_calculation_type_invalid');
    }

    /**
     * 当一条交易所有返佣腿都已结算或无需在线结算时，完成源交易。
     *
     * @param int $tradeId 源 user_trades 主键。
     * @return bool 本次调用实际完成源交易时返回 true；仍有未完成返佣或已完成时返回 false。
     */
    protected function completeSourceTradeWhenAllPayoutsFinished(int $tradeId): bool
    {
        return DB::transaction(function () use ($tradeId): bool {
            $trade = UserTrade::whereKey($tradeId)->lockForUpdate()->first();
            if (!$trade || (int) $trade->settlement_status === 1) {
                return false;
            }
            $hasPayout = CommissionRebatePayout::where('source_trade_id', $tradeId)->exists();
            $hasUnfinished = CommissionRebatePayout::where('source_trade_id', $tradeId)
                ->whereNotIn('status', ['settled', 'not_payable'])
                ->exists();
            if (!$hasPayout || $hasUnfinished) {
                return false;
            }

            $trade->settlement_status = 1;
            $trade->settled_at = now();
            $trade->saveOrFail();

            return true;
        }, 3);
    }

    /**
     * 完成无需生成任何上级返佣腿的源交易。
     *
     * @param int $tradeId 源 user_trades 主键。
     * @return bool 本次写入成功时返回 true；已由并发请求完成时返回 false。
     */
    protected function completeSourceTradeWithoutPayout(int $tradeId): bool
    {
        return DB::transaction(function () use ($tradeId): bool {
            $trade = UserTrade::whereKey($tradeId)->lockForUpdate()->first();
            if (!$trade || (int) $trade->settlement_status === 1) {
                return false;
            }
            $trade->settlement_status = 1;
            $trade->settled_at = now();
            $trade->saveOrFail();

            return true;
        }, 3);
    }

    /**
     * 验证重复调用时的返佣业务快照没有被组别或比例变更覆盖。
     *
     * @param CommissionRebatePayout $payout 已存在的出账记录。
     * @param array<string, int|string> $plan 本次按当前资料计算得到的计划。
     * @return void 快照一致时不返回值。
     *
     * @throws RuntimeException 交易归属、金额、备注或状态类型不一致时抛出异常。
     */
    private function assertPayoutSnapshot(CommissionRebatePayout $payout, array $plan): void
    {
        if ((int) $payout->source_ticket !== (int) $plan['source_ticket']
            || (int) $payout->trader_user_id !== (int) $plan['trader_user_id']
            || (int) $payout->parent_id !== (int) $plan['parent_id']
            || (int) $payout->volume !== (int) $plan['volume']
            || bccomp((string) $payout->rate_difference, (string) $plan['rate_difference'], 4) !== 0
            || bccomp((string) $payout->group_radix, (string) $plan['group_radix'], 8) !== 0
            || bccomp((string) $payout->amount, (string) $plan['amount'], 2) !== 0
            || (string) $payout->calculation_type !== (string) $plan['calculation_type']
            || bccomp((string) $payout->spread, (string) $plan['spread'], 4) !== 0
            || bccomp((string) $payout->volume_multiplier, (string) $plan['volume_multiplier'], 4) !== 0
            || (string) $payout->comment !== (string) $plan['comment']) {
            throw new RuntimeException('legacy_commission_payout_identity_conflict');
        }
    }

    /**
     * 将普通十进制字符串规范为指定小数位数。
     *
     * @param string $value 待规范化的组基数或比例值。
     * @param int $scale 目标小数位数。
     * @return string 返回 BCMath 可安全比较和保存的固定小数位字符串。
     */
    protected function normalizeDecimal(string $value, int $scale): string
    {
        return bcadd($value, '0', $scale);
    }

    /**
     * 使用半进位规则把高精度返佣金额固定为两位小数。
     *
     * @param string $value 最高八位小数的正金额。
     * @return string 返回可提交 MT4 和 DECIMAL(18,2) 的金额字符串。
     */
    protected function roundMoney(string $value): string
    {
        $truncated = bcadd($value, '0', 2);
        $remainder = bcsub($value, $truncated, 8);
        if (bccomp($remainder, '0.00500000', 8) >= 0) {
            return bcadd($truncated, '0.01', 2);
        }

        return $truncated;
    }

    /**
     * 确认运行环境具备金融金额计算所需的 BCMath 扩展。
     *
     * @return void 扩展存在时不返回值。
     *
     * @throws LogicException BCMath 缺失时抛出异常，禁止以 float 静默降级计算返佣。
     */
    protected function assertBcmathAvailable(): void
    {
        foreach (['bcadd', 'bcsub', 'bcmul', 'bcdiv', 'bccomp'] as $function) {
            if (!function_exists($function)) {
                throw new LogicException('BCMath is required for legacy commission settlement.');
            }
        }
    }
}
