<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:33
 */

/**
 * 生成 CRM 调试数据命令。
 *
 * 文件功能：
 * - 调用 DebugDataSeeder 依次生成系统配置、角色权限、代理等级、分组配置、
 *   管理员、用户、财务、交易、佣金等全套调试数据，并在控制台输出
 *   测试账号与数据清单。
 *
 * 适用场景：
 * - 开发/测试环境初始化演示数据时手动执行。
 *
 * 入参例子：
 * - php artisan debug:generate-data
 * - php artisan debug:generate-data --users=20 --agents=3
 * - php artisan debug:generate-data --truncate
 *
 * 返回值：
 * - 0=生成完成或用户取消截断确认；
 * - 各 seeder 内部异常会直接抛出中断命令（由 Laravel 打印错误）。
 *
 * 异常或失败场景：
 * - --truncate 时先询问确认，用户取消则直接返回 0 不执行任何生成。
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\DebugDataSeeder;

class GenerateDebugData extends Command
{
    /**
     * 命令签名与参数说明。
     *
     * @var string
     */
    protected $signature = 'debug:generate-data
                            {--truncate : 清空现有数据（谨慎使用）}
                            {--users=10 : 生成的客户用户数量}
                            {--agents=2 : 生成的代理用户数量}';

    /**
     * 命令说明。
     *
     * @var string
     */
    protected $description = '生成CRM系统的调试数据';

    /**
     * 执行命令：按顺序调用 DebugDataSeeder 生成各模块调试数据。
     *
     * @return int 0=执行完成。
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
        $this->info('超级管理员: superadmin@co-crm.com / abc123');
        $this->info('财务管理员: finance@co-crm.com / abc123');
        $this->info('代理用户1: agent1@co-crm.com / abc123');
        $this->info('代理用户2: agent2@co-crm.com / abc123');
        $this->info('客户用户: customer1@co-crm.com / abc123');
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
