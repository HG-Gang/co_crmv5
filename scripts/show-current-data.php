<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "system_configs表数据\n";
echo "====================================\n\n";

$configs = DB::table('system_configs')->get();
echo "总计: " . count($configs) . " 条配置\n\n";

foreach ($configs as $config) {
    $value = strlen($config->value ?? '') > 100 ? substr($config->value, 0, 97) . '...' : ($config->value ?? 'NULL');
    echo "[{$config->group}] {$config->key}\n";
    echo "  值: {$value}\n";
    echo "  描述: {$config->description}\n\n";
}

echo "====================================\n";
echo "user_auths表数据\n";
echo "====================================\n\n";

$auths = DB::table('user_auths')->get();
echo "总计: " . count($auths) . " 条\n\n";

foreach ($auths as $auth) {
    echo "user_id: {$auth->user_id}\n";
    echo "  real_name: {$auth->real_name}\n";
    echo "  id_number: {$auth->id_number}\n";
    echo "  审核状态: " . ['待审核', '已通过', '已拒绝'][$auth->audit_status ?? 0] . "\n\n";
}

echo "====================================\n";
echo "需要填充的关键数据\n";
echo "====================================\n\n";

$userCount = DB::table('user_logins')->count();
$authCount = DB::table('user_auths')->count();
echo "✗ user_auths: 需要为 " . ($userCount - $authCount) . " 个用户创建认证记录\n";

$countryCount = DB::table('countries')->count();
echo ($countryCount > 0 ? '✓' : '✗') . " countries: {$countryCount} 条（前端注册需要国家列表）\n";

$paymentCount = DB::table('payment_channels')->count();
echo ($paymentCount > 0 ? '✓' : '✗') . " payment_channels: {$paymentCount} 条（入金需要支付渠道）\n";

$mt4ConfigCount = DB::table('mt4_configs')->count();
echo ($mt4ConfigCount > 0 ? '✓' : '✗') . " mt4_configs: {$mt4ConfigCount} 条（MT4集成配置）\n";

echo "\n====================================\n";
echo "CRM偏好设置检查\n";
echo "====================================\n\n";

// 检查前端语言切换相关配置
$langConfig = DB::table('system_configs')->where('key', 'default_language')->first();
if ($langConfig) {
    echo "✓ 默认语言配置: {$langConfig->value}\n";
} else {
    echo "✗ 默认语言配置不存在\n";
}

$prefConfig = DB::table('system_configs')->where('key', 'crm_preference')->first();
if ($prefConfig) {
    echo "✓ CRM偏好配置: {$prefConfig->value}\n";
} else {
    echo "✗ CRM偏好配置不存在（前端无法切换语言）\n";
}
