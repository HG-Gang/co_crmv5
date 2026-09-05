<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "新库表数据检查\n";
echo "====================================\n\n";

$tables = [
    'admins' => '管理员',
    'user_logins' => '用户登录',
    'user_infos' => '用户信息',
    'user_auths' => '用户认证资料',
    'mt4_trades' => 'MT4交易',
    'deposit_records' => '入金记录',
    'withdraw_records' => '出金记录',
    'system_configs' => '系统配置',
    'languages' => '语言',
    'permissions' => '权限',
    'roles' => '角色',
    'role_permissions' => '角色权限',
    'agent_levels' => '代理等级',
    'group_configs' => '组配置',
    'payment_channels' => '支付渠道',
    'countries' => '国家',
    'mt4_configs' => 'MT4配置',
    'voucher_infos' => '凭证信息',
    'cancel_applies' => '销户申请',
];

foreach ($tables as $table => $name) {
    try {
        $count = DB::table($table)->count();
        $status = $count > 0 ? '✓' : '✗';
        echo "{$status} {$name} ({$table}): {$count} 条\n";
    } catch (Exception $e) {
        echo "? {$name} ({$table}): 表不存在\n";
    }
}

echo "\n====================================\n";
echo "关键检查\n";
echo "====================================\n\n";

// 检查语言表
$langCount = DB::table('languages')->count();
echo "languages表记录数: {$langCount}\n";
if ($langCount > 0) {
    $langs = DB::table('languages')->get(['language_code', 'name', 'is_active']);
    foreach ($langs as $lang) {
        echo "  - {$lang->language_code}: {$lang->name} (active={$lang->is_active})\n";
    }
}

// 检查system_configs
$configCount = DB::table('system_configs')->count();
echo "\nsystem_configs表记录数: {$configCount}\n";
if ($configCount > 0) {
    echo "关键配置:\n";
    $keys = ['site_name', 'default_language', 'crm_preference', 'withdrawal_fee_enabled'];
    foreach ($keys as $key) {
        $config = DB::table('system_configs')->where('config_key', $key)->first();
        if ($config) {
            echo "  - {$key}: {$config->config_value}\n";
        } else {
            echo "  ✗ {$key}: 不存在\n";
        }
    }
}

// 检查user_auths
$authCount = DB::table('user_auths')->count();
echo "\nuser_auths表记录数: {$authCount}\n";
echo "迁移的用户数: " . DB::table('user_logins')->count() . "\n";
echo "需要创建的user_auths记录: " . (DB::table('user_logins')->count() - $authCount) . "\n";
