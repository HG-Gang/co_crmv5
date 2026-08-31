<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 12:41
 */
/**
 * 隔离验证库克隆脚本（中文注释标准）。
 *
 * 文件功能：
 * - 将开发库 co_crmv5 的结构与数据克隆到独立验证库 co_crmv5_verify。
 * - 支持分批执行（start/count 两个参数），避免单次运行超时被沙箱中断。
 * - 仅允许操作名称精确等于 co_crmv5_verify 的库，防止误清空其他数据库。
 *
 * 入参例子：
 * - php scripts\clone_verify_db.php 0 32   # 复制前 32 张表
 * - php scripts\clone_verify_db.php 32 32  # 复制第 33~64 张表
 *
 * 返回值：
 * - 每批输出已复制的表名与总数。
 * - 目标库名不匹配时输出 SAFE_NAME_FAIL 并以退出码 1 结束。
 *
 * 失败场景：
 * - MySQL 未启动、账号密码错误、源表不存在时抛出异常。
 */

$startIndex = isset($argv[1]) ? (int) $argv[1] : 0;
$count = isset($argv[2]) ? (int) $argv[2] : 128;

$src = 'co_crmv5';
$dst = 'co_crmv5_verify';

// 安全校验：只允许克隆到专用验证库，防止误操作其他数据库。
if (!preg_match('/^co_crmv5_verify$/', $dst)) {
    fwrite(STDERR, 'SAFE_NAME_FAIL' . PHP_EOL);
    exit(1);
}

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3307;charset=utf8mb4',
    'root',
    '123456',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 60,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]
);

// 首批发起时重建目标库，保证克隆结果与源库完全一致。
if ($startIndex === 0) {
    $pdo->exec("DROP DATABASE IF EXISTS `$dst`");
    $pdo->exec("CREATE DATABASE `$dst` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
$pdo->exec("USE `$dst`");

$srcTables = $pdo->query(
    "SELECT table_name FROM information_schema.tables WHERE table_schema = '$src' ORDER BY table_name"
)->fetchAll(PDO::FETCH_COLUMN);

$copied = 0;
for ($i = $startIndex; $i < min($startIndex + $count, count($srcTables)); $i++) {
    $table = $srcTables[$i];
    $pdo->exec("CREATE TABLE `$dst`.`$table` LIKE `$src`.`$table`");
    // 显式列出非生成列，跳过 MySQL 不允许显式赋值的 generated column。
    $columns = $pdo->query(
        "SELECT column_name FROM information_schema.columns " .
        "WHERE table_schema = '$src' AND table_name = " . $pdo->quote($table) . " " .
        "AND extra NOT LIKE '%GENERATED%' ORDER BY ordinal_position"
    )->fetchAll(PDO::FETCH_COLUMN);
    $columnList = implode(',', array_map(static function (string $column): string {
        return '`' . str_replace('`', '``', $column) . '`';
    }, $columns));
    $pdo->exec(
        "INSERT INTO `$dst`.`$table` ($columnList) SELECT $columnList FROM `$src`.`$table`"
    );
    $copied++;
    echo 'COPIED:' . $table . PHP_EOL;
}

echo 'BATCH_DONE start=' . $startIndex . ' copied=' . $copied . PHP_EOL;
