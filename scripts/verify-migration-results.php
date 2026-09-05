<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "数据迁移验证报告\n";
echo "====================================\n\n";

$tables = [
    'deposit_records' => '入金记录',
    'withdraw_records' => '出金记录',
    'voucher_infos' => '凭证信息',
    'cancel_applies' => '销户申请',
    'operation_logs' => '操作日志',
    'admin_login_logs' => '管理员登录日志',
    'user_images' => '用户图片',
    'mt4_users' => 'MT4用户',
    'symbol_prices' => '符号价格',
    'user_addresses' => '用户地址',
    'user_onlines' => '在线用户',
    'trans_apply_logs' => '转账申请日志',
    'agent_descendants' => '代理层级关系',
];

$total = 0;

foreach ($tables as $table => $name) {
    $count = DB::connection('mysql')->table($table)->count();
    $total += $count;
    echo sprintf("%-25s %s: %s 条\n", $table, $name, number_format($count));
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "总计: " . number_format($total) . " 条记录\n";
echo str_repeat("=", 60) . "\n\n";

// 检查关键表的样本数据
echo "【样本数据验证】\n\n";

// 入金记录
$deposit = DB::connection('mysql')->table('deposit_records')->first();
if ($deposit) {
    echo "入金记录样本:\n";
    echo "  ID: {$deposit->id}, 用户: {$deposit->user_id}, 金额: {$deposit->amount}, 订单号: {$deposit->local_order_no}\n\n";
}

// 出金记录
$withdraw = DB::connection('mysql')->table('withdraw_records')->first();
if ($withdraw) {
    echo "出金记录样本:\n";
    echo "  ID: {$withdraw->id}, 用户: {$withdraw->user_id}, 金额: {$withdraw->apply_amount}, 订单号: {$withdraw->local_order_no}\n\n";
}

// 管理员登录日志
$loginLog = DB::connection('mysql')->table('admin_login_logs')->orderBy('id', 'desc')->first();
if ($loginLog) {
    echo "管理员登录日志样本（最新）:\n";
    echo "  ID: {$loginLog->id}, 管理员: {$loginLog->admin_id}, IP: {$loginLog->login_ip}, 长度: " . strlen($loginLog->login_ip) . "\n\n";
}

// 在线用户
$online = DB::connection('mysql')->table('user_onlines')->orderBy('id', 'desc')->first();
if ($online) {
    echo "在线用户样本（最新）:\n";
    echo "  ID: {$online->id}, 用户: {$online->user_id}, IP: {$online->ip_address}, 长度: " . strlen($online->ip_address) . "\n\n";
}

// 代理层级关系
$descendant = DB::connection('mysql')->table('agent_descendants')->first();
if ($descendant) {
    echo "代理层级关系样本:\n";
    echo "  代理: {$descendant->agent_id}, 下级: {$descendant->descendant_id}, 深度: {$descendant->depth}, 直属: {$descendant->is_direct}\n\n";
}

echo "✅ 数据迁移验证完成！\n";
