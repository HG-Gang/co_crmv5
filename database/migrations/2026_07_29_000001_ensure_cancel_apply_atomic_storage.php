<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:20
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 确保销户申请审核使用事务型存储引擎。
 *
 * 文件功能：
 * - cancel_applies 保存待审、通过和拒绝状态，必须与用户能力和操作日志一起提交或回滚。
 * - InnoDB 提供事务回滚与行级锁，防止并发管理员重复审核同一申请。
 * - MyISAM 转换前后按主键计算逐行内容摘要，发现数据变化立即中止迁移。
 *
 * 返回结果：
 * - up 成功后 cancel_applies 在 MySQL/MariaDB 中固定为 InnoDB。
 * - 非 MySQL/MariaDB 环境不执行存储引擎 DDL。
 * - 表缺失、引擎不受支持、转换失败或数据摘要变化时抛出异常。
 */
class EnsureCancelApplyAtomicStorage extends Migration
{
    /** @var string TABLE 销户审核事务依赖的数据表白名单。 */
    private const TABLE = 'cancel_applies';

    /**
     * 转换销户申请表并验证转换前后数据完全一致。
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
        if (!Schema::hasTable(self::TABLE)) {
            throw new RuntimeException('销户审核事务存储缺少数据表：' . self::TABLE);
        }

        $engine = $this->engine();
        if (!in_array(strtolower($engine), ['innodb', 'myisam'], true)) {
            throw new RuntimeException('销户申请表使用不支持的存储引擎：' . $engine);
        }
        if (strcasecmp($engine, 'InnoDB') === 0) {
            return;
        }

        $before = $this->contentDigest();
        DB::statement('ALTER TABLE `cancel_applies` ENGINE=InnoDB');
        $after = $this->contentDigest();

        if ($before !== $after) {
            throw new RuntimeException('销户申请表存储引擎转换改变了数据内容。');
        }
        if (strcasecmp($this->engine(), 'InnoDB') !== 0) {
            throw new RuntimeException('销户申请表未成功转换为 InnoDB。');
        }
    }

    /**
     * 保留事务型存储，不把销户申请表降级回 MyISAM。
     *
     * @return void 不执行任何数据或结构修改。
     */
    public function down(): void
    {
        // InnoDB 是审核事务和并发串行化的安全前提，回滚迁移记录时不能破坏该属性。
    }

    /**
     * 读取销户申请表当前使用的存储引擎。
     *
     * @return string 返回 MySQL/MariaDB 报告的存储引擎名称。
     */
    private function engine(): string
    {
        return (string) DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->value('ENGINE');
    }

    /**
     * 计算销户申请表按主键排序后的逐行内容摘要。
     *
     * @return array{row_count: int, content_digest: string} 返回总行数和 SHA-256 内容摘要。
     *
     * @throws RuntimeException 任一数据行无法稳定序列化时抛出。
     */
    private function contentDigest(): array
    {
        $context = hash_init('sha256');
        $rowCount = 0;

        foreach (DB::table(self::TABLE)->useWritePdo()->orderBy('id')->cursor() as $row) {
            $values = (array) $row;
            ksort($values, SORT_STRING);
            $encoded = json_encode(
                $values,
                JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if (!is_string($encoded)) {
                throw new RuntimeException('无法序列化销户申请表内容摘要。');
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
