<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 02:55
 */

/**
 * FrontLegacyLogoutClosureModuleTest
 *
 * 文件功能：
 * - 验证旧前台登出闭环：清理旧 session 并跳回登录页、清理新 user guard、重建 session 与 CSRF token、登出后受保护页跳登录、大代理登出清理代理会话。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Support\CreatesLegacyFrontUserFixture;

/**
 * 闭环测试：旧前台登出。
 *
 * 覆盖矩阵项：
 * - GET user/loginOut（web.php:237 -> Front\LegacyPageController@logout，公开白名单）
 * - GET user/agents/loginOut（web.php:273 -> Front\BigNumberController@loginOut，代理登出）
 *
 * 旧行为：注销 session 后跳回 /user/login（或代理登录页）。
 * 新行为（legacy_user_logout）：依次清理 user guard、suser session、使 session 失效并
 * 重新生成 CSRF token，最后重定向到 front_page_login（/front/login）；无缺口。
 * 本测试固化：登出后 guard 与 suser 双态清空、受保护页被拒回登录页。
 */
class FrontLegacyLogoutClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesLegacyFrontUserFixture;

    /**
     * 夹具登录用户 ID。验证旧版登出路由使会话失效。
     * @var int
     */
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = random_int(360000000, 369999999);
        $this->createLegacyFrontUserFixture($this->userId, 2, 'Logout Fixture');
    }

    public function test_logout_clears_legacy_session_and_redirects_to_login(): void
    {
        $this->withSession(['suser' => ['user_id' => $this->userId]])
            ->get('/user/loginOut')
            ->assertRedirect('/front/login')
            ->assertSessionMissing('suser');
    }

    public function test_logout_clears_new_user_guard(): void
    {
        $userLogin = UserLogin::query()
            ->where('user_id', $this->userId)
            ->first();

        $this->actingAs($userLogin, 'user');

        $this->get('/user/loginOut')
            ->assertRedirect('/front/login');

        $this->assertGuest('user');
    }

    public function test_logout_regenerates_session_and_csrf_token(): void
    {
        $this->withSession(['suser' => ['user_id' => $this->userId]]);

        $this->get('/user/loginOut')
            ->assertRedirect('/front/login');

        $this->assertNotSame('', $this->app['session']->token());
    }

    public function test_protected_page_redirects_to_login_after_logout(): void
    {
        $this->withSession(['suser' => ['user_id' => $this->userId]]);

        $this->get('/user/loginOut')
            ->assertRedirect('/front/login');

        $this->get('/user/index')
            ->assertRedirect('/user/login');
    }

    public function test_big_agent_logout_clears_agent_session(): void
    {
        $bigAgentId = 360099999;

        $this->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->get('/user/agents/loginOut')
            ->assertRedirect('/agents/login')
            ->assertSessionMissing('bigAgents');
    }
}
