<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (['admin_login_logs','user_login_logs'] as $t) {
    echo "=== $t ===\n";
    foreach (DB::select("SHOW COLUMNS FROM $t") as $c) echo "  $c->Field $c->Type\n";
}
