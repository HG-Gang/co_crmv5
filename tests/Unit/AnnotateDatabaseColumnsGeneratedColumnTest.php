<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 04:23
 */

/**
 * AnnotateDatabaseColumnsGeneratedColumnTest
 *
 * 文件功能：
 * - 验证数据库列注释工具保留生成列定义：加注释后生成列表达式不变、虚拟生成列保持虚拟、MariaDB 转义的生成表达式在 DDL 前正确反转义。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\AnnotateDatabaseColumns;
use ReflectionMethod;
use Tests\TestCase;

final class AnnotateDatabaseColumnsGeneratedColumnTest extends TestCase
{
    public function test_generated_column_definition_is_preserved_when_adding_a_comment(): void
    {
        $method = new ReflectionMethod(AnnotateDatabaseColumns::class, 'buildModifySql');
        $method->setAccessible(true);

        $sql = $method->invoke(new AnnotateDatabaseColumns(), [
            'TABLE_NAME' => 'news_langs',
            'COLUMN_NAME' => 'active_translation_key',
            'COLUMN_TYPE' => 'varchar(64)',
            'IS_NULLABLE' => 'YES',
            'COLUMN_DEFAULT' => null,
            'EXTRA' => 'STORED GENERATED',
            'GENERATION_EXPRESSION' => "(case when isnull(`deleted_at`) then concat(`news_id`,':',`lang_code`) else NULL end)",
            'CHARACTER_SET_NAME' => 'utf8mb4',
            'COLLATION_NAME' => 'utf8mb4_unicode_ci',
        ], '当前生效的翻译 key');

        $this->assertStringContainsString(
            "GENERATED ALWAYS AS ((case when isnull(`deleted_at`) then concat(`news_id`,':',`lang_code`) else NULL end)) STORED",
            $sql
        );
        $this->assertStringNotContainsString('varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL', $sql);
    }

    public function test_virtual_generated_column_definition_remains_virtual(): void
    {
        $method = new ReflectionMethod(AnnotateDatabaseColumns::class, 'buildModifySql');
        $method->setAccessible(true);

        $sql = $method->invoke(new AnnotateDatabaseColumns(), [
            'TABLE_NAME' => 'fixture_table',
            'COLUMN_NAME' => 'virtual_key',
            'COLUMN_TYPE' => 'varchar(64)',
            'IS_NULLABLE' => 'YES',
            'COLUMN_DEFAULT' => null,
            'EXTRA' => 'VIRTUAL GENERATED',
            'GENERATION_EXPRESSION' => 'concat(`left_value`,`right_value`)',
            'CHARACTER_SET_NAME' => 'utf8mb4',
            'COLLATION_NAME' => 'utf8mb4_unicode_ci',
        ], '虚拟生成列');

        $this->assertStringContainsString(
            'GENERATED ALWAYS AS (concat(`left_value`,`right_value`)) VIRTUAL',
            $sql
        );
        $this->assertStringNotContainsString(' STORED', $sql);
    }

    public function test_mariadb_escaped_generation_expression_is_unescaped_before_ddl(): void
    {
        $method = new ReflectionMethod(AnnotateDatabaseColumns::class, 'buildModifySql');
        $method->setAccessible(true);

        $sql = $method->invoke(new AnnotateDatabaseColumns(), [
            'TABLE_NAME' => 'news_langs',
            'COLUMN_NAME' => 'active_translation_key',
            'COLUMN_TYPE' => 'varchar(64)',
            'IS_NULLABLE' => 'YES',
            'COLUMN_DEFAULT' => null,
            'EXTRA' => 'STORED GENERATED',
            'GENERATION_EXPRESSION' => "(case when isnull(`deleted_at`) then concat(`news_id`,_utf8mb4\\':\\',`lang_code`) else NULL end)",
            'CHARACTER_SET_NAME' => 'utf8mb4',
            'COLLATION_NAME' => 'utf8mb4_unicode_ci',
        ], '当前生效的翻译 key');

        $this->assertStringContainsString("concat(`news_id`,_utf8mb4':',`lang_code`)", $sql);
        $this->assertStringNotContainsString("_utf8mb4\\':\\'", $sql);
    }
}
