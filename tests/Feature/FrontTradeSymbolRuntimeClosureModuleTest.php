<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:05
 */

/**
 * FrontTradeSymbolRuntimeClosureModuleTest
 *
 * 文件功能：
 * - 验证前台交易品种选项运行时契约：仅返回活跃且未删除的数据库行。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 前台交易品种动态选项真实数据闭环测试。
 *
 * 测试仅向 co_crmv5_test.symbol_prices 写入带 ZDB_ 前缀的隔离行，并在 finally 中按主键清理。
 */
class FrontTradeSymbolRuntimeClosureModuleTest extends TestCase
{
    public function test_trade_symbol_options_only_return_active_non_deleted_database_rows(): void
    {
        $this->assertTrue(Schema::hasTable('symbol_prices'));
        $this->assertTrue(Schema::hasColumn('symbol_prices', 'deleted_at'));

        $insertedIds = [];
        $now = now()->format('Y-m-d H:i:s');
        $timestamp = time();

        try {
            foreach ([
                ['ZDB_XAUUSD', 1, null],
                ['ZDB_EURUSD', 1, null],
                ['ZDB_XAUUSD', 1, null],
                ['ZDB_STOPPED', 0, null],
                ['ZDB_DELETED', 1, $timestamp],
                ['   ', 1, null],
            ] as [$symbol, $enabled, $deletedAt]) {
                $insertedIds[] = DB::table('symbol_prices')->insertGetId(
                    $this->symbolRow($symbol, $enabled, $deletedAt, $now, $timestamp)
                );
            }

            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->getJson('/api/front/trade-symbols');

            $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

            $databaseOptions = array_values(array_filter(
                $response->json('data.list') ?: [],
                static function (array $option): bool {
                    return strpos((string) ($option['value'] ?? ''), 'ZDB_') === 0;
                }
            ));

            $this->assertSame([
                ['value' => 'ZDB_EURUSD', 'label' => 'ZDB_EURUSD'],
                ['value' => 'ZDB_XAUUSD', 'label' => 'ZDB_XAUUSD'],
            ], $databaseOptions);
            $this->assertNotContains(['value' => '', 'label' => ''], $response->json('data.list') ?: []);
        } finally {
            if ($insertedIds !== []) {
                DB::table('symbol_prices')->whereIn('id', $insertedIds)->delete();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function symbolRow(string $symbol, int $enabled, ?int $deletedAt, string $now, int $timestamp): array
    {
        $columns = array_flip(Schema::getColumnListing('symbol_prices'));
        $row = [
            'symbol' => $symbol,
            'sym_symbol' => $symbol,
            'time' => $now,
            'bid' => 1.1,
            'ask' => 1.2,
            'low' => 1.0,
            'high' => 1.3,
            'direction' => 0,
            'digits' => 5,
            'spread' => 0.1,
            'group_id' => 3,
            'sym_grp_id' => 3,
            'status' => $enabled,
            'voided' => $enabled,
            'modify_time' => $now,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => $deletedAt,
        ];

        return array_intersect_key($row, $columns);
    }
}
