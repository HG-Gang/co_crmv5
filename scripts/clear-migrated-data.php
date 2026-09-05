<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "清空已迁移的测试数据...\n\n";

$tables = [
    'withdraw_records',
    'deposit_records',
    'voucher_infos',
    'cancel_applies',
    'symbol_prices',
    'operation_logs',
    'admin_login_logs',
    'user_images',
    'mt4_users',
    'user_addresses',
    'user_onlines',
    'trans_apply_logs',
    'agent_descendants',
];

foreach ($tables as $table) {
    $count = DB::connection('mysql')->table($table)->count();
    if ($count > 0) {
        DB::connection('mysql')->table($table)->truncate();
        echo "✓ {$table}: 清空了 {$count} 条\n";
    } else {
        echo "○ {$table}: 已经是空表\n";
    }
}

echo "\n清空完成！\n";
