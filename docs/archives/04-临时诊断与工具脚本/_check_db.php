<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TABLES ===\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $t) {
    echo array_values(get_object_vars($t))[0] . "\n";
}

echo "\n=== ADMINS ===\n";
try { $r = DB::select('SELECT * FROM admins LIMIT 5'); print_r($r); } catch(Exception $e) { echo $e->getMessage()."\n"; }
try { $r = DB::select('SELECT * FROM admin_logins LIMIT 5'); print_r($r); } catch(Exception $e) { echo $e->getMessage()."\n"; }

echo "\n=== ROLES ===\n";
try { $r = DB::select('SELECT * FROM roles'); print_r($r); } catch(Exception $e) { echo $e->getMessage()."\n"; }

echo "\n=== PERMISSIONS ===\n";
try { $r = DB::select('SELECT id,parent_id,guard,name,title,type FROM permissions LIMIT 20'); print_r($r); } catch(Exception $e) { echo $e->getMessage()."\n"; }

echo "\n=== ID_SEQUENCES ===\n";
try { $r = DB::select('SELECT * FROM id_sequences'); print_r($r); } catch(Exception $e) { echo $e->getMessage()."\n"; }

echo "\n=== ROLE_PERMISSIONS ===\n";
try { $r = DB::select('SELECT COUNT(*) as cnt FROM role_permissions'); print_r($r); } catch(Exception $e) { echo $e->getMessage()."\n"; }
