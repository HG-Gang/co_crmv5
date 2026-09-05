<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'ybf_001@163.com';

echo "检查邮箱: {$email}\n\n";

$agents = DB::connection('old_crm')
    ->table('agents')
    ->where('email', $email)
    ->select('user_id', 'email', 'user_name')
    ->orderBy('user_id')
    ->get();

echo "在agents表中出现 " . count($agents) . " 次：\n";
foreach ($agents as $agent) {
    echo "  user_id: {$agent->user_id}, name: {$agent->user_name}\n";
}

$users = DB::connection('old_crm')
    ->table('user')
    ->where('email', $email)
    ->select('user_id', 'email', 'user_name')
    ->orderBy('user_id')
    ->get();

echo "\n在user表中出现 " . count($users) . " 次：\n";
foreach ($users as $user) {
    echo "  user_id: {$user->user_id}, name: {$user->user_name}\n";
}
