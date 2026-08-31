<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 11:42
 */

/**
 * AdminLegacyWithdrawStatusSearchClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台出金状态搜索闭环：URI status 强制、rows/limit/per_page 分页优先级与默认 15、汇总尊重数据范围、默认日期窗口与空页契约、非法旧筛选拒绝与大额合计精确。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLegacyWithdrawStatusSearchClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @dataProvider statusSearchProvider
     */
    public function test_status_search_enforces_the_uri_status_and_returns_the_legacy_v2_envelope(
        string $action,
        int $status
    ): void {
        $admin = Admin::query()->findOrFail(1);
        $userId = 994301 + $status;
        $ticket = 'STATUS-MT4-' . $status;
        $otherStatus = ($status + 1) % 4;

        $this->seedWithdraw($userId, 'LOCAL-TARGET-' . $status, $ticket, $status, '2026-08-10 12:00:00');
        $this->seedWithdraw($userId, 'LOCAL-OTHER-STATUS-' . $status, $ticket, $otherStatus, '2026-08-10 12:00:00');
        $this->seedWithdraw($userId, $ticket, 'DECOY-MT4-' . $status, $status, '2026-08-10 12:00:00');
        $this->seedWithdraw($userId + 100, 'LOCAL-OTHER-USER-' . $status, $ticket, $status, '2026-08-10 12:00:00');
        $this->seedWithdraw($userId, 'LOCAL-OUTSIDE-DATE-' . $status, $ticket, $status, '2026-07-31 23:59:59');

        $response = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/withdraw/' . $action . '?status=' . $otherStatus . '&withdraw_source=' . $otherStatus,
            [
                'userId' => $userId,
                'withdraw_id' => $ticket,
                'withdraw_source' => (string) $otherStatus,
                'status' => (string) $otherStatus,
                'data' => [
                    'status' => (string) $otherStatus,
                    'withdraw_source' => (string) $otherStatus,
                ],
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
                'page' => 1,
                'rows' => 20,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mt4_ticket', $ticket)
            ->assertJsonPath('data.0.userId', $userId)
            ->assertJsonPath('data.0.applystatus', $status)
            ->assertJsonPath('totalRow.applyamount', '25.00');

        $payload = $response->json();
        $this->assertSame(
            ['code', 'msg', 'count', 'data', 'totalRow'],
            array_slice(array_keys($payload), 0, 5)
        );
        $this->assertSame(
            ['request_id', 'trace_id'],
            array_values(array_diff(array_keys($payload), ['code', 'msg', 'count', 'data', 'totalRow']))
        );
        foreach ([
            'mt4_ticket',
            'userId',
            'username',
            'bank_no',
            'bank_no_info',
            'applyamount',
            'actapplyamount',
            'drawrate',
            'actdraw',
            'drawpoundage',
            'applystatus',
            'apply_remark',
            'rec_crt_date',
        ] as $field) {
            $this->assertArrayHasKey($field, $payload['data'][0], $action . ':' . $field);
        }
    }

    public static function statusSearchProvider(): array
    {
        return [
            'pending' => ['pendingSearch', 0],
            'processing' => ['processingSearch', 1],
            'completed' => ['completedSearch', 2],
            'failed' => ['failedSearch', 3],
        ];
    }

    public function test_status_search_uses_rows_then_limit_then_per_page_and_defaults_to_fifteen(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 994305;
        for ($index = 1; $index <= 16; $index++) {
            $this->seedWithdraw(
                $userId,
                'STATUS-PAGE-' . $index,
                'STATUS-PAGE-MT4-' . $index,
                0,
                '2026-08-10 12:' . str_pad((string) $index, 2, '0', STR_PAD_LEFT) . ':00'
            );
        }

        $base = [
            'userId' => $userId,
            'withdraw_startdate' => '2026-08-01',
            'withdraw_enddate' => '2026-08-31',
            'page' => 1,
        ];

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/pendingSearch', $base + [
                'rows' => 1,
                'limit' => 2,
                'per_page' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('count', 16)
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/pendingSearch', $base + [
                'limit' => 2,
                'per_page' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('count', 16)
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/pendingSearch', $base + ['per_page' => 3])
            ->assertOk()
            ->assertJsonPath('count', 16)
            ->assertJsonCount(3, 'data');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/pendingSearch', $base)
            ->assertOk()
            ->assertJsonPath('count', 16)
            ->assertJsonCount(15, 'data');
    }

    public function test_status_search_summary_respects_the_admin_data_scope(): void
    {
        $allowedUserId = 994306;
        $blockedUserId = 994307;
        $admin = $this->seedScopedAdmin(994308, 994308, [$allowedUserId]);

        $this->seedWithdraw($allowedUserId, 'STATUS-SCOPE-ALLOWED', 'STATUS-SCOPE', 1, '2026-08-11 12:00:00');
        $blockedId = $this->seedWithdraw(
            $blockedUserId,
            'STATUS-SCOPE-BLOCKED',
            'STATUS-SCOPE',
            1,
            '2026-08-11 12:00:00'
        );
        DB::table('withdraw_records')->where('id', $blockedId)->update([
            'apply_amount' => '99999999999999.99',
            'actual_amount' => '99999999999999.99',
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/processingSearch', [
                'withdraw_id' => 'STATUS-SCOPE',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.userId', $allowedUserId)
            ->assertJsonPath('totalRow.applyamount', '25.00')
            ->assertJsonPath('totalRow.actapplyamount', '24.00');
    }

    public function test_status_search_applies_the_default_date_window_and_returns_an_empty_page_contract(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        try {
            $admin = Admin::query()->findOrFail(1);
            $userId = 994309;
            $this->seedWithdraw($userId, 'STATUS-BEFORE-WINDOW', 'STATUS-BEFORE-WINDOW', 2, '2023-12-31 23:59:59');
            $this->seedWithdraw($userId, 'STATUS-IN-WINDOW', 'STATUS-IN-WINDOW', 2, '2026-08-12 12:00:00');
            $this->seedWithdraw($userId, 'STATUS-AFTER-WINDOW', 'STATUS-AFTER-WINDOW', 2, '2026-08-16 00:00:00');

            $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/withdraw/completedSearch', [
                    'userId' => $userId,
                    'page' => 2,
                    'rows' => 1,
                ])
                ->assertOk()
                ->assertJsonPath('code', 200)
                ->assertJsonPath('count', 1)
                ->assertJsonPath('data', [])
                ->assertJsonPath('totalRow', []);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_status_search_returns_an_empty_total_for_no_matches(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/failedSearch', [
                'withdraw_id' => 'STATUS-NOT-FOUND',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow', []);
    }

    /**
     * @dataProvider invalidSearchProvider
     */
    public function test_status_search_rejects_invalid_legacy_filters(array $filters): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/pendingSearch', $filters)
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function invalidSearchProvider(): array
    {
        return [
            'invalid start date' => [[
                'withdraw_startdate' => '2026/08/01',
                'withdraw_enddate' => '2026-08-31',
            ]],
            'reversed date range' => [[
                'withdraw_startdate' => '2026-08-31',
                'withdraw_enddate' => '2026-08-01',
            ]],
            'invalid user' => [['userId' => 'invalid']],
            'invalid page' => [['page' => 0]],
            'invalid rows' => [['rows' => 101]],
            'rows array' => [['rows' => []]],
            'rows zero' => [['rows' => 0]],
            'rows negative' => [['rows' => -1]],
            'rows decimal' => [['rows' => '1.5']],
            'invalid limit' => [['limit' => 101]],
            'limit array' => [['limit' => []]],
            'limit zero' => [['limit' => 0]],
            'limit negative' => [['limit' => -1]],
            'limit decimal' => [['limit' => '1.5']],
            'invalid per page' => [['per_page' => 101]],
            'per page array' => [['per_page' => []]],
            'per page zero' => [['per_page' => 0]],
            'per page negative' => [['per_page' => -1]],
            'per page decimal' => [['per_page' => '1.5']],
        ];
    }

    public function test_status_search_keeps_large_decimal_totals_exact(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 994310;
        $recordId = $this->seedWithdraw(
            $userId,
            'STATUS-DECIMAL-BOUNDARY',
            'STATUS-DECIMAL-BOUNDARY',
            3,
            '2026-08-13 12:00:00'
        );
        DB::table('withdraw_records')->where('id', $recordId)->update([
            'apply_amount' => '99999999999999.99',
            'actual_amount' => '99999999999999.99',
            'fee' => '0.01',
            'exchange_rate' => '9.99999999',
            'rmb_fee' => '0.10',
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/withdraw/failedSearch', [
                'userId' => $userId,
                'withdraw_id' => 'STATUS-DECIMAL-BOUNDARY',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.applyamount', '99999999999999.99')
            ->assertJsonPath('data.0.actapplyamount', '99999999999999.99')
            ->assertJsonPath('data.0.actdraw', '999999998999999.90')
            ->assertJsonPath('totalRow.applyamount', '99999999999999.99')
            ->assertJsonPath('totalRow.actapplyamount', '99999999999999.99')
            ->assertJsonPath('totalRow.actdraw', '999999998999999.90')
            ->assertJsonPath('totalRow.drawpoundage', '0.01');
    }

    private function seedWithdraw(
        int $userId,
        string $localOrderNo,
        string $mt4Ticket,
        int $status,
        string $createdAt
    ): int {
        $timestamp = strtotime($createdAt);

        return (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'withdraw-status-' . $userId,
            'mt4_ticket' => $mt4Ticket,
            'apply_amount' => '25.00',
            'actual_amount' => '24.00',
            'fee' => '1.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '7.00',
            'bank_no' => '622200001',
            'bank_name' => 'Test Bank',
            'bank_addr' => 'Shanghai Branch',
            'status' => $status,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => 'status-search-test',
            'mt4_return_status' => '',
            'idempotency_key' => $localOrderNo,
            'funding_status' => 'debited',
            'funding_payload_hash' => hash('sha256', $localOrderNo),
            'created_by' => 'test',
            'updated_by' => '',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
    }

    private function seedScopedAdmin(int $adminId, int $roleId, array $userIds): Admin
    {
        $now = time();
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            [
                'name' => 'withdraw_status_scope_' . $roleId,
                'guard_type' => 'admin',
                'description' => 'Withdraw status search scope test',
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
                'user_ids' => json_encode($userIds),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $this->permissionIdForRoute('admin_api_withdrawList'),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'role_id' => (string) $roleId,
                'email' => 'withdraw-status-scope-' . $adminId . '@example.test',
                'username' => 'withdraw_status_scope_' . $adminId,
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function permissionIdForRoute(string $apiRoute): int
    {
        $permission = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('api_route', $apiRoute)
            ->orderBy('id')
            ->first();

        if ($permission) {
            DB::table('permissions')->where('id', $permission->id)->update([
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

            return (int) $permission->id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'parent_id' => 0,
            'name' => $apiRoute,
            'slug' => 'test_' . md5($apiRoute),
            'api_route' => $apiRoute,
            'route' => '',
            'icon' => '',
            'type' => 3,
            'guard_type' => 'admin',
            'sort' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
}
