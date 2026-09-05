<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "新库 co_crmv5 表结构清单\n";
echo "====================================\n\n";

$newDb = 'mysql';

// 获取所有表名
$tables = DB::connection($newDb)->select('SHOW TABLES');
$tableNames = array_map(function($table) {
    return array_values((array)$table)[0];
}, $tables);

sort($tableNames);

$excludeSystem = ['migrations', 'password_resets', 'failed_jobs', 'personal_access_tokens'];

echo "总计表数: " . count($tableNames) . "\n\n";

$businessTables = [];
$systemTables = [];

foreach ($tableNames as $tableName) {
    if (in_array($tableName, $excludeSystem)) {
        $systemTables[] = $tableName;
        continue;
    }

    $count = DB::connection($newDb)->table($tableName)->count();
    $businessTables[$tableName] = $count;

    $status = $count > 0 ? '✓' : '○';
    echo "{$status} {$tableName}: " . number_format($count) . " 条\n";
}

echo "\n====================================\n";
echo "统计\n";
echo "====================================\n\n";

echo "业务表: " . count($businessTables) . " 个\n";
echo "系统表: " . count($systemTables) . " 个\n";

$withData = array_filter($businessTables, fn($count) => $count > 0);
$emptyTables = array_filter($businessTables, fn($count) => $count === 0);

echo "\n有数据的表: " . count($withData) . " 个\n";
echo "空表: " . count($emptyTables) . " 个\n";
echo "总记录数: " . number_format(array_sum($businessTables)) . " 条\n";

echo "\n====================================\n";
echo "需要迁移数据的目标表\n";
echo "====================================\n\n";

// 标注空表，这些可能需要从旧库迁移数据
foreach ($emptyTables as $table => $count) {
    echo "○ {$table} (空)\n";
}
