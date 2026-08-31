<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:36
 */

/**
 * 强制启用新闻翻译的唯一性约束。
 *
 * 文件功能：
 * - MySQL/MariaDB 通过 active_translation_key 生成列建立唯一索引；该列仅在
 *   deleted_at 为空时组合 news_id 与 lang_code，保证同一语言只有一条活动翻译。
 * - SQLite/PostgreSQL 使用（news_id, lang_code）的条件唯一索引实现相同语义。
 * - 执行前检测重复活动翻译并失败关闭，禁止静默删除历史业务数据。
 *
 * 字段语义：
 * - 软删除行的生成列为 NULL，因此不限制同语言历史翻译数量；回滚时移除约束列与索引。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnforceUniqueActiveNewsTranslations extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('news_langs')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // 旧库中的新闻表仍可能是 MyISAM；先启用真实事务，再建立活跃翻译唯一约束。
            if (Schema::hasTable('news')) {
                DB::statement('ALTER TABLE `news` ENGINE=InnoDB');
            }
            DB::statement('ALTER TABLE `news_langs` ENGINE=InnoDB');
        }

        $duplicates = DB::table('news_langs')
            ->select('news_id', 'lang_code', DB::raw('COUNT(*) AS total'))
            ->whereNull('deleted_at')
            ->groupBy('news_id', 'lang_code')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicates) {
            throw new \RuntimeException('news_langs contains duplicate active translations; resolve them before adding the unique constraint.');
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if (!Schema::hasColumn('news_langs', 'active_translation_key')) {
                DB::statement(<<<'SQL'
ALTER TABLE `news_langs`
ADD COLUMN `active_translation_key` VARCHAR(64)
GENERATED ALWAYS AS (
    CASE
        WHEN `deleted_at` IS NULL THEN CONCAT(`news_id`, ':', `lang_code`)
        ELSE NULL
    END
) STORED
SQL
                );
            }
            if (!$this->mysqlIndexExists('news_langs_active_translation_unique')) {
                DB::statement('ALTER TABLE `news_langs` ADD UNIQUE INDEX `news_langs_active_translation_unique` (`active_translation_key`)');
            }

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS news_langs_active_translation_unique ON news_langs (news_id, lang_code) WHERE deleted_at IS NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS news_langs_active_translation_unique ON news_langs (news_id, lang_code) WHERE deleted_at IS NULL');
            return;
        }

        throw new \RuntimeException('Unsupported database driver for active news translation uniqueness: ' . $driver);
    }

    public function down(): void
    {
        if (!Schema::hasTable('news_langs')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if ($this->mysqlIndexExists('news_langs_active_translation_unique')) {
                DB::statement('ALTER TABLE `news_langs` DROP INDEX `news_langs_active_translation_unique`');
            }
            if (Schema::hasColumn('news_langs', 'active_translation_key')) {
                DB::statement('ALTER TABLE `news_langs` DROP COLUMN `active_translation_key`');
            }
            return;
        }

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS news_langs_active_translation_unique');
        }
    }

    private function mysqlIndexExists(string $indexName): bool
    {
        return !empty(DB::select(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            ['news_langs', $indexName]
        ));
    }
}
