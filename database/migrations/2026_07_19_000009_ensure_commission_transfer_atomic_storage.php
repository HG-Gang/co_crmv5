<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 确保佣金划转存储具备原子性。
 *
 * 文件功能：
 * - 将佣金划转相关表转换为事务型存储引擎（InnoDB），保证 Saga 步骤原子提交。
 *
 * 字段语义：
 * - 仅修改存储引擎；回滚不降级为 MyISAM，避免破坏事务一致性。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EnsureCommissionTransferAtomicStorage extends Migration
{
    /**
     * 参与同一次转账状态变更的全部表清单：用户资料、佣金流水、操作日志、转账主表与出箱表。
     * 任一表停留在 MyISAM 都会让 Saga 步骤的事务与行锁失效（best-effort），
     * 因此迁移把它们统一转为 InnoDB；新增参与转账事务的表必须加入此清单。
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'user_infos',
        'commission_records',
        'operation_logs',
        'commission_transfers',
        'commission_transfer_outbox',
    ];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException(
                    'Cannot enable atomic commission transfer storage: missing table ' . $table . '.'
                );
            }

            $before = $this->contentDigest($table);
            $engine = (string) DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');
            if (!in_array(strtolower($engine), ['innodb', 'myisam'], true)) {
                throw new RuntimeException(
                    'Cannot enable atomic commission transfer storage: unsupported engine '
                    . $engine . ' for ' . $table . '.'
                );
            }

            if (strcasecmp($engine, 'InnoDB') !== 0) {
                DB::statement('ALTER TABLE `' . $table . '` ENGINE=InnoDB');
            }

            $after = $this->contentDigest($table);
            if ($before !== $after) {
                throw new RuntimeException(
                    'Atomic storage migration changed financial rows in ' . $table . '.'
                );
            }
        }
    }

    public function down(): void
    {
        // Financial state must remain transactional; rollback must not destroy financial rows.
    }

    /** @return array{row_count:int,content_digest:string} */
    private function contentDigest(string $table): array
    {
        $context = hash_init('sha256');
        $rowCount = 0;
        foreach (DB::table($table)->useWritePdo()->orderBy('id')->cursor() as $row) {
            $values = (array) $row;
            ksort($values, SORT_STRING);
            $encoded = json_encode(
                $values,
                JSON_PRESERVE_ZERO_FRACTION
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );
            hash_update($context, strlen($encoded) . ':' . $encoded . ';');
            $rowCount++;
        }

        return [
            'row_count' => $rowCount,
            'content_digest' => hash_final($context),
        ];
    }
}
