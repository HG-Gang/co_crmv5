<?php

/**
 * 检查旧库表结构
 * 用于数据迁移前的字段映射分析
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = ['admin', 'user', 'agents', 'system_config', 'mt4_trades', 'deposit_record_log', 'draw_record_log'];

foreach ($tables as $table) {
    try {
        $columns = DB::connection('old_crm')->select("DESCRIBE {$table}");
        echo "\n=== {$table} ===\n";
        foreach (array_slice($columns, 0, 15) as $col) {
            echo sprintf("%-30s %s\n", $col->Field, $col->Type);
        }
        $count = count($columns);
        echo "Total columns: {$count}\n";

        // 显示数据量
        $rowCount = DB::connection('old_crm')->table($table)->count();
        echo "Total rows: {$rowCount}\n";

    } catch (Exception $e) {
        echo "{$table}: {$e->getMessage()}\n";
    }
}
