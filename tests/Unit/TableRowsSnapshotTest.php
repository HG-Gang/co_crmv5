<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:11
 */

declare(strict_types=1);

/**
 * 数据表行快照单元测试。
 *
 * 文件功能：
 * - 校验 TableRowsSnapshot 捕获指定键的行并在 restore 时原位恢复原始行、仅删除新增键的行，且不影响无关行。
 * - 校验恢复使用写 PDO 且重复恢复幂等。
 *
 * 适用场景：
 * - 改动夹具行快照/恢复逻辑后回归。
 *
 * 入参例子：
 * - capture('system_configs', 'key', ['existing-key', 'new-key'])。
 *
 * 返回值：断言通过表示恢复后的行集与删除键完全符合预期。
 *
 * 异常或失败场景：
 * - 原始行未被原位恢复、新增键行残留、无关行被误删或重复恢复产生额外变更时失败。
 */
namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\Support\TableRowsSnapshot;
use Tests\TestCase;

final class TableRowsSnapshotTest extends TestCase
{
    /**
     * 校验恢复原位还原原始行且只删除新增键的行。
     *
     * @return void 断言通过不返回值。
     */
    public function test_restore_reinstates_original_row_in_place_and_only_removes_new_keys(): void
    {
        $store = new InMemorySnapshotRows([
            ['id' => 7, 'key' => 'existing-key', 'value' => 'before', 'updated_at' => 10],
        ]);
        DB::shouldReceive('table')->andReturnUsing(static function (string $table) use ($store): InMemorySnapshotQuery {
            return $store->query($table);
        });
        DB::shouldReceive('transaction')->andReturnUsing(static function (callable $callback) {
            return $callback();
        });

        $snapshot = TableRowsSnapshot::capture('system_configs', 'key', ['existing-key', 'new-key']);
        $store->replaceRows([
            ['id' => 9, 'key' => 'existing-key', 'value' => 'mutated', 'updated_at' => 20],
            ['id' => 10, 'key' => 'new-key', 'value' => 'created', 'updated_at' => 30],
            ['id' => 11, 'key' => 'unrelated-key', 'value' => 'untouched', 'updated_at' => 40],
        ]);

        $snapshot->restore();
        $snapshot->restore();

        $this->assertSame([
            ['id' => 7, 'key' => 'existing-key', 'value' => 'before', 'updated_at' => 10],
            ['id' => 11, 'key' => 'unrelated-key', 'value' => 'untouched', 'updated_at' => 40],
        ], $store->rows());
        $this->assertSame(['new-key'], $store->deletedKeys());
        $this->assertSame(3, $store->writePdoCalls());
    }
}

final class InMemorySnapshotRows
{
    /**
     * 内存表数据行。模拟真实库中被快照捕获的行集合，replaceRows/delete/updateOrInsert
     * 都作用其上，供 restore 结果断言使用。
     *
     * @var array<int, array<string, mixed>>
     */
    private $rows;

    /**
     * 已删除行的业务键记录。restore 的事务删除动作经由 delete() 落在这里，
     * 断言用它确认“只删了测试新增的行”。
     *
     * @var array<int, string>
     */
    private $deletedKeys = [];

    /**
     * 走写连接的调用次数。快照契约要求写入必须 useWritePdo（读写分离下保证写到主库），
     * 断言用它确认写路径确实走了写连接而非读连接。
     *
     * @var int
     */
    private $writePdoCalls = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function query(string $table): InMemorySnapshotQuery
    {
        return new InMemorySnapshotQuery($this, $table);
    }

    public function rows(): array
    {
        $rows = $this->rows;
        usort($rows, static function (array $left, array $right): int {
            return $left['id'] <=> $right['id'];
        });

        return $rows;
    }

    public function replaceRows(array $rows): void
    {
        $this->rows = $rows;
    }

    public function delete(array $filters): int
    {
        $before = count($this->rows);
        $this->rows = array_values(array_filter(
            $this->rows,
            function (array $row) use ($filters): bool {
                if (!$this->matches($row, $filters)) {
                    return true;
                }
                if (array_key_exists('key', $filters)) {
                    $this->deletedKeys[] = (string) $filters['key'];
                }

                return false;
            }
        ));

        return $before - count($this->rows);
    }

    public function updateOrInsert(array $attributes, array $values): void
    {
        foreach ($this->rows as $index => $row) {
            if ($this->matches($row, $attributes)) {
                $this->rows[$index] = array_merge($row, $values);

                return;
            }
        }

        $this->rows[] = array_merge($attributes, $values);
    }

    public function deletedKeys(): array
    {
        return $this->deletedKeys;
    }

    public function markWritePdo(): void
    {
        ++$this->writePdoCalls;
    }

    public function writePdoCalls(): int
    {
        return $this->writePdoCalls;
    }

    private function matches(array $row, array $filters): bool
    {
        foreach ($filters as $column => $value) {
            if ((string) ($row[$column] ?? '') !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}

final class InMemorySnapshotQuery
{
    /**
     * 共享的内存行存储。查询/删除/更新都作用其上，模拟 DB::table() 对真实库的效果。
     *
     * @var InMemorySnapshotRows
     */
    private $store;

    /**
     * 查询目标表名。与真实 DB::table($table) 的表名语义一致，用于区分不同内存表。
     *
     * @var string
     */
    private $table;

    /**
     * 已应用的查询条件（whereIn/where 累积）。delete/updateOrInsert 按它匹配行，
     * 复现快照 restore 的条件匹配路径。
     *
     * @var array<string, mixed>
     */
    private $filters = [];

    public function __construct(InMemorySnapshotRows $store, string $table)
    {
        $this->store = $store;
        $this->table = $table;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->filters[$column] = $values;

        return $this;
    }

    public function useWritePdo(): self
    {
        $this->store->markWritePdo();

        return $this;
    }

    public function where(string $column, $value): self
    {
        $this->filters[$column] = $value;

        return $this;
    }

    public function get()
    {
        $rows = array_filter($this->store->rows(), function (array $row): bool {
            foreach ($this->filters as $column => $value) {
                if (is_array($value)) {
                    if (!in_array((string) ($row[$column] ?? ''), array_map('strval', $value), true)) {
                        return false;
                    }
                } elseif ((string) ($row[$column] ?? '') !== (string) $value) {
                    return false;
                }
            }

            return true;
        });

        return collect(array_map(static function (array $row): object {
            return (object) $row;
        }, array_values($rows)));
    }

    public function delete(): int
    {
        return $this->store->delete($this->filters);
    }

    public function updateOrInsert(array $attributes, array $values): void
    {
        $this->store->updateOrInsert($attributes, $values);
    }
}
