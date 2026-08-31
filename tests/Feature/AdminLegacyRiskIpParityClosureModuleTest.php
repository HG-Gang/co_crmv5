<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 00:03
 */

/**
 * AdminLegacyRiskIpParityClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台风控 IP 列表与明细双口径等价：rows/total 与业务用户去重、登录 IP 搜索 trim、管理范围作用于行与计数、明细每业务用户一行且大额合计保留 JSON 字符串。
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

final class AdminLegacyRiskIpParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 异常 IP 列表对账用的固定日期（2026-08-18）。夹具流水都落在此日期，验证新旧接口同日同结果。
     * @var string
     */
    private const LIST_DATE = '2026-08-18';

    public function test_legacy_list_returns_rows_total_and_only_distinct_business_user_risks(): void
    {
        $admin = $this->ensureSuperAdmin();
        $singleUserIp = '198.18.40.10';
        $sharedIp = '198.18.40.20';

        $this->seedUser(985401, 'IP parity repeated user', 885401);
        $this->seedUser(985402, 'IP parity shared user', 885402);
        $this->seedLoginLog(985401, $singleUserIp, 'Repeated network', '2026-08-18 08:00:00');
        $this->seedLoginLog(985401, $singleUserIp, 'Repeated network', '2026-08-18 09:00:00');
        $this->seedLoginLog(985401, $sharedIp, 'Shared network', '2026-08-18 10:00:00');
        $this->seedLoginLog(985402, $sharedIp, 'Shared network', '2026-08-18 10:30:00');

        $response = $this->asAdmin($admin)->postJson('/index/admin/fengXian/IpaddressSearch', [
            'login_ip' => '198.18.40',
            'startdate' => self::LIST_DATE,
            'enddate' => self::LIST_DATE,
            'rows' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.login_ip', $sharedIp)
            ->assertJsonPath('rows.0.login_count', 2);

        $rows = $response->json('rows');
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) ($rows[0]['distinct_user_count'] ?? 0));
        foreach (['sys_id', 'login_id', 'login_name', 'login_ip', 'login_id_desc', 'login_count'] as $field) {
            $this->assertArrayHasKey($field, $rows[0]);
        }
        $this->assertStringNotContainsString($singleUserIp, $response->getContent());
    }

    public function test_legacy_list_keeps_sample_user_id_and_name_from_the_same_user(): void
    {
        $admin = $this->ensureSuperAdmin();
        $ip = '198.18.40.30';
        $lowerUserId = 985403;
        $higherUserId = 985404;

        $this->seedUser($lowerUserId, 'Zulu paired user', 885403);
        $this->seedUser($higherUserId, 'Alpha other user', 885404);
        $this->seedLoginLog($lowerUserId, $ip, 'Paired network', '2026-08-18 10:40:00');
        $this->seedLoginLog($higherUserId, $ip, 'Paired network', '2026-08-18 10:45:00');

        $this->asAdmin($admin)
            ->postJson('/index/admin/fengXian/IpaddressSearch', [
                'login_ip' => $ip,
                'startdate' => self::LIST_DATE,
                'enddate' => self::LIST_DATE,
            ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.login_id', $lowerUserId)
            ->assertJsonPath('rows.0.login_name', 'Zulu paired user');
    }

    public function test_modern_list_trims_a_valid_login_ip_search_before_querying(): void
    {
        $admin = $this->ensureSuperAdmin();
        $ip = '198.18.40.40';

        $this->seedUser(985405, 'IP trim first', 885405);
        $this->seedUser(985406, 'IP trim second', 885406);
        $this->seedLoginLog(985405, $ip, 'Trim network', '2026-08-18 10:50:00');
        $this->seedLoginLog(985406, $ip, 'Trim network', '2026-08-18 10:55:00');

        $this->asAdmin($admin)
            ->postJson('/api/admin/riskIpList', [
                'login_ip' => '  ' . $ip . '  ',
                'start_date' => self::LIST_DATE,
                'end_date' => self::LIST_DATE,
            ])
            ->assertOk()
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.login_ip', $ip);
    }

    public function test_legacy_user_filter_keeps_the_whole_risk_group_before_filtering_matching_ips(): void
    {
        $admin = $this->ensureSuperAdmin();
        $targetIp = '198.18.41.10';
        $otherIp = '198.18.41.20';

        foreach ([985411, 985412, 985413, 985414] as $offset => $userId) {
            $this->seedUser($userId, 'IP parity filter user ' . $userId, 885411 + $offset);
        }
        $this->seedLoginLog(985411, $targetIp, 'Target network', '2026-08-18 10:00:00');
        $this->seedLoginLog(985412, $targetIp, 'Target network', '2026-08-18 10:05:00');
        $this->seedLoginLog(985413, $otherIp, 'Other network', '2026-08-18 10:10:00');
        $this->seedLoginLog(985414, $otherIp, 'Other network', '2026-08-18 10:15:00');

        $response = $this->asAdmin($admin)->postJson('/index/admin/fengXian/IpaddressSearch', [
            'userId' => 985411,
            'startdate' => self::LIST_DATE,
            'enddate' => self::LIST_DATE,
        ]);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.login_ip', $targetIp)
            ->assertJsonPath('rows.0.login_count', 2);
        $this->assertStringNotContainsString($otherIp, $response->getContent());
    }

    public function test_legacy_list_applies_admin_scope_to_rows_and_risk_counts(): void
    {
        $visibleUserIds = [985421, 985422];
        $outsideUserId = 985423;
        $admin = $this->seedRestrictedAdmin($visibleUserIds, 'ip-list');
        $ip = '198.18.42.10';

        foreach (array_merge($visibleUserIds, [$outsideUserId]) as $offset => $userId) {
            $this->seedUser($userId, 'IP parity scoped list ' . $userId, 885421 + $offset);
            $this->seedLoginLog($userId, $ip, 'Scoped list network', '2026-08-18 11:0' . $offset . ':00');
        }

        $response = $this->asAdmin($admin)->postJson('/index/admin/fengXian/IpaddressSearch', [
            'login_ip' => $ip,
            'startdate' => self::LIST_DATE,
            'enddate' => self::LIST_DATE,
        ]);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.login_ip', $ip)
            ->assertJsonPath('rows.0.login_count', 2)
            ->assertJsonPath('rows.0.distinct_user_count', 2);
    }

    public function test_legacy_v1_empty_result_uses_an_empty_string_for_rows(): void
    {
        $admin = $this->ensureSuperAdmin();

        $this->asAdmin($admin)
            ->postJson('/index/admin/fengXian/IpaddressSearch', [
                'login_ip' => '198.18.255.254',
                'startdate' => self::LIST_DATE,
                'enddate' => self::LIST_DATE,
            ])
            ->assertOk()
            ->assertJsonPath('rows', '')
            ->assertJsonPath('total', 0);
    }

    /**
     * @dataProvider invalidListFilterProvider
     *
     * @param array<string, mixed> $payload
     */
    public function test_legacy_and_modern_lists_reject_invalid_filters(
        string $surface,
        array $payload
    ): void {
        $admin = $this->ensureSuperAdmin();
        $uri = $surface === 'legacy'
            ? '/index/admin/fengXian/IpaddressSearch'
            : '/api/admin/riskIpList';

        $this->asAdmin($admin)
            ->postJson($uri, $payload)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public function invalidListFilterProvider(): array
    {
        return [
            'legacy userId array' => ['legacy', ['userId' => [985401]]],
            'legacy startdate array' => ['legacy', ['startdate' => [self::LIST_DATE]]],
            'legacy enddate array' => ['legacy', ['enddate' => [self::LIST_DATE]]],
            'modern user_id array' => ['modern', ['user_id' => [985401]]],
            'modern user_id object' => ['modern', ['user_id' => (object) ['value' => 985401]]],
            'modern login_ip array' => ['modern', ['login_ip' => ['198.18.40.20']]],
            'modern invalid login_ip' => ['modern', ['login_ip' => '999.18.40.20']],
            'modern min_user_count array' => ['modern', ['min_user_count' => [2]]],
            'legacy zero userId' => ['legacy', ['userId' => 0]],
            'legacy negative userId' => ['legacy', ['userId' => -1]],
            'modern zero user_id' => ['modern', ['user_id' => 0]],
            'modern negative user_id' => ['modern', ['user_id' => -1]],
            'minimum user count zero' => ['modern', ['min_user_count' => 0]],
            'minimum user count one' => ['legacy', ['min_user_count' => 1]],
            'minimum user count negative' => ['modern', ['min_user_count' => -2]],
            'legacy impossible start date' => ['legacy', ['startdate' => '2026-02-30']],
            'legacy invalid end date' => ['legacy', ['enddate' => '18-08-2026']],
            'modern invalid start date' => ['modern', ['start_date' => 'not-a-date']],
            'modern invalid end date' => ['modern', ['end_date' => '2026/08/18']],
            'legacy reversed dates' => ['legacy', ['startdate' => '2026-08-19', 'enddate' => '2026-08-18']],
            'modern reversed dates' => ['modern', ['start_date' => '2026-08-19', 'end_date' => '2026-08-18']],
        ];
    }

    public function test_legacy_detail_decodes_ip_and_returns_one_legacy_row_per_business_user(): void
    {
        $admin = $this->ensureSuperAdmin();
        $ip = '203.0.113.44';
        $firstUserId = 985441;
        $secondUserId = 985442;

        $this->seedUser($firstUserId, 'IP parity detail first', 885441, '2026-08-01 09:00:00');
        $this->seedUser($secondUserId, 'IP parity detail second', 885442, '2026-08-02 09:00:00');
        $this->seedLoginLog($firstUserId, $ip, 'Detail network', '2026-08-18 10:00:00');
        $this->seedLoginLog($firstUserId, $ip, 'Detail network', '2026-08-18 11:00:00');
        $this->seedLoginLog($secondUserId, $ip, 'Detail network', '2026-08-18 10:30:00');
        $this->seedTrade(9954411, 885441, false);
        $this->seedTrade(9954412, 885441, true);
        $this->seedDeposit($firstUserId, '125.50');
        $this->seedWithdraw($firstUserId, '25.25');

        $response = $this->asAdmin($admin)
            ->getJson('/index/admin/fengXian/IpaddressDeatail/203_0_113_44');

        $response->assertOk()->assertJsonPath('total', 2);
        $rows = $response->json('rows');
        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $this->assertCount(2, array_unique(array_map('intval', array_column($rows, 'login_id'))));

        $firstRow = collect($rows)->firstWhere('login_id', $firstUserId);
        $this->assertIsArray($firstRow);
        $this->assertSame('IP parity detail first', $firstRow['login_name'] ?? null);
        $this->assertSame($ip, $firstRow['login_ip'] ?? null);
        $this->assertSame('Detail network', $firstRow['login_id_desc'] ?? null);
        $this->assertSame(2, (int) ($firstRow['login_count'] ?? 0));
        $this->assertSame('2026-08-18 11:00:00', $firstRow['login_date'] ?? null);
        $this->assertSame('2026-08-01 09:00:00', $firstRow['rec_crt_date'] ?? null);
        $this->assertSame(1, (int) ($firstRow['open'] ?? -1));
        $this->assertSame(1, (int) ($firstRow['close'] ?? -1));
        $this->assertSame('125.50', $firstRow['amount_rj'] ?? null);
        $this->assertSame('25.25', $firstRow['amount_cj'] ?? null);
    }

    public function test_legacy_detail_applies_admin_scope_before_returning_rows_and_totals(): void
    {
        $visibleUserId = 985451;
        $outsideUserId = 985452;
        $admin = $this->seedRestrictedAdmin([$visibleUserId], 'ip-detail');
        $ip = '203.0.113.45';

        $this->seedUser($visibleUserId, 'IP parity visible detail', 885451);
        $this->seedUser($outsideUserId, 'IP parity outside detail', 885452);
        $this->seedLoginLog($visibleUserId, $ip, 'Scoped detail network', '2026-08-18 12:00:00');
        $this->seedLoginLog($outsideUserId, $ip, 'Scoped detail network', '2026-08-18 12:05:00');

        $response = $this->asAdmin($admin)
            ->getJson('/index/admin/fengXian/IpaddressDeatail/203_0_113_45');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.login_id', $visibleUserId)
            ->assertJsonPath('rows.0.login_name', 'IP parity visible detail');
        $this->assertStringNotContainsString('IP parity outside detail', $response->getContent());
    }

    public function test_legacy_detail_preserves_large_deposit_and_withdraw_totals_as_json_strings(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 985461;
        $ip = '203.0.113.46';
        $deposit = '9000000000000.12';
        $withdraw = '8765432109876.34';

        $this->seedUser($userId, 'IP parity large amount', 885461);
        $this->seedLoginLog($userId, $ip, 'Large amount network', '2026-08-18 13:00:00');
        $this->seedDeposit($userId, $deposit);
        $this->seedWithdraw($userId, $withdraw);

        $response = $this->asAdmin($admin)
            ->getJson('/index/admin/fengXian/IpaddressDeatail/203_0_113_46');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.amount_rj', $deposit)
            ->assertJsonPath('rows.0.amount_cj', $withdraw);
        $this->assertIsString($response->json('rows.0.amount_rj'));
        $this->assertIsString($response->json('rows.0.amount_cj'));
    }

    /**
     * @dataProvider invalidDetailFilterProvider
     *
     * @param array<string, mixed> $payload
     */
    public function test_legacy_and_modern_details_reject_invalid_filters(
        string $surface,
        array $payload
    ): void {
        $admin = $this->ensureSuperAdmin();
        if ($surface === 'legacy') {
            $query = http_build_query($payload);
            $response = $this->asAdmin($admin)->getJson(
                '/index/admin/fengXian/IpaddressDeatail/203_0_113_200' . ($query === '' ? '' : '?' . $query)
            );
        } else {
            $response = $this->asAdmin($admin)->postJson('/api/admin/riskIpDetail', array_replace([
                'login_ip' => '203.0.113.200',
            ], $payload));
        }

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public function invalidDetailFilterProvider(): array
    {
        return [
            'legacy userId array' => ['legacy', ['userId' => [985441]]],
            'legacy startdate array' => ['legacy', ['startdate' => [self::LIST_DATE]]],
            'legacy enddate array' => ['legacy', ['enddate' => [self::LIST_DATE]]],
            'modern login_ip array' => ['modern', ['login_ip' => ['203.0.113.200']]],
            'modern login_ip object' => ['modern', ['login_ip' => (object) ['value' => '203.0.113.200']]],
            'modern invalid login_ip' => ['modern', ['login_ip' => '999.0.113.200']],
            'modern user_id array' => ['modern', ['user_id' => [985441]]],
            'modern start_date array' => ['modern', ['start_date' => [self::LIST_DATE]]],
            'modern end_date array' => ['modern', ['end_date' => [self::LIST_DATE]]],
            'legacy zero userId' => ['legacy', ['userId' => 0]],
            'legacy negative userId' => ['legacy', ['userId' => -1]],
            'modern zero user_id' => ['modern', ['user_id' => 0]],
            'modern negative user_id' => ['modern', ['user_id' => -1]],
            'legacy impossible start date' => ['legacy', ['startdate' => '2026-02-30']],
            'legacy invalid end date' => ['legacy', ['enddate' => '18-08-2026']],
            'modern invalid start date' => ['modern', ['start_date' => 'not-a-date']],
            'modern invalid end date' => ['modern', ['end_date' => '2026/08/18']],
            'legacy reversed dates' => ['legacy', ['startdate' => '2026-08-19', 'enddate' => '2026-08-18']],
            'modern reversed dates' => ['modern', ['start_date' => '2026-08-19', 'end_date' => '2026-08-18']],
        ];
    }

    /** @dataProvider invalidLegacyDetailIpProvider */
    public function test_legacy_detail_rejects_malformed_ip_route_values(string $idaddr): void
    {
        $admin = $this->ensureSuperAdmin();

        $this->asAdmin($admin)
            ->getJson('/index/admin/fengXian/IpaddressDeatail/' . $idaddr)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /** @return array<string, array{string}> */
    public function invalidLegacyDetailIpProvider(): array
    {
        return [
            'malformed IPv4' => ['999_168_1_1'],
            'malformed IPv6' => ['2001_db8__1'],
        ];
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
                'username' => 'risk-ip-parity-super',
                'email' => 'risk-ip-parity-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /** @param array<int, int> $visibleUserIds */
    private function seedRestrictedAdmin(array $visibleUserIds, string $suffix): Admin
    {
        $now = time();
        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => uniqid('risk-ip-' . $suffix . '-', true),
            'guard_type' => 'admin',
            'description' => 'Legacy risk IP parity scope fixture',
            'permissions' => null,
            'status' => 1,
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
        $adminId = (int) DB::table('admins')->insertGetId([
            'role_id' => $roleId,
            'username' => uniqid('risk-ip-' . $suffix . '-admin-', true),
            'email' => uniqid('risk-ip-' . $suffix . '-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    private function seedUser(
        int $userId,
        string $userName,
        int $mt4Login,
        string $createdAt = '2026-08-01 09:00:00'
    ): void {
        $createdTimestamp = strtotime($createdAt);
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'mt4_code' => $mt4Login,
                'mt4_group' => 'RISK-IP-LIVE',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $createdTimestamp,
                'updated_at' => time(),
                'deleted_at' => null,
            ]
        );
    }

    private function seedLoginLog(int $userId, string $ip, string $location, string $createdAt): void
    {
        $timestamp = strtotime($createdAt);
        DB::table('user_login_logs')->insert([
            'login_id' => $userId,
            'user_id' => $userId,
            'login_ip' => $ip,
            'ip_location' => $location,
            'user_agent' => 'Legacy risk IP parity browser',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
    }

    private function seedTrade(int $ticket, int $login, bool $closed): void
    {
        $openTime = strtotime('2026-08-18 08:00:00');
        DB::table('mt4_trades')->updateOrInsert(
            ['ticket' => $ticket],
            [
                'login' => $login,
                'symbol' => 'RISKIP',
                'cmd' => 0,
                'volume' => 100,
                'open_price' => 100,
                'close_price' => $closed ? 101 : null,
                'commission' => '-1.00',
                'swaps' => '0.00',
                'profit' => '10.00',
                'open_time' => $openTime,
                'close_time' => $closed ? strtotime('2026-08-18 09:00:00') : 0,
                'comment' => 'legacy risk IP parity',
                'modify_time' => strtotime('2026-08-18 09:00:00'),
                'created_at' => $openTime,
                'updated_at' => $openTime,
            ]
        );
    }

    private function seedDeposit(int $userId, string $amount): void
    {
        $now = strtotime('2026-08-18 08:00:00');
        DB::table('deposit_records')->insert([
            'user_id' => $userId,
            'user_name' => 'risk-ip-deposit-' . $userId,
            'mt4_ticket' => 0,
            'amount' => $amount,
            'actual_amount' => $amount,
            'exchange_rate' => '1.00000000',
            'channel_name' => 'risk-ip-test',
            'channel_order_no' => '',
            'local_order_no' => 'RISK-IP-DEP-' . $userId,
            'status' => 'success',
            'payment_time' => '2026-08-18 08:00:00',
            'remarks' => 'Legacy risk IP parity',
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedWithdraw(int $userId, string $amount): void
    {
        $now = strtotime('2026-08-18 08:30:00');
        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => 'risk-ip-withdraw-' . $userId,
            'mt4_ticket' => 'RISK-IP-' . $userId,
            'apply_amount' => $amount,
            'actual_amount' => $amount,
            'fee' => '0.00',
            'exchange_rate' => '1.00000000',
            'rmb_fee' => '0.00',
            'bank_no' => '62220000' . $userId,
            'bank_name' => 'Risk IP Test Bank',
            'bank_addr' => 'Test Branch',
            'status' => 2,
            'local_order_no' => 'RISK-IP-WD-' . $userId,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => 'RISK-IP-WD-' . $userId,
            'funding_status' => 'completed',
            'funding_payload_hash' => hash('sha256', 'RISK-IP-WD-' . $userId),
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
