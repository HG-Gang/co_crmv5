<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:27
 */

/**
 * AdminLegacyWithdrawSearchParityClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台出金搜索双口径等价：旧字段筛选与预期 envelope、默认日期窗口与空页、汇总尊重数据范围、MT4 票号与日期现代筛选、大精度金额与费率精确保留。
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
use Tests\TestCase;

class AdminLegacyWithdrawSearchParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @dataProvider legacySearchProvider
     */
    public function test_legacy_search_filters_old_fields_and_returns_the_expected_envelope(
        string $action,
        string $rowsPath,
        string $totalPath,
        string $footerPath
    ): void {
        $admin = Admin::query()->findOrFail(1);
        $userId = 993101;
        $firstRecordId = $this->seedWithdraw(
            $userId,
            'LEGACY-WITHDRAW-TARGET-ONE',
            'MT4-TICKET-TARGET',
            0,
            '2026-08-10 12:00:00'
        );
        DB::table('withdraw_records')->where('id', $firstRecordId)->update([
            'fee' => '1.25',
            'rmb_fee' => '101.00',
        ]);
        $secondRecordId = $this->seedWithdraw(
            $userId,
            'LEGACY-WITHDRAW-TARGET-TWO',
            'MT4-TICKET-TARGET',
            0,
            '2026-08-10 12:00:00'
        );
        DB::table('withdraw_records')->where('id', $secondRecordId)->update([
            'apply_amount' => '30.00',
            'actual_amount' => '20.00',
            'fee' => '2.75',
            'exchange_rate' => '8.00000000',
            'rmb_fee' => '203.00',
        ]);
        $this->seedWithdraw($userId, 'LEGACY-WITHDRAW-OTHER-STATUS', 'MT4-TICKET-TARGET', 1, '2026-08-10 12:00:00');
        $this->seedWithdraw($userId, 'LEGACY-WITHDRAW-OTHER-TICKET', 'MT4-TICKET-OTHER', 0, '2026-08-10 12:00:00');

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/' . $action, [
                'userId' => $userId,
                'withdraw_id' => 'MT4-TICKET-TARGET',
                'withdraw_source' => '0',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
                'page' => 1,
                'rows' => 20,
            ])
            ->assertOk();

        if ($action === 'withdrawApplySearchV2') {
            $response->assertJsonPath('code', 200)
                ->assertJsonPath('msg', 'Request data successful.');
        } else {
            $response->assertJsonStructure(['rows', 'total', 'footer']);
        }

        $response->assertJsonCount(2, $rowsPath)
            ->assertJsonPath($totalPath, 2)
            ->assertJsonPath($rowsPath . '.0.mt4_ticket', 'MT4-TICKET-TARGET')
            ->assertJsonPath($rowsPath . '.0.userId', $userId)
            ->assertJsonPath($rowsPath . '.0.bank_no', '622200001')
            ->assertJsonPath($rowsPath . '.0.applystatus', 0)
            ->assertJsonPath($footerPath . '.mt4_ticket', __('systemlanguage.total'))
            ->assertJsonPath($footerPath . '.applyamount', '55.00')
            ->assertJsonPath($footerPath . '.actapplyamount', '44.00')
            ->assertJsonPath($footerPath . '.actdraw', '328.00')
            ->assertJsonPath($footerPath . '.drawpoundage', '4.00');

        $rows = data_get($response->json(), $rowsPath);
        $this->assertIsArray($rows);
        $this->assertEqualsCanonicalizing(['25.00', '30.00'], array_column($rows, 'applyamount'));
        $this->assertEqualsCanonicalizing(['24.00', '20.00'], array_column($rows, 'actapplyamount'));
        $this->assertEqualsCanonicalizing(['168.00', '160.00'], array_column($rows, 'actdraw'));
        $this->assertEqualsCanonicalizing(['1.25', '2.75'], array_column($rows, 'drawpoundage'));
        $this->assertNotSame('304.00', (string) data_get($response->json(), $footerPath . '.drawpoundage'));
    }

    public static function legacySearchProvider(): array
    {
        return [
            'legacy v1' => ['withdrawApplySearch', 'rows', 'total', 'footer.0'],
            'legacy v2' => ['withdrawApplySearchV2', 'data', 'count', 'totalRow'],
        ];
    }

    public function test_legacy_v2_search_applies_default_date_window_and_handles_an_empty_page(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 993102;
        $this->seedWithdraw($userId, 'LEGACY-WITHDRAW-BEFORE-WINDOW', 'MT4-BEFORE-WINDOW', 0, '2023-12-31 23:59:59');
        $this->seedWithdraw($userId, 'LEGACY-WITHDRAW-IN-WINDOW', 'MT4-IN-WINDOW', 0, '2026-08-11 12:00:00');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawApplySearchV2', [
                'userId' => $userId,
                'page' => 2,
                'rows' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data', [])
            ->assertJsonPath('totalRow', []);
    }

    public function test_legacy_v2_search_summary_respects_admin_data_scope(): void
    {
        $allowedUserId = 993103;
        $blockedUserId = 993104;
        $admin = $this->seedScopedAdmin(993105, 993105, [$allowedUserId]);
        $this->seedWithdraw($allowedUserId, 'LEGACY-WITHDRAW-SCOPE-ALLOWED', 'MT4-SCOPE', 0, '2026-08-12 12:00:00');
        $this->seedWithdraw($blockedUserId, 'LEGACY-WITHDRAW-SCOPE-BLOCKED', 'MT4-SCOPE', 0, '2026-08-12 12:00:00');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawApplySearchV2', [
                'withdraw_id' => 'MT4-SCOPE',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.userId', $allowedUserId)
            ->assertJsonPath('totalRow.applyamount', '25.00');
    }

    public function test_legacy_v2_search_rejects_invalid_date_range(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawApplySearchV2', [
                'withdraw_startdate' => '2026-08-31',
                'withdraw_enddate' => '2026-08-01',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_v2_search_keeps_decimal_precision_for_large_amounts_and_rates(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 993107;
        $recordId = $this->seedWithdraw(
            $userId,
            'LEGACY-WITHDRAW-DECIMAL-BOUNDARY',
            'MT4-DECIMAL-BOUNDARY',
            0,
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
            ->postJson('/index/admin/amount/withdrawApplySearchV2', [
                'userId' => $userId,
                'withdraw_id' => 'MT4-DECIMAL-BOUNDARY',
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
            ->assertJsonPath('totalRow.actdraw', '999999998999999.90');
    }

    /**
     * 锁定「出金合计行 actdraw 必须等于逐行显示值之和」这一同源同口径契约。
     *
     * 为什么必须有这条测试：
     * - 逐行 actdraw 走 WithdrawRecordQueryService::multiplyMoneyByRate()，乘完立刻舍入到分；
     *   合计行走 summarize()，若在 SQL 侧先累加乘积、最后只舍入一次，
     *   两者在「分位以下仍有尾数」时必然分叉。本用例每行尾数恰好是半分，放大该分叉。
     * - 项目1 的基准是 sum(act_draw)，而 act_draw 是**已按分存储**的列，
     *   即旧口径等价于「逐行先舍、再求和」；新项目合计行必须复刻这个顺序。
     * - LegacyAdminController::formatLegacyWithdrawTotalRow() 的注释已声明
     *   「合计行与逐行必须同源同口径」，本用例把该声明变成可执行断言。
     *
     * 边界与失败语义：
     * - 断言同时写成「不变式（合计 == 逐行求和）」与「精确期望值」两条：
     *   只写不变式，会在两侧同时算错成同一个值时被静默满足；
     *   只写硬编码值，则夹具一改契约就失锁。
     * - 4 行 × 半分 = 2 分偏差，属用户可见的对不上账，不允许放过。
     *
     * @dataProvider legacySearchProvider
     * @param string $action 旧搜索动作名，覆盖 v1 与 v2 两套 envelope。
     * @param string $rowsPath 行集合在响应中的路径。
     * @param string $totalPath 总数在响应中的路径。
     * @param string $footerPath 合计行在响应中的路径。
     */
    public function test_legacy_footer_actual_draw_equals_the_sum_of_displayed_rows(
        string $action,
        string $rowsPath,
        string $totalPath,
        string $footerPath
    ): void {
        $admin = Admin::query()->findOrFail(1);
        $userId = 993108;

        // 每行 1.00 × 1.00500000 = 1.0050000000，逐行舍入后为 1.01，4 行正确合计为 4.04。
        // 若合计行先累加再舍入，SUM 得 4.02000000，只会输出 4.02，与页面上四个 1.01 对不上。
        for ($index = 1; $index <= 4; $index++) {
            $recordId = $this->seedWithdraw(
                $userId,
                'LEGACY-WITHDRAW-ROUNDING-TAIL-' . $index,
                'MT4-ROUNDING-TAIL',
                0,
                '2026-08-14 12:00:00'
            );
            DB::table('withdraw_records')->where('id', $recordId)->update([
                'actual_amount' => '1.00',
                'exchange_rate' => '1.00500000',
            ]);
        }

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/' . $action, [
                'userId' => $userId,
                'withdraw_id' => 'MT4-ROUNDING-TAIL',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
                'page' => 1,
                'rows' => 20,
            ])
            ->assertOk()
            ->assertJsonPath($totalPath, 4);

        $rows = data_get($response->json(), $rowsPath);
        $this->assertIsArray($rows);
        $this->assertSame(
            ['1.01', '1.01', '1.01', '1.01'],
            array_column($rows, 'actdraw'),
            '逐行 actdraw 必须按分逐行舍入，1.00 × 1.005 应显示 1.01'
        );

        // 逐行求和全程走 BCMath 字符串加法：这里若用 array_sum 会引入 float，
        // 断言自身就带上了本测试要防的那类精度噪声。
        $rowSum = '0.00';
        foreach (array_column($rows, 'actdraw') as $rowActualDraw) {
            $rowSum = bcadd($rowSum, (string) $rowActualDraw, 2);
        }

        $footerActualDraw = (string) data_get($response->json(), $footerPath . '.actdraw');
        $this->assertSame(
            $rowSum,
            $footerActualDraw,
            '合计行 actdraw 必须等于逐行显示值之和，否则页面上行与合计自相矛盾'
        );
        $this->assertSame('4.04', $footerActualDraw, '4 行 1.01 的正确合计为 4.04，先累加再舍入会得到 4.02');
    }

    public function test_modern_search_filters_by_mt4_ticket_and_date_range(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 993106;
        $this->seedWithdraw($userId, 'MODERN-WITHDRAW-TARGET', 'MT4-MODERN-TARGET', 0, '2026-08-10 12:00:00');
        $this->seedWithdraw($userId, 'MODERN-WITHDRAW-OUTSIDE-DATE', 'MT4-MODERN-TARGET', 0, '2026-07-31 23:59:59');

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')
            ->postJson('/api/admin/withdrawList', [
                'mt4_ticket' => 'MT4-MODERN-TARGET',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'page' => 1,
                'per_page' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.local_order_no', 'MODERN-WITHDRAW-TARGET');
    }

    public function test_modern_search_rejects_page_sizes_above_the_safe_limit(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')
            ->postJson('/api/admin/withdrawList', ['page' => 1, 'per_page' => 101])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
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
            'user_name' => 'legacy-search-' . $userId,
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
            'reject_reason' => '',
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
                'name' => 'legacy_withdraw_scope_' . $roleId,
                'guard_type' => 'admin',
                'description' => 'Legacy withdrawal search scope',
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
                'email' => 'legacy-withdraw-scope-' . $adminId . '@example.test',
                'username' => 'legacy_withdraw_scope_' . $adminId,
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
