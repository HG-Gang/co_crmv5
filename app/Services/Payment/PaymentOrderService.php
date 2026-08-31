<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Support\Money;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Closure;

/**
 * 支付订单创建服务。
 *
 * 文件功能：
 * - 根据用户、支付通道、金额创建充值订单。
 * - 通过幂等键保证重复请求不产生重复订单。
 * - 自动生成本地订单号和渠道标识。
 *
 * 适用场景：
 * - 前端 / 后台发起用户充值请求时调用。
 * - 需要支持多支付通道（TigerPay / WpPay / Exlink 等）的统一订单创建入口。
 *
 * 入参例子：
 * - user: UserInfo 实例（包含 user_id / user_name）。
 * - channel: ['code' => 'tiger', 'name' => 'TigerPay', 'exchange_rate' => '7.25', 'currency' => 'CNY', '_config' => [...]]。
 * - amount: Money::fromDecimalString('100.00', ...)。
 * - idempotencyKey: 'deposit_req_a1b2c3'。
 *
 * 返回值：
 * - ['order' => DepositRecord, 'created' => true]：新创建的订单。
 * - ['order' => DepositRecord, 'created' => false]：幂等命中已有订单。
 *
 * 异常或失败场景：
 * - DomainException('idempotency_conflict')：幂等键已存在但金额或网关不一致。
 * - LogicException：自定义 orderCreator 未返回 DepositRecord。
 * - QueryException：正常数据库异常抛出，幂等唯一约束异常内部处理重试。
 */
class PaymentOrderService
{
    /**
     * 幂等键唯一索引名清单：新索引 (idempotency_key, user_id) 与旧索引 (idempotency_key, user_id, gateway_code)。
     * 捕获唯一约束异常时按两个索引分别识别幂等冲突——新库只有新索引，历史库可能仍带旧索引，
     * 清单缺项会把并发重复下单误判为普通数据库异常。
     *
     * @var array<int, string>
     */
    private const IDEMPOTENCY_UNIQUE_CONSTRAINTS = [
        'deposit_records_idempotency_user_unique',
        'deposit_records_idempotency_user_gateway_unique',
    ];

    /**
     * 自定义订单创建回调（接收字段数组返回 DepositRecord），未注入时为 null 表示使用默认 DepositRecord::create。
     * 仅测试替身与特殊落库场景注入；正常链路必须走默认创建以保证字段口径一致。
     *
     * @var Closure|null
     */
    private $orderCreator;

    /**
     * 构造支付订单创建服务。
     *
     * @param callable|null $orderCreator 自定义订单创建回调（接收字段数组并返回 DepositRecord）；
     *                                    未注入时使用默认 DepositRecord::create。
     */
    public function __construct(callable $orderCreator = null)
    {
        $this->orderCreator = $orderCreator === null ? null : Closure::fromCallable($orderCreator);
    }

    /**
     * 创建或检索支付订单。
     *
     * 通过幂等键保证同一请求不会重复创建订单。先查已有订单，存在则比对参数后返回已有订单；
     * 不存在则在事务中创建新订单，捕获唯一约束异常做二次检测。
     *
     * @param UserInfo $user 发起充值的用户信息。
     * @param array<string, mixed> $channel 支付通道配置，必含 code / name / exchange_rate / currency / _config。
     * @param Money $amount 充值金额。
     * @param string $idempotencyKey 幂等键，由调用方生成，用于防止重复下单。
     *
     * @return array{order: DepositRecord, created: bool} created 为 true 表示新创建订单。
     *
     * @throws DomainException 幂等冲突或参数非法时抛出。
     */
    public function createOrRetrieve(
        UserInfo $user,
        array $channel,
        Money $amount,
        string $idempotencyKey
    ): array {
        try {
            // 事务 + 幂等键：同一用户同一请求只产生一张订单；行锁保证并发请求串行化。
            return DB::transaction(function () use ($user, $channel, $amount, $idempotencyKey) {
                $gateway = (string) $channel['code'];
                // 先查已有订单（含软删除、带行锁），命中则走比对返回，不重复创建。
                $existing = $this->findExisting($idempotencyKey, (int) $user->user_id, true);

                if ($existing) {
                    return $this->existingResult($existing, $amount, $gateway);
                }

                // 生成本地订单号：时间戳 + 10 位随机大写字母，唯一性由数据库唯一约束兜底。
                $localOrderNo = 'DEP' . date('YmdHis') . Str::upper(Str::random(10));
                // 实际支付金额 = 用户金额 × 渠道汇率；币种保持字符串运算，全程不经过浮点。
                $actualAmount = $amount->multiplyByRate((string) $channel['exchange_rate']);
                $config = is_array($channel['_config'] ?? null) ? $channel['_config'] : [];
                // 商户号优先取 merchant_id，缺省回退 app_id，兼容不同渠道的配置键名。
                $merchantId = trim((string) ($config['merchant_id'] ?? ''));
                if ($merchantId === '') {
                    $merchantId = trim((string) ($config['app_id'] ?? ''));
                }
                $currency = strtoupper((string) $channel['currency']);
                // USD/USDT 以用户原币作为供应商金额（不再乘汇率），其余币种用换算后金额。
                $providerAmount = in_array($currency, ['USD', 'USDT'], true)
                    ? $amount->toDecimalString()
                    : $actualAmount;
                $attributes = [
                    'user_id' => $user->user_id,
                    'user_name' => $user->user_name,
                    'amount' => $amount->toDecimalString(),
                    'actual_amount' => $actualAmount,
                    'provider_amount' => $providerAmount,
                    'exchange_rate' => (string) $channel['exchange_rate'],
                    'channel_name' => $channel['name'],
                    'local_order_no' => $localOrderNo,
                    'idempotency_key' => $idempotencyKey,
                    'gateway_code' => $channel['code'],
                    'merchant_id' => $merchantId,
                    'currency' => $currency,
                    'payment_status' => 'pending',
                    'settlement_status' => 'pending',
                    'status' => '01',
                    'remarks' => 'DBUN-' . $user->user_id . '-#' . $localOrderNo . ';pay_channel=' . $channel['code'],
                    'created_by' => $user->user_name,
                ];
                $order = $this->createOrder($attributes);

                return ['order' => $order, 'created' => true];
            }, 3);
        } catch (QueryException $exception) {
            // 唯一约束冲突只处理幂等约束：并发下先提交的那笔胜出，另一笔转二次查询。
            if (!$this->isIdempotencyUniqueViolation($exception)) {
                throw $exception;
            }

            $gateway = (string) $channel['code'];
            $existing = $this->findExisting($idempotencyKey, (int) $user->user_id, false);
            if (!$existing) {
                // 冲突但查不到订单（约束被其他维度触发），原样抛出交给上层处理。
                throw $exception;
            }

            return $this->existingResult($existing, $amount, $gateway);
        }
    }

    /**
     * 按幂等键查找已有订单；含软删除记录，防止已删除订单的幂等键被复用。
     *
     * @param string $key 幂等键。
     * @param int $userId 用户 ID，幂等键的用户维度。
     * @param bool $lock 是否加行锁（事务内首次查询使用）。
     * @return DepositRecord|null 找到的订单。
     */
    private function findExisting(string $key, int $userId, bool $lock): ?DepositRecord
    {
        $query = DepositRecord::withTrashed()
            ->where('idempotency_key', $key)
            ->where('user_id', $userId);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /**
     * 幂等命中处理：订单已删除或金额/网关不一致时抛出幂等冲突。
     *
     * @param DepositRecord $existing 已存在的订单。
     * @param Money $amount 本次请求金额。
     * @param string $gateway 本次请求网关码。
     * @return array{order: DepositRecord, created: bool} created 恒为 false。
     * @throws DomainException 订单已删除或参数与已有订单不一致时抛出。
     */
    private function existingResult(DepositRecord $existing, Money $amount, string $gateway): array
    {
        // 已软删除的订单不再返回给调用方，防止对已废弃订单继续操作。
        if ($existing->trashed()) {
            throw new DomainException('idempotency_conflict');
        }
        // 同一幂等键必须对应相同金额与网关，否则视为两个不同意图的请求，拒绝复用。
        if ((string) $existing->amount !== $amount->toDecimalString()
            || !hash_equals((string) $existing->gateway_code, $gateway)) {
            throw new DomainException('idempotency_conflict');
        }

        return ['order' => $existing, 'created' => false];
    }

    /**
     * 创建订单记录；可通过 orderCreator 替换默认创建逻辑（用于测试与审计）。
     *
     * @param array<string, mixed> $attributes 订单字段。
     * @return DepositRecord 已创建的订单。
     * @throws \LogicException 自定义 orderCreator 未返回 DepositRecord 时抛出。
     */
    private function createOrder(array $attributes): DepositRecord
    {
        if ($this->orderCreator === null) {
            return DepositRecord::create($attributes);
        }

        $order = ($this->orderCreator)($attributes);
        if ($order instanceof DepositRecord) {
            return $order;
        }

        throw new \LogicException('Payment order creator must return a DepositRecord.');
    }

    /**
     * 判断查询异常是否为幂等键唯一约束冲突。
     *
     * 不同驱动错误码与消息格式不同：MySQL 23000/1062，SQLite 23000/HY000（19/2067），
     * PostgreSQL 23505；统一从中提取约束名或冲突列做比对。
     *
     * @param QueryException $exception 待判定异常。
     * @return bool 是幂等约束冲突返回 true。
     */
    private function isIdempotencyUniqueViolation(QueryException $exception): bool
    {
        $previous = $exception->getPrevious();
        $errorInfo = $exception->errorInfo ?: ($previous->errorInfo ?? []);
        $sqlState = (string) ($errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = trim((string) ($errorInfo[2] ?? ''));
        if ($message === '' && $previous !== null) {
            $message = $previous->getMessage();
        }
        $driver = DB::connection()->getDriverName();
        $constraintMatched = $driver === 'mysql'
            ? $this->mysqlMessageNamesKnownConstraint($message)
            : $this->messageContainsAny($message, self::IDEMPOTENCY_UNIQUE_CONSTRAINTS);

        if ($driver === 'mysql') {
            return $sqlState === '23000'
                && $driverCode === 1062
                && $constraintMatched;
        }

        if ($driver === 'sqlite') {
            $columnsMatched = $this->messageContainsAny($message, [
                'deposit_records.idempotency_key, deposit_records.user_id',
                'deposit_records.idempotency_key, deposit_records.user_id, deposit_records.gateway_code',
                'deposit_records.user_id, deposit_records.idempotency_key',
                'deposit_records.user_id, deposit_records.idempotency_key, deposit_records.gateway_code',
            ]);

            return in_array($sqlState, ['23000', 'HY000'], true)
                && in_array($driverCode, [19, 2067], true)
                && ($constraintMatched || $columnsMatched);
        }

        if ($driver === 'pgsql') {
            return $sqlState === '23505' && $constraintMatched;
        }

        return false;
    }

    /**
     * 从 MySQL 唯一约束冲突消息中提取约束名并判断是否为幂等约束。
     *
     * 兼容带引号、反引号与无引号三种约束名写法，并去掉可能存在的库名前缀。
     *
     * @param string $message 数据库异常消息文本。
     * @return bool 约束名命中幂等唯一约束列表时返回 true。
     */
    private function mysqlMessageNamesKnownConstraint(string $message): bool
    {
        if (preg_match(
            '/\bfor key\s+(?:(\'|"|`)([^\'"`]+)\1|([A-Za-z0-9_$.-]+))/i',
            $message,
            $matches
        ) !== 1) {
            return false;
        }

        $identity = ($matches[2] ?? '') !== '' ? $matches[2] : ($matches[3] ?? '');
        $separator = strrpos($identity, '.');
        if ($separator !== false) {
            $identity = substr($identity, $separator + 1);
        }

        return in_array($identity, self::IDEMPOTENCY_UNIQUE_CONSTRAINTS, true);
    }

    /**
     * 判断消息文本是否包含任一目标子串（SQLite/PostgreSQL 冲突消息匹配用）。
     *
     * @param string $message 待匹配的消息文本。
     * @param array<int, string> $needles 候选子串列表。
     * @return bool 命中任意一个子串时返回 true。
     */
    private function messageContainsAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
