<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/22
 * Time: 00:09
 */

declare(strict_types=1);

/**
 * 开发库全量备份脚本（Phase 7 迁移演练前置）。
 *
 * 用法：php scripts/php-db-backup.php <database> <output-file>
 *
 * 安全边界：
 * - 只读连接，对源库零写入。
 * - 输出为可重放的 schema+data SQL（含 CREATE TABLE 与逐行 INSERT）。
 */

if ($argc < 3) {
    fwrite(STDERR, "用法：php php-db-backup.php <database> <output-file>\n");
    exit(1);
}

$database = $argv[1];
$output = $argv[2];

$pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', '123456', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 白名单校验数据库名，防止误传任意目标。
$allowed = ['co_crmv5', 'hank_zl_data', 'co_crmv5_test'];
if (!in_array($database, $allowed, true)) {
    fwrite(STDERR, "数据库不在白名单：{$database}\n");
    exit(1);
}

$stmt = $pdo->prepare(
    "SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_type='BASE TABLE' ORDER BY table_name"
);
$stmt->execute([$database]);
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($tables === []) {
    fwrite(STDERR, "目标库没有基表：{$database}\n");
    exit(1);
}

$handle = fopen($output, 'w');
if ($handle === false) {
    fwrite(STDERR, "无法创建输出文件：{$output}\n");
    exit(1);
}

fwrite($handle, "-- 备份目标库：{$database}\n");
fwrite($handle, '-- 备份时间：' . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($handle, "SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'STRICT_TRANS_TABLES', ''), 'STRICT_ALL_TABLES', '');\n\n");

$totalRows = 0;
foreach ($tables as $table) {
    $createStmt = $pdo->query("SHOW CREATE TABLE `{$database}`.`{$table}`")->fetch(PDO::FETCH_NUM);
    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($handle, $createStmt[1] . ";\n");

    $rows = $pdo->query("SELECT * FROM `{$database}`.`{$table}`");
    $batch = [];
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $values = array_map(static function ($value) use ($pdo): string {
            return $value === null ? 'NULL' : $pdo->quote((string) $value);
        }, $row);
        $batch[] = '(' . implode(',', $values) . ')';
        if (count($batch) >= 200) {
            fwrite($handle, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
            $totalRows += count($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        fwrite($handle, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
        $totalRows += count($batch);
    }
    fwrite($handle, "\n");
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

$sizeMb = round((float) filesize($output) / 1048576, 2);
echo "备份完成：{$output}（表 " . count($tables) . "，行 {$totalRows}，{$sizeMb} MB）" . PHP_EOL;
