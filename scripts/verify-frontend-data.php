<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "前端功能数据验证\n";
echo "====================================\n\n";

// 测试用户：info@gmtkg.com (user_id=1001)
$testUserId = 1001;

echo "【测试用户】user_id={$testUserId}, email=info@gmtkg.com\n\n";

// 1. 检查user_logins
$login = DB::table('user_logins')->where('user_id', $testUserId)->first();
echo "✓ user_logins:\n";
echo "  email: {$login->email}\n";
echo "  account_type: {$login->account_type}\n";
echo "  is_enabled: {$login->is_enabled}\n\n";

// 2. 检查user_infos
$info = DB::table('user_infos')->where('user_id', $testUserId)->first();
echo "✓ user_infos:\n";
echo "  user_name: {$info->user_name}\n";
echo "  phone: {$info->phone}\n";
echo "  parent_id: {$info->parent_id}\n";
echo "  total_funds: {$info->total_funds}\n\n";

// 3. 检查user_auths（现在应该存在）
$auth = DB::table('user_auths')->where('user_id', $testUserId)->first();
if ($auth) {
    echo "✓ user_auths:\n";
    echo "  bank_status: {$auth->bank_status}\n";
    echo "  id_card_status: {$auth->id_card_status}\n";
    echo "  bank_no: " . ($auth->bank_no ?: '(空)') . "\n";
    echo "  id_card_no: " . ($auth->id_card_no ?: '(空)') . "\n\n";
} else {
    echo "✗ user_auths不存在！\n\n";
}

// 4. 检查交易记录
$tradeCount = DB::table('mt4_trades')->where('login', $testUserId)->count();
echo "✓ mt4_trades: {$tradeCount} 条交易记录\n\n";

// 5. 检查语言配置
$langs = DB::table('languages')->get();
echo "✓ 语言列表:\n";
foreach ($langs as $lang) {
    $active = $lang->is_active ? '启用' : '停用';
    echo "  - {$lang->language_code}: {$lang->name} ({$active})\n";
}
echo "\n";

// 6. 检查国家列表
$countryCount = DB::table('countries')->count();
echo "✓ countries: {$countryCount} 个国家（前端注册需要）\n\n";

// 7. 检查系统配置
$configs = DB::table('system_configs')
    ->whereIn('key', ['default_language', 'crm_preference', 'site_name'])
    ->get();

echo "✓ 关键系统配置:\n";
foreach ($configs as $config) {
    $value = strlen($config->value) > 50 ? substr($config->value, 0, 47) . '...' : $config->value;
    echo "  - {$config->key}: {$value}\n";
}

echo "\n====================================\n";
echo "前端功能可用性评估\n";
echo "====================================\n\n";

$checks = [
    'user_auths记录' => DB::table('user_auths')->where('user_id', $testUserId)->exists(),
    'languages表有数据' => DB::table('languages')->count() > 0,
    'countries表有数据' => DB::table('countries')->count() > 0,
    'system_configs有default_language' => DB::table('system_configs')->where('key', 'default_language')->exists(),
    'system_configs有crm_preference' => DB::table('system_configs')->where('key', 'crm_preference')->exists(),
    'payment_channels有数据' => DB::table('payment_channels')->count() > 0,
];

$allPassed = true;
foreach ($checks as $item => $passed) {
    $status = $passed ? '✓' : '✗';
    echo "{$status} {$item}\n";
    if (!$passed) {
        $allPassed = false;
    }
}

echo "\n";
if ($allPassed) {
    echo "✅ 所有检查通过！前端应该可以正常使用。\n";
    echo "   - 登录功能：可用\n";
    echo "   - 用户资料更新：可用\n";
    echo "   - 语言切换：可用\n";
    echo "   - 注册功能：可用（有国家列表）\n";
    echo "   - 入金功能：可用（有支付渠道）\n";
} else {
    echo "⚠️  部分检查未通过，可能影响前端功能。\n";
}
