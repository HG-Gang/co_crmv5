<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:26
 */

/**
 * 数据迁移状态检查命令。
 *
 * 文件功能：
 * - 检查新数据库（co_crmv5）user_logins / user_infos / user_auths 三张表的
 *   数据量，判断旧数据迁移是否已执行；
 * - 若已迁移，抽样验证密码哈希格式（用 Hash::check 测试统一密码 123456），
 *   并列出代理/客户测试账号样例；
 * - 若未迁移，尝试连接旧数据库（old_crm）统计可迁移记录数并给出迁移提示。
 *
 * 适用场景：
 * - 数据迁移前后手动执行，快速确认迁移状态与密码格式是否正常。
 *
 * 入参例子：
 * - php artisan migration:check-status
 *
 * 返回值：
 * - 始终返回 0（检查结果通过控制台输出表达，不因检查项失败而改变退出码）。
 *
 * 异常或失败场景：
 * - 新库查询异常会被捕获并输出错误信息（不中断命令）；
 * - 旧库连接失败仅输出错误提示，不影响新库检查继续执行。
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CheckMigrationStatus extends Command
{
    /** @var string 命令签名（无参数）。 */
    protected $signature = 'migration:check-status';

    /** @var string 命令说明。 */
    protected $description = '检查数据迁移状态和密码格式';

    /**
     * 执行命令：输出迁移状态、密码验证结果与测试账号样例。
     *
     * @return int 始终返回 0。
     */
    public function handle()
    {
        $this->info('========================================');
        $this->info('   数据迁移状态检查');
        $this->info('========================================');
        $this->newLine();

        try {
            // 检查新数据库
            $loginCount = DB::table('user_logins')->count();
            $infoCount = DB::table('user_infos')->count();
            $authCount = DB::table('user_auths')->count();

            $this->line("新数据库 (co_crmv5):");
            $this->line("  user_logins: {$loginCount} 条");
            $this->line("  user_infos: {$infoCount} 条");
            $this->line("  user_auths: {$authCount} 条");
            $this->newLine();

            if ($loginCount === 0) {
                $this->warn('⚠️  新数据库为空，迁移尚未执行');
                $this->line('   请运行: php artisan migrate:old-data');
                $this->newLine();

                // 检查旧数据库
                try {
                    $oldUserCount = DB::connection('old_crm')->table('user')->where('voided', '1')->count();
                    $oldAgentCount = DB::connection('old_crm')->table('agents')->where('voided', '1')->count();
                    $this->line("旧数据库 (hank_zl_data):");
                    $this->line("  user表: {$oldUserCount} 条");
                    $this->line("  agents表: {$oldAgentCount} 条");
                    $this->info("  ✓ 旧数据库连接正常");
                    $this->newLine();
                } catch (\Exception $e) {
                    $this->error("❌ 旧数据库连接失败: " . $e->getMessage());
                    $this->newLine();
                }
            } else {
                $this->info("✓ 数据已迁移");
                $this->newLine();

                // 检查密码格式
                $sampleUser = DB::table('user_logins')->first();
                if ($sampleUser) {
                    $this->line("密码验证测试:");
                    $this->line("  用户邮箱: {$sampleUser->email}");
                    $this->line("  密码哈希长度: " . strlen($sampleUser->password) . " 字符");
                    $this->line("  密码前缀: " . substr($sampleUser->password, 0, 7) . "...");

                    // 测试密码验证
                    $testResult = Hash::check('123456', $sampleUser->password);
                    if ($testResult) {
                        $this->info("  Hash::check('123456'): ✓ 通过");
                    } else {
                        $this->error("  Hash::check('123456'): ❌ 失败");
                    }
                    $this->newLine();

                    if (!$testResult) {
                        $this->warn("⚠️  密码验证失败！可能原因:");
                        $this->line("   1. 密码未重置（迁移时保留了旧密码）");
                        $this->line("   2. 密码哈希算法不匹配");
                        $this->line("   3. 密码字段存储格式错误");
                        $this->newLine();
                        $this->info("解决方案: 重新执行密码重置");
                        $this->line("   php artisan password:reset-all 123456");
                        $this->newLine();
                    } else {
                        $this->info("✓ 密码格式正确，可以正常登录");
                        $this->newLine();
                    }
                }

                // 显示测试账号
                $this->line("测试账号样例:");
                $agents = DB::table('user_logins')
                    ->where('account_type', 1)
                    ->limit(2)
                    ->get(['email', 'user_id']);

                $customers = DB::table('user_logins')
                    ->where('account_type', 2)
                    ->limit(2)
                    ->get(['email', 'user_id']);

                $this->line("  代理账号:");
                foreach ($agents as $agent) {
                    $this->line("    📧 {$agent->email} (ID: {$agent->user_id})");
                }

                $this->newLine();
                $this->line("  客户账号:");
                foreach ($customers as $customer) {
                    $this->line("    📧 {$customer->email} (ID: {$customer->user_id})");
                }

                $this->newLine();
                $this->info("  统一密码: 123456");
            }

        } catch (\Exception $e) {
            $this->error("❌ 检查失败: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }

        $this->newLine();
        $this->info('========================================');
        return 0;
    }
}
