<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/22
 * Time: 00:11
 */

declare(strict_types=1);

/**
 * SQL 文件执行器（mysqli multi_query）。
 *
 * 用法：php scripts/php-sql-execute.php <sql-file>
 *
 * 安全边界：
 * - 仅允许执行 database/sql/full_reset_and_migrate.sql（白名单），防止误执行任意脚本。
 * - 任一语句失败立即停止并输出错误位置，不继续半执行。
 */

if ($argc < 2) {
    fwrite(STDERR, "用法：php php-sql-execute.php <sql-file>\n");
    exit(1);
}

$sqlFile = realpath($argv[1]);
$allowed = realpath(dirname(__DIR__) . '/database/sql/full_reset_and_migrate.sql');
if ($sqlFile === false || $sqlFile !== $allowed) {
    fwrite(STDERR, "只允许执行白名单迁移文件：full_reset_and_migrate.sql\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "SQL 文件为空或不可读。\n");
    exit(1);
}

$mysqli = new mysqli('127.0.0.1', 'root', '123456', 'co_crmv5', (int) 3307);
if ($mysqli->connect_error) {
    fwrite(STDERR, '连接失败：' . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

if (!$mysqli->multi_query($sql)) {
    fwrite(STDERR, '执行失败于首个语句：' . $mysqli->error . "\n");
    exit(1);
}

$statementCount = 0;
do {
    while ($mysqli->more_results() && $mysqli->next_result()) {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        ++$statementCount;
    }
    if ($mysqli->more_results()) {
        if (!$mysqli->next_result()) {
            break;
        }
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        ++$statementCount;
    } else {
        break;
    }
} while (true);

if ($mysqli->errno) {
    fwrite(STDERR, "执行中断（已执行约 {$statementCount} 条语句）：" . $mysqli->error . "\n");
    exit(1);
}

echo "迁移 SQL 执行完成，语句数约 {$statementCount}。" . PHP_EOL;
$mysqli->close();
