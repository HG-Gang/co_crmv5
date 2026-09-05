<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = DB::connection('old_crm')->select('SHOW TABLES');

echo "包含 'admin' 和 'log' 的表:\n";
foreach ($tables as $t) {
    $name = array_values((array)$t)[0];
    if (stripos($name, 'admin') !== false && stripos($name, 'log') !== false) {
        $count = DB::connection('old_crm')->table($name)->count();
        echo "  {$name}: {$count} 条\n";
    }
}
