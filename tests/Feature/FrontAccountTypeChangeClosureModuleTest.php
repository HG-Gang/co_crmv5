<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 10:32
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 前台账户类型切换闭环测试。
 *
 * 文件功能：
 * - 验证当前登录用户只能修改自己的交易账户类型。
 * - 验证 ECN/STP 切换先通过配对组解析目标 MT4 组，再一次同步组别和杠杆。
 * - 验证 ECN 最低净值资格由服务端强制执行，前端无法绕过。
 * - 验证旧账户页由 Blade 输出完整切换控件，并复用账户资料接口的单次加载结果。
 * - 验证 MT4 明确失败或组别关系缺失时，本地用户资料保持不变。
 *
 * 入参示例：
 * - POST /user/change_account_save，is_enc=1 表示切换到 ECN。
 *
 * 返回值：
 * - 成功返回 msg=SUCCESS、err=noerr。
 * - MT4 失败返回 msg=FAIL、err=MT4OHTERUPDFAIL。
 * - 配对组缺失返回 msg=FAIL、err=relationGroupNotExit。
 */
class FrontAccountTypeChangeClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证账户资料接口返回交易账户类型和 ECN 最低净值，供 Blade 页面一次请求完成渲染。
     *
     * @return void
     */
    public function test_account_profile_endpoint_returns_trading_type_and_ecn_minimum_equity(): void
    {
        $userId = 413260001;
        $groups = $this->insertPairedGroups('account-profile-type');
        $this->insertUser($userId, 'account-profile-type', $groups['ecn_id'], 'TEST-ECN', 1, 200, 4500.00);

        $response = $this->actingAs($this->login($userId), 'user')
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->getJson('/api/front/account/profile');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.is_ecn', 1)
            ->assertJsonPath('data.ecn_minimum_equity', 3000);
    }

    /**
     * 验证成功切换会同步 MT4，并原子更新全部本地交易组字段。
     *
     * @return void
     */
    public function test_successful_switch_syncs_mt4_and_updates_local_trading_fields(): void
    {
        $viewerId = 413260101;
        $otherId = 413260102;
        $groups = $this->insertPairedGroups('account-switch-success');
        $this->insertUser($viewerId, 'account-switch-viewer', $groups['stp_id'], 'TEST-STP', 0, 100);
        $this->insertUser($otherId, 'account-switch-other', $groups['stp_id'], 'TEST-STP', 0, 100);

        $manager = new AccountTypeChangeMt4ManagerStub([
            ['status' => 'ok', 'err' => '0', 'message' => 'OK'],
        ]);
        $this->app->instance(Mt4ManagerService::class, $manager);

        $response = $this->actingAs($this->login($viewerId), 'user')
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->postJson('/user/change_account_save', [
                'is_enc' => 1,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertJsonPath('err', 'noerr')
            ->assertJsonPath('col', 'nocol');

        $this->assertSame([
            ['user_id' => $viewerId, 'group' => 'TEST-ECN', 'leverage' => 200],
        ], $manager->calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'group_id' => $groups['ecn_id'],
            'mt4_group' => 'TEST-ECN',
            'original_group' => 'TEST-STP',
            'is_ecn' => 1,
            'leverage' => 200,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'group_id' => $groups['stp_id'],
            'mt4_group' => 'TEST-STP',
            'is_ecn' => 0,
            'leverage' => 100,
        ]);
    }

    /**
     * 验证净值低于 3000 时服务端拒绝切换 ECN，且不得调用 MT4 或改写本地资料。
     *
     * @return void
     */
    public function test_low_equity_rejected_by_server_without_mt4_call_or_local_change(): void
    {
        $userId = 413260151;
        $groups = $this->insertPairedGroups('account-switch-low-equity');
        $this->insertUser($userId, 'account-switch-low-equity', $groups['stp_id'], 'TEST-STP', 0, 100, 2999.99);

        $manager = new AccountTypeChangeMt4ManagerStub([
            ['status' => 'ok', 'err' => '0', 'message' => 'OK'],
        ]);
        $this->app->instance(Mt4ManagerService::class, $manager);

        $response = $this->actingAs($this->login($userId), 'user')
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->postJson('/user/change_account_save', ['is_enc' => 1]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'ECNMINBALANCE')
            ->assertJsonPath('col', 'is_enc');
        $this->assertSame([], $manager->calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $groups['stp_id'],
            'mt4_group' => 'TEST-STP',
            'is_ecn' => 0,
            'leverage' => 100,
        ]);
    }

    /**
     * 验证 MT4 未明确成功时禁止提前写入本地交易组和杠杆。
     *
     * @return void
     */
    public function test_mt4_non_success_blocks_local_group_and_leverage_update(): void
    {
        $userId = 413260201;
        $groups = $this->insertPairedGroups('account-switch-mt4-failure');
        $this->insertUser($userId, 'account-switch-failure', $groups['stp_id'], 'TEST-STP', 0, 100);

        $manager = new AccountTypeChangeMt4ManagerStub([
            ['status' => 'error', 'err' => '1003', 'error_code' => '1003', 'message' => 'provider rejected'],
        ]);
        $this->app->instance(Mt4ManagerService::class, $manager);

        $response = $this->actingAs($this->login($userId), 'user')
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->postJson('/user/change_account_save', ['is_enc' => 1]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'MT4OHTERUPDFAIL')
            ->assertJsonPath('col', 'userphoneNo');
        $this->assertCount(1, $manager->calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $groups['stp_id'],
            'mt4_group' => 'TEST-STP',
            'original_group' => '',
            'is_ecn' => 0,
            'leverage' => 100,
        ]);
    }

    /**
     * 验证配对组缺失时直接拒绝请求，且不得调用 MT4 或修改本地资料。
     *
     * @return void
     */
    public function test_missing_pair_group_rejects_request_without_mt4_call_or_local_change(): void
    {
        $userId = 413260301;
        $groupId = $this->insertGroup('TEST-STP-NO-PAIR', 0, null);
        $this->insertUser($userId, 'account-switch-no-pair', $groupId, 'TEST-STP-NO-PAIR', 0, 100);

        $manager = new AccountTypeChangeMt4ManagerStub([
            ['status' => 'ok', 'err' => '0', 'message' => 'OK'],
        ]);
        $this->app->instance(Mt4ManagerService::class, $manager);

        $response = $this->actingAs($this->login($userId), 'user')
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->postJson('/user/change_account_save', ['is_enc' => 1]);

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'relationGroupNotExit')
            ->assertJsonPath('col', 'is_enc');
        $this->assertSame([], $manager->calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $groupId,
            'mt4_group' => 'TEST-STP-NO-PAIR',
            'is_ecn' => 0,
            'leverage' => 100,
        ]);
    }

    /**
     * 验证 GET /user/account 的 frame 页面包含可操作的 Blade 账户类型切换区和自定义静态资源。
     *
     * @return void
     */
    public function test_account_page_frame_contains_operable_blade_switch_and_assets(): void
    {
        $userId = 413260401;
        $groups = $this->insertPairedGroups('account-page-contract');
        $this->insertUser($userId, 'account-page-contract', $groups['stp_id'], 'TEST-STP', 0, 100);

        $response = $this->actingAs($this->login($userId), 'user')
            ->get('/user/account?frame=1');

        $response->assertOk()
            ->assertSee('id="accountTypeSwitch"', false)
            ->assertSee('data-change-api="/user/change_account_save"', false)
            ->assertSee('data-account-type="0"', false)
            ->assertSee('data-account-type="1"', false)
            ->assertSee('data-lucide="repeat-2"', false)
            ->assertSee('/css/front/account-type.css?v=2026072802', false)
            ->assertSee('/js/apps/front/layui/account-type.js?v=2026081801', false)
            ->assertDontSee('node_modules', false);
    }

    /**
     * 验证 CrmUI 与 Naive 账户页复用同一账户类型组件和现代 REST 入口。
     *
     * @return void
     */
    public function test_crmui_and_naive_account_pages_share_operable_switch_and_rest_endpoint(): void
    {
        foreach (['/front-crmui/account/info', '/front-naive/account/info'] as $url) {
            $response = $this->get($url . '?frame=1');

            $response->assertOk()
                ->assertSee('data-crmui-page="front.account_info"', false)
                ->assertSee('id="accountTypeSwitch"', false)
                ->assertSee('data-change-api="http://localhost/api/front/account/trading-profile"', false)
                ->assertSee('data-account-type="0"', false)
                ->assertSee('data-account-type="1"', false)
                ->assertSee('/css/front/account-type.css?v=2026072802', false)
                ->assertSee('/js/apps/front/layui/account-type.js?v=2026081801', false);
        }
    }

    /**
     * 验证现代账户类型资源使用 RESTful PATCH，并继续复用既有切换逻辑。
     *
     * @return void
     */
    public function test_modern_account_type_route_is_restful_and_reuses_existing_controller_logic(): void
    {
        $route = Route::getRoutes()->getByName('front_api_account_trading_profile_update');

        $this->assertNotNull($route);
        $this->assertSame('api/front/account/trading-profile', $route->uri());
        $this->assertSame(['PATCH'], $route->methods());
        $this->assertSame(
            'App\\Http\\Controllers\\Front\\AccountController@updateTradingProfile',
            $route->getActionName()
        );
    }

    /**
     * 验证现代 PATCH 与旧 POST 使用相同的事务、资格和 MT4 同步逻辑。
     *
     * @return void
     */
    public function test_modern_patch_switch_updates_the_authenticated_account_through_existing_logic(): void
    {
        $userId = 413260451;
        $groups = $this->insertPairedGroups('account-modern-switch');
        $this->insertUser($userId, 'account-modern-switch', $groups['stp_id'], 'TEST-STP', 0, 100);

        $manager = new AccountTypeChangeMt4ManagerStub([
            ['status' => 'ok', 'err' => '0', 'message' => 'OK'],
        ]);
        $this->app->instance(Mt4ManagerService::class, $manager);

        $response = $this->actingAs($this->login($userId), 'user')
            ->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->patchJson('/api/front/account/trading-profile', ['is_ecn' => 1]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertJsonPath('err', 'noerr');
        $this->assertSame([
            ['user_id' => $userId, 'group' => 'TEST-ECN', 'leverage' => 200],
        ], $manager->calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $groups['ecn_id'],
            'is_ecn' => 1,
            'leverage' => 200,
        ]);
    }

    /**
     * 验证账户专用脚本消费通用模块已加载事件，并通过通用模块事件刷新而不重复直连资料接口。
     *
     * @return void
     */
    public function test_account_script_consumes_shared_module_loaded_event_without_direct_profile_call(): void
    {
        $accountScriptPath = public_path('js/apps/front/layui/account-type.js');
        $accountStylePath = public_path('css/front/account-type.css');
        $moduleScriptPath = public_path('js/apps/front/layui/module-page.js');
        $crmuiScriptPath = public_path('js/apps/crmui/front.js');

        $this->assertFileExists($accountScriptPath);
        $this->assertFileExists($accountStylePath);

        $accountScript = file_get_contents($accountScriptPath) ?: '';
        $moduleScript = file_get_contents($moduleScriptPath) ?: '';
        $crmuiScript = file_get_contents($crmuiScriptPath) ?: '';

        $this->assertStringContainsString('crm:module-page-loaded', $moduleScript);
        $this->assertStringContainsString('crm:module-page-reload', $moduleScript);
        $this->assertStringContainsString('crm:module-page-loaded', $accountScript);
        $this->assertStringContainsString('crm:module-page-reload', $accountScript);
        $this->assertStringContainsString('front.account_info', $accountScript);
        $this->assertStringContainsString("is_enc", $accountScript);
        $this->assertStringContainsString('ECNMINBALANCE', $accountScript);
        $this->assertStringContainsString('ERRVOL', $accountScript);
        $this->assertStringContainsString('MT4OHTERUPDFAIL', $accountScript);
        $this->assertStringNotContainsString('/api/front/account/profile', $accountScript);
        $this->assertStringContainsString('crm:module-page-loaded', $crmuiScript);
        $this->assertStringContainsString('crm:module-page-reload', $crmuiScript);
    }

    /**
     * 验证低净值用户的 ECN 目标不仅禁止提交，还会禁用单选控件并显示不可用状态。
     *
     * 这样可以在浏览器原生交互层阻止无资格用户继续操作，服务端最低净值校验仍作为最终安全边界。
     *
     * @return void
     */
    public function test_low_equity_ecn_option_disabled_and_marked_unavailable(): void
    {
        $accountScript = file_get_contents(public_path('js/apps/front/layui/account-type.js')) ?: '';
        $accountStyle = file_get_contents(public_path('css/front/account-type.css')) ?: '';

        $this->assertStringContainsString('type === 1 && !canSubmit', $accountScript);
        $this->assertStringContainsString("classList.toggle('is-unavailable'", $accountScript);
        $this->assertStringContainsString('.account-type-option.is-unavailable', $accountStyle);
    }

    /**
     * 创建一组双向关联的 STP 与 ECN 测试交易组。
     *
     * @param string $suffix 组名称后缀，用于避免不同测试数据重名。
     * @return array{stp_id: int, ecn_id: int} 返回两个当前表主键。
     */
    private function insertPairedGroups(string $suffix): array
    {
        $stpId = $this->insertGroup('TEST-STP', 0, null, $suffix);
        $ecnId = $this->insertGroup('TEST-ECN', 1, $stpId, $suffix);
        DB::table('group_configs')->where('id', $stpId)->update(['pair_id' => $ecnId]);

        return ['stp_id' => $stpId, 'ecn_id' => $ecnId];
    }

    /**
     * 写入一条测试交易组配置。
     *
     * @param string $mt4Name MT4 真实组名。
     * @param int $isEcn 账户类型，0=STP，1=ECN。
     * @param int|null $pairId 当前表配对组主键；null 表示暂未配对。
     * @param string $suffix 仅用于备注化区分测试数据，不改变真实 MT4 组名。
     * @return int 新增 group_configs.id。
     */
    private function insertGroup(string $mt4Name, int $isEcn, int $pairId = null, string $suffix = ''): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'pair_id' => $pairId,
            'name' => $mt4Name,
            'radix' => 50,
            'category' => 2,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => $isEcn,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 写入可登录的前台客户资料。
     *
     * @param int $userId 业务用户编号。
     * @param string $userName 用户姓名。
     * @param int $groupId 当前 group_configs.id。
     * @param string $mt4Group 当前 MT4 组名。
     * @param int $isEcn 账户类型，0=STP，1=ECN。
     * @param int $leverage 当前杠杆。
     * @param float $equity 当前账户净值；默认 3000 用于验证 ECN 资格边界值可通过。
     * @return void
     */
    private function insertUser(
        int $userId,
        string $userName,
        int $groupId,
        string $mt4Group,
        int $isEcn,
        int $leverage,
        float $equity = 3000.00
    ): void {
        $now = time();
        $email = $userName . '-' . $userId . '@example.test';
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => 2,
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
            'phone' => '139' . substr((string) $userId, -8),
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'group_id' => $groupId,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => $equity,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => $equity,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => $leverage,
            'is_ecn' => $isEcn,
            'mt4_group' => $mt4Group,
            'original_group' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 读取测试用户登录模型。
     *
     * @param int $userId 业务用户编号。
     * @return UserLogin 当前登录记录。
     */
    private function login(int $userId): UserLogin
    {
        return UserLogin::where('user_id', $userId)->firstOrFail();
    }
}

/**
 * 账户类型切换专用 MT4 测试替身。
 *
 * 文件功能：记录 update_user 调用参数，并按测试场景返回预设结果，不访问真实 Socket。
 */
final class AccountTypeChangeMt4ManagerStub extends Mt4ManagerService
{
    /** @var array<int, array<string, mixed>> $results 依次返回的 MT4 结果。 */
    private $results;

    /** @var array<int, array{user_id: int, group: string, leverage: int}> $calls 已收到的同步调用。 */
    public $calls = [];

    /**
     * 构造测试替身。
     *
     * @param array<int, array<string, mixed>> $results 每次调用应返回的结果。
     */
    public function __construct(array $results)
    {
        parent::__construct('127.0.0.1', 1, 'test-key', 'test-version', 1);
        $this->results = $results;
    }

    /**
     * 模拟一次 MT4 用户组别与杠杆统一更新。
     *
     * @param int $userId 当前登录用户编号。
     * @param string $group 目标 MT4 组名。
     * @param int $leverage 目标杠杆。
     * @return array<string, mixed> 预设的 MT4 规范化响应。
     */
    public function updateUserTradingProfile($userId, $group, $leverage)
    {
        $this->calls[] = [
            'user_id' => (int) $userId,
            'group' => (string) $group,
            'leverage' => (int) $leverage,
        ];

        return array_shift($this->results) ?: [
            'status' => 'error',
            'error_code' => 'missing_test_result',
            'message' => '测试未配置 MT4 返回结果',
        ];
    }
}
