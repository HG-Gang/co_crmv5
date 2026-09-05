<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Hash;

echo "========== 重置所有前台用户密码为 123456 ==========\n\n";

$newPassword = '123456';
$hashedPassword = Hash::make($newPassword);

echo "新密码: {$newPassword}\n";
echo "加密后: {$hashedPassword}\n\n";

// 获取所有启用的账号数量
$totalCount = DB::table('user_logins')
    ->where('is_enabled', 1)
    ->where('is_cancelled', 0)
    ->count();

echo "需要重置的账号总数: {$totalCount}\n\n";

if ($totalCount === 0) {
    echo "❌ 没有需要重置的账号\n";
    exit(1);
}

// 确认操作
echo "⚠️  警告: 此操作将重置 {$totalCount} 个账号的密码！\n";
echo "按 Enter 继续，Ctrl+C 取消...\n";
// readline(""); // 注释掉交互确认，直接执行

echo "\n开始重置密码...\n\n";

$startTime = microtime(true);

// 批量更新密码
$updated = DB::table('user_logins')
    ->where('is_enabled', 1)
    ->where('is_cancelled', 0)
    ->update([
        'password' => $hashedPassword,
    ]);

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "✅ 密码重置完成！\n";
echo "更新记录数: {$updated}\n";
echo "耗时: {$duration} 秒\n\n";

// 显示几个测试账号
echo "========== 可用测试账号 ==========\n\n";

$testAccounts = DB::table('user_logins as ul')
    ->join('users as u', 'ul.user_id', '=', 'u.id')
    ->where('ul.is_enabled', 1)
    ->where('ul.is_cancelled', 0)
    ->select('ul.email', 'ul.user_id', 'u.name', 'u.type')
    ->limit(10)
    ->get();

foreach ($testAccounts as $account) {
    $type = $account->type == 0 ? '客户' : ($account->type == 1 ? '代理' : '其他');
    echo "邮箱: {$account->email}\n";
    echo "密码: {$newPassword}\n";
    echo "用户ID: {$account->user_id}\n";
    echo "姓名: {$account->name}\n";
    echo "类型: {$type}\n";
    echo str_repeat("-", 50) . "\n";
}

echo "\n所有账号密码已统一重置为: {$newPassword}\n";
