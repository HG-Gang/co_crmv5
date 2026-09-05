<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "检查迁移问题\n";
echo "====================================\n\n";

// 1. 检查 admin_login_logs.login_ip 字段定义
echo "【问题1: admin_login_logs.login_ip 字段长度】\n";
$col = DB::connection('mysql')->select("SHOW COLUMNS FROM admin_login_logs WHERE Field = 'login_ip'");
if ($col) {
    echo "新库字段定义: {$col[0]->Field} {$col[0]->Type}\n";
}

// 检查旧库中最长的 login_ip 数据（正确表名是 system_login_log）
$maxLen = DB::connection('old_crm')->selectOne(
    "SELECT MAX(LENGTH(login_ip)) as max_len FROM system_login_log"
);
echo "旧库最长 login_ip 长度: {$maxLen->max_len}\n";

$samples = DB::connection('old_crm')->table('system_login_log')
    ->whereRaw('LENGTH(login_ip) > 50')
    ->select('sys_id', 'login_ip', 'login_id_desc', DB::raw('LENGTH(login_ip) as len'))
    ->orderByDesc('len')
    ->limit(5)
    ->get();

echo "超长样本:\n";
foreach ($samples as $s) {
    echo "  sys_id={$s->sys_id}, 长度={$s->len}, IP={$s->login_ip}, 描述={$s->login_id_desc}\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// 2. 检查 deposit_record_log 重复订单号
echo "【问题2: deposit_record_log 重复订单号】\n";
$duplicates = DB::connection('old_crm')->select(
    "SELECT dep_outTrande, COUNT(*) as cnt
     FROM deposit_record_log
     WHERE dep_outTrande != ''
     GROUP BY dep_outTrande
     HAVING cnt > 1
     LIMIT 5"
);

echo "重复订单号样本:\n";
foreach ($duplicates as $dup) {
    echo "  {$dup->dep_outTrande}: {$dup->cnt} 条记录\n";

    $records = DB::connection('old_crm')->table('deposit_record_log')
        ->where('dep_outTrande', $dup->dep_outTrande)
        ->select('dep_id', 'dep_outTrande', 'dep_mt4_id', 'dep_amount', 'dep_status')
        ->get();

    foreach ($records as $rec) {
        echo "    dep_id={$rec->dep_id}, user={$rec->dep_mt4_id}, amount={$rec->dep_amount}, status={$rec->dep_status}\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// 3. 统计空订单号数量
$emptyCount = DB::connection('old_crm')->table('deposit_record_log')
    ->where('dep_outTrande', '')
    ->count();

echo "空订单号记录数: {$emptyCount}\n";
