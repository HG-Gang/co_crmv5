<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 13:49
 */

declare(strict_types=1);

namespace App\Services\Withdrawal;

use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\SystemConfig;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Support\Money;
use Closure;
use DomainException;
use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * 提现订单创建服务。
 *
 * 文件功能：
 * - 校验用户提现资格（账户状态、实名认证、银行卡审核、风险率、持仓检查、提现时段）。
 * - 通过 MySQL GET_LOCK 实现同用户并发提现的预约锁，防止重复扣款。
 * - 计算手续费（固定费 + 比例费）和人民币折合金额，生成本地订单号。
 * - 通过幂等键保证同一请求不重复创建提现记录。
 * - 创建结算出箱记录（withdraw_debit），由出箱扫描器驱动后续提现扣款。
 *
 * 适用场景：
 * - 前端用户发起提现请求时调用。
 * - 后台管理员代用户发起提现时同样适用。
 *
 * 入参例子：
 * - user: UserInfo 实例。
 * - amount: Money::fromDecimalString('1000.00', ...)。
 * - key: 'wdr_req_a1b2c3'。
 *
 * 返回值：
 * - createOrRetrieve(): ['order' => WithdrawRecord, 'created' => true] 新创建。
 * - ['order' => WithdrawRecord, 'created' => false] 幂等命中。
 * - replayExisting(): ['order' => WithdrawRecord, 'created' => false] 或 null。
 *
 * 异常或失败场景：
 * - DomainException('withdrawal_disabled')：用户被禁止提现。
 * - DomainException('identity_not_approved')：实名未通过。
 * - DomainException('bank_not_approved')：银行卡未审核。
 * - DomainException('risk_rate_exceeded')：风险率超限。
 * - DomainException('open_positions')：存在持仓。
 * - DomainException('withdrawal_time_unavailable')：非提现时段。
 * - DomainException('insufficient_balance')：可用余额不足。
 * - DomainException('reservation_lock_unavailable')：并发锁获取失败。
 * - DomainException('snapshot_unavailable')：余额快照查询失败。
 */
class WithdrawalOrderService
{
    /**
     * 余额快照网关：创建提现单前读取 MT4 balance/freeMargin 计算可出金额度。
     * 快照不可用必须以 snapshot_unavailable 拒绝下单（失败关闭）；缺失或被替换为
     * 本地估算实现会绕过真实资金校验，造成超余额提现。
     *
     * @var WithdrawalAccountSnapshotGateway
     */
    private $snapshotGateway;

    /**
     * 可选订单创建器（接收字段数组返回 WithdrawRecord），默认 null 表示用 WithdrawRecord::create。
     * 仅测试替身注入；正常链路必须走默认创建，保证订单字段与幂等口径一致。
     *
     * @var Closure|null
     */
    private $orderCreator;

    /**
     * 可选预约锁释放器：业务成功后释放 MySQL GET_LOCK 预约锁；默认 null 用原生实现。
     * 释放失败时提现请求必须失败关闭，测试注入替身以便复现锁释放异常分支。
     *
     * @var Closure|null
     */
    private $lockReleaser;

    /**
     * 可选锁断开器：预约锁释放失败后的最后手段——断开当前数据库连接强制释放连接级锁；
     * 默认 null 用原生实现。没有这一层，释放失败的锁要等会话超时才释放，同用户后续提现全部被阻塞。
     *
     * @var Closure|null
     */
    private $lockDisconnector;

    /**
     * 构造提现订单服务。
     *
     * @param WithdrawalAccountSnapshotGateway $snapshotGateway 余额快照网关，获取 MT4 balance/freeMargin。
     * @param callable|null $orderCreator 可选订单创建器，默认 WithdrawRecord::create，测试时注入替身。
     * @param callable|null $lockReleaser 可选预约锁释放器，测试时注入替身。
     * @param callable|null $lockDisconnector 可选锁释放失败后的连接断开器，测试时注入替身。
     */
    public function __construct(
        WithdrawalAccountSnapshotGateway $snapshotGateway,
        callable $orderCreator = null,
        callable $lockReleaser = null,
        callable $lockDisconnector = null
    )
    {
        $this->snapshotGateway = $snapshotGateway;
        $this->orderCreator = $orderCreator === null ? null : Closure::fromCallable($orderCreator);
        $this->lockReleaser = $lockReleaser === null ? null : Closure::fromCallable($lockReleaser);
        $this->lockDisconnector = $lockDisconnector === null
            ? null
            : Closure::fromCallable($lockDisconnector);
    }

    /**
     * 创建或检索提现订单。
     *
     * 依次执行：幂等检查 -> 余额快照 -> 获取预约锁 -> 事务内校验用户规则和银行规则 ->
     * 计算手续费 -> 创建提现记录和结算出箱。锁在 finally 中释放。
     *
     * @param UserInfo $user 提现用户。
     * @param Money $amount 提现金额。
     * @param string $key 幂等键。
     *
     * @return array<string, mixed> ['order' => WithdrawRecord, 'created' => bool]
     *
     * @throws DomainException 用户资格、银行信息、余额、时间窗口、并发锁等校验失败时抛出。
     */
    /** @return array<string, mixed> */
    public function createOrRetrieve(UserInfo $user, Money $amount, string $key): array
    {
        // 阶段 1：幂等快查 —— 事务外先查已存在订单，命中即返回，不重复获取锁与快照。
        $replay = $this->replayExisting($user, $amount, $key);
        if ($replay !== null) {
            return $replay;
        }

        // 阶段 2：余额快照 —— 取 MT4 balance/freeMargin；失败关闭（不得在无快照下继续）。
        try {
            $snapshot = $this->snapshotGateway->snapshot((int) $user->user_id);
        } catch (Throwable $exception) {
            throw new DomainException('snapshot_unavailable', 0, $exception);
        }

        // 阶段 3：同用户并发预约锁 —— GET_LOCK(1 秒超时) 防止同一用户并发提现互相挤占；
        // 锁名取自数据库名 + 用户 ID 的 SHA-256 前缀，避免跨库串锁。
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();
        $lockName = $this->reservationLockName(
            $databaseName,
            (int) $user->user_id
        );
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 1) AS acquired', [$lockName], false);
        if (!$lock || (int) $lock->acquired !== 1) {
            throw new DomainException('reservation_lock_unavailable');
        }

        try {
            try {
                // 阶段 4：事务内创建订单 —— 锁内复检幂等，然后依次校验用户规则、银行规则、
                // 金额区间、在途预留金额，最后计算结算快照并创建订单 + 结算出箱。
                return $connection->transaction(function () use ($user, $amount, $key, $snapshot): array {
                    $existing = $this->findExisting($key, (int) $user->user_id, true);
                    if ($existing) {
                        return $this->existingResult($existing, $amount);
                    }

                    // 行锁用户行后再校验，防止校验与写库之间被并发修改。
                    $lockedUser = UserInfo::query()
                        ->where('user_id', (int) $user->user_id)
                        ->lockForUpdate()
                        ->first();
                    if (!$lockedUser) {
                        throw new DomainException('withdrawal_user_not_found');
                    }

                    $config = $this->loadConfiguration();
                    $this->assertUserRules($lockedUser, $config);
                    $auth = UserAuth::query()
                        ->where('user_id', (int) $lockedUser->user_id)
                        ->lockForUpdate()
                        ->first();
                    $this->assertBankRules($auth);
                    $this->assertAmountWithinCurrentLimits($amount, $config);

                    // 可出金额 = 快照可提取额 - 在途未决申请额；不足则拒绝（失败关闭）。
                    $reserved = (string) WithdrawRecord::withTrashed()
                        ->where('user_id', (int) $lockedUser->user_id)
                        ->whereIn('funding_status', ['pending', 'processing', 'unknown', 'retryable'])
                        ->sum('apply_amount');
                    $remaining = bcsub($snapshot->available(), $reserved === '' ? '0.00' : $reserved, 2);
                    if (bccomp($remaining, $amount->toDecimalString(), 2) < 0) {
                        throw new DomainException('insufficient_balance');
                    }

                    // 计算手续费/实扣/汇率折合，并生成订单号与出箱 payload 摘要
                    // （摘要用于出箱扫描器执行时校验 payload 未被篡改）。
                    $settlement = $this->settlementSnapshot($amount, $config);
                    $localOrderNo = 'WDR' . Carbon::now('Asia/Shanghai')->format('YmdHis')
                        . Str::upper(Str::random(10));
                    $payloadHash = hash('sha256', json_encode([
                        'event_type' => 'withdraw_debit',
                        'local_order_no' => $localOrderNo,
                        'user_id' => (int) $lockedUser->user_id,
                        'amount' => $amount->toDecimalString(),
                        'fee' => $settlement['fee'],
                        'actual_amount' => $settlement['actual_amount'],
                        'exchange_rate' => $settlement['exchange_rate'],
                        'rmb_fee' => $settlement['rmb_fee'],
                        'bank_no' => trim((string) $auth->bank_no),
                        'bank_name' => trim((string) $auth->bank_name),
                        'bank_addr' => trim((string) $auth->bank_addr),
                    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                    // 落订单与结算出箱（withdraw_debit），出箱由扫描器驱动实际扣款。
                    $order = $this->createOrder([
                        'user_id' => $lockedUser->user_id,
                        'user_name' => $lockedUser->user_name,
                        'apply_amount' => $amount->toDecimalString(),
                        'actual_amount' => $settlement['actual_amount'],
                        'fee' => $settlement['fee'],
                        'exchange_rate' => $settlement['exchange_rate'],
                        'rmb_fee' => $settlement['rmb_fee'],
                        'bank_no' => trim((string) $auth->bank_no),
                        'bank_name' => trim((string) $auth->bank_name),
                        'bank_addr' => trim((string) $auth->bank_addr),
                        'status' => 0,
                        'local_order_no' => $localOrderNo,
                        'reject_reason' => '',
                        'idempotency_key' => $key,
                        'funding_status' => 'pending',
                        'funding_payload_hash' => $payloadHash,
                        'created_by' => $lockedUser->user_name,
                    ]);

                    WithdrawSettlementOutbox::create([
                        'withdraw_record_id' => $order->id,
                        'local_order_no' => $localOrderNo,
                        'event_type' => 'withdraw_debit',
                        'status' => 'pending',
                        'attempts' => 0,
                        'payload_hash' => $payloadHash,
                        'available_at' => time(),
                    ]);

                    return ['order' => $order, 'created' => true];
                }, 3);
            } catch (QueryException $exception) {
                // 幂等唯一键并发冲突：说明另一个请求已创建同键订单，读取后返回既有记录；
                // 非幂等冲突的错误原样抛出，不得吞掉。
                if (!$this->isIdempotencyUniqueViolation($exception)) {
                    throw $exception;
                }

                $existing = $this->findExisting($key, (int) $user->user_id, false);
                if (!$existing) {
                    throw $exception;
                }

                return $this->existingResult($existing, $amount);
            }
        } finally {
            // 阶段 5：释放预约锁 —— 无论成功失败都必须释放；释放失败时记日志并断开连接兜底，
            // 避免长连接上锁残留影响后续请求。
            $this->releaseReservationLock($connection, $databaseName, $lockName);
        }
    }

    /**
     * 释放预约锁；释放未确认时记日志并按需断开连接兜底。
     *
     * 失败关闭语义：释放结果必须是明确的 1，否则认为锁仍被持有；
     * 清理动作本身失败只记日志，绝不影响提现结果或主异常。
     *
     * @param \Illuminate\Database\Connection $connection 当前数据库连接。
     * @param string $databaseName 数据库名（日志上下文）。
     * @param string $lockName 预约锁名。
     * @return void
     */
    private function releaseReservationLock(
        $connection,
        string $databaseName,
        string $lockName
    ): void {
        try {
            if ($this->lockReleaser === null) {
                $result = $connection->selectOne(
                    'SELECT RELEASE_LOCK(?) AS released',
                    [$lockName],
                    false
                );
            } else {
                $result = ($this->lockReleaser)($connection, $lockName);
            }

            if (!is_object($result)
                || !property_exists($result, 'released')) {
                throw new LogicException('Reservation lock release was not confirmed.');
            }

            $released = $result->released === '1' ? 1 : $result->released;
            if ($released !== 1) {
                throw new LogicException('Reservation lock release was not confirmed.');
            }
        } catch (Throwable $exception) {
            $this->writeLockCleanupLog('withdrawal.reservation_lock_release_failed', [
                'lock_hash' => hash('sha256', $lockName),
                'database' => $databaseName,
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);

            try {
                // 事务击穿护栏：默认连接仍处于挂起事务时禁止 disconnect——断开会隐式回滚
                // 挂起事务且 Laravel 的 $transactions 计数器不重置，后续写入将在无事务连接上
                // 自动提交（测试环境表现为夹具永久污染）。锁残留改由日志暴露，连接保持存活。
                if ($connection->transactionLevel() > 0) {
                    $this->writeLockCleanupLog('withdrawal.reservation_lock_disconnect_skipped_active_transaction', [
                        'lock_hash' => hash('sha256', $lockName),
                        'database' => $databaseName,
                        'transaction_level' => $connection->transactionLevel(),
                    ]);
                } elseif ($this->lockDisconnector === null) {
                    $connection->disconnect();
                } else {
                    ($this->lockDisconnector)($connection);
                }
            } catch (Throwable $disconnectException) {
                $this->writeLockCleanupLog('withdrawal.reservation_lock_disconnect_failed', [
                    'lock_hash' => hash('sha256', $lockName),
                    'database' => $databaseName,
                    'exception_class' => get_class($disconnectException),
                    'exception_message' => $disconnectException->getMessage(),
                ]);
            }
        }
    }

    /**
     * 记录锁清理失败日志；日志系统自身失败时降级到 error_log。
     *
     * 清理流程必须不抛异常：任何失败都不允许替换提现结果或主异常。
     *
     * @param string $message 日志消息。
     * @param array<string, string> $context 日志上下文（锁名只记录哈希，不记录原始键）。
     * @return void
     */
    /** @param array<string, string> $context */
    private function writeLockCleanupLog(string $message, array $context): void
    {
        try {
            Log::error($message, $context);
        } catch (Throwable $loggingException) {
            try {
                error_log($message . ' logging failed: ' . get_class($loggingException));
            } catch (Throwable $ignored) {
                // Cleanup must never replace the withdrawal result or primary exception.
            }
        }
    }

    /**
     * 幂等重放：查询已存在的提现记录。
     *
     * @param UserInfo $user 提现用户。
     * @param Money $amount 提现金额，用于比对已有记录金额是否一致。
     * @param string $key 幂等键。
     *
     * @return array{order: WithdrawRecord, created: bool}|null 找到则返回已有记录，未找到返回 null。
     */
    /** @return array{order: WithdrawRecord, created: bool}|null */
    public function replayExisting(UserInfo $user, Money $amount, string $key): ?array
    {
        $existing = $this->findExisting($key, (int) $user->user_id, false);

        return $existing ? $this->existingResult($existing, $amount) : null;
    }

    /**
     * 加载提现相关系统配置；任一必需键缺失即失败关闭。
     *
     * @return array<string, string> 键值均为字符串的配置表。
     * @throws DomainException 配置键缺失时抛出。
     */
    /** @return array<string, string> */
    private function loadConfiguration(): array
    {
        $keys = [
            'withdrawal_enabled',
            'withdrawal_weekend_enabled',
            'withdrawal_start_time',
            'withdrawal_end_time',
            'withdraw_min_amount',
            'withdraw_max_amount',
            'withdraw_risk_rate_limit',
            'withdraw_check_open',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'withdraw_exchange_rate_cny',
        ];
        // 可选键：缺失时按默认值兜底，不参与上面的必填校验。
        // withdrawal_fee_enabled 是后加的手续费总开关（2026_08_30_000001 迁移写入）；
        // 若把它列为必填，尚未执行该迁移的库会因缺键而使全部出金失败关闭 —— 那是拿可用性
        // 换配置完整性，代价不对等。因此缺键时默认 '1'（扣费），与迁移前的既有行为完全一致。
        $optionalDefaults = [
            'withdrawal_fee_enabled' => '1',
        ];

        $rows = SystemConfig::query()
            ->whereIn('key', array_merge($keys, array_keys($optionalDefaults)))
            ->get()
            ->keyBy('key');
        $config = [];
        foreach ($keys as $key) {
            if (!$rows->has($key)) {
                throw new DomainException('withdrawal_configuration_invalid');
            }
            $config[$key] = (string) $rows->get($key)->value;
        }
        foreach ($optionalDefaults as $key => $default) {
            $config[$key] = $rows->has($key) ? (string) $rows->get($key)->value : $default;
        }

        return $config;
    }

    /**
     * 校验用户提现资格：全局开关、用户禁止提现标记、实名状态、提现时段、风险率与持仓检查。
     *
     * 风险率语义：风险率必须为 0 或 >= 上限，介于 0 与上限之间视为高风险拒绝。
     *
     * @param UserInfo $user 已行锁的用户记录。
     * @param array<string, string> $config 提现配置表。
     * @return void
     * @throws DomainException 任一资格校验不通过时抛出。
     */
    /** @param array<string, string> $config */
    private function assertUserRules(UserInfo $user, array $config): void
    {
        $globalEnabled = $this->binaryConfig($config['withdrawal_enabled']);
        if ($globalEnabled === 0 || (int) $user->is_withdrawal_allowed !== 0) {
            throw new DomainException('withdrawal_disabled');
        }
        if ((int) $user->auth_status !== 1) {
            throw new DomainException('identity_not_approved');
        }

        $this->assertWithdrawalTime($config);

        try {
            $riskLimit = $this->normalizeUnsignedDecimal(
                $config['withdraw_risk_rate_limit'],
                8,
                10
            );
            $riskRatio = $this->normalizeUnsignedDecimal((string) $user->risk_ratio, 8, 10);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('withdrawal_configuration_invalid', 0, $exception);
        }
        if (bccomp($riskRatio, '0.00000000', 8) > 0
            && bccomp($riskRatio, $riskLimit, 8) < 0) {
            throw new DomainException('risk_rate_exceeded');
        }

        if ($this->binaryConfig($config['withdraw_check_open']) === 1
            && UserTrade::query()->where('user_id', (int) $user->user_id)->open()->exists()) {
            throw new DomainException('open_positions');
        }
    }

    /**
     * 校验银行信息：实名/银行卡须已审核通过，且出金所需的卡号、户名、开户行三项齐全。
     *
     * @param UserAuth|null $auth 用户实名/银行卡记录（可能缺失）。
     * @return void
     * @throws DomainException 任一条件不满足时抛出。
     */
    private function assertBankRules(UserAuth $auth = null): void
    {
        if (!$auth || (int) $auth->id_card_status !== 2) {
            throw new DomainException('identity_not_approved');
        }
        if ((int) $auth->bank_status !== 2) {
            throw new DomainException('bank_not_approved');
        }
        if (trim((string) $auth->bank_no) === ''
            || trim((string) $auth->bank_name) === ''
            || trim((string) $auth->bank_addr) === '') {
            throw new DomainException('bank_snapshot_incomplete');
        }
    }

    /**
     * 校验提现金额落在配置区间内；区间本身非法（最小>最大）也失败关闭。
     *
     * @param Money $amount 提现金额。
     * @param array<string, string> $config 提现配置表。
     * @return void
     * @throws DomainException 配置非法或金额越界时抛出。
     */
    /** @param array<string, string> $config */
    private function assertAmountWithinCurrentLimits(Money $amount, array $config): void
    {
        try {
            $minimum = $this->normalizeUnsignedDecimal($config['withdraw_min_amount'], 2, 16);
            $maximum = $this->normalizeUnsignedDecimal($config['withdraw_max_amount'], 2, 16);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('withdrawal_configuration_invalid', 0, $exception);
        }
        if (bccomp($minimum, '0.00', 2) <= 0 || bccomp($minimum, $maximum, 2) > 0) {
            throw new DomainException('withdrawal_configuration_invalid');
        }
        if (bccomp($amount->toDecimalString(), $minimum, 2) < 0
            || bccomp($amount->toDecimalString(), $maximum, 2) > 0) {
            throw new DomainException('invalid_amount');
        }
    }

    /**
     * 计算提现结算快照：固定费 + 比例费（按比例 10 位精度、保留 3 位后四舍五入到 2 位），
     * 实扣金额与人民币费用；任何配置非法或费用不小于申请金额都失败关闭。
     *
     * @param Money $amount 提现金额。
     * @param array<string, string> $config 提现配置表。
     * @return array{fee: string, actual_amount: string, exchange_rate: string, rmb_fee: string}
     * @throws DomainException 配置非法或费用不小于金额时抛出。
     */
    /**
     * @param array<string, string> $config
     * @return array{fee: string, actual_amount: string, exchange_rate: string, rmb_fee: string}
     */
    private function settlementSnapshot(Money $amount, array $config): array
    {
        try {
            $fixedFee = $this->normalizeUnsignedDecimal(
                $config['withdrawal_fixed_fee_usd'],
                2,
                16
            );
            $feeRate = $this->normalizeUnsignedDecimal($config['withdrawal_fee_rate'], 8, 10);
            $exchangeRate = $this->normalizeUnsignedDecimal(
                $config['withdraw_exchange_rate_cny'],
                8,
                10
            );
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('withdrawal_configuration_invalid', 0, $exception);
        }
        if (bccomp($exchangeRate, '0.00000000', 8) <= 0) {
            throw new DomainException('withdrawal_configuration_invalid');
        }

        // 手续费总开关：关闭时把固定费与费率一并视为 0，而不是跳过整段计算。
        // 这样做的三个理由：
        // 1) 后续的 fee < amount 校验、actual_amount 与 rmb_fee 推导仍走同一条路径，
        //    不出现「开关开」与「开关关」两套互不相干的算术分支；
        // 2) 原配置值原样保留在 system_configs 中，重新开启即恢复既有费率，运营无需记录旧值；
        // 3) 落库快照里的 fee/rmb_fee 明确为 0.00，而非 null 或缺字段，
        //    下游报表与旧信封的合计口径不受开关状态影响。
        // 取值判定用严格字符串比较 '1'：configs 是 key/value 文本表，
        // 用 (bool) 会把 '0' 之外的任意非空串（含 'false'、'off'）都判成开启。
        $feeEnabled = (string) ($config['withdrawal_fee_enabled'] ?? '1') === '1';
        if (!$feeEnabled) {
            $fixedFee = '0.00';
            $feeRate = '0.00000000';
        }

        $percentageFee = bcdiv(
            bcmul($amount->toDecimalString(), $feeRate, 10),
            '100',
            3
        );
        $fee = $this->roundHalfUp(bcadd($fixedFee, $percentageFee, 3));
        try {
            $fee = $this->normalizeUnsignedDecimal($fee, 2, 16);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('withdrawal_configuration_invalid', 0, $exception);
        }
        if (bccomp($fee, $amount->toDecimalString(), 2) >= 0) {
            throw new DomainException('fee_not_less_than_amount');
        }

        $actualAmount = bcsub($amount->toDecimalString(), $fee, 2);
        $rmbFee = $this->roundHalfUp(bcmul($fee, $exchangeRate, 3));
        try {
            $rmbFee = $this->normalizeUnsignedDecimal($rmbFee, 2, 16);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('withdrawal_configuration_invalid', 0, $exception);
        }

        return [
            'fee' => $fee,
            'actual_amount' => $actualAmount,
            'exchange_rate' => $exchangeRate,
            'rmb_fee' => $rmbFee,
        ];
    }

    /**
     * 校验提现时段：周末（周六/周日）默认不可提现；起止时间同时配置时按当天窗口判断，
     * 跨零点（start > end）视为次日凌晨窗口。
     *
     * @param array<string, string> $config 提现配置表。
     * @return void
     * @throws DomainException 时段配置非法或当前不在可提现时段时抛出。
     */
    /** @param array<string, string> $config */
    private function assertWithdrawalTime(array $config): void
    {
        $weekendEnabled = $this->binaryConfig($config['withdrawal_weekend_enabled']);
        $now = Carbon::now('Asia/Shanghai');
        if ($weekendEnabled === 0 && $now->dayOfWeekIso >= 6) {
            throw new DomainException('withdrawal_time_unavailable');
        }

        $start = trim($config['withdrawal_start_time']);
        $end = trim($config['withdrawal_end_time']);
        if ($start === '' && $end === '') {
            return;
        }
        if ($start === '' || $end === '') {
            throw new DomainException('withdrawal_configuration_invalid');
        }
        $start = $this->normalizeTime($start);
        $end = $this->normalizeTime($end);
        $current = $now->format('H:i:s');
        $allowed = $start <= $end
            ? $current >= $start && $current <= $end
            : $current >= $start || $current <= $end;
        if (!$allowed) {
            throw new DomainException('withdrawal_time_unavailable');
        }
    }

    /**
     * 解析 0/1 二进制配置；其他值视为配置损坏并失败关闭。
     *
     * @param string $value 配置原始值。
     * @return int 0 或 1。
     * @throws DomainException 值不是 "0"/"1" 时抛出。
     */
    private function binaryConfig(string $value): int
    {
        if ($value !== '0' && $value !== '1') {
            throw new DomainException('withdrawal_configuration_invalid');
        }

        return (int) $value;
    }

    /**
     * 规范化无符号小数字符串：限定位数与整数位数，避免配置脏值进入 BCMath 计算。
     *
     * @param string $value 原始字符串。
     * @param int $scale 允许的小数位数。
     * @param int $maximumWholeDigits 允许的最大整数位数。
     * @return string 规范化的两位（按 scale 补齐）小数字符串。
     * @throws InvalidArgumentException 格式非法或位数超限时抛出。
     */
    private function normalizeUnsignedDecimal(string $value, int $scale, int $maximumWholeDigits): string
    {
        if (!preg_match('/^[0-9]+(?:\.[0-9]{1,' . $scale . '})?$/', $value)) {
            throw new InvalidArgumentException('Configuration must be a plain unsigned decimal string.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }
        if (strlen($whole) > $maximumWholeDigits) {
            throw new InvalidArgumentException('Configuration exceeds its DECIMAL range.');
        }

        return $whole . '.' . str_pad($fraction, $scale, '0');
    }

    /**
     * 规范化时间配置：接受 HH:mm 或 HH:mm:ss，统一为 HH:mm:ss 格式。
     *
     * @param string $value 配置原始时间。
     * @return string 规范化时间。
     * @throws DomainException 格式非法时抛出。
     */
    private function normalizeTime(string $value): string
    {
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $value)) {
            throw new DomainException('withdrawal_configuration_invalid');
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    /**
     * 四舍五入到两位小数：BCMath 没有 round，用加 0.005 后截位实现。
     *
     * @param string $value 三位小数金额。
     * @return string 两位小数金额。
     */
    private function roundHalfUp(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    /**
     * 生成预约锁名：数据库名 + 用户 ID 的 SHA-256 前缀（48 字符），
     * 避免锁名携带可读用户信息，并防止跨库同锁名串锁。
     *
     * @param string $database 当前数据库名。
     * @param int $userId 用户 ID。
     * @return string 锁名。
     */
    private function reservationLockName(string $database, int $userId): string
    {
        return 'wdr:reserve:' . substr(hash('sha256', $database . ':' . $userId), 0, 48);
    }

    /**
     * 按幂等键查找提现记录（含软删除）；可选行锁用于事务内复检。
     *
     * @param string $key 幂等键。
     * @param int $userId 用户 ID。
     * @param bool $lock 是否加行锁。
     * @return WithdrawRecord|null 命中返回记录，否则返回 null。
     */
    private function findExisting(string $key, int $userId, bool $lock): ?WithdrawRecord
    {
        $query = WithdrawRecord::withTrashed()
            ->where('idempotency_key', $key)
            ->where('user_id', $userId);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /**
     * 构造幂等命中结果；软删除订单或金额不一致时拒绝返回（失败关闭）。
     *
     * @param WithdrawRecord $existing 已存在的提现记录。
     * @param Money $amount 本次请求金额（用于一致性比对）。
     * @return array{order: WithdrawRecord, created: bool} created 恒为 false。
     * @throws DomainException 记录已软删除或金额与本次请求不一致时抛出。
     */
    /** @return array{order: WithdrawRecord, created: bool} */
    private function existingResult(WithdrawRecord $existing, Money $amount): array
    {
        if ($existing->trashed()) {
            throw new DomainException('Idempotency key belongs to a soft-deleted withdrawal order.');
        }
        if ((string) $existing->apply_amount !== $amount->toDecimalString()) {
            throw new DomainException('Idempotency key was already used with a different amount.');
        }

        return ['order' => $existing, 'created' => false];
    }

    /**
     * 创建提现订单；注入的 orderCreator 便于测试替换，返回值类型不符时失败关闭。
     *
     * @param array<string, mixed> $attributes 订单字段。
     * @return WithdrawRecord 已创建的订单。
     * @throws LogicException 注入创建器返回非 WithdrawRecord 时抛出。
     */
    /** @param array<string, mixed> $attributes */
    private function createOrder(array $attributes): WithdrawRecord
    {
        if ($this->orderCreator === null) {
            return WithdrawRecord::create($attributes);
        }

        $order = ($this->orderCreator)($attributes);
        if ($order instanceof WithdrawRecord) {
            return $order;
        }

        throw new LogicException('Withdrawal order creator must return a WithdrawRecord.');
    }

    /**
     * 判断 QueryException 是否为幂等唯一键冲突（按驱动区分错误码与约束名）。
     *
     * @param QueryException $exception 待判断异常。
     * @return bool 是幂等唯一键冲突为 true；未知驱动返回 false（原样抛出）。
     */
    private function isIdempotencyUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?: ($exception->getPrevious()->errorInfo ?? []);
        $sqlState = (string) ($errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = $exception->getMessage();
        $constraint = 'withdraw_records_idempotency_user_unique';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return $sqlState === '23000'
                && $driverCode === 1062
                && strpos($message, $constraint) !== false;
        }
        if ($driver === 'sqlite') {
            $columns = 'withdraw_records.idempotency_key, withdraw_records.user_id';

            return in_array($sqlState, ['23000', 'HY000'], true)
                && in_array($driverCode, [19, 2067], true)
                && (strpos($message, $constraint) !== false || strpos($message, $columns) !== false);
        }
        if ($driver === 'pgsql') {
            return $sqlState === '23505' && strpos($message, $constraint) !== false;
        }

        return false;
    }
}
