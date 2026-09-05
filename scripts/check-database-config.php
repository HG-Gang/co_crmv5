<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "数据库连接配置检查\n";
echo "====================================\n\n";

echo "MySQL 连接（新库）:\n";
echo "  数据库: " . config('database.connections.mysql.database') . "\n";
echo "  主机: " . config('database.connections.mysql.host') . "\n\n";

echo "Old CRM 连接（旧库）:\n";
echo "  数据库: " . config('database.connections.old_crm.database') . "\n";
echo "  主机: " . config('database.connections.old_crm.host') . "\n\n";

echo "====================================\n";
echo "实际数据验证\n";
echo "====================================\n\n";

// 检查新库数据
echo "新库 (mysql) 数据:\n";
$tables = [
    'deposit_records' => '入金记录',
    'withdraw_records' => '出金记录',
    'mt4_users' => 'MT4用户',
    'agent_descendants' => '代理层级',
];

foreach ($tables as $table => $name) {
    $count = DB::connection('mysql')->table($table)->count();
    echo "  {$name} ({$table}): {$count} 条\n";
}

echo "\n旧库 (old_crm) 数据:\n";
$oldTables = [
    'deposit_record_log' => '入金记录',
    'draw_record_log' => '出金记录',
    'mt4_users' => 'MT4用户',
    'hierarchy' => '代理层级',
];

foreach ($oldTables as $table => $name) {
    $count = DB::connection('old_crm')->table($table)->count();
    echo "  {$name} ({$table}): {$count} 条\n";
}

echo "\n✅ 数据库连接正常！\n";
