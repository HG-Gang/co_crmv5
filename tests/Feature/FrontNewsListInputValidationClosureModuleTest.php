<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 12:34
 */

/**
 * FrontNewsListInputValidationClosureModuleTest
 *
 * 文件功能：
 * - 验证前台新闻列表输入校验闭环：现代列表拒绝非法分页与日期、旧列表拒绝任何非法别名并保留表格契约、per_page/limit/rows 优先级、校验先于新闻查询。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Feature\Concerns\CreatesLegacySmokeUsers;

class FrontNewsListInputValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacySmokeUsers;

    /**
     * 旧会话用例统一自建真实用户，避免依赖其他测试残留的 990001 数据。
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureLegacySmokeUser(990001);
    }

    public function test_modern_list_rejects_invalid_pagination_and_dates(): void
    {
        foreach ([
            ['page' => '1abc'], ['page' => 0], ['page' => -1],
            ['per_page' => '1abc'], ['per_page' => 0], ['per_page' => -1], ['per_page' => 101],
            ['date_from' => '2026-02-30'], ['date_to' => '11/07/2026'],
            ['startdate' => '2026-02-30'], ['enddate' => '2026-07-11T00:00:00'],
            ['date_from' => '2026-07-12', 'date_to' => '2026-07-11'],
            ['date_from' => '2026-07-10', 'enddate' => '2026-07-09'],
        ] as $query) {
            $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->getJson('/api/front/news?' . http_build_query($query))
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    public function test_legacy_list_rejects_every_provided_invalid_alias_and_preserves_table_contract(): void
    {
        foreach ([
            ['page' => '1abc'], ['page' => 0],
            ['per_page' => '2abc'], ['limit' => 0], ['rows' => 101],
            ['date_from' => '2026-02-30'], ['date_to' => '2026-02-30'],
            ['startdate' => '2026-02-30'], ['enddate' => '2026-13-01'],
            ['date_from' => '2026-07-12', 'enddate' => '2026-07-11'],
        ] as $payload) {
            $response = $this->withSession($this->legacyUserSession())
                ->postJson('/user/newsListSearch', $payload)
                ->assertOk();
            $response->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
                ->assertJsonPath('rows', [])
                ->assertJsonPath('total', 0);
            $this->assertSame($response->json('message'), $response->json('msg'));
            $this->assertNotSame('', trim((string) $response->json('message')));
        }
    }

    public function test_legacy_rows_paginates_and_explicit_priority_is_per_page_then_limit_then_rows(): void
    {
        $base = random_int(210000000, 220000000);
        $now = time() + 5000;
        foreach (range(1, 5) as $offset) {
            DB::table('news')->insert([
                'id' => $base + $offset,
                'title' => 'Rows contract ' . $offset,
                'content' => 'content',
                'image' => '',
                'author_id' => 0,
                'author_name' => 'Boundary',
                'is_published' => 1,
                'created_at' => $now + $offset,
                'updated_at' => $now + $offset,
                'deleted_at' => null,
            ]);
        }

        $rows = $this->withSession($this->legacyUserSession())
            ->postJson('/user/newsListSearch', ['title' => 'Rows contract', 'page' => 2, 'rows' => 2])
            ->assertOk()->json('rows');
        $this->assertCount(2, $rows);
        $this->assertSame($base + 3, (int) $rows[0]['news_id']);

        $priority = $this->withSession($this->legacyUserSession())->postJson('/user/newsListSearch', [
            'title' => 'Rows contract', 'page' => 1, 'rows' => 4, 'limit' => 3, 'per_page' => 2,
        ])->assertOk()->json('rows');
        $this->assertCount(2, $priority);

        $limitPriority = $this->withSession($this->legacyUserSession())->postJson('/user/newsListSearch', [
            'title' => 'Rows contract', 'page' => 1, 'rows' => 4, 'limit' => 3,
        ])->assertOk()->json('rows');
        $this->assertCount(3, $limitPriority);
    }

    public function test_validation_precedes_news_query_in_both_entry_methods(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/NewsController.php')) ?: '';
        $modern = substr($source, strpos($source, 'public function newsList('), strpos($source, 'public function newsListSearch(') - strpos($source, 'public function newsList('));
        $legacy = substr($source, strpos($source, 'public function newsListSearch('), strpos($source, 'public function newsDetail(') - strpos($source, 'public function newsListSearch('));

        foreach ([$modern, $legacy] as $method) {
            $validationPosition = strpos($method, 'Validator::make(');
            $queryPosition = strpos($method, '$this->newsQuery(');
            $this->assertNotFalse($validationPosition);
            $this->assertNotFalse($queryPosition);
            $this->assertLessThan($queryPosition, $validationPosition);
        }
    }

    /** @return array<string, array<string, int>> */
    private function legacyUserSession(): array
    {
        return ['suser' => ['user_id' => 990001]];
    }
}
