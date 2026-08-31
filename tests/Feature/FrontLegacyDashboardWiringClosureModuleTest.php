<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 12:32
 */

/**
 * FrontLegacyDashboardWiringClosureModuleTest
 *
 * 文件功能：
 * - 验证旧前台首页三路由与现代 API 接线：别名路由渲染同一外壳、内页暴露 layui 注册接线、页面要求登录、API 契约与无 token 拒绝、样式切换路由注入。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\UserLogin;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Support\CreatesLegacyFrontUserFixture;

/**
 * 闭环测试：旧前台首页（Dashboard）三路由与前端接线确认。
 *
 * 覆盖矩阵项：
 * - GET user/index            -> LegacyPageController@dashboard（legacy_user_index_page）
 * - GET user/index/index      -> 同上（legacy_user_index_index_page）
 * - GET user/main/home        -> 同上（legacy_user_main_home_page）
 * - GET /api/front/dashboard  -> DashboardController@dashboardData（front_api_dashboard）
 *
 * 接线链路：/user/index 渲染框架外壳（含 iframe -> ?frame=1），内页
 * front_layui::dashboard.index_v2 通过 data-layui-page="dashboard/index"
 * 触发 pages.js 的 registry['dashboard/index']，其内调用 guard=front 的
 * /api/front/dashboard 拉取数字卡片、图表、分享链接与公告数据。
 * 本测试固化外壳/内页接线锚点与 API 契约，确认三路由共享同一工作台页面。
 */
class FrontLegacyDashboardWiringClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacyFrontUserFixture;

    /**
     * 夹具登录用户 ID。验证旧版仪表盘的路由接线与页面渲染。
     * @var int
     */
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = random_int(370000000, 379999999);
        $this->createLegacyFrontUserFixture($this->userId, 2, 'Dashboard Wiring Fixture');
    }

    private function dashboardUris(): array
    {
        return [
            '/user/index',
            '/user/index/index',
            '/user/main/home',
        ];
    }

    public function test_dashboard_route_aliases_render_same_frame_shell(): void
    {
        foreach ($this->dashboardUris() as $uri) {
            $response = $this->withSession(['suser' => ['user_id' => $this->userId]])
                ->get($uri);

            $response->assertOk()
                ->assertSee('id="contentFrame"', false)
                ->assertSee($uri . '?frame=1', false);
        }
    }

    public function test_dashboard_inner_page_exposes_layui_registry_wiring(): void
    {
        foreach ($this->dashboardUris() as $uri) {
            $response = $this->withSession(['suser' => ['user_id' => $this->userId]])
                ->get($uri . '?frame=1');

            $response->assertOk()
                ->assertSee('data-layui-page="dashboard/index"', false)
                ->assertSee('id="crm-dashboard-routes"', false)
                ->assertSee('id="shareUrlList"', false)
                ->assertSee('id="dashboardNews"', false)
                ->assertSee('id="fundsChart"', false);
        }
    }

    public function test_dashboard_page_requires_login(): void
    {
        $this->get('/user/index')
            ->assertRedirect('/user/login');
    }

    public function test_dashboard_api_contract_serves_workspace_payload(): void
    {
        $login = UserLogin::query()->where('user_id', $this->userId)->firstOrFail();
        $token = app(JwtService::class)->generateToken(['sub' => $login->getAuthIdentifier(), 'guard' => 'user']);

        $response = $this->withToken($token)->getJson('/api/front/dashboard');

        $response->assertOk()
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.user.user_id', (string) $this->userId)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['user_id', 'user_name', 'account_type', 'email', 'title'],
                    'profile' => ['share_url', 'share_urls', 'commission_rate', 'total_funds', 'equity', 'effective_credit'],
                    'downloads' => ['pc' => ['label', 'url'], 'mobile' => ['label', 'url']],
                    'stats' => ['direct_agents', 'indirect_agents', 'total_commission', 'account_balance', 'open_orders_count'],
                    'news',
                    'period' => ['from', 'to'],
                ],
            ]);
    }

    public function test_dashboard_api_rejects_request_without_token(): void
    {
        $this->getJson('/api/front/dashboard')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::TOKEN_MISSING);
    }

    public function test_dashboard_inner_page_injects_crmui_route_for_style_switch(): void
    {
        $response = $this->withSession(['suser' => ['user_id' => $this->userId]])
            ->get('/user/index?frame=1');

        $html = $response->getContent();
        $jsonBlock = '';

        if (preg_match('/<script type="application\/json" id="crm-dashboard-routes">(.*?)<\/script>/s', $html, $matches)) {
            $jsonBlock = trim($matches[1]);
        }

        $this->assertNotSame('', $jsonBlock);
        $payload = json_decode($jsonBlock, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('crmuiDashboard', $payload);
        $this->assertStringContainsString('/front-crmui/dashboard', $payload['crmuiDashboard']);
    }
}
