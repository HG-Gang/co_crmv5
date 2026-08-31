<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 01:31
 */

/**
 * Phase2Task4LegacyAdminAgentRouteContractTest
 *
 * 文件功能：
 * - 验证 Phase2 旧后台代理路由契约：旧路由经 legacy handle 注册、business_id 页面在数据范围外失败关闭、代理页拒绝客户 ID、旧 GET 页面只读、nested 旧字段映射注册契约、外部状态未知不报成功。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Models\Admin;
use App\Models\UserLogin;
use App\Services\UserPasswordService;
use App\Services\UserRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

/**
 * Phase 2 Task 4: lock the old AgentControllerV3 URI adapter at the HTTP boundary.
 */
final class Phase2Task4LegacyAdminAgentRouteContractTest extends TestCase
{
    use DatabaseTransactions;

    /** @dataProvider legacyAgentRouteProvider */
    public function test_old_agent_route_is_registered_through_legacy_admin_handle(
        string $method,
        string $uri
    ): void {
        $routes = array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            static function (LaravelRoute $route) use ($method, $uri): bool {
                return trim($route->uri(), '/') === $uri
                    && in_array($method, $route->methods(), true);
            }
        ));

        $this->assertNotSame([], $routes, 'Missing legacy Agent route: ' . $method . ' ' . $uri);
        foreach ($routes as $route) {
            $this->assertSame(
                LegacyAdminController::class . '@handle',
                $route->getActionName(),
                $method . ' ' . $uri
            );
            $this->assertContains('legacy.admin.auth', $route->gatherMiddleware(), $method . ' ' . $uri);
        }
    }

    public function legacyAgentRouteProvider(): array
    {
        return [
            ['POST', 'index/admin/send/againSendSms'],
            ['GET', 'index/admin/agent/edit/{user_id?}'],
            ['GET', 'index/admin/agents_add'],
            ['GET', 'index/admin/agents/agents_edit_info/{uid}'],
            ['GET', 'index/admin/agents_examine'],
            ['GET', 'index/admin/agents_list'],
            ['POST', 'index/admin/agents_save'],
            ['GET', 'index/admin/customer/{user_id?}'],
            ['GET', 'index/admin/agent/{user_id?}'],
        ];
    }

    public function test_business_id_pages_fail_closed_outside_the_admin_data_scope(): void
    {
        $visibleAgentId = $this->unusedUserId();
        $hiddenAgentId = $this->unusedUserId();
        $this->seedUser($visibleAgentId, 1, 'Visible scoped agent');
        $this->seedUser($hiddenAgentId, 1, 'Hidden out-of-scope agent');
        $admin = $this->createAgentTreeAdmin($visibleAgentId);

        foreach ($this->businessIdPageUrls($hiddenAgentId) as $url) {
            $response = $this->withoutMiddleware(LegacyAdminAuthenticate::class)
                ->actingAs($admin, 'admin')
                ->getJson($url);

            $this->assertFailClosed($response, $url);
        }
    }

    public function test_agent_business_id_pages_reject_a_customer_id(): void
    {
        $admin = $this->ensureSuperAdmin();
        $customerId = $this->unusedUserId();
        $this->seedUser($customerId, 2, 'Customer must not own an agent page');

        foreach ($this->businessIdPageUrls($customerId) as $url) {
            $response = $this->withoutMiddleware(LegacyAdminAuthenticate::class)
                ->actingAs($admin, 'admin')
                ->getJson($url);

            $this->assertFailClosed($response, $url);
        }
    }

    public function test_all_old_agent_get_pages_are_read_only(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentId = $this->unusedUserId();
        $this->seedUser($agentId, 1, 'Read-only page agent');
        $urls = [
            '/index/admin/agent/edit/' . $agentId,
            '/index/admin/agents_add',
            '/index/admin/agents/agents_edit_info/' . $agentId,
            '/index/admin/agents_examine',
            '/index/admin/agents_list',
            '/index/admin/customer/' . $agentId,
            '/index/admin/agent/' . $agentId,
        ];

        foreach ($urls as $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $response = $this->withoutMiddleware(LegacyAdminAuthenticate::class)
                    ->actingAs($admin, 'admin')
                    ->get($url);
                $queries = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
            }

            $response->assertOk();
            $this->assertSame([], $this->writeQueries($queries), $url . ' must not write to the database.');
        }
    }

    public function test_customer_page_lists_only_direct_customers_with_local_financial_summary(): void
    {
        $admin = $this->ensureSuperAdmin();
        $agentId = $this->unusedUserId();
        $directCustomerId = $this->unusedUserId();
        $nestedCustomerId = $this->unusedUserId();
        $unrelatedCustomerId = $this->unusedUserId();

        $this->seedUser($agentId, 1, 'Direct customer parent agent');
        $this->seedUser($directCustomerId, 2, 'Direct customer page fixture');
        $this->seedUser($nestedCustomerId, 2, 'Nested customer page fixture');
        $this->seedUser($unrelatedCustomerId, 2, 'Unrelated customer page fixture');

        DB::table('user_infos')->where('user_id', $directCustomerId)->update([
            'parent_id' => $agentId,
            'family_tree' => $agentId . ',' . $directCustomerId,
            'total_funds' => 123.45,
            'equity' => 120.40,
            'mt4_group' => 'direct-customer-group',
        ]);
        DB::table('user_infos')->where('user_id', $nestedCustomerId)->update([
            'parent_id' => $directCustomerId,
            'family_tree' => $agentId . ',' . $directCustomerId . ',' . $nestedCustomerId,
        ]);
        $this->seedBalanceTrade($directCustomerId, 500.00, 'Deposit');
        $this->seedBalanceTrade($directCustomerId, -100.00, 'Withdrawal');

        $response = $this->withoutMiddleware(LegacyAdminAuthenticate::class)
            ->actingAs($admin, 'admin')
            ->get('/index/admin/customer/' . $agentId);

        $response->assertOk();
        $response->assertSee('data-legacy-direct-customers', false);
        $response->assertSee('data-customer-id="' . $directCustomerId . '"', false);
        $response->assertDontSee('data-customer-id="' . $nestedCustomerId . '"', false);
        $response->assertDontSee('data-customer-id="' . $unrelatedCustomerId . '"', false);
        $response->assertSee('123.45');
        $response->assertSee('120.40');
        $response->assertSee('500.00');
        $response->assertSee('-100.00');
        $response->assertSee('400.00');
    }

    public function test_agents_save_maps_nested_old_fields_to_the_registration_contract(): void
    {
        $admin = $this->ensureSuperAdmin();
        $email = 'phase2-task4-agent-' . bin2hex(random_bytes(5)) . '@example.test';
        $registration = Mockery::mock(UserRegistrationService::class);
        $registration->shouldReceive('register')
            ->once()
            ->withArgs(static function (array $payload, $parentId, int $accountType) use ($email): bool {
                return $accountType === 1
                    && $parentId === 10
                    && $payload['email'] === $email
                    && $payload['password'] === 'LegacyA123'
                    && $payload['password_confirmation'] === 'LegacyA123'
                    && $payload['user_name'] === 'Phase2 old nested agent'
                    && $payload['phone'] === '86-13800138004'
                    && $payload['id_card_no'] === 'PHASE2-TASK4-NESTED'
                    && (int) $payload['gender'] === 1
                    && $payload['commission_mode'] === 'A';
            })
            ->andReturn([
                'success' => true,
                'registered' => true,
                'provisioning_status' => 'pending',
                'data' => ['user_id' => 41994004, 'email' => $email],
            ]);
        $this->app->instance(UserRegistrationService::class, $registration);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/agents_save', [
                'data' => [
                    'useremail' => $email,
                    'password1' => 'LegacyA123',
                    'againpassword' => 'LegacyA123',
                    'username' => 'Phase2 old nested agent',
                    'userphoneNo' => '13800138004',
                    'modules' => '86',
                    'userIdcardNo' => 'PHASE2-TASK4-NESTED',
                    'userInviterId' => 10,
                    'sex' => '1',
                    'comm_type' => 'A',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED)
            ->assertJsonPath('data.user_id', 41994004);
    }

    public function test_agents_save_does_not_report_success_when_external_status_is_unknown(): void
    {
        $admin = $this->ensureSuperAdmin();
        $registration = Mockery::mock(UserRegistrationService::class);
        $registration->shouldReceive('register')->once()->andReturn([
            'success' => false,
            'message' => 'MT4/external status unknown',
            'data' => [],
        ]);
        $this->app->instance(UserRegistrationService::class, $registration);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/agents_save', [
                'data' => [
                    'useremail' => 'phase2-task4-unknown@example.test',
                    'password1' => 'LegacyA123',
                    'againpassword' => 'LegacyA123',
                    'username' => 'External unknown agent',
                    'userphoneNo' => '13800138005',
                    'modules' => '86',
                    'userIdcardNo' => 'PHASE2-TASK4-UNKNOWN',
                    'userInviterId' => 10,
                ],
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertNotContains((int) $response->json('code'), [
            ResponseCode::SUCCESS,
            ResponseCode::CREATED,
            ResponseCode::UPDATED,
        ]);
    }

    public function test_again_send_sms_rejects_a_non_strict_user_id_without_changing_password(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = $this->unusedUserId();
        $this->seedUser($userId, 1, 'Strict reset target');
        $before = (string) DB::table('user_logins')->where('user_id', $userId)->value('password');
        $passwordService = Mockery::mock(UserPasswordService::class);
        $passwordService->shouldNotReceive('change');
        $this->app->instance(UserPasswordService::class, $passwordService);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/send/againSendSms', [
                'user_id' => $userId . 'abc',
                'password1' => 'ResetA123',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame($before, DB::table('user_logins')->where('user_id', $userId)->value('password'));
    }

    public function test_again_send_sms_does_not_fabricate_success_for_an_unknown_password_change(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = $this->unusedUserId();
        $this->seedUser($userId, 1, 'Unknown reset target');
        $before = (string) DB::table('user_logins')->where('user_id', $userId)->value('password');
        $passwordService = Mockery::mock(UserPasswordService::class);
        $passwordService->shouldReceive('change')
            ->once()
            ->withArgs(static function (UserLogin $login, string $password) use ($userId): bool {
                return (int) $login->user_id === $userId && $password === 'ResetA123';
            })
            ->andReturn(false);
        $this->app->instance(UserPasswordService::class, $passwordService);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/send/againSendSms', [
                'user_id' => $userId,
                'password1' => 'ResetA123',
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertNotContains((int) $response->json('code'), [
            ResponseCode::SUCCESS,
            ResponseCode::CREATED,
            ResponseCode::UPDATED,
        ]);
        $this->assertSame($before, DB::table('user_logins')->where('user_id', $userId)->value('password'));
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'phase2-task4-super',
                'email' => 'phase2-task4-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createAgentTreeAdmin(int $visibleAgentId): Admin
    {
        $now = time();
        $token = bin2hex(random_bytes(5));
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'phase2-task4-scope-' . $token,
            'guard_type' => 'admin',
            'description' => 'Phase2 Task4 route scope fixture',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->insert([
            'role_id' => $roleId,
            'scope_type' => 'agent_tree',
            'agent_ids' => null,
            'user_ids' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $adminId = DB::table('admins')->insertGetId([
            'username' => 'phase2-task4-scope-' . $token,
            'email' => 'phase2-task4-scope-' . $token . '@example.test',
            'password' => Hash::make('password'),
            'role_id' => $roleId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admin_agent_bindings')->insert([
            'admin_id' => $adminId,
            'agent_id' => $visibleAgentId,
            'binding_type' => 'primary',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    private function seedUser(int $userId, int $accountType, string $name): void
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'phase2-task4-' . $userId . '@example.test',
            'password' => Hash::make('OriginalA123'),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $name,
            'phone' => '86-13' . substr((string) $userId, -9),
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = random_int(1200000000, 1900000000);
            if (!DB::table('user_logins')->where('user_id', $candidate)->exists()
                && !DB::table('user_infos')->where('user_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to allocate a Phase2 Task4 fixture user ID.');
    }

    private function seedBalanceTrade(int $userId, float $profit, string $comment): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $ticket = random_int(2000000000, 2147483646);
            if (!DB::table('user_trades')->where('ticket', $ticket)->exists()) {
                DB::table('user_trades')->insert([
                    'user_id' => $userId,
                    'ticket' => $ticket,
                    'symbol' => '',
                    'digits' => 2,
                    'cmd' => 6,
                    'volume' => 0,
                    'open_time' => '2026-08-10 09:00:00',
                    'open_price' => 0,
                    'close_time' => '2026-08-10 09:00:00',
                    'close_price' => 0,
                    'profit' => $profit,
                    'commission' => 0,
                    'swaps' => 0,
                    'comment' => $comment,
                    'margin_rate' => 0,
                    'modify_time' => '2026-08-10 09:00:00',
                    'created_at' => time(),
                    'updated_at' => time(),
                    'deleted_at' => null,
                ]);

                return;
            }
        }

        throw new \RuntimeException('Unable to allocate a Phase2 Task4 fixture ticket.');
    }

    /** @return array<int, string> */
    private function businessIdPageUrls(int $userId): array
    {
        return [
            '/index/admin/agent/edit/' . $userId,
            '/index/admin/agents/agents_edit_info/' . $userId,
            '/index/admin/customer/' . $userId,
            '/index/admin/agent/' . $userId,
        ];
    }

    private function assertFailClosed($response, string $url): void
    {
        $code = null;
        if (strpos((string) $response->headers->get('content-type'), 'json') !== false) {
            $code = (int) $response->json('code');
        }

        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 404], true)
                || in_array($code, [
                    ResponseCode::PERMISSION_DENIED,
                    ResponseCode::USER_NOT_FOUND,
                    ResponseCode::DATA_NOT_FOUND,
                    ResponseCode::VALIDATION_FAILED,
                ], true),
            $url . ' returned an accessible page instead of failing closed.'
        );
    }

    /** @param array<int, array<string, mixed>> $queries */
    private function writeQueries(array $queries): array
    {
        return array_values(array_filter($queries, static function (array $query): bool {
            return preg_match(
                '/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i',
                (string) ($query['query'] ?? '')
            ) === 1;
        }));
    }
}
