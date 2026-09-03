<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:27
 */

/**
 * 数据库总种子入口 Seeder。
 *
 * 文件功能：
 * - 幂等写入 id_sequences 编号序列（代理从 1001 起、客户从 600001 起）。
 * - 幂等写入默认角色（admin/front 守卫）与初始管理员。
 * - 调用业务 Seeder 补齐系统配置、交易组等基础数据。
 * - 前台演示业务数据仅在安全环境且显式开启开关时写入。
 *
 * 运行方式：
 * - php artisan db:seed 或 php artisan migrate:fresh --seed（全新库安装）。
 * - 全部使用 updateOrInsert 幂等写入，重复执行不会产生重复数据。
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $intTimestamp = time(); // Integer timestamp for tables with unsignedInteger columns
        $timestamp = now(); // Use Carbon instance for datetime columns

        // 1. Seed id_sequences (uses integer timestamps)。
        // 使用 updateOrInsert 按 type 幂等写入：迁移链路可能已种过相同序列，
        // 直接 insert 会在全新库 migrate:fresh --seed 时撞唯一键导致安装失败。
        foreach ([
            ['type' => 'agent', 'current_value' => 1001],
            ['type' => 'customer', 'current_value' => 600001],
        ] as $sequence) {
            DB::table('id_sequences')->updateOrInsert(
                ['type' => $sequence['type']],
                [
                    'current_value' => $sequence['current_value'],
                    'prefix' => '',
                    'step' => 1,
                    'created_at' => $intTimestamp,
                    'updated_at' => $intTimestamp,
                ]
            );
        }

        // 调用调试数据填充器（可选）
        // $this->call(DebugDataSeeder::class);

        // 2. Seed default roles (uses integer timestamps)。
        // 按 name+guard_type 幂等写入，避免与迁移预置角色重复。
        foreach ([
            ['name' => 'super_admin', 'guard_type' => 'admin', 'description' => 'Super Administrator'],
            ['name' => 'agent_role', 'guard_type' => 'front', 'description' => 'Agent Role'],
            ['name' => 'customer_role', 'guard_type' => 'front', 'description' => 'Customer Role'],
        ] as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name'], 'guard_type' => $role['guard_type']],
                [
                    'description' => $role['description'],
                    'status' => 1,
                    'created_at' => $intTimestamp,
                    'updated_at' => $intTimestamp,
                ]
            );
        }

        // 3. Seed default admin user (uses integer timestamps)。
        // 按 username 幂等写入；密码只在首次创建时生成，避免重复播种覆盖真实密码。
        DB::table('admin_logins')->updateOrInsert(
            ['username' => 'admin'],
            [
                'password' => Hash::make('abc123'),
                'role_id' => 1,
                'status' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ]
        );

        // 4. Seed default languages (uses integer timestamps)。
        // 按 iso_code 幂等写入，兼容迁移或旧数据已存在的语言行。
        foreach ([
            ['name' => 'English', 'iso_code' => 'en', 'language_code' => 'en-US', 'locale' => 'en'],
            ['name' => '简体中文', 'iso_code' => 'zh', 'language_code' => 'zh-CN', 'locale' => 'zh_CN'],
        ] as $language) {
            DB::table('languages')->updateOrInsert(
                ['iso_code' => $language['iso_code']],
                [
                    'name' => $language['name'],
                    'language_code' => $language['language_code'],
                    'locale' => $language['locale'],
                    'is_active' => 1,
                    'created_at' => $intTimestamp,
                    'updated_at' => $intTimestamp,
                ]
            );
        }

        // 5. Seed default permissions for admin guard (uses datetime columns)
        $permissions = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'type' => 1, 'route' => '/dashboard'],
            // 用户管理必须使用现代后台页面路由与接口权限字符串，
            // 与 AdminPageMenuPermissionCoverageTest / ProtectedRoutePermission 契约一致。
            ['name' => '用户管理', 'slug' => 'admin_users_6a23fb27413fd', 'type' => 1, 'route' => '/admin/users', 'api_route' => 'admin_api_userList'],
            ['name' => 'Agent Management', 'slug' => 'agent_management', 'type' => 1, 'route' => '/agents'],
            ['name' => 'Deposit', 'slug' => 'deposit', 'type' => 1, 'route' => '/deposits'],
            ['name' => 'Withdraw', 'slug' => 'withdraw', 'type' => 1, 'route' => '/withdraws'],
            ['name' => 'Commission', 'slug' => 'commission', 'type' => 1, 'route' => '/commissions'],
            ['name' => 'System Config', 'slug' => 'system_config', 'type' => 1, 'route' => '/configs'],
        ];

        // 5. Seed default permissions for admin guard (uses datetime columns)。
        // 按 slug+guard_type 幂等写入，避免与迁移预置的权限字典重复。
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug'], 'guard_type' => 'admin'],
                array_merge($permission, [
                    'guard_type' => 'admin',
                    'parent_id' => 0,
                    'status' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
            );
        }

        // InitialDataSeeder 是全新库所需的基础字典；演示账号与演示业务记录
        // 必须在 local/testing 环境通过 FRONT_DEMO_SEEDER_ENABLED 显式启用。
        $this->call(InitialDataSeeder::class);
        if ($this->shouldSeedFrontDemoData()) {
            $this->call(FrontDemoDataSeeder::class);
        }

        // 后台统计演示数据（出入金统计区块、实时返佣统计图表）走同一套双重闸门，
        // 但由独立开关控制，正式环境永远不会写入演示数字。
        if ($this->shouldSeedAdminDemoStatistics()) {
            $this->call(AdminDemoStatisticsSeeder::class);
        }

        // 后台页面演示数据（大代理、黑名单、销户申请、在线用户、数据范围绑定、佣金转账 Saga）
        // 同样是双重闸门 + 独立开关：这几张表默认为空会让对应后台页只有空态，
        // UI 验收测不出长文本溢出这类缺陷；正式环境必须保持关闭。
        if ($this->shouldSeedAdminPageDemoData()) {
            $this->call(AdminPageDemoDataSeeder::class);
        }
    }

    /**
     * 判断当前进程是否允许写入前台演示业务数据。
     */
    protected function shouldSeedFrontDemoData(): bool
    {
        return app()->environment('local', 'testing')
            && config('seeding.front_demo_enabled', false) === true;
    }

    /**
     * 判断当前进程是否允许写入后台统计演示数据。
     *
     * 双重闸门：
     * - 环境必须是 local 或 testing。
     * - config('seeding.admin_demo_statistics_enabled') 必须显式为 true
     *   （由 ADMIN_DEMO_STATISTICS_SEEDER_ENABLED 环境变量提供）。
     */
    protected function shouldSeedAdminDemoStatistics(): bool
    {
        return app()->environment('local', 'testing')
            && config('seeding.admin_demo_statistics_enabled', false) === true;
    }

    /**
     * 判断当前进程是否允许写入后台页面演示数据。
     *
     * 双重闸门：
     * - 环境必须是 local 或 testing。
     * - config('seeding.admin_page_demo_enabled') 必须显式为 true
     *   （由 ADMIN_PAGE_DEMO_SEEDER_ENABLED 环境变量提供）。
     */
    protected function shouldSeedAdminPageDemoData(): bool
    {
        return app()->environment('local', 'testing')
            && config('seeding.admin_page_demo_enabled', false) === true;
    }
}
