<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

declare(strict_types=1);

/**
 * 佣金划转 Saga 迁移契约测试。
 *
 * 文件功能：
 * - 验证迁移声明了持久化的 Saga 主表与步骤出站表（commission_transfers、commission_transfer_outbox）及其关键字段。
 * - 验证迁移在建立唯一索引前对重复财务身份做前置检查。
 * - 验证迁移能安全修复部分缺失的表/列，但拒绝不可恢复的非空身份冲突，且 down() 不销毁表。
 *
 * 适用场景：
 * - 佣金划转 Saga 数据库迁移脚本的契约回归测试。
 *
 * 入参例子：
 * - 无外部入参；直接读取迁移文件源码。
 *
 * 返回值：
 * - 迁移源码包含全部预期声明时断言通过。
 *
 * 异常或失败场景：
 * - 迁移缺字段、缺预检、down() 含 dropIfExists/Schema::drop 即断言失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

final class CommissionTransferSagaMigrationContractTest extends TestCase
{
    /**
     * 被测迁移文件路径：创建佣金转账 saga 表。断言迁移后的表结构、索引与默认值符合契约。
     * @var string
     */
    private const MIGRATION = 'database/migrations/2026_07_19_000003_create_commission_transfer_saga.php';

    /**
     * 验证迁移声明了持久化 Saga 主表与步骤出站表契约。
     */
    public function test_migration_declares_durable_saga_and_step_outbox_contract(): void
    {
        $source = $this->migrationSource();

        foreach ([
            "Schema::create('commission_transfers'",
            "Schema::create('commission_transfer_outbox'",
            "\$table->engine = 'InnoDB'",
            "'local_order_no'",
            "'source_user_id'",
            "'target_user_id'",
            "'request_purpose'",
            "'idempotency_key'",
            "'payload_hash'",
            "'amount'",
            "'reservation_status'",
            "'small_limit_day'",
            "'small_limit_key'",
            "'commission_transfer_id'",
            "'event_type'",
            "'attempts'",
            "'provider_reference'",
            "'last_error_code'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }

    /**
     * 验证迁移在建立唯一索引前预检重复财务身份。
     */
    public function test_migration_preflights_duplicate_financial_identities_before_unique_indexes(): void
    {
        $source = $this->migrationSource();

        foreach ([
            'assertNoDuplicateTransferLocalOrders',
            'assertNoDuplicateTransferIdempotencyKeys',
            'assertNoDuplicateSmallLimitKeys',
            'assertNoDuplicateOutboxEvents',
            'commission_transfers_local_order_unique',
            'commission_transfers_source_purpose_idempotency_unique',
            'commission_transfers_small_limit_unique',
            'commission_transfer_outbox_event_unique',
            'commission_transfer_outbox_ready_index',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }

    /**
     * 验证迁移可安全修复部分表但拒绝不可恢复的非空身份，且 down() 不销毁表。
     */
    public function test_migration_repairs_safe_partial_tables_but_refuses_unrecoverable_nonempty_identity(): void
    {
        $source = $this->migrationSource();

        foreach ([
            'assertNonemptyTransferContract',
            'assertNonemptyOutboxContract',
            'addMissingTransferColumns',
            'addMissingOutboxColumns',
            'ensureTransferIndexes',
            'ensureOutboxIndexes',
            'Cannot safely repair nonempty commission_transfers',
            'Cannot safely repair nonempty commission_transfer_outbox',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $down = $this->methodSource($source, 'public function down()', 'private function');
        $this->assertStringNotContainsString('dropIfExists', $down);
        $this->assertStringNotContainsString('Schema::drop', $down);
    }

    private function migrationSource(): string
    {
        $path = base_path(self::MIGRATION);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function methodSource(string $source, string $start, string $end): string
    {
        $startPosition = strpos($source, $start);
        $this->assertNotFalse($startPosition);
        $endPosition = strpos($source, $end, $startPosition + strlen($start));
        $this->assertNotFalse($endPosition);

        return substr($source, $startPosition, $endPosition - $startPosition);
    }
}
