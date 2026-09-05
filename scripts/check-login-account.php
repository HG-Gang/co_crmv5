<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$email = 'info@gmtkg.com';

$login = DB::table('user_logins')->where('email', $email)->first();

if ($login) {
    echo "✅ 账号存在\n";
    echo "邮箱: {$login->email}\n";
    echo "用户ID: {$login->user_id}\n";
    echo "账户类型: {$login->account_type}\n";
    echo "是否启用: " . ($login->is_enabled ? '是' : '否') . "\n";
    echo "是否注销: " . ($login->is_cancelled ? '是' : '否') . "\n";

    $user = DB::table('users')->where('id', $login->user_id)->first();
    if ($user) {
        echo "\n用户信息:\n";
        echo "姓名: {$user->name}\n";
        echo "用户类型: " . ($user->type == 0 ? '普通用户' : '代理') . "\n";
    }

    echo "\n密码提示: 请查看 database/seeders/FrontDemoDataSeeder.php 第 633 行\n";
} else {
    echo "❌ 账号不存在\n";
}
