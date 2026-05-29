<?php

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

        // 1. Seed id_sequences (uses integer timestamps)
        DB::table('id_sequences')->insert([
            [
                'type' => 'agent',
                'current_value' => 1001,
                'prefix' => '',
                'step' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ],
            [
                'type' => 'customer',
                'current_value' => 600001,
                'prefix' => '',
                'step' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ],
        ]);

        // 调用调试数据填充器（可选）
        // $this->call(DebugDataSeeder::class);

        // 2. Seed default roles (uses integer timestamps)
        DB::table('roles')->insert([
            [
                'name' => 'super_admin',
                'guard_type' => 'admin',
                'description' => 'Super Administrator',
                'status' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ],
            [
                'name' => 'agent_role',
                'guard_type' => 'front',
                'description' => 'Agent Role',
                'status' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ],
            [
                'name' => 'customer_role',
                'guard_type' => 'front',
                'description' => 'Customer Role',
                'status' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ],
        ]);

        // 3. Seed default admin user (uses integer timestamps)
        DB::table('admin_logins')->insert([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role_id' => 1,
            'status' => 1,
            'created_at' => $intTimestamp,
            'updated_at' => $intTimestamp,
        ]);

        // 4. Seed default languages (uses integer timestamps)
        DB::table('languages')->insert([
            [
                'name' => 'English',
                'iso_code' => 'en',
                'language_code' => 'en-US',
                'locale' => 'en',
                'is_active' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ],
            [
                'name' => '简体中文',
                'iso_code' => 'zh',
                'language_code' => 'zh-CN',
                'locale' => 'zh_CN',
                'is_active' => 1,
                'created_at' => $intTimestamp,
                'updated_at' => $intTimestamp,
            ],
        ]);

        // 5. Seed default permissions for admin guard (uses datetime columns)
        $permissions = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'type' => 1, 'route' => '/dashboard'],
            ['name' => 'User Management', 'slug' => 'user_management', 'type' => 1, 'route' => '/users'],
            ['name' => 'Agent Management', 'slug' => 'agent_management', 'type' => 1, 'route' => '/agents'],
            ['name' => 'Deposit', 'slug' => 'deposit', 'type' => 1, 'route' => '/deposits'],
            ['name' => 'Withdraw', 'slug' => 'withdraw', 'type' => 1, 'route' => '/withdraws'],
            ['name' => 'Commission', 'slug' => 'commission', 'type' => 1, 'route' => '/commissions'],
            ['name' => 'System Config', 'slug' => 'system_config', 'type' => 1, 'route' => '/configs'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert(array_merge($permission, [
                'guard_type' => 'admin',
                'parent_id' => 0,
                'status' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]));
        }
    }
}
