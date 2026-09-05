<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "检查特定表的实际字段结构\n";
echo "====================================\n\n";

$oldDb = 'old_crm';

$tables = ['user_addresses', 'user_online', 'trans_apply_log'];

foreach ($tables as $table) {
    echo "【{$table}】\n";

    try {
        $cols = DB::connection($oldDb)->select("SHOW COLUMNS FROM {$table}");
        echo "字段:\n";
        foreach ($cols as $col) {
            echo "  {$col->Field} ({$col->Type})\n";
        }

        $sample = DB::connection($oldDb)->table($table)->first();
        if ($sample) {
            echo "\n样本数据:\n";
            foreach ($sample as $field => $value) {
                $display = is_string($value) && strlen($value) > 40 ? substr($value, 0, 37) . '...' : $value;
                echo "  {$field}: {$display}\n";
            }
        } else {
            echo "\n(无数据)\n";
        }
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";
}
