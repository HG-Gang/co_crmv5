<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:12
 */

/**
 * 表行快照测试替身：按业务唯一列捕获指定行的完整数据，测试结束后还原。
 *
 * 文件功能：
 * - capture() 读取指定列值集合对应的所有行，并校验该列在结果中无重复值。
 * - restore() 在事务中删除测试新增的行，并用 updateOrInsert 还原被修改的行。
 *
 * 适用场景：
 * - 涉及真实数据库写操作的测试（如支付流程落库）回归时，恢复受影响行的原始数据。
 *
 * 入参例子：
 * - capture('deposit_records', 'idempotency_key', ['payment-task2-xxx'])。
 *
 * 返回值：
 * - capture() 返回快照对象，restore() 无返回值；restore() 成功即表示数据已还原。
 *
 * 失败场景：
 * - 指定列在结果中出现重复值时抛 RuntimeException（快照要求业务唯一列）；
 *   restore() 抛异常说明测试数据未能还原，需人工核对。
 */

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TableRowsSnapshot
{
    /**
     * 被快照的表名。capture/restore 都以它为操作对象，标识符格式已在 capture 时校验防注入。
     *
     * @var string
     */
    private $table;

    /**
     * 业务唯一列名（如 idempotency_key）。快照按该列匹配行，capture 时校验其结果无重复值；
     * 非唯一列会让 restore 的 updateOrInsert 还原到错误的行。
     *
     * @var string
     */
    private $column;

    /**
     * 参与快照的业务键值集合。restore() 只处理这些键对应的行，测试新增/修改的数据范围由此收敛。
     *
     * @var array<int, string|int>
     */
    private $values;

    /**
     * 捕获的原始行数据（业务键 => 行数组）。restore() 在事务中删除多余行并 updateOrInsert
     * 还原被改行，保证真实数据库写测试不留下脏数据。
     *
     * @var array<string|int, array<string, mixed>>
     */
    private $rows;

    private function __construct(string $table, string $column, array $values, array $rows)
    {
        $this->table = $table;
        $this->column = $column;
        $this->values = $values;
        $this->rows = $rows;
    }

    public static function capture(string $table, string $column, array $values): self
    {
        $rows = DB::table($table)
            ->useWritePdo()
            ->whereIn($column, $values)
            ->get()
            ->map(static function (object $row): array {
                return (array) $row;
            })
            ->all();

        $counts = array_count_values(array_map(static function (array $row) use ($column): string {
            return (string) ($row[$column] ?? '');
        }, $rows));
        foreach ($counts as $count) {
            if ($count > 1) {
                throw new RuntimeException(
                    'TableRowsSnapshot requires a unique business column: ' . $table . '.' . $column
                );
            }
        }

        return new self($table, $column, $values, $rows);
    }

    public function restore(): void
    {
        $rowsByValue = [];
        foreach ($this->rows as $row) {
            $rowsByValue[(string) $row[$this->column]] = $row;
        }

        DB::transaction(function () use ($rowsByValue): void {
            foreach ($this->values as $value) {
                $key = (string) $value;
                if (!array_key_exists($key, $rowsByValue)) {
                    DB::table($this->table)->where($this->column, $value)->delete();
                    continue;
                }

                DB::table($this->table)->useWritePdo()->updateOrInsert(
                    [$this->column => $value],
                    $rowsByValue[$key]
                );
            }
        });
    }
}
