<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = ['admin_logins','admins','roles','permissions','role_permissions','user_logins','user_infos'];
foreach ($tables as $t) {
    echo "\n=== $t ===\n";
    try {
        $cols = DB::select("SHOW COLUMNS FROM $t");
        foreach ($cols as $c) {
            echo "  {$c->Field}  {$c->Type}  {$c->Null}  {$c->Default}\n";
        }
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
}
