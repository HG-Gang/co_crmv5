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
 * MySQL 索引快照单元测试。
 *
 * 文件功能：
 * - 校验 MySqlIndexSnapshot 捕获表 SHOW CREATE 定义并恢复被变更的索引。
 * - 校验恢复按精确 SQL 重放（DROP 多余索引、ADD 原始索引），且重复恢复幂等。
 *
 * 适用场景：
 * - 改动 MySQL 夹具索引快照/恢复逻辑后回归。
 *
 * 入参例子：
 * - capture('deposit_records', ['idempotency_key'], ['deposit_records_idempotency_user_unique'])。
 *
 * 返回值：断言通过表示恢复语句序列与预期完全一致。
 *
 * 异常或失败场景：
 * - 恢复语句顺序/内容与预期不符，或重复恢复产生额外变更时失败。
 */
namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\Support\MySqlIndexSnapshot;
use Tests\TestCase;

final class MySqlIndexSnapshotTest extends TestCase
{
    /**
     * 校验恢复重放精确的 SHOW CREATE 定义且幂等。
     *
     * @return void 断言通过不返回值。
     */
    public function test_restore_replays_exact_show_create_definition_and_is_idempotent(): void
    {
        $original = <<<'SQL'
CREATE TABLE `deposit_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `gateway_code` varchar(50) DEFAULT NULL,
  UNIQUE KEY `deposit_records_idempotency_user_unique` (`idempotency_key`,`user_id`) USING BTREE COMMENT 'canonical identity',
  KEY `deposit_records_status_index` (`gateway_code`)
) ENGINE=InnoDB
SQL;
        $mutated = <<<'SQL'
CREATE TABLE `deposit_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(100) DEFAULT NULL,
  `user_id` int NOT NULL,
  `gateway_code` varchar(50) DEFAULT NULL,
  KEY `deposit_records_idempotency_user_unique` (`gateway_code`),
  KEY `payment_idempotency_shadow` (`idempotency_key`(32)),
  KEY `deposit_records_status_index` (`gateway_code`)
) ENGINE=InnoDB
SQL;
        DB::shouldReceive('selectOne')
            ->with('SHOW CREATE TABLE `deposit_records`', [], false)
            ->andReturn(
                $this->showCreateRow($original),
                $this->showCreateRow($mutated),
                $this->showCreateRow($original)
            );
        DB::shouldReceive('statement')->once()->ordered()->with(
            'ALTER TABLE `deposit_records` DROP INDEX `deposit_records_idempotency_user_unique`'
        );
        DB::shouldReceive('statement')->once()->ordered()->with(
            'ALTER TABLE `deposit_records` DROP INDEX `payment_idempotency_shadow`'
        );
        DB::shouldReceive('statement')->once()->ordered()->with(
            "ALTER TABLE `deposit_records` ADD UNIQUE KEY `deposit_records_idempotency_user_unique` (`idempotency_key`,`user_id`) USING BTREE COMMENT 'canonical identity'"
        );

        $snapshot = MySqlIndexSnapshot::capture(
            'deposit_records',
            ['idempotency_key'],
            ['deposit_records_idempotency_user_unique']
        );
        $snapshot->restore();
        $snapshot->restore();

        $this->addToAssertionCount(1);
    }

    private function showCreateRow(string $sql): object
    {
        return (object) [
            'Create Table' => $sql,
            'Table' => 'deposit_records',
        ];
    }
}
