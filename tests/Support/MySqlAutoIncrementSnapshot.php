<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 23:53
 */

/**
 * MySqlAutoIncrementSnapshot
 *
 * 文件功能：
 * - 为真实 MySQL 夹具捕获并恢复指定表的 AUTO_INCREMENT 值：restore 按捕获值还原自增计数，保证同批用例重复执行时主键区间一致。
 * - 调用方必须在捕获、写入、清理和恢复窗口内持有 schema 锁或建议锁；现存 ID 占用目标区间时恢复失败关闭，绝不强行降低自增值制造主键冲突。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 捕获并恢复隔离 MySQL 夹具表的 AUTO_INCREMENT。
 *
 * 调用方必须在完整捕获、写入、清理和恢复窗口内持有 schema 锁或建议锁；
 * 现存 ID 已占用目标区间时失败关闭，禁止强行降低自增值制造后续主键冲突。
 */
final class MySqlAutoIncrementSnapshot
{
    /**
     * 表名 => 捕获时的 AUTO_INCREMENT 值映射。restore() 按它把自增值还原到夹具写入前，
     * 保证同一批用例重复执行时主键区间一致；已有行占用目标区间时恢复必须失败关闭。
     *
     * @var array<string, int|null>
     */
    private $values;

    private function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * 捕获指定表当前的 AUTO_INCREMENT。
     *
     * @param array<int, string> $tables 仅允许字母、数字和下划线组成的表名列表。
     * @return self 可在夹具清理后执行恢复的不可变快照。
     *
     * @throws RuntimeException 表不存在、元数据无效或列表为空时抛出。
     */
    public static function capture(array $tables): self
    {
        $tables = self::normalizeTables($tables);
        self::disableStatsCache();

        $values = [];
        foreach ($tables as $table) {
            $row = DB::table('information_schema.TABLES')
                ->useWritePdo()
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->first(['AUTO_INCREMENT']);
            if ($row === null) {
                throw new RuntimeException('Unable to read AUTO_INCREMENT for ' . $table . '.');
            }
            $value = $row->AUTO_INCREMENT;
            if ($value !== null && (!is_numeric($value) || (int) $value < 1)) {
                throw new RuntimeException('Invalid AUTO_INCREMENT value for ' . $table . '.');
            }
            $values[$table] = $value === null ? null : (int) $value;
        }

        return new self($values);
    }

    /**
     * 将所有表的 AUTO_INCREMENT 恢复为捕获值。
     *
     * 单表预检或恢复失败不会阻断其他表的恢复尝试，最终统一抛出全部失败；
     * 任一表仍有 ID 占用目标区间时不会执行危险的降低操作。原始值为 NULL
     * 且表已清空时通过 TRUNCATE 恢复数据库对空表的未初始化自增状态。
     *
     * @return void 所有表恢复并复核成功时无返回值。
     *
     * @throws RuntimeException 任一表无法安全恢复或复核不一致时抛出。
     */
    public function restore(): void
    {
        $restoreFailures = [];
        try {
            self::disableStatsCache();
        } catch (\Throwable $exception) {
            $restoreFailures[] = 'stats-cache preflight: ' . $exception->getMessage();
        }

        $restores = [];
        foreach ($this->values as $table => $expected) {
            try {
                if ($expected === null) {
                    $maxId = DB::table($table)->useWritePdo()->max('id');
                    if ($maxId !== null) {
                        throw new RuntimeException(
                            'Refusing to restore NULL AUTO_INCREMENT while ' . $table . '.id rows remain.'
                        );
                    }

                    if ($this->read($table) !== null) {
                        $restores[$table] = null;
                    }
                    continue;
                }

                $maxId = DB::table($table)->useWritePdo()->max('id');
                if ($maxId !== null && (int) $maxId >= $expected) {
                    throw new RuntimeException(
                        'Refusing to lower fixture AUTO_INCREMENT below an existing '
                        . $table . '.id value.'
                    );
                }

                $actual = $this->read($table);
                if ($actual !== $expected) {
                    $restores[$table] = $expected;
                }
            } catch (\Throwable $exception) {
                $restoreFailures[] = $table . ' preflight: ' . $exception->getMessage();
            }
        }

        foreach ($restores as $table => $expected) {
            try {
                if ($expected === null) {
                    // 只有预检确认表为空时才执行，避免以 TRUNCATE 删除未知数据。
                    if (DB::table($table)->useWritePdo()->max('id') !== null) {
                        throw new RuntimeException(
                            'Refusing to truncate non-empty ' . $table . ' while restoring NULL AUTO_INCREMENT.'
                        );
                    }
                    DB::statement('TRUNCATE TABLE ' . self::quoteIdentifier($table));
                } else {
                    DB::statement(
                        'ALTER TABLE ' . self::quoteIdentifier($table) . ' AUTO_INCREMENT = ' . $expected
                    );
                }
            } catch (\Throwable $exception) {
                $restoreFailures[] = $table . ' restore: ' . $exception->getMessage();
                unset($restores[$table]);
            }
        }

        try {
            self::disableStatsCache();
        } catch (\Throwable $exception) {
            $restoreFailures[] = 'stats-cache verification: ' . $exception->getMessage();
        }

        foreach ($restores as $table => $expected) {
            try {
                $actual = $this->read($table);
                if ($actual !== $expected) {
                    throw new RuntimeException(
                        $table . ' AUTO_INCREMENT mismatch: expected '
                        . $expected . ', actual ' . $actual . '.'
                    );
                }
            } catch (\Throwable $exception) {
                $restoreFailures[] = $table . ' verification: ' . $exception->getMessage();
            }
        }

        if ($restoreFailures !== []) {
            throw new RuntimeException(
                'AUTO_INCREMENT restore failures: ' . implode(' | ', $restoreFailures)
            );
        }
    }

    /** @return array<string, int|null> */
    public function values(): array
    {
        return $this->values;
    }

    private function read(string $table): ?int
    {
        $row = DB::table('information_schema.TABLES')
            ->useWritePdo()
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->first(['AUTO_INCREMENT']);
        if ($row === null) {
            throw new RuntimeException('Unable to read AUTO_INCREMENT for ' . $table . '.');
        }
        if ($row->AUTO_INCREMENT === null) {
            return null;
        }
        if (!is_numeric($row->AUTO_INCREMENT) || (int) $row->AUTO_INCREMENT < 1) {
            throw new RuntimeException('Invalid AUTO_INCREMENT value for ' . $table . '.');
        }

        return (int) $row->AUTO_INCREMENT;
    }

    private static function disableStatsCache(): void
    {
        DB::statement('SET SESSION information_schema_stats_expiry = 0');
    }

    /** @param array<int, string> $tables @return array<int, string> */
    private static function normalizeTables(array $tables): array
    {
        $normalized = [];
        foreach ($tables as $table) {
            self::assertIdentifier($table);
            if (!in_array($table, $normalized, true)) {
                $normalized[] = $table;
            }
        }
        if ($normalized === []) {
            throw new RuntimeException('AUTO_INCREMENT snapshot requires at least one table.');
        }

        return $normalized;
    }

    private static function quoteIdentifier(string $identifier): string
    {
        self::assertIdentifier($identifier);

        return '`' . $identifier . '`';
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Unsafe MySQL identifier: ' . $identifier);
        }
    }
}
