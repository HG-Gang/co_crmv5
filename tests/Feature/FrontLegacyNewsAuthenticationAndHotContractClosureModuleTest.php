<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * 前台遗留新闻鉴权与热点合约闭环测试。
 *
 * 文件功能：
 * - 验证遗留受保护新闻路由（/user/front/message、/user/news_list_browse、
 *   /user/news/news_detail/{id}）对匿名请求重定向到登录页。
 * - 验证公共热点路由（/user/main/hot/newsV2、/user/main/hasShowGiftTips、
 *   /user/newsListSearch 等）对匿名请求返回统一未认证 JSON 合约。
 * - 验证 user 守卫与 legacy session 均能认证遗留路由，礼品提示缓存使用会话 userId。
 * - 验证登录后热点新闻 newsV2 恢复 code 200 并返回多语言标题与 lang_name。
 * - 验证公共注册页热点新闻 /user/register/hotnews 返回独立的原始数组合约。
 *
 * 适用场景：
 * - 前台遗留新闻与热点合约回归测试，防止鉴权缺失或响应合约漂移。
 *
 * 入参例子：
 * - POST /user/main/hot/newsV2：page、lang_id（1=zh-CN、2=en）。
 * - GET /user/register/hotnews?page=1&limit=4&lang_id=2。
 *
 * 返回值：
 * - 受保护路由匿名访问重定向 /user/login；公共路由匿名访问 code 为 AUTH_FAILED。
 * - 登录后 newsV2 code 为 200，data 含 aid、title、lang_name。
 *
 * 异常或失败场景：
 * - 匿名访问受保护路由被重定向；未认证访问公共热点路由返回 AUTH_FAILED 合约。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\UserLogin;
use Tests\TestCase;
use Tests\Support\CreatesLegacyFrontUserFixture;

class FrontLegacyNewsAuthenticationAndHotContractClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacyFrontUserFixture;

    /**
     * 夹具新闻 ID。验证新闻接口的登录边界与热门新闻契约。
     * @var int
     */
    private $newsId;

    /**
     * 夹具登录用户 ID。用于带鉴权访问新闻接口。
     * @var int
     */
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->newsId = random_int(240000000, 249999999);
        $this->userId = random_int(250000000, 259999999);
        $now = time() + 6000;

        try {
            DB::table('news')->insert([
            'id' => $this->newsId,
            'title' => 'Hot contract main title',
            'content' => '<p>Hot contract content</p>',
            'image' => '',
            'author_id' => 0,
            'author_name' => 'Hot Admin',
            'is_published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
            ]);
            DB::table('news_langs')->insert([
            [
                'news_id' => $this->newsId,
                'lang_code' => 'zh-CN',
                'title' => '热点中文标题',
                'content' => '<p>热点中文正文</p>',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'news_id' => $this->newsId,
                'lang_code' => 'en',
                'title' => 'Hot English Title',
                'content' => '<p>Hot English Content</p>',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            ]);
            $this->createLegacyFrontUserFixture($this->userId, 2, 'Legacy News Contract User');
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
        Cache::forget('gift_tips_shown_' . $this->userId);
        DB::table('news_langs')->where('news_id', $this->newsId)->delete();
        DB::table('news')->where('id', $this->newsId)->delete();
        $this->cleanupLegacyFrontUserFixtures();
    }

    // 验证遗留受保护新闻路由拒绝匿名请求而公共热点路由保持开放。
    public function test_legacy_protected_news_routes_reject_anonymous_requests_but_public_hot_routes_remain_open(): void
    {
        foreach (['/user/front/message', '/user/news_list_browse', '/user/news/news_detail/' . $this->newsId] as $path) {
            $this->get($path)->assertRedirect('/user/login');
        }

        foreach (['/user/main/hot/newsV2', '/user/main/hasShowGiftTips', '/user/newsListSearch'] as $path) {
            $response = $this->postJson($path)->assertOk();
            $response->assertJsonPath('code', ResponseCode::AUTH_FAILED)
                ->assertJsonPath('message', __('response.auth_failed'))
                ->assertJsonPath('msg', __('response.auth_failed'))
                ->assertJsonPath('rows', [])
                ->assertJsonPath('total', 0)
                ->assertJsonPath('footer', [])
                ->assertJsonPath('redirect', true)
                ->assertJsonPath('redirectUrl', url('/user/login'));
        }

        $this->postJson('/user/main/hot/news', ['page' => 1])
            ->assertOk()
            ->assertJsonPath('code', 0);
        $this->getJson('/user/register/hotnews?page=1&limit=4')->assertOk();
    }

    // 验证 user 守卫也能认证遗留新闻路由。
    public function test_user_guard_also_authenticates_legacy_routes(): void
    {
        $login = UserLogin::where('user_id', $this->userId)->firstOrFail();
        Cache::forget('gift_tips_shown_' . $this->userId);

        $this->actingAs($login, 'user')->get('/user/front/message')->assertOk();
        $this->actingAs($login, 'user')->postJson('/user/main/hasShowGiftTips')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertTrue(Cache::has('gift_tips_shown_' . $this->userId));
    }

    // 验证 legacy session 可认证受保护路由且礼品提示使用会话 userId。
    public function test_legacy_session_authenticates_protected_routes_and_gift_tip_uses_session_user_id(): void
    {
        Cache::forget('gift_tips_shown_' . $this->userId);
        $session = ['suser' => ['user_id' => $this->userId]];

        $this->withSession($session)->get('/user/front/message')->assertOk();
        $this->withSession($session)->get('/user/news_list_browse?frame=1')->assertOk();
        $this->withSession($session)->get('/user/news/news_detail/' . $this->newsId)->assertOk();
        $this->withSession($session)->postJson('/user/newsListSearch', ['page' => 1, 'rows' => 1])
            ->assertOk()
            ->assertJsonStructure(['rows', 'total']);
        $this->withSession($session)->postJson('/user/main/hasShowGiftTips')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertTrue(Cache::has('gift_tips_shown_' . $this->userId));
    }

    // 验证登录后热点新闻 newsV2 恢复 code 200 及多语言标题与 lang_name。
    public function test_authenticated_hot_news_v2_restores_code_200_lang_id_and_legacy_lang_name(): void
    {
        $session = ['suser' => ['user_id' => $this->userId]];

        $zh = $this->withSession($session)->postJson('/user/main/hot/newsV2', ['page' => 1, 'lang_id' => 1]);
        $zh->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.0.aid', $this->newsId)
            ->assertJsonPath('data.0.title', '热点中文标题')
            ->assertJsonPath('data.0.lang_name', 'zh-cn');

        $en = $this->withSession($session)->postJson('/user/main/hot/newsV2', ['page' => 1, 'lang_id' => 2]);
        $en->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.0.title', 'Hot English Title')
            ->assertJsonPath('data.0.lang_name', 'en');
    }

    // 验证公共注册页热点新闻使用独立的原始数组合约。
    public function test_public_register_hot_news_uses_independent_raw_array_contract(): void
    {
        $response = $this->getJson('/user/register/hotnews?page=1&limit=4&lang_id=2');
        $response->assertOk();
        $payload = $response->json();

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('code', $payload);
        $row = collect($payload)->firstWhere('aid', $this->newsId);
        $this->assertSame('Hot English Title', $row['title'] ?? null);
        $this->assertSame($this->newsId, (int) ($row['aid'] ?? 0));
    }
}
