<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "核心表结构对比\n";
echo "====================================\n\n";

$oldDb = 'old_crm';
$newDb = 'mysql';

$tablePairs = [
    ['deposit_record_log', 'deposit_records'],
    ['draw_record_log', 'withdraw_records'],
    ['voucher_info', 'voucher_infos'],
    ['cancel_apply', 'cancel_applies'],
    ['operation_log', 'operation_logs'],
    ['system_login_log', 'admin_login_logs'],
    ['user_img', 'user_images'],
    ['mt4_users', 'mt4_users'],
    ['symbol_prices', 'symbol_prices'],
    ['user_trades', 'commission_records'],
];

foreach ($tablePairs as [$oldTable, $newTable]) {
    echo "【{$oldTable} => {$newTable}】\n";
    echo str_repeat("-", 60) . "\n";

    // 旧表结构
    echo "旧表字段:\n";
    $oldCols = DB::connection($oldDb)->select("SHOW COLUMNS FROM {$oldTable}");
    foreach ($oldCols as $col) {
        $null = $col->Null === 'YES' ? 'NULL' : 'NOT NULL';
        echo "  {$col->Field} ({$col->Type}) {$null}\n";
    }

    echo "\n";

    // 新表结构
    echo "新表字段:\n";
    $newCols = DB::connection($newDb)->select("SHOW COLUMNS FROM {$newTable}");
    foreach ($newCols as $col) {
        $null = $col->Null === 'YES' ? 'NULL' : 'NOT NULL';
        echo "  {$col->Field} ({$col->Type}) {$null}\n";
    }

    echo "\n";

    // 样本数据
    echo "样本数据:\n";
    $sample = DB::connection($oldDb)->table($oldTable)->first();
    if ($sample) {
        foreach ($sample as $field => $value) {
            $display = is_string($value) && strlen($value) > 40 ? substr($value, 0, 37) . '...' : $value;
            echo "  {$field}: {$display}\n";
        }
    } else {
        echo "  (无数据)\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";
}
