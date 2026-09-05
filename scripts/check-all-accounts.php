<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========== 检查所有前台账号 ==========\n\n";

// 检查 user_logins 表
$logins = DB::table('user_logins')
    ->where('is_enabled', 1)
    ->where('is_cancelled', 0)
    ->orderBy('user_id')
    ->get();

echo "user_logins 表总数: " . $logins->count() . "\n\n";

if ($logins->count() === 0) {
    echo "❌ user_logins 表为空！\n";
    exit(1);
}

echo "账号列表:\n";
echo str_repeat("-", 100) . "\n";
printf("%-10s %-30s %-15s %-10s %-10s\n", "用户ID", "邮箱", "账户类型", "是否启用", "是否注销");
echo str_repeat("-", 100) . "\n";

foreach ($logins as $login) {
    $accountType = $login->account_type == 1 ? '普通账户' : '其他';
    $isEnabled = $login->is_enabled ? '是' : '否';
    $isCancelled = $login->is_cancelled ? '是' : '否';

    printf("%-10s %-30s %-15s %-10s %-10s\n",
        $login->user_id,
        $login->email,
        $accountType,
        $isEnabled,
        $isCancelled
    );
}

echo "\n";

// 检查 users 表
echo "========== 检查 users 表关联数据 ==========\n\n";
$users = DB::table('users')
    ->whereIn('id', $logins->pluck('user_id'))
    ->orderBy('id')
    ->get();

echo "users 表总数: " . $users->count() . "\n\n";

if ($users->count() === 0) {
    echo "❌ users 表为空！\n";
    exit(1);
}

echo "用户详情:\n";
echo str_repeat("-", 100) . "\n";
printf("%-10s %-30s %-10s %-15s\n", "用户ID", "姓名", "类型", "上级ID");
echo str_repeat("-", 100) . "\n";

foreach ($users as $user) {
    $type = $user->type == 0 ? '客户' : ($user->type == 1 ? '代理' : '其他');

    printf("%-10s %-30s %-10s %-15s\n",
        $user->id,
        $user->name,
        $type,
        $user->parent_id ?? '无'
    );
}

echo "\n========== 推荐测试账号 ==========\n\n";

// 找一个代理账号
$agentLogin = DB::table('user_logins as ul')
    ->join('users as u', 'ul.user_id', '=', 'u.id')
    ->where('u.type', 1)
    ->where('ul.is_enabled', 1)
    ->select('ul.email', 'ul.user_id', 'u.name', 'u.type')
    ->first();

if ($agentLogin) {
    echo "✅ 代理账号: {$agentLogin->email}\n";
    echo "   用户ID: {$agentLogin->user_id}\n";
    echo "   姓名: {$agentLogin->name}\n\n";
}

// 找一个客户账号
$customerLogin = DB::table('user_logins as ul')
    ->join('users as u', 'ul.user_id', '=', 'u.id')
    ->where('u.type', 2)
    ->where('ul.is_enabled', 1)
    ->select('ul.email', 'ul.user_id', 'u.name', 'u.type')
    ->first();

if ($customerLogin) {
    echo "✅ 客户账号: {$customerLogin->email}\n";
    echo "   用户ID: {$customerLogin->user_id}\n";
    echo "   姓名: {$customerLogin->name}\n\n";
}
