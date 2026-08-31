<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 23:26
 */

/**
 * AdminLegacyRiskProfitParityClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台风控盈亏双口径等价：完整用户公式与三种响应契约、MT4 注册日期与精确身份筛选、受限管理范围、大数值精确 decimal 字符串、查询次数恒定与各类排除口径。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminLegacyRiskProfitParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 盈亏风险新旧对账用例的业务用户 ID。验证 v1/v2 返回同一份用户公式与响应契约。
     * @var int
     */
    private const USER_ID = 985101;
    /**
     * USER_ID 映射的真实 MT4 登录号。断言两个版本都输出真实登录号。
     * @var int
     */
    private const MT4_LOGIN = 885101;

    public function test_profit_risk_restores_complete_user_formula_and_three_response_contracts(): void
    {
        $admin = $this->ensureSuperAdmin();
        $registeredAt = time() - 3600;
        $this->seedUserAndMt4Account($registeredAt);
        $this->seedTrade(9951011, 0, 100, -10, -3, 125, 1, true, 'closed loss fee');
        $this->seedTrade(9951012, 1, 50, 2, 5, 75, 1, true, 'closed positive fee');
        $this->seedTrade(9951013, 0, 999, -1, -1, 100, 1, false, 'open excluded');
        $this->seedTrade(9951014, 0, 999, -1, -1, 100, 0, true, 'zero margin excluded');
        $this->seedTrade(9951015, 6, 200, 0, 0, 0, 0, true, 'DBAA deposit');
        $this->seedTrade(9951016, 6, -50, 0, 0, 0, 0, true, 'WBCN withdrawal');

        $modern = $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', [
            'user_id' => self::USER_ID,
            'per_page' => 10,
        ]);

        $modern->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 1);
        $this->assertProfitRow($modern->json('data.records.data.0'), $registeredAt);

        $v1 = $this->asAdmin($admin)->postJson('/index/admin/fengXian/profitSearch', [
            'userId' => self::MT4_LOGIN,
            'rows' => 10,
        ]);
        $v1->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertProfitRow($v1->json('rows.0'), $registeredAt);

        $v2 = $this->asAdmin($admin)->postJson('/index/admin/fengXian/profitSearchV2', [
            'userId' => self::USER_ID,
            'rows' => 10,
        ]);
        $v2->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('totalRow', []);
        $this->assertProfitRow($v2->json('data.0'), $registeredAt);
    }

    public function test_profit_filters_use_mt4_registration_date_and_exact_business_or_login_identity(): void
    {
        $admin = $this->ensureSuperAdmin();
        $registeredAt = strtotime('2025-06-15 12:00:00');
        $this->seedSimpleProfitableUser(
            985201,
            885201,
            $registeredAt,
            'Different CRM name',
            'Target MT4 Search Name',
            '2023-02-01 12:00:00'
        );
        $this->seedSimpleProfitableUser(
            985202,
            885202,
            strtotime('2023-12-31 12:00:00'),
            'Outside date CRM',
            'Outside date MT4',
            '2025-06-20 12:00:00'
        );

        foreach ([985201, 885201] as $identity) {
            $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', [
                'user_id' => $identity,
                'start_date' => '2025-06-01',
                'end_date' => '2025-06-30',
            ])->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS)
                ->assertJsonPath('data.records.total', 1)
                ->assertJsonPath('data.records.data.0.user_id', 985201);
        }

        $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', [
            'user_name' => 'MT4 Search',
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ])->assertOk()
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.user_id', 985201);

        $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', [
            'user_id' => 985202,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ])->assertOk()
            ->assertJsonPath('data.records.total', 0);
    }

    public function test_profit_empty_results_keep_each_response_contract(): void
    {
        $admin = $this->ensureSuperAdmin();

        $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', [
            'user_id' => 999999,
        ])->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.records.total', 0)
            ->assertJsonPath('data.records.data', []);

        $this->asAdmin($admin)->postJson('/index/admin/fengXian/profitSearch', [
            'userId' => 999999,
        ])->assertOk()
            ->assertJsonPath('rows', '')
            ->assertJsonPath('total', 0);

        $this->asAdmin($admin)->postJson('/index/admin/fengXian/profitSearchV2', [
            'userId' => 999999,
        ])->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow', []);
    }

    /**
     * @dataProvider invalidProfitFilterProvider
     * @param array<string, mixed> $payload
     */
    public function test_profit_filters_fail_closed_before_query(string $path, array $payload, string $field): void
    {
        $admin = $this->ensureSuperAdmin();

        $this->asAdmin($admin)->postJson($path, $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.field', $field);
    }

    public static function invalidProfitFilterProvider(): array
    {
        return [
            'modern array user id' => ['/api/admin/riskProfitUsers', ['user_id' => [1]], 'user_id'],
            'modern zero user id' => ['/api/admin/riskProfitUsers', ['user_id' => 0], 'user_id'],
            'modern invalid date' => ['/api/admin/riskProfitUsers', ['start_date' => '2026-02-30'], 'start_date'],
            'modern inverted dates' => ['/api/admin/riskProfitUsers', ['start_date' => '2026-08-18', 'end_date' => '2026-08-17'], 'date_range'],
            'legacy negative user id' => ['/index/admin/fengXian/profitSearch', ['userId' => -1], 'user_id'],
            'legacy array username' => ['/index/admin/fengXian/profitSearch', ['username' => ['name']], 'user_name'],
            'legacy array date' => ['/index/admin/fengXian/profitSearch', ['startdate' => ['2026-01-01']], 'start_date'],
            'legacy zero rows' => ['/index/admin/fengXian/profitSearch', ['rows' => 0], 'rows'],
            'legacy v2 invalid end date' => ['/index/admin/fengXian/profitSearchV2', ['enddate' => 'not-a-date'], 'end_date'],
        ];
    }

    public function test_profit_rows_and_totals_apply_the_same_restricted_admin_scope(): void
    {
        $insideUserId = 985301;
        $this->seedSimpleProfitableUser(
            $insideUserId,
            885301,
            strtotime('2026-08-01 12:00:00'),
            'Inside scope CRM',
            'Inside scope MT4',
            '2026-08-02 12:00:00'
        );
        $this->seedSimpleProfitableUser(
            985302,
            885302,
            strtotime('2026-08-01 12:00:00'),
            'Outside scope CRM',
            'Outside scope MT4',
            '2026-08-02 12:00:00'
        );
        $admin = $this->createRestrictedAdmin($insideUserId);

        $modern = $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers');
        $modern->assertOk()
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.user_id', $insideUserId);

        $v1 = $this->asAdmin($admin)->postJson('/index/admin/fengXian/profitSearch');
        $v1->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.user_id', $insideUserId);

        $v2 = $this->asAdmin($admin)->postJson('/index/admin/fengXian/profitSearchV2');
        $v2->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.user_id', $insideUserId);

        foreach ([$modern->getContent(), $v1->getContent(), $v2->getContent()] as $content) {
            $this->assertStringNotContainsString('985302', $content);
        }
    }

    public function test_profit_summary_exposes_legacy_totals_without_per_user_queries(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedUserAndMt4Account(time() - 3600);
        $this->seedTrade(9951021, 0, 100, -10, -3, 125, 1, true, 'closed loss fee');
        $this->seedTrade(9951022, 1, 50, 2, 5, 75, 1, true, 'closed positive fee');
        $this->seedTrade(9951023, 6, 200, 0, 0, 0, 0, true, 'DBAA deposit');
        $this->seedTrade(9951024, 6, -50, 0, 0, 0, 0, true, 'WBCN withdrawal');

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $response = $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers');
            $queryCount = count($connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
        }

        $response->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.summary.total_comm', '8.00')
            ->assertJsonPath('data.summary.total_yuerj', '200.00')
            ->assertJsonPath('data.summary.total_yuecj', '-50.00')
            ->assertJsonPath('data.summary.total_swaps', '3.00')
            ->assertJsonPath('data.summary.total_profit', '150.00')
            ->assertJsonPath('data.summary.total_net_worth', '150.00');
        $this->assertLessThanOrEqual(4, $queryCount, '盈利风险聚合不得按用户产生 N+1 查询。');
    }

    public function test_profit_pagination_prefers_rows_then_limit_then_per_page_then_default(): void
    {
        $admin = $this->ensureSuperAdmin();
        for ($offset = 0; $offset < 16; $offset++) {
            $this->seedSimpleProfitableUser(
                985600 + $offset,
                885600 + $offset,
                time() - 3600,
                'Pagination CRM ' . $offset,
                'Pagination MT4 ' . $offset,
                '2026-08-18 12:00:00'
            );
        }

        $cases = [
            'rows' => [['rows' => 2, 'limit' => 3, 'per_page' => 4], 2],
            'limit' => [['limit' => 3, 'per_page' => 4], 3],
            'per_page' => [['per_page' => 4], 4],
            'default' => [[], 15],
        ];

        foreach ($cases as $label => [$payload, $expected]) {
            $response = $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', $payload);

            $response->assertOk()
                ->assertJsonPath('data.records.per_page', $expected)
                ->assertJsonPath('data.records.total', 16);
            $this->assertCount(
                $expected,
                $response->json('data.records.data'),
                $label . ' did not select the expected page size.'
            );
        }
    }

    public function test_profit_large_safe_magnitudes_remain_exact_decimal_strings(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedUserAndMt4Account(time() - 3600);
        $this->seedTrade(9951031, 0, 999999999999.99, -123456789012.34, -9876543210.12, 100, 1, true, 'large closed trade');
        $this->seedTrade(9951032, 6, 2000000000000.00, 0, 0, 0, 0, true, 'DBAA large deposit');
        $this->seedTrade(9951033, 6, -1000000000000.00, 0, 0, 0, 0, true, 'WBCN large withdrawal');

        $response = $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', [
            'user_id' => self::USER_ID,
        ]);

        $response->assertOk()->assertJsonPath('data.records.total', 1);
        $row = $response->json('data.records.data.0');
        $this->assertSame('123456789012.34', $row['total_comm'] ?? null);
        $this->assertSame('2000000000000.00', $row['total_yuerj'] ?? null);
        $this->assertSame('-1000000000000.00', $row['total_yuecj'] ?? null);
        $this->assertSame('9876543210.12', $row['total_swaps'] ?? null);
        $this->assertSame('999999999999.99', $row['total_profit'] ?? null);
        $this->assertSame('1000000000000.00', $row['total_net_worth'] ?? null);
        $this->assertSame('43.83', $row['feng_xian_val'] ?? null);

        $response->assertJsonPath('data.summary.total_comm', '123456789012.34')
            ->assertJsonPath('data.summary.total_yuerj', '2000000000000.00')
            ->assertJsonPath('data.summary.total_yuecj', '-1000000000000.00')
            ->assertJsonPath('data.summary.total_swaps', '9876543210.12')
            ->assertJsonPath('data.summary.total_profit', '999999999999.99')
            ->assertJsonPath('data.summary.total_net_worth', '1000000000000.00');

        foreach (['total_comm', 'total_yuerj', 'total_yuecj', 'total_swaps', 'total_profit', 'total_net_worth', 'feng_xian_val'] as $field) {
            $this->assertIsString($row[$field] ?? null, $field . ' must remain a decimal string.');
        }
    }

    public function test_profit_query_count_is_constant_as_user_count_grows(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedSimpleProfitableUser(985620, 885620, time() - 3600, 'Query CRM 0', 'Query MT4 0', '2026-08-18 12:00:00');
        $singleUserQueryCount = $this->profitRequestQueryCount($admin);

        for ($offset = 1; $offset <= 6; $offset++) {
            $this->seedSimpleProfitableUser(
                985620 + $offset,
                885620 + $offset,
                time() - 3600,
                'Query CRM ' . $offset,
                'Query MT4 ' . $offset,
                '2026-08-18 12:00:00'
            );
        }
        $sevenUserQueryCount = $this->profitRequestQueryCount($admin);

        $this->assertSame(
            $singleUserQueryCount,
            $sevenUserQueryCount,
            'Profit risk query count must not grow with returned users.'
        );
        $this->assertLessThanOrEqual(4, $sevenUserQueryCount);
    }

    public function test_profit_excludes_soft_deleted_users_and_mt4_accounts(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedSimpleProfitableUser(985640, 885640, time() - 3600, 'Deleted CRM', 'Deleted user MT4', '2026-08-18 12:00:00');
        DB::table('user_infos')->where('user_id', 985640)->update(['deleted_at' => time()]);

        $this->seedSimpleProfitableUser(985641, 885641, time() - 3600, 'Deleted MT4 CRM', 'Deleted MT4', '2026-08-18 12:00:00');
        DB::table('mt4_users')->where('login', 885641)->update(['deleted_at' => time()]);

        foreach ([985640, 985641] as $userId) {
            $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', ['user_id' => $userId])
                ->assertOk()
                ->assertJsonPath('data.records.total', 0)
                ->assertJsonPath('data.records.data', []);
        }
    }

    public function test_profit_aggregate_excludes_soft_deleted_trades(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 985650;
        $this->seedSimpleProfitableUser($userId, 885650, time() - 3600, 'Trade CRM', 'Trade MT4', '2026-08-18 12:00:00');
        $this->seedTrade(9956502, 0, -1000, -500, -200, 100, 1, true, 'soft deleted loss', $userId);
        DB::table('user_trades')->where('ticket', 9956502)->update(['deleted_at' => time()]);

        $response = $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', ['user_id' => $userId]);

        $response->assertOk()
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.total_profit', '10.00')
            ->assertJsonPath('data.records.data.0.total_comm', '1.00')
            ->assertJsonPath('data.records.data.0.total_swaps', '0.00');
    }

    public function test_profit_excludes_auth_status_three_users(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 985660;
        $this->seedSimpleProfitableUser($userId, 885660, time() - 3600, 'Disabled CRM', 'Disabled MT4', '2026-08-18 12:00:00');
        DB::table('user_infos')->where('user_id', $userId)->update(['auth_status' => 3]);

        $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('data.records.total', 0)
            ->assertJsonPath('data.records.data', []);
    }

    public function test_profit_defaults_missing_user_auth_statuses_to_zero(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 985670;
        $this->seedSimpleProfitableUser($userId, 885670, time() - 3600, 'No auth CRM', 'No auth MT4', '2026-08-18 12:00:00');

        $this->assertFalse(DB::table('user_auths')->where('user_id', $userId)->exists());
        $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.IDcard_status', 0)
            ->assertJsonPath('data.records.data.0.bank_status', 0);
    }

    public function test_profit_excludes_equal_fee_and_negative_profit_users(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedSimpleProfitableUser(985680, 885680, time() - 3600, 'Equal fee CRM', 'Equal fee MT4', '2026-08-18 12:00:00');
        DB::table('user_trades')->where('user_id', 985680)->update(['profit' => 10, 'commission' => -10]);

        $this->seedSimpleProfitableUser(985681, 885681, time() - 3600, 'Negative CRM', 'Negative MT4', '2026-08-18 12:00:00');
        DB::table('user_trades')->where('user_id', 985681)->update(['profit' => -1, 'commission' => 0]);

        foreach ([985680, 985681] as $userId) {
            $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', ['user_id' => $userId])
                ->assertOk()
                ->assertJsonPath('data.records.total', 0)
                ->assertJsonPath('data.records.data', []);
        }
    }

    public function test_profitable_user_without_deposit_has_zero_risk_percentage(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 985690;
        $this->seedSimpleProfitableUser($userId, 885690, time() - 3600, 'No deposit CRM', 'No deposit MT4', '2026-08-18 12:00:00');

        $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.total_yuerj', '0.00')
            ->assertJsonPath('data.records.data.0.feng_xian_val', '0.00');
    }

    public function test_profit_classifies_the_complete_legacy_deposit_and_withdrawal_comment_codes(): void
    {
        $admin = $this->ensureSuperAdmin();
        $this->seedUserAndMt4Account(time() - 3600);
        $this->seedTrade(9957000, 0, 10, 0, 0, 100, 1, true, 'eligible closed trade');

        $depositCodes = ['DBAA', 'DBCT', 'DBGN', 'DBMN', 'DBPA', 'DBPN', 'DBSN', 'DBTN', 'DBUN', 'DBZN', 'DBAD', 'WBIR'];
        foreach ($depositCodes as $offset => $code) {
            $this->seedTrade(9957010 + $offset, 6, 1, 0, 0, 0, 0, true, 'prefix-' . $code . '-deposit');
        }

        $withdrawalCodes = ['WBAA', 'WBCN', 'WBCT', 'WBHN', 'WBIN', 'WBPN', 'WBSN', 'WBTN', 'WBAD', 'DBZR'];
        foreach ($withdrawalCodes as $offset => $code) {
            $this->seedTrade(9957030 + $offset, 6, -1, 0, 0, 0, 0, true, 'prefix-' . $code . '-withdrawal');
        }

        $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers', ['user_id' => self::USER_ID])
            ->assertOk()
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.total_yuerj', '12.00')
            ->assertJsonPath('data.records.data.0.total_yuecj', '-10.00')
            ->assertJsonPath('data.records.data.0.total_net_worth', '2.00');
    }

    /** @param array<string, mixed>|null $row */
    private function assertProfitRow(?array $row, int $registeredAt): void
    {
        $this->assertIsArray($row);
        $this->assertSame(self::USER_ID, $row['user_id'] ?? null);
        $this->assertSame('Profit risk CRM user', $row['user_name'] ?? null);
        $this->assertSame(771001, $row['parent_id'] ?? null);
        $this->assertSame(1, $row['trans_mode'] ?? null);
        $this->assertSame(self::MT4_LOGIN, $row['mt4_code'] ?? null);
        $this->assertSame('321.45', $row['cust_eqy'] ?? null);
        $this->assertSame('PROFIT-LIVE', $row['mt4_grp'] ?? null);
        $this->assertSame(1, $row['user_status'] ?? null);
        $this->assertSame(1, $row['voided'] ?? null);
        $this->assertSame(2, $row['IDcard_status'] ?? null);
        $this->assertSame(4, $row['bank_status'] ?? null);
        $this->assertSame(self::MT4_LOGIN, $row['mt4_login'] ?? null);
        $this->assertSame('Profit risk MT4 user', $row['mt4_name'] ?? null);
        $this->assertSame('1200.10', $row['mt4_balance'] ?? null);
        $this->assertSame('1300.20', $row['mt4_equity'] ?? null);
        $this->assertSame(date('Y-m-d H:i:s', $registeredAt), $row['mt4_regdate'] ?? null);
        $this->assertSame('8.00', $row['total_comm'] ?? null);
        $this->assertSame('200.00', $row['total_yuerj'] ?? null);
        $this->assertSame('-50.00', $row['total_yuecj'] ?? null);
        $this->assertSame('2.00', $row['total_volume'] ?? null);
        $this->assertSame('3.00', $row['total_swaps'] ?? null);
        $this->assertSame('150.00', $row['total_profit'] ?? null);
        $this->assertSame('150.00', $row['total_net_worth'] ?? null);
        $this->assertSame('71.00', $row['feng_xian_val'] ?? null);
    }

    private function asAdmin(Admin $admin): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'risk-profit-parity-super',
                'email' => 'risk-profit-parity-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function profitRequestQueryCount(Admin $admin): int
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $this->asAdmin($admin)->postJson('/api/admin/riskProfitUsers')->assertOk();

            return count($connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
        }
    }

    private function createRestrictedAdmin(int $userId): Admin
    {
        $now = time();
        $roleId = 985390;
        $adminId = 985390;
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            [
                'name' => 'risk_profit_scope',
                'guard_type' => 'admin',
                'description' => 'Profit risk scope test',
                'permissions' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_data_scopes')->updateOrInsert(
            ['role_id' => $roleId],
            [
                'scope_type' => 'custom_users',
                'agent_ids' => null,
                'user_ids' => json_encode([$userId]),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'role_id' => (string) $roleId,
                'username' => 'risk-profit-scope-admin',
                'email' => 'risk-profit-scope-admin@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function seedUserAndMt4Account(int $registeredAt): void
    {
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => self::USER_ID],
            [
                'login_id' => 0,
                'user_name' => 'Profit risk CRM user',
                'parent_id' => 771001,
                'account_type' => 2,
                'family_tree' => '771001,' . self::USER_ID,
                'equity' => 321.45,
                'auth_status' => 1,
                'is_mt4_synced' => 1,
                'mt4_group' => 'PROFIT-LIVE',
                'mt4_code' => self::MT4_LOGIN,
                'trading_mode' => 1,
                'created_at' => $registeredAt,
                'updated_at' => time(),
                'deleted_at' => null,
            ]
        );
        DB::table('user_auths')->updateOrInsert(
            ['user_id' => self::USER_ID],
            [
                'id_card_status' => 2,
                'bank_status' => 4,
                'created_at' => $registeredAt,
                'updated_at' => time(),
                'deleted_at' => null,
            ]
        );
        DB::table('mt4_users')->updateOrInsert(
            ['login' => self::MT4_LOGIN],
            [
                'name' => 'Profit risk MT4 user',
                'group' => 'PROFIT-LIVE',
                'balance' => '1200.10',
                'equity' => '1300.20',
                'margin' => '20.00',
                'margin_free' => '1280.20',
                'leverage' => 100,
                'created_at' => $registeredAt,
                'updated_at' => time(),
                'deleted_at' => null,
            ]
        );
    }

    private function seedTrade(
        int $ticket,
        int $cmd,
        float $profit,
        float $commission,
        float $swaps,
        int $volume,
        float $marginRate,
        bool $closed,
        string $comment,
        int $userId = self::USER_ID
    ): void {
        $now = time();
        DB::table('user_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'user_id' => $userId,
                'symbol' => 'PROFIT',
                'digits' => 2,
                'cmd' => $cmd,
                'volume' => $volume,
                'open_time' => date('Y-m-d H:i:s', $now - 7200),
                'open_price' => 100,
                'stop_loss' => 0,
                'take_profit' => 0,
                'close_time' => $closed ? date('Y-m-d H:i:s', $now - 60) : '1970-01-01 00:00:00',
                'commission' => $commission,
                'swaps' => $swaps,
                'close_price' => $closed ? 101 : 0,
                'profit' => $profit,
                'margin_rate' => $marginRate,
                'comment' => $comment,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function seedSimpleProfitableUser(
        int $userId,
        int $mt4Login,
        int $registeredAt,
        string $crmName,
        string $mt4Name,
        string $tradeClosedAt
    ): void {
        $now = time();
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $crmName,
                'parent_id' => 0,
                'account_type' => 2,
                'family_tree' => (string) $userId,
                'equity' => 100,
                'auth_status' => 1,
                'is_mt4_synced' => 1,
                'mt4_group' => 'PROFIT-LIVE',
                'mt4_code' => $mt4Login,
                'created_at' => $registeredAt,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('mt4_users')->updateOrInsert(
            ['login' => $mt4Login],
            [
                'name' => $mt4Name,
                'group' => 'PROFIT-LIVE',
                'balance' => '100.00',
                'equity' => '110.00',
                'margin' => '0.00',
                'margin_free' => '110.00',
                'leverage' => 100,
                'created_at' => $registeredAt,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('user_trades')->updateOrInsert(
            ['ticket' => 9000000 + $userId],
            [
                'user_id' => $userId,
                'symbol' => 'PROFIT',
                'digits' => 2,
                'cmd' => 0,
                'volume' => 100,
                'open_time' => date('Y-m-d H:i:s', strtotime($tradeClosedAt) - 3600),
                'open_price' => 100,
                'stop_loss' => 0,
                'take_profit' => 0,
                'close_time' => $tradeClosedAt,
                'commission' => -1,
                'swaps' => 0,
                'close_price' => 101,
                'profit' => 10,
                'margin_rate' => 1,
                'comment' => 'simple profitable risk user',
                'modify_time' => $tradeClosedAt,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
