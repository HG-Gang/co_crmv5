<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "检查空邮箱...\n\n";

$emptyAgents = DB::connection('old_crm')
    ->table('agents')
    ->where(function($q) {
        $q->whereNull('email')->orWhere('email', '');
    })
    ->count();

echo "agents表中空邮箱数量：{$emptyAgents}\n";

$emptyUsers = DB::connection('old_crm')
    ->table('user')
    ->where(function($q) {
        $q->whereNull('email')->orWhere('email', '');
    })
    ->count();

echo "user表中空邮箱数量：{$emptyUsers}\n";

// 检查ybf_001@163.com在agents表中的顺序位置
$agents = DB::connection('old_crm')
    ->table('agents')
    ->orderBy('user_id')
    ->get(['user_id', 'email']);

$positions = [];
foreach ($agents as $index => $agent) {
    if ($agent->email === 'ybf_001@163.com') {
        $positions[] = "位置" . ($index + 1) . " (user_id: {$agent->user_id})";
    }
}

echo "\nybf_001@163.com在agents表中的位置：\n";
foreach ($positions as $pos) {
    echo "  {$pos}\n";
}
