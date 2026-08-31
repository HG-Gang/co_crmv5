<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 01:21
 */

/**
 * AdminLegacyCustListSearchClosureTest
 *
 * 文件功能：
 * - 验证旧后台客户列表搜索闭环：V1/V2 旧契约与基础别名、交易账号/证件尾号模糊匹配、旧筛选别名、稳定分页排序、非法筛选显式校验失败，以及客户数据范围失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLegacyCustListSearchClosureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_v1_returns_legacy_rows_for_customers_without_agents(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $customerId = 985401;
        $agentId = 985402;
        $sharedName = 'legacy customer list account type fixture';
        $this->seedUser($customerId, $sharedName, 2);
        $this->seedUser($agentId, $sharedName, 1);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearch', [
                'username' => $sharedName,
                'page' => 1,
                'rows' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.user_id', $customerId)
            ->assertJsonCount(1, 'footer');

        $this->assertStringNotContainsString((string) $agentId, $response->getContent());
    }

    public function test_v2_returns_legacy_contract_and_complete_base_aliases(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 985403;
        $createdAt = strtotime('2026-08-10 12:34:56');
        $this->seedUser($userId, 'legacy v2 base aliases', 2, [
            'parent_id' => 900001,
            'trading_mode' => 2,
            'mt4_code' => 403,
            'total_funds' => 123.45,
            'equity' => 118.76,
            'mt4_group' => 'demo\standard',
            'auth_status' => 3,
            'is_mt4_synced' => 1,
            'risk_ratio' => 48.126,
            'created_at' => $createdAt,
        ]);
        $this->seedAuth($userId, '11010519491231002X', 2, 1);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', [
                'userId' => '5403',
                'page' => 1,
                'limit' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.user_id', $userId)
            ->assertJsonPath('data.0.user_name', 'legacy v2 base aliases')
            ->assertJsonPath('data.0.parent_id', 900001)
            ->assertJsonPath('data.0.trans_mode', 2)
            ->assertJsonPath('data.0.mt4_code', 403)
            ->assertJsonPath('data.0.user_money', '123.45')
            ->assertJsonPath('data.0.cust_eqy', '118.76')
            ->assertJsonPath('data.0.mt4_grp', 'demo\standard')
            ->assertJsonPath('data.0.user_status', 3)
            ->assertJsonPath('data.0.voided', 1)
            ->assertJsonPath('data.0.IDcard_status', 2)
            ->assertJsonPath('data.0.bank_status', 1)
            ->assertJsonPath('data.0.rec_crt_date', '2026-08-10 12:34:56')
            ->assertJsonPath('data.0.mt4_login', $userId)
            ->assertJsonPath('data.0.mt4_name', 'legacy v2 base aliases')
            ->assertJsonPath('data.0.mt4_balance', '123.45')
            ->assertJsonPath('data.0.mt4_equity', '118.76')
            ->assertJsonPath('data.0.mt4MarginLevel', '48.13')
            ->assertJsonPath('totalRow.mt4_login', trans('systemlanguage.total'));

        $body = $response->json();
        $this->assertIsArray($body['data']);
        $this->assertIsArray($body['totalRow']);
        $this->assertFalse(array_is_list($body['totalRow']));
    }

    public function test_both_versions_return_stable_empty_arrays_and_zero_summary(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $v1 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearch', ['userId' => '999999999'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0)
            ->assertJsonCount(1, 'footer')
            ->assertJsonPath('footer.0.total_yuerj', '0.00');

        $this->assertIsArray($v1->json('rows'));

        $v2 = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', ['userId' => '999999999'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow.total_yuerj', '0.00');

        $this->assertIsArray($v2->json('data'));
        $this->assertFalse(array_is_list($v2->json('totalRow')));
    }

    public function test_user_id_filter_fuzzily_matches_trading_account_or_id_card_tail(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $accountMatchId = 985410;
        $idCardMatchId = 985411;
        $this->seedUser($accountMatchId, 'account fuzzy match', 2);
        $this->seedUser($idCardMatchId, 'id card fuzzy match', 2);
        $this->seedAuth($idCardMatchId, '11010519491231002X', 2, 2);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearch', ['userId' => '5410'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.user_id', $accountMatchId);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', ['user_id' => '002x'])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.user_id', $idCardMatchId);
    }

    public function test_name_status_and_created_date_filters_use_legacy_aliases(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = 985412;
        $wrongStatusId = 985413;
        $outsideDateId = 985414;
        $name = 'legacy combined filter fixture';
        $this->seedUser($targetId, $name . ' target', 2, [
            'auth_status' => 3,
            'created_at' => strtotime('2026-08-08 10:00:00'),
        ]);
        $this->seedUser($wrongStatusId, $name . ' wrong status', 2, [
            'auth_status' => 2,
            'created_at' => strtotime('2026-08-08 11:00:00'),
        ]);
        $this->seedUser($outsideDateId, $name . ' outside date', 2, [
            'auth_status' => 3,
            'created_at' => strtotime('2026-08-01 10:00:00'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', [
                'username' => 'combined filter',
                'userstatus' => 4,
                'startdate' => '2026-08-08',
                'enddate' => '2026-08-09',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.user_id', $targetId);

        $this->assertStringNotContainsString((string) $wrongStatusId, $response->getContent());
        $this->assertStringNotContainsString((string) $outsideDateId, $response->getContent());
    }

    public function test_status_zero_through_two_are_supported_and_default_start_date_is_2024(): void
    {
        $admin = Admin::query()->findOrFail(1);
        foreach ([0, 1, 2] as $status) {
            $userId = 985420 + $status;
            $this->seedUser($userId, 'legacy status ' . $status . ' fixture', 2, [
                'auth_status' => $status,
                'created_at' => strtotime('2026-08-05 09:00:00'),
            ]);

            $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/cust/custListSearch', [
                    'user_name' => 'legacy status ' . $status . ' fixture',
                    'userstatus' => $status,
                ])
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('rows.0.user_id', $userId);
        }

        $oldUserId = 985429;
        $this->seedUser($oldUserId, 'legacy pre default date fixture', 2, [
            'created_at' => strtotime('2023-12-31 23:59:59'),
        ]);
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearch', [
                'user_name' => 'legacy pre default date fixture',
            ])
            ->assertOk()
            ->assertJsonPath('rows', [])
            ->assertJsonPath('total', 0);
    }

    public function test_rows_and_limit_pagination_use_stable_created_at_and_user_id_order(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $createdAt = strtotime('2026-08-12 10:00:00');
        $this->seedUser(985430, 'legacy stable pagination fixture', 2, ['created_at' => $createdAt]);
        $this->seedUser(985431, 'legacy stable pagination fixture', 2, ['created_at' => $createdAt]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearch', [
                'username' => 'legacy stable pagination fixture',
                'page' => 1,
                'rows' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.user_id', 985431);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', [
                'username' => 'legacy stable pagination fixture',
                'page' => 2,
                'limit' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', 985430);
    }

    public function test_invalid_filters_return_explicit_validation_failure(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $invalidPayloads = [
            ['userId' => 12.5],
            ['user_id' => '12.5'],
            ['userId' => '12%'],
            ['userId' => '12_'],
            ['userId' => "12' OR 1=1"],
            ['userId' => 'abc'],
            ['userId' => 'X123'],
            ['userstatus' => 3],
            ['userstatus' => 5],
            ['startdate' => '2026-02-30'],
            ['start_date' => 'not-a-date'],
            ['startdate' => '2026-08-10', 'enddate' => '2026-08-09'],
            ['page' => 0],
            ['rows' => 101],
            ['limit' => 0],
        ];

        foreach ($invalidPayloads as $payload) {
            $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/cust/custListSearchV2', $payload)
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    public function test_empty_legacy_date_strings_use_default_customer_opening_date_range(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 985460;
        $this->seedUser($userId, 'legacy empty date default fixture', 2, [
            'created_at' => strtotime('2026-08-10 10:00:00'),
        ]);

        foreach (['custListSearch', 'custListSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/cust/' . $action, [
                    'username' => 'legacy empty date default fixture',
                    'startdate' => '',
                    'enddate' => '',
                ])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            if ($action === 'custListSearch') {
                $response->assertJsonPath('total', 1)->assertJsonPath('rows.0.user_id', $userId);
            } else {
                $response->assertJsonPath('count', 1)->assertJsonPath('data.0.user_id', $userId);
            }
        }

        $serviceResult = app(\App\Services\LegacyAdminCustomerSearchService::class)->search([
            'user_name' => 'legacy empty date default fixture',
            'start_date' => '',
            'end_date' => '',
        ], $admin);
        $this->assertSame(1, $serviceResult['total']);
    }

    public function test_json_numeric_decimal_user_id_is_rejected_as_validation_failure(): void
    {
        $admin = Admin::query()->findOrFail(1);

        foreach (['custListSearch', 'custListSearchV2'] as $action) {
            $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/cust/' . $action, ['userId' => 12.5])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }

        foreach (['custListSearch', 'custListSearchV2'] as $action) {
            $response = $this->call(
                'POST',
                '/index/admin/cust/' . $action,
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                ],
                '{"userId":12.0}'
            );

            $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    public function test_rows_and_filtered_summary_use_full_history_local_trade_statistics(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $otherId = 985440;
        $targetId = 985441;
        $createdAt = strtotime('2026-08-10 10:00:00');
        $this->seedUser($otherId, 'legacy full history statistics fixture', 2, [
            'total_funds' => 77,
            'equity' => 80,
            'created_at' => $createdAt,
        ]);
        $this->seedUser($targetId, 'legacy full history statistics fixture', 2, [
            'total_funds' => 123,
            'equity' => 130,
            'created_at' => $createdAt,
        ]);
        $this->seedSymbol('XAU-CUST-STAT', 1);
        $this->seedTrade($targetId, 1, 6, 0, 1000, 0, 0, 'Deposit', '2020-01-01 12:00:00');
        $this->seedTrade($targetId, 2, 6, 0, -200, 0, 0, 'Withdrawal', '2020-01-02 12:00:00');
        $this->seedTrade($targetId, 3, 0, 250, 30, -5, -1.5, '', '2020-01-03 12:00:00', 'XAU-CUST-STAT');
        $this->seedTrade($otherId, 4, 6, 0, 500, 0, 0, 'Deposit', '2020-01-04 12:00:00');

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', [
                'username' => 'legacy full history statistics fixture',
                'startdate' => '2026-08-10',
                'enddate' => '2026-08-10',
                'page' => 1,
                'rows' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $targetId)
            ->assertJsonPath('data.0.total_comm', '5.00')
            ->assertJsonPath('data.0.total_yuerj', '1000.00')
            ->assertJsonPath('data.0.total_yuecj', '-200.00')
            ->assertJsonPath('data.0.total_volume', 2.5)
            ->assertJsonPath('data.0.total_swaps', '1.50')
            ->assertJsonPath('data.0.total_profit', '30.00')
            ->assertJsonPath('data.0.total_noble_metal', 2.5)
            ->assertJsonPath('data.0.total_for_exca', 0)
            ->assertJsonPath('data.0.total_crud_oil', 0)
            ->assertJsonPath('data.0.total_index', 0)
            ->assertJsonPath('data.0.total_currency', 0)
            ->assertJsonPath('data.0.total_stock', 0)
            ->assertJsonPath('data.0.total_net_worth', '800.00')
            ->assertJsonPath('totalRow.mt4_balance', '200.00')
            ->assertJsonPath('totalRow.mt4_equity', '210.00')
            ->assertJsonPath('totalRow.total_yuerj', '1500.00')
            ->assertJsonPath('totalRow.total_yuecj', '-200.00')
            ->assertJsonPath('totalRow.total_net_worth', '1300.00')
            ->assertJsonPath('totalRow.total_comm', '5.00')
            ->assertJsonPath('totalRow.total_profit', '30.00')
            ->assertJsonPath('totalRow.total_volume', 2.5)
            ->assertJsonPath('totalRow.total_swaps', '1.50');

        $row = $response->json('data.0');
        foreach ([
            'total_comm', 'total_yuerj', 'total_yuecj', 'total_volume', 'total_swaps',
            'total_profit', 'total_noble_metal', 'total_for_exca', 'total_crud_oil',
            'total_index', 'total_currency', 'total_stock', 'total_net_worth',
        ] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
    }

    public function test_both_versions_apply_custom_user_scope_to_rows_and_summary(): void
    {
        $visibleId = 985450;
        $hiddenId = 985451;
        $name = 'legacy scoped customer fixture';
        $this->seedUser($visibleId, $name . ' visible', 2, [
            'total_funds' => 50,
            'equity' => 55,
        ]);
        $this->seedUser($hiddenId, $name . ' hidden', 2, [
            'total_funds' => 900,
            'equity' => 950,
        ]);
        $admin = $this->seedRestrictedAdmin([$visibleId], true);

        foreach (['custListSearch', 'custListSearchV2'] as $action) {
            $response = $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/cust/' . $action, ['username' => $name])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::SUCCESS);

            if ($action === 'custListSearch') {
                $response
                    ->assertJsonPath('total', 1)
                    ->assertJsonPath('rows.0.user_id', $visibleId)
                    ->assertJsonPath('footer.0.mt4_balance', '50.00')
                    ->assertJsonPath('footer.0.mt4_equity', '55.00');
            } else {
                $response
                    ->assertJsonPath('count', 1)
                    ->assertJsonPath('data.0.user_id', $visibleId)
                    ->assertJsonPath('totalRow.mt4_balance', '50.00')
                    ->assertJsonPath('totalRow.mt4_equity', '55.00');
            }

            $this->assertStringNotContainsString((string) $hiddenId, $response->getContent());
            $this->assertStringNotContainsString('900.00', $response->getContent());
        }
    }

    public function test_ordinary_admin_without_data_scope_fails_closed(): void
    {
        $userId = 985452;
        $this->seedUser($userId, 'legacy missing scope fixture', 2);
        $admin = $this->seedRestrictedAdmin([], false);

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', [
                'username' => 'legacy missing scope fixture',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow.mt4_balance', '0.00');

        $this->assertStringNotContainsString((string) $userId, $response->getContent());
    }

    public function test_unknown_data_scope_type_fails_closed_without_sql_error(): void
    {
        $userId = 985453;
        $this->seedUser($userId, 'legacy unknown scope fixture', 2);
        $admin = $this->seedRestrictedAdmin([], true, 'unknown_scope_type');

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/custListSearchV2', [
                'username' => 'legacy unknown scope fixture',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow.mt4_balance', '0.00');

        $this->assertStringNotContainsString((string) $userId, $response->getContent());
    }

    /** @param array<string, mixed> $overrides */
    private function seedUser(int $userId, string $userName, int $accountType, array $overrides = []): void
    {
        $now = time();
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-cust-list-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => $accountType,
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

        DB::table('user_infos')->insert(array_replace([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '178000' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => '',
            'total_funds' => 0,
            'equity' => 0,
            'auth_status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $overrides));
    }

    private function seedAuth(
        int $userId,
        string $idCardNo,
        int $idCardStatus,
        int $bankStatus
    ): void {
        $now = time();
        DB::table('user_auths')->updateOrInsert(['user_id' => $userId], [
            'bank_no' => 'BANK-' . $userId,
            'bank_no_tmp' => '',
            'bank_name' => 'Test Bank',
            'bank_name_tmp' => '',
            'bank_card_img' => '',
            'bank_card_back_img' => '',
            'bank_card_img_tmp' => '',
            'bank_card_back_img_tmp' => '',
            'bank_addr' => 'Branch',
            'bank_addr_tmp' => '',
            'bank_status' => $bankStatus,
            'bank_remarks' => '',
            'id_card_no' => $idCardNo,
            'id_card_status' => $idCardStatus,
            'id_card_front' => '',
            'id_card_back' => '',
            'id_card_remarks' => '',
            'is_bank_synced' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedSymbol(string $symbol, int $groupId): void
    {
        $now = time();
        DB::table('symbol_prices')->where('symbol', $symbol)->delete();
        DB::table('symbol_prices')->insert([
            'symbol' => $symbol,
            'time' => '2026-08-10 00:00:00',
            'bid' => 1,
            'ask' => 1,
            'low' => 1,
            'high' => 1,
            'direction' => 0,
            'digits' => 2,
            'spread' => 0,
            'group_id' => $groupId,
            'status' => 1,
            'modify_time' => '2026-08-10 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /** @param array<int, int> $visibleUserIds */
    private function seedRestrictedAdmin(
        array $visibleUserIds,
        bool $withScope,
        string $scopeType = 'custom_users'
    ): Admin
    {
        $now = time();
        $token = uniqid('legacy-cust-scope-', true);
        $roleId = DB::table('roles')->insertGetId([
            'name' => $token,
            'guard_type' => 'admin',
            'description' => 'Legacy customer list scope test',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $permissionId = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('api_route', 'admin_api_userList')
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

        if ($withScope) {
            DB::table('role_data_scopes')->insert([
                'role_id' => $roleId,
                'scope_type' => $scopeType,
                'agent_ids' => null,
                'user_ids' => json_encode($visibleUserIds),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        $adminId = DB::table('admins')->insertGetId([
            'role_id' => $roleId,
            'username' => $token,
            'email' => $token . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    private function seedTrade(
        int $userId,
        int $ticketSuffix,
        int $cmd,
        int $volume,
        float $profit,
        float $commission,
        float $swaps,
        string $comment,
        string $closeTime,
        string $symbol = 'BALANCE'
    ): void {
        $now = time();
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => ($userId * 10) + $ticketSuffix,
            'symbol' => $symbol,
            'digits' => 2,
            'cmd' => $cmd,
            'volume' => $volume,
            'open_time' => $closeTime,
            'open_price' => 1,
            'close_time' => $closeTime,
            'close_price' => 1,
            'profit' => $profit,
            'commission' => $commission,
            'swaps' => $swaps,
            'comment' => $comment,
            'margin_rate' => $cmd <= 5 ? 1 : 0,
            'modify_time' => $closeTime,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
