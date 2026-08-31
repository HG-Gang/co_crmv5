<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:01
 */

declare(strict_types=1);

namespace App\Services\CommissionTransfer;

use App\Models\Admin;
use App\Models\CommissionTransfer;
use App\Models\CommissionTransferOutbox;
use App\Models\OperationLog;
use App\Services\AdminDataScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * 佣金转账人工对账服务。
 *
 * 文件功能：
 * - 列出所有需要人工对账的转账记录（分页查询）。
 * - 提供转账详情供管理员查看。
 * - 管理员提交对账证据后，根据策略做出最终决策（completed / compensated / rejected）并完成记账或终止。
 * - 记录完整的对账审计日志。
 *
 * 适用场景：
 * - 佣金转账进入 manual_reconcile_required 状态后，管理员在后台对账页面操作。
 * - 依据 MT4 实际交易结果（withdraw / deposit / compensation 状态）人工裁决。
 *
 * 入参例子：
 * - admin: Admin 实例（当前登录管理员）。
 * - transferId: 转账记录 ID。
 * - evidence: ['decision' => 'confirmed_completed', 'withdraw_status' => 'confirmed_processed', 'deposit_status' => 'confirmed_processed', 'withdraw_reference' => 'TICKET123', 'deposit_reference' => 'TICKET456', 'source_balance_after' => '5000.00', 'target_balance_after' => '2000.00']
 * - externalReference: '手动对账备注'
 * - ip: '192.168.1.1'
 *
 * 返回值：
 * - cases(): 分页列表 LengthAwarePaginator。
 * - detail(): ['result' => 'ok', 'transfer' => CommissionTransfer] 或 ['result' => 'not_allowed'|'forbidden']。
 * - reconcile(): ['result' => 'ok', 'transfer' => CommissionTransfer] 或 ['result' => 'invalid_evidence'|'not_allowed'|'not_found'|'forbidden']。
 *
 * 异常或失败场景：
 * - 数据权限校验失败返回 forbidden。
 * - 证据与已存储数据冲突返回 invalid_evidence。
 * - 不在 manual_reconcile_required 状态的记录不可操作。
 */
final class CommissionTransferReconciliationService
{
    /**
     * 人工对账目标的转账状态值：'manual_reconcile_required'。
     * Saga 在外部资金结果不确定（unknown）时落入该状态，是后台对账页面唯一可操作的记录筛选口径；
     * 值写入 commission_transfers.status 与 process 出箱状态，修改会同时影响自动扫描与人工页面。
     *
     * @var string
     */
    public const MANUAL_STATUS = 'manual_reconcile_required';

    /**
     * 管理员裁决决策到转账终态的映射表：键是请求允许的 decision 值（confirmed_completed /
     * confirmed_compensated / confirmed_rejected），值是写回的最终状态（completed/compensated/rejected）。
     * 三者的业务差异：completed=资金已按原指令到账、按原计划记账；compensated=已反向补偿、按补偿记账；
     * rejected=指令实际未生效、按拒绝终止。映射之外的决策值一律按 invalid_evidence 拒绝。
     *
     * @var array<string, string>
     */
    private const DECISION_STATUSES = [
        'confirmed_completed' => 'completed',
        'confirmed_compensated' => 'compensated',
        'confirmed_rejected' => 'rejected',
    ];

    /**
     * 后台数据范围服务：对账案例列表与详情按 commission_transfers 的代理归属过滤管理员可见范围；
     * 缺失时任何管理员可查看/裁决全量转账对账案例，越权后果是触碰他人管辖的资金裁决。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 对账策略评估器：校验管理员提交的证据（各步骤资金状态、参考号、余额）与存储数据是否自洽、
     * 是否匹配转账起源步骤；裁决正确性完全依赖它，缺失或放水实现会把不一致证据写成终态，无法回滚。
     *
     * @var CommissionTransferReconciliationPolicy
     */
    private $policy;

    /**
     * 记账终态器：completed 裁决通过后执行完整记账（流水写入与状态落库）。
     * 未显式传入时构造函数以默认实现兜底；缺失时已确认完成的转账无法闭环到本地账本。
     *
     * @var CommissionTransferLedgerFinalizer
     */
    private $ledgerFinalizer;

    /**
     * 余额字符串规范化器：把管理员提交的余额文本统一为 DECIMAL(18,2) 字符串再参与证据比对。
     * 规范化必须在比较前完成，否则 '500.0' 与 '500.00' 会被误判为证据冲突。
     *
     * @var CommissionTransferBalanceNormalizer
     */
    private $balanceNormalizer;

    /**
     * 构造函数注入对账依赖服务。
     *
     * policy 与 ledgerFinalizer 未显式传入时使用默认实现，便于依赖注入容器装配与测试替身替换。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于限制管理员可见与可操作的转账范围。
     * @param CommissionTransferReconciliationPolicy|null $policy 对账策略评估器，裁决证据与起源步骤是否匹配。
     * @param CommissionTransferLedgerFinalizer|null $ledgerFinalizer 记账终态器，completed 时执行完整记账。
     * @param CommissionTransferBalanceNormalizer|null $balanceNormalizer 余额字符串规范化器。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        CommissionTransferReconciliationPolicy $policy = null,
        CommissionTransferLedgerFinalizer $ledgerFinalizer = null,
        CommissionTransferBalanceNormalizer $balanceNormalizer = null
    ) {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->balanceNormalizer = $balanceNormalizer ?: new CommissionTransferBalanceNormalizer();
        $this->policy = $policy ?: new CommissionTransferReconciliationPolicy($this->balanceNormalizer);
        $this->ledgerFinalizer = $ledgerFinalizer ?: new CommissionTransferLedgerFinalizer($this->balanceNormalizer);
    }

    /**
     * 返回允许的裁决决策值列表。
     *
     * @return array<int, string> 决策值：confirmed_completed / confirmed_compensated / confirmed_rejected。
     */
    public static function decisionStatuses(): array
    {
        return array_keys(self::DECISION_STATUSES);
    }

    /**
     * 返回允许的出入金资金状态值列表。
     *
     * @return array<int, string> 资金状态：confirmed_processed / confirmed_rejected / confirmed_not_processed。
     */
    public static function fundingStatuses(): array
    {
        return ['confirmed_processed', 'confirmed_rejected', 'confirmed_not_processed'];
    }

    /**
     * 分页查询需要人工对账的转账记录。
     *
     * 仅返回状态为 manual_reconcile_required 且关联的 process 出箱同样为该状态的记录。
     * 应用管理员数据权限范围过滤。
     *
     * @param Admin $admin 当前登录管理员。
     * @param int $page 当前页码。
     * @param int $perPage 每页条数。
     *
     * @return LengthAwarePaginator 分页列表，含 source / target 关联。
     */
    public function cases(Admin $admin, int $page, int $perPage): LengthAwarePaginator
    {
        // 只列出转账与 process 出箱双双处于 manual_reconcile_required 的记录，
        // 再叠加管理员数据范围，防止越权看到他人客户的待对账数据。
        $query = $this->manualTransferQuery()->with(['source', 'target']);
        $query = $this->adminDataScopeService->apply($query, $admin, 'agent', 'source_user_id');

        return $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 获取单笔待对账转账的详情。
     *
     * @param Admin $admin 当前登录管理员。
     * @param int $transferId 转账记录 ID。
     *
     * @return array{result:string, transfer?:CommissionTransfer, error_code?:string}
     *         result 为 ok 时附带 transfer，否则为 forbidden / not_allowed。
     */
    public function detail(Admin $admin, int $transferId): array
    {
        $transfer = $this->manualTransferQuery()
            ->with(['source', 'target'])
            ->whereKey($transferId)
            ->first();
        if (!$transfer) {
            return ['result' => 'not_allowed'];
        }
        if (!$this->adminDataScopeService->canAccessUser($admin, $transfer->source_user_id, 'agent')) {
            return ['result' => 'forbidden'];
        }
        $outboxes = CommissionTransferOutbox::query()
            ->where('commission_transfer_id', $transferId)
            ->where('event_type', 'process')
            ->get();
        if ($outboxes->count() !== 1 || $outboxes->first()->status !== self::MANUAL_STATUS) {
            return ['result' => 'not_allowed'];
        }
        $transfer->setRelation('outbox', $outboxes->first());

        return ['result' => 'ok', 'transfer' => $transfer];
    }

    /**
     * 对账裁决并最终确认转账结果。
     *
     * 管理员提交外部证据，由 CommissionTransferReconciliationPolicy 评估后，
     * 将转账标记为 completed / compensated / rejected 并完成记账或终止流程。
     * 全程在事务中执行，并写入操作日志。
     *
     * @param Admin $admin 当前登录管理员。
     * @param int $transferId 转账记录 ID。
     * @param array<string, mixed> $evidence 对账证据，包含 decision / withdraw_status / deposit_status / compensation_status 及各参考号、余额快照。
     * @param string $externalReference 外部参考号，最长 100 字符。
     * @param string $ip 操作 IP。
     *
     * @return array{result:string, transfer?:CommissionTransfer, error_code?:string}
     */
    public function reconcile(
        Admin $admin,
        int $transferId,
        array $evidence,
        string $externalReference,
        string $ip
    ): array {
        // 事务外先做纯格式校验：外部引用与决策值非法时直接返回，不进入锁与写路径。
        $externalReference = trim($externalReference);
        if ($externalReference === '' || mb_strlen($externalReference, 'UTF-8') > 100) {
            return ['result' => 'invalid_evidence', 'error_code' => 'invalid_external_reference'];
        }

        // 统一裁剪并规范化证据字段，保证后续策略评估与审计日志拿到一致格式。
        $evidence = $this->normalizeEvidence($evidence);
        if (!isset(self::DECISION_STATUSES[$evidence['decision']])) {
            return ['result' => 'invalid_decision'];
        }

        // 行锁内复核状态与权限，防止并发下重复裁决或越权操作。
        return DB::transaction(function () use (
            $admin,
            $transferId,
            $evidence,
            $externalReference,
            $ip
        ): array {
            $transfer = CommissionTransfer::query()->whereKey($transferId)->lockForUpdate()->first();
            if (!$transfer) {
                return ['result' => 'not_found'];
            }
            if ($transfer->status !== self::MANUAL_STATUS) {
                return ['result' => 'not_allowed'];
            }

            $outboxes = CommissionTransferOutbox::query()
                ->where('commission_transfer_id', $transferId)
                ->where('event_type', 'process')
                ->lockForUpdate()
                ->get();
            if ($outboxes->count() !== 1 || $outboxes->first()->status !== self::MANUAL_STATUS) {
                return ['result' => 'not_allowed'];
            }
            $outbox = $outboxes->first();

            if (!$this->adminDataScopeService->canAccessUser(
                $admin,
                (int) $transfer->source_user_id,
                'agent'
            )) {
                return ['result' => 'forbidden'];
            }

            // 策略裁决：证据与起源步骤不匹配时直接拒绝（失败关闭，禁止猜测性记账）。
            $originStep = trim((string) $transfer->manual_origin_step);
            $decision = $this->policy->evaluate($originStep, $evidence);
            if (!$decision->isAllowed()) {
                return ['result' => 'invalid_evidence', 'error_code' => $decision->errorCode()];
            }

            // 证据必须与已存储的 ticket/余额事实一致，防止用虚假证据覆盖真实状态。
            $conflict = $this->storedEvidenceConflict($transfer, $evidence);
            if ($conflict !== null) {
                return ['result' => 'invalid_evidence', 'error_code' => $conflict];
            }

            // 落证据并执行终态：completed 走完整记账，compensated/rejected 只终止流程不记账。
            $before = $this->auditState($transfer);
            $this->applyEvidence($transfer, $evidence, $externalReference, (int) $admin->id);

            if ($decision->terminalStatus() === 'completed') {
                $this->ledgerFinalizer->finalizeCompleted(
                    $transfer,
                    $outbox,
                    (string) $evidence['source_balance_after'],
                    (string) $evidence['target_balance_after'],
                    (string) $evidence['withdraw_reference'],
                    (string) $evidence['deposit_reference']
                );
            } else {
                $this->finalizeWithoutLedger(
                    $transfer,
                    $outbox,
                    (string) $decision->terminalStatus(),
                    $evidence
                );
            }

            // 记录裁决前后状态与证据，保证对账动作可审计追溯。
            $after = $this->auditState($transfer->fresh());
            $this->writeOperationLog(
                $admin,
                $transfer,
                $before,
                $after,
                $evidence,
                $externalReference,
                $ip
            );

            return ['result' => 'ok', 'transfer' => $transfer->fresh()];
        }, 3);
    }

    /**
     * 构造待对账转账查询：转账与 process 出箱都处于 manual_reconcile_required，
     * 且 process 出箱必须唯一（保证行锁语义下不存在歧义）。
     *
     * @return \Illuminate\Database\Eloquent\Builder 查询构建器。
     */
    private function manualTransferQuery()
    {
        return CommissionTransfer::query()
            ->where('status', self::MANUAL_STATUS)
            ->whereHas('outbox', function ($query): void {
                $query->where('event_type', 'process')
                    ->where('status', self::MANUAL_STATUS);
            })
            ->whereRaw(
                '(SELECT COUNT(*) FROM commission_transfer_outbox AS process_outbox_count'
                . ' WHERE process_outbox_count.commission_transfer_id = commission_transfers.id'
                . ' AND process_outbox_count.event_type = ?'
                . ' AND process_outbox_count.deleted_at IS NULL) = 1',
                ['process']
            );
    }

    /**
     * 规范化证据：决策与状态字段裁剪为字符串；引用与余额为空转 null，余额合法时统一两位小数格式。
     *
     * @param array<string, mixed> $evidence 管理员提交的原始证据。
     * @return array<string, mixed> 规范化后的证据。
     */
    private function normalizeEvidence(array $evidence): array
    {
        foreach (['decision', 'withdraw_status', 'deposit_status', 'compensation_status'] as $field) {
            $evidence[$field] = trim((string) ($evidence[$field] ?? ''));
        }
        foreach (['withdraw_reference', 'deposit_reference', 'compensation_reference'] as $field) {
            $value = trim((string) ($evidence[$field] ?? ''));
            $evidence[$field] = $value === '' ? null : $value;
        }
        foreach (['source_balance_after', 'target_balance_after'] as $field) {
            $value = trim((string) ($evidence[$field] ?? ''));
            $evidence[$field] = $value === '' ? null : $value;
            if ($value !== '') {
                $normalized = $this->balanceNormalizer->normalize($value);
                if ($normalized !== null) {
                    $evidence[$field] = $normalized;
                }
            }
        }

        return $evidence;
    }

    /**
     * 校验证据与已存储事实一致：已存 ticket 对应状态必须是已处理；
     * 已存引用/余额与证据值冲突时返回冲突错误码（失败关闭）。
     *
     * @param CommissionTransfer $transfer 已行锁的转账记录。
     * @param array<string, mixed> $evidence 规范化后的证据。
     * @return string|null 冲突时返回错误码，无冲突返回 null。
     */
    private function storedEvidenceConflict(CommissionTransfer $transfer, array $evidence): ?string
    {
        foreach ([
            'withdraw_ticket' => ['withdraw_status', 'withdraw'],
            'deposit_ticket' => ['deposit_status', 'deposit'],
            'compensation_ticket' => ['compensation_status', 'compensation'],
        ] as $storedField => $statusFact) {
            if (trim((string) $transfer->{$storedField}) !== ''
                && ($evidence[$statusFact[0]] ?? null) !== 'confirmed_processed') {
                return $statusFact[1] . '_status_conflicts_with_stored_ticket';
            }
        }

        foreach ([
            'withdraw_ticket' => 'withdraw_reference',
            'deposit_ticket' => 'deposit_reference',
            'compensation_ticket' => 'compensation_reference',
            'source_balance_after' => 'source_balance_after',
            'target_balance_after' => 'target_balance_after',
        ] as $storedField => $evidenceField) {
            $stored = trim((string) $transfer->{$storedField});
            $provided = trim((string) ($evidence[$evidenceField] ?? ''));
            if ($stored !== '' && $provided !== '' && !hash_equals($stored, $provided)) {
                return $evidenceField . '_conflicts_with_stored_fact';
            }
        }

        return null;
    }

    /**
     * 将裁决结果落库到转账记录（含证据 JSON 与裁决人/时间）。
     *
     * @param CommissionTransfer $transfer 已行锁的转账记录。
     * @param array<string, mixed> $evidence 规范化后的证据。
     * @param string $externalReference 外部参考号。
     * @param int $adminId 裁决管理员 ID。
     * @return void
     */
    private function applyEvidence(
        CommissionTransfer $transfer,
        array $evidence,
        string $externalReference,
        int $adminId
    ): void {
        $transfer->withdraw_ticket = $evidence['withdraw_reference'];
        $transfer->deposit_ticket = $evidence['deposit_reference'];
        $transfer->compensation_ticket = $evidence['compensation_reference'];
        $transfer->source_balance_after = $evidence['source_balance_after'];
        $transfer->target_balance_after = $evidence['target_balance_after'];
        $transfer->reconcile_decision = $evidence['decision'];
        $transfer->reconcile_external_reference = $externalReference;
        $transfer->reconcile_evidence = $this->encodeEvidence($evidence);
        $transfer->reconciled_by = $adminId;
        $transfer->reconciled_at = now();
    }

    /**
     * 不记账的终态收尾（compensated / rejected）：只更新转账与出箱状态并清除敏感 payload。
     *
     * compensated 以补偿 ticket 为 provider_reference，rejected 以出金 ticket 为引用；
     * 终态后禁止再被 Saga 扫描器认领。
     *
     * @param CommissionTransfer $transfer 已行锁的转账记录。
     * @param CommissionTransferOutbox $outbox 对应 process 出箱。
     * @param string $terminalStatus compensated / rejected。
     * @param array<string, mixed> $evidence 规范化后的证据。
     * @return void
     */
    private function finalizeWithoutLedger(
        CommissionTransfer $transfer,
        CommissionTransferOutbox $outbox,
        string $terminalStatus,
        array $evidence
    ): void {
        $now = now();
        $transfer->status = $terminalStatus;
        $transfer->current_step = $terminalStatus;
        $transfer->processed_at = $now;
        $transfer->available_at = null;
        $transfer->locked_at = null;
        $transfer->payload_ciphertext = null;
        $transfer->provider_reference = $terminalStatus === 'compensated'
            ? $evidence['compensation_reference']
            : $evidence['withdraw_reference'];
        $transfer->last_error_code = $terminalStatus === 'compensated'
            ? 'manual_confirmed_compensated'
            : 'manual_confirmed_rejected';
        $transfer->last_error_message = null;
        $transfer->saveOrFail();

        $outbox->status = 'completed';
        $outbox->processed_at = $now;
        $outbox->available_at = null;
        $outbox->locked_at = null;
        $outbox->provider_reference = $transfer->provider_reference;
        $outbox->last_error_code = $transfer->last_error_code;
        $outbox->saveOrFail();
    }

    /**
     * 证据编码为 JSON 落库；编码失败抛 RuntimeException（失败关闭）。
     *
     * @param array<string, mixed> $evidence 规范化后的证据。
     * @return string JSON 字符串。
     * @throws RuntimeException 编码失败时抛出。
     */
    private function encodeEvidence(array $evidence): string
    {
        try {
            return json_encode(
                $evidence,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode commission transfer reconciliation evidence.', 0, $exception);
        }
    }

    /**
     * 写入对账操作日志：记录裁决前后状态、关键证据与管理员/来源 IP，保证可审计。
     *
     * @param Admin $admin 裁决管理员。
     * @param CommissionTransfer $transfer 转账记录。
     * @param array<string, mixed> $before 裁决前状态快照。
     * @param array<string, mixed> $after 裁决后状态快照。
     * @param array<string, mixed> $evidence 规范化后的证据。
     * @param string $externalReference 外部参考号。
     * @param string $ip 操作来源 IP。
     * @return void
     */
    private function writeOperationLog(
        Admin $admin,
        CommissionTransfer $transfer,
        array $before,
        array $after,
        array $evidence,
        string $externalReference,
        string $ip
    ): void {
        $content = $this->encodeCompactAudit([
            'a' => 'commission_transfer_reconcile',
            'id' => (int) $transfer->id,
            'ref' => $externalReference,
            'b' => $before,
            'f' => [
                'd' => $evidence['decision'],
                'ws' => $evidence['withdraw_status'],
                'wr' => $evidence['withdraw_reference'],
                'ds' => $evidence['deposit_status'],
                'dr' => $evidence['deposit_reference'],
                'cs' => $evidence['compensation_status'],
                'cr' => $evidence['compensation_reference'],
                'sb' => $evidence['source_balance_after'],
                'tb' => $evidence['target_balance_after'],
            ],
            'n' => $after,
        ]);

        OperationLog::query()->create([
            'admin_id' => $admin->id,
            'admin_name' => (string) $admin->username,
            'target_user_id' => $transfer->source_user_id,
            'order_no' => $transfer->local_order_no,
            'content' => $content,
            'ip' => $ip,
            'action_type' => 0,
        ]);
    }

    /**
     * 审计内容编码为紧凑 JSON；超长（>1000 字节）拒绝落库，避免审计字段溢出。
     *
     * @param array<string, mixed> $audit 审计内容。
     * @return string JSON 字符串。
     * @throws RuntimeException 编码失败或超长时抛出。
     */
    private function encodeCompactAudit(array $audit): string
    {
        try {
            $content = json_encode(
                $audit,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode commission transfer reconciliation audit log.', 0, $exception);
        }
        if (strlen($content) > 1000) {
            throw new RuntimeException('Commission transfer reconciliation audit log exceeds 1000 bytes.');
        }

        return $content;
    }

    /**
     * 提取转账记录用于审计的状态快照（压缩键名，控制审计体积）。
     *
     * @param CommissionTransfer $transfer 转账记录。
     * @return array<string, mixed> 状态快照。
     */
    private function auditState(CommissionTransfer $transfer): array
    {
        return [
            's' => (string) $transfer->status,
            'st' => (string) $transfer->current_step,
            'os' => (string) $transfer->manual_origin_step,
            'e' => $transfer->last_error_code,
            'p' => $transfer->provider_reference,
            'd' => $transfer->reconcile_decision,
            'r' => $transfer->reconcile_external_reference,
            'by' => $transfer->reconciled_by,
            'at' => $transfer->reconciled_at ? (int) $transfer->reconciled_at->timestamp : null,
        ];
    }
}
