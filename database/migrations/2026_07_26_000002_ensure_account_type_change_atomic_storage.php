<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 13:40
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 确保账户类型切换涉及的数据表使用事务型存储引擎。
 *
 * 文件功能：
 * - group_configs 保存当前组和配对组，必须支持事务读取与测试回滚。
 * - user_infos 保存用户当前组、ECN 标识和杠杆，必须支持行锁。
 * - user_trades 保存未平仓与挂单，必须和切换校验处于可事务化的数据源上。
 * - MyISAM 转换前后计算逐行内容摘要，发现任何数据变化时立即失败。
 *
 * 返回值：
 * - up 成功后，三张表在 MySQL/MariaDB 中均为 InnoDB。
 * - 非 MySQL/MariaDB 环境不执行存储引擎 DDL。
 */
class EnsureAccountTypeChangeAtomicStorage extends Migration
{
    /** @var array<int, string> $tables 账户类型切换读取或写入的数据表。 */
    private const TABLES = ['group_configs', 'user_infos', 'user_trades'];

    /**
     * 转换非事务表并验证转换前后数据完全一致。
     *
     * @return void
     *
     * @throws RuntimeException 表缺失、引擎不受支持、转换后引擎错误或数据摘要变化时抛出。
     */
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('账户类型切换事务存储缺少数据表：' . $table);
            }

            $engine = $this->engine($table);
            if (!in_array(strtolower($engine), ['innodb', 'myisam'], true)) {
                throw new RuntimeException('账户类型切换数据表使用不支持的存储引擎：' . $table . '=' . $engine);
            }
            if (strcasecmp($engine, 'InnoDB') === 0) {
                continue;
            }

            $before = $this->contentDigest($table);
            DB::statement('ALTER TABLE `' . $table . '` ENGINE=InnoDB');
            $after = $this->contentDigest($table);

            if ($before !== $after) {
                throw new RuntimeException('存储引擎转换改变了数据表内容：' . $table);
            }
            if (strcasecmp($this->engine($table), 'InnoDB') !== 0) {
                throw new RuntimeException('数据表未成功转换为 InnoDB：' . $table);
            }
        }
    }

    /**
     * 保持事务型存储，不把业务表降级回 MyISAM。
     *
     * @return void
     */
    public function down(): void
    {
        // InnoDB 是账户切换并发一致性前提，回滚迁移记录时不破坏该安全属性。
    }

    /**
     * 读取当前 MySQL 数据表存储引擎。
     *
     * @param string $table 白名单内数据表名称。
     * @return string 当前存储引擎名称。
     */
    private function engine(string $table): string
    {
        return (string) DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->value('ENGINE');
    }

    /**
     * 计算按主键排序后的逐行内容摘要。
     *
     * @param string $table 白名单内数据表名称。
     * @return array{row_count: int, content_digest: string} 返回总行数和 SHA-256 内容摘要。
     */
    private function contentDigest(string $table): array
    {
        $context = hash_init('sha256');
        $rowCount = 0;

        foreach (DB::table($table)->useWritePdo()->orderBy('id')->cursor() as $row) {
            $values = (array) $row;
            ksort($values, SORT_STRING);
            $encoded = json_encode(
                $values,
                JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if (!is_string($encoded)) {
                throw new RuntimeException('无法序列化数据表内容摘要：' . $table);
            }
            hash_update($context, strlen($encoded) . ':' . $encoded . ';');
            $rowCount++;
        }

        return [
            'row_count' => $rowCount,
            'content_digest' => hash_final($context),
        ];
    }
}
