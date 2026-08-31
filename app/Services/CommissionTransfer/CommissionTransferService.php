<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use App\Contracts\CommissionTransferAccountSnapshotGateway;
use App\Contracts\CommissionTransferFundingGateway;
use App\Contracts\TradePasswordGateway;
use App\Models\CommissionTransfer;
use App\Models\CommissionTransferOutbox;
use App\Models\UserInfo;
use App\Support\FrontLegacyData;
use App\Support\Money;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * 佣金转账 Saga 服务。
 *
 * 文件功能：
 * - 编排佣金转账的完整 Saga 流程：验证交易密码 -> 小额限额预留 -> 出金 -> 入金 -> 补偿 -> 余额快照 -> 最终记账。
 * - 通过幂等键 + 出箱模式保证同一笔转账不会被重复执行。
 * - 支持自动重试、手动人工介入两种恢复路径。
 *
 * 安全边界：
 * - 交易密码不落明文：仅以 app key 做 HMAC 摘要存入 payload_hash，密文经 Laravel Crypt 加密后存入 payload_ciphertext，verify 步骤完成后立即置空。
 * - 任何日志、错误码与返回值都不携带密码原文或完整密文。
 * - 外部资金指令结果不确定（unknown）时禁止直接重试，必须转人工对账（fail-closed）。
 * - 出箱行锁 + payload_hash 一致性校验保证并发认领唯一；任一步本地失败时按"外部是否可能已生效"分流为重试或人工。
 *
 * 适用场景：
 * - 用户在前端发起佣金转账到其名下其他账户时调用。
 * - 后台定时任务扫描出箱表驱动自动重试。
 * - 异常转账流转至 manual_reconcile_required 后由管理员人工处理。
 *
 * 入参例子：
 * - sourceUserId: 10001
 * - targetUserId: 10002
 * - amount: '500.00'
 * - password: '123456'
 * - remark: '测试转账'
 * - purpose: 'commission_transfer_test'
 * - idempotencyKey: 'ct_req_a1b2c3'
 *
 * 返回值：
 * - createOrRetrieve(): ['created' => true, 'transfer' => CommissionTransfer] 新创建
 * - ['created' => false, 'transfer' => CommissionTransfer] 幂等命中
 * - process(): 返回当前状态字符串（如 completed / rejected / retryable / manual_reconcile_required）
 *
 * 异常或失败场景：
 * - DomainException：非法用户、密码为空、备注过长、目标不在直推范围内。
 * - RuntimeException：payload 加解密失败、应用密钥缺失。
 * - 外部网关失败时根据步骤自动进入 retryable / manual_reconcile_required / compensated 状态。
 */
final class CommissionTransferService
{
    /**
     * 小额免密预留限额，固定 '500.00'（两位小数金额字符串，与 DECIMAL(18,2) 口径一致）。
     * 金额小于等于该值的转账在密码校验步骤可用预留标记代替真实密码校验以减少 MT4 交互；
     * 提高该值等于扩大免密面，会直接放宽资金安全边界，修改需安全评审。
     *
     * @var string
     */
    private const SMALL_LIMIT = '500.00';

    /**
     * Saga 步骤认领的陈旧阈值（分钟），固定 5。processing 状态超过该时长视为执行方已崩溃，
     * 允许重试流程重新认领；过短会与正常慢请求冲突产生并发重放，过长则延长故障恢复时间。
     *
     * @var int
     */
    private const STALE_MINUTES = 5;

    /**
     * 交易密码校验网关（Saga verify 步骤）：把加密后的密码摘要送到 MT4 侧验证。
     * 交易密码是对资金操作的最后一道用户侧认证，缺失时转账链路无法核验操作人身份，
     * 小额预留路径也依赖它保持同一协议。
     *
     * @var TradePasswordGateway
     */
    private $passwordGateway;

    /**
     * 外部资金网关（Saga withdraw/deposit/compensate 步骤）：负责 MT4 侧真实出金、入金与补偿转账。
     * 返回 unknown（结果不确定）时服务据此转人工对账而非重试；缺失或语义不正确的实现会导致资金指令重复下发。
     *
     * @var CommissionTransferFundingGateway
     */
    private $fundingGateway;

    /**
     * 账户余额快照网关（Saga accountinfo 步骤）：读取 MT4 账户余额用于入金后校验与对账证据。
     * 缺失时最终记账无法闭环——completed 判定依赖快照与预期余额一致。
     *
     * @var CommissionTransferAccountSnapshotGateway
     */
    private $snapshotGateway;

    /**
     * 最终记账器：Saga 到达终态后把余额变化落成本地流水与状态。
     * 未注入时构造函数用默认实现兜底，保证容器与直接实例化两条路径行为一致；
     * 记账器缺失会让远端资金已变动而本地无据可查。
     *
     * @var CommissionTransferLedgerFinalizer
     */
    private $ledgerFinalizer;

    /**
     * 构造佣金转账 Saga 服务。
     *
     * @param TradePasswordGateway $passwordGateway 交易密码校验网关（verify 步骤）。
     * @param CommissionTransferFundingGateway $fundingGateway 外部资金出入金网关（withdraw/deposit/compensate 步骤）。
     * @param CommissionTransferAccountSnapshotGateway $snapshotGateway 账户余额快照网关（accountinfo 步骤）。
     * @param CommissionTransferLedgerFinalizer|null $ledgerFinalizer 最终记账器；未注入时使用默认实现。
     */
    public function __construct(
        TradePasswordGateway $passwordGateway,
        CommissionTransferFundingGateway $fundingGateway,
        CommissionTransferAccountSnapshotGateway $snapshotGateway,
        CommissionTransferLedgerFinalizer $ledgerFinalizer = null
    ) {
        $this->passwordGateway = $passwordGateway;
        $this->fundingGateway = $fundingGateway;
        $this->snapshotGateway = $snapshotGateway;
        $this->ledgerFinalizer = $ledgerFinalizer ?: new CommissionTransferLedgerFinalizer();
    }

    /**
     * 创建或检索佣金转账记录。
     *
     * 校验源用户和目标用户的合法性（源用户非只读、目标用户在直推范围内），
     * 通过幂等键查找已有记录，不存在则创建新记录和出箱并启动 Saga。
     *
     * @param int $sourceUserId 转出方用户 ID。
     * @param int $targetUserId 接收方用户 ID。
     * @param string $amount 转账金额，两位小数字符串，如 '100.00'。
     * @param string $password 交易密码。
     * @param string $remark 备注，最长 500 字符。
     * @param string $purpose 请求用途标识，小写字母开头，最长 40 字符。
     * @param string $idempotencyKey 幂等键，用于防止重复创建。
     *
     * @return array{created: bool, transfer: CommissionTransfer}
     *
     * @throws DomainException 用户非法、金额超限、目标不在直推范围内时抛出。
     */
    public function createOrRetrieve(
        int $sourceUserId,
        int $targetUserId,
        string $amount,
        string $password,
        string $remark,
        string $purpose,
        string $idempotencyKey
    ): array {
        $amount = Money::fromDecimalString($amount, '0.01', '9999999999999999.99')->toDecimalString();
        $password = trim($password);
        $remark = trim($remark);
        $purpose = trim($purpose);
        $idempotencyKey = trim($idempotencyKey);
        if ($sourceUserId <= 0 || $targetUserId <= 0 || $sourceUserId === $targetUserId) {
            throw new DomainException('invalid_transfer_users');
        }
        if ($password === '') {
            throw new DomainException('password_required');
        }
        if (strlen($remark) > 500) {
            throw new DomainException('remark_too_long');
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $purpose)) {
            throw new DomainException('invalid_request_purpose');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $idempotencyKey)) {
            throw new DomainException('invalid_idempotency_key');
        }

        $source = UserInfo::where('user_id', $sourceUserId)->first();
        $target = UserInfo::where('user_id', $targetUserId)->first();
        if (!$source || !$target) {
            throw new DomainException('transfer_user_not_found');
        }
        if ((int) $source->account_type !== 1
            || (int) $source->is_mt4_readonly === 1
            || (int) $source->is_withdrawal_allowed !== 0) {
            throw new DomainException('transfer_not_allowed');
        }
        $directIds = FrontLegacyData::userScopeIds($sourceUserId, false, null, true);
        if (!in_array($targetUserId, $directIds, true)) {
            throw new DomainException('transfer_target_not_allowed');
        }

        $payloadHash = $this->payloadHash(
            $sourceUserId,
            $targetUserId,
            $amount,
            $password,
            $remark,
            $purpose
        );
        $existing = $this->findExisting($sourceUserId, $purpose, $idempotencyKey);
        if ($existing) {
            $this->assertSamePayload($existing, $payloadHash);
            if ($existing->status === 'retryable') {
                $this->process((int) $existing->id);
            }

            return ['created' => false, 'transfer' => $existing->fresh()];
        }

        try {
            $transfer = DB::transaction(function () use (
                $sourceUserId,
                $targetUserId,
                $amount,
                $password,
                $remark,
                $purpose,
                $idempotencyKey,
                $payloadHash
            ): CommissionTransfer {
                $existing = CommissionTransfer::where('source_user_id', $sourceUserId)
                    ->where('request_purpose', $purpose)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    $this->assertSamePayload($existing, $payloadHash);

                    return $existing;
                }

                $transfer = CommissionTransfer::create([
                    'local_order_no' => 'CT' . date('YmdHis') . Str::upper(Str::random(8)),
                    'source_user_id' => $sourceUserId,
                    'target_user_id' => $targetUserId,
                    'request_purpose' => $purpose,
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'payload_ciphertext' => $this->encryptPassword($password),
                    'amount' => $amount,
                    'remark' => $remark,
                    'status' => 'pending',
                    'current_step' => 'verify',
                    'reservation_status' => bccomp($amount, self::SMALL_LIMIT, 2) < 0 ? 'pending' : 'not_required',
                    'attempts' => 0,
                    'available_at' => now(),
                ]);
                CommissionTransferOutbox::create([
                    'commission_transfer_id' => $transfer->id,
                    'event_type' => 'process',
                    'status' => 'pending',
                    'attempts' => 0,
                    'payload_hash' => $payloadHash,
                    'available_at' => now(),
                ]);

                return $transfer;
            }, 3);
        } catch (QueryException $exception) {
            $transfer = $this->findExisting($sourceUserId, $purpose, $idempotencyKey);
            if (!$transfer) {
                throw $exception;
            }
            $this->assertSamePayload($transfer, $payloadHash);

            return ['created' => false, 'transfer' => $transfer];
        }

        $this->process((int) $transfer->id);

        return ['created' => true, 'transfer' => $transfer->fresh()];
    }

    /**
     * 执行佣金转账 Saga 流程。
     *
     * 出箱扫描器或 createOrRetrieve 调用，按当前步骤执行对应操作：
     * verify -> limit -> withdraw -> deposit -> (compensate) -> accountinfo -> finalize。
     * 根据网关返回状态决定记录为 retryable / terminal / manual_reconcile_required。
     *
     * 失败关闭语义：
     * - retryable_not_sent：请求未送达，可安全重试。
     * - unknown：外部结果不确定，禁止重试，转人工对账。
     * - 本地异常且 moneyMayHaveMoved：外部资金可能已生效，转人工而不是拒绝。
     *
     * @param int $transferId 转账记录 ID。
     *
     * @return string 当前转账状态（completed / rejected / compensated / retryable / manual_reconcile_required / missing）。
     */
    public function process(int $transferId): string
    {
        // 先原子认领：行锁下确认出箱可执行并置为 processing，失败返回当前状态。
        $claim = $this->claim($transferId);
        if ($claim === null) {
            return $this->currentStatus($transferId);
        }

        // 进入 deposit 之后资金必然已发生变动：此后任何本地失败都不能直接拒绝，必须转人工。
        $moneyMayHaveMoved = in_array($claim['step'], ['deposit', 'accountinfo', 'finalize', 'compensate'], true);
        try {
            while (true) {
                switch ($claim['step']) {
                    // 阶段 1：verify —— 校验交易密码；通过后清空密码密文并进入限额预留。
                    case 'verify':
                        $result = $this->passwordGateway->verify($claim['source_user_id'], $claim['password']);
                        // 密码已送达网关，立即从内存清空，防止后续步骤与异常链中残留明文。
                        $claim['password'] = '';
                        if ($result->status() === 'verified') {
                            if (!$this->transition($claim, 'limit', ['payload_ciphertext' => null])) {
                                return $this->currentStatus($transferId);
                            }
                            $claim['step'] = 'limit';
                            continue 2;
                        }
                        if ($result->status() === 'retryable_not_sent') {
                            return $this->recordRetryable($claim, $result->errorCode() ?: 'connection_failed');
                        }

                        return $this->recordTerminal(
                            $claim,
                            'rejected',
                            $result->status() === 'rejected' ? 'invalid_trade_password' : 'password_verification_unknown'
                        );

                    // 阶段 2：limit —— 小额转账的每日唯一预留，避免同用户同日多笔小额绕过限额。
                    case 'limit':
                        if (!$this->reserveSmallLimit($claim)) {
                            return $this->recordTerminal($claim, 'rejected', 'small_transfer_daily_limit');
                        }
                        if (!$this->transition($claim, 'withdraw')) {
                            return $this->currentStatus($transferId);
                        }
                        $claim['step'] = 'withdraw';
                        continue 2;

                    // 阶段 3：withdraw —— 源账户扣款；ticket 为后续补偿与对账的凭证。
                    case 'withdraw':
                        $result = $this->fundingGateway->withdraw(
                            $claim['source_user_id'],
                            $claim['amount'],
                            'WBCT-' . $claim['target_user_id']
                        );
                        if ($result->status() === 'processed') {
                            // 出金成功即资金已变动，本地后续失败必须转人工而不是拒绝。
                            $moneyMayHaveMoved = true;
                            $claim['withdraw_ticket'] = (string) $result->providerReference();
                            if (!$this->transition($claim, 'deposit', [
                                'withdraw_ticket' => $claim['withdraw_ticket'],
                                'provider_reference' => $claim['withdraw_ticket'],
                            ])) {
                                return $this->currentStatus($transferId);
                            }
                            $claim['step'] = 'deposit';
                            continue 2;
                        }
                        if ($result->status() === 'retryable_not_sent') {
                            return $this->recordRetryable($claim, $result->errorCode() ?: 'connection_failed');
                        }
                        if ($result->status() === 'unknown') {
                            // 扣款结果不确定：可能已扣款，禁止重试，转人工对账。
                            return $this->recordManual($claim, $result->errorCode() ?: 'withdraw_result_unknown');
                        }

                        return $this->recordTerminal($claim, 'rejected', $result->errorCode() ?: 'withdraw_rejected');

                    // 阶段 4：deposit —— 目标账户入金；失败时进入 compensate 补偿分支。
                    case 'deposit':
                        $result = $this->fundingGateway->deposit(
                            $claim['target_user_id'],
                            $claim['amount'],
                            'DBCT-' . $claim['source_user_id']
                        );
                        if ($result->status() === 'processed') {
                            $moneyMayHaveMoved = true;
                            $claim['deposit_ticket'] = (string) $result->providerReference();
                            if (!$this->transition($claim, 'accountinfo', [
                                'deposit_ticket' => $claim['deposit_ticket'],
                                'provider_reference' => $claim['deposit_ticket'],
                            ])) {
                                return $this->currentStatus($transferId);
                            }
                            $claim['step'] = 'accountinfo';
                            continue 2;
                        }
                        if ($result->status() === 'retryable_not_sent') {
                            return $this->recordRetryable($claim, $result->errorCode() ?: 'connection_failed');
                        }
                        if ($result->status() === 'unknown') {
                            // 入金结果不确定：可能已入账，禁止重试，转人工对账。
                            return $this->recordManual($claim, $result->errorCode() ?: 'deposit_result_unknown');
                        }

                        // 入金被明确拒绝：启动补偿，把已扣出的款项退回源账户。
                        if (!$this->transition($claim, 'compensate', [
                            'last_error_code' => $result->errorCode() ?: 'target_deposit_rejected',
                        ])) {
                            return $this->currentStatus($transferId);
                        }
                        $claim['step'] = 'compensate';
                        continue 2;

                    // 阶段 5：compensate —— 补偿退回源账户；补偿成功则整单终态 compensated。
                    case 'compensate':
                        $result = $this->fundingGateway->compensate(
                            $claim['source_user_id'],
                            $claim['amount'],
                            'DBCR-' . $claim['source_user_id'] . '-#' . $claim['withdraw_ticket']
                        );
                        if ($result->status() === 'processed') {
                            return $this->recordTerminal(
                                $claim,
                                'compensated',
                                'target_deposit_rejected',
                                ['compensation_ticket' => (string) $result->providerReference()]
                            );
                        }
                        if ($result->status() === 'retryable_not_sent') {
                            return $this->recordRetryable($claim, $result->errorCode() ?: 'connection_failed');
                        }

                        // 补偿结果不确定或拒绝：资金去向未定，只能转人工核对。
                        return $this->recordManual(
                            $claim,
                            $result->errorCode() ?: 'compensation_result_uncertain'
                        );

                    // 阶段 6：accountinfo —— 读取双方余额快照，供最终记账覆盖本地余额。
                    case 'accountinfo':
                        $sourceSnapshot = $this->snapshotGateway->snapshot($claim['source_user_id']);
                        if ($sourceSnapshot->status() === 'confirmed') {
                            $targetSnapshot = $this->snapshotGateway->snapshot($claim['target_user_id']);
                            if ($targetSnapshot->status() === 'retryable') {
                                return $this->recordRetryable(
                                    $claim,
                                    $targetSnapshot->errorCode() ?: 'target_accountinfo_unavailable'
                                );
                            }
                            if ($targetSnapshot->status() !== 'confirmed') {
                                return $this->recordManual(
                                    $claim,
                                    $targetSnapshot->errorCode() ?: 'target_accountinfo_rejected'
                                );
                            }
                            $claim['source_balance_after'] = (string) $sourceSnapshot->balance();
                            $claim['target_balance_after'] = (string) $targetSnapshot->balance();
                            if (!$this->transition($claim, 'finalize', [
                                'source_balance_after' => $claim['source_balance_after'],
                                'target_balance_after' => $claim['target_balance_after'],
                            ])) {
                                return $this->currentStatus($transferId);
                            }
                            $claim['step'] = 'finalize';
                            continue 2;
                        }
                        if ($sourceSnapshot->status() === 'retryable') {
                            return $this->recordRetryable(
                                $claim,
                                $sourceSnapshot->errorCode() ?: 'source_accountinfo_unavailable'
                            );
                        }

                        return $this->recordManual(
                            $claim,
                            $sourceSnapshot->errorCode() ?: 'source_accountinfo_rejected'
                        );

                    // 阶段 7：finalize —— 幂等记账并标记完成。
                    case 'finalize':
                        $this->finalize($claim);

                        return $this->currentStatus($transferId);
                }

                return $this->recordManual($claim, 'unsupported_saga_step');
            }
        } catch (Throwable $exception) {
            // 本地失败按资金是否可能已变动分流：已变动转人工核对，未变动直接拒绝。
            return $moneyMayHaveMoved
                ? $this->recordManual($claim, 'local_state_after_external_success_failed')
                : $this->recordTerminal($claim, 'rejected', 'local_processing_failed');
        }
    }

    /**
     * 原子认领转账：行锁下校验状态并置为 processing。
     *
     * 失败关闭语义：
     * - 终态/非 pending/retryable 出箱或 available_at 未到，直接返回 null（不抢占）。
     * - processing 超时（STALE_MINUTES 5 分钟）：资金步骤转人工，非资金步骤重置为 retryable。
     * - 出箱与转账 payload_hash 不一致或密码密文解密失败：转人工（可能数据损坏，禁止自动恢复）。
     *
     * @param int $transferId 转账记录 ID。
     * @return array<string, mixed>|null 认领成功返回 claim 数据（含密码明文，仅 verify 步骤）；否则返回 null。
     */
    /** @return array<string, mixed>|null */
    private function claim(int $transferId): ?array
    {
        return DB::transaction(function () use ($transferId): ?array {
            // 行锁转账与 process 出箱，保证同一时刻只有单个扫描器/请求可以推进。
            $transfer = CommissionTransfer::whereKey($transferId)->lockForUpdate()->first();
            $outbox = CommissionTransferOutbox::where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->lockForUpdate()
                ->first();
            if (!$transfer || !$outbox) {
                return null;
            }
            if (in_array($transfer->status, ['completed', 'rejected', 'compensated', 'manual_reconcile_required'], true)) {
                return null;
            }
            if ($outbox->status === 'processing') {
                // 处理中未超时：说明另一执行者持有认领，直接让出。
                if ($outbox->locked_at !== null && $outbox->locked_at->gt(now()->subMinutes(self::STALE_MINUTES))) {
                    return null;
                }
                // 超时认领：withdraw/deposit/compensate 期间崩溃意味着资金可能已变动，
                // 必须转人工而不能自动重试；其余步骤（无外部资金副作用）可安全重置重试。
                if (in_array($transfer->current_step, ['withdraw', 'deposit', 'compensate'], true)) {
                    $this->markManualModels($transfer, $outbox, 'stale_financial_command_claim');
                } else {
                    $outbox->status = 'retryable';
                    $outbox->available_at = now();
                    $outbox->locked_at = null;
                    $outbox->last_error_code = 'stale_safe_claim';
                    $outbox->saveOrFail();
                    $transfer->status = 'retryable';
                    $transfer->locked_at = null;
                    $transfer->available_at = now();
                    $transfer->last_error_code = 'stale_safe_claim';
                    $transfer->saveOrFail();
                }

                return null;
            }
            if (!in_array($outbox->status, ['pending', 'retryable'], true)) {
                return null;
            }
            if ($outbox->available_at !== null && $outbox->available_at->isFuture()) {
                return null;
            }
            // 出箱与转账的 payload_hash 不一致说明数据被篡改或脏写，禁止继续执行。
            if (!hash_equals((string) $transfer->payload_hash, (string) $outbox->payload_hash)) {
                $this->markManualModels($transfer, $outbox, 'outbox_payload_hash_mismatch');

                return null;
            }

            // 仅 verify 步骤需要解密密码；解密后与 payload_hash 交叉校验，
            // 任何一步失败都转人工（payload_decrypt_failed），不自动重试。
            $password = '';
            if ($transfer->current_step === 'verify') {
                try {
                    $password = $this->decryptPassword((string) $transfer->payload_ciphertext);
                    $expected = $this->payloadHash(
                        (int) $transfer->source_user_id,
                        (int) $transfer->target_user_id,
                        (string) $transfer->amount,
                        $password,
                        (string) $transfer->remark,
                        (string) $transfer->request_purpose
                    );
                    if (!hash_equals((string) $transfer->payload_hash, $expected)) {
                        throw new RuntimeException('payload_hash_mismatch');
                    }
                } catch (Throwable $exception) {
                    $this->markManualModels($transfer, $outbox, 'payload_decrypt_failed');

                    return null;
                }
            }

            // 认领成功：双方置为 processing 并累加尝试次数（重试退避的依据）。
            $outbox->status = 'processing';
            $outbox->attempts = (int) $outbox->attempts + 1;
            $outbox->locked_at = now();
            $outbox->last_error_code = null;
            $outbox->saveOrFail();
            $transfer->status = 'processing';
            $transfer->attempts = (int) $transfer->attempts + 1;
            $transfer->locked_at = now();
            $transfer->last_error_code = null;
            $transfer->saveOrFail();

            return [
                'id' => (int) $transfer->id,
                'attempt' => (int) $outbox->attempts,
                'payload_hash' => (string) $outbox->payload_hash,
                'step' => (string) $transfer->current_step,
                'source_user_id' => (int) $transfer->source_user_id,
                'target_user_id' => (int) $transfer->target_user_id,
                'amount' => (string) $transfer->amount,
                'remark' => (string) $transfer->remark,
                'password' => $password,
                'withdraw_ticket' => (string) $transfer->withdraw_ticket,
                'deposit_ticket' => (string) $transfer->deposit_ticket,
                'source_balance_after' => (string) $transfer->source_balance_after,
                'target_balance_after' => (string) $transfer->target_balance_after,
            ];
        }, 3);
    }

    /**
     * 推进转账步骤：行锁复核认领有效后写入步骤字段。
     *
     * @param array<string, mixed> $claim 认领数据（含 id/attempt/payload_hash）。
     * @param string $step 目标步骤名。
     * @param array<string, mixed> $fields 需一并更新的转账字段（如 ticket、余额）。
     * @return bool 推进成功为 true；认领已失效（并发被抢占）时为 false，调用方应停止执行。
     */
    /** @param array<string, mixed> $claim @param array<string, mixed> $fields */
    private function transition(array $claim, string $step, array $fields = []): bool
    {
        return DB::transaction(function () use ($claim, $step, $fields): bool {
            [$transfer, $outbox] = $this->lockedClaimModels($claim);
            if (!$transfer || !$outbox) {
                return false;
            }
            foreach ($fields as $field => $value) {
                $transfer->{$field} = $value;
            }
            $transfer->current_step = $step;
            $transfer->saveOrFail();

            return true;
        }, 3);
    }

    /**
     * 小额转账每日唯一预留（单用户单日最多一笔小额）。
     *
     * 语义：金额 < SMALL_LIMIT(500.00) 时需要预留；预留键为 source_user_id:日期，
     * 已有他人占用则预留失败（同日小额限额已用尽）。并发下依赖 small_limit_key 唯一约束兜底。
     *
     * @param array<string, mixed> $claim 认领数据。
     * @return bool 预留成功为 true；被占用（含并发唯一冲突）返回 false，调用方应终态拒绝。
     */
    /** @param array<string, mixed> $claim */
    private function reserveSmallLimit(array $claim): bool
    {
        // 大额转账无需预留，直接跳过本步骤。
        if (bccomp($claim['amount'], self::SMALL_LIMIT, 2) >= 0) {
            return $this->transition($claim, 'limit', ['reservation_status' => 'not_required']);
        }

        $day = now()->toDateString();
        $key = $claim['source_user_id'] . ':' . $day;
        try {
            return DB::transaction(function () use ($claim, $day, $key): bool {
                [$transfer, $outbox] = $this->lockedClaimModels($claim);
                if (!$transfer || !$outbox) {
                    return false;
                }
                // 当天已有其他小额转账占用预留键：本次直接拒绝（失败关闭，不允许并发挤占）。
                $occupied = CommissionTransfer::where('small_limit_key', $key)
                    ->where('id', '<>', $transfer->id)
                    ->exists();
                if ($occupied) {
                    $transfer->reservation_status = 'denied';
                    $transfer->small_limit_day = $day;
                    $transfer->saveOrFail();

                    return false;
                }
                $transfer->reservation_status = 'reserved';
                $transfer->small_limit_day = $day;
                $transfer->small_limit_key = $key;
                $transfer->saveOrFail();

                return true;
            }, 3);
        } catch (QueryException $exception) {
            // 唯一键冲突兜底：说明并发下已被占用，按未预留处理；否则原样抛出。
            if (CommissionTransfer::where('small_limit_key', $key)->where('id', '<>', $claim['id'])->exists()) {
                return false;
            }
            throw $exception;
        }
    }

    /**
     * 最终记账：行锁复核后交给 LedgerFinalizer 幂等落账（见该类的幂等语义）。
     *
     * @param array<string, mixed> $claim 认领数据（含双方余额与 ticket）。
     * @return void
     */
    /** @param array<string, mixed> $claim */
    private function finalize(array $claim): void
    {
        DB::transaction(function () use ($claim): void {
            [$transfer, $outbox] = $this->lockedClaimModels($claim);
            if (!$transfer || !$outbox) {
                return;
            }
            $this->ledgerFinalizer->finalizeCompleted(
                $transfer,
                $outbox,
                (string) $claim['source_balance_after'],
                (string) $claim['target_balance_after'],
                (string) $claim['withdraw_ticket'],
                (string) $claim['deposit_ticket']
            );
        }, 3);
    }

    /**
     * 记录可重试状态：按尝试次数指数退避（1～10 分钟）后重新可见。
     *
     * 仅用于请求未送达（retryable_not_sent）等安全场景；结果不确定的步骤不得走此路径。
     *
     * @param array<string, mixed> $claim 认领数据。
     * @param string $errorCode 重试原因码。
     * @return string 当前转账状态。
     */
    /** @param array<string, mixed> $claim */
    private function recordRetryable(array $claim, string $errorCode): string
    {
        DB::transaction(function () use ($claim, $errorCode): void {
            [$transfer, $outbox] = $this->lockedClaimModels($claim);
            if (!$transfer || !$outbox) {
                return;
            }
            // 退避窗口 = 60 秒 * min(attempts, 10)，attempts 越高等待越久，避免风暴式重试。
            $availableAt = now()->addSeconds(60 * max(1, min((int) $outbox->attempts, 10)));
            $transfer->status = 'retryable';
            $transfer->available_at = $availableAt;
            $transfer->locked_at = null;
            $transfer->last_error_code = $errorCode;
            $transfer->saveOrFail();
            $outbox->status = 'retryable';
            $outbox->available_at = $availableAt;
            $outbox->locked_at = null;
            $outbox->last_error_code = $errorCode;
            $outbox->saveOrFail();
        }, 3);

        return $this->currentStatus($claim['id']);
    }

    /**
     * 记录终态（rejected / compensated）：清锁与敏感 payload，出箱标记 completed。
     *
     * @param array<string, mixed> $claim 认领数据。
     * @param string $status 终态状态（rejected / compensated）。
     * @param string $errorCode 终态原因码。
     * @param array<string, mixed> $fields 需一并落库的字段（如补偿 ticket）。
     * @return string 当前转账状态。
     */
    /** @param array<string, mixed> $claim @param array<string, mixed> $fields */
    private function recordTerminal(array $claim, string $status, string $errorCode, array $fields = []): string
    {
        DB::transaction(function () use ($claim, $status, $errorCode, $fields): void {
            [$transfer, $outbox] = $this->lockedClaimModels($claim);
            if (!$transfer || !$outbox) {
                return;
            }
            foreach ($fields as $field => $value) {
                $transfer->{$field} = $value;
            }
            $transfer->status = $status;
            $transfer->current_step = $status;
            $transfer->processed_at = now();
            $transfer->available_at = null;
            $transfer->locked_at = null;
            $transfer->last_error_code = $errorCode;
            $transfer->payload_ciphertext = null;
            $transfer->saveOrFail();
            $outbox->status = 'completed';
            $outbox->processed_at = now();
            $outbox->available_at = null;
            $outbox->locked_at = null;
            $outbox->last_error_code = $errorCode;
            $outbox->saveOrFail();
        }, 3);

        return $this->currentStatus($claim['id']);
    }

    /**
     * 转人工对账：状态置为 manual_reconcile_required，等待管理员裁决。
     *
     * 适用于结果不确定（unknown）、外部资金可能已变动或数据一致性存疑的所有场景。
     *
     * @param array<string, mixed> $claim 认领数据。
     * @param string $errorCode 转人工原因码。
     * @return string 当前转账状态。
     */
    /** @param array<string, mixed> $claim */
    private function recordManual(array $claim, string $errorCode): string
    {
        DB::transaction(function () use ($claim, $errorCode): void {
            [$transfer, $outbox] = $this->lockedClaimModels($claim);
            if (!$transfer || !$outbox) {
                return;
            }
            $this->markManualModels($transfer, $outbox, $errorCode);
        }, 3);

        return $this->currentStatus($claim['id']);
    }

    /**
     * 落人工状态：首次标记时回填 manual_origin_step，清除锁与敏感 payload。
     *
     * @param CommissionTransfer $transfer 转账记录。
     * @param CommissionTransferOutbox $outbox 对应 process 出箱。
     * @param string $errorCode 转人工原因码。
     * @return void
     */
    private function markManualModels(
        CommissionTransfer $transfer,
        CommissionTransferOutbox $outbox,
        string $errorCode
    ): void {
        if ($transfer->manual_origin_step === null) {
            $transfer->manual_origin_step = (string) $transfer->current_step;
        }
        $transfer->status = 'manual_reconcile_required';
        $transfer->processed_at = now();
        $transfer->available_at = null;
        $transfer->locked_at = null;
        $transfer->last_error_code = $errorCode;
        $transfer->payload_ciphertext = null;
        $transfer->saveOrFail();
        $outbox->status = 'manual_reconcile_required';
        $outbox->processed_at = now();
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->last_error_code = $errorCode;
        $outbox->saveOrFail();
    }

    /**
     * 行锁复核认领是否仍有效：出箱必须仍是 processing、尝试次数与 payload_hash 与认领时一致。
     *
     * 任一条件不满足说明认领已被另一执行者接管或数据被修改，返回 [null, null] 让调用方放弃写库。
     *
     * @param array<string, mixed> $claim 认领数据。
     * @return array{0: CommissionTransfer|null, 1: CommissionTransferOutbox|null} 有效时返回模型对。
     */
    /**
     * @param array<string, mixed> $claim
     * @return array{0: CommissionTransfer|null, 1: CommissionTransferOutbox|null}
     */
    private function lockedClaimModels(array $claim): array
    {
        $transfer = CommissionTransfer::whereKey($claim['id'])->lockForUpdate()->first();
        $outbox = CommissionTransferOutbox::where('commission_transfer_id', $claim['id'])
            ->where('event_type', 'process')
            ->lockForUpdate()
            ->first();
        if (!$transfer || !$outbox
            || $outbox->status !== 'processing'
            || (int) $outbox->attempts !== (int) $claim['attempt']
            || !hash_equals((string) $outbox->payload_hash, (string) $claim['payload_hash'])
            || !hash_equals((string) $transfer->payload_hash, (string) $claim['payload_hash'])) {
            return [null, null];
        }

        return [$transfer, $outbox];
    }

    /**
     * 按用户+用途+幂等键查找已有转账记录（幂等命中检查）。
     *
     * @param int $sourceUserId 转出方用户 ID。
     * @param string $purpose 请求用途标识。
     * @param string $idempotencyKey 幂等键。
     * @return CommissionTransfer|null 命中返回记录，否则返回 null。
     */
    private function findExisting(int $sourceUserId, string $purpose, string $idempotencyKey): ?CommissionTransfer
    {
        return CommissionTransfer::where('source_user_id', $sourceUserId)
            ->where('request_purpose', $purpose)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * 幂等命中时校验请求身份一致；不一致说明同键复用但参数不同，禁止返回旧记录。
     *
     * @param CommissionTransfer $transfer 已存在的转账记录。
     * @param string $payloadHash 本次请求计算的 payload 摘要。
     * @return void
     * @throws DomainException 摘要不一致时抛出。
     */
    private function assertSamePayload(CommissionTransfer $transfer, string $payloadHash): void
    {
        if (!hash_equals((string) $transfer->payload_hash, $payloadHash)) {
            throw new DomainException('idempotency_conflict');
        }
    }

    /**
     * 计算请求身份摘要：密码以 app key 做 HMAC-SHA256 摘要后参与计算。
     *
     * 安全说明：密码原文不直接进入摘要输入与任何持久化字段，只存摘要；app key 缺失时失败关闭。
     *
     * @param int $sourceUserId 转出方用户 ID。
     * @param int $targetUserId 接收方用户 ID。
     * @param string $amount 转账金额。
     * @param string $password 交易密码明文。
     * @param string $remark 备注。
     * @param string $purpose 请求用途标识。
     * @return string HMAC-SHA256 摘要（hex）。
     * @throws RuntimeException app key 缺失或 JSON 编码失败时抛出。
     */
    private function payloadHash(
        int $sourceUserId,
        int $targetUserId,
        string $amount,
        string $password,
        string $remark,
        string $purpose
    ): string {
        $passwordDigest = hash_hmac('sha256', $password, $this->applicationKey());
        try {
            $json = json_encode([
                'source_user_id' => $sourceUserId,
                'target_user_id' => $targetUserId,
                'amount' => $amount,
                'remark' => $remark,
                'purpose' => $purpose,
                'password_digest' => $passwordDigest,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode commission transfer identity.', 0, $exception);
        }

        return hash_hmac('sha256', $json, $this->applicationKey());
    }

    /**
     * 加密交易密码：使用 Laravel Crypt（应用密钥加密）后落库，不存明文。
     *
     * @param string $password 交易密码明文。
     * @return string 加密密文。
     */
    private function encryptPassword(string $password): string
    {
        return Crypt::encryptString($password);
    }

    /**
     * 解密交易密码：仅 verify 步骤使用；密文缺失或解密为空视为数据损坏并失败关闭。
     *
     * @param string $ciphertext 密文。
     * @return string 密码明文（调用方必须在使用后立即清除）。
     * @throws RuntimeException 密文缺失、解密失败或内容为空时抛出。
     */
    private function decryptPassword(string $ciphertext): string
    {
        if (trim($ciphertext) === '') {
            throw new RuntimeException('Commission transfer password payload is missing.');
        }
        $password = Crypt::decryptString($ciphertext);
        if ($password === '') {
            throw new RuntimeException('Commission transfer password payload is empty.');
        }

        return $password;
    }

    /**
     * 获取应用密钥；缺失时失败关闭，防止在无密钥环境下生成可被伪造的身份摘要。
     *
     * @return string app.key 配置值。
     * @throws RuntimeException 密钥为空时抛出。
     */
    private function applicationKey(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('Application key is required for commission transfer identities.');
        }

        return $key;
    }

    /**
     * 读取转账当前状态；记录已不存在时返回 "missing"。
     *
     * @param int $transferId 转账记录 ID。
     * @return string 状态字符串或 "missing"。
     */
    private function currentStatus(int $transferId): string
    {
        $status = CommissionTransfer::whereKey($transferId)->value('status');

        return $status === null ? 'missing' : (string) $status;
    }
}
