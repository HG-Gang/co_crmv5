<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:10
 */

declare(strict_types=1);

/**
 * 管理员数据权限 MySQL 夹具契约测试。
 *
 * 文件功能：
 * - 校验 Feature/AdminDataScopeServiceTest.php 使用真实 MySQL/MyISAM 夹具而非事务回滚（DatabaseTransactions）。
 * - 校验夹具完整覆盖旧版 MyISAM 数据权限表：roles、role_data_scopes、admins、admin_agent_bindings、agent_descendants、user_infos。
 *
 * 适用场景：
 * - 改动 AdminDataScopeServiceTest 的夹具生命周期或数据权限相关表后回归。
 *
 * 入参例子：无（直接读取目标测试文件源码做字符串断言）。
 *
 * 返回值：断言通过表示夹具序列化/恢复契约成立。
 *
 * 异常或失败场景：
 * - 夹具误用事务回滚、缺少 MySqlFixtureMutex/MySqlAutoIncrementSnapshot/MySqlTableFingerprint 等生命周期证据，或遗漏任一旧版表时失败。
 */
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminDataScopeFixtureContractTest extends TestCase
{
    /**
     * 校验真实 MySQL 夹具序列化并恢复全部旧版 MyISAM 数据权限表。
     *
     * @return void 断言通过不返回值。
     */
    public function test_real_mysql_fixture_serializes_and_restores_every_legacy_myisam_table(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__) . '/Feature/AdminDataScopeServiceTest.php'
        );

        $this->assertStringNotContainsString('DatabaseTransactions', $source);
        foreach ([
            'MySqlFixtureMutex',
            'MySqlAutoIncrementSnapshot',
            'MySqlTableFingerprint',
            'FIXTURE_TABLES',
            'unusedUserId',
            'createdUserInfoIds',
            'cleanupFixture',
            'table_fingerprint_mismatch',
        ] as $requiredLifecycleEvidence) {
            $this->assertStringContainsString($requiredLifecycleEvidence, $source);
        }

        foreach ([
            "'roles'",
            "'role_data_scopes'",
            "'admins'",
            "'admin_agent_bindings'",
            "'agent_descendants'",
            "'user_infos'",
        ] as $table) {
            $this->assertStringContainsString($table, $source);
        }
    }
}
