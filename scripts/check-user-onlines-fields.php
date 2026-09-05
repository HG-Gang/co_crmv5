<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "检查 user_onlines 字段长度问题\n";
echo "====================================\n\n";

// 检查新库字段定义
$col = DB::connection('mysql')->select("SHOW COLUMNS FROM user_onlines WHERE Field = 'ip_address'");
if ($col) {
    echo "新库 ip_address 字段定义: {$col[0]->Type}\n";
}

$colAgent = DB::connection('mysql')->select("SHOW COLUMNS FROM user_onlines WHERE Field = 'user_agent'");
if ($colAgent) {
    echo "新库 user_agent 字段定义: {$colAgent[0]->Type}\n";
}

// 检查旧库中最长的数据
$maxIp = DB::connection('old_crm')->selectOne(
    "SELECT MAX(LENGTH(ip)) as max_len FROM user_online"
);
echo "\n旧库最长 ip 长度: {$maxIp->max_len}\n";

$maxUrl = DB::connection('old_crm')->selectOne(
    "SELECT MAX(LENGTH(req_url)) as max_len FROM user_online"
);
echo "旧库最长 req_url 长度: {$maxUrl->max_len}\n";

// 超长 IP 样本
$samples = DB::connection('old_crm')->table('user_online')
    ->whereRaw('LENGTH(ip) > 45')
    ->select('id', 'ip', DB::raw('LENGTH(ip) as len'))
    ->orderByDesc('len')
    ->limit(3)
    ->get();

echo "\n超长 IP 样本:\n";
foreach ($samples as $s) {
    echo "  ID={$s->id}, 长度={$s->len}, IP={$s->ip}\n";
}

// 超长 URL 样本
$urlSamples = DB::connection('old_crm')->table('user_online')
    ->whereRaw('LENGTH(req_url) > 255')
    ->select('id', 'req_url', DB::raw('LENGTH(req_url) as len'))
    ->orderByDesc('len')
    ->limit(3)
    ->get();

echo "\n超长 URL 样本:\n";
foreach ($urlSamples as $s) {
    $display = strlen($s->req_url) > 100 ? substr($s->req_url, 0, 97) . '...' : $s->req_url;
    echo "  ID={$s->id}, 长度={$s->len}, URL={$display}\n";
}
