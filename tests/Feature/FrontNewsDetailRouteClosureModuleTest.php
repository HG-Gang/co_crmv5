<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:07
 */

/**
 * 前台新闻详情路由闭环测试。
 *
 * 文件功能：
 * - 验证遗留详情路由 /user/news/news_detail/{id} 仅接受已发布且未删除的数字新闻 ID，
 *   并对内容做安全净化（去除 script、onerror、javascript: 等）。
 * - 验证现代详情页 /front/news/detail/{id} 校验新闻存在，并通过 data 属性暴露初始新闻 ID，
 *   页面不含内联脚本。
 * - 验证新闻 API /api/front/news 在分页前精确过滤 news_id，拒绝非严格数字过滤值。
 * - 验证新闻模块仅在加载到准确行后打开一次初始详情弹窗。
 *
 * 适用场景：
 * - 前台新闻详情路由与前端模块的回归测试，覆盖 ID 校验、内容净化与弹窗行为。
 *
 * 入参例子：
 * - GET /user/news/news_detail/{publishedId}、/front/news/detail/{publishedId}?frame=1。
 * - GET /api/front/news?news_id={publishedId}&page=1&per_page=1。
 *
 * 返回值：
 * - 合法 ID 返回 200 并渲染净化后的内容；未发布/已删除/非法 ID 返回 404。
 * - API 非法 news_id 返回 VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 未发布、已删除、不存在或非数字 ID 均返回 404；XSS 内容被净化。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Support\CreatesLegacyFrontUserFixture;

class FrontNewsDetailRouteClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacyFrontUserFixture;

    /**
     * 已发布新闻的夹具 ID。新旧两条详情路由与 API 列表都以它验证可见性。
     * @var int
     */
    private $publishedId;

    /**
     * 未发布新闻的夹具 ID。验证详情路由与列表都不返回未发布内容。
     * @var int
     */
    private $unpublishedId;

    /**
     * 被软删除新闻的夹具 ID。验证软删除内容不可见（news 表带 deleted_at 过滤）。
     * @var int
     */
    private $deletedId;

    /**
     * 发布时间更晚的干扰新闻 ID。验证"最新一条"类断言取的是正确记录而不是干扰项。
     * @var int
     */
    private $newerDistractorId;

    /**
     * 夹具创建的登录用户 ID，用于带鉴权访问新闻接口的用例。
     * @var int
     */
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishedId = random_int(230000000, 239999999);
        $this->unpublishedId = $this->publishedId + 1;
        $this->deletedId = $this->publishedId + 2;
        $this->newerDistractorId = $this->publishedId + 3;
        $this->userId = random_int(260000000, 269999999);
        $now = time();

        try {
            DB::table('news')->insert([
            [
                'id' => $this->publishedId,
                'title' => 'Direct detail published news',
                'content' => '<p>Direct detail published content</p><script>alert("news-xss")</script><img src="/safe.png" onerror="alert(1)"><a href="javascript:alert(2)">unsafe link</a>',
                'image' => '',
                'author_id' => 0,
                'author_name' => 'Detail Admin',
                'is_published' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id' => $this->unpublishedId,
                'title' => 'Direct detail unpublished news',
                'content' => '<p>Unpublished</p>',
                'image' => '',
                'author_id' => 0,
                'author_name' => 'Detail Admin',
                'is_published' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id' => $this->deletedId,
                'title' => 'Direct detail deleted news',
                'content' => '<p>Deleted</p>',
                'image' => '',
                'author_id' => 0,
                'author_name' => 'Detail Admin',
                'is_published' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => $now,
            ],
            [
                'id' => $this->newerDistractorId,
                'title' => 'Newer first-page distractor',
                'content' => '<p>Distractor</p>',
                'image' => '',
                'author_id' => 0,
                'author_name' => 'Detail Admin',
                'is_published' => 1,
                'created_at' => $now + 5000,
                'updated_at' => $now + 5000,
                'deleted_at' => null,
            ],
            ]);

            $this->createLegacyFrontUserFixture($this->userId, 2, 'Legacy News Detail User');
        } catch (\Throwable $exception) {
            $this->cleanupFixtureRows();
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixtureRows();
        } finally {
            parent::tearDown();
        }
    }

    private function cleanupFixtureRows(): void
    {
        $newsIds = [
            $this->publishedId,
            $this->unpublishedId,
            $this->deletedId,
            $this->newerDistractorId,
        ];
        DB::table('news_langs')->whereIn('news_id', $newsIds)->delete();
        DB::table('news')->whereIn('id', $newsIds)->delete();
        $this->cleanupLegacyFrontUserFixtures();
    }

    // 验证遗留详情路由仅接受已发布且未删除的数字新闻 ID 并净化内容。
    public function test_legacy_detail_accepts_only_published_numeric_news_id(): void
    {
        $session = ['suser' => ['user_id' => $this->userId]];
        $this->withSession($session)->get('/user/news/news_detail/' . $this->publishedId)
            ->assertOk()
            ->assertSeeText('Direct detail published news')
            ->assertSee('<p>Direct detail published content</p>', false)
            ->assertDontSee('<script', false)
            ->assertDontSee('onerror=', false)
            ->assertDontSee('javascript:', false);
        $this->withSession($session)->get('/user/news/news_detail/' . $this->unpublishedId)->assertNotFound();
        $this->withSession($session)->get('/user/news/news_detail/' . $this->deletedId)->assertNotFound();
        $this->withSession($session)->get('/user/news/news_detail/' . ($this->unpublishedId + 100))->assertNotFound();

        foreach (['abc', '1.2', $this->publishedId . 'abc'] as $invalid) {
            $this->withSession($session)->get('/user/news/news_detail/' . $invalid)->assertNotFound();
        }
    }

    // 验证现代详情页校验新闻存在并通过 data 属性暴露初始新闻 ID 且无内联脚本。
    public function test_modern_detail_page_checks_existence_and_exposes_initial_news_id_without_inline_script(): void
    {
        $response = $this->get('/front/news/detail/' . $this->publishedId . '?frame=1');
        $response->assertOk()
            ->assertSee('data-initial-news-id="' . $this->publishedId . '"', false)
            ->assertSee('data-default-filters=\'{"news_id":' . $this->publishedId . '}\'', false);

        $this->get('/front/news/detail/' . $this->unpublishedId)->assertNotFound();
        $this->get('/front/news/detail/' . $this->deletedId)->assertNotFound();
        $this->get('/front/news/detail/' . ($this->unpublishedId + 100))->assertNotFound();
        foreach (['abc', '1.2', $this->publishedId . 'abc'] as $invalid) {
            $this->get('/front/news/detail/' . $invalid)->assertNotFound();
        }
    }

    // 验证新闻 API 在分页前精确过滤已发布新闻 ID 并拒绝非法过滤值。
    public function test_news_api_filters_exact_published_news_id_before_pagination(): void
    {
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->getJson('/api/front/news?news_id=' . $this->publishedId . '&page=1&per_page=1');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.news.data')
            ->assertJsonPath('data.news.data.0.news_id', $this->publishedId);

        foreach (['abc', '1.2', '0', '-1'] as $invalid) {
            $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->getJson('/api/front/news?news_id=' . urlencode($invalid))
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    // 验证新闻模块仅在加载准确行后打开一次初始详情弹窗。
    public function test_news_module_opens_initial_detail_once_after_loading_exact_row(): void
    {
        $blade = file_get_contents(resource_path('front/layui/news/index.blade.php')) ?: '';
        $partial = file_get_contents(resource_path('front/layui/partials/module-page.blade.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';

        $this->assertStringContainsString("'news_id' => (int) \$legacyNewsId", $blade);
        $this->assertStringContainsString('data-initial-news-id=', $partial);
        $this->assertStringContainsString("var initialNewsId = parseInt(\$page.attr('data-initial-news-id')", $script);
        $this->assertSame(1, substr_count($script, 'openNewsDetailModal(initialNewsRow);'));
        $guardPosition = strpos($script, 'if (initialNewsId > 0 && !initialNewsOpened)');
        $rowPosition = strpos($script, 'if (initialNewsRow)', $guardPosition);
        $markPosition = strpos($script, 'initialNewsOpened = true;', $guardPosition);
        $openPosition = strpos($script, 'openNewsDetailModal(initialNewsRow);', $guardPosition);
        $this->assertNotFalse($guardPosition);
        $this->assertNotFalse($rowPosition);
        $this->assertNotFalse($markPosition);
        $this->assertNotFalse($openPosition);
        $this->assertLessThan($markPosition, $rowPosition);
        $this->assertLessThan($openPosition, $markPosition);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>\s*[^<\s]/i', $blade);
    }
}
