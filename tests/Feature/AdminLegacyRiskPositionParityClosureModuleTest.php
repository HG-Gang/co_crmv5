<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:05
 */

/**
 * AdminLegacyRiskPositionParityClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台风控持仓双口径等价：真实持仓旧契约、非法筛选拒绝、旧默认日期窗口、order_type 区分真实/测试组、分页优先级、大精度持仓保留精确字符串。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 锁定旧 FengXian 持仓风险 V1/V2 的真实本地读模型与响应契约。
 */
class AdminLegacyRiskPositionParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 新旧风险持仓对账用例的业务用户 ID。验证 v1/v2 接口返回同一份本地事实源数据。
     * @var int
     */
    private const USER_ID = 998181;
    /**
     * USER_ID 映射的真实 MT4 登录号。断言两个版本都输出真实登录号。
     * @var int
     */
    private const MT4_LOGIN = 898181;
    /**
     * 夹具开仓订单 ticket，构成新旧接口对账的样本持仓。
     * @var int
     */
    private const VALID_TICKET = 99100101;

    public function test_v1_and_v2_return_real_open_position_risk_with_the_legacy_contract(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->seedUser(self::USER_ID, self::MT4_LOGIN, 'Legacy risk position');
        $this->seedTrade(self::VALID_TICKET, 125, -50, 1, '1970-01-01 00:00:00');
        $this->seedTrade(99100102, 125, -50, 0, '1970-01-01 00:00:00');
        $this->seedTrade(99100103, 125, -50, 1, '2026-08-18 12:00:00');
        $this->seedTrade(99100104, 40, -50, 1, '1970-01-01 00:00:00');

        $v1 = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/fengXian/positionSearch',
            ['userId' => self::USER_ID, 'startdate' => '2026-08-18', 'enddate' => '2026-08-18']
        );

        $v1->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.ticket', self::VALID_TICKET)
            ->assertJsonPath('rows.0.user_id', self::USER_ID)
            ->assertJsonPath('rows.0.login', self::MT4_LOGIN)
            ->assertJsonPath('rows.0.sl', '90')
            ->assertJsonPath('rows.0.tp', '130')
            ->assertJsonPath('rows.0.abs_comm', '50.00')
            ->assertJsonPath('rows.0.feng_xian_positionval', '150.00');

        $v2 = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/fengXian/positionSearchv2',
            ['user_id' => self::USER_ID, 'start_date' => '2026-08-18', 'end_date' => '2026-08-18']
        );

        $v2->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.ticket', self::VALID_TICKET)
            ->assertJsonPath('data.0.feng_xian_positionval', '150.00')
            ->assertJsonPath('totalRow', []);
    }

    /**
     * 旧别名与现代别名共用严格失败关闭校验，不能把数组或零值隐式转成有效筛选。
     *
     * @dataProvider invalidPositionFilterProvider
     */
    public function test_position_search_rejects_invalid_filters(
        array $payload,
        string $field
    ): void {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.field', $field);
    }

    public static function invalidPositionFilterProvider(): array
    {
        return [
            'user id zero' => [['userId' => 0], 'user_id'],
            'ticket array' => [['orderId' => ['99100101']], 'ticket'],
            'symbol array' => [['symbol' => ['XAUUSD']], 'symbol'],
            'invalid start date' => [['startdate' => '2026/08/18'], 'start_date'],
            'reversed dates' => [['startdate' => '2026-08-19', 'enddate' => '2026-08-18'], 'date_range'],
            'future start against default end' => [['startdate' => '2099-01-01'], 'date_range'],
            'page zero' => [['page' => 0], 'page'],
            'rows zero' => [['rows' => 0], 'rows'],
        ];
    }

    public function test_v1_empty_result_preserves_the_legacy_empty_rows_marker(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', ['userId' => 998199])
            ->assertOk()
            ->assertJsonPath('rows', '')
            ->assertJsonPath('total', 0);
    }

    public function test_v2_empty_result_uses_empty_arrays_in_the_v2_envelope(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearchv2', ['userId' => 998199])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow', []);
    }

    public function test_missing_dates_use_the_legacy_2024_to_today_window(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->seedUser(self::USER_ID, self::MT4_LOGIN, 'Legacy risk date window');
        $this->seedTrade(self::VALID_TICKET, 125, -50, 1, '1970-01-01 00:00:00');
        $this->seedTrade(
            99100105,
            125,
            -50,
            1,
            '1970-01-01 00:00:00',
            self::USER_ID,
            '2023-12-31 23:59:59'
        );

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', ['userId' => self::USER_ID])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.ticket', self::VALID_TICKET);
    }

    public function test_modern_positions_without_dates_include_open_positions_from_2023(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ]);
        $userId = 998190;
        $ticket = 99100241;
        $this->seedUser($userId, 898190, 'Modern risk date window');
        $this->seedTrade(
            $ticket,
            25,
            -5,
            1,
            '1970-01-01 00:00:00',
            $userId,
            '2023-06-15 10:00:00'
        );

        $this->actingAs($admin, 'admin')
            ->postJson('/api/admin/riskPositions', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.ticket', $ticket);
    }

    public function test_non_positive_mt4_code_does_not_fall_back_to_business_user_id_as_login(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 998191;
        $ticket = 99100242;
        $this->seedUser($userId, 0, 'Risk invalid MT4 login');
        $this->seedTrade($ticket, 25, -5, 1, '1970-01-01 00:00:00', $userId);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', [
                'userId' => $userId,
                'startdate' => '2026-08-18',
                'enddate' => '2026-08-18',
            ]);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.ticket', $ticket)
            ->assertJsonPath('rows.0.login', null);
        $this->assertNotSame($userId, $response->json('rows.0.login'));
    }

    public function test_order_type_separates_real_and_test_groups_and_excludes_missing_groups(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $symbol = 'RISK-TYPE';
        $users = [
            998182 => ['login' => 898182, 'group' => 'RISK-LIVE', 'ticket' => 99100111],
            998183 => ['login' => 898183, 'group' => 'RISK-TEST', 'ticket' => 99100112],
            998184 => ['login' => 898184, 'group' => 'RISK-TEST-P', 'ticket' => 99100113],
            998185 => ['login' => 898185, 'group' => '', 'ticket' => 99100114],
        ];

        foreach ($users as $userId => $fixture) {
            $this->seedUser($userId, $fixture['login'], 'Risk order type ' . $userId, $fixture['group']);
            $this->seedTrade(
                $fixture['ticket'],
                25,
                -5,
                1,
                '1970-01-01 00:00:00',
                $userId,
                '2026-08-18 10:00:00',
                $symbol
            );
        }

        $real = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/fengXian/positionSearch',
            ['orderType' => 'real_disk', 'symbol' => $symbol, 'startdate' => '2026-08-18', 'enddate' => '2026-08-18']
        );
        $real->assertOk()->assertJsonPath('total', 1);
        $this->assertSame([99100111], array_column($real->json('rows'), 'ticket'));

        $test = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/fengXian/positionSearch',
            ['order_type' => 'test_disk', 'symbol' => $symbol, 'start_date' => '2026-08-18', 'end_date' => '2026-08-18']
        );
        $test->assertOk()->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([99100112, 99100113], array_column($test->json('rows'), 'ticket'));

        foreach (['real_disk', 'test_disk'] as $orderType) {
            $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/fengXian/positionSearch', [
                    'userId' => 998185,
                    'orderType' => $orderType,
                    'startdate' => '2026-08-18',
                    'enddate' => '2026-08-18',
                ])
                ->assertOk()
                ->assertJsonPath('total', 0);
        }
    }

    public function test_position_pagination_uses_rows_then_limit_then_per_page_then_fifteen(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ]);
        $userId = 998186;
        $symbol = 'RISK-PAGE';
        $this->seedUser($userId, 898186, 'Risk pagination');
        for ($offset = 0; $offset < 20; $offset++) {
            $this->seedTrade(
                99100200 + $offset,
                25,
                -5,
                1,
                '1970-01-01 00:00:00',
                $userId,
                '2026-08-18 10:00:00',
                $symbol
            );
        }

        $cases = [
            [['rows' => 2, 'limit' => 3, 'per_page' => 4], 2],
            [['rows' => null, 'limit' => 3, 'per_page' => 4], 3],
            [['limit' => null, 'per_page' => 4], 4],
            [[], 15],
        ];

        foreach ($cases as [$pagination, $expectedPerPage]) {
            $response = $this->actingAs($admin, 'admin')->postJson(
                '/api/admin/riskPositions',
                array_merge([
                    'user_id' => $userId,
                    'symbol' => $symbol,
                    'start_date' => '2026-08-18',
                    'end_date' => '2026-08-18',
                ], $pagination)
            );

            $response->assertOk();
            $this->assertSame(ResponseCode::SUCCESS, $response->json('code'), $response->getContent());
            $response->assertJsonPath('data.records.per_page', $expectedPerPage);
            $this->assertCount($expectedPerPage, $response->json('data.records.data'));
        }
    }

    public function test_zero_commission_uses_profit_as_the_position_risk_value(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 998187;
        $ticket = 99100221;
        $this->seedUser($userId, 898187, 'Risk zero commission');
        $this->seedTrade($ticket, 123.45, 0, 1, '1970-01-01 00:00:00', $userId);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', [
                'userId' => $userId,
                'startdate' => '2026-08-18',
                'enddate' => '2026-08-18',
            ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.ticket', $ticket)
            ->assertJsonPath('rows.0.abs_comm', '0.00')
            ->assertJsonPath('rows.0.feng_xian_positionval', '123.45');
    }

    public function test_legacy_v1_and_v2_apply_the_restricted_admin_user_scope(): void
    {
        $visibleUserId = 998188;
        $outsideUserId = 998189;
        $visibleTicket = 99100231;
        $outsideTicket = 99100232;
        $symbol = 'RISK-SCOPE';
        $admin = $this->seedRestrictedAdmin([$visibleUserId]);
        $this->seedUser($visibleUserId, 898188, 'Visible scoped risk user');
        $this->seedUser($outsideUserId, 898189, 'Outside scoped risk user');
        $this->seedTrade($visibleTicket, 25, -5, 1, '1970-01-01 00:00:00', $visibleUserId, '2026-08-18 10:00:00', $symbol);
        $this->seedTrade($outsideTicket, 25, -5, 1, '1970-01-01 00:00:00', $outsideUserId, '2026-08-18 10:00:00', $symbol);

        $payload = ['symbol' => $symbol, 'startdate' => '2026-08-18', 'enddate' => '2026-08-18'];
        $v1 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', $payload);
        $v1->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.ticket', $visibleTicket);
        $this->assertStringNotContainsString((string) $outsideTicket, $v1->getContent());

        $v2 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearchv2', $payload);
        $v2->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.ticket', $visibleTicket)
            ->assertJsonPath('totalRow', []);
        $this->assertStringNotContainsString((string) $outsideTicket, $v2->getContent());
    }

    public function test_large_position_decimals_remain_exact_strings_without_php_float_conversion(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 998192;
        $ticket = 99100251;
        $this->seedUser($userId, 898192, 'Risk large decimal');
        $this->seedTrade(
            $ticket,
            '9007199254740.50',
            '-0.50',
            1,
            '1970-01-01 00:00:00',
            $userId
        );
        DB::table('user_trades')->where('ticket', $ticket)->update([
            'swaps' => '-123456789012.50',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/fengXian/positionSearch', [
                'userId' => $userId,
                'startdate' => '2026-08-18',
                'enddate' => '2026-08-18',
            ]);

        $response->assertOk()->assertJsonPath('total', 1);
        $row = $response->json('rows.0');
        $this->assertSame('9007199254740.50', $row['profit']);
        $this->assertSame('-0.50', $row['commission']);
        $this->assertSame('-123456789012.50', $row['swaps']);
        $this->assertSame('9007199254740.00', $row['risk_value']);
        $this->assertSame('1801439850948000.00', $row['feng_xian_positionval']);
        $this->assertSame('0.50', $row['abs_comm']);

        $source = (string) file_get_contents(app_path('Services/LegacyRiskQueryService.php'));
        $this->assertStringNotContainsString('(float)', $source);
    }

    /** @param array<int, int> $visibleUserIds */
    private function seedRestrictedAdmin(array $visibleUserIds): Admin
    {
        $now = time();
        $roleId = DB::table('roles')->insertGetId([
            'name' => uniqid('risk-position-scope-', true),
            'guard_type' => 'admin',
            'description' => 'Legacy risk position scope test',
            'permissions' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $permissionId = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('api_route', 'admin_api_riskPositions')
            ->where('status', 1)
            ->value('id');
        $this->assertNotNull($permissionId);
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->insert([
            'role_id' => $roleId,
            'scope_type' => 'custom_users',
            'agent_ids' => null,
            'user_ids' => json_encode($visibleUserIds),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $adminId = DB::table('admins')->insertGetId([
            'role_id' => $roleId,
            'username' => uniqid('risk-position-scope-admin-', true),
            'email' => uniqid('risk-position-scope-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    private function seedUser(int $userId, int $mt4Login, string $userName, string $group = 'RISK-LIVE'): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => ',' . $userId . ',',
                'mt4_code' => $mt4Login,
                'mt4_group' => $group,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function seedTrade(
        int $ticket,
        $profit,
        $commission,
        float $marginRate,
        string $closeTime,
        int $userId = self::USER_ID,
        string $openTime = '2026-08-18 10:00:00',
        string $symbol = 'XAUUSD'
    ): void {
        $now = time();

        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => $symbol,
            'digits' => 5,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => $openTime,
            'open_price' => 100.12345,
            'stop_loss' => 90,
            'take_profit' => 130,
            'close_time' => $closeTime,
            'commission' => $commission,
            'swaps' => -2.5,
            'close_price' => 0,
            'profit' => $profit,
            'comment' => 'legacy risk position parity',
            'margin_rate' => $marginRate,
            'modify_time' => $openTime,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
