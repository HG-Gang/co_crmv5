<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "agent_descendants 表结构\n";
echo "====================================\n\n";

$cols = DB::connection('mysql')->select('SHOW COLUMNS FROM agent_descendants');
foreach ($cols as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}
