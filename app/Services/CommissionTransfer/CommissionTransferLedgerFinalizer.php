<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:57
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use App\Models\CommissionRecord;
use App\Models\CommissionTransfer;
use App\Models\CommissionTransferOutbox;
use App\Models\UserInfo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 佣金转账账本终结器。
 *
 * 文件功能：
 * - 转账 Saga 最终步骤：记账并标记为完成。
 * - 更新本地转账记录、出箱记录为 completed，并对来源和目标用户各创建一条佣金记录。
 *
 * 适用场景：
 * - CommissionTransferService.process() 的 finalize 步骤。
 * - CommissionTransferReconciliationService 对账完成后调用。
 *
 * 入参例子：
 * - transfer: CommissionTransfer 实例（当前在 finalize 步骤）。
 * - outbox: CommissionTransferOutbox 实例。
 * - sourceBalanceAfter: '5000.00'（源用户扣款后余额）。
 * - targetBalanceAfter: '2000.00'（目标用户入金后余额）。
 * - withdrawReference: 'TICKET123'。
 * - depositReference: 'TICKET456'。
 *
 * 返回值：
 * - void，成功时修改数据库记录。
 *
 * 异常或失败场景：
 * - RuntimeException：余额快照非法、数据库操作失败。
 */
final class CommissionTransferLedgerFinalizer
{
    /**
     * 余额规范化器：终结记账前把源/目标余额统一为两位小数字符串参与校验与落库；
     * 记账金额的正确性依赖规范化口径一致，缺失时非法余额可能被原样写入账本。
     *
     * @var CommissionTransferBalanceNormalizer
     */
    private $balanceNormalizer;

    /**
     * 构造账本终结器。
     *
     * @param CommissionTransferBalanceNormalizer|null $balanceNormalizer 余额规范化器；为空时自动创建默认实现。
     */
    public function __construct(CommissionTransferBalanceNormalizer $balanceNormalizer = null)
    {
        $this->balanceNormalizer = $balanceNormalizer ?: new CommissionTransferBalanceNormalizer();
    }

    /**
     * 终结已完成转账：记账、更新余额并标记完成。
     *
     * 幂等语义：佣金记录以 hash(sha256, 'commission-transfer:' + identity) 为 unique_id，
     * 重复执行时不会重复入账，只会校验与已有记录一致；余额与状态更新在同一事务内，
     * 任一步失败整体回滚并抛出 RuntimeException（失败关闭）。
     *
     * @param CommissionTransfer $transfer 当前 finalize 步骤的转账记录。
     * @param CommissionTransferOutbox $outbox 对应的 process 出箱记录。
     * @param string $sourceBalanceAfter 源用户扣款后余额（两位小数）。
     * @param string $targetBalanceAfter 目标用户入金后余额（两位小数）。
     * @param string $withdrawReference 出金 ticket。
     * @param string $depositReference 入金 ticket。
     *
     * @return void 成功时在事务内落库。
     *
     * @throws RuntimeException 余额/引用非法、用户缺失或记账冲突时抛出。
     */
    public function finalizeCompleted(
        CommissionTransfer $transfer,
        CommissionTransferOutbox $outbox,
        string $sourceBalanceAfter,
        string $targetBalanceAfter,
        string $withdrawReference,
        string $depositReference
    ): void {
        // 先做纯内存校验：外部传入的余额与 ticket 若不合法，直接失败，不进入任何写路径。
        $sourceBalanceAfter = $this->requiredBalance($sourceBalanceAfter, 'source');
        $targetBalanceAfter = $this->requiredBalance($targetBalanceAfter, 'target');
        $withdrawReference = $this->requiredReference($withdrawReference, 'withdraw');
        $depositReference = $this->requiredReference($depositReference, 'deposit');

        DB::transaction(function () use (
            $transfer,
            $outbox,
            $sourceBalanceAfter,
            $targetBalanceAfter,
            $withdrawReference,
            $depositReference
        ): void {
            // 锁定转账双方用户行，保证并发下余额快照校验与回写一致。
            $users = UserInfo::whereIn('user_id', [$transfer->source_user_id, $transfer->target_user_id])
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');
            $source = $users->get($transfer->source_user_id);
            $target = $users->get($transfer->target_user_id);
            if (!$source || !$target) {
                // 外部资金已成功但本地用户缺失：绝不能伪造成功，转人工核对。
                throw new RuntimeException('commission_transfer_user_missing_after_external_success');
            }

            // 为源/目标各写一条佣金记录（目标入金为 +amount，源扣款为 -amount），
            // 以 unique_id 幂等，防止重放 finalize 造成双记账。
            $createdBy = trim((string) $source->user_name) ?: (string) $transfer->source_user_id;
            $this->ensureCommissionRecords([
                [
                    'identity' => 'DBCT-' . $transfer->id,
                    'agent_id' => (int) $transfer->target_user_id,
                    'parent_id' => (int) $transfer->source_user_id,
                    'amount' => (string) $transfer->amount,
                    'remarks' => $this->transferRemarks(
                        'DBCT-' . $transfer->source_user_id . '-#' . $depositReference,
                        (string) $transfer->remark
                    ),
                    'reason' => (string) $transfer->remark,
                    'created_by' => $createdBy,
                    'provider_reference' => $depositReference,
                ],
                [
                    'identity' => 'WBCT-' . $transfer->id,
                    'agent_id' => (int) $transfer->source_user_id,
                    'parent_id' => (int) $transfer->target_user_id,
                    'amount' => bcsub('0.00', (string) $transfer->amount, 2),
                    'remarks' => $this->transferRemarks(
                        'WBCT-' . $transfer->target_user_id . '-#' . $withdrawReference,
                        (string) $transfer->remark
                    ),
                    'reason' => (string) $transfer->remark,
                    'created_by' => $createdBy,
                    'provider_reference' => $withdrawReference,
                ],
            ]);

            // 以 MT4 快照余额覆盖本地总资金（外部资金流已真实发生，以远端为准），
            // 然后标记转账与出箱为 completed，并清除锁与敏感 payload。
            $source->total_funds = $sourceBalanceAfter;
            $target->total_funds = $targetBalanceAfter;
            $source->saveOrFail();
            $target->saveOrFail();

            $now = now();
            $transfer->status = 'completed';
            $transfer->current_step = 'completed';
            $transfer->withdraw_ticket = $withdrawReference;
            $transfer->deposit_ticket = $depositReference;
            $transfer->source_balance_after = $sourceBalanceAfter;
            $transfer->target_balance_after = $targetBalanceAfter;
            $transfer->processed_at = $now;
            $transfer->locked_at = null;
            $transfer->available_at = null;
            $transfer->payload_ciphertext = null;
            $transfer->last_error_code = null;
            $transfer->last_error_message = null;
            $transfer->provider_reference = $depositReference;
            $transfer->saveOrFail();

            $outbox->status = 'completed';
            $outbox->processed_at = $now;
            $outbox->locked_at = null;
            $outbox->available_at = null;
            $outbox->provider_reference = $depositReference;
            $outbox->last_error_code = null;
            $outbox->saveOrFail();
        }, 3);
    }

    /**
     * 幂等写入佣金记录：已存在的 unique_id 只校验一致性，缺失的才创建。
     *
     * @param array<int, array<string, int|string>> $legs 出入账明细（每条含 identity/agent_id/parent_id/amount 等）。
     * @return void
     * @throws RuntimeException 已有记录与本次入账不一致时抛出（幂等冲突失败关闭）。
     */
    private function ensureCommissionRecords(array $legs): void
    {
        // 先锁定已有记录并按 unique_id 建索引，避免并发重复创建。
        $uniqueIds = array_map(function (array $leg): string {
            return $this->ledgerUniqueId((string) $leg['identity']);
        }, $legs);
        $records = CommissionRecord::withTrashed()
            ->whereIn('unique_id', $uniqueIds)
            ->orderBy('unique_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('unique_id');

        foreach ($legs as $leg) {
            $uniqueId = $this->ledgerUniqueId((string) $leg['identity']);
            $record = $records->get($uniqueId);
            if ($record) {
                $this->assertCommissionRecordMatches($record, $leg);
            }
        }

        foreach ($legs as $leg) {
            $uniqueId = $this->ledgerUniqueId((string) $leg['identity']);
            if ($records->has($uniqueId)) {
                continue;
            }
            $providerReference = (string) $leg['provider_reference'];
            $record = CommissionRecord::firstOrCreate(['unique_id' => $uniqueId], [
                'agent_id' => (int) $leg['agent_id'],
                'parent_id' => (int) $leg['parent_id'],
                'commission_amount' => (string) $leg['amount'],
                'returned_amount' => (string) $leg['amount'],
                'real_amount' => (string) $leg['amount'],
                'mt4_order_id' => ctype_digit($providerReference) ? (int) $providerReference : 0,
                'settle_status' => 2,
                'data_type' => 'transfer',
                'manual_reason' => (string) $leg['reason'],
                'remarks' => (string) $leg['remarks'],
                'created_by' => (string) $leg['created_by'],
                'updated_by' => (string) $leg['created_by'],
            ]);
            $this->assertCommissionRecordMatches($record, $leg);
        }
    }

    /**
     * 校验已有佣金记录与本次入账完全一致；不一致说明账本身份冲突，必须失败关闭。
     *
     * @param CommissionRecord $record 已存在或刚创建的记录。
     * @param array<string, int|string> $leg 本次期望的入账明细。
     * @return void
     * @throws RuntimeException 任一关键字段不一致（含软删除）时抛出。
     */
    private function assertCommissionRecordMatches(CommissionRecord $record, array $leg): void
    {
        $amount = (string) $leg['amount'];
        $providerReference = (string) $leg['provider_reference'];
        $createdBy = (string) $leg['created_by'];
        if ($record->trashed()
            || (int) $record->agent_id !== (int) $leg['agent_id']
            || (int) $record->parent_id !== (int) $leg['parent_id']
            || bccomp((string) $record->commission_amount, $amount, 2) !== 0
            || bccomp((string) $record->returned_amount, $amount, 2) !== 0
            || bccomp((string) $record->real_amount, $amount, 2) !== 0
            || (string) $record->data_type !== 'transfer'
            || (int) $record->settle_status !== 2
            || (string) $record->manual_reason !== (string) $leg['reason']
            || (string) $record->remarks !== (string) $leg['remarks']
            || (string) $record->created_by !== $createdBy
            || (string) $record->updated_by !== $createdBy
            || (int) $record->mt4_order_id !== (ctype_digit($providerReference) ? (int) $providerReference : 0)) {
            throw new RuntimeException('commission_transfer_ledger_identity_conflict');
        }
    }

    /**
     * 生成佣金记录唯一 ID：对身份串做 SHA-256，避免原始身份串中的 ticket 明文入键。
     *
     * @param string $identity 如 'DBCT-<transferId>' 或 'WBCT-<transferId>'。
     * @return string 32 位十六进制摘要。
     */
    private function ledgerUniqueId(string $identity): string
    {
        return hash('sha256', 'commission-transfer:' . $identity);
    }

    /**
     * 拼接转账备注：ticket 引用必填，追加业务备注并截断到 500 字符（对齐库字段上限）。
     *
     * @param string $ticketReference 出金/入金 ticket 引用。
     * @param string $remark 原始业务备注。
     * @return string 拼接后的备注。
     */
    private function transferRemarks(string $ticketReference, string $remark): string
    {
        $remarks = trim($remark) === '' ? $ticketReference : $ticketReference . ';' . trim($remark);

        return mb_substr($remarks, 0, 500, 'UTF-8');
    }

    /**
     * 校验并规范化余额快照，非法时失败关闭。
     *
     * @param string $value 原始余额。
     * @param string $side 余额归属（source/target），用于错误码区分。
     * @return string 规范化后的两位小数字符串。
     * @throws RuntimeException 格式或位数不符时抛出。
     */
    private function requiredBalance(string $value, string $side): string
    {
        $normalized = $this->balanceNormalizer->normalize($value);
        if ($normalized === null) {
            throw new RuntimeException('invalid_' . $side . '_balance_after');
        }

        return $normalized;
    }

    /**
     * 校验外部 ticket 引用非空且不超过 100 字符，非法时失败关闭。
     *
     * @param string $value 原始引用。
     * @param string $command 命令标识（withdraw/deposit），用于错误码区分。
     * @return string 去首尾空格后的引用。
     * @throws RuntimeException 为空或超长时抛出。
     */
    private function requiredReference(string $value, string $command): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > 100) {
            throw new RuntimeException('invalid_' . $command . '_reference');
        }

        return $value;
    }
}
