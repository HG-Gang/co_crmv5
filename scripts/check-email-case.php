<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "检查邮箱大小写问题...\n\n";

// 检查ybf_001@163.com的所有变体
$variants = ['ybf_001@163.com', 'YBF_001@163.com', 'Ybf_001@163.com'];

foreach ($variants as $email) {
    $count = DB::connection('old_crm')
        ->table('agents')
        ->where('email', $email)
        ->count();

    if ($count > 0) {
        echo "agents表 '{$email}': {$count}条\n";

        $records = DB::connection('old_crm')
            ->table('agents')
            ->where('email', $email)
            ->select('user_id', 'email', 'user_name')
            ->get();

        foreach ($records as $r) {
            echo "  user_id: {$r->user_id}, email: '{$r->email}', name: {$r->user_name}\n";
        }
    }
}

// 使用BINARY精确匹配
echo "\n使用BINARY精确匹配：\n";
$exactMatches = DB::connection('old_crm')
    ->table('agents')
    ->whereRaw("BINARY email = 'ybf_001@163.com'")
    ->select('user_id', 'email', 'user_name')
    ->get();

echo "精确匹配数量：" . count($exactMatches) . "\n";
foreach ($exactMatches as $r) {
    echo "  user_id: {$r->user_id}, email: '{$r->email}', name: {$r->user_name}\n";
}
