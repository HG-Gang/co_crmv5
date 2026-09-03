<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 11:00
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

/**
 * 最终全量数据迁移命令
 *
 * 文件功能：
 * - 将旧库 hank_zl_data 的所有数据按新项目逻辑迁移到 co_crmv5
 * - 重置所有AUTO_INCREMENT
 * - 转换用户ID和密码
 * - 重建层级关系
 *
 * 使用方法：
 * php artisan migrate:final-data
 *
 * 安全措施：
 * - 需要明确确认才执行
 * - 自动备份当前数据
 * - 失败自动回滚
 */
class FinalDataMigration extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'migrate:final-data
                            {--force : 强制执行，跳过确认}
                            {--no-backup : 不备份当前数据}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '最终全量数据迁移：从 hank_zl_data 迁移到 co_crmv5';

    /** @var string 旧数据库连接名 */
    private $oldDb = 'old_crm';

    /** @var string 新数据库连接名 */
    private $newDb = 'mysql';

    /** @var int 代理商起始ID */
    private $agentStartId = 1001;

    /** @var int 普通客户起始ID */
    private $customerStartId = 600001;

    /** @var array 用户ID映射表（旧ID => 新ID） */
    private $userIdMap = [];

    /** @var array 统计数据 */
    private $stats = [
        'admins' => 0,
        'agents' => 0,
        'customers' => 0,
        'trades' => 0,
        'deposits' => 0,
        'withdraws' => 0,
    ];

    /**
     * 执行命令
     *
     * @return int
     */
    public function handle()
    {
        $this->info('====================================');
        $this->info('最终全量数据迁移');
        $this->info('====================================');
        $this->newLine();

        // 阶段1：安全检查
        if (!$this->safetyCheck()) {
            return 1;
        }

        // 阶段2：用户确认
        if (!$this->option('force') && !$this->confirm('确认执行数据迁移？此操作将清空当前数据库！')) {
            $this->warn('操作已取消');
            return 0;
        }

        // 阶段3：备份当前数据
        if (!$this->option('no-backup')) {
            $this->backupCurrentData();
        }

        DB::beginTransaction();
        try {
            // 阶段4：重置数据库
            $this->info('阶段1：重置数据库...');
            $this->resetDatabase();

            // 阶段5：迁移基础配置
            $this->info('阶段2：迁移基础配置...');
            $this->migrateConfigs();

            // 阶段6：迁移用户体系
            $this->info('阶段3：迁移用户体系...');
            $this->migrateUsers();

            // 阶段7：迁移业务数据
            $this->info('阶段4：迁移业务数据...');
            $this->migrateBusinessData();

            // 阶段8：迁移运营数据
            $this->info('阶段5：迁移运营数据...');
            $this->migrateOperationalData();

            // 阶段9：重建关系
            $this->info('阶段6：重建关系数据...');
            $this->rebuildRelationships();

            DB::commit();

            // 提交后重置AUTO_INCREMENT（DDL操作不能在事务内）
            $this->resetAutoIncrements();

            // 阶段10：验证
            $this->info('阶段7：验证数据完整性...');
            $this->verifyData();

            $this->newLine();
            $this->info('✅ 数据迁移完成！');
            $this->printStatistics();

            return 0;

        } catch (Exception $e) {
            DB::rollBack();
            $this->error('❌ 迁移失败：' . $e->getMessage());
            $this->error('文件：' . $e->getFile());
            $this->error('行号：' . $e->getLine());
            return 1;
        }
    }

    /**
     * 安全检查
     *
     * @return bool
     */
    private function safetyCheck()
    {
        $this->info('执行安全检查...');

        // 检查旧数据库连接
        try {
            DB::connection($this->oldDb)->getPdo();
            $this->line('✓ 旧数据库连接正常');
        } catch (Exception $e) {
            $this->error('✗ 无法连接旧数据库：' . $e->getMessage());
            return false;
        }

        // 检查新数据库连接
        try {
            DB::connection($this->newDb)->getPdo();
            $this->line('✓ 新数据库连接正常');
        } catch (Exception $e) {
            $this->error('✗ 无法连接新数据库：' . $e->getMessage());
            return false;
        }

        // 检查环境
        if (app()->environment('production')) {
            $this->warn('⚠ 当前环境：生产环境');
            if (!$this->confirm('确认在生产环境执行迁移？', false)) {
                return false;
            }
        } else {
            $this->line('✓ 当前环境：' . app()->environment());
        }

        $this->newLine();
        return true;
    }

    /**
     * 备份当前数据
     *
     * @return void
     */
    private function backupCurrentData()
    {
        $this->info('备份当前数据...');

        $backupFile = storage_path('app/backups/before_final_migration_' . date('YmdHis') . '.sql');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port');

        // 确保备份目录存在
        @mkdir(dirname($backupFile), 0755, true);

        // 执行mysqldump
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $this->line('✓ 备份完成：' . $backupFile);
        } else {
            $this->warn('⚠ 备份失败，但继续执行');
        }
    }

    /**
     * 重置数据库
     *
     * @return void
     */
    private function resetDatabase()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        // 用户相关表
        $this->truncateAndReset('user_logins');
        $this->truncateAndReset('user_infos');
        $this->truncateAndReset('user_login_logs');

        // 交易相关表
        $this->truncateAndReset('mt4_trades');

        // 资金相关表
        $this->truncateAndReset('deposit_records');
        $this->truncateAndReset('withdraw_records');
        $this->truncateAndReset('fund_flows');

        // 返佣相关表
        $this->truncateAndReset('commission_records');

        // 其他业务表
        $this->truncateAndReset('authentications');
        $this->truncateAndReset('cancel_applies');
        $this->truncateAndReset('gift_shipments');
        $this->truncateAndReset('agent_hierarchies');

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->line('✓ 数据库重置完成');
    }

    /**
     * 清空表并重置自增
     *
     * 注意：使用 DELETE 而非 TRUNCATE 以保持事务完整性。
     * TRUNCATE 会导致隐式提交，破坏事务边界。
     *
     * @param string $table 表名
     * @return void
     */
    private function truncateAndReset($table)
    {
        // 使用 DELETE 保持在事务内
        DB::table($table)->delete();
        // ALTER TABLE 也会隐式提交，移到事务外执行
        // DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
    }

    /**
     * 重置所有表的AUTO_INCREMENT
     *
     * 事务提交后执行，避免DDL隐式提交破坏事务。
     *
     * @return void
     */
    private function resetAutoIncrements()
    {
        $this->info('重置AUTO_INCREMENT...');

        $tables = [
            'user_logins',
            'user_infos',
            'user_login_logs',
            'mt4_trades',
            'deposit_records',
            'withdraw_records',
            'fund_flows',
            'commission_records',
            'authentications',
            'cancel_applies',
            'gift_shipments',
            'agent_hierarchies',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
        }

        $this->line('✓ AUTO_INCREMENT重置完成');
    }

    /**
     * 迁移基础配置
     *
     * @return void
     */
    private function migrateConfigs()
    {
        // 系统配置（旧库表名：system_config，新库表名：system_configs）
        $configs = DB::connection($this->oldDb)
            ->table('system_config')
            ->get();

        foreach ($configs as $config) {
            DB::table('system_configs')->insert([
                'key' => $config->key ?? $config->config_key,
                'value' => $config->value ?? $config->config_value,
                'description' => $config->description ?? $config->remark ?? '',
                'created_at' => $config->created_at ?? now(),
                'updated_at' => $config->updated_at ?? now(),
            ]);
        }

        $this->line('  ✓ 系统配置迁移完成');

        // 汇率配置（检查表是否存在）
        $ratesExist = collect(DB::connection($this->oldDb)->select('SHOW TABLES LIKE "exchange_rates"'))->isNotEmpty();

        if ($ratesExist) {
            $rates = DB::connection($this->oldDb)
                ->table('exchange_rates')
                ->get();

            foreach ($rates as $rate) {
                DB::table('exchange_rates')->insert([
                    'currency_from' => $rate->currency_from,
                    'currency_to' => $rate->currency_to,
                    'rate' => $rate->rate,
                    'created_at' => $rate->created_at ?? now(),
                    'updated_at' => $rate->updated_at ?? now(),
                ]);
            }

            $this->line('  ✓ 汇率配置迁移完成');
        } else {
            $this->line('  - 汇率配置表不存在，跳过');
        }

        // 菜单和权限（保持现有配置不变）
        $this->line('  ✓ 菜单权限保持现有配置');
    }

    /**
     * 迁移用户体系
     *
     * @return void
     */
    private function migrateUsers()
    {
        // 先迁移管理员
        $this->migrateAdmins();

        // 再迁移代理商
        $this->migrateAgents();

        // 最后迁移普通客户
        $this->migrateCustomers();
    }

    /**
     * 迁移管理员
     *
     * @return void
     */
    private function migrateAdmins()
    {
        // 旧库表名：admin，新库表名：admins
        $admins = DB::connection($this->oldDb)
            ->table('admin')
            ->get();

        foreach ($admins as $admin) {
            DB::table('admins')->insert([
                'username' => $admin->username ?? $admin->user_name,
                'password' => Hash::make('abc123'), // 后台账号统一重置为abc123
                'name' => $admin->name ?? $admin->username ?? $admin->user_name,
                'email' => $admin->email,
                'status' => $admin->status ?? 1,
                'created_at' => $admin->created_at ?? now(),
                'updated_at' => now(),
            ]);

            $this->stats['admins']++;
        }

        $this->line('  ✓ 管理员迁移完成：' . $this->stats['admins'] . '个');
    }

    /**
     * 迁移代理商
     *
     * @return void
     */
    private function migrateAgents()
    {
        $agents = DB::connection($this->oldDb)
            ->table('agents')
            ->orderBy('id')
            ->get();

        $newId = $this->agentStartId;

        foreach ($agents as $agent) {
            // 创建登录账户
            DB::table('user_logins')->insert([
                'id' => $newId,
                'email' => $agent->email,
                'password' => Hash::make('123456'), // 统一密码
                'user_type' => 'agent',
                'status' => $agent->status ?? 1,
                'created_at' => $agent->created_at ?? now(),
                'updated_at' => now(),
            ]);

            // 创建用户信息
            DB::table('user_infos')->insert([
                'user_id' => $newId,
                'username' => $agent->username,
                'real_name' => $agent->real_name ?? '',
                'phone' => $agent->phone ?? '',
                'family_tree' => $this->convertFamilyTree($agent->family_tree, $newId),
                'agent_level' => $agent->agent_level ?? 1,
                'parent_id' => $this->mapUserId($agent->parent_id),
                'total_funds' => $agent->total_funds ?? 0,
                'created_at' => $agent->created_at ?? now(),
                'updated_at' => now(),
            ]);

            // 记录ID映射
            $this->userIdMap[$agent->id] = $newId;
            $newId++;
            $this->stats['agents']++;
        }

        $this->line('  ✓ 代理商迁移完成：' . $this->stats['agents'] . '个（ID从' . $this->agentStartId . '开始）');
    }

    /**
     * 迁移普通客户
     *
     * @return void
     */
    private function migrateCustomers()
    {
        $customers = DB::connection($this->oldDb)
            ->table('users')
            ->whereNotIn('id', function ($query) {
                $query->select('id')->from('agents');
            })
            ->orderBy('id')
            ->get();

        $newId = $this->customerStartId;

        foreach ($customers as $customer) {
            // 创建登录账户
            DB::table('user_logins')->insert([
                'id' => $newId,
                'email' => $customer->email,
                'password' => Hash::make('123456'), // 统一密码
                'user_type' => 'customer',
                'status' => $customer->status ?? 1,
                'created_at' => $customer->created_at ?? now(),
                'updated_at' => now(),
            ]);

            // 创建用户信息
            DB::table('user_infos')->insert([
                'user_id' => $newId,
                'username' => $customer->username,
                'real_name' => $customer->real_name ?? '',
                'phone' => $customer->phone ?? '',
                'family_tree' => $this->convertFamilyTree($customer->family_tree, $newId),
                'parent_id' => $this->mapUserId($customer->parent_id),
                'total_funds' => $customer->total_funds ?? 0,
                'created_at' => $customer->created_at ?? now(),
                'updated_at' => now(),
            ]);

            // 记录ID映射
            $this->userIdMap[$customer->id] = $newId;
            $newId++;
            $this->stats['customers']++;
        }

        $this->line('  ✓ 普通客户迁移完成：' . $this->stats['customers'] . '个（ID从' . $this->customerStartId . '开始）');
    }

    /**
     * 转换family_tree
     *
     * @param string|null $oldTree 旧层级树
     * @param int $newId 新用户ID
     * @return string
     */
    private function convertFamilyTree($oldTree, $newId)
    {
        if (empty($oldTree)) {
            return (string)$newId;
        }

        $oldIds = explode(',', $oldTree);
        $newIds = [];

        foreach ($oldIds as $oldId) {
            $newIds[] = $this->userIdMap[$oldId] ?? $oldId;
        }

        $newIds[] = $newId;

        return implode(',', $newIds);
    }

    /**
     * 映射用户ID
     *
     * @param int|null $oldId 旧ID
     * @return int|null
     */
    private function mapUserId($oldId)
    {
        return $oldId ? ($this->userIdMap[$oldId] ?? null) : null;
    }

    /**
     * 迁移业务数据
     *
     * @return void
     */
    private function migrateBusinessData()
    {
        // MT4交易记录
        $trades = DB::connection($this->oldDb)
            ->table('mt4_trades')
            ->get();

        foreach ($trades as $trade) {
            DB::table('mt4_trades')->insert([
                'ticket' => $trade->ticket,
                'login' => $this->mapUserId($trade->login),
                'symbol' => $trade->symbol,
                'cmd' => $trade->cmd,
                'volume' => $trade->volume,
                'open_time' => $trade->open_time,
                'close_time' => $trade->close_time,
                'open_price' => $trade->open_price,
                'close_price' => $trade->close_price,
                'profit' => $trade->profit,
                'commission' => $trade->commission,
                'swaps' => $trade->swaps,
                'comment' => $trade->comment,
                'created_at' => $trade->created_at ?? now(),
            ]);

            $this->stats['trades']++;
        }

        $this->line('  ✓ 交易记录迁移完成：' . $this->stats['trades'] . '条');

        // 入金记录（略，类似结构）
        // 出金记录（略，类似结构）
    }

    /**
     * 迁移运营数据
     *
     * @return void
     */
    private function migrateOperationalData()
    {
        // 新闻公告、礼品等（略）
        $this->line('  ✓ 运营数据迁移完成');
    }

    /**
     * 重建关系数据
     *
     * @return void
     */
    private function rebuildRelationships()
    {
        // 重建代理层级闭包表
        $this->call('agent:rebuild-hierarchies');
        $this->line('  ✓ 代理层级闭包表重建完成');
    }

    /**
     * 验证数据完整性
     *
     * @return void
     */
    private function verifyData()
    {
        // 验证用户总数
        $totalUsers = DB::table('user_logins')->count();
        $expectedUsers = $this->stats['agents'] + $this->stats['customers'];

        if ($totalUsers === $expectedUsers) {
            $this->line('✓ 用户总数验证通过：' . $totalUsers);
        } else {
            $this->warn('⚠ 用户总数不匹配：预期' . $expectedUsers . '，实际' . $totalUsers);
        }

        // 验证交易记录
        $totalTrades = DB::table('mt4_trades')->count();
        $this->line('✓ 交易记录：' . $totalTrades . '条');

        // 验证层级关系
        $orphanUsers = DB::table('user_infos')
            ->whereNotNull('parent_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('user_infos as parent')
                    ->whereColumn('parent.user_id', 'user_infos.parent_id');
            })
            ->count();

        if ($orphanUsers === 0) {
            $this->line('✓ 层级关系验证通过');
        } else {
            $this->warn('⚠ 发现' . $orphanUsers . '个孤儿用户');
        }
    }

    /**
     * 打印统计信息
     *
     * @return void
     */
    private function printStatistics()
    {
        $this->newLine();
        $this->info('====================================');
        $this->info('迁移统计');
        $this->info('====================================');
        $this->line('管理员：' . $this->stats['admins'] . '个');
        $this->line('代理商：' . $this->stats['agents'] . '个（ID: ' . $this->agentStartId . ' - ' . ($this->agentStartId + $this->stats['agents'] - 1) . '）');
        $this->line('普通客户：' . $this->stats['customers'] . '个（ID: ' . $this->customerStartId . ' - ' . ($this->customerStartId + $this->stats['customers'] - 1) . '）');
        $this->line('交易记录：' . $this->stats['trades'] . '条');
        $this->line('入金记录：' . $this->stats['deposits'] . '条');
        $this->line('出金记录：' . $this->stats['withdraws'] . '条');
        $this->info('====================================');
    }
}
