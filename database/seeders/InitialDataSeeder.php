<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    public function run()
    {
        // 1. 设置 user_login 的 AUTO_INCREMENT 从 600001 起（普通客户ID）
        // 代理商ID从1001起，由 agent_id_sequence 控制，不使用 user_login 的自增
        // 这里设置 user_login 的自增从 600001 起，用于普通客户
        DB::statement('ALTER TABLE user_login AUTO_INCREMENT = 600001');

        // 2. 初始化代理商ID序列（已在 migration 的 up() 中插入，这里做 upsert）
        DB::table('agent_id_sequence')->updateOrInsert(
            ['sequence_name' => 'agent'],
            ['current_value' => 1000, 'increment_by' => 1, 'updated_at' => now()]
        );

        // 3. 创建超级管理员
        if (!DB::table('admins')->where('email', 'admin@crmv5.com')->exists()) {
            DB::table('admins')->insert([
                'role_id'      => '1',
                'email'        => 'admin@crmv5.com',
                'username'     => 'superadmin',
                'password'     => Hash::make('Admin@123456'),
                'mobile'       => '13800138000',
                'login_num'    => 0,
                'last_login_ip'=> '',
                'status'       => 1,
                'created_name' => 'system',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // 4. 初始化管理员角色
        if (!DB::table('admin_roles')->exists()) {
            DB::table('admin_roles')->insert([
                ['name' => '超级管理员', 'description' => '拥有全部权限', 'acl' => json_encode(['*']), 'created_at' => now(), 'updated_at' => now()],
                ['name' => '客服', 'description' => '客服权限', 'acl' => json_encode(['user.view']), 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // 5. 初始化代理级别
        if (!DB::table('agent_levels')->exists()) {
            DB::table('agent_levels')->insert([
                ['level_code' => 1, 'name' => '一级代理', 'max_comm_rate' => 80, 'min_comm_rate' => 60, 'user_comm_rate' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['level_code' => 2, 'name' => '二级代理', 'max_comm_rate' => 70, 'min_comm_rate' => 50, 'user_comm_rate' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['level_code' => 3, 'name' => '三级代理', 'max_comm_rate' => 60, 'min_comm_rate' => 40, 'user_comm_rate' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // 6. 初始化用户组
        if (!DB::table('user_groups')->exists()) {
            DB::table('user_groups')->insert([
                ['name' => '有佣金代理组', 'base_rate' => 50, 'category' => 1, 'has_commission' => 1, 'is_ecn' => 0, 'is_default' => 1, 'is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '无佣金代理组', 'base_rate' => 50, 'category' => 1, 'has_commission' => 0, 'is_ecn' => 0, 'is_default' => 0, 'is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '普通用户组', 'base_rate' => 50, 'category' => 2, 'has_commission' => 0, 'is_ecn' => 0, 'is_default' => 1, 'is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // 7. 系统配置
        if (!DB::table('system_settings')->exists()) {
            DB::table('system_settings')->insert([
                ['key' => 'site_name', 'value' => 'CRM V5', 'description' => '站点名称', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'agent_id_start', 'value' => '1001', 'description' => '代理商ID起始值', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'member_id_start', 'value' => '600001', 'description' => '普通客户ID起始值', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $this->command->info('初始数据填充完成！');
        $this->command->info('管理员账号: admin@crmv5.com / Admin@123456');
    }
}