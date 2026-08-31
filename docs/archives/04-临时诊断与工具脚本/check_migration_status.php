<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "========================================\n";
echo "   数据迁移状态检查\n";
echo "========================================\n\n";

try {
    // 检查新数据库
    $loginCount = DB::table('user_logins')->count();
    $infoCount = DB::table('user_infos')->count();
    $authCount = DB::table('user_auths')->count();

    echo "新数据库 (co_crmv5):\n";
    echo "  user_logins: {$loginCount} 条\n";
    echo "  user_infos: {$infoCount} 条\n";
    echo "  user_auths: {$authCount} 条\n\n";

    if ($loginCount === 0) {
        echo "⚠️  新数据库为空，迁移尚未执行\n";
        echo "   请运行: php artisan migrate:old-data\n\n";

        // 检查旧数据库
        try {
            $oldUserCount = DB::connection('old_crm')->table('user')->where('voided', '1')->count();
            $oldAgentCount = DB::connection('old_crm')->table('agents')->where('voided', '1')->count();
            echo "旧数据库 (hank_zl_data):\n";
            echo "  user表: {$oldUserCount} 条\n";
            echo "  agents表: {$oldAgentCount} 条\n";
            echo "  ✓ 旧数据库连接正常\n\n";
        } catch (Exception $e) {
            echo "❌ 旧数据库连接失败: " . $e->getMessage() . "\n\n";
        }
    } else {
        echo "✓ 数据已迁移\n\n";

        // 检查密码格式
        $sampleUser = DB::table('user_logins')->first();
        if ($sampleUser) {
            echo "密码验证测试:\n";
            echo "  用户邮箱: {$sampleUser->email}\n";
            echo "  密码哈希长度: " . strlen($sampleUser->password) . " 字符\n";
            echo "  密码前缀: " . substr($sampleUser->password, 0, 7) . "...\n";

            // 测试密码验证
            $testResult = Hash::check('123456', $sampleUser->password);
            echo "  Hash::check('123456'): " . ($testResult ? '✓ 通过' : '❌ 失败') . "\n\n";

            if (!$testResult) {
                echo "⚠️  密码验证失败！可能原因:\n";
                echo "   1. 密码未重置（迁移时保留了旧密码）\n";
                echo "   2. 密码哈希算法不匹配\n";
                echo "   3. 密码字段存储格式错误\n\n";
                echo "解决方案: 重新执行密码重置\n";
                echo "   php artisan password:reset-all 123456\n\n";
            } else {
                echo "✓ 密码格式正确，可以正常登录\n\n";
            }
        }

        // 显示测试账号
        echo "测试账号样例:\n";
        $agents = DB::table('user_logins')
            ->where('account_type', 1)
            ->limit(2)
            ->get(['email', 'user_id']);

        $customers = DB::table('user_logins')
            ->where('account_type', 2)
            ->limit(2)
            ->get(['email', 'user_id']);

        echo "  代理账号:\n";
        foreach ($agents as $agent) {
            echo "    📧 {$agent->email} (ID: {$agent->user_id})\n";
        }

        echo "\n  客户账号:\n";
        foreach ($customers as $customer) {
            echo "    📧 {$customer->email} (ID: {$customer->user_id})\n";
        }

        echo "\n  统一密码: 123456\n";
    }

} catch (Exception $e) {
    echo "❌ 检查失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n========================================\n";
