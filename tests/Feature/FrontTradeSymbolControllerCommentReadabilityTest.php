<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:06
 */

/**
 * FrontTradeSymbolControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 TradeSymbolController 中文注释与真实数据来源：品种下拉来自真实 symbol_prices 表、新旧字段兼容边界有中文说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台交易品种控制器中文注释与真实数据来源可读性测试。
 *
 * 测试目标：
 * - 只读取 TradeSymbolController 源码，不连接真实数据库。
 * - 约束交易品种下拉选项必须来自真实 symbol_prices 表，不能使用 mock 或写死数据。
 * - 约束 sym_symbol/symbol、voided/status 等新旧字段兼容边界必须有中文逻辑说明。
 */
class FrontTradeSymbolControllerCommentReadabilityTest extends TestCase
{
    public function test_trade_symbol_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/TradeSymbolController.php')) ?: '';

        $expectedComments = [
            '前台交易品种控制器',
            'GET /api/front/trade-symbols',
            'symbol_prices 表',
            '动态下拉选项',
            'sym_symbol 表示旧表结构中的交易品种字段',
            'symbol 表示新表结构中的交易品种字段',
            'voided 表示旧表结构中的启用状态字段',
            'status 表示新表结构中的启用状态字段',
            'deleted_at 表示新表结构中的软删除时间',
            'list 表示前端 select 组件使用的选项数组',
            'value 和 label 都使用交易品种编码',
            'response.query_success',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'TradeSymbolController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_trade_symbol_controller_uses_real_symbol_price_table_without_mock_data(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/TradeSymbolController.php')) ?: '';

        $this->assertStringContainsString("Schema::hasTable('symbol_prices')", $source);
        $this->assertStringContainsString("DB::table('symbol_prices')", $source);
        $this->assertStringContainsString('distinct()', $source);
        $this->assertStringContainsString("->whereNotNull(\$symbolColumn)", $source);
        $this->assertStringContainsString("->where(\$symbolColumn, '<>', '')", $source);
        $this->assertStringContainsString("->whereNull('deleted_at')", $source);
        $this->assertStringContainsString("'response.query_success'", $source);
        $this->assertStringNotContainsString('mock', strtolower($source), '交易品种接口不能使用 mock 数据。');
        $this->assertStringNotContainsString('XAUUSD,EURUSD', $source, '交易品种接口不能写死示例品种。');
    }
}
