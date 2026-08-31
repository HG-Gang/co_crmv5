<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:50
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 数据库全中文注释闭环测试。
 *
 * 文件功能：
 * - 验证全库 77 张表均有中文表注释。
 * - 验证全部字段注释为中文（允许 JWT/MT4 等组合技术术语，不允许中英双语对照与纯英文）。
 * - 验证 db:annotate-columns 命令幂等（重复执行零变更）。
 *
 * 适用场景：
 * - 任何改动数据库结构或注释映射后回归。
 *
 * 入参例子：无（直接查 information_schema 与执行命令）。
 *
 * 返回值：断言通过即表示全库注释契约成立。
 *
 * 异常或失败场景：
 * - 存在无注释表/字段、双语注释或纯英文注释时失败。
 */
final class DatabaseChineseCommentsClosureModuleTest extends TestCase
{
    /**
     * 测试前置：全量回归中其他测试可能重建表（清空字段注释），
     * 此处先执行 db:annotate-columns 自愈，保证本文件断言的是“全中文注释契约”而非运行顺序。
     *
     * @return void 无返回值。
     */
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:annotate-columns');
    }

    /**
     * 全部 77 张表必须存在中文表注释。
     *
     * @return void 断言通过不返回值。
     */
    public function test_all_tables_have_chinese_table_comment(): void
    {
        $tables = DB::select("SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.tables WHERE table_schema = DATABASE()");
        $this->assertNotEmpty($tables);
        $bad = [];
        foreach ($tables as $t) {
            $comment = trim((string) $t->TABLE_COMMENT);
            if ($comment === '' || ! preg_match('/[\x{4e00}-\x{9fff}]/u', $comment)) {
                $bad[] = $t->TABLE_NAME;
            }
        }
        $this->assertSame([], $bad, '以下表缺少中文表注释: ' . implode(', ', $bad));
    }

    /**
     * 全部字段注释必须为中文（允许 JWT/MT4 等组合技术术语，禁止双语对照与纯英文）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_all_columns_have_chinese_comment(): void
    {
        $columns = DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_COMMENT FROM information_schema.columns WHERE table_schema = DATABASE()"
        );
        $this->assertNotEmpty($columns);
        $bad = [];
        foreach ($columns as $c) {
            $comment = trim((string) $c->COLUMN_COMMENT);
            // 空注释 / 双语对照 / 纯英文（无任何中文字符）均为违规。
            if ($comment === '') {
                $bad[] = "{$c->TABLE_NAME}.{$c->COLUMN_NAME}=空";
                continue;
            }
            if (strpos($comment, ' | ') !== false) {
                $bad[] = "{$c->TABLE_NAME}.{$c->COLUMN_NAME}=双语";
                continue;
            }
            if (! preg_match('/[\x{4e00}-\x{9fff}]/u', $comment)) {
                $bad[] = "{$c->TABLE_NAME}.{$c->COLUMN_NAME}=非中文";
            }
        }
        $this->assertSame([], $bad, '以下字段注释不合规: ' . implode(', ', array_slice($bad, 0, 20)));
    }

    /**
     * db:annotate-columns 命令必须幂等（已全中文时执行零变更）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_annotate_command_is_idempotent(): void
    {
        $exit = Artisan::call('db:annotate-columns', ['--dry-run' => true]);
        $this->assertSame(0, $exit);
        $output = Artisan::output();
        // 幂等断言：无任何待变更语句。
        $this->assertStringContainsString('语句总数 0', $output, '全中文状态下命令必须零变更');
    }
}
