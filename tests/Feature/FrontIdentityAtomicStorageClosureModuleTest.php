<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 21:22
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 前台身份资料事务存储闭环测试。
 *
 * 文件功能：
 * - 验证登录账号、实名认证资料和登录日志使用 InnoDB。
 * - 防止注册、资料更新或测试事务在 MyISAM 上出现局部提交和无法回滚。
 * - 约束身份链路的核心表具备行锁、事务和崩溃恢复能力。
 *
 * 返回值：
 * - 三张表均为 InnoDB 时测试通过。
 * - 任一表仍为 MyISAM 或其他非事务引擎时测试失败并指出表名。
 */
class FrontIdentityAtomicStorageClosureModuleTest extends TestCase
{
    /** @var array<int, string> IDENTITY_TABLES 前台身份链路必须使用事务存储的表。 */
    private const IDENTITY_TABLES = ['user_logins', 'user_auths', 'user_login_logs'];

    /**
     * 验证身份链路的所有关键表均使用 InnoDB。
     *
     * @return void 全部满足事务存储要求时无返回值。
     */
    public function test_front_identity_tables_use_transactional_storage(): void
    {
        foreach (self::IDENTITY_TABLES as $table) {
            $engine = (string) DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine, $table . ' must use InnoDB for identity transactions.');
        }
    }
}
