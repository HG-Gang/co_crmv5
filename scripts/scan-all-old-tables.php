<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "旧库所有表结构与数据统计\n";
echo "====================================\n\n";

$oldDb = 'old_crm';

// 获取所有表名
$tables = DB::connection($oldDb)->select('SHOW TABLES');
$tableNames = array_map(function($table) {
    return array_values((array)$table)[0];
}, $tables);

sort($tableNames);

echo "总计表数: " . count($tableNames) . "\n\n";

$tableStats = [];

foreach ($tableNames as $tableName) {
    try {
        $count = DB::connection($oldDb)->table($tableName)->count();
        $tableStats[$tableName] = $count;

        $status = $count > 0 ? '✓' : '○';
        echo "{$status} {$tableName}: " . number_format($count) . " 条\n";
    } catch (Exception $e) {
        echo "✗ {$tableName}: 查询失败 - {$e->getMessage()}\n";
        $tableStats[$tableName] = 0;
    }
}

echo "\n====================================\n";
echo "表分类统计\n";
echo "====================================\n\n";

$withData = array_filter($tableStats, fn($count) => $count > 0);
$emptyTables = array_filter($tableStats, fn($count) => $count === 0);

echo "有数据的表: " . count($withData) . " 个\n";
foreach ($withData as $table => $count) {
    echo "  - {$table}: " . number_format($count) . "\n";
}

echo "\n空表: " . count($emptyTables) . " 个\n";
foreach ($emptyTables as $table => $count) {
    echo "  - {$table}\n";
}

echo "\n总记录数: " . number_format(array_sum($tableStats)) . " 条\n";

echo "\n====================================\n";
echo "需要映射的表（旧表→新表）\n";
echo "====================================\n\n";

$tableMapping = [
    'admin' => 'admins',
    'agents' => 'user_logins + user_infos (account_type=1)',
    'user' => 'user_logins + user_infos (account_type=2)',
    'mt4_trades' => 'mt4_trades',
    'system_config' => 'system_configs',
    'voucher_info' => 'voucher_infos',
    'cancel_apply' => 'cancel_applies',
    'news' => 'news',
    'user_auth' => 'user_auths',
];

foreach ($tableMapping as $oldTable => $newTable) {
    $count = $tableStats[$oldTable] ?? 0;
    $status = $count > 0 ? '→' : '○';
    echo "{$status} {$oldTable} ({$count}条) => {$newTable}\n";
}

echo "\n====================================\n";
echo "表结构样本（前3个有数据的表）\n";
echo "====================================\n\n";

$sampled = 0;
foreach ($withData as $table => $count) {
    if ($sampled >= 3) break;

    echo "【{$table}】样本记录:\n";
    $sample = DB::connection($oldDb)->table($table)->first();

    if ($sample) {
        foreach ($sample as $field => $value) {
            $display = is_string($value) && strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value;
            echo "  - {$field}: {$display}\n";
        }
    }
    echo "\n";
    $sampled++;
}
