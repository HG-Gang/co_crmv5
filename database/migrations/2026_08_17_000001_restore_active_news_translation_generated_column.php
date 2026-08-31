<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 04:50
 */

/**
 * 文件功能：修复被字段注释命令降级为普通列的新闻活跃翻译生成列。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 活跃翻译列与索引的规范定义校验器。
 *
 * 该类保持纯函数语义，避免迁移把“存在同名列/索引”误当成结构正确。
 */
final class ActiveNewsTranslationSchemaDefinition
{
    /**
     * 活跃翻译列必须携带的字段注释原文。校验器以“注释内容 + 列类型/生成表达式”双口径判定结构正确，
     * 防止“存在同名列”被误判为生成列完好；注释被 ALTER 命令降级改写即视为结构损坏需要重建。
     */
    public const COLUMN_COMMENT = '当前生效的翻译 key：指向 news_langs 中实际展示的语言条目。';

    /** @param array<string, mixed> $column */
    public static function commentMatches(array $column): bool
    {
        $column = array_change_key_case($column, CASE_LOWER);

        return trim((string) ($column['column_comment'] ?? '')) === self::COLUMN_COMMENT;
    }

    /** @param array<string, mixed> $column */
    public static function columnMatches(array $column): bool
    {
        $column = array_change_key_case($column, CASE_LOWER);
        if (strtolower(trim((string) ($column['column_type'] ?? ''))) !== 'varchar(64)'
            || strtolower(trim((string) ($column['character_set_name'] ?? ''))) !== 'utf8mb4'
            || strtolower(trim((string) ($column['collation_name'] ?? ''))) !== 'utf8mb4_unicode_ci'
            || strtoupper(preg_replace('/\s+/', ' ', trim((string) ($column['extra'] ?? ''))) ?? '')
                !== 'STORED GENERATED'
            || strtoupper(trim((string) ($column['is_nullable'] ?? ''))) !== 'YES'
            || ($column['column_default'] ?? null) !== null) {
            return false;
        }

        return in_array(
            self::normalizeExpression((string) ($column['generation_expression'] ?? '')),
            [
                "casewhenisnull(deleted_at)thenconcat(news_id,':',lang_code)elsenullend",
                "casewhendeleted_atisnullthenconcat(news_id,':',lang_code)elsenullend",
            ],
            true
        );
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function indexMatches(array $rows): bool
    {
        if (count($rows) !== 1) {
            return false;
        }

        $row = array_change_key_case($rows[0], CASE_LOWER);

        return (int) ($row['non_unique'] ?? 1) === 0
            && (string) ($row['column_name'] ?? '') === 'active_translation_key'
            && (int) ($row['seq_in_index'] ?? 0) === 1
            && ($row['sub_part'] ?? null) === null;
    }

    private static function normalizeExpression(string $expression): string
    {
        $expression = strtolower(trim($expression));
        $expression = str_replace('`', '', $expression);
        $expression = str_replace("\\'", "'", $expression);
        $expression = preg_replace('/_utf8mb4(?=\x27)/', '', $expression) ?? $expression;
        $expression = preg_replace('/\s+/', '', $expression) ?? $expression;

        while (strlen($expression) >= 2
            && $expression[0] === '('
            && substr($expression, -1) === ')') {
            $depth = 0;
            $balanced = true;
            $last = strlen($expression) - 1;
            for ($index = 0; $index <= $last; ++$index) {
                if ($expression[$index] === '(') {
                    ++$depth;
                } elseif ($expression[$index] === ')') {
                    --$depth;
                    if ($depth === 0 && $index !== $last) {
                        $balanced = false;
                        break;
                    }
                }
            }
            if (!$balanced || $depth !== 0) {
                break;
            }
            $expression = substr($expression, 1, -1);
        }

        return $expression;
    }
}

class RestoreActiveNewsTranslationGeneratedColumn extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('news_langs') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $duplicates = DB::table('news_langs')
            ->select('news_id', 'lang_code')
            ->whereNull('deleted_at')
            ->groupBy('news_id', 'lang_code')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicates) {
            throw new \RuntimeException(
                'news_langs contains duplicate active translations; resolve them before restoring the unique constraint.'
            );
        }

        $column = $this->columnMetadata();
        $indexRows = $this->indexMetadata();
        $columnMatches = $column !== null
            && ActiveNewsTranslationSchemaDefinition::columnMatches($column);
        $commentMatches = $column !== null
            && ActiveNewsTranslationSchemaDefinition::commentMatches($column);
        $indexRowsArray = array_map(static fn ($row): array => (array) $row, $indexRows);
        $indexMatches = ActiveNewsTranslationSchemaDefinition::indexMatches($indexRowsArray);

        if ($columnMatches && $commentMatches && $indexMatches) {
            return;
        }

        if ($indexRows !== []) {
            DB::statement(
                'ALTER TABLE `news_langs` DROP INDEX `news_langs_active_translation_unique`'
            );
        }

        if (!$columnMatches || !$commentMatches) {
            $operation = $column === null ? 'ADD COLUMN' : 'MODIFY COLUMN';
            DB::statement(
                'ALTER TABLE `news_langs` ' . $operation . ' `active_translation_key` '
                . "VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci "
                . "GENERATED ALWAYS AS (CASE WHEN `deleted_at` IS NULL "
                . "THEN CONCAT(`news_id`, ':', `lang_code`) ELSE NULL END) STORED "
                . "COMMENT '" . ActiveNewsTranslationSchemaDefinition::COLUMN_COMMENT . "'"
            );
        }

        DB::statement(
            'ALTER TABLE `news_langs` ADD UNIQUE INDEX '
            . '`news_langs_active_translation_unique` (`active_translation_key`)'
        );

        $this->assertCanonicalSchema();
    }

    public function down(): void
    {
        // 活跃翻译唯一性是数据安全约束，修复后不再降级。
    }

    /** @return array<string, mixed>|null */
    private function columnMetadata(): ?array
    {
        $row = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'news_langs')
            ->where('COLUMN_NAME', 'active_translation_key')
            ->first([
                'COLUMN_TYPE',
                'IS_NULLABLE',
                'COLUMN_DEFAULT',
                'EXTRA',
                'GENERATION_EXPRESSION',
                'CHARACTER_SET_NAME',
                'COLLATION_NAME',
                'COLUMN_COMMENT',
            ]);

        return $row === null ? null : array_change_key_case((array) $row, CASE_LOWER);
    }

    /** @return array<int, array<string, mixed>> */
    private function indexMetadata(): array
    {
        return array_map(
            static fn ($row): array => array_change_key_case((array) $row, CASE_LOWER),
            DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'news_langs')
                ->where('INDEX_NAME', 'news_langs_active_translation_unique')
                ->orderBy('SEQ_IN_INDEX')
                ->get(['NON_UNIQUE', 'COLUMN_NAME', 'SEQ_IN_INDEX', 'SUB_PART'])
                ->all()
        );
    }

    private function assertCanonicalSchema(): void
    {
        $column = $this->columnMetadata();
        $indexRows = $this->indexMetadata();
        if ($column === null
            || !ActiveNewsTranslationSchemaDefinition::columnMatches($column)
            || !ActiveNewsTranslationSchemaDefinition::commentMatches($column)
            || !ActiveNewsTranslationSchemaDefinition::indexMatches($indexRows)) {
            throw new \RuntimeException(
                'news_langs active translation schema verification failed after repair.'
            );
        }
    }
}
