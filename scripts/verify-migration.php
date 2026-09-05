<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "数据验证\n";
echo "====================================\n\n";

// 1. 管理员表
$adminCount = DB::table('admins')->count();
echo "admins表：{$adminCount} 条\n";

// 2. 用户登录表（按账户类型统计）
$agentLogins = DB::table('user_logins')->where('account_type', 1)->count();
$customerLogins = DB::table('user_logins')->where('account_type', 2)->count();
echo "user_logins表：代理 {$agentLogins} + 客户 {$customerLogins} = " . ($agentLogins + $customerLogins) . " 条\n";

// 3. 用户信息表
$agentInfos = DB::table('user_infos')->where('account_type', 1)->count();
$customerInfos = DB::table('user_infos')->where('account_type', 2)->count();
echo "user_infos表：代理 {$agentInfos} + 客户 {$customerInfos} = " . ($agentInfos + $customerInfos) . " 条\n";

// 4. 交易记录表
$tradesCount = DB::table('mt4_trades')->count();
echo "mt4_trades表：{$tradesCount} 条\n";

echo "\n====================================\n";
echo "ID范围检查\n";
echo "====================================\n\n";

// 5. 代理商ID范围
$agentMinId = DB::table('user_infos')->where('account_type', 1)->min('user_id');
$agentMaxId = DB::table('user_infos')->where('account_type', 1)->max('user_id');
echo "代理商ID范围：{$agentMinId} - {$agentMaxId}\n";

// 6. 客户ID范围
$customerMinId = DB::table('user_infos')->where('account_type', 2)->min('user_id');
$customerMaxId = DB::table('user_infos')->where('account_type', 2)->max('user_id');
echo "客户ID范围：{$customerMinId} - {$customerMaxId}\n";

echo "\n====================================\n";
echo "数据一致性检查\n";
echo "====================================\n\n";

// 7. user_logins与user_infos的user_id一致性
$loginsWithoutInfos = DB::table('user_logins')
    ->leftJoin('user_infos', 'user_logins.user_id', '=', 'user_infos.user_id')
    ->whereNull('user_infos.user_id')
    ->count();
echo "孤立的user_logins（无对应user_infos）：{$loginsWithoutInfos} 条\n";

$infosWithoutLogins = DB::table('user_infos')
    ->leftJoin('user_logins', 'user_infos.user_id', '=', 'user_logins.user_id')
    ->whereNull('user_logins.user_id')
    ->count();
echo "孤立的user_infos（无对应user_logins）：{$infosWithoutLogins} 条\n";

// 8. 邮箱唯一性检查
$duplicateEmails = DB::table('user_logins')
    ->select('email', DB::raw('COUNT(*) as count'))
    ->groupBy('email')
    ->having('count', '>', 1)
    ->count();
echo "重复邮箱数量：{$duplicateEmails} 个\n";

// 9. 未平仓交易数量（close_time为NULL）
$openTrades = DB::table('mt4_trades')->whereNull('close_time')->count();
echo "未平仓交易：{$openTrades} 条\n";

// 10. 已平仓交易数量
$closedTrades = DB::table('mt4_trades')->whereNotNull('close_time')->count();
echo "已平仓交易：{$closedTrades} 条\n";

echo "\n====================================\n";
echo "密码验证（抽样检查）\n";
echo "====================================\n\n";

// 11. 抽查3个代理账号密码
$agentSamples = DB::table('user_logins')
    ->where('account_type', 1)
    ->limit(3)
    ->get(['user_id', 'email', 'password']);

echo "代理账号密码检查（密码应为123456）：\n";
foreach ($agentSamples as $agent) {
    $valid = Hash::check('123456', $agent->password) ? '✓' : '✗';
    echo "  {$valid} user_id={$agent->user_id}, email={$agent->email}\n";
}

// 12. 抽查3个管理员账号密码
$adminSamples = DB::table('admins')
    ->limit(3)
    ->get(['id', 'username', 'password']);

echo "\n管理员账号密码检查（密码应为abc123）：\n";
foreach ($adminSamples as $admin) {
    $valid = Hash::check('abc123', $admin->password) ? '✓' : '✗';
    echo "  {$valid} id={$admin->id}, username={$admin->username}\n";
}
