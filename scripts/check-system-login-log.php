<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "system_login_log 表结构\n";
echo "====================================\n\n";

$cols = DB::connection('old_crm')->select('SHOW COLUMNS FROM system_login_log');
foreach ($cols as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}

echo "\n样本数据:\n";
$sample = DB::connection('old_crm')->table('system_login_log')->first();
if ($sample) {
    foreach ($sample as $field => $value) {
        $display = is_string($value) && strlen($value) > 60 ? substr($value, 0, 57) . '...' : $value;
        echo "  {$field}: {$display}\n";
    }
}
