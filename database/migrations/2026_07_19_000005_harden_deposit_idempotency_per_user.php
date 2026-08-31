<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 加固充值幂等性：按用户维度限制重复幂等键。
 *
 * 文件功能：
 * - 调整/新增幂等约束，保证同一用户同一幂等键只能成功入账一次。
 *
 * 字段语义：
 * - 涉及 deposit_records 索引与数据规范化；回滚保留加固约束（资金安全不可逆）。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenDepositIdempotencyPerUser extends Migration
{
    /**
     * 加固操作的充值订单表名。建索引、查重与驱动断言都以该表为对象，
     * 集中定义避免 SQL 拼接处出现不一致的字面量。
     */
    private const TABLE = 'deposit_records';

    /**
     * 目标唯一索引名：(idempotency_key, user_id)。把幂等唯一性收窄到“同一用户同一键”，
     * 修复旧三列索引允许不同用户复用同一幂等键的漏洞；资金安全约束，down() 不回滚。
     */
    private const TARGET_INDEX = 'deposit_records_idempotency_user_unique';

    /**
     * 旧唯一索引名：(idempotency_key, user_id, gateway_code)。含 gateway_code 维度，
     * 允许同一用户同一键跨网关重复下单，是本迁移要移除的弱约束；up() 中存在即删除。
     */
    private const LEGACY_INDEX = 'deposit_records_idempotency_user_gateway_unique';

    /**
     * 目标索引的列清单。建索引后按此逐列核对，防止 MySQL 静默把索引建错列；
     * 列序（idempotency_key 在前）与幂等查询前缀匹配。
     */
    private const TARGET_COLUMNS = ['idempotency_key', 'user_id'];

    /**
     * 旧索引的列清单。用于识别存量库中旧索引的形状，确认命中后才允许删除，
     * 避免误删名称相同但列不同的其他索引。
     */
    private const LEGACY_COLUMNS = ['idempotency_key', 'user_id', 'gateway_code'];

    public function up()
    {
        $this->assertMySqlContract();
        $this->assertNoDuplicateUserKeys();

        $indexes = $this->indexes();
        $this->assertKnownIdempotencyIndexes($indexes);
        $target = $indexes->get(self::TARGET_INDEX, collect());
        $legacy = $indexes->get(self::LEGACY_INDEX, collect());

        if ($target->isEmpty()) {
            DB::statement(
                'ALTER TABLE ' . self::TABLE
                . ' ADD UNIQUE INDEX `' . self::TARGET_INDEX . '` (`idempotency_key`, `user_id`)'
            );
            $target = $this->indexes()->get(self::TARGET_INDEX, collect());
            if (!$this->matchesIndex($target, self::TARGET_COLUMNS, true)) {
                throw new RuntimeException(
                    'Failed to verify canonical deposit idempotency index ' . self::TARGET_INDEX . '.'
                );
            }
        }

        if (!$legacy->isEmpty()) {
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' DROP INDEX `' . self::LEGACY_INDEX . '`'
            );
        }
    }

    public function down()
    {
        // Per-user payment identity is a forward-only financial safety guarantee.
    }

    private function assertMySqlContract(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Deposit idempotency hardening requires MySQL.');
        }
        if (!Schema::hasTable(self::TABLE)) {
            throw new RuntimeException('Cannot harden deposit idempotency: deposit_records is missing.');
        }
        foreach (['user_id', 'idempotency_key', 'gateway_code'] as $column) {
            if (!Schema::hasColumn(self::TABLE, $column)) {
                throw new RuntimeException(
                    'Cannot harden deposit idempotency: deposit_records.' . $column . ' is missing.'
                );
            }
        }

        $engine = DB::table('information_schema.TABLES')->useWritePdo()
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->value('ENGINE');
        if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
            throw new RuntimeException('Cannot harden deposit idempotency: deposit_records must use InnoDB.');
        }

        $idempotencyColumn = DB::table('information_schema.COLUMNS')->useWritePdo()
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('COLUMN_NAME', 'idempotency_key')
            ->first();
        if ($idempotencyColumn === null
            || strtolower((string) $idempotencyColumn->DATA_TYPE) !== 'varchar'
            || (int) $idempotencyColumn->CHARACTER_MAXIMUM_LENGTH !== 100) {
            throw new RuntimeException(
                'Cannot harden deposit idempotency: idempotency_key must be VARCHAR(100).'
            );
        }
    }

    private function assertNoDuplicateUserKeys(): void
    {
        $duplicate = DB::table(self::TABLE)->useWritePdo()
            ->select('user_id', 'idempotency_key', DB::raw('COUNT(*) AS duplicate_count'))
            ->whereNotNull('idempotency_key')
            ->groupBy('user_id', 'idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot harden deposit idempotency: duplicate user/idempotency keys exist.'
            );
        }
    }

    /** @param Collection<string, Collection<int, object>> $indexes */
    private function assertKnownIdempotencyIndexes(Collection $indexes): void
    {
        foreach ($indexes as $name => $rows) {
            if ($name === self::TARGET_INDEX) {
                if (!$this->matchesIndex($rows, self::TARGET_COLUMNS, true)) {
                    throw new RuntimeException('Unknown index definition for ' . self::TARGET_INDEX . '.');
                }
                continue;
            }

            if ($name === self::LEGACY_INDEX) {
                if (!$this->matchesIndex($rows, self::LEGACY_COLUMNS, true)) {
                    throw new RuntimeException('Unknown index definition for ' . self::LEGACY_INDEX . '.');
                }
                continue;
            }

            if (!$this->involvesIdempotencyKey($rows)) {
                continue;
            }

            throw new RuntimeException('Unknown deposit idempotency index: ' . $name . '.');
        }
    }

    private function involvesIdempotencyKey(Collection $rows): bool
    {
        foreach ($rows as $row) {
            if (strcasecmp((string) ($row->Column_name ?? ''), 'idempotency_key') === 0) {
                return true;
            }

            if (preg_match(
                '/(?<![A-Za-z0-9_])idempotency_key(?![A-Za-z0-9_])/i',
                (string) ($row->Expression ?? '')
            ) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $columns */
    private function matchesIndex(Collection $rows, array $columns, bool $unique): bool
    {
        return !$rows->isEmpty()
            && $rows->pluck('Column_name')->values()->all() === $columns
            && (int) $rows->first()->Non_unique === ($unique ? 0 : 1)
            && $rows->pluck('Sub_part')->filter(static function ($part): bool {
                return $part !== null;
            })->isEmpty();
    }

    /** @return Collection<string, Collection<int, object>> */
    private function indexes(): Collection
    {
        return collect(DB::select('SHOW INDEX FROM ' . self::TABLE, [], false))
            ->sortBy('Seq_in_index')
            ->groupBy('Key_name');
    }
}
