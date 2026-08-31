<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:05
 */

/**
 * 旧版前台鉴权边界闭合测试。
 *
 * 文件功能：
 * - 验证匿名访问标准页面（/user/index、/user/center、/user/deposit）与
 *   大代理页面（/user/agents/index）分别重定向到 /user/login 与 /agents/login。
 * - 验证匿名 AJAX 请求返回旧重定向契约（code = AUTH_FAILED、rows/total/footer 为空、
 *   redirect = true、redirectUrl 指向对应旧登录页）。
 * - 验证有效/无效/错类会话：有效 suser 会话通过标准路由、无效 suser 会话被清除重定向、
 *   有效 bigAgents 会话通过代理路由，且两类会话互不通用。
 * - 验证每个 user/ 前缀业务路由都显式声明鉴权边界：PUBLIC_USER_URIS 白名单
 *   保持公开，其余路由必须挂载 legacy.front.auth 中间件。
 *
 * 适用场景：
 * - 回归旧前台路由鉴权改造（legacy.front.auth），防止新增或改动路由后
 *   出现未鉴权或误公开的安全回归。
 *
 * 入参例子：
 * - 匿名 GET /user/index、POST /user/agents/proxy/proxySearch、
 *   POST /user/flow/depositFlowSearch；withSession 注入
 *   ['suser' => ['user_id' => 990001]] 或 ['bigAgents' => ['id' => ...]]。
 *
 * 返回值：
 * - 测试无返回值；全部断言通过即表示鉴权边界闭环：
 *   匿名拦截、有效会话放行、无效会话清除、两类会话隔离。
 *
 * 异常或失败场景：
 * - 断言失败意味着存在未受 legacy.front.auth 保护、误设公开或会话串用的路由，
 *   属于安全回归，必须立即修复。
 */
namespace Tests\Feature;

use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Feature\Concerns\CreatesLegacySmokeUsers;

class FrontLegacyAuthenticationBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacySmokeUsers;

    /**
     * 无需登录即可访问的旧版用户端 URI 清单（登录、注册、验证码等）。
     * 边界断言：清单内公开可达、清单外必须重定向登录。
     * @var array<int, string>
     */
    private const PUBLIC_USER_URIS = [
        'user/login',
        'user/index/login',
        'user/signIn',
        'user/index/signIn',
        'user/captcha',
        'user/loginOut',
        'user/register/registerVerifyInfo',
        'user/register/registerSendCode',
        'user/register/registerinto',
        'user/register/captcha',
        'user/register/testemail',
        'user/register/testmodel',
        'user/register/rebateDeposit',
        'user/register/hotnews',
        'user/offweb/feedback',
        'user/register/{register_type?}/{user_id?}/{comm_type?}',
        'user/index/register/{register_type?}/{user_id?}/{comm_type?}',
        'user/relationShip',
        'user/relationShipHtml',
        'user/agents/relationShipHtml',
        'user/forget_password',
        'user/check_user_info',
        'user/forgetpswSendCode',
        'user/forgetPasswordInfoVerification',
        'user/change_password',
        'user/agents/signIn',
        // Login pages request these images before a big-agent session exists.
        'user/agents/captcha',
        'user/agents/login/captcha',
        'user/main/hot/news',
        'user/deposit_notfiy',
        'user/deposit_notfiy2',
        'user/deposit_tigerpay_notify',
        'user/deposit_wppay_notify',
        'user/deposit_wppay_return',
        'user/deposit_exlink_bbnotify',
        'user/deposit_exlink_bbreturn',
        'user/deposit_exlink_fbnotify',
        'user/deposit_exlink_fbreturn',
        'user/deposit_btb_notify',
        'user/deposit_btb_return',
        'user/deposit_passto_notify',
        'user/deposit_switch_notify',
        'user/deposit_notfiy_otc',
        'user/withdraw_notfiy_otc',
        'user/withdraw_verify_otc',
        'user/deposit_return',
        'user/deposit_return2',
        'user/proxy/direct_cust_detail/{puid}',
        'user/proxy/direct_cust_detail_list',
        'user/position/comm_summary',
        'user/position/comm_summaryv2',
        'user/cust/show_direct_cust_info/{role}/{uid}',
        'user/cust/loginHistorySearch/{uid}',
        'user/realtime/rebate_detail/{orderNo}/{role}',
    ];

    public function test_anonymous_standard_pages_redirect_to_the_legacy_user_login(): void
    {
        foreach (['/user/index', '/user/center', '/user/deposit'] as $uri) {
            $this->get($uri)->assertRedirect('/user/login');
        }
    }

    public function test_anonymous_big_agent_page_redirects_to_the_legacy_agent_login(): void
    {
        $this->get('/user/agents/index')->assertRedirect('/agents/login');
    }

    public function test_anonymous_big_agent_ajax_returns_the_legacy_redirect_contract(): void
    {
        $this->postJson('/user/agents/proxy/proxySearch')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED)
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0)
            ->assertJsonPath('footer', [])
            ->assertJsonPath('redirect', true)
            ->assertJsonPath('redirectUrl', url('/agents/login'));
    }

    public function test_anonymous_big_agent_captcha_routes_remain_available_for_the_login_form(): void
    {
        foreach (['/user/agents/captcha', '/user/agents/login/captcha'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertHeader('Content-Type', 'image/svg+xml');
        }
    }

    public function test_anonymous_standard_ajax_returns_the_legacy_redirect_contract(): void
    {
        $this->postJson('/user/flow/depositFlowSearch')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED)
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0)
            ->assertJsonPath('footer', [])
            ->assertJsonPath('redirect', true)
            ->assertJsonPath('redirectUrl', url('/user/login'));
    }

    public function test_legacy_user_session_passes_through_standard_routes(): void
    {
        // 该用例验证“有效会话可访问标准路由”，必须先创建真实可用用户。
        $this->ensureLegacySmokeUser(990001);

        $this->withSession(['suser' => ['user_id' => 990001]])
            ->get('/user/index')
            ->assertOk();
    }

    public function test_nonexistent_legacy_user_session_is_rejected_and_cleared(): void
    {
        $userId = 990099996;
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $this->withSession(['suser' => ['user_id' => $userId]])
            ->get('/user/index')
            ->assertRedirect('/user/login')
            ->assertSessionMissing('suser');
    }

    public function test_legacy_big_agent_session_passes_through_agent_routes(): void
    {
        $bigAgentId = 990099997;
        $now = time();

        DB::table('big_agents')->where('id', $bigAgentId)->delete();
        DB::table('big_agents')->insert([
            'id' => $bigAgentId,
            'email' => 'legacy-auth-boundary@example.test',
            'username' => 'legacy-auth-boundary',
            'password' => Hash::make('password'),
            'sub_agent_ids' => '',
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->get('/user/agents/index')
            ->assertOk();
    }

    public function test_standard_user_session_does_not_authenticate_big_agent_routes(): void
    {
        $this->withSession(['suser' => ['user_id' => 990001]])
            ->get('/user/agents/index')
            ->assertRedirect('/agents/login');
    }

    public function test_big_agent_session_does_not_authenticate_standard_routes(): void
    {
        $this->withSession(['bigAgents' => ['id' => 990001]])
            ->get('/user/index')
            ->assertRedirect('/user/login');
    }

    public function test_invalid_big_agent_session_reaches_legacy_password_actions_for_historical_not_found_response(): void
    {
        foreach ([
            ['/user/agents/changePassword', 'error'],
            ['/user/agents/editpsw_save', 'FAIL'],
        ] as [$uri, $message]) {
            $this->withSession(['bigAgents' => ['id' => 990099999]])
                ->postJson($uri, [
                    'olduserpsw' => 'old-password',
                    'newuserpsw' => 'new-password',
                    'confirmuserpsw' => 'new-password',
                ])
                ->assertOk()
                ->assertJsonPath('msg', $message)
                ->assertJsonPath('code', 1010);
        }
    }

    public function test_every_legacy_user_business_route_has_an_explicit_auth_boundary(): void
    {
        $seenPublicUris = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (strpos($uri, 'user/') !== 0) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            if (in_array($uri, self::PUBLIC_USER_URIS, true)) {
                $seenPublicUris[] = $uri;
                $this->assertNotContains('legacy.front.auth', $middleware, $uri . ' must remain public.');
                continue;
            }

            $this->assertContains('legacy.front.auth', $middleware, $uri . ' must require legacy front authentication.');
        }

        $this->assertSame([], array_values(array_diff(self::PUBLIC_USER_URIS, array_unique($seenPublicUris))));
    }
}
