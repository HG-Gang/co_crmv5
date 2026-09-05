<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "新库目标表字段结构\n";
echo "====================================\n\n";

$newDb = 'mysql';

$tables = ['user_addresses', 'user_onlines', 'trans_apply_logs'];

foreach ($tables as $table) {
    echo "【{$table}】\n";

    try {
        $cols = DB::connection($newDb)->select("SHOW COLUMNS FROM {$table}");
        echo "字段:\n";
        foreach ($cols as $col) {
            $null = $col->Null === 'YES' ? 'NULL' : 'NOT NULL';
            echo "  {$col->Field} ({$col->Type}) {$null}\n";
        }
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";
}
