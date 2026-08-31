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
 * 充值幂等键索引迁移契约测试。
 *
 * 文件功能：
 * - 校验迁移类 HardenDepositIdempotencyPerUser 的 assertKnownIdempotencyIndexes 只接受精确的规范索引与已知旧索引定义。
 * - 校验任何未知索引、列序错误、前缀长度或函数表达式索引都会抛 RuntimeException。
 *
 * 适用场景：
 * - 改动充值幂等键相关迁移（2026_07_19_000005_harden_deposit_idempotency_per_user.php）后回归。
 *
 * 入参例子：
 * - 已知索引：deposit_records_idempotency_user_unique(idempotency_key, user_id) 唯一索引。
 * - 非法索引：payment_idempotency_lookup、规范名加前缀长度、旧名加 gateway_code 错序等。
 *
 * 返回值：断言通过表示索引白名单校验契约成立。
 *
 * 异常或失败场景：
 * - 出现未知索引或已知索引的定义漂移时抛 RuntimeException，测试断言异常消息包含索引名。
 */
namespace Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class DepositIdempotencyIndexContractTest extends TestCase
{
    /**
     * 规范唯一索引名 (idempotency_key, user_id)。白名单校验契约以它为唯一允许的新索引；
     * 测试断言迁移在存在该索引时跳过重建、缺列时拒绝降级。
     */
    private const TARGET_INDEX = 'deposit_records_idempotency_user_unique';

    /**
     * 旧唯一索引名 (idempotency_key, user_id, gateway_code)。仍属白名单但仅允许“存在则保留”语义，
     * 不允许迁移重建或变更其定义——与 TARGET_INDEX 的差异是多出 gateway_code 维度的历史弱约束。
     */
    private const LEGACY_INDEX = 'deposit_records_idempotency_user_gateway_unique';

    /**
     * @dataProvider invalidIndexProvider
     * 校验迁移拒绝一切涉及幂等键的未知索引定义。
     *
     * @param string $name 索引名。
     * @param array $columns 索引列。
     * @param bool $unique 是否唯一索引。
     * @param array $prefixLengths 各列前缀长度，默认空。
     * @param array $expressions 函数索引表达式，默认空。
     * @return void 断言通过不返回值。
     */
    public function test_migration_rejects_every_unknown_index_involving_idempotency_key(
        string $name,
        array $columns,
        bool $unique,
        array $prefixLengths = [],
        array $expressions = []
    ): void {
        $migration = $this->migration();
        $method = new ReflectionMethod($migration, 'assertKnownIdempotencyIndexes');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($name);
        $method->invoke($migration, collect([
            $name => $this->indexRows($columns, $unique, $prefixLengths, $expressions),
        ]));
    }

    public function invalidIndexProvider(): array
    {
        return [
            'unknown non-unique full index' => [
                'payment_idempotency_lookup',
                ['idempotency_key', 'user_id'],
                false,
            ],
            'unknown non-unique prefix index' => [
                'payment_idempotency_prefix_lookup',
                ['idempotency_key', 'user_id'],
                false,
                [32, null],
            ],
            'unknown non-unique wrong order' => [
                'payment_user_idempotency_lookup',
                ['user_id', 'idempotency_key'],
                false,
            ],
            'unknown unique canonical columns' => [
                'payment_idempotency_shadow_unique',
                ['idempotency_key', 'user_id'],
                true,
            ],
            'canonical name with prefix columns' => [
                self::TARGET_INDEX,
                ['idempotency_key', 'user_id'],
                true,
                [48, null],
            ],
            'legacy name with wrong order' => [
                self::LEGACY_INDEX,
                ['user_id', 'idempotency_key', 'gateway_code'],
                true,
            ],
            'functional index expression' => [
                'payment_idempotency_expression_lookup',
                [null],
                false,
                [null],
                ['lower(`idempotency_key`)'],
            ],
        ];
    }

    /**
     * 校验迁移只接受精确的规范索引、已知旧索引和本地订单号唯一索引。
     *
     * @return void 断言通过不返回值。
     */
    public function test_migration_accepts_only_exact_canonical_and_known_legacy_definitions(): void
    {
        $migration = $this->migration();
        $method = new ReflectionMethod($migration, 'assertKnownIdempotencyIndexes');
        $method->setAccessible(true);

        $method->invoke($migration, collect([
            self::TARGET_INDEX => $this->indexRows(['idempotency_key', 'user_id'], true),
            self::LEGACY_INDEX => $this->indexRows(
                ['idempotency_key', 'user_id', 'gateway_code'],
                true
            ),
            'deposit_records_local_order_no_unique' => $this->indexRows(['local_order_no'], true),
        ]));

        $this->addToAssertionCount(1);
    }

    private function migration()
    {
        require_once dirname(__DIR__, 2)
            . '/database/migrations/2026_07_19_000005_harden_deposit_idempotency_per_user.php';

        return new \HardenDepositIdempotencyPerUser();
    }

    private function indexRows(
        array $columns,
        bool $unique,
        array $prefixLengths = [],
        array $expressions = []
    ): Collection {
        return collect(array_map(static function ($column, int $offset) use (
            $unique,
            $prefixLengths,
            $expressions
        ): object {
            return (object) [
                'Column_name' => $column,
                'Non_unique' => $unique ? 0 : 1,
                'Sub_part' => $prefixLengths[$offset] ?? null,
                'Expression' => $expressions[$offset] ?? null,
            ];
        }, $columns, array_keys($columns)));
    }
}
