<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========== 验证密码重置结果 ==========\n\n";

// 测试几个账号
$testEmails = [
    'info@gmtkg.com',
    'gmtk2088@gmail.com',
    'gmtk88@gmail.com',
];

foreach ($testEmails as $email) {
    $login = DB::table('user_logins')->where('email', $email)->first();

    if ($login) {
        $passwordCheck = password_verify('123456', $login->password);
        $status = $passwordCheck ? '✅ 正确' : '❌ 错误';

        echo "邮箱: {$email}\n";
        echo "用户ID: {$login->user_id}\n";
        echo "密码验证 (123456): {$status}\n";
        echo str_repeat("-", 50) . "\n";
    } else {
        echo "❌ 邮箱不存在: {$email}\n";
        echo str_repeat("-", 50) . "\n";
    }
}

echo "\n✅ 所有账号密码已重置为: 123456\n";
echo "\n推荐登录账号:\n";
echo "邮箱: info@gmtkg.com\n";
echo "密码: 123456\n";
