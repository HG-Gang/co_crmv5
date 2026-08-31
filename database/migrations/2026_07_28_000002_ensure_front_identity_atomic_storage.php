<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 21:24
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 确保前台身份链路使用事务型存储引擎。
 *
 * 文件功能：
 * - user_logins 保存登录凭证和账号状态，必须参与注册、开户和密码更新事务。
 * - user_auths 保存银行卡和身份证审核资料，必须支持资料更新回滚和行锁。
 * - user_login_logs 保存登录审计记录，必须支持测试隔离和一致的故障恢复。
 * - MyISAM 转换前后按主键逐行计算内容摘要，发现任何数据变化立即失败。
 *
 * 返回值：
 * - up 成功后，三张身份表在 MySQL/MariaDB 中均为 InnoDB。
 * - 非 MySQL/MariaDB 环境不执行存储引擎 DDL。
 * - 表缺失、引擎不受支持或转换改变数据时抛出异常。
 */
class EnsureFrontIdentityAtomicStorage extends Migration
{
    /** @var array<int, string> TABLES 前台身份闭环依赖的事务表白名单。 */
    private const TABLES = ['user_logins', 'user_auths', 'user_login_logs'];

    /**
     * 转换身份表并验证转换前后数据完全一致。
     *
     * @return void 转换成功或当前数据库无需转换时无返回值。
     *
     * @throws RuntimeException 表缺失、引擎不受支持、转换失败或内容摘要变化时抛出。
     */
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('前台身份事务存储缺少数据表：' . $table);
            }

            $engine = $this->engine($table);
            if (!in_array(strtolower($engine), ['innodb', 'myisam'], true)) {
                throw new RuntimeException('前台身份数据表使用不支持的存储引擎：' . $table . '=' . $engine);
            }
            if (strcasecmp($engine, 'InnoDB') === 0) {
                continue;
            }

            $before = $this->contentDigest($table);
            DB::statement('ALTER TABLE `' . $table . '` ENGINE=InnoDB');
            $after = $this->contentDigest($table);

            if ($before !== $after) {
                throw new RuntimeException('身份表存储引擎转换改变了数据内容：' . $table);
            }
            if (strcasecmp($this->engine($table), 'InnoDB') !== 0) {
                throw new RuntimeException('身份数据表未成功转换为 InnoDB：' . $table);
            }
        }
    }

    /**
     * 保留事务型存储，不把身份数据表降级回 MyISAM。
     *
     * @return void 不执行任何数据或结构修改。
     */
    public function down(): void
    {
        // InnoDB 是注册、登录、资料更新和测试回滚的安全前提，回滚记录时不得破坏该属性。
    }

    /**
     * 读取指定身份数据表的当前存储引擎。
     *
     * @param string $table TABLES 白名单中的数据表名称。
     * @return string 返回 MySQL/MariaDB 报告的存储引擎名称。
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
     * @param string $table TABLES 白名单中的数据表名称。
     * @return array{row_count: int, content_digest: string} 返回总行数和 SHA-256 内容摘要。
     *
     * @throws RuntimeException 任一数据行无法稳定序列化时抛出。
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
                throw new RuntimeException('无法序列化身份数据表内容摘要：' . $table);
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
