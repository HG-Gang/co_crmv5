<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:55
 */

declare(strict_types=1);

/**
 * 支付订单幂等重复分类器单元测试。
 *
 * 文件功能：
 * - 校验 PaymentOrderService::isIdempotencyUniqueViolation 对 MySQL 1062 重复键错误进行分类。
 * - 校验只认驱动错误消息中精确出现的规范/已知旧索引名，包装 SQL 提及、未知索引或子串相似索引一律不算幂等重复。
 *
 * 适用场景：
 * - 改动支付订单幂等重复判定逻辑后回归。
 *
 * 入参例子：
 * - 错误消息 “Duplicate entry 'fixture' for key 'deposit_records.deposit_records_idempotency_user_unique'” => true。
 * - 错误消息 key 为 deposit_records_local_order_no_unique => false。
 *
 * 返回值：断言通过表示分类结果与预期布尔值一致。
 *
 * 异常或失败场景：
 * - 包装 SQL 中提及规范索引、未知索引名包含规范索引子串或缺少驱动消息时误判为幂等重复则失败。
 */
namespace Tests\Unit;

use App\Services\Payment\PaymentOrderService;
use Illuminate\Database\QueryException;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class PaymentOrderServiceDuplicateClassifierTest extends TestCase
{
    /**
     * 规范唯一索引名。MySQL 1062 驱动证据中出现该精确索引名时必须判为幂等重复（createOrRetrieve 返回已有订单）。
     */
    private const TARGET_INDEX = 'deposit_records_idempotency_user_unique';

    /**
     * 旧唯一索引名。与 TARGET_INDEX 同属已知身份白名单，差别仅在列维度（多 gateway_code）；
     * 两个名字都必须被识别为幂等冲突，其余索引名一律按普通数据库异常处理。
     */
    private const LEGACY_INDEX = 'deposit_records_idempotency_user_gateway_unique';

    /**
     * @dataProvider knownIdentityProvider
     * 校验仅驱动证据中出现精确已知索引名时判定为幂等重复。
     *
     * @param string $identity 已知索引名。
     * @return void 断言通过不返回值。
     */
    public function test_mysql_1062_accepts_exact_known_identity_from_driver_evidence(string $identity): void
    {
        $exception = $this->queryException(
            "Duplicate entry 'fixture' for key 'deposit_records.{$identity}'"
        );

        $this->assertTrue($this->classify($exception));
    }

    public function knownIdentityProvider(): array
    {
        return [
            'canonical' => [self::TARGET_INDEX],
            'known legacy' => [self::LEGACY_INDEX],
        ];
    }

    /**
     * 校验规范索引只出现在包装 SQL 中时不算幂等重复。
     *
     * @return void 断言通过不返回值。
     */
    public function test_mysql_1062_ignores_canonical_identity_mentioned_only_by_wrapped_sql(): void
    {
        $exception = $this->queryException(
            "Duplicate entry 'fixture' for key 'deposit_records_local_order_no_unique'",
            'insert into deposit_records (`' . self::TARGET_INDEX . '`) values (?)'
        );

        $this->assertFalse($this->classify($exception));
    }

    /**
     * 校验包含已知索引名子串的未知键不被误判。
     *
     * @return void 断言通过不返回值。
     */
    public function test_mysql_1062_rejects_an_unknown_key_containing_a_known_identity(): void
    {
        $exception = $this->queryException(
            "Duplicate entry 'fixture' for key 'shadow_" . self::TARGET_INDEX . "'"
        );

        $this->assertFalse($this->classify($exception));
    }

    /**
     * 校验 errorInfo 无消息时回退到前驱 PDO 异常消息。
     *
     * @return void 断言通过不返回值。
     */
    public function test_mysql_1062_uses_previous_driver_message_when_error_info_has_no_message(): void
    {
        $previous = new PDOException(
            "Duplicate entry 'fixture' for key '" . self::TARGET_INDEX . "'",
            1062
        );
        $previous->errorInfo = ['23000', 1062];

        $this->assertTrue($this->classify(new QueryException('insert into deposit_records', [], $previous)));
    }

    /**
     * 校验未加引号的精确约束索引名同样被接受。
     *
     * @return void 断言通过不返回值。
     */
    public function test_mysql_1062_accepts_an_unquoted_exact_constraint_identity(): void
    {
        $exception = $this->queryException(
            'Duplicate entry \'fixture\' for key ' . self::TARGET_INDEX
        );

        $this->assertTrue($this->classify($exception));
    }

    /**
     * 校验无关的 MySQL 1062 重复键不算幂等重复。
     *
     * @return void 断言通过不返回值。
     */
    public function test_unrelated_mysql_1062_is_not_an_idempotency_duplicate(): void
    {
        $exception = $this->queryException(
            "Duplicate entry 'fixture' for key 'deposit_records_local_order_no_unique'"
        );

        $this->assertFalse($this->classify($exception));
    }

    private function classify(QueryException $exception): bool
    {
        config(['database.default' => 'mysql']);
        $method = new ReflectionMethod(PaymentOrderService::class, 'isIdempotencyUniqueViolation');
        $method->setAccessible(true);

        return $method->invoke(new PaymentOrderService(), $exception);
    }

    private function queryException(string $driverMessage, string $sql = 'insert into deposit_records'): QueryException
    {
        $previous = new PDOException($driverMessage, 1062);
        $previous->errorInfo = ['23000', 1062, $driverMessage];

        return new QueryException($sql, [], $previous);
    }
}
