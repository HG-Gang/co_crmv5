<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 16:44
 */

/**
 * FrontBigAgentLegacyShellClosureTest
 *
 * 文件功能：
 * - 验证大代理商旧页面外壳闭环：旧登录字段走 session 登录、现代登录保持 JWT 契约、失效/软删会话不可渲染旧页、旧分页/搜索/异步请求语义与 CSRF 外壳、密码页旧契约与范围外代理拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\BigAgent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\ExecutesJavascriptScenarios;
use Tests\TestCase;

/**
 * 旧大代理入口的端到端边界测试。
 *
 * 这些测试验证的是旧页面真正使用 bigAgents session 和旧 POST 契约，
 * 防止页面看似可渲染、脚本却悄悄切回普通用户 JWT API。
 */
class FrontBigAgentLegacyShellClosureTest extends TestCase
{
    use DatabaseTransactions;
    use ExecutesJavascriptScenarios;

    public function test_legacy_login_page_posts_legacy_fields_to_session_sign_in(): void
    {
        $response = $this->get('/agents/login');

        $response->assertOk()
            ->assertSee('data-legacy-big-agent="1"', false)
            ->assertSee('data-login-endpoint="' . url('/user/agents/signIn') . '"', false)
            ->assertSee('name="loginUid"', false)
            ->assertSee('name="loginPassword"', false)
            ->assertDontSee('/api/front/auth/big-number/login', false);

        $script = file_get_contents(public_path('js/apps/front/layui/pages.js')) ?: '';
        $this->assertStringContainsString("'/user/agents/signIn'", $script);
        $this->assertStringContainsString("data-legacy-big-agent", $script);
    }

    public function test_modern_big_number_login_keeps_user_jwt_contract(): void
    {
        $this->get('/front/big-number/login')
            ->assertOk()
            ->assertSee('data-legacy-big-agent="0"', false)
            ->assertSee('data-login-endpoint="' . url('/api/front/auth/big-number/login') . '"', false)
            ->assertSee('name="user_id"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="loginUid"', false)
            ->assertDontSee('data-login-endpoint="' . url('/user/agents/signIn') . '"', false);
    }

    public function test_valid_legacy_login_establishes_session_without_modern_user_token(): void
    {
        $id = 4938101;
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, 'legacy-shell-login-' . $id, 'legacy-shell@example.test', 'legacy-login-password');

        $response = $this->postJson('/user/agents/signIn', [
            'loginUid' => 'legacy-shell-login-' . $id,
            'loginPassword' => 'legacy-login-password',
            'cptcode' => '',
        ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'OK')
            ->assertJsonPath('loginStatus', 200)
            ->assertSessionHas('bigAgents.id', $id);
        $this->assertArrayNotHasKey('access_token', $response->json());
        $this->assertSame('', (string) DB::table('big_agents')->where('id', $id)->value('jwt_token_id'));

        $page = $this->withSession(['bigAgents' => ['id' => $id]])->get('/user/agents/index');
        $page->assertOk()
            ->assertSee('data-legacy-shell="big-agent"', false)
            ->assertDontSee('/api/front/profile', false)
            ->assertDontSee('/api/front/navigation/menus', false)
            ->assertDontSee('layout.js', false);
    }

    public function test_legacy_login_error_branches_never_create_big_agent_session(): void
    {
        $id = 4938106;
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, 'legacy-login-errors-' . $id, 'legacy-errors@example.test', 'legacy-login-password');

        $this->postJson('/user/agents/signIn', [
            'loginUid' => 'missing-legacy-agent',
            'loginPassword' => 'legacy-login-password',
        ])->assertOk()
            ->assertJsonPath('loginStatus', 401)
            ->assertJsonPath('notactive', __('auth.failed'))
            ->assertSessionMissing('bigAgents');

        DB::table('big_agents')->where('id', $id)->update(['is_enabled' => 0]);
        $this->postJson('/user/agents/signIn', [
            'loginUid' => 'legacy-login-errors-' . $id,
            'loginPassword' => 'legacy-login-password',
        ])->assertOk()
            ->assertJsonPath('loginStatus', 403)
            ->assertJsonPath('notactive', __('auth.account_disabled'))
            ->assertSessionMissing('bigAgents');

        DB::table('big_agents')->where('id', $id)->update(['is_enabled' => 1]);
        $this->postJson('/user/agents/signIn', [
            'loginUid' => 'legacy-login-errors-' . $id,
            'loginPassword' => 'wrong-password',
        ])->assertOk()
            ->assertJsonPath('loginStatus', 404)
            ->assertJsonPath('errpsw', __('auth.failed'))
            ->assertSessionMissing('bigAgents');

        DB::table('big_agents')->where('id', $id)->update(['deleted_at' => time()]);
        $this->postJson('/user/agents/signIn', [
            'loginUid' => 'legacy-login-errors-' . $id,
            'loginPassword' => 'legacy-login-password',
        ])->assertOk()
            ->assertJsonPath('loginStatus', 401)
            ->assertSessionMissing('bigAgents');
    }

    public function test_disabled_soft_deleted_or_missing_session_cannot_render_legacy_pages(): void
    {
        $id = 4938102;
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, 'legacy-shell-disabled-' . $id, 'legacy-disabled@example.test', 'legacy-password');

        foreach ([
            ['is_enabled' => 0, 'deleted_at' => null],
            ['is_enabled' => 1, 'deleted_at' => time()],
        ] as $state) {
            DB::table('big_agents')->where('id', $id)->update($state);

            $this->withSession(['bigAgents' => ['id' => $id]])
                ->get('/user/agents/index')
                ->assertRedirect('/agents/login');
        }

        $this->withSession(['bigAgents' => ['id' => 999999991]])
            ->get('/user/agents/index')
            ->assertRedirect('/agents/login');
    }

    public function test_legacy_pages_use_old_post_endpoints_and_csrf_aware_shell(): void
    {
        $id = 4938103;
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, 'legacy-shell-pages-' . $id, 'legacy-pages@example.test', 'legacy-password');
        $session = ['bigAgents' => ['id' => $id]];

        $cases = [
            ['/user/agents/proxy/list', '/user/agents/proxy/proxySearch'],
            ['/user/agents/position/summary', '/user/agents/position/positionSummarySearch'],
            ['/user/agents/close/order', '/user/agents/close/closeOrderSearch'],
            ['/user/agents/open/order', '/user/agents/open/openOrderSearch'],
            ['/user/agents/editpsw?frame=1', '/user/agents/changePassword'],
        ];

        foreach ($cases as [$pageUri, $endpoint]) {
            $response = $this->withSession($session)->get($pageUri);

            $response->assertOk()
                ->assertSee('data-legacy-shell="big-agent"', false)
                ->assertSee($endpoint, false)
                ->assertSee('X-CSRF-TOKEN', false)
                ->assertDontSee('/api/front/', false);
        }

        $script = file_get_contents(public_path('js/apps/front/layui/legacy-big-agent.js')) ?: '';
        $this->assertStringContainsString('X-CSRF-TOKEN', $script);
        $this->assertStringContainsString("response.rows || []", $script);
        $this->assertStringNotContainsString("Authorization", $script);
    }

    public function test_legacy_child_pagination_preserves_endpoint_and_parent_payload(): void
    {
        $script = file_get_contents(public_path('js/apps/front/layui/legacy-big-agent.js')) ?: '';

        $this->assertStringContainsString('function renderPagination(total, endpoint, extraPayload)', $script);
        $this->assertStringContainsString('loadRows(endpoint, extraPayload);', $script);
        $this->assertStringContainsString(
            'renderPagination(response.total || 0, endpoint, extraPayload);',
            $script
        );
    }

    public function test_legacy_search_and_reset_reload_root_endpoint_without_drill_context(): void
    {
        $script = file_get_contents(public_path('js/apps/front/layui/legacy-big-agent.js')) ?: '';
        $script = str_replace("\r\n", "\n", $script);

        $this->assertStringContainsString(
            "function loadRootRows() {\n        loadRows();\n    }",
            $script
        );
        $this->assertStringContainsString(
            "layui.form.on('submit(legacyBigAgentSearch)', function () {\n"
                . "            currentPage = 1;\n"
                . "            loadRootRows();\n"
                . "            return false;\n"
                . "        });",
            $script
        );
        $this->assertStringContainsString(
            "\$('#legacyBigAgentReset').on('click', function () {\n"
                . "            document.getElementById('legacyBigAgentSearchForm').reset();\n"
                . "            currentPage = 1;\n"
                . "            loadRootRows();\n"
                . "        });",
            $script
        );
        $this->assertStringContainsString(
            "loadRows(endpoint, {userPId: userId, searchtype: 'subSearch'});",
            $script
        );
    }

    public function test_legacy_async_actions_cancel_superseded_requests_and_only_render_latest_response(): void
    {
        $source = file_get_contents(public_path('js/apps/front/layui/legacy-big-agent.js')) ?: '';
        $encodedSource = json_encode($source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encodedSource);

        $scenario = str_replace('__SCRIPT_SOURCE__', $encodedSource, <<<'JS'
'use strict';

const handlers = {};
const requests = [];
const renderedHtml = [];
const pagerCounts = [];
const pagers = [];
const errors = [];
let resetCalls = 0;

function chainable(overrides) {
    return Object.assign({
        length: 1,
        attr: function () { return ''; },
        each: function () { return this; },
        empty: function () { return this; },
        append: function () { return this; },
        serializeArray: function () { return []; },
        on: function () { return this; }
    }, overrides || {});
}

const tableConfig = chainable({
    attr: function (name) {
        const values = {
            'data-columns': '[{"key":"user_id"}]',
            'data-endpoint': '/root',
            'data-child-endpoint': '/child',
            'data-empty-text': 'empty',
            'data-error-text': 'error'
        };
        return values[name] || '';
    }
});

const document = {
    getElementById: function () {
        return {reset: function () { resetCalls += 1; }};
    }
};

function jquery(target) {
    if (target === document) {
        return chainable({
            on: function (event, selector, callback) {
                if (event === 'click' && selector === '.J_legacyBigAgentDrill') {
                    handlers.drill = callback;
                }
                return this;
            }
        });
    }
    if (target && target.__attrs) {
        return chainable({attr: function (name) { return target.__attrs[name] || ''; }});
    }
    if (target === '#legacyBigAgentTableConfig') {
        return tableConfig;
    }
    if (target === '#legacyBigAgentSearchForm select[data-options-endpoint]') {
        return chainable({each: function () { return this; }});
    }
    if (target === '#legacyBigAgentSearchForm') {
        return chainable({serializeArray: function () { return []; }});
    }
    if (target === '#legacyBigAgentResultTable tbody') {
        return chainable({append: function (html) { renderedHtml.push(String(html)); return this; }});
    }
    if (target === '#legacyBigAgentResultTable tfoot') {
        return chainable();
    }
    if (target === '#legacyBigAgentReset') {
        return chainable({
            on: function (event, callback) {
                if (event === 'click') handlers.reset = callback;
                return this;
            }
        });
    }
    if (target === '<div>') {
        let value = '';
        return chainable({
            text: function (next) { value = next; return this; },
            html: function () { return String(value); }
        });
    }
    return chainable();
}

jquery.extend = function (target) {
    for (let index = 1; index < arguments.length; index += 1) {
        Object.assign(target, arguments[index] || {});
    }
    return target;
};
jquery.ajaxSetup = function () {};
jquery.when = function () {
    return {always: function (callback) { callback(); return this; }};
};
jquery.getJSON = function () {
    throw new Error('No dynamic option request is expected in this scenario.');
};
jquery.ajax = function (options) {
    const doneCallbacks = [];
    const failCallbacks = [];
    const alwaysCallbacks = [];
    const request = {
        options: options,
        aborted: false,
        settled: false,
        done: function (callback) { doneCallbacks.push(callback); return request; },
        fail: function (callback) { failCallbacks.push(callback); return request; },
        always: function (callback) { alwaysCallbacks.push(callback); return request; },
        abort: function () { request.aborted = true; return request; },
        resolve: function (response) {
            if (request.settled) return;
            request.settled = true;
            doneCallbacks.slice().forEach(function (callback) { callback(response); });
            alwaysCallbacks.slice().forEach(function (callback) { callback(response, 'success', request); });
        },
        reject: function (response, status) {
            if (request.settled) return;
            request.settled = true;
            failCallbacks.slice().forEach(function (callback) { callback(response, status || 'error'); });
            alwaysCallbacks.slice().forEach(function (callback) { callback(response, status || 'error', request); });
        }
    };
    requests.push(request);
    return request;
};

const layui = {
    form: {
        on: function (event, callback) {
            if (event === 'submit(legacyBigAgentSearch)') handlers.search = callback;
        },
        render: function () {}
    },
    layer: {msg: function (message) { errors.push(message); }},
    laypage: {
        render: function (options) {
            pagerCounts.push(options.count);
            pagers.push(options);
        }
    },
    use: function (modules, callback) { callback(); }
};
const window = {
    LegacyBigAgent: {csrfToken: 'csrf-token'},
    jQuery: jquery,
    layui: layui,
    location: {href: ''}
};

eval(__SCRIPT_SOURCE__);

requests[0].resolve({rows: [{user_id: 'initial'}], total: 20, footer: []});
const oldRootPager = pagers[0];
handlers.drill.call({__attrs: {'data-user-id': '77'}});
handlers.search();
handlers.reset();
oldRootPager.jump({curr: 2}, false);

if (requests.length === 5) {
    requests[4].resolve({rows: [{user_id: 'latest-page-2'}], total: 444, footer: []});
    requests[1].resolve({rows: [{user_id: 'stale-drill'}], total: 111, footer: []});
    requests[2].resolve({rows: [{user_id: 'stale-search'}], total: 222, footer: []});
    requests[3].resolve({rows: [{user_id: 'stale-reset'}], total: 333, footer: []});
} else if (requests[1]) {
    requests[1].resolve({rows: [{user_id: 'stale-drill'}], total: 111, footer: []});
}

console.log(JSON.stringify({
    requestUrls: requests.map(function (request) { return request.options.url; }),
    requestPages: requests.map(function (request) { return request.options.data.page; }),
    requestSearchTypes: requests.map(function (request) { return request.options.data.searchtype; }),
    requestParents: requests.map(function (request) { return request.options.data.userPId || null; }),
    aborted: requests.map(function (request) { return request.aborted; }),
    renderedHtml: renderedHtml,
    pagerCounts: pagerCounts,
    resetCalls: resetCalls,
    errors: errors
}));
JS
        );

        $result = $this->executeJavascriptJson($scenario);

        $this->assertSame(['/root', '/child', '/root', '/root', '/root'], $result['requestUrls']);
        $this->assertSame([1, 1, 1, 1, 2], $result['requestPages']);
        $this->assertSame(['clickSearch', 'subSearch', 'clickSearch', 'clickSearch', 'clickSearch'], $result['requestSearchTypes']);
        $this->assertSame([null, 77, null, null, null], $result['requestParents']);
        $this->assertSame([false, true, true, true, false], $result['aborted']);
        $this->assertSame(1, $result['resetCalls']);
        $this->assertSame([20, 444], $result['pagerCounts']);
        $rendered = implode("\n", $result['renderedHtml']);
        $this->assertStringContainsString('initial', $rendered);
        $this->assertStringContainsString('latest-page-2', $rendered);
        $this->assertStringNotContainsString('stale-drill', $rendered);
        $this->assertStringNotContainsString('stale-search', $rendered);
        $this->assertStringNotContainsString('stale-reset', $rendered);
        $this->assertSame([], $result['errors']);
    }

    public function test_crmui_big_agent_password_page_keeps_the_legacy_change_password_contract(): void
    {
        $id = 4938107;
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, 'crmui-password-' . $id, 'crmui-password@example.test', 'legacy-password');

        $response = $this->withSession(['bigAgents' => ['id' => $id]])
            ->get('/front-crmui/big-agent/profile/password');

        $response->assertOk()
            ->assertSee('data-crmui-session="big-agent"', false)
            ->assertSee('data-crmui-legacy-response="1"', false)
            ->assertSee('data-action-url="' . url('/user/agents/changePassword') . '"', false)
            ->assertSee('name="old_password"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertDontSee('/api/front/', false);
    }

    public function test_crmui_big_agent_pages_only_bind_scoped_legacy_endpoints_without_jwt(): void
    {
        $id = 4938108;
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, 'crmui-pages-' . $id, 'crmui-pages@example.test', 'legacy-password');
        $session = ['bigAgents' => ['id' => $id]];

        foreach (['crmui', 'naive'] as $family) {
            foreach ([
                ["/front-{$family}/big-agent/proxy/list", '/user/agents/proxy/proxySearch'],
                ["/front-{$family}/big-agent/proxy/descendants?userId=100", '/user/agents/proxy/proxySearchBySub'],
                ["/front-{$family}/big-agent/position/summary", '/user/agents/position/positionSummarySearch'],
                ["/front-{$family}/big-agent/position/descendants?userId=100", '/user/agents/position/subAgentsListSearch'],
                ["/front-{$family}/big-agent/orders/open", '/user/agents/open/openOrderSearch'],
                ["/front-{$family}/big-agent/orders/closed", '/user/agents/close/closeOrderSearch'],
            ] as [$uri, $endpoint]) {
                $response = $this->withSession($session)->get($uri);

                $response->assertOk()
                    ->assertSee('data-crmui-session="big-agent"', false)
                    ->assertSee('data-crmui-legacy-response="1"', false)
                    ->assertSee('data-ui-family="' . $family . '"', false)
                    ->assertSee('data-ui-current-family="' . $family . '"', false)
                    ->assertSee('data-api-url="' . url($endpoint) . '"', false)
                    ->assertDontSee('/api/front/', false);
            }
        }

        $script = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $this->assertStringContainsString("data-crmui-session') === 'big-agent'", $script);
        $this->assertStringContainsString('auth: bigAgentSession ? false : undefined', $script);
    }

    public function test_disabled_or_soft_deleted_big_agent_cannot_render_crmui_pages(): void
    {
        $id = 4938109;
        $this->deleteBigAgent($id);
        $this->insertBigAgent($id, 'crmui-inactive-' . $id, 'crmui-inactive@example.test', 'legacy-password');

        foreach ([
            ['is_enabled' => 0, 'deleted_at' => null],
            ['is_enabled' => 1, 'deleted_at' => time()],
        ] as $state) {
            DB::table('big_agents')->where('id', $id)->update($state);

            $this->withSession(['bigAgents' => ['id' => $id]])
                ->get('/front-crmui/big-agent/orders/open')
                ->assertRedirect('/front-crmui/big-agent/login');
        }
    }

    public function test_legacy_order_search_honors_order_user_id_alias(): void
    {
        $bigId = 4938104;
        $agentId = 49381041;
        $customerId = 49381042;
        $otherCustomerId = 49381043;
        $visibleTicket = 949381041;
        $otherTicket = 949381042;

        $this->deleteBigAgent($bigId);
        $this->deleteUsers([$agentId, $customerId, $otherCustomerId]);
        $this->insertUser($agentId, 'legacy-order-agent', 1, 0);
        $this->insertUser($customerId, 'legacy-order-customer', 2, $agentId);
        $this->insertUser($otherCustomerId, 'legacy-order-other', 2, $agentId);
        $this->insertBigAgent($bigId, 'legacy-order-big-' . $bigId, 'legacy-order@example.test', 'legacy-password', $agentId);
        $this->insertTrade($customerId, $visibleTicket);
        $this->insertTrade($otherCustomerId, $otherTicket);

        $response = $this->withSession(['bigAgents' => ['id' => $bigId]])
            ->postJson('/user/agents/close/closeOrderSearch', [
                'orderUserId' => $customerId,
                'limit' => 20,
            ]);

        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertStringContainsString((string) $visibleTicket, $response->getContent());
        $this->assertStringNotContainsString((string) $otherTicket, $response->getContent());
    }

    public function test_legacy_agent_response_contains_old_count_aliases(): void
    {
        $bigId = 4938105;
        $agentId = 49381051;

        $this->deleteBigAgent($bigId);
        $this->deleteUsers([$agentId]);
        $this->insertUser($agentId, 'legacy-alias-agent', 1, 0);
        $this->insertBigAgent($bigId, 'legacy-alias-big-' . $bigId, 'legacy-alias@example.test', 'legacy-password', $agentId);

        $response = $this->withSession(['bigAgents' => ['id' => $bigId]])
            ->postJson('/user/agents/proxy/proxySearch', ['limit' => 20]);

        $response->assertOk()->assertJsonPath('total', 1);
        $row = $response->json('rows.0');
        foreach (['agentsTotal', 'accountTotal', 'user_status', 'group_comm_prop'] as $key) {
            $this->assertArrayHasKey($key, $row, $key . ' is required by the legacy big-agent table.');
        }
    }

    private function insertBigAgent(int $id, string $username, string $email, string $password, int $subAgentId = 0): void
    {
        DB::table('big_agents')->insert([
            'id' => $id,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make($password),
            'sub_agent_ids' => $subAgentId > 0 ? (string) $subAgentId : '',
            'is_enabled' => 1,
            'jwt_token_id' => '',
            'created_by' => 'phpunit',
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function insertUser(int $userId, string $name, int $accountType, int $parentId): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-shell-' . $userId . '@example.test',
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
            'user_name' => $name,
            'phone' => '1380000' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $parentId > 0 ? $parentId . ',' . $userId : '',
            'group_id' => 0,
            'level_id' => $accountType === 1 ? 1 : 0,
            'comm_rate' => 0.1,
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

    private function insertTrade(int $userId, int $ticket): void
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
            'close_time' => '2026-07-09 12:00:00',
            'expiration' => null,
            'reason' => 0,
            'conv_rate1' => 1,
            'conv_rate2' => 1,
            'commission' => 0,
            'commission_agent' => 0,
            'swaps' => 0,
            'close_price' => 2301.20,
            'profit' => 10.50,
            'taxes' => 0,
            'comment' => 'legacy shell order',
            'internal_id' => 0,
            'margin_rate' => 1,
            'timestamp_val' => $now,
            'magic' => 0,
            'gw_volume' => 0,
            'gw_open_price' => 0,
            'gw_close_price' => 0,
            'modify_time' => '2026-07-09 12:00:00',
            'settlement_status' => 1,
            'settled_at' => '2026-07-09 12:05:00',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function deleteBigAgent(int $id): void
    {
        DB::table('big_agents')->where('id', $id)->delete();
    }

    /** @param array<int, int> $ids */
    private function deleteUsers(array $ids): void
    {
        DB::table('user_trades')->whereIn('user_id', $ids)->delete();
        DB::table('user_infos')->whereIn('user_id', $ids)->delete();
        DB::table('user_logins')->whereIn('user_id', $ids)->delete();
    }
}
