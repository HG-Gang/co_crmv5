<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:19
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台销户审核事务存储闭环测试。
 *
 * 文件功能：
 * - 验证 cancel_applies 使用 InnoDB，确保审核状态能够回滚并支持行级锁。
 * - 验证存量表转换迁移包含数据摘要校验，防止转换存储引擎时静默改变业务数据。
 *
 * 返回结果：
 * - InnoDB 表示通过/拒绝审核可与用户状态、操作日志组成真实数据库事务。
 * - MyISAM 或其他引擎表示事务和 lockForUpdate 无效，测试必须失败并阻止继续发布。
 */
class AdminCancelApplyAtomicStorageClosureModuleTest extends TestCase
{
    /**
     * 验证当前数据库的销户申请表支持事务与行锁。
     */
    public function test_cancel_applies_uses_innodb_for_review_transactions(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('存储引擎断言仅适用于 MySQL 或 MariaDB。');
        }

        $engine = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'cancel_applies')
            ->value('ENGINE');

        $this->assertSame(
            'InnoDB',
            (string) $engine,
            'cancel_applies 必须使用 InnoDB，否则审核事务回滚和 lockForUpdate 均不会生效。'
        );
    }

    /**
     * 验证存量转换迁移具备引擎校验、内容摘要和不可降级回滚规则。
     */
    public function test_cancel_apply_atomic_storage_migration_keeps_data_safety_contract(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_07_29_000001_ensure_cancel_apply_atomic_storage.php'
        )) ?: '';

        $this->assertStringContainsString('ALTER TABLE `cancel_applies` ENGINE=InnoDB', $source);
        $this->assertStringContainsString('contentDigest', $source);
        $this->assertStringContainsString('不把销户申请表降级回 MyISAM', $source);
    }
}
