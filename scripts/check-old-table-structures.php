<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "旧库表结构检查\n";
echo "========================================\n\n";

$tables = ['group_config', 'mt4_config', 'symbol_spread'];

foreach ($tables as $table) {
    echo "【{$table}】\n";
    try {
        $columns = DB::connection('old_crm')->select("SHOW COLUMNS FROM {$table}");
        foreach ($columns as $col) {
            echo "  {$col->Field} | {$col->Type}" . ($col->Null === 'YES' ? ' | NULL' : '') . "\n";
        }
    } catch (Exception $e) {
        echo "  ❌ 查询失败：" . $e->getMessage() . "\n";
    }
    echo "\n";
}
