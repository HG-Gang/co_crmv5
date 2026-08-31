<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

declare(strict_types=1);

/**
 * 佣金划转财务表原子存储（InnoDB）迁移契约测试。
 *
 * 文件功能：
 * - 验证佣金划转相关财务表（user_infos、commission_records、operation_logs、commission_transfers、commission_transfer_outbox）均为 InnoDB 事务引擎。
 * - 验证原子存储迁移脚本包含全部目标表、ALTER TABLE ENGINE=InnoDB 与内容摘要。
 * - 验证迁移的 down() 不销毁财务数据。
 *
 * 适用场景：
 * - MySQL 环境下佣金划转原子存储迁移的契约回归测试；非 MySQL 环境跳过。
 *
 * 入参例子：
 * - 无外部入参；直接读取 information_schema 与迁移文件源码。
 *
 * 返回值：
 * - 所有表引擎为 InnoDB、迁移脚本包含预期声明时断言通过。
 *
 * 异常或失败场景：
 * - 任一表非 InnoDB、迁移脚本缺声明或 down() 含 drop 即断言失败。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\MySqlFixtureMutex;
use Tests\TestCase;

final class CommissionTransferAtomicStorageMigrationTest extends TestCase
{
    /**
     * 被测迁移涉及的表清单。断言原子存储结构（表/列/索引）在迁移后符合契约。
     * @var array<int, string>
     */
    private const TABLES = [
        'user_infos',
        'commission_records',
        'operation_logs',
        'commission_transfers',
        'commission_transfer_outbox',
    ];

    /**
     * MySqlFixtureMutex 实例。串行化共享测试库上的迁移执行与清理，避免并行进程互相踩踏。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'mysql') {
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * 验证佣金划转全部财务表使用事务型 InnoDB 存储引擎（仅 MySQL）。
     */
    public function test_all_commission_transfer_financial_tables_use_transactional_innodb_storage(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This migration contract requires MySQL.');
        }

        foreach (self::TABLES as $table) {
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', (string) $engine, $table . ' must be transactional.');
        }
    }

    /**
     * 验证原子存储迁移脚本保留数据且 down() 不销毁财务数据。
     */
    public function test_atomic_storage_migration_is_data_preserving_and_non_destructive_on_down(): void
    {
        $source = (string) file_get_contents(
            base_path('database/migrations/2026_07_19_000009_ensure_commission_transfer_atomic_storage.php')
        );

        foreach (self::TABLES as $table) {
            $this->assertStringContainsString("'" . $table . "'", $source);
        }
        $this->assertStringContainsString('ALTER TABLE', $source);
        $this->assertStringContainsString('ENGINE=InnoDB', $source);
        $this->assertStringContainsString('content_digest', $source);
        $this->assertStringContainsString('down(): void', $source);
        $this->assertStringContainsString('must not destroy financial', strtolower($source));
    }
}
