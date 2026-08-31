<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 14:42
 */

/**
 * 验证库（co_crmv5_verify）维护脚本。
 *
 * 文件功能：
 * - 清理被中断测试留下的夹具残留（system_configs 夹具描述行、user_infos 今日夹具行）。
 * - 确保 11 个出金生产配置键存在（值取自 2026-07-15 迁移的权威默认值）。
 *
 * 适用场景：
 * - 全量/分批回归前调用，避免被环境杀进程的测试把共享配置行删掉后级联失败。
 *
 * 入参例子：
 * - 无参数：默认维护 co_crmv5_verify。
 * - 可选参数 1：目标库名（默认 co_crmv5_verify）。
 *
 * 返回值：
 * - 输出清理/补齐的行数与最终状态；非零退出码表示失败。
 */

$database = $argv[1] ?? 'co_crmv5_verify';

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3307',
    'root',
    '123456',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec('USE `' . $database . '`');

// 清理已知夹具描述行（保留生产配置行）。
$fixtureDescriptions = [
    'Withdrawal Task 2 fixture',
    'Payment Task 3 fixture',
    'Legacy form intent fixture',
    'Transaction cleanup AUTO_INCREMENT sentinel',
];
$deletedConfig = 0;
foreach ($fixtureDescriptions as $description) {
    $deletedConfig += (int) $pdo->exec(
        'DELETE FROM system_configs WHERE description = ' . $pdo->quote($description)
    );
}

// 清理今日创建的夹具用户行（真实历史数据 created_at 为旧时间戳，不受影响）。
$deletedInfos = (int) $pdo->exec(
    'DELETE FROM user_infos WHERE created_at >= 1785550000'
);

// 确保 11 个出金生产配置键存在；缺失时按迁移默认值插入。
$defaults = [
    'withdrawal_enabled' => '0',
    'withdrawal_weekend_enabled' => '0',
    'withdrawal_start_time' => '',
    'withdrawal_end_time' => '',
    'withdraw_min_amount' => '50',
    'withdraw_max_amount' => '50000',
    'withdraw_risk_rate_limit' => '50',
    'withdraw_check_open' => '1',
    'withdrawal_fee_rate' => '0',
    'withdrawal_fixed_fee_usd' => '0',
    'withdraw_exchange_rate_cny' => '7.05',
];
$now = time();
$inserted = 0;
foreach ($defaults as $key => $value) {
    $exists = $pdo->query(
        'SELECT id FROM system_configs WHERE `key` = ' . $pdo->quote($key)
    )->fetchColumn();
    if ($exists !== false) {
        continue;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO system_configs'
        . ' (`key`,`value`,`group`,`description`,`created_at`,`updated_at`,`deleted_at`)'
        . ' VALUES (?,?,?,?,?,?,NULL)'
    );
    $stmt->execute([
        $key,
        $value,
        'finance',
        'Required withdrawal config added by 2026-07-15 migration: ' . $key,
        $now,
        $now,
    ]);
    $inserted++;
}

$fixtureLeft = (int) $pdo->query(
    "SELECT COUNT(*) FROM system_configs WHERE description LIKE '%fixture%'"
)->fetchColumn();
$configCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM system_configs WHERE `key` LIKE 'withdrawal_%' OR `key` LIKE 'withdraw_%'"
)->fetchColumn();

echo 'deleted_config_fixture=' . $deletedConfig
    . ' deleted_today_infos=' . $deletedInfos
    . ' inserted_withdraw_config=' . $inserted
    . ' fixture_left=' . $fixtureLeft
    . ' withdraw_keys=' . $configCount
    . PHP_EOL;
