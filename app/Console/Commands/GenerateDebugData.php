<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\DebugDataSeeder;

class GenerateDebugData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:generate-data
                            {--truncate : 清空现有数据（谨慎使用）}
                            {--users=10 : 生成的客户用户数量}
                            {--agents=2 : 生成的代理用户数量}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成CRM系统的调试数据';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始生成CRM系统调试数据...');
        
        $seeder = new DebugDataSeeder();
        
        // 设置选项
        if ($this->option('truncate')) {
            $this->warn('警告：这将清空所有相关表的数据！');
            if (!$this->confirm('确定要继续吗？')) {
                $this->info('操作已取消。');
                return 0;
            }
        }
        
        $this->info('生成配置数据...');
        $seeder->seedSystemConfigs();
        
        $this->info('生成角色权限数据...');
        $seeder->seedRolesAndPermissions();
        
        $this->info('生成代理等级数据...');
        $seeder->seedAgentLevels();
        
        $this->info('生成分组配置数据...');
        $seeder->seedGroupConfigs();
        
        $this->info('生成管理员数据...');
        $seeder->seedAdmins();
        
        $this->info('生成用户数据...');
        $seeder->seedUsers();
        
        $this->info('生成财务数据...');
        $seeder->seedFinancialData();
        
        $this->info('生成交易数据...');
        $seeder->seedTradingData();
        
        $this->info('生成佣金数据...');
        $seeder->seedCommissionData();
        
        $this->info('');
        $this->info('================================');
        $this->info('调试数据生成完成！');
        $this->info('================================');
        $this->info('');
        $this->info('测试账号信息：');
        $this->info('超级管理员: superadmin@co-crm.com / password123');
        $this->info('财务管理员: finance@co-crm.com / password123');
        $this->info('代理用户1: agent1@co-crm.com / password123');
        $this->info('代理用户2: agent2@co-crm.com / password123');
        $this->info('客户用户: customer1@co-crm.com / password123');
        $this->info('');
        $this->info('数据包含：');
        $this->info('- 系统配置');
        $this->info('- 角色权限');
        $this->info('- 代理等级');
        $this->info('- 用户数据（代理+客户）');
        $this->info('- 财务记录（充值+提现）');
        $this->info('- 交易数据');
        $this->info('- 佣金记录');
        
        return 0;
    }
}
