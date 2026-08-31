<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 修复 MySqlAutoIncrementSnapshotTest 方法名的历史一次性脚本。
 *
 * 文件功能：
 * - 将 docblock 语义与方法名错位的测试方法名恢复为正确名称（含匿名类对照表）。
 *
 * 适用场景：
 * - 仅历史修复用，当前代码无需再执行。
 */

// 恢复 MySqlAutoIncrementSnapshotTest 的方法名（docblock 语义 + 匿名类对照表）。
$f = 'tests/Unit/MySqlAutoIncrementSnapshotTest.php';
$c = file_get_contents($f);

$replacements = [
    // 主测试类 4 个测试方法（按 docblock 语义）
    ['public function (): void', 'public function test_restore_rolls_back_auto_increment_after_row_deletion(): void', 1],
    ['public function (): void', 'public function test_restore_fails_closed_when_max_id_reaches_snapshot(): void', 2],
    ['public function (): void', 'public function test_null_snapshot_fails_closed_on_change(): void', 3],
    ['public function (): void', 'public function test_restore_continues_other_tables_after_blocked_table(): void', 4],
    // 私有辅助方法
    ['private function (AutoIncrementState $state): void', 'private function mockDatabase(AutoIncrementState $state): void', 1],
    // AutoIncrementState 匿名类方法
    ['public function (array $autoIncrements, array $maxIds)', 'public function __construct(array $autoIncrements, array $maxIds)', 1],
    ['public function (string $table): ?int', 'public function autoIncrement(string $table): ?int', 1],
    ['public function (string $table): ?int', 'public function maxId(string $table): ?int', 2],
    ['public function (string $table, int $value = null): void', 'public function setAutoIncrement(string $table, int $value = null): void', 1],
    ['public function (): array', 'public function tables(): array', 1],
    // AutoIncrementQuery 匿名类方法
    ['public function (AutoIncrementState $state, string $table)', 'public function __construct(AutoIncrementState $state, string $table)', 1],
    ['public function (string $column, $value): self', 'public function where(string $column, $value): self', 1],
    ['public function (): self', 'public function useWritePdo(): self', 1],
    ['public function (array $columns = [\'*\']): ?object', 'public function first(array $columns = [\'*\']): ?object', 1],
    ['public function (string $column): ?int', 'public function max(string $column): ?int', 1],
];

$changed = 0;
foreach ($replacements as [$old, $new, $occurrences]) {
    $pos = 0;
    for ($i = 0; $i < $occurrences; $i++) {
        $found = strpos($c, $old, $pos);
        if ($found === false) {
            echo "NOT FOUND: $old (occurrence " . ($i + 1) . ")\n";
            break;
        }
        $c = substr($c, 0, $found) . $new . substr($c, $found + strlen($old));
        $pos = $found + strlen($new);
        $changed++;
    }
}
file_put_contents($f, $c);
echo "替换: $changed 处\n";
