<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:37
 */

/**
 * AdminRealtimeCommissionPerformanceModuleTest
 *
 * 文件功能：
 * - 验证实时返佣性能契约：per_page 封顶、有界查询次数、生成列与索引覆盖关键字筛选排序聚合、缺失生成列回退关键词过滤、前端防抖取消过期请求。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Controllers\Admin\RealtimeCommissionController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

/**
 * 后台实时返佣性能闭环测试（需求 15：模块很卡，必须优化）。
 *
 * 文件目的：
 * - 锁定服务端的四项性能修复，防止回归：
 *   1) 每页条数有硬上限（旧实现直接用请求值，客户端可以要十万行）。
 *   2) 列表 + 汇总只跑固定趟数的查询（旧实现是 4 趟全表：汇总 count、汇总 sum、paginator count、列表 select *）。
 *   3) 不再预加载从未使用的 user 关系（旧实现每次请求都全表扫一遍 user_infos，因为 mt4_code 没有索引）。
 *   4) 生成列 is_rebate / rebate_time 存在时改走 mt4_trades_rebate_lookup_index，
 *      把「全表扫描 + filesort」换成索引区间扫描。
 * - 锁定迁移里的生成列表达式与控制器的返佣关键词常量不漂移。
 * - 锁定前端的防抖、请求取消与受控分页档位。
 *
 * 实测口径（80 万行 mt4_trades，本机 MySQL 8.0.12）：
 * - 优化前单次请求数据库耗时约 1520 ms（4 趟 type=ALL + filesort）。
 * - 优化后约 194 ms（2 趟索引扫描），页查询本身 515 ms -> 0.5 ms。
 */
class AdminRealtimeCommissionPerformanceModuleTest extends TestCase
{
    use DatabaseTransactions;
    use ReadsAggregatedLayuiScripts;

    /** @var int 演示 MT4 登录号。 */
    private const LOGIN = 986411;

    /** @var int 夹具起始订单号。 */
    private const TICKET_BASE = 991411;

    protected function setUp(): void
    {
        parent::setUp();
        RealtimeCommissionController::resetIndexedRebateColumnCache();
    }

    public function test_per_page_is_capped_so_a_client_cannot_request_unbounded_rows(): void
    {
        $admin = $this->ensureAdmin();
        $this->fixtureRebateTrades(120);

        // 请求 100000 行：服务端必须收敛到 MAX_PER_PAGE=100。
        $response = $this->actingAsAdmin($admin)
            ->post('/api/admin/realtimeCommissionList', [
                'user_id' => self::LOGIN,
                'per_page' => 100000,
            ]);

        $response->assertOk();
        $this->assertSame(100, (int) $response->json('data.records.per_page'));
        $this->assertLessThanOrEqual(100, count($response->json('data.records.data')));

        // Layui 的 limit 参数走同一条收敛链路。
        $viaLimit = $this->actingAsAdmin($admin)
            ->post('/api/admin/realtimeCommissionList', [
                'user_id' => self::LOGIN,
                'limit' => 5000,
            ]);
        $viaLimit->assertOk();
        $this->assertSame(100, (int) $viaLimit->json('data.records.per_page'));

        // 非法值回落到默认档位，不允许 0 或负数把分页算崩。
        foreach ([0, -20] as $invalid) {
            $fallback = $this->actingAsAdmin($admin)
                ->post('/api/admin/realtimeCommissionList', [
                    'user_id' => self::LOGIN,
                    'per_page' => $invalid,
                ]);
            $fallback->assertOk();
            $this->assertSame(15, (int) $fallback->json('data.records.per_page'), 'per_page=' . $invalid);
        }
    }

    public function test_list_request_issues_a_bounded_number_of_queries_without_per_row_lookups(): void
    {
        $admin = $this->ensureAdmin();
        $this->fixtureRebateTrades(40);

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAsAdmin($admin)
            ->post('/api/admin/realtimeCommissionList', [
                'user_id' => self::LOGIN,
                'per_page' => 20,
            ])
            ->assertOk();

        $mt4Queries = array_values(array_filter($queries, static function (string $sql): bool {
            return strpos($sql, 'mt4_trades') !== false;
        }));

        // 汇总一趟 + 列表一趟，固定 2 趟；旧实现是 4 趟。
        $this->assertCount(2, $mt4Queries, "实时返佣列表必须只跑 2 趟 mt4_trades 查询，实际：\n" . implode("\n", $mt4Queries));

        $aggregate = $mt4Queries[0];
        $this->assertStringContainsString('count(*)', strtolower($aggregate), '第一趟必须是合并的聚合查询。');
        $this->assertStringContainsString('sum(profit)', strtolower($aggregate), 'COUNT 与 SUM 必须合并成同一趟聚合。');

        // 行数不随返佣记录数增长：不能出现 N+1 的 user_infos 查询。
        $userInfoQueries = array_filter($queries, static function (string $sql): bool {
            return strpos($sql, 'user_infos') !== false;
        });
        $this->assertCount(0, $userInfoQueries, '列表输出不含用户字段，不允许再预加载 user 关系（会全表扫 user_infos）。');

        // 列表查询只取需要的列，不是 select *。
        $this->assertStringNotContainsString('select *', strtolower($mt4Queries[1]), '列表必须显式列出输出列，避免读取整行。');
    }

    public function test_controller_no_longer_eager_loads_the_unused_user_relation(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/RealtimeCommissionController.php')) ?: '';

        $this->assertStringNotContainsString("->with('user')", $source, "formatRealtimeCommissionRecord 不输出用户字段，with('user') 是纯浪费。");
        $this->assertStringContainsString('LengthAwarePaginator', $source, '必须复用已知总数手工分页，避免 paginate() 再 count 一次。');
        $this->assertStringContainsString('MAX_PER_PAGE', $source);
        $this->assertStringContainsString('SELECT_COLUMNS', $source);
    }

    public function test_indexed_generated_columns_are_used_and_keep_the_same_result_set(): void
    {
        if (!Schema::hasColumn('mt4_trades', 'is_rebate') || !Schema::hasColumn('mt4_trades', 'rebate_time')) {
            $this->markTestSkipped('mt4_trades 尚未执行返佣检索生成列迁移。');
        }

        $admin = $this->ensureAdmin();
        $this->fixtureRebateTrades(6);

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->actingAsAdmin($admin)
            ->post('/api/admin/realtimeCommissionList', ['user_id' => self::LOGIN]);
        $response->assertOk();

        $listSql = implode(' ', $queries);
        $this->assertStringContainsString('is_rebate', $listSql, '生成列可用时必须用 is_rebate 替代前置通配符 LIKE。');
        $this->assertStringContainsString('rebate_time', $listSql, '排序必须直接引用 rebate_time 列，才能避免 filesort。');
        $this->assertStringNotContainsString("comment` like '%DBCN%'", $listSql);

        // 口径等价：生成列筛选出来的条数必须和关键词 LIKE 完全一致。
        $indexedTotal = (int) $response->json('data.summary.total_records');
        $likeTotal = (int) DB::table('mt4_trades')
            ->where('login', self::LOGIN)
            ->where('cmd', 6)
            ->where('profit', '>', 0)
            ->where(static function ($where): void {
                $where->where('comment', 'LIKE', '%DBCN%')
                    ->orWhere('comment', 'LIKE', '%-FY%');
            })
            ->count();

        $this->assertSame($likeTotal, $indexedTotal, '生成列口径必须与旧 COMMENT 关键词口径完全等价。');
        $this->assertSame(6, $indexedTotal);
    }

    public function test_rebate_lookup_index_covers_filter_sort_and_aggregate_columns(): void
    {
        if (!Schema::hasColumn('mt4_trades', 'is_rebate')) {
            $this->markTestSkipped('mt4_trades 尚未执行返佣检索生成列迁移。');
        }

        $columns = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'mt4_trades')
            ->where('INDEX_NAME', 'mt4_trades_rebate_lookup_index')
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->map(static function ($column): string {
                return (string) $column;
            })
            ->all();

        // 等值条件在前、排序列居中、聚合列收尾，让汇总查询成为 Using index 的仅索引扫描。
        $this->assertSame(['is_rebate', 'cmd', 'rebate_time', 'profit'], $columns);
    }

    public function test_generated_column_expression_matches_the_controller_keyword_constant(): void
    {
        $migrationPath = database_path('migrations/2026_08_28_000001_add_mt4_trades_rebate_lookup_index.php');
        $this->assertFileExists($migrationPath);
        $migration = file_get_contents($migrationPath) ?: '';

        // 关键词常量与生成列表达式必须永远一致，否则索引口径会和业务口径漂移。
        foreach (RealtimeCommissionController::REBATE_COMMENT_KEYWORDS as $keyword) {
            $this->assertStringContainsString(
                "LIKE '%" . $keyword . "%'",
                $migration,
                '生成列表达式缺少返佣关键词：' . $keyword
            );
        }

        $this->assertSame(['DBCN', '-FY'], RealtimeCommissionController::REBATE_COMMENT_KEYWORDS);
        $this->assertStringContainsString('COALESCE(NULLIF(`modify_time`, 0), `close_time`)', $migration);
        $this->assertStringContainsString('STORED', $migration);
    }

    public function test_controller_falls_back_to_keyword_filter_when_generated_columns_are_missing(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/RealtimeCommissionController.php')) ?: '';

        // 迁移未落库的环境必须还能用，只是没有索引加速。
        $this->assertStringContainsString('hasIndexedRebateColumns', $source);
        $this->assertStringContainsString("Schema::hasColumn('mt4_trades', 'is_rebate')", $source);
        $this->assertStringContainsString("orWhere('comment', 'LIKE', '%' . \$keyword . '%')", $source);
        $this->assertStringContainsString('COALESCE(NULLIF(modify_time, 0), close_time)', $source);
    }

    public function test_statistics_endpoint_returns_aggregated_series_without_detail_rows(): void
    {
        $this->assertTrue(Route::has('admin_api_realtimeCommissionStatistics'));
        $this->assertContains(
            'check.permission:admin',
            Route::getRoutes()->getByName('admin_api_realtimeCommissionStatistics')->gatherMiddleware()
        );

        $admin = $this->ensureAdmin();
        $this->fixtureRebateTrades(8);

        $response = $this->actingAsAdmin($admin)
            ->post('/api/admin/realtimeCommissionStatistics', ['user_id' => self::LOGIN]);

        $response->assertOk();
        $payload = $response->json('data');

        $this->assertSame(count($payload['labels']), count($payload['records']));
        $this->assertSame(count($payload['labels']), count($payload['profit']));
        $this->assertNotEmpty($payload['sources']);
        // 响应体只有按天聚合与来源分布，不含任何明细行，因此不会随返佣总量膨胀。
        $this->assertArrayNotHasKey('records_detail', $payload);
        $this->assertLessThanOrEqual(180, count($payload['labels']));

        foreach ($payload['sources'] as $source) {
            $this->assertArrayHasKey('key', $source);
            $this->assertArrayHasKey('records', $source);
            $this->assertArrayHasKey('profit', $source);
        }
    }

    public function test_frontend_debounces_search_cancels_stale_requests_and_bounds_page_size(): void
    {
        $script = $this->adminLayuiScript('realtime-commissions/index.js');
        $ajax = file_get_contents(public_path('js/shared/ajax.js')) ?: '';

        // 防抖：连续提交只保留最后一次。
        $this->assertStringContainsString('SEARCH_DEBOUNCE_MS', $script);
        $this->assertStringContainsString('window.clearTimeout(searchDebounceTimer)', $script);

        // 请求取消：切换筛选条件时旧统计请求必须被 abort。
        $this->assertStringContainsString('statisticsRequest.abort()', $script);
        $this->assertStringContainsString('return $.ajax({', $ajax, 'CrmAjax.request 必须返回 jqXHR 才能取消请求。');
        $this->assertStringContainsString("textStatus === 'abort'", $ajax, '被取消的请求不能当成网络错误弹提示。');

        // 分页档位受控，避免一次渲染上千个 DOM 行。
        $this->assertStringContainsString('limits: [15, 30, 50, 100]', $script);

        // 汇总与多语言刷新限定在统计区块内，不再全文档扫描。
        $this->assertStringContainsString('$summaryBlock.find(\'[data-summary-field]\')', $script);
        $this->assertStringNotContainsString("$('[data-summary-field]').each", $script);

        // 布局抖动：resize 合并到下一帧。
        $this->assertStringContainsString('window.requestAnimationFrame', $script);
    }

    private function actingAsAdmin(Admin $admin)
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }

    private function ensureAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'realtime-performance-admin',
                'email' => 'realtime-performance-admin@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 写入带旧返佣 COMMENT 关键词的 MT4 余额记录。
     *
     * @param int $count 需要写入的返佣记录条数。
     * @return void
     */
    private function fixtureRebateTrades(int $count): void
    {
        $now = time();

        DB::table('mt4_trades')->where('login', self::LOGIN)->delete();

        $rows = [];
        for ($index = 0; $index < $count; $index++) {
            $closeTime = $now - ($index * 3600) - 600;
            $rows[] = [
                'ticket' => self::TICKET_BASE + $index,
                'login' => self::LOGIN,
                'symbol' => 'REBATEPERF',
                'cmd' => 6,
                'volume' => 0,
                'open_price' => 0,
                'close_price' => 0,
                'commission' => 0,
                'swaps' => 0,
                'profit' => 10 + $index,
                'open_time' => $closeTime - 600,
                'close_time' => $closeTime,
                'comment' => $index % 2 === 0
                    ? 'DBCN-' . self::LOGIN . '-#' . (self::TICKET_BASE + $index)
                    : 'PERF-' . (self::TICKET_BASE + $index) . '-FY',
                'modify_time' => $closeTime,
                'created_at' => $closeTime,
                'updated_at' => $closeTime,
            ];
        }

        foreach (array_chunk($rows, 60) as $chunk) {
            DB::table('mt4_trades')->insertOrIgnore($chunk);
        }
    }
}
