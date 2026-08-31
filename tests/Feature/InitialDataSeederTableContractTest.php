<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:09
 */

/**
 * InitialDataSeeder 表契约回归测试：初始数据播种器只能使用当前 schema 的表与字段。
 *
 * 文件功能：
 * - 静态读取 database/seeders/InitialDataSeeder.php 源码字符串，断言其不包含旧表旧字段
 *   （user_login、agent_id_sequence、admin_roles、user_groups、system_settings、login_num、
 *   created_name、max_comm_rate、min_comm_rate、user_comm_rate、base_rate 等）。
 * - 断言其必须包含当前 schema 契约：user_logins AUTO_INCREMENT=600001、
 *   id_sequences（agent/customer 类型）、roles、group_configs（radix=50）、
 *   system_configs（site_name、agent_id_start、member_id_start）及对应字段默认值。
 *
 * 适用场景：任何改动 InitialDataSeeder.php、相关迁移或表结构重构的提交都应回归本文件，
 * 防止播种器回退到旧表导致初始化数据错位或插入失败。
 *
 * 入参：无外部参数；用例直接读取 seeder 文件源码进行字符串断言。
 *
 * 返回值：无返回值；所有断言通过即表示播种器与当前 schema 契约一致。
 *
 * 失败场景：断言失败表示播种器混入旧表旧字段或缺失当前契约字段，初始化数据将写入错误表结构，
 * 需在涉及 seeder 或迁移的改动中立即修正。
 */

namespace Tests\Feature;

use Tests\TestCase;

class InitialDataSeederTableContractTest extends TestCase
{
    public function test_initial_data_seeder_uses_current_schema_tables(): void
    {
        $source = file_get_contents(database_path('seeders/InitialDataSeeder.php')) ?: '';

        foreach ([
            "ALTER TABLE user_login AUTO_INCREMENT",
            "DB::table('agent_id_sequence')",
            "DB::table('admin_roles')",
            "DB::table('user_groups')",
            "DB::table('system_settings')",
            "'login_num'",
            "'created_name'",
            "'max_comm_rate'",
            "'min_comm_rate'",
            "'user_comm_rate'",
            "'base_rate'",
        ] as $legacyNeedle) {
            $this->assertStringNotContainsString($legacyNeedle, $source, 'InitialDataSeeder must not use legacy table or field: ' . $legacyNeedle);
        }

        foreach ([
            'ALTER TABLE user_logins AUTO_INCREMENT = 600001',
            "DB::table('id_sequences')->updateOrInsert",
            "'type' => 'agent'",
            "'type' => 'customer'",
            "DB::table('roles')->updateOrInsert",
            "'login_count' => 0",
            "'created_by' => 'system'",
            "'max_commission' => 80",
            "'min_commission' => 60",
            "'user_commission' => 0",
            "DB::table('group_configs')->updateOrInsert",
            "'radix' => 50",
            "DB::table('system_configs')->updateOrInsert",
            "'key' => 'site_name'",
            "'key' => 'agent_id_start'",
            "'key' => 'member_id_start'",
        ] as $currentNeedle) {
            $this->assertStringContainsString($currentNeedle, $source, 'InitialDataSeeder must use current schema contract: ' . $currentNeedle);
        }
    }
}
