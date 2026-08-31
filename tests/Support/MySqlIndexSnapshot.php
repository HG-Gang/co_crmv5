<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:12
 */

/**
 * MySQL 索引快照测试替身：捕获指定表的索引定义，测试结束后按快照还原表结构。
 *
 * 文件功能：
 * - capture() 通过 SHOW CREATE TABLE 解析索引定义，仅保留与指定列或指定索引名相关的索引。
 * - restore() 对比当前索引与快照，删除多余索引、重建缺失或变更的索引。
 *
 * 适用场景：
 * - 涉及索引变更（如支付相关表新增/删除索引）的迁移类测试回归时，用于清理测试副作用。
 *
 * 入参例子：
 * - capture('deposit_records', ['idempotency_key'], ['deposit_records_idempotency_user_unique'])。
 * - 表名、列名、索引名必须匹配 /^[A-Za-z0-9_]+$/，否则抛 RuntimeException（防 SQL 注入）。
 *
 * 返回值：
 * - capture() 返回快照对象，restore() 无返回值；restore() 成功即表示表结构已还原。
 *
 * 失败场景：
 * - SHOW CREATE TABLE 拿不到建表语句时抛 RuntimeException；restore() 抛异常说明
 *   测试留下了未清理的表结构变更，需人工检查后再跑后续用例。
 */

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MySqlIndexSnapshot
{
    /**
     * 被快照的表名。restore() 按它执行 SHOW CREATE TABLE 与索引变更，标识符格式已在 capture 时校验。
     *
     * @var string
     */
    private $table;

    /**
     * 关注的列名清单。只有涉及这些列的索引才会纳入快照与还原范围，
     * 与快照无关的索引（如主键）不受影响。
     *
     * @var array<int, string>
     */
    private $columns;

    /**
     * 关注的索引名清单。与列清单互补：允许按名称保护与列匹配不到的索引（如组合索引按名引用）。
     *
     * @var array<int, string>
     */
    private $names;

    /**
     * 捕获到的索引定义原文（索引名 => SHOW CREATE TABLE 片段）。restore() 逐一比对当前定义，
     * 不一致即重建，保证迁移类测试执行后表结构与快照时完全一致。
     *
     * @var array<string, string>
     */
    private $definitions;

    private function __construct(
        string $table,
        array $columns,
        array $names,
        array $definitions
    ) {
        $this->table = $table;
        $this->columns = $columns;
        $this->names = $names;
        $this->definitions = $definitions;
    }

    public static function capture(string $table, array $columns, array $names = []): self
    {
        self::assertIdentifier($table);
        foreach (array_merge($columns, $names) as $identifier) {
            self::assertIdentifier($identifier);
        }

        $all = self::indexDefinitions($table);
        $definitions = array_filter(
            $all,
            static function (string $definition, string $name) use ($columns, $names): bool {
                return in_array($name, $names, true)
                    || self::definitionInvolvesAnyColumn($definition, $columns);
            },
            ARRAY_FILTER_USE_BOTH
        );

        return new self($table, $columns, $names, $definitions);
    }

    public function restore(): void
    {
        $current = self::indexDefinitions($this->table);
        $relevantNames = array_unique(array_merge(
            $this->names,
            array_keys($this->definitions),
            array_keys(array_filter(
                $current,
                function (string $definition): bool {
                    return self::definitionInvolvesAnyColumn($definition, $this->columns);
                }
            ))
        ));

        foreach ($relevantNames as $name) {
            if (!isset($current[$name])) {
                continue;
            }
            if (isset($this->definitions[$name])
                && $current[$name] === $this->definitions[$name]) {
                continue;
            }

            DB::statement(
                'ALTER TABLE ' . self::quoteIdentifier($this->table)
                . ' DROP INDEX ' . self::quoteIdentifier($name)
            );
            unset($current[$name]);
        }

        foreach ($this->definitions as $name => $definition) {
            if (isset($current[$name]) && $current[$name] === $definition) {
                continue;
            }

            DB::statement(
                'ALTER TABLE ' . self::quoteIdentifier($this->table) . ' ADD ' . $definition
            );
        }
    }

    private static function indexDefinitions(string $table): array
    {
        $row = DB::selectOne(
            'SHOW CREATE TABLE ' . self::quoteIdentifier($table),
            [],
            false
        );
        if ($row === null) {
            throw new RuntimeException('Unable to snapshot indexes for ' . $table . '.');
        }
        $raw = (array) $row;
        $createSql = '';
        foreach ($raw as $column => $value) {
            if (strcasecmp(trim((string) $column), 'Create Table') === 0) {
                $createSql = (string) $value;
                break;
            }
        }
        if ($createSql === '') {
            foreach ($raw as $value) {
                if (preg_match('/^\s*CREATE\s+TABLE\b/i', (string) $value) === 1) {
                    $createSql = (string) $value;
                    break;
                }
            }
        }
        if ($createSql === '') {
            throw new RuntimeException('SHOW CREATE TABLE returned no definition for ' . $table . '.');
        }

        $definitions = [];
        foreach (preg_split('/\R/', $createSql) ?: [] as $line) {
            if (preg_match(
                '/^\s*((?:(?:UNIQUE|FULLTEXT|SPATIAL)\s+)?KEY\s+`((?:``|[^`])+)`\s+.+?)(?:,)?\s*$/i',
                $line,
                $matches
            ) !== 1) {
                continue;
            }

            $definitions[str_replace('``', '`', $matches[2])] = $matches[1];
        }

        return $definitions;
    }

    private static function definitionInvolvesAnyColumn(string $definition, array $columns): bool
    {
        foreach ($columns as $column) {
            if (preg_match(
                '/(?<![A-Za-z0-9_])' . preg_quote($column, '/') . '(?![A-Za-z0-9_])/i',
                $definition
            ) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Unsafe MySQL identifier: ' . $identifier);
        }
    }

    private static function quoteIdentifier(string $identifier): string
    {
        self::assertIdentifier($identifier);

        return '`' . $identifier . '`';
    }
}
