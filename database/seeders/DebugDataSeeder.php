<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DebugDataSeeder extends Seeder
{
    /**
     * 运行调试数据填充
     * Run the debug data seeder
     */
    public function run()
    {
        $this->command->info('开始生成调试数据...');
        
        // 清空现有数据（可选，谨慎使用）
        // $this->truncateTables();
        
        // 生成基础配置数据
        $this->seedSystemConfigs();
        $this->seedRolesAndPermissions();
        $this->seedAgentLevels();
        $this->seedGroupConfigs();
        
        // 生成用户数据
        $this->seedAdmins();
        $this->seedUsers();
        
        // 生成业务数据
        $this->seedFinancialData();
        $this->seedTradingData();
        $this->seedCommissionData();
        
        $this->command->info('调试数据生成完成！');
    }
    
    /**
     * 清空表数据（谨慎使用）
     * Truncate tables (use with caution)
     */
    public function truncateTables()
    {
        $tables = [
            'admins', 'admin_login_logs', 'roles', 'permissions',
            'user_logins', 'user_infos', 'user_login_logs', 'user_auths',
            'agent_levels', 'group_configs', 'agent_descendants',
            'deposit_records', 'withdraw_records', 'user_trades', 'commission_records',
            'news', 'operation_logs', 'cancel_applies', 'blacklists',
            'payment_channels', 'big_agents', 'voucher_infos'
        ];
        
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
    }
    
    /**
     * 生成系统配置数据
     * Seed system configurations
     */
    public function seedSystemConfigs()
    {
        $configs = [
            [
                'key' => 'system_name',
                'value' => 'CO CRM V5 调试系统',
                'description' => '系统名称',
                'group' => 'general'
            ],
            [
                'key' => 'company_name',
                'value' => 'CO Financial Group',
                'description' => '公司名称',
                'group' => 'general'
            ],
            [
                'key' => 'default_currency',
                'value' => 'USD',
                'description' => '默认货币',
                'group' => 'finance'
            ],
            [
                'key' => 'min_deposit_amount',
                'value' => '100',
                'description' => '最小充值金额',
                'group' => 'finance'
            ],
            [
                'key' => 'max_withdraw_amount',
                'value' => '50000',
                'description' => '最大提现金额',
                'group' => 'finance'
            ],
            [
                'key' => 'commission_rate_base',
                'value' => '0.3',
                'description' => '基础佣金率',
                'group' => 'commission'
            ]
        ];
        
        foreach ($configs as $config) {
            DB::table('system_configs')->updateOrInsert(
                ['key' => $config['key']],
                array_merge($config, [
                    'created_at' => time(),
                    'updated_at' => time()
                ])
            );
        }
    }
    
    /**
     * 生成角色和权限数据
     * Seed roles and permissions
     */
    public function seedRolesAndPermissions()
    {
        // 权限数据
        $permissions = [
            ['name' => '用户管理', 'slug' => 'user.manage', 'guard_type' => 'admin'],
            ['name' => '代理管理', 'slug' => 'agent.manage', 'guard_type' => 'admin'],
            ['name' => '财务管理', 'slug' => 'finance.manage', 'guard_type' => 'admin'],
            ['name' => '系统设置', 'slug' => 'system.manage', 'guard_type' => 'admin'],
            ['name' => '报表查看', 'slug' => 'report.view', 'guard_type' => 'admin'],
            ['name' => '交易管理', 'slug' => 'trade.manage', 'guard_type' => 'admin'],
            ['name' => '客户管理', 'slug' => 'customer.manage', 'guard_type' => 'front'],
            ['name' => '佣金查看', 'slug' => 'commission.view', 'guard_type' => 'front'],
        ];
        
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                array_merge($permission, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ])
            );
        }
        
        // 角色数据
        $roles = [
            [
                'name' => '超级管理员',
                'guard_type' => 'admin',
                'description' => '拥有所有权限',
                'permissions' => json_encode(['user.manage', 'agent.manage', 'finance.manage', 'system.manage', 'report.view', 'trade.manage']),
                'status' => 1
            ],
            [
                'name' => '财务管理员',
                'guard_type' => 'admin',
                'description' => '负责财务相关操作',
                'permissions' => json_encode(['finance.manage', 'report.view']),
                'status' => 1
            ],
            [
                'name' => '代理',
                'guard_type' => 'front',
                'description' => '代理用户',
                'permissions' => json_encode(['customer.manage', 'commission.view']),
                'status' => 1
            ],
            [
                'name' => '客户',
                'guard_type' => 'front',
                'description' => '普通客户',
                'permissions' => json_encode([]),
                'status' => 1
            ]
        ];
        
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name'], 'guard_type' => $role['guard_type']],
                array_merge($role, [
                    'created_at' => time(),
                    'updated_at' => time()
                ])
            );
        }
    }
    
    /**
     * 生成代理等级数据
     * Seed agent levels
     */
    public function seedAgentLevels()
    {
        $levels = [
            ['level_code' => 1, 'name' => '一级代理', 'max_commission' => 30, 'min_commission' => 10, 'user_commission' => 5],
            ['level_code' => 2, 'name' => '二级代理', 'max_commission' => 25, 'min_commission' => 8, 'user_commission' => 4],
            ['level_code' => 3, 'name' => '三级代理', 'max_commission' => 20, 'min_commission' => 6, 'user_commission' => 3],
            ['level_code' => 4, 'name' => '区域代理', 'max_commission' => 15, 'min_commission' => 5, 'user_commission' => 2],
        ];
        
        foreach ($levels as $level) {
            DB::table('agent_levels')->updateOrInsert(
                ['level_code' => $level['level_code']],
                array_merge($level, [
                    'created_at' => time(),
                    'updated_at' => time()
                ])
            );
        }
    }
    
    /**
     * 生成分组配置数据
     * Seed group configurations
     */
    public function seedGroupConfigs()
    {
        $groups = [
            ['name' => '默认组', 'category' => 2, 'has_commission' => 0, 'is_enabled' => 1, 'is_default' => 1],
            ['name' => 'VIP组', 'category' => 2, 'has_commission' => 0, 'is_enabled' => 1, 'is_default' => 0],
            ['name' => '代理组', 'category' => 1, 'has_commission' => 1, 'is_enabled' => 1, 'is_default' => 0],
        ];
        
        foreach ($groups as $group) {
            DB::table('group_configs')->updateOrInsert(
                ['name' => $group['name']],
                array_merge($group, [
                    'created_at' => time(),
                    'updated_at' => time()
                ])
            );
        }
    }
    
    /**
     * 生成管理员数据
     * Seed admin users
     */
    public function seedAdmins()
    {
        $superAdminRole = DB::table('roles')->where('name', '超级管理员')->first();
        $financeAdminRole = DB::table('roles')->where('name', '财务管理员')->first();
        
        $admins = [
            [
                'username' => 'superadmin',
                'email' => 'superadmin@co-crm.com',
                'password' => bcrypt('password123'),
                'mobile' => '13800138000',
                'role_id' => $superAdminRole->id,
                'status' => 1,
                'last_login_ip' => '192.168.1.100',
                'last_login_at' => Carbon::now()->subDays(1),
                'created_by' => 1
            ],
            [
                'username' => 'financeadmin',
                'email' => 'finance@co-crm.com',
                'password' => bcrypt('password123'),
                'mobile' => '13800138001',
                'role_id' => $financeAdminRole->id,
                'status' => 1,
                'last_login_ip' => '192.168.1.101',
                'last_login_at' => Carbon::now()->subHours(2),
                'created_by' => 1
            ]
        ];
        
        foreach ($admins as $admin) {
            DB::table('admins')->updateOrInsert(
                ['email' => $admin['email']],
                array_merge($admin, [
                    'created_at' => time(),
                    'updated_at' => time()
                ])
            );
        }
    }
    
    /**
     * 生成用户数据
     * Seed user data
     */
    public function seedUsers()
    {
        $agentLevels = DB::table('agent_levels')->get()->keyBy('level_code');
        $groups = DB::table('group_configs')->get()->keyBy('name');
        
        // 生成代理用户
        $agents = [
            [
                'name' => '张代理',
                'email' => 'agent1@co-crm.com',
                'password' => bcrypt('password123')
            ],
            [
                'name' => '李代理',
                'email' => 'agent2@co-crm.com',
                'password' => bcrypt('password123')
            ]
        ];
        
        foreach ($agents as $agentData) {
            // 创建用户
            $userId = DB::table('users')->insertGetId(array_merge($agentData, [
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]));
            
            // 创建登录信息
            $loginId = DB::table('user_logins')->insertGetId([
                'user_id' => $userId,
                'email' => $agentData['email'],
                'password' => bcrypt('password123'),
                'account_type' => 1, // 代理
                'is_enabled' => 1,
                'last_login_ip' => '192.168.1.' . rand(100, 200),
                'last_login_at' => Carbon::now()->subDays(rand(1, 30)),
                'created_at' => time(),
                'updated_at' => time()
            ]);
            
            // 创建用户详细信息
            DB::table('user_infos')->insert([
                'user_id' => $userId,
                'login_id' => $loginId,
                'user_name' => $agentData['name'],
                'phone' => '13800' . rand(100000, 999999),
                'level_id' => $agentLevels[1]->id, // 一级代理
                'group_id' => $groups['代理组']->id,
                'parent_id' => 0,
                'family_tree' => '0',
                'total_funds' => rand(10000, 100000),
                'used_margin' => rand(1000, 50000),
                'avail_margin' => rand(1000, 50000),
                'equity' => rand(10000, 100000),
                'effective_credit' => rand(50000, 200000),
                'risk_ratio' => rand(10, 500) / 10,
                'margin_amount' => rand(1000, 50000),
                'leverage' => rand(100, 500),
                'comm_rate' => rand(10, 30),
                'auth_status' => 1,
                'created_at' => time(),
                'updated_at' => time()
            ]);
        }
        
        // 生成客户用户
        for ($i = 1; $i <= 10; $i++) {
            $customerData = [
                'name' => '客户' . $i,
                'email' => 'customer' . $i . '@co-crm.com',
                'password' => bcrypt('password123')
            ];
            
            $userId = DB::table('users')->insertGetId(array_merge($customerData, [
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]));
            
            $loginId = DB::table('user_logins')->insertGetId([
                'user_id' => $userId,
                'email' => $customerData['email'],
                'password' => bcrypt('password123'),
                'account_type' => 2, // 客户
                'is_enabled' => 1,
                'last_login_ip' => '192.168.1.' . rand(100, 200),
                'last_login_at' => Carbon::now()->subDays(rand(1, 30)),
                'created_at' => time(),
                'updated_at' => time()
            ]);
            
            DB::table('user_infos')->insert([
                'user_id' => $userId,
                'login_id' => $loginId,
                'user_name' => $customerData['name'],
                'phone' => '13800' . rand(100000, 999999),
                'level_id' => 0,
                'group_id' => $groups['默认组']->id,
                'parent_id' => 1, // 归属于第一个代理
                'family_tree' => '0,1',
                'total_funds' => rand(1000, 50000),
                'used_margin' => rand(100, 10000),
                'avail_margin' => rand(100, 10000),
                'equity' => rand(1000, 50000),
                'effective_credit' => rand(10000, 50000),
                'risk_ratio' => rand(10, 500) / 10,
                'margin_amount' => rand(100, 10000),
                'leverage' => rand(100, 500),
                'comm_rate' => 0,
                'auth_status' => rand(0, 1),
                'created_at' => time(),
                'updated_at' => time()
            ]);
        }
    }
    
    /**
     * 生成财务数据
     * Seed financial data
     */
    public function seedFinancialData()
    {
        $users = DB::table('user_infos')->get();
        
        // 生成充值记录
        foreach ($users as $user) {
            for ($i = 0; $i < rand(1, 5); $i++) {
                $amount = rand(100, 10000);
                $statuses = ['01', '02', '09']; // 待支付, 已支付, 失败
                DB::table('deposit_records')->insert([
                    'user_id' => $user->user_id,
                    'user_name' => $user->user_name,
                    'amount' => $amount,
                    'actual_amount' => $amount,
                    'channel_name' => ['银行转账', '在线支付', '信用卡'][rand(0, 2)],
                    'local_order_no' => 'DEP' . time() . rand(1000, 9999),
                    'status' => $statuses[rand(0, 2)],
                    'payment_time' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 30)) : null,
                    'created_at' => time() - rand(0, 2592000), // 30天内
                    'updated_at' => time()
                ]);
            }
            
            // 生成提现记录
            for ($i = 0; $i < rand(0, 3); $i++) {
                $amount = rand(50, 5000);
                $statuses = ['01', '02', '09']; // 待处理, 已处理, 失败
                DB::table('withdraw_records')->insert([
                    'user_id' => $user->user_id,
                    'user_name' => $user->user_name,
                    'amount' => $amount,
                    'actual_amount' => $amount,
                    'channel_name' => ['银行转账', '在线支付'][rand(0, 1)],
                    'local_order_no' => 'WDR' . time() . rand(1000, 9999),
                    'status' => $statuses[rand(0, 2)],
                    'payment_time' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 30)) : null,
                    'created_at' => time() - rand(0, 2592000),
                    'updated_at' => time()
                ]);
            }
        }
    }
    
    /**
     * 生成交易数据
     * Seed trading data
     */
    public function seedTradingData()
    {
        $users = DB::table('user_infos')->where('level_id', 0)->get(); // 只为客户生成交易数据
        $symbols = ['EURUSD', 'GBPUSD', 'USDJPY', 'XAUUSD', 'USOIL'];
        
        foreach ($users as $user) {
            for ($i = 0; $i < rand(5, 20); $i++) {
                $volume = rand(0.1, 10);
                $openPrice = rand(10000, 200000) / 100;
                $closePrice = $openPrice + rand(-500, 500) / 100;
                $profit = rand(-1000, 1000);
                
                DB::table('user_trades')->insert([
                    'user_id' => $user->user_id,
                    'symbol' => $symbols[rand(0, 4)],
                    'volume' => $volume,
                    'open_price' => $openPrice,
                    'close_price' => $closePrice,
                    'profit' => $profit,
                    'commission' => abs($profit) * 0.01,
                    'swap' => rand(-10, 10),
                    'trade_type' => rand(0, 1) ? 'buy' : 'sell',
                    'status' => rand(0, 1) ? 'closed' : 'open',
                    'open_time' => time() - rand(0, 2592000),
                    'close_time' => time() - rand(0, 2592000),
                    'created_at' => time(),
                    'updated_at' => time()
                ]);
            }
        }
    }
    
    /**
     * 生成佣金数据
     * Seed commission data
     */
    public function seedCommissionData()
    {
        $agents = DB::table('user_infos')->where('level_id', '>', 0)->get();
        
        foreach ($agents as $agent) {
            for ($i = 0; $i < rand(3, 10); $i++) {
                $amount = rand(10, 1000);
                DB::table('commission_records')->insert([
                    'agent_id' => $agent->user_id,
                    'user_id' => rand(3, 12), // 假设客户ID从3开始
                    'amount' => $amount,
                    'currency' => 'USD',
                    'commission_rate' => rand(10, 30) / 100,
                    'trade_volume' => rand(100, 10000),
                    'status' => rand(0, 1) ? 'paid' : 'pending',
                    'period' => date('Y-m', strtotime('-' . rand(0, 6) . ' months')),
                    'created_at' => time() - rand(0, 15552000), // 6个月内
                    'updated_at' => time()
                ]);
            }
        }
    }
}
