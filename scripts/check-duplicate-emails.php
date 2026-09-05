<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "检查agents表重复邮箱...\n";
$duplicates = DB::connection('old_crm')
    ->select('SELECT email, COUNT(*) as cnt FROM agents GROUP BY email HAVING cnt > 1');

echo "重复邮箱数量：" . count($duplicates) . "\n\n";

foreach (array_slice($duplicates, 0, 20) as $dup) {
    echo "{$dup->email} : {$dup->cnt}次\n";
}

echo "\n检查user表重复邮箱...\n";
$duplicates = DB::connection('old_crm')
    ->select('SELECT email, COUNT(*) as cnt FROM user GROUP BY email HAVING cnt > 1');

echo "重复邮箱数量：" . count($duplicates) . "\n\n";

foreach (array_slice($duplicates, 0, 20) as $dup) {
    echo "{$dup->email} : {$dup->cnt}次\n";
}
