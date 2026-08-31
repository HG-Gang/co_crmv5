<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 23:52
 */

declare(strict_types=1);

/**
 * MySQL 自增值快照单元测试。
 *
 * 文件功能：
 * - 校验 MySqlAutoIncrementSnapshot 捕获并恢复各夹具表的 AUTO_INCREMENT。
 * - 校验现有行 ID 到达原始自增值时 fail-closed，并允许空表的 NULL 自增值安全恢复。
 *
 * 适用场景：
 * - 改动 MySQL 夹具自增值快照/恢复逻辑后回归。
 *
 * 入参例子：
 * - capture(['user_logins', 'user_infos']) 捕获自增值与最大 ID；restore() 将自增值回退到捕获值。
 *
 * 返回值：断言通过表示自增值精确回退到原始值。
 *
 * 异常或失败场景：
 * - 自增值需降到现有 ID 之下时抛 RuntimeException；空表的 NULL 自增值通过清空恢复。
 */
namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\TestCase;

final class MySqlAutoIncrementSnapshotTest extends TestCase
{
    /**
     * 校验恢复时各夹具表自增值回退到删除行后的原始值。
     *
     * @return void 断言通过不返回值。
     */
    public function test_restore_rolls_back_auto_increment_after_row_deletion(): void
    {
        $state = new AutoIncrementState([
            'user_logins' => 10,
            'user_infos' => 20,
        ], [
            'user_logins' => 5,
            'user_infos' => 15,
        ]);
        $this->mockDatabase($state);

        $snapshot = MySqlAutoIncrementSnapshot::capture(['user_logins', 'user_infos']);
        $state->setAutoIncrement('user_logins', 12);
        $state->setAutoIncrement('user_infos', 22);

        $snapshot->restore();

        $this->assertSame(10, $state->autoIncrement('user_logins'));
        $this->assertSame(20, $state->autoIncrement('user_infos'));
    }

    /**
     * 校验现有行 ID 达到原始自增值时恢复 fail-closed。
     *
     * @return void 断言通过不返回值。
     */
    public function test_restore_fails_closed_when_max_id_reaches_snapshot(): void
    {
        $state = new AutoIncrementState(['deposit_records' => 10], ['deposit_records' => 10]);
        $this->mockDatabase($state);
        $snapshot = MySqlAutoIncrementSnapshot::capture(['deposit_records']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to lower fixture AUTO_INCREMENT');
        $snapshot->restore();
    }

    /**
     * 校验原始自增值为 NULL 的空表在夹具写入并清理后可恢复为 NULL。
     *
     * @return void 断言通过不返回值。
     */
    public function test_null_snapshot_restores_after_fixture_rows_are_removed(): void
    {
        $state = new AutoIncrementState(['legacy_table' => null], ['legacy_table' => null]);
        $this->mockDatabase($state);
        $snapshot = MySqlAutoIncrementSnapshot::capture(['legacy_table']);
        $state->setAutoIncrement('legacy_table', 5);

        $snapshot->restore();

        $this->assertNull($state->autoIncrement('legacy_table'));
    }

    /**
     * 校验单表预检失败后仍继续恢复其他表，并在最后汇总抛错。
     *
     * @return void 断言通过不返回值。
     */
    public function test_restore_continues_other_tables_after_blocked_table(): void
    {
        $state = new AutoIncrementState([
            'blocked_table' => 10,
            'restorable_table' => 20,
        ], [
            'blocked_table' => 10,
            'restorable_table' => 15,
        ]);
        $this->mockDatabase($state);
        $snapshot = MySqlAutoIncrementSnapshot::capture(['blocked_table', 'restorable_table']);
        $state->setAutoIncrement('blocked_table', 12);
        $state->setAutoIncrement('restorable_table', 22);

        try {
            $snapshot->restore();
            $this->fail('Expected an aggregated AUTO_INCREMENT restore failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('blocked_table', $exception->getMessage());
        }

        $this->assertSame(12, $state->autoIncrement('blocked_table'));
        $this->assertSame(20, $state->autoIncrement('restorable_table'));
    }

    private function mockDatabase(AutoIncrementState $state): void
    {
        DB::shouldReceive('getDatabaseName')->andReturn('test');
        DB::shouldReceive('statement')->andReturnUsing(static function (string $sql) use ($state): void {
            if (preg_match('/ALTER TABLE `([^`]+)` AUTO_INCREMENT = (\d+)/', $sql, $matches) === 1) {
                $state->setAutoIncrement($matches[1], (int) $matches[2]);
            } elseif (preg_match('/TRUNCATE TABLE `([^`]+)`/', $sql, $matches) === 1) {
                $state->setAutoIncrement($matches[1], null);
            }
        });
        DB::shouldReceive('table')->andReturnUsing(static function (string $table) use ($state): AutoIncrementQuery {
            return new AutoIncrementQuery($state, $table);
        });
    }
}

final class AutoIncrementState
{
    /**
     * 内存中的表自增值状态（表名 => AUTO_INCREMENT 或 null）。模拟 information_schema 读取
     * 与 ALTER TABLE/TRUNCATE 的效果，让快照捕获/恢复逻辑在无真实 MySQL 时完全可复现。
     *
     * @var array<string, int|null>
     */
    private $autoIncrements;

    /**
     * 内存中的表当前最大主键（表名 => max(id)）。恢复时用于判定“目标自增值是否被已占用的 ID 阻挡”，
     * 模拟真实库中 restore 失败关闭的约束分支。
     *
     * @var array<string, int|null>
     */
    private $maxIds;

    public function __construct(array $autoIncrements, array $maxIds)
    {
        $this->autoIncrements = $autoIncrements;
        $this->maxIds = $maxIds;
    }

    public function autoIncrement(string $table): ?int
    {
        return $this->autoIncrements[$table] ?? null;
    }

    public function maxId(string $table): ?int
    {
        return $this->maxIds[$table] ?? null;
    }

    public function setAutoIncrement(string $table, int $value = null): void
    {
        $this->autoIncrements[$table] = $value;
    }

    /** @return array<int, string> */
    public function tables(): array
    {
        return array_keys($this->autoIncrements);
    }
}

final class AutoIncrementQuery
{
    /**
     * 共享的内存状态机。查询读取的自增值都来自它，保证 mock 与断言看到同一份数据。
     *
     * @var AutoIncrementState
     */
    private $state;

    /**
     * 查询目标表名。information_schema 查询按表名过滤，与真实 SQL 的 WHERE TABLE_NAME 语义一致。
     *
     * @var string
     */
    private $table;

    /**
     * 已应用的 where 条件（列 => 值）。目前仅支持 TABLE_NAME 过滤；记录条件以复现
     * “带条件查询 information_schema”的调用形态。
     *
     * @var array<string, mixed>
     */
    private $filters = [];

    public function __construct(AutoIncrementState $state, string $table)
    {
        $this->state = $state;
        $this->table = $table;
    }

    public function where(string $column, $value): self
    {
        $this->filters[$column] = $value;

        return $this;
    }

    public function useWritePdo(): self
    {
        return $this;
    }

    public function first(array $columns = ['*']): ?object
    {
        if ($this->table !== 'information_schema.TABLES') {
            return null;
        }

        $table = $this->tableNameFromFilters();

        return (object) ['AUTO_INCREMENT' => $this->state->autoIncrement($table)];
    }

    public function max(string $column): ?int
    {
        return $this->state->maxId($this->table);
    }

    private function tableNameFromFilters(): string
    {
        return (string) ($this->filters['TABLE_NAME'] ?? '');
    }
}
