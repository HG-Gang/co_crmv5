<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:55
 */

/**
 * FrontBigNumberOwnerBoundaryClosureModuleTest
 *
 * 文件功能：
 * - 验证大代理归属边界闭环：旧改密字段与会话/数据库 token 回退、短或复用新密码拒绝、禁用与软删大代理拒绝、登出失效 token 清会话、范围外代理/客户伪造请求被拒、缺配置根失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\BigAgent;
use App\Services\JwtService;
use App\Support\FrontLegacyData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class FrontBigNumberOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_agent_edit_password_accepts_big_agents_session_and_old_fields(): void
    {
        $bigAgentId = 4124210;

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'password' => Hash::make('big-agent-old-password'),
            'jwt_token_id' => 'legacy-big-agent-token',
        ]);

        $response = $this->withSession([
            'bigAgents' => ['id' => $bigAgentId],
            'unrelated' => 'must-be-flushed',
        ])->postJson('/user/agents/editpsw_save', [
            'olduserpsw' => 'big-agent-old-password',
            'newuserpsw' => 'big-agent-new-password',
            'confirmuserpsw' => 'big-agent-new-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertJsonPath('data', [])
            ->assertJsonPath('code', 0)
            ->assertJsonPath('loginStatus', 200)
            ->assertSessionMissing('bigAgents')
            ->assertSessionMissing('unrelated');
        $password = (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password');
        $this->assertTrue(Hash::check('big-agent-new-password', $password));
        $this->assertSame('', (string) DB::table('big_agents')->where('id', $bigAgentId)->value('jwt_token_id'));
    }

    public function test_legacy_change_password_route_accepts_old_fields_and_returns_historical_contract(): void
    {
        $bigAgentId = 4124211;

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'password' => Hash::make('change-route-old-password'),
            'jwt_token_id' => 'change-route-token',
        ]);

        $response = $this->withSession([
            'bigAgents' => ['id' => $bigAgentId],
        ])->postJson('/user/agents/changePassword', [
            'olduserpsw' => 'change-route-old-password',
            'newuserpsw' => 'change-route-new-password',
            'confirmuserpsw' => 'change-route-new-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertJsonPath('data', [])
            ->assertJsonPath('code', 0)
            ->assertJsonPath('loginStatus', 200)
            ->assertSessionMissing('bigAgents');
        $this->assertTrue(Hash::check(
            'change-route-new-password',
            (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password')
        ));
        $this->assertSame('', (string) DB::table('big_agents')->where('id', $bigAgentId)->value('jwt_token_id'));
    }

    public function test_legacy_change_password_falls_back_to_database_token_when_session_token_is_empty(): void
    {
        $bigAgentId = 4124219;
        $databaseToken = 'database-token-after-legacy-login';

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'password' => Hash::make('database-token-old-password'),
            'jwt_token_id' => $databaseToken,
        ]);

        $jwtService = Mockery::mock(JwtService::class);
        $jwtService->shouldReceive('invalidateToken')->once()->with($databaseToken)->andReturnTrue();
        $this->app->instance(JwtService::class, $jwtService);

        $response = $this->withSession([
            'bigAgents' => ['id' => $bigAgentId, 'jwt_token_id' => ''],
        ])->postJson('/user/agents/changePassword', [
            'olduserpsw' => 'database-token-old-password',
            'newuserpsw' => 'database-token-new-password',
            'confirmuserpsw' => 'database-token-new-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertJsonPath('code', 0)
            ->assertSessionMissing('bigAgents');
    }

    public function test_legacy_change_password_route_keeps_modern_alias_without_confirmation(): void
    {
        $bigAgentId = 4124212;

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'password' => Hash::make('modern-old-password'),
        ]);

        $response = $this->withSession([
            'bigAgents' => ['id' => $bigAgentId],
        ])->postJson('/user/agents/changePassword', [
            'old_password' => 'modern-old-password',
            'password' => 'modern-new-password',
        ]);

        $response->assertOk()->assertJsonPath('code', 0);
        $this->assertTrue(Hash::check(
            'modern-new-password',
            (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password')
        ));
    }

    /**
     * 旧页面在提交前会做弱密码和复用旧密码校验；后端也必须复核，
     * 否则绕过 JavaScript 就能写入不符合策略的密码。
     */
    public function test_legacy_change_password_rejects_short_or_reused_new_password_without_mutating(): void
    {
        $bigAgentId = 4124220;
        $oldPassword = 'server-side-old-password';

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'password' => Hash::make($oldPassword),
        ]);
        $before = (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password');

        foreach (['short', $oldPassword] as $newPassword) {
            $response = $this->withSession([
                'bigAgents' => ['id' => $bigAgentId],
            ])->postJson('/user/agents/changePassword', [
                'old_password' => $oldPassword,
                'password' => $newPassword,
            ]);

            $response->assertOk()
                ->assertJsonPath('msg', 'error')
                ->assertJsonPath('code', 1000)
                ->assertJsonPath('errorType', 'PARAM');
            $this->assertSame($before, (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password'));
            $this->assertSame($bigAgentId, (int) session('bigAgents.id'));
        }
    }

    public function test_legacy_change_password_route_rejects_disabled_and_soft_deleted_big_agents(): void
    {
        foreach ([
            ['id' => 4124213, 'deleted_at' => null],
            ['id' => 4124214, 'deleted_at' => time()],
        ] as $fixture) {
            $bigAgentId = $fixture['id'];
            $this->deleteFixtureRows([], $bigAgentId, []);
            $this->insertBigAgent($bigAgentId, 0);
            DB::table('big_agents')->where('id', $bigAgentId)->update([
                'password' => Hash::make('inactive-old-password'),
                'is_enabled' => 0,
                'deleted_at' => $fixture['deleted_at'],
                'jwt_token_id' => 'inactive-token',
            ]);

            $response = $this->withSession([
                'bigAgents' => ['id' => $bigAgentId],
            ])->postJson('/user/agents/changePassword', [
                'olduserpsw' => 'inactive-old-password',
                'newuserpsw' => 'inactive-new-password',
                'confirmuserpsw' => 'inactive-new-password',
            ]);

            $response->assertOk()
                ->assertJsonPath('msg', 'error')
                ->assertJsonPath('data', [])
                ->assertJsonPath('code', 1010);
            $this->assertTrue(Hash::check(
                'inactive-old-password',
                (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password')
            ));
            $this->assertSame('inactive-token', DB::table('big_agents')->where('id', $bigAgentId)->value('jwt_token_id'));
        }
    }

    public function test_legacy_change_password_route_rejects_missing_or_wrong_old_password_without_mutating(): void
    {
        $bigAgentId = 4124215;

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'password' => Hash::make('old-password-boundary'),
        ]);
        $before = (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password');

        foreach ([
            ['olduserpsw' => '', 'newuserpsw' => 'new-password-boundary', 'confirmuserpsw' => 'new-password-boundary'],
            ['olduserpsw' => 'wrong-password', 'newuserpsw' => 'new-password-boundary', 'confirmuserpsw' => 'new-password-boundary'],
        ] as $payload) {
            $response = $this->withSession([
                'bigAgents' => ['id' => $bigAgentId],
            ])->postJson('/user/agents/changePassword', $payload);

            $response->assertOk()
                ->assertJsonPath('msg', 'error')
                ->assertJsonPath('data', [])
                ->assertJsonPath('code', 1011);
            $this->assertSame($before, (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password'));
        }
    }

    public function test_legacy_change_password_route_returns_system_error_when_save_is_cancelled(): void
    {
        $bigAgentId = 4124216;

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'password' => Hash::make('save-failure-old-password'),
            'jwt_token_id' => 'save-failure-token',
        ]);
        $dispatcher = BigAgent::getEventDispatcher();
        $event = 'eloquent.updating: ' . BigAgent::class;
        BigAgent::updating(function (BigAgent $agent) use ($bigAgentId) {
            return (int) $agent->id !== $bigAgentId;
        });

        try {
            $response = $this->withSession([
                'bigAgents' => ['id' => $bigAgentId],
            ])->postJson('/user/agents/changePassword', [
                'olduserpsw' => 'save-failure-old-password',
                'newuserpsw' => 'save-failure-new-password',
                'confirmuserpsw' => 'save-failure-new-password',
            ]);
        } finally {
            $dispatcher->forget($event);
        }

        $response->assertOk()
            ->assertJsonPath('msg', 'error')
            ->assertJsonPath('data', [])
            ->assertJsonPath('code', 1000)
            ->assertSessionHas('bigAgents.id', $bigAgentId);
        $this->assertTrue(Hash::check(
            'save-failure-old-password',
            (string) DB::table('big_agents')->where('id', $bigAgentId)->value('password')
        ));
        $this->assertSame('save-failure-token', DB::table('big_agents')->where('id', $bigAgentId)->value('jwt_token_id'));
    }

    public function test_big_agent_password_page_uses_legacy_endpoint_and_dedicated_form(): void
    {
        $bigAgentId = 4124217;

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);

        $response = $this->withSession([
            'bigAgents' => ['id' => $bigAgentId],
        ])->get('/user/agents/editpsw?frame=1');

        $response->assertOk()
            ->assertSee('data-layui-page="profile/change-password"', false)
            ->assertSee('data-legacy-big-agent="1"', false)
            ->assertSee('data-password-endpoint="' . url('/user/agents/changePassword') . '"', false)
            ->assertSee('data-login-url="' . route('agentsLogin') . '"', false)
            ->assertSee('lay-filter="passwordForm"', false)
            ->assertDontSee('lay-filter="profileForm"', false)
            ->assertDontSee('/api/front/profile/password', false);

        $ordinary = $this->get('/front/profile/change-password?frame=1');
        $ordinary->assertOk()
            ->assertSee('data-legacy-big-agent="0"', false)
            ->assertSee('data-password-endpoint="/api/front/profile/password"', false);
    }

    public function test_big_agent_logout_invalidates_stored_token_clears_field_and_flushes_session(): void
    {
        $bigAgentId = 4124218;
        $logoutToken = 'legacy-big-agent-logout-token';

        $this->deleteFixtureRows([], $bigAgentId, []);
        $this->insertBigAgent($bigAgentId, 0);
        DB::table('big_agents')->where('id', $bigAgentId)->update([
            'jwt_token_id' => $logoutToken,
        ]);

        $jwtService = Mockery::mock(JwtService::class);
        $jwtService->shouldReceive('invalidateToken')->once()->with($logoutToken)->andReturnTrue();
        $this->app->instance(JwtService::class, $jwtService);

        $response = $this->withSession([
            'bigAgents' => ['id' => $bigAgentId, 'jwt_token_id' => $logoutToken],
            'unrelated' => 'must-be-flushed',
        ])->get('/user/agents/loginOut');

        $response->assertRedirect('/agents/login')
            ->assertSessionMissing('bigAgents')
            ->assertSessionMissing('unrelated');
        $this->assertSame('', (string) DB::table('big_agents')->where('id', $bigAgentId)->value('jwt_token_id'));
    }

    public function test_big_agent_password_layui_script_keeps_ordinary_and_legacy_submit_contracts_separate(): void
    {
        $source = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $viewSource = file_get_contents(resource_path('front/layui/profile/change-password.blade.php')) ?: '';

        $this->assertStringContainsString('data-password-endpoint', $source);
        $this->assertStringContainsString('data-legacy-big-agent', $source);
        $this->assertStringContainsString('olduserpsw', $source);
        $this->assertStringContainsString('newuserpsw', $source);
        $this->assertStringContainsString('confirmuserpsw', $source);
        $this->assertStringContainsString("Number(res.code) === 0", $source);
        foreach ([1000, 1010, 1011] as $code) {
            $this->assertStringContainsString((string) $code, $source);
        }
        $this->assertStringContainsString("'/api/front/profile/password'", $source);
        $this->assertStringContainsString("'/user/agents/changePassword'", $viewSource);
    }

    public function test_big_agent_proxy_lists_reject_spoofed_agent_outside_configured_scope(): void
    {
        $bigAgentId = 4124201;
        $visibleAgentId = 412420101;
        $otherAgentId = 412420102;

        $this->deleteFixtureRows([$visibleAgentId, $otherAgentId], $bigAgentId, []);
        $this->insertUserInfo($visibleAgentId, 'big-owner-visible-agent', 1, 0);
        $this->insertUserInfo($otherAgentId, 'big-owner-other-agent', 1, 0);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);

        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/proxy/proxySearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertStringContainsString((string) $visibleAgentId, $visibleResponse->getContent());
        $this->assertStringNotContainsString((string) $otherAgentId, $visibleResponse->getContent());
        $this->assertStringNotContainsString('big-owner-other-agent', $visibleResponse->getContent());

        foreach (['/user/agents/proxy/proxySearch', '/user/agents/proxy/proxySearchBySub'] as $uri) {
            $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->withSession(['bigAgents' => ['id' => $bigAgentId]])
                ->postJson($uri, [
                    'userId' => $otherAgentId,
                    'user_id' => $otherAgentId,
                    'limit' => 20,
                ]);

            $spoofedResponse->assertOk()
                ->assertJsonPath('rows', [])
                ->assertJsonPath('total', 0);
            $this->assertStringNotContainsString((string) $otherAgentId, $spoofedResponse->getContent());
            $this->assertStringNotContainsString('big-owner-other-agent', $spoofedResponse->getContent());
        }
    }

    public function test_big_agent_closed_order_search_rejects_spoofed_customer_outside_configured_scope(): void
    {
        $bigAgentId = 4124202;
        $visibleAgentId = 412420201;
        $visibleCustomerId = 412420202;
        $otherAgentId = 412420203;
        $otherCustomerId = 412420204;
        $visibleTicket = 82420201;
        $otherTicket = 82420202;

        $this->deleteFixtureRows(
            [$visibleAgentId, $visibleCustomerId, $otherAgentId, $otherCustomerId],
            $bigAgentId,
            [$visibleTicket, $otherTicket]
        );
        $this->insertUserInfo($visibleAgentId, 'big-closed-visible-agent', 1, 0);
        $this->insertUserInfo($visibleCustomerId, 'big-closed-visible-customer', 2, $visibleAgentId);
        $this->insertUserInfo($otherAgentId, 'big-closed-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'big-closed-other-customer', 2, $otherAgentId);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);
        $this->insertTrade($visibleCustomerId, $visibleTicket, false, 'visible closed big owner order');
        $this->insertTrade($otherCustomerId, $otherTicket, false, 'other closed big owner order');

        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/close/closeOrderSearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertStringContainsString((string) $visibleTicket, $visibleResponse->getContent());
        $this->assertStringNotContainsString((string) $otherTicket, $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/close/closeOrderSearch', [
                'userId' => $otherCustomerId,
                'user_id' => $otherCustomerId,
                'limit' => 20,
            ]);

        $spoofedResponse->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $otherTicket, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('other closed big owner order', $spoofedResponse->getContent());
    }

    public function test_big_agent_open_order_search_rejects_spoofed_customer_outside_configured_scope(): void
    {
        $bigAgentId = 4124203;
        $visibleAgentId = 412420301;
        $visibleCustomerId = 412420302;
        $otherAgentId = 412420303;
        $otherCustomerId = 412420304;
        $visibleTicket = 82420301;
        $otherTicket = 82420302;

        $this->deleteFixtureRows(
            [$visibleAgentId, $visibleCustomerId, $otherAgentId, $otherCustomerId],
            $bigAgentId,
            [$visibleTicket, $otherTicket]
        );
        $this->insertUserInfo($visibleAgentId, 'big-open-visible-agent', 1, 0);
        $this->insertUserInfo($visibleCustomerId, 'big-open-visible-customer', 2, $visibleAgentId);
        $this->insertUserInfo($otherAgentId, 'big-open-other-agent', 1, 0);
        $this->insertUserInfo($otherCustomerId, 'big-open-other-customer', 2, $otherAgentId);
        $this->insertBigAgent($bigAgentId, $visibleAgentId);
        $this->insertTrade($visibleCustomerId, $visibleTicket, true, 'visible open big owner order');
        $this->insertTrade($otherCustomerId, $otherTicket, true, 'other open big owner order');

        $visibleResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/open/openOrderSearch', ['limit' => 20]);

        $visibleResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertStringContainsString((string) $visibleTicket, $visibleResponse->getContent());
        $this->assertStringNotContainsString((string) $otherTicket, $visibleResponse->getContent());

        $spoofedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession(['bigAgents' => ['id' => $bigAgentId]])
            ->postJson('/user/agents/open/openOrderSearch', [
                'userId' => $otherCustomerId,
                'user_id' => $otherCustomerId,
                'limit' => 20,
            ]);

        $spoofedResponse->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
        $this->assertStringNotContainsString((string) $otherTicket, $spoofedResponse->getContent());
        $this->assertStringNotContainsString('other open big owner order', $spoofedResponse->getContent());
    }

    public function test_missing_configured_agent_root_fails_closed_for_orders_and_proxy_child_search(): void
    {
        $bigAgentId = 4124260;
        $missingRootId = 412426001;
        $childAgentId = 412426002;
        $customerId = 412426003;
        $ticket = 84242601;

        $this->deleteFixtureRows([$missingRootId, $childAgentId, $customerId], $bigAgentId, [$ticket]);
        $this->insertUserInfo($childAgentId, 'big-missing-root-child-agent', 1, $missingRootId);
        $this->insertUserInfo($customerId, 'big-missing-root-customer', 2, $childAgentId);
        $this->insertBigAgent($bigAgentId, $missingRootId);
        $this->insertTrade($customerId, $ticket, true, 'missing root leaked open order');

        $session = ['bigAgents' => ['id' => $bigAgentId]];
        $orderResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession($session)
            ->postJson('/user/agents/open/openOrderSearch', ['limit' => 20]);
        $childResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession($session)
            ->postJson('/user/agents/proxy/proxySearchBySub', [
                'userPId' => $missingRootId,
                'searchtype' => 'subSearch',
                'limit' => 20,
            ]);

        $orderResponse->assertOk();
        $childResponse->assertOk();
        $this->assertSame(
            ['order' => 0, 'proxy_child' => 0],
            [
                'order' => (int) $orderResponse->json('total'),
                'proxy_child' => (int) $childResponse->json('total'),
            ]
        );
        $orderResponse->assertJsonPath('rows', []);
        $childResponse->assertJsonPath('rows', []);
        $this->assertStringNotContainsString((string) $ticket, $orderResponse->getContent());
        $this->assertStringNotContainsString((string) $childAgentId, $childResponse->getContent());
        $this->assertNull(FrontLegacyData::agentScopeIdsOrNull($missingRootId));
    }

    public function test_soft_deleted_configured_agent_root_fails_closed_for_orders_and_position_child_search(): void
    {
        $bigAgentId = 4124261;
        $rootAgentId = 412426101;
        $childAgentId = 412426102;
        $customerId = 412426103;
        $ticket = 84242611;

        $this->deleteFixtureRows([$rootAgentId, $childAgentId, $customerId], $bigAgentId, [$ticket]);
        $this->insertUserInfo($rootAgentId, 'big-soft-deleted-root-agent', 1, 0);
        $this->insertUserInfo($childAgentId, 'big-soft-deleted-root-child-agent', 1, $rootAgentId);
        $this->insertUserInfo($customerId, 'big-soft-deleted-root-customer', 2, $childAgentId);
        DB::table('user_infos')->where('user_id', $rootAgentId)->update(['deleted_at' => time()]);
        $this->insertBigAgent($bigAgentId, $rootAgentId);
        $this->insertTrade($customerId, $ticket, false, 'soft-deleted root leaked closed order');

        $session = ['bigAgents' => ['id' => $bigAgentId]];
        $orderResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession($session)
            ->postJson('/user/agents/close/closeOrderSearch', ['limit' => 20]);
        $childResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession($session)
            ->postJson('/user/agents/position/subAgentsListSearch', [
                'userPId' => $rootAgentId,
                'searchtype' => 'subSearch',
                'limit' => 20,
            ]);

        $orderResponse->assertOk();
        $childResponse->assertOk();
        $this->assertSame(
            ['order' => 0, 'position_child' => 0],
            [
                'order' => (int) $orderResponse->json('total'),
                'position_child' => (int) $childResponse->json('total'),
            ]
        );
        $orderResponse->assertJsonPath('rows', []);
        $childResponse->assertJsonPath('rows', []);
        $this->assertStringNotContainsString((string) $ticket, $orderResponse->getContent());
        $this->assertStringNotContainsString((string) $childAgentId, $childResponse->getContent());
        $this->assertNull(FrontLegacyData::agentScopeIdsOrNull($rootAgentId));
    }

    public function test_big_agent_legacy_open_and_closed_orders_include_order_comment_alias(): void
    {
        $bigAgentId = 4124250;
        $agentId = 41242501;
        $customerId = 41242502;
        $closedTicket = 84242501;
        $openTicket = 84242502;
        $closedComment = 'legacy closed order comment';
        $openComment = 'legacy open order comment';

        $this->deleteFixtureRows([$agentId, $customerId], $bigAgentId, [$closedTicket, $openTicket]);
        $this->insertUserInfo($agentId, 'big-order-comment-agent', 1, 0);
        $this->insertUserInfo($customerId, 'big-order-comment-customer', 2, $agentId);
        $this->insertBigAgent($bigAgentId, $agentId);
        $this->insertTrade($customerId, $closedTicket, false, $closedComment);
        $this->insertTrade($customerId, $openTicket, true, $openComment);

        $session = ['bigAgents' => ['id' => $bigAgentId]];
        $closedResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession($session)
            ->postJson('/user/agents/close/closeOrderSearch', ['limit' => 20]);
        $openResponse = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->withSession($session)
            ->postJson('/user/agents/open/openOrderSearch', ['limit' => 20]);

        $closedResponse->assertOk();
        $openResponse->assertOk();
        $this->assertSame(
            ['closed' => $closedComment, 'open' => $openComment],
            [
                'closed' => $closedResponse->json('rows.0.orderComment'),
                'open' => $openResponse->json('rows.0.orderComment'),
            ]
        );
        $closedResponse->assertJsonPath('rows.0.comment', $closedComment);
        $openResponse->assertJsonPath('rows.0.comment', $openComment);
    }

    public function test_final_checklist_records_big_number_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 242.', $checklist);
        $this->assertStringContainsString('BigNumberController::bigNumberListSearch', $checklist);
        $this->assertStringContainsString('BigNumberController::bigNumberListSearchBySubAgents', $checklist);
        $this->assertStringContainsString('BigNumberController::bigCloseOrderSearch', $checklist);
        $this->assertStringContainsString('BigNumberController::bigOpenOrderSearch', $checklist);
        $this->assertStringContainsString('user/agents/proxy/proxySearch', $checklist);
        $this->assertStringContainsString('user/agents/close/closeOrderSearch', $checklist);
        $this->assertStringContainsString('user/agents/open/openOrderSearch', $checklist);
        $this->assertStringContainsString('FrontBigNumberOwnerBoundaryClosureModuleTest', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType, int $parentId): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-big-owner-boundary-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1782420' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => $accountType === 1 ? 0.1 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertBigAgent(int $bigAgentId, int $visibleAgentId): void
    {
        $now = time();

        DB::table('big_agents')->where('id', $bigAgentId)->delete();
        DB::table('big_agents')->insert([
            'id' => $bigAgentId,
            'email' => 'front-big-owner-boundary-' . $bigAgentId . '@example.test',
            'username' => 'front-big-owner-boundary-' . $bigAgentId,
            'password' => Hash::make('password'),
            'sub_agent_ids' => (string) $visibleAgentId,
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertTrade(int $userId, int $ticket, bool $open, string $comment): void
    {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'XAUUSD',
            'digits' => 2,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-07-09 10:00:00',
            'open_price' => 2300.10,
            'stop_loss' => 0,
            'take_profit' => 0,
            'close_time' => $open ? '1970-01-01 00:00:00' : '2026-07-09 12:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => $open ? 0 : 2301.20,
            'profit' => $open ? 0 : 10.50,
            'taxes' => 0,
            'comment' => $comment,
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => $open ? '2026-07-09 10:00:00' : '2026-07-09 12:00:00',
            'settlement_status' => $open ? 0 : 1,
            'settled_at' => $open ? null : '2026-07-09 12:05:00',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, int> $tickets
     */
    private function deleteFixtureRows(array $userIds, int $bigAgentId, array $tickets): void
    {
        DB::table('user_trades')
            ->whereIn('user_id', $userIds)
            ->orWhereIn('ticket', $tickets)
            ->delete();
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();
        DB::table('big_agents')->where('id', $bigAgentId)->delete();
        DB::table('agent_descendants')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('agent_id', $userIds)
                    ->orWhereIn('descendant_id', $userIds);
            })
            ->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
