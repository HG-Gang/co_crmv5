<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "数据库表数据检查\n";
echo "====================================\n\n";

$tables = [
    'admins' => '管理员',
    'user_logins' => '用户登录',
    'user_infos' => '用户信息',
    'user_profiles' => '用户详细资料',
    'mt4_trades' => 'MT4交易',
    'deposit_records' => '入金记录',
    'withdraw_records' => '出金记录',
    'system_configs' => '系统配置',
    'languages' => '语言',
    'permissions' => '权限',
    'roles' => '角色',
    'role_permissions' => '角色权限',
    'admin_role' => '管理员角色',
    'mt4_groups' => 'MT4组',
    'mt4_symbols' => 'MT4交易品种',
    'exchange_rates' => '汇率',
    'commission_rules' => '佣金规则',
];

foreach ($tables as $table => $name) {
    try {
        $count = DB::table($table)->count();
        $status = $count > 0 ? '✓' : '✗';
        echo "{$status} {$name} ({$table}): {$count} 条\n";
    } catch (Exception $e) {
        echo "? {$name} ({$table}): 表不存在或查询失败\n";
    }
}

echo "\n====================================\n";
echo "关键配置检查\n";
echo "====================================\n\n";

// 检查语言配置
$langs = DB::table('languages')->get(['code', 'name', 'is_default']);
echo "语言配置：\n";
foreach ($langs as $lang) {
    $default = $lang->is_default ? '(默认)' : '';
    echo "  - {$lang->code}: {$lang->name} {$default}\n";
}

// 检查system_configs
echo "\n系统配置项：\n";
$configs = DB::table('system_configs')->get(['config_key', 'config_value']);
foreach ($configs as $config) {
    $value = strlen($config->config_value) > 50 ? substr($config->config_value, 0, 47) . '...' : $config->config_value;
    echo "  - {$config->config_key}: {$value}\n";
}

// 检查user_profiles表是否为空
echo "\nuser_profiles检查：\n";
$profileCount = DB::table('user_profiles')->count();
echo "  总记录数: {$profileCount}\n";

if ($profileCount === 0) {
    echo "  ⚠️  user_profiles表为空，需要为迁移用户创建profile记录\n";
}
