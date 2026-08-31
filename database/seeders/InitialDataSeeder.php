<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:33
 */

/**
 * 初始数据 Seeder（全新库初始化）。
 *
 * 文件功能：
 * - 调整 user_logins 自增起点（客户业务 ID 从 600001 起），并与 id_sequences 对齐。
 * - 幂等创建超级管理员（admin@crmv5.com / abc123）与固定前台测试账号。
 * - 写入提现必需的系统配置（通过 WritesRequiredWithdrawalConfigs trait，替换迁移占位值）。
 *
 * 运行方式：
 * - php artisan db:seed --class=Database\\Seeders\\InitialDataSeeder
 * - 重复执行安全：均使用 updateOrInsert 幂等写入。
 */

namespace Database\Seeders;

use Database\Seeders\Concerns\WritesRequiredWithdrawalConfigs;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    use WritesRequiredWithdrawalConfigs;

    public function run()
    {
        $now = time();

        // 普通客户业务 ID 从 600001 起；真实登录表为 user_logins。
        DB::statement('ALTER TABLE user_logins AUTO_INCREMENT = 600001');

        // 当前项目统一使用 id_sequences 生成代理和客户业务 ID。
        DB::table('id_sequences')->updateOrInsert(
            ['type' => 'agent'],
            ['current_value' => 1000, 'prefix' => '', 'step' => 1, 'created_at' => $now, 'updated_at' => $now]
        );
        DB::table('id_sequences')->updateOrInsert(
            ['type' => 'customer'],
            ['current_value' => 600000, 'prefix' => '', 'step' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('admins')->updateOrInsert(
            ['email' => 'admin@crmv5.com'],
            [
                'role_id' => '1',
                'email' => 'admin@crmv5.com',
                'username' => 'superadmin',
                'password' => Hash::make('abc123'),
                'mobile' => '13800138000',
                'login_count' => 0,
                'last_login_ip' => '',
                'status' => 1,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        foreach ([
            ['name' => 'super_admin', 'description' => '拥有全部权限', 'permissions' => ['*']],
            ['name' => 'customer_service', 'description' => '客服权限', 'permissions' => ['user.view']],
        ] as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name'], 'guard_type' => 'admin'],
                [
                    'description' => $role['description'],
                    'permissions' => json_encode($role['permissions'], JSON_UNESCAPED_UNICODE),
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ([
            ['level_code' => 1, 'name' => '一级代理', 'max_commission' => 80, 'min_commission' => 60],
            ['level_code' => 2, 'name' => '二级代理', 'max_commission' => 70, 'min_commission' => 50],
            ['level_code' => 3, 'name' => '三级代理', 'max_commission' => 60, 'min_commission' => 40],
        ] as $level) {
            DB::table('agent_levels')->updateOrInsert(
                ['level_code' => $level['level_code']],
                [
                    'name' => $level['name'],
                    'max_commission' => $level['max_commission'],
                    'min_commission' => $level['min_commission'],
                    'user_commission' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ([
            ['name' => '有佣金代理组', 'category' => 1, 'has_commission' => 1, 'is_default' => 1],
            ['name' => '无佣金代理组', 'category' => 1, 'has_commission' => 0, 'is_default' => 0],
            ['name' => '普通用户组', 'category' => 2, 'has_commission' => 0, 'is_default' => 1],
        ] as $group) {
            DB::table('group_configs')->updateOrInsert(
                ['name' => $group['name']],
                [
                    'radix' => 50,
                    'category' => $group['category'],
                    'has_commission' => $group['has_commission'],
                    'is_enabled' => 1,
                    'is_ecn' => 0,
                    'is_default' => $group['is_default'],
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ([
            ['key' => 'site_name', 'value' => 'CRM V5', 'description' => '站点名称'],
            ['key' => 'agent_id_start', 'value' => '1001', 'description' => '代理商 ID 起始值'],
            ['key' => 'member_id_start', 'value' => '600001', 'description' => '普通客户 ID 起始值'],
        ] as $config) {
            DB::table('system_configs')->updateOrInsert(
                ['key' => $config['key']],
                [
                    'value' => $config['value'],
                    'group' => 'general',
                    'description' => $config['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->seedRequiredWithdrawalConfigs($now);

        $this->command->info('初始数据填充完成。');
        $this->command->info('管理员账号: admin@crmv5.com / abc123');
    }

    private function seedRequiredWithdrawalConfigs(int $now): void
    {
        $configs = [
            ['withdrawal_enabled', '1', 'Initial withdrawal switch'],
            ['withdrawal_weekend_enabled', '1', 'Initial weekend withdrawal switch'],
            ['withdrawal_start_time', '', 'Initial withdrawal start time'],
            ['withdrawal_end_time', '', 'Initial withdrawal end time'],
            ['withdraw_min_amount', '50', 'Initial minimum withdrawal amount'],
            ['withdraw_max_amount', '50000', 'Initial maximum withdrawal amount'],
            ['withdraw_risk_rate_limit', '50', 'Initial withdrawal risk-rate limit'],
            ['withdraw_check_open', '0', 'Initial open-position withdrawal check'],
            ['withdrawal_fee_rate', '0', 'Initial withdrawal fee rate'],
            ['withdrawal_fixed_fee_usd', '0', 'Initial fixed withdrawal fee'],
            ['withdraw_exchange_rate_cny', '7.05', 'Initial withdrawal CNY rate'],
        ];

        foreach ($configs as $config) {
            $this->writeRequiredWithdrawalConfig(
                $config[0],
                $config[1],
                'finance',
                $config[2],
                $now,
                true
            );
        }
    }
}
