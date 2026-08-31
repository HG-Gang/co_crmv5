<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:16
 */

/**
 * 前台账户流水演示数据覆盖闭环测试。
 *
 * 文件功能：
 * - 验证 FrontDemoDataSeeder 为「账户流水」页面的全部 8 个页签都写入了可展示的演示数据，
 *   演示环境下任何一个页签都不会出现空表。
 * - 验证演示数据仍受 local/testing + FRONT_DEMO_SEEDER_ENABLED 双重门禁保护，
 *   生产环境不会静默获得演示数据。
 * - 验证出金演示数据同时覆盖银行转账与数字货币两种来源，出金来源筛选的两个选项都能筛出结果。
 *
 * 覆盖的页签与后端范围：
 * - all / deposit / withdraw / withdraw_apply：当前登录代理本人的 user_id 记录。
 * - direct_deposit / direct_withdraw：直属客户（agent_descendants.descendant_type=2 且 is_direct=1）。
 * - direct_agents_deposit / direct_agents_withdraw：直属代理（descendant_type=1 且 is_direct=1）。
 *
 * 入参例子：
 * - 以 Seeder 写入的演示根代理 1001 登录后请求 /api/front/flows/*，per_page=10。
 *
 * 返回值：
 * - 测试无返回值；断言每个页签 data.list.data 至少 1 行即表示演示数据闭环。
 *
 * 异常或失败场景：
 * - 某个页签为空表示 Seeder 未覆盖该页签的数据范围，演示时会出现空白表格。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FrontDemoDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class FrontFlowDemoDataCoverageModuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var int DEMO_ROOT_AGENT_ID 演示根代理业务用户编号，与 FrontDemoDataSeeder 保持一致。 */
    private const DEMO_ROOT_AGENT_ID = 1001;

    /** @var array<int, int> DEMO_DIRECT_AGENT_IDS 演示直属下级代理业务用户编号。 */
    private const DEMO_DIRECT_AGENT_IDS = [1101, 1102];

    /** @var array<int, int> DEMO_DIRECT_CUSTOMER_IDS 演示直属客户业务用户编号。 */
    private const DEMO_DIRECT_CUSTOMER_IDS = [600101, 600102];

    /**
     * 验证账户流水 8 个页签在演示数据下全部有行。
     *
     * @return void 每个页签都至少返回 1 行时无返回值；出现空表时断言报告具体页签。
     */
    public function test_every_account_flow_tab_has_demo_rows(): void
    {
        $this->seedDemoFlowFixture();

        $login = UserLogin::where('user_id', self::DEMO_ROOT_AGENT_ID)->firstOrFail();
        $endpoints = [
            'all' => '/api/front/flows/account',
            'deposit' => '/api/front/flows/deposits',
            'withdraw' => '/api/front/flows/withdrawals',
            'withdraw_apply' => '/api/front/flows/withdrawal-applications',
            'direct_deposit' => '/api/front/flows/direct-deposits',
            'direct_withdraw' => '/api/front/flows/direct-withdrawals',
            'direct_agents_deposit' => '/api/front/flows/direct-agent-deposits',
            'direct_agents_withdraw' => '/api/front/flows/direct-agent-withdrawals',
        ];

        foreach ($endpoints as $tab => $endpoint) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->getJson($endpoint . '?per_page=10');

            $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
            $rows = $response->json('data.list.data');
            $this->assertIsArray($rows, 'Flow tab ' . $tab . ' must return a list payload.');
            $this->assertNotEmpty(
                $rows,
                'Flow tab ' . $tab . ' has no demo rows; the demo seeder must cover it so the tab is never empty.'
            );
        }
    }

    /**
     * 验证本人页签读到的是登录代理自己的记录，直属页签读到的是下级记录。
     *
     * @return void 归属正确时无返回值；串数据时断言失败。
     */
    public function test_demo_flow_rows_belong_to_the_expected_scope(): void
    {
        $this->seedDemoFlowFixture();

        $login = UserLogin::where('user_id', self::DEMO_ROOT_AGENT_ID)->firstOrFail();

        $ownDeposit = $this->flowRequest($login, '/api/front/flows/deposits');
        $this->assertSame(
            (string) self::DEMO_ROOT_AGENT_ID,
            (string) $ownDeposit->json('data.list.data.0.userId'),
            'The deposit tab must show the logged-in agent own records.'
        );

        $agentDeposit = $this->flowRequest($login, '/api/front/flows/direct-agent-deposits');
        $this->assertContains(
            (int) $agentDeposit->json('data.list.data.0.userId'),
            self::DEMO_DIRECT_AGENT_IDS,
            'The direct agent deposit tab must show direct sub-agent records.'
        );

        $customerDeposit = $this->flowRequest($login, '/api/front/flows/direct-deposits');
        $this->assertContains(
            (int) $customerDeposit->json('data.list.data.0.userId'),
            self::DEMO_DIRECT_CUSTOMER_IDS,
            'The direct customer deposit tab must show direct customer records.'
        );
    }

    /**
     * 验证出金演示数据同时覆盖银行转账与数字货币两种来源。
     *
     * @return void 两个筛选值都能返回行时无返回值。
     */
    public function test_demo_withdraw_rows_cover_both_withdraw_sources(): void
    {
        $this->seedDemoFlowFixture();

        $login = UserLogin::where('user_id', self::DEMO_ROOT_AGENT_ID)->firstOrFail();

        // 使用与语言无关的稳定筛选键；控制器同时接受这两个键和当前语言的展示文案。
        foreach (['bank_transfer', 'crypto_currency'] as $source) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->getJson('/api/front/flows/withdrawals?per_page=10&withdraw_source=' . $source);

            $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
            $this->assertNotEmpty(
                $response->json('data.list.data'),
                'Withdraw source filter "' . $source . '" must have demo rows.'
            );
        }

        // 演示数据必须同时包含银行卡出金（bank_name 有值）与数字货币出金（bank_name 为空）两类行。
        $bankNames = DB::table('withdraw_records')
            ->where('user_id', self::DEMO_ROOT_AGENT_ID)
            ->pluck('bank_name')
            ->all();

        $this->assertNotEmpty(
            array_filter($bankNames, static function ($value) {
                return trim((string) $value) !== '';
            }),
            'Demo withdraw rows must include bank transfer rows.'
        );
        $this->assertNotEmpty(
            array_filter($bankNames, static function ($value) {
                return trim((string) $value) === '';
            }),
            'Demo withdraw rows must include crypto currency rows.'
        );
    }

    /**
     * 验证演示数据仍受双重门禁保护：非 local/testing 环境或未显式开启开关时都不得注入。
     *
     * @return void 门禁生效时无返回值。
     */
    public function test_demo_seeder_stays_behind_the_environment_and_flag_double_gate(): void
    {
        $seeder = new DatabaseSeeder();

        Config::set('seeding.front_demo_enabled', false);
        $this->assertFalse(
            (bool) $this->invokeMethod($seeder, 'shouldSeedFrontDemoData'),
            'Demo data must stay disabled while FRONT_DEMO_SEEDER_ENABLED is off.'
        );

        Config::set('seeding.front_demo_enabled', true);
        $this->assertTrue(
            (bool) $this->invokeMethod($seeder, 'shouldSeedFrontDemoData'),
            'Demo data must be available in the testing environment once the flag is explicitly enabled.'
        );

        Config::set('seeding.front_demo_enabled', '1');
        $this->assertFalse(
            (bool) $this->invokeMethod($seeder, 'shouldSeedFrontDemoData'),
            'The gate must require a strict boolean true, not any truthy value.'
        );

        $configPath = base_path('config/seeding.php');
        $this->assertStringContainsString(
            "env('FRONT_DEMO_SEEDER_ENABLED', false)",
            file_get_contents($configPath) ?: '',
            'The demo gate must stay driven by an explicit environment flag.'
        );
    }

    /**
     * 验证 Seeder 显式声明了代理侧流水演示数据的补齐方法。
     *
     * @return void 方法与调用都存在时无返回值。
     */
    public function test_seeder_declares_agent_flow_demo_records(): void
    {
        $source = file_get_contents(database_path('seeders/FrontDemoDataSeeder.php')) ?: '';

        $this->assertStringContainsString('private function seedAgentFlowRecords(', $source);
        $this->assertStringContainsString('$this->seedAgentFlowRecords($now, $users);', $source);
        $this->assertStringContainsString("'local_order_no' => 'AGDEP'", $source);
        $this->assertStringContainsString("'local_order_no' => 'AGWDR'", $source);
    }

    /**
     * 写入本测试需要的演示流水前置数据。
     *
     * 只调用 Seeder 中与流水相关的受控私有方法，避免执行菜单、配置和 MT4 相关的全量步骤。
     *
     * @return void 前置数据写入后无返回值。
     */
    private function seedDemoFlowFixture(): void
    {
        $now = time();
        $seeder = new FrontDemoDataSeeder();
        $users = $this->demoUsers();
        $userIds = array_keys($users);

        DB::table('agent_descendants')->whereIn('agent_id', $userIds)->orWhereIn('descendant_id', $userIds)->delete();
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();

        foreach ($users as $userId => $user) {
            $loginId = (int) $this->invokeMethod($seeder, 'upsertLogin', [$userId, $user, $now]);
            $this->invokeMethod($seeder, 'upsertUserInfo', [$userId, $loginId, $user, $now]);
        }

        $this->invokeMethod($seeder, 'seedHierarchy', [$now, $users]);
        $this->invokeMethod($seeder, 'seedFinance', [$now, $users, []]);
        $this->invokeMethod($seeder, 'seedAgentFlowRecords', [$now, $users]);
        $this->invokeMethod($seeder, 'seedCommission', [$now, $users]);
    }

    /**
     * 返回本测试使用的演示用户集合，字段与 FrontDemoDataSeeder::seedUsers 保持一致。
     *
     * @return array<int, array<string, mixed>> 键为业务用户编号的演示用户集合。
     */
    private function demoUsers(): array
    {
        return [
            1001 => ['email' => 'agent@test.com', 'password' => 'abc123', 'name' => 'Demo Root Agent', 'type' => 1, 'parent' => 0, 'level' => 0, 'group' => 0, 'rate' => 65, 'funds' => 88000, 'is_mt4_enabled' => 1],
            1101 => ['email' => 'subagent1@test.com', 'password' => 'abc123', 'name' => 'Demo Sub Agent A', 'type' => 1, 'parent' => 1001, 'level' => 0, 'group' => 0, 'rate' => 48, 'funds' => 42000, 'is_mt4_enabled' => 1],
            1102 => ['email' => 'subagent2@test.com', 'password' => 'abc123', 'name' => 'Demo Sub Agent B', 'type' => 1, 'parent' => 1001, 'level' => 0, 'group' => 0, 'rate' => 45, 'funds' => 39000, 'is_mt4_enabled' => 1],
            600101 => ['email' => 'customer1@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 1', 'type' => 2, 'parent' => 1001, 'level' => 0, 'group' => 0, 'rate' => 0, 'funds' => 13200, 'is_mt4_enabled' => 1],
            600102 => ['email' => 'customer2@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 2', 'type' => 2, 'parent' => 1001, 'level' => 0, 'group' => 0, 'rate' => 0, 'funds' => 8600, 'is_mt4_enabled' => 1],
        ];
    }

    /**
     * 以演示代理身份请求流水接口，并断言响应为业务成功。
     *
     * @param UserLogin $login 演示代理登录记录。
     * @param string $endpoint 流水接口地址。
     * @return \Illuminate\Testing\TestResponse 流水接口响应。
     */
    private function flowRequest(UserLogin $login, string $endpoint)
    {
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson($endpoint . '?per_page=10');

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);

        return $response;
    }

    /**
     * 调用受控的私有方法，避免执行与本测试无关的全量演示步骤。
     *
     * @param object $subject 目标对象。
     * @param string $methodName 私有方法名称。
     * @param array<int, mixed> $arguments 按目标方法签名排列的参数。
     * @return mixed 目标方法的真实返回值。
     */
    private function invokeMethod(object $subject, string $methodName, array $arguments = [])
    {
        $method = new ReflectionMethod($subject, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($subject, $arguments);
    }
}
