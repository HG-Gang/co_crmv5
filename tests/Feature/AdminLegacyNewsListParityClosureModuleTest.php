<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 12:53
 */

/**
 * AdminLegacyNewsListParityClosureModuleTest
 *
 * 文件功能：
 * - 验证新闻公告列表双口径等价：现代列表筛选/排序/分页校验、旧 rows/limit/per_page 优先级与默认日期、旧严格空 envelope、旧适配器转发到现代路由且不带新闻 SQL。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLegacyNewsListParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_modern_list_validates_filters_and_orders_by_updated_at_then_id_desc(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'News List Order ' . uniqid('', true);
        $olderId = $this->insertNews($prefix . ' older', strtotime('2026-08-17 12:00:00'), 1);
        $tieLowId = $this->insertNews($prefix . ' tie-low', strtotime('2026-08-18 12:00:00'), 1);
        $tieHighId = $this->insertNews($prefix . ' tie-high', strtotime('2026-08-18 12:00:00'), 1);

        $response = $this->modernList($admin, [
            'page' => 1,
            'per_page' => 10,
            'title' => $prefix,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-18',
            'is_published' => 1,
        ])->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame(
            [$tieHighId, $tieLowId, $olderId],
            array_column($response->json('data.data'), 'id')
        );
    }

    public function test_modern_date_filter_uses_application_timezone_start_and_end_of_day(): void
    {
        config(['app.timezone' => 'Asia/Shanghai']);
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Timezone Boundary ' . uniqid('', true);
        $timezone = config('app.timezone');
        $previous = CarbonImmutable::create(2026, 8, 18, 23, 59, 59, $timezone)->timestamp;
        $first = CarbonImmutable::create(2026, 8, 19, 0, 0, 0, $timezone)->timestamp;
        $last = CarbonImmutable::create(2026, 8, 19, 23, 59, 59, $timezone)->timestamp;
        $next = CarbonImmutable::create(2026, 8, 20, 0, 0, 0, $timezone)->timestamp;
        $this->insertNews($prefix . ' previous', $previous, 1);
        $firstId = $this->insertNews($prefix . ' first', $first, 1);
        $lastId = $this->insertNews($prefix . ' last', $last, 1);
        $this->insertNews($prefix . ' next', $next, 1);

        $response = $this->modernList($admin, [
            'title' => $prefix,
            'start_date' => '2026-08-19',
            'end_date' => '2026-08-19',
        ])->assertOk()->assertJsonPath('data.total', 2);

        $this->assertSame([$lastId, $firstId], array_column($response->json('data.data'), 'id'));
    }

    public function test_modern_list_accepts_empty_optional_filters_from_layui_forms(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Empty Optional Filters ' . uniqid('', true);
        $publishedId = $this->insertNews($prefix . ' published', time(), 1);
        $unpublishedId = $this->insertNews($prefix . ' unpublished', time() - 1, 0);

        $response = $this->modernList($admin, [
            'title' => $prefix,
            'start_date' => '',
            'end_date' => '',
            'is_published' => '',
        ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.per_page', 15);

        $this->assertSame(
            [$publishedId, $unpublishedId],
            array_column($response->json('data.data'), 'id')
        );
    }

    public function test_modern_list_treats_all_empty_layui_filters_as_absent_without_filtering_status_zero(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $updatedAt = time() + 100;

        for ($index = 1; $index <= 16; $index++) {
            $this->insertNews(
                'All Empty Filters ' . uniqid('', true) . ' ' . $index,
                $updatedAt + $index,
                $index % 2
            );
        }

        $expectedTotal = (int) DB::table('news')->whereNull('deleted_at')->count();
        $response = $this->modernList($admin, [
            'title' => '',
            'start_date' => '',
            'end_date' => '',
            'is_published' => '',
        ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.total', $expectedTotal)
            ->assertJsonPath('data.per_page', 15);

        $rows = $response->json('data.data');
        $this->assertCount(min(15, $expectedTotal), $rows);
        $statuses = array_values(array_unique(array_map('intval', array_column($rows, 'is_published'))));
        sort($statuses);
        $this->assertSame([0, 1], $statuses);
    }

    /**
     * @dataProvider invalidListPayloadProvider
     */
    public function test_modern_list_rejects_invalid_scalar_pagination_date_and_status_inputs(array $payload): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->modernList($admin, $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function invalidListPayloadProvider(): array
    {
        return [
            'page zero' => [['page' => 0]],
            'page array' => [['page' => ['1']]],
            'page object' => [['page' => (object) ['value' => 1]]],
            'per page zero' => [['per_page' => 0]],
            'per page too large' => [['per_page' => 101]],
            'title array' => [['title' => ['unsafe']]],
            'invalid start date' => [['start_date' => '2026-02-30']],
            'date array' => [['end_date' => ['2026-08-19']]],
            'reversed dates' => [['start_date' => '2026-08-19', 'end_date' => '2026-08-18']],
            'invalid status' => [['is_published' => 2]],
            'status array' => [['is_published' => ['1']]],
            'status object' => [['is_published' => (object) ['value' => 1]]],
        ];
    }

    public function test_legacy_list_uses_rows_limit_per_page_precedence_defaults_dates_and_adapts_rows(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Legacy List Adapter ' . uniqid('', true);
        $this->insertNews($prefix . ' excluded', strtotime('2023-12-31 12:00:00'), 1);
        $includedId = $this->insertNews($prefix . ' included-1', strtotime(date('Y-m-d') . ' 10:00:00'), 1);
        $this->insertNews($prefix . ' included-2', strtotime(date('Y-m-d') . ' 11:00:00'), 1);

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/news/newsListSearch', [
            'page' => 1,
            'rows' => 1,
            'limit' => 2,
            'per_page' => 3,
            'title' => $prefix,
            'startdate' => '',
            'enddate' => '',
            'ispush' => 1,
        ])->assertOk();

        $rows = $response->json('rows');
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $response->assertJsonPath('total', 2);
        $row = $rows[0];
        $this->assertSame($includedId + 1, $row['id']);
        foreach (['id', 'title', 'content', 'author_id', 'author_name', 'is_published', 'created_at', 'updated_at'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
        $this->assertSame($row['id'], $row['news_id']);
        $this->assertSame($row['title'], $row['news_title']);
        $this->assertSame($row['content'], $row['news_content']);
        $this->assertSame($row['is_published'], $row['is_push']);
        $this->assertSame($row['author_name'], $row['news_user']);
        $this->assertSame($row['updated_at'], $row['rec_upd_date']);
        $this->assertSame($row['created_at'], $row['rec_crt_date']);
    }

    public function test_legacy_list_prefers_rows_when_rows_is_greater_than_limit_and_per_page(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Legacy Rows Greater ' . uniqid('', true);
        for ($index = 1; $index <= 5; $index++) {
            $this->insertNews($prefix . ' ' . $index, time() + $index, 1);
        }

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/news/newsListSearch', [
            'title' => $prefix,
            'rows' => 3,
            'limit' => 2,
            'per_page' => 1,
            'startdate' => '',
            'enddate' => '',
        ])->assertOk();

        $this->assertCount(3, $response->json('rows'));
        $response->assertJsonPath('total', 5);
    }

    public function test_legacy_list_uses_limit_when_rows_is_absent_and_limit_exceeds_per_page(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Legacy Limit Greater ' . uniqid('', true);
        for ($index = 1; $index <= 5; $index++) {
            $this->insertNews($prefix . ' ' . $index, time() + $index, 1);
        }

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/news/newsListSearch', [
            'title' => $prefix,
            'limit' => 3,
            'per_page' => 1,
            'startdate' => '',
            'enddate' => '',
        ])->assertOk();

        $this->assertCount(3, $response->json('rows'));
        $response->assertJsonPath('total', 5);
    }

    public function test_legacy_list_uses_per_page_when_rows_and_limit_are_absent(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Legacy Per Page Only ' . uniqid('', true);
        for ($index = 1; $index <= 4; $index++) {
            $this->insertNews($prefix . ' ' . $index, time() + $index, 1);
        }

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/news/newsListSearch', [
            'title' => $prefix,
            'per_page' => 2,
            'startdate' => '',
            'enddate' => '',
        ])->assertOk();

        $this->assertCount(2, $response->json('rows'));
        $response->assertJsonPath('total', 4);
    }

    public function test_modern_list_defaults_to_fifteen_rows_when_per_page_is_absent(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Modern Default Fifteen ' . uniqid('', true);
        for ($index = 1; $index <= 16; $index++) {
            $this->insertNews($prefix . ' ' . $index, time() + $index, 1);
        }

        $response = $this->modernList($admin, [
            'title' => $prefix,
        ])->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertCount(15, $response->json('data.data'));
        $response->assertJsonPath('data.total', 16);
    }

    public function test_legacy_empty_list_keeps_the_strict_old_empty_envelope(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/newsListSearch', [
            'title' => 'No Such Legacy News ' . uniqid('', true),
            'startdate' => '',
            'enddate' => '',
        ])->assertOk()
            ->assertJsonPath('rows', '')
            ->assertJsonPath('total', '');
    }

    public function test_legacy_out_of_range_page_also_keeps_the_strict_old_empty_envelope(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $title = 'Legacy Empty Page ' . uniqid('', true);
        $this->insertNews($title, strtotime(date('Y-m-d') . ' 12:00:00'), 1);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/newsListSearch', [
            'title' => $title,
            'page' => 2,
            'rows' => 1,
            'startdate' => '',
            'enddate' => '',
        ])->assertOk()
            ->assertJsonPath('rows', '')
            ->assertJsonPath('total', '');
    }

    public function test_legacy_list_defaults_to_twenty_rows_without_any_pagination_field(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $prefix = 'Legacy Default Twenty ' . uniqid('', true);
        $updatedAt = strtotime(date('Y-m-d') . ' 12:00:00');
        for ($index = 1; $index <= 21; $index++) {
            $this->insertNews($prefix . ' ' . $index, $updatedAt + $index, 1);
        }

        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/news/newsListSearch', [
            'title' => $prefix,
            'startdate' => '',
            'enddate' => '',
        ])->assertOk();

        $rows = $response->json('rows');
        $this->assertIsArray($rows);
        $this->assertCount(20, $rows);
        $response->assertJsonPath('total', 21);
    }

    /**
     * @dataProvider invalidLegacyPayloadProvider
     */
    public function test_legacy_list_rejects_invalid_pagination_dates_and_status(array $payload): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/news/newsListSearch', $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function invalidLegacyPayloadProvider(): array
    {
        return [
            'rows array' => [['rows' => ['15']]],
            'rows object' => [['rows' => (object) ['value' => 15]]],
            'rows zero' => [['rows' => 0]],
            'rows too large' => [['rows' => 101]],
            'limit too large' => [['limit' => 101]],
            'invalid start date' => [['startdate' => 'not-a-date']],
            'reversed dates' => [['startdate' => '2026-08-19', 'enddate' => '2026-08-18']],
            'invalid push state' => [['ispush' => 2]],
        ];
    }

    public function test_legacy_list_adapter_forwards_to_the_modern_route_without_news_sql(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/LegacyAdminController.php')) ?: '';
        $start = strpos($source, 'private function forwardLegacyNewsList');
        $end = strpos($source, "\n    private function", $start + 1);
        $method = $start === false ? '' : substr($source, $start, $end - $start);

        $this->assertNotSame('', $method);
        $this->assertStringContainsString("'admin_api_newsList'", $method);
        $this->assertStringNotContainsString('News::', $method);
        $this->assertStringNotContainsString("DB::table('news')", $method);
    }

    private function modernList(Admin $admin, array $payload)
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')->postJson('/api/admin/newsList', $payload);
    }

    private function insertNews(string $title, int $updatedAt, int $isPublished): int
    {
        return (int) DB::table('news')->insertGetId([
            'title' => $title,
            'content' => 'Content: ' . $title,
            'image' => '',
            'author_id' => 1,
            'author_name' => 'List Admin',
            'is_published' => $isPublished,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
            'deleted_at' => null,
        ]);
    }
}
