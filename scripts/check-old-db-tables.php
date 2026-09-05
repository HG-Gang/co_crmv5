<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "旧库表数据统计\n";
echo "====================================\n\n";

$oldDb = 'old_crm';

$tables = [
    'admin' => '管理员',
    'agents' => '代理商',
    'user' => '客户',
    'mt4_trades' => 'MT4交易',
    'recharge' => '入金记录',
    'withdraw' => '出金记录',
    'system_config' => '系统配置',
    'language' => '语言',
    'user_auth' => '用户认证资料',
    'mt4_group' => 'MT4组',
    'mt4_symbol' => 'MT4交易品种',
    'exchange_rate' => '汇率',
    'commission' => '佣金记录',
    'news' => '新闻',
    'user_address' => '用户地址',
    'voucher_info' => '凭证信息',
    'cancel_apply' => '销户申请',
];

$counts = [];
foreach ($tables as $table => $name) {
    try {
        $count = DB::connection($oldDb)->table($table)->count();
        $counts[$table] = $count;
        echo "{$name} ({$table}): {$count} 条\n";
    } catch (Exception $e) {
        echo "{$name} ({$table}): 表不存在\n";
    }
}

echo "\n====================================\n";
echo "需要迁移的关键表\n";
echo "====================================\n\n";

// 检查user_auth表结构
echo "user_auth表结构样本：\n";
$sample = DB::connection($oldDb)->table('user_auth')->first();
if ($sample) {
    foreach ($sample as $field => $value) {
        $display = is_string($value) && strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value;
        echo "  - {$field}: {$display}\n";
    }
}

echo "\n语言表样本：\n";
$langs = DB::connection($oldDb)->table('language')->limit(5)->get();
foreach ($langs as $lang) {
    echo "  - id={$lang->id}, name={$lang->name}\n";
}

echo "\n系统配置样本：\n";
$configs = DB::connection($oldDb)->table('system_config')->limit(5)->get();
foreach ($configs as $config) {
    $value = strlen($config->config_value ?? '') > 30 ? substr($config->config_value, 0, 27) . '...' : ($config->config_value ?? '');
    echo "  - {$config->config_key}: {$value}\n";
}
