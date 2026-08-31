<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 12:11
 */

/**
 * MySqlTableFingerprint
 *
 * 文件功能：
 * - 捕获指定表的可比指纹（行数、内容摘要、存储引擎、忽略自增值后的建表语句摘要），用于证明测试前后没有业务行或表结构残留变化；自增值漂移由 MySqlAutoIncrementSnapshot 独立保障。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 捕获隔离 MySQL 夹具表的持久数据与结构指纹。
 *
 * 指纹包含行数、内容摘要、存储引擎和忽略当前自增值后的建表语句摘要，
 * 用于证明测试前后没有业务行或表结构残留变化。
 *
 * 自增值（AUTO_INCREMENT）不纳入指纹：MySQL 8.0.12 在夹具快照恢复后，同一会话内
 * 两次读取 information_schema.TABLES.AUTO_INCREMENT 可能出现 ±1 的陈旧视图差异
 * （2026-08-28 全量串行与 2026-08-29 本地复现：行数、内容摘要、结构哈希完全一致，
 * 仅该字段漂移），属于服务器元数据间歇性滞后而非业务残留。自增计数器由
 * MySqlAutoIncrementSnapshot::restore() 的捕获-预检-复核门禁独立保障。
 */
final class MySqlTableFingerprint
{
    /**
     * 捕获指定表的完整可比指纹。
     *
     * @param array<int, string> $tables 仅允许字母、数字和下划线组成的表名列表。
     * @return array<string, array<string, int|string|null>> 以表名为键的稳定指纹。
     *
     * @throws RuntimeException 表不存在、元数据缺失或标识符不安全时抛出。
     */
    public static function capture(array $tables): array
    {
        $tables = self::normalizeTables($tables);
        DB::statement('SET SESSION information_schema_stats_expiry = 0');
        $fingerprint = [];

        foreach ($tables as $table) {
            $metadata = DB::table('information_schema.TABLES')
                ->useWritePdo()
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->first(['AUTO_INCREMENT', 'ENGINE']);
            if ($metadata === null) {
                throw new RuntimeException('Unable to read metadata for ' . $table . '.');
            }

            $contentDigest = strcasecmp((string) $metadata->ENGINE, 'MyISAM') === 0
                ? 'checksum:' . self::myIsamChecksum($table)
                : 'rows:' . self::digestRows(
                    DB::table($table)->useWritePdo()->orderBy('id')->cursor()
                );

            $createSql = self::createSql($table);
            $fingerprint[$table] = [
                'row_count' => (int) DB::table($table)->useWritePdo()->count(),
                'content_digest' => $contentDigest,
                'engine' => (string) $metadata->ENGINE,
                'structure_hash' => hash(
                    'sha256',
                    (string) preg_replace('/AUTO_INCREMENT\s*=\s*\d+/i', 'AUTO_INCREMENT=?', $createSql)
                ),
            ];
        }

        return $fingerprint;
    }

    /**
     * 按行顺序和列名排序生成稳定内容摘要。
     *
     * @param iterable<int, object|array<string, mixed>> $rows 已按稳定主键顺序读取的行。
     * @return string SHA-256 十六进制摘要。
     *
     * @throws \JsonException 行内容无法编码为 JSON 时抛出。
     */
    public static function digestRows(iterable $rows): string
    {
        $context = hash_init('sha256');
        foreach ($rows as $row) {
            $values = (array) $row;
            ksort($values, SORT_STRING);
            $encoded = json_encode(
                $values,
                JSON_PRESERVE_ZERO_FRACTION
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );
            hash_update($context, strlen($encoded) . ':' . $encoded . ';');
        }

        return hash_final($context);
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
            throw new RuntimeException('Table fingerprint requires at least one table.');
        }

        return $normalized;
    }

    private static function createSql(string $table): string
    {
        $row = DB::selectOne(
            'SHOW CREATE TABLE ' . self::quoteIdentifier($table),
            [],
            false
        );
        if ($row === null) {
            throw new RuntimeException('Unable to inspect ' . $table . ' structure.');
        }

        foreach ((array) $row as $value) {
            if (preg_match('/^\s*CREATE\s+TABLE\b/i', (string) $value) === 1) {
                return (string) $value;
            }
        }

        throw new RuntimeException('SHOW CREATE TABLE returned no definition for ' . $table . '.');
    }

    private static function myIsamChecksum(string $table): string
    {
        $row = DB::selectOne(
            'CHECKSUM TABLE ' . self::quoteIdentifier($table),
            [],
            false
        );
        $values = $row ? array_values((array) $row) : [];
        if (!array_key_exists(1, $values) || $values[1] === null) {
            throw new RuntimeException('Unable to checksum ' . $table . '.');
        }

        return (string) $values[1];
    }

    private static function quoteIdentifier(string $identifier): string
    {
        self::assertIdentifier($identifier);

        return chr(96) . $identifier . chr(96);
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Unsafe MySQL identifier: ' . $identifier);
        }
    }
}
