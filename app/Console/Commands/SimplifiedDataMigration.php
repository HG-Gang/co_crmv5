<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 13:00
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

/**
 * 简化版数据迁移命令
 *
 * 文件功能：
 * - 迁移旧库核心数据到新库
 * - 用户ID重新编号（agents: 1001+, customers: 600001+）
 * - 密码统一重置（前台123456，后台abc123）
 *
 * 使用方法：
 * php artisan migrate:data-simple --force
 */
class SimplifiedDataMigration extends Command
{
    protected $signature = 'migrate:data-simple {--force : 强制执行}';
    protected $description = '简化版数据迁移：仅迁移核心业务数据';

    private $oldDb = 'old_crm';
    private $newDb = 'mysql';
    private $userIdMap = []; // 旧ID => 新ID
    private $emailUsage = []; // 邮箱使用计数（按邮箱记录已处理次数）
    private $globalEmailCounts = []; // 全局邮箱计数（agents + user表总数）
    private $passwordHash123456; // 预生成的123456哈希
    private $passwordHashAbc123; // 预生成的abc123哈希
    private $stats = [
        'admins' => 0,
        'agents' => 0,
        'customers' => 0,
        'mt4_trades' => 0,
        'deposits' => 0,
        'withdraws' => 0,
    ];

    public function handle()
    {
        // 提升内存限制（处理87万条交易记录）
        ini_set('memory_limit', '512M');

        $this->info('====================================');
        $this->info('简化版数据迁移');
        $this->info('====================================');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('确认执行？将清空co_crmv5数据库！')) {
            return 0;
        }

        // 预生成密码哈希（避免17,952次bcrypt调用）
        $this->info('预生成密码哈希...');
        $this->passwordHash123456 = Hash::make('123456');
        $this->passwordHashAbc123 = Hash::make('abc123');
        $this->line('✓ 密码哈希已生成');
        $this->newLine();

        // 预扫描全局邮箱：agents + user 表，建立全局邮箱计数
        $this->info('预扫描邮箱重复情况...');
        $this->buildGlobalEmailCounts();
        $this->line('✓ 邮箱扫描完成');
        $this->newLine();

        DB::beginTransaction();
        try {
            $this->info('阶段1：清空目标表...');
            $this->cleanTables();

            $this->info('阶段2：迁移管理员...');
            $this->migrateAdmins();

            $this->info('阶段3：迁移代理商...');
            $this->migrateAgents();

            $this->info('阶段4：迁移客户...');
            $this->migrateCustomers();

            $this->info('阶段5：迁移交易记录...');
            $this->migrateTrades();

            $this->info('阶段6：迁移入金记录...');
            $this->migrateDeposits();

            $this->info('阶段7：迁移出金记录...');
            $this->migrateWithdraws();

            DB::commit();

            $this->newLine();
            $this->info('✅ 迁移完成！');
            $this->printStats();

            return 0;

        } catch (Exception $e) {
            DB::rollBack();
            $this->error('❌ 迁移失败：' . $e->getMessage());
            $this->error('文件：' . $e->getFile() . ':' . $e->getLine());
            return 1;
        }
    }

    private function cleanTables()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = [
            'admins', 'user_logins', 'user_infos',
            'mt4_trades', 'deposit_records', 'withdraw_records'
        ];

        foreach ($tables as $table) {
            DB::table($table)->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->line('✓ 表已清空');
    }

    private function buildGlobalEmailCounts()
    {
        // 扫描agents表
        $agentEmails = DB::connection($this->oldDb)
            ->table('agents')
            ->pluck('email');

        foreach ($agentEmails as $email) {
            $email = strtolower($email); // 统一小写
            $this->globalEmailCounts[$email] = ($this->globalEmailCounts[$email] ?? 0) + 1;
        }

        // 扫描user表
        $userEmails = DB::connection($this->oldDb)
            ->table('user')
            ->pluck('email');

        foreach ($userEmails as $email) {
            $email = strtolower($email); // 统一小写
            $this->globalEmailCounts[$email] = ($this->globalEmailCounts[$email] ?? 0) + 1;
        }

        $duplicateCount = count(array_filter($this->globalEmailCounts, fn($count) => $count > 1));
        $this->line("  发现 {$duplicateCount} 个重复邮箱（已统一小写）");
    }

    private function migrateAdmins()
    {
        $admins = DB::connection($this->oldDb)->table('admin')->get();

        foreach ($admins as $admin) {
            DB::table('admins')->insert([
                'username' => $admin->username,
                'password' => $this->passwordHashAbc123,
                'email' => $admin->email ?? '',
                'mobile' => $admin->mobile ?? null,
                'status' => $admin->state ?? 1,
                'created_at' => strtotime($admin->created_at ?? 'now'),
                'updated_at' => time(),
            ]);

            $this->stats['admins']++;
        }

        $this->line("  ✓ 管理员：{$this->stats['admins']}个");
    }

    private function migrateAgents()
    {
        $agents = DB::connection($this->oldDb)
            ->table('agents')
            ->orderBy('user_id')
            ->get();

        $newId = 1001;
        $now = time();

        $loginBatch = [];
        $infoBatch = [];

        foreach ($agents as $agent) {
            // 处理重复邮箱：使用全局计数判断是否需要添加前缀
            // 统一转小写避免大小写差异导致计数错误（MySQL索引不区分大小写）
            $originalEmail = $agent->email;
            $email = strtolower($originalEmail);

            if (($this->globalEmailCounts[$email] ?? 1) > 1) {
                if (isset($this->emailUsage[$email])) {
                    $this->emailUsage[$email]++;
                } else {
                    $this->emailUsage[$email] = 0;
                }
                $email = $this->emailUsage[$email] . '_' . $email;
            }

            // 准备user_logins批量数据
            $loginBatch[] = [
                'user_id' => $newId,
                'email' => $email,
                'password' => $this->passwordHash123456,
                'account_type' => 1,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // 准备user_infos批量数据
            $parentId = 0;
            if ($agent->parent_id && isset($this->userIdMap[$agent->parent_id])) {
                $parentId = $this->userIdMap[$agent->parent_id];
            }

            $infoBatch[] = [
                'user_id' => $newId,
                'login_id' => $newId,
                'user_name' => $agent->user_name,
                'phone' => $agent->phone ?? '',
                'parent_id' => $parentId,
                'account_type' => 1,
                'family_tree' => '',
                'total_funds' => $agent->user_money ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->userIdMap[$agent->user_id] = $newId;
            $newId++;
            $this->stats['agents']++;

            // 每1000条批量插入一次
            if (count($loginBatch) >= 1000) {
                DB::table('user_logins')->insert($loginBatch);
                DB::table('user_infos')->insert($infoBatch);
                $this->line("    已处理：{$this->stats['agents']} 个代理...");
                $loginBatch = [];
                $infoBatch = [];
            }
        }

        // 插入剩余数据
        if (count($loginBatch) > 0) {
            DB::table('user_logins')->insert($loginBatch);
            DB::table('user_infos')->insert($infoBatch);
        }

        $this->line("  ✓ 代理商：{$this->stats['agents']}个 (ID: 1001-" . ($newId - 1) . ")");
    }

    private function migrateCustomers()
    {
        // 获取agents的user_id列表
        $agentIds = DB::connection($this->oldDb)
            ->table('agents')
            ->pluck('user_id')
            ->toArray();

        $customers = DB::connection($this->oldDb)
            ->table('user')
            ->whereNotIn('user_id', $agentIds)
            ->orderBy('user_id')
            ->get();

        $newId = 600001;
        $now = time();

        $loginBatch = [];
        $infoBatch = [];

        foreach ($customers as $customer) {
            // 处理重复邮箱：使用全局计数判断是否需要添加前缀
            // 统一转小写避免大小写差异导致计数错误（MySQL索引不区分大小写）
            $originalEmail = $customer->email;
            $email = strtolower($originalEmail);

            if (($this->globalEmailCounts[$email] ?? 1) > 1) {
                if (isset($this->emailUsage[$email])) {
                    $this->emailUsage[$email]++;
                } else {
                    $this->emailUsage[$email] = 0;
                }
                $email = $this->emailUsage[$email] . '_' . $email;
            }

            // 准备user_logins批量数据
            $loginBatch[] = [
                'user_id' => $newId,
                'email' => $email,
                'password' => $this->passwordHash123456,
                'account_type' => 2, // 2=客户
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 1, // 1=导入
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // 准备user_infos批量数据
            $parentId = 0;
            if ($customer->parent_id && isset($this->userIdMap[$customer->parent_id])) {
                $parentId = $this->userIdMap[$customer->parent_id];
            }

            $infoBatch[] = [
                'user_id' => $newId,
                'login_id' => $newId,
                'user_name' => $customer->user_name,
                'phone' => $customer->phone ?? '',
                'parent_id' => $parentId,
                'account_type' => 2, // 2=客户
                'family_tree' => '', // 稍后处理
                'total_funds' => $customer->user_money ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->userIdMap[$customer->user_id] = $newId;
            $newId++;
            $this->stats['customers']++;

            // 每1000条批量插入一次
            if (count($loginBatch) >= 1000) {
                DB::table('user_logins')->insert($loginBatch);
                DB::table('user_infos')->insert($infoBatch);
                $this->line("    已处理：{$this->stats['customers']} 个客户...");
                $loginBatch = [];
                $infoBatch = [];
            }
        }

        // 插入剩余数据
        if (count($loginBatch) > 0) {
            DB::table('user_logins')->insert($loginBatch);
            DB::table('user_infos')->insert($infoBatch);
        }

        $this->line("  ✓ 客户：{$this->stats['customers']}个 (ID: 600001-" . ($newId - 1) . ")");
    }

    private function migrateTrades()
    {
        $this->line('  迁移MT4交易记录（872,140条）...');

        // 减小批次大小以降低内存峰值
        $chunkSize = 2000;
        $totalProcessed = 0;

        DB::connection($this->oldDb)
            ->table('mt4_trades')
            ->orderBy('TICKET')
            ->chunk($chunkSize, function ($trades) use (&$totalProcessed) {
                $batch = [];

                foreach ($trades as $trade) {
                    // 映射用户ID
                    $newLogin = $this->userIdMap[$trade->LOGIN] ?? $trade->LOGIN;

                    // 处理close_time：'1970-01-01 00:00:00'表示未平仓，应为NULL
                    $closeTime = null;
                    if ($trade->CLOSE_TIME && $trade->CLOSE_TIME !== '1970-01-01 00:00:00') {
                        $timestamp = strtotime($trade->CLOSE_TIME);
                        // 防止负数时间戳（时区问题导致）
                        $closeTime = ($timestamp > 0) ? $timestamp : null;
                    }

                    // 处理open_time
                    $openTime = strtotime($trade->OPEN_TIME);
                    if ($openTime < 0) {
                        $openTime = 0; // 使用0代替负数
                    }

                    $batch[] = [
                        'ticket' => $trade->TICKET,
                        'login' => $newLogin,
                        'symbol' => $trade->SYMBOL,
                        'cmd' => $trade->CMD,
                        'volume' => $trade->VOLUME,
                        'open_price' => $trade->OPEN_PRICE,
                        'close_price' => $trade->CLOSE_PRICE ?? null,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                        'profit' => $trade->PROFIT ?? 0,
                        'commission' => $trade->COMMISSION ?? 0,
                        'swaps' => $trade->SWAPS ?? 0,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];

                    $this->stats['mt4_trades']++;
                }

                DB::table('mt4_trades')->insert($batch);

                $totalProcessed += count($batch);
                if ($totalProcessed % 10000 === 0) {
                    $this->line("    已处理：{$totalProcessed} 条...");
                }
            });

        $this->line("  ✓ 交易记录：{$this->stats['mt4_trades']}条");
    }

    private function migrateDeposits()
    {
        $this->line('  ⚠️ 入金记录迁移暂时跳过（字段映射需进一步确认）');
        $this->stats['deposits'] = 0;
    }

    private function migrateWithdraws()
    {
        $this->line('  ⚠️ 出金记录迁移暂时跳过（字段映射需进一步确认）');
        $this->stats['withdraws'] = 0;
    }

    private function printStats()
    {
        $this->info('====================================');
        $this->info('迁移统计');
        $this->info('====================================');
        $this->line('管理员：' . $this->stats['admins']);
        $this->line('代理商：' . $this->stats['agents'] . ' (1001-)');
        $this->line('客户：' . $this->stats['customers'] . ' (600001-)');
        $this->line('交易记录：' . number_format($this->stats['mt4_trades']));
        $this->line('入金记录：' . number_format($this->stats['deposits']));
        $this->line('出金记录：' . number_format($this->stats['withdraws']));
        $this->line('总计：' . number_format(array_sum($this->stats)) . ' 条记录');
        $this->info('====================================');
    }
}
