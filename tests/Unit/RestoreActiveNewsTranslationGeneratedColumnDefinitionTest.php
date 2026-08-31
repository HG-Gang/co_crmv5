<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 04:50
 */

/**
 * RestoreActiveNewsTranslationGeneratedColumnDefinitionTest
 *
 * 文件功能：
 * - 验证活跃新闻翻译生成列恢复定义：列定义匹配规范存储表达式、索引为单列全长度唯一、列注释匹配规范中文注释。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class RestoreActiveNewsTranslationGeneratedColumnDefinitionTest extends TestCase
{
    /**
     * @dataProvider columnDefinitionProvider
     *
     * @param array<string, mixed> $column
     */
    public function test_column_definition_must_match_the_canonical_stored_expression(
        array $column,
        bool $expected
    ): void {
        $validator = $this->schemaDefinitionValidator();

        $this->assertSame($expected, $validator::columnMatches($column));
    }

    public function columnDefinitionProvider(): array
    {
        $valid = $this->validColumn();

        return [
            'canonical stored generated column' => [$valid, true],
            'mariadb escaped literal metadata' => [array_replace($valid, [
                'GENERATION_EXPRESSION' => "(case when isnull(`deleted_at`) then concat(`news_id`,_utf8mb4\\':\\',`lang_code`) else NULL end)",
            ]), true],
            'wrong expression' => [array_replace($valid, [
                'GENERATION_EXPRESSION' => "case when isnull(`deleted_at`) then concat(`news_id`, ':bad:', `lang_code`) else NULL end",
            ]), false],
            'virtual generated column' => [array_replace($valid, [
                'EXTRA' => 'VIRTUAL GENERATED',
            ]), false],
            'wrong type' => [array_replace($valid, [
                'COLUMN_TYPE' => 'varchar(63)',
            ]), false],
            'wrong collation' => [array_replace($valid, [
                'COLLATION_NAME' => 'utf8mb4_general_ci',
            ]), false],
            'wrong character set' => [array_replace($valid, [
                'CHARACTER_SET_NAME' => 'utf8',
            ]), false],
        ];
    }

    /**
     * @dataProvider indexDefinitionProvider
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function test_index_definition_requires_one_full_length_unique_column(
        array $rows,
        bool $expected
    ): void {
        $validator = $this->schemaDefinitionValidator();

        $this->assertSame($expected, $validator::indexMatches($rows));
    }

    public function indexDefinitionProvider(): array
    {
        $valid = [[
            'NON_UNIQUE' => 0,
            'COLUMN_NAME' => 'active_translation_key',
            'SEQ_IN_INDEX' => 1,
            'SUB_PART' => null,
        ]];

        return [
            'canonical full unique index' => [$valid, true],
            'prefix unique index' => [[array_replace($valid[0], ['SUB_PART' => 32])], false],
            'wrong sequence' => [[array_replace($valid[0], ['SEQ_IN_INDEX' => 2])], false],
            'composite unique index' => [[
                $valid[0],
                [
                    'NON_UNIQUE' => 0,
                    'COLUMN_NAME' => 'news_id',
                    'SEQ_IN_INDEX' => 2,
                    'SUB_PART' => null,
                ],
            ], false],
            'non unique index' => [[array_replace($valid[0], ['NON_UNIQUE' => 1])], false],
        ];
    }

    public function test_column_comment_must_match_the_canonical_chinese_comment(): void
    {
        $validator = $this->schemaDefinitionValidator();

        $this->assertTrue($validator::commentMatches([
            'COLUMN_COMMENT' => $validator::COLUMN_COMMENT,
        ]));
        $this->assertFalse($validator::commentMatches(['COLUMN_COMMENT' => '']));
        $this->assertFalse($validator::commentMatches(['COLUMN_COMMENT' => '错误注释']));
    }

    /** @return class-string */
    private function schemaDefinitionValidator(): string
    {
        require_once database_path(
            'migrations/2026_08_17_000001_restore_active_news_translation_generated_column.php'
        );
        $class = 'ActiveNewsTranslationSchemaDefinition';
        $this->assertTrue(
            class_exists($class),
            'The migration must expose its schema definition checks as a pure validator.'
        );

        return $class;
    }

    /** @return array<string, mixed> */
    private function validColumn(): array
    {
        return [
            'COLUMN_TYPE' => 'varchar(64)',
            'IS_NULLABLE' => 'YES',
            'COLUMN_DEFAULT' => null,
            'EXTRA' => 'STORED GENERATED',
            'GENERATION_EXPRESSION' => "(case when isnull(`deleted_at`) then concat(`news_id`,_utf8mb4':',`lang_code`) else NULL end)",
            'CHARACTER_SET_NAME' => 'utf8mb4',
            'COLLATION_NAME' => 'utf8mb4_unicode_ci',
        ];
    }
}
