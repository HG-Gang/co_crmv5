<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 13:57
 */

/**
 * AdminLegacyWithdrawStatusExportClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台出金状态导出闭环：URI status 与扁平旧筛选、自定义用户范围、旧新筛选合并规则、非法筛选返回校验 JSON、空导出仅表头、公式保护不转义金额。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AdminLegacyWithdrawStatusExportClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @dataProvider legacyStatusExportRoutes
     */
    public function test_status_export_uses_uri_status_and_flat_legacy_filters(
        string $route,
        int $uriStatus
    ): void {
        $allowedUserId = 995100 + $uriStatus;
        $blockedUserId = 995110 + $uriStatus;
        $ticket = 'MT4-LEGACY-STATUS-' . $uriStatus;
        $matchingOrder = 'LEGACY-STATUS-MATCH-' . $uriStatus;
        $oppositeStatus = ($uriStatus + 1) % 4;
        $admin = $this->seedScopedAdmin(
            995120 + $uriStatus,
            995130 + $uriStatus,
            [$allowedUserId]
        );

        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => $matchingOrder,
            'mt4_ticket' => $ticket,
            'status' => $uriStatus,
        ], '2026-08-10 12:00:00');
        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => 'LEGACY-STATUS-WRONG-STATUS-' . $uriStatus,
            'mt4_ticket' => $ticket,
            'status' => $oppositeStatus,
        ], '2026-08-10 12:00:00');
        $this->seedWithdraw($blockedUserId, [
            'local_order_no' => 'LEGACY-STATUS-BLOCKED-USER-' . $uriStatus,
            'mt4_ticket' => $ticket,
            'status' => $uriStatus,
        ], '2026-08-10 12:00:00');
        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => 'LEGACY-STATUS-WRONG-TICKET-' . $uriStatus,
            'mt4_ticket' => 'MT4-LEGACY-OTHER-' . $uriStatus,
            'status' => $uriStatus,
        ], '2026-08-10 12:00:00');
        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => $ticket,
            'mt4_ticket' => 'MT4-LOCAL-ORDER-LURE-' . $uriStatus,
            'status' => $uriStatus,
        ], '2026-08-10 12:00:00');
        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => 'LEGACY-STATUS-BEFORE-RANGE-' . $uriStatus,
            'mt4_ticket' => $ticket,
            'status' => $uriStatus,
        ], '2026-07-31 23:59:59');
        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => 'LEGACY-STATUS-AFTER-RANGE-' . $uriStatus,
            'mt4_ticket' => $ticket,
            'status' => $uriStatus,
        ], '2026-09-01 00:00:00');

        $response = $this->actingAs($admin, 'admin')->post(
            '/' . $route . '?status=' . $oppositeStatus,
            [
                'userId' => (string) $allowedUserId,
                'withdraw_id' => $ticket,
                'withdraw_source' => (string) $oppositeStatus,
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
                'status' => $oppositeStatus,
                'data' => ['status' => $oppositeStatus],
            ]
        );

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);

        $this->assertCount(2, $rows);
        $this->assertSame($matchingOrder, $rows[1][$header['local_order_no']]);
        $this->assertSame($ticket, $rows[1][$header['mt4_ticket']]);
        $this->assertSame((string) $allowedUserId, $rows[1][$header['user_id']]);
        $this->assertSame((string) $uriStatus, $rows[1][$header['status']]);
    }

    public static function legacyStatusExportRoutes(): array
    {
        return [
            'pending' => ['index/admin/withdraw/pendingExport', 0],
            'processing' => ['index/admin/withdraw/processingExport', 1],
            'completed' => ['index/admin/withdraw/completedExport', 2],
            'failed' => ['index/admin/withdraw/failedExport', 3],
        ];
    }

    public function test_status_export_applies_custom_users_scope(): void
    {
        $allowedUserId = 995200;
        $blockedUserId = 995201;
        $ticket = 'MT4-LEGACY-CUSTOM-SCOPE';
        $admin = $this->seedScopedAdmin(995202, 995203, [$allowedUserId]);

        $this->seedWithdraw($allowedUserId, [
            'local_order_no' => 'LEGACY-CUSTOM-SCOPE-ALLOWED',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ]);
        $this->seedWithdraw($blockedUserId, [
            'local_order_no' => 'LEGACY-CUSTOM-SCOPE-BLOCKED',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', [
                'userId' => '',
                'withdraw_id' => $ticket,
                'withdraw_source' => '3',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ])
            ->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);

        $this->assertCount(2, $rows);
        $this->assertSame((string) $allowedUserId, $rows[1][$header['user_id']]);
        $this->assertSame('LEGACY-CUSTOM-SCOPE-ALLOWED', $rows[1][$header['local_order_no']]);
    }

    public function test_flat_legacy_user_id_filters_between_two_users_in_scope(): void
    {
        $targetUserId = 995250;
        $otherUserId = 995251;
        $ticket = 'MT4-LEGACY-TWO-SCOPED-USERS';
        $admin = $this->seedScopedAdmin(995252, 995253, [$targetUserId, $otherUserId]);

        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-TWO-SCOPED-USERS-TARGET',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ]);
        $this->seedWithdraw($otherUserId, [
            'local_order_no' => 'LEGACY-TWO-SCOPED-USERS-LURE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', [
                'userId' => (string) $targetUserId,
            ])
            ->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);

        $this->assertCount(2, $rows);
        $this->assertSame((string) $targetUserId, $rows[1][$header['user_id']]);
        $this->assertSame('LEGACY-TWO-SCOPED-USERS-TARGET', $rows[1][$header['local_order_no']]);
    }

    public function test_modern_filters_are_preserved_when_flat_legacy_fields_are_missing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));

        $targetUserId = 995260;
        $otherUserId = 995261;
        $ticket = 'MT4-LEGACY-MODERN-FILTERS';
        $admin = $this->seedScopedAdmin(995262, 995263, [$targetUserId, $otherUserId]);
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MODERN-FILTERS-TARGET',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-07-15 12:00:00');
        $this->seedWithdraw($otherUserId, [
            'local_order_no' => 'LEGACY-MODERN-FILTERS-USER-LURE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-07-15 12:00:00');
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MODERN-FILTERS-TICKET-LURE',
            'mt4_ticket' => 'MT4-LEGACY-MODERN-OTHER',
            'status' => 0,
        ], '2026-07-15 12:00:00');
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MODERN-FILTERS-BEFORE-LURE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-06-30 23:59:59');
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MODERN-FILTERS-AFTER-LURE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-08-01 00:00:00');

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', [
                'user_id' => (string) $targetUserId,
                'mt4_ticket' => $ticket,
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ])
            ->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);

        $this->assertCount(2, $rows);
        $this->assertSame('LEGACY-MODERN-FILTERS-TARGET', $rows[1][$header['local_order_no']]);
        $this->assertSame((string) $targetUserId, $rows[1][$header['user_id']]);
        $this->assertSame($ticket, $rows[1][$header['mt4_ticket']]);
    }

    public function test_empty_legacy_query_aliases_do_not_erase_modern_body_filters(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));

        $targetUserId = 995290;
        $otherUserId = 995291;
        $ticket = 'MT4-LEGACY-MIXED-SOURCES';
        $admin = $this->seedScopedAdmin(995292, 995293, [$targetUserId, $otherUserId]);
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MIXED-SOURCES-TARGET',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-07-15 12:00:00');
        $this->seedWithdraw($otherUserId, [
            'local_order_no' => 'LEGACY-MIXED-SOURCES-USER-LURE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-07-15 12:00:00');
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MIXED-SOURCES-TICKET-LURE',
            'mt4_ticket' => 'MT4-LEGACY-MIXED-OTHER',
            'status' => 0,
        ], '2026-07-15 12:00:00');
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MIXED-SOURCES-BEFORE-LURE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-06-30 23:59:59');
        $this->seedWithdraw($targetUserId, [
            'local_order_no' => 'LEGACY-MIXED-SOURCES-AFTER-LURE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-08-01 00:00:00');

        $response = $this->actingAs($admin, 'admin')->post(
            '/index/admin/withdraw/pendingExport'
                . '?userId=&withdraw_id=&withdraw_startdate=&withdraw_enddate=',
            [
                'user_id' => (string) $targetUserId,
                'mt4_ticket' => $ticket,
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]
        )->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);

        $this->assertCount(2, $rows);
        $this->assertSame('LEGACY-MIXED-SOURCES-TARGET', $rows[1][$header['local_order_no']]);
        $this->assertSame((string) $targetUserId, $rows[1][$header['user_id']]);
        $this->assertSame($ticket, $rows[1][$header['mt4_ticket']]);
    }

    /**
     * @dataProvider invalidNullLegacyAliases
     */
    public function test_null_aliases_fall_back_to_invalid_modern_values(array $overrides): void
    {
        $userId = 995300;
        $admin = $this->seedScopedAdmin(995301, 995302, [$userId]);
        $payload = array_replace([
            'user_id' => (string) $userId,
            'mt4_ticket' => 'MT4-LEGACY-ALIAS-VALIDATION',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ], $overrides);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', $payload);

        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function invalidNullLegacyAliases(): array
    {
        return [
            'null user alias falls back to invalid modern user' => [[
                'userId' => null,
                'user_id' => 'invalid-modern-user',
            ]],
            'null date alias falls back to invalid modern date' => [[
                'withdraw_startdate' => null,
                'start_date' => '2026/08/01',
            ]],
        ];
    }

    /**
     * @dataProvider arrayLegacyAliases
     */
    public function test_array_aliases_are_not_silently_fallback_to_modern_values(array $overrides): void
    {
        $userId = 995310;
        $admin = $this->seedScopedAdmin(995311, 995312, [$userId]);
        $payload = array_replace([
            'user_id' => (string) $userId,
            'mt4_ticket' => 'MT4-LEGACY-ARRAY-VALIDATION',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ], $overrides);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', $payload);

        $this->assertNotInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function arrayLegacyAliases(): array
    {
        return [
            'array user alias is preserved' => [['userId' => []]],
            'array ticket alias is preserved' => [['withdraw_id' => []]],
            'array start date alias is preserved' => [['withdraw_startdate' => []]],
            'array end date alias is preserved' => [['withdraw_enddate' => []]],
        ];
    }

    /**
     * @dataProvider invalidModernFilters
     */
    public function test_invalid_modern_filters_remain_validation_failures(array $overrides): void
    {
        $userId = 995270;
        $admin = $this->seedScopedAdmin(995271, 995272, [$userId]);
        $payload = array_replace([
            'user_id' => (string) $userId,
            'mt4_ticket' => 'MT4-LEGACY-MODERN-VALIDATION',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ], $overrides);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', $payload);

        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function invalidModernFilters(): array
    {
        return [
            'invalid user' => [['user_id' => 'invalid-modern-user']],
            'invalid date' => [['start_date' => '2026/08/01']],
        ];
    }

    /**
     * @dataProvider invalidLegacyFilters
     */
    public function test_invalid_flat_legacy_filters_return_validation_json(array $overrides): void
    {
        $admin = $this->seedScopedAdmin(995210, 995211, [995212]);
        $payload = array_replace([
            'userId' => '995212',
            'withdraw_id' => 'MT4-LEGACY-VALIDATION',
            'withdraw_source' => '3',
            'withdraw_startdate' => '2026-08-01',
            'withdraw_enddate' => '2026-08-31',
        ], $overrides);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', $payload);

        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function invalidLegacyFilters(): array
    {
        return [
            'invalid date' => [['withdraw_startdate' => '2026/08/01']],
            'inverted dates' => [[
                'withdraw_startdate' => '2026-09-01',
                'withdraw_enddate' => '2026-08-31',
            ]],
            'invalid user' => [['userId' => 'invalid-user']],
        ];
    }

    /**
     * @dataProvider nonPositiveUserIdProvider
     */
    public function test_status_exports_reject_non_positive_user_ids(
        string $action,
        string $field,
        string $value
    ): void {
        $admin = $this->seedScopedAdmin(995215, 995216, [995217]);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/' . $action, [
                $field => $value,
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ]);

        $this->assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public static function nonPositiveUserIdProvider(): array
    {
        $datasets = [];
        foreach (['pendingExport', 'processingExport', 'completedExport', 'failedExport'] as $action) {
            $datasets[$action . ' legacy zero'] = [$action, 'userId', '0'];
            $datasets[$action . ' modern negative'] = [$action, 'user_id', '-1'];
        }

        return $datasets;
    }

    public function test_empty_status_export_contains_only_the_header(): void
    {
        $userId = 995220;
        $admin = $this->seedScopedAdmin(995221, 995222, [$userId]);

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', [
                'userId' => (string) $userId,
                'withdraw_id' => 'MT4-LEGACY-NOT-FOUND',
                'withdraw_source' => '3',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ])
            ->assertOk();

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertCount(1, $rows);
        $this->assertSame([
            'id', 'local_order_no', 'third_order_no', 'mt4_ticket', 'user_id',
            'user_name', 'apply_amount', 'actual_amount', 'fee', 'exchange_rate',
            'rmb_fee', 'bank_no', 'bank_name', 'bank_addr', 'status',
            'funding_status', 'reject_reason', 'created_at',
        ], $rows[0]);
    }

    public function test_status_export_reuses_csv_formula_protection_without_escaping_amounts(): void
    {
        $userId = 995230;
        $ticket = '-MT4-LEGACY-DANGEROUS';
        $admin = $this->seedScopedAdmin(995231, 995232, [$userId]);
        $dangerousCells = [
            'local_order_no' => '=LOCAL-ORDER',
            'third_order_no' => '+THIRD-ORDER',
            'mt4_ticket' => $ticket,
            'user_name' => '@USER-NAME',
            'bank_no' => "\tBANK-NO",
            'bank_name' => "\rBANK-NAME",
            'bank_addr' => "\nBANK-ADDR",
            'funding_status' => '=FUNDING-STATUS',
            'reject_reason' => '+REJECT-REASON',
        ];
        $this->seedWithdraw($userId, array_replace($dangerousCells, ['status' => 0]));

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', [
                'userId' => (string) $userId,
                'withdraw_id' => $ticket,
                'withdraw_source' => '3',
                'withdraw_startdate' => '2026-08-01',
                'withdraw_enddate' => '2026-08-31',
            ])
            ->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);
        $this->assertCount(2, $rows);
        $row = $rows[1];

        foreach ($dangerousCells as $column => $value) {
            $this->assertSame("'" . $value, $row[$header[$column]]);
        }
        $numericCells = [
            'apply_amount' => '125.00',
            'actual_amount' => '120.00',
            'fee' => '5.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '35.00',
        ];
        foreach ($numericCells as $column => $value) {
            $this->assertSame($value, $row[$header[$column]]);
            $this->assertMatchesRegularExpression('/^\d+(?:\.\d+)?$/D', $row[$header[$column]]);
        }
    }

    public function test_missing_modern_dates_use_frozen_default_bounds(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));

        $userId = 995280;
        $ticket = 'MT4-LEGACY-MISSING-MODERN-DATES';
        $admin = $this->seedScopedAdmin(995281, 995282, [$userId]);
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-MISSING-MODERN-DATE-LOWER',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2024-01-01 00:00:00');
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-MISSING-MODERN-DATE-UPPER',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-08-18 23:59:59');
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-MISSING-MODERN-DATE-BEFORE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2023-12-31 23:59:59');
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-MISSING-MODERN-DATE-AFTER',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-08-19 00:00:00');

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', [
                'user_id' => (string) $userId,
                'mt4_ticket' => $ticket,
            ])
            ->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing([
            'LEGACY-MISSING-MODERN-DATE-LOWER',
            'LEGACY-MISSING-MODERN-DATE-UPPER',
        ], array_column(array_slice($rows, 1), $header['local_order_no']));
    }

    public function test_empty_dates_default_to_frozen_lower_and_upper_bounds(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));

        $userId = 995240;
        $ticket = 'MT4-LEGACY-DEFAULT-DATES';
        $admin = $this->seedScopedAdmin(995241, 995242, [$userId]);
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-DEFAULT-DATE-LOWER',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2024-01-01 00:00:00');
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-DEFAULT-DATE-UPPER',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-08-18 23:59:59');
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-DEFAULT-DATE-BEFORE',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2023-12-31 23:59:59');
        $this->seedWithdraw($userId, [
            'local_order_no' => 'LEGACY-DEFAULT-DATE-AFTER',
            'mt4_ticket' => $ticket,
            'status' => 0,
        ], '2026-08-19 00:00:00');

        $response = $this->actingAs($admin, 'admin')
            ->post('/index/admin/withdraw/pendingExport', [
                'userId' => (string) $userId,
                'withdraw_id' => $ticket,
                'withdraw_source' => '3',
                'withdraw_startdate' => '',
                'withdraw_enddate' => '',
            ])
            ->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_flip($rows[0]);

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing([
            'LEGACY-DEFAULT-DATE-LOWER',
            'LEGACY-DEFAULT-DATE-UPPER',
        ], array_column(array_slice($rows, 1), $header['local_order_no']));
    }

    private function seedWithdraw(int $userId, array $overrides, string $createdAt = '2026-08-10 12:00:00'): int
    {
        $localOrderNo = (string) ($overrides['local_order_no'] ?? ('LEGACY-STATUS-' . $userId));
        $timestamp = Carbon::parse($createdAt, config('app.timezone'))->getTimestamp();

        return (int) DB::table('withdraw_records')->insertGetId(array_replace([
            'user_id' => $userId,
            'user_name' => 'legacy-status-' . $userId,
            'mt4_ticket' => 'MT4-LEGACY-STATUS-' . $userId,
            'apply_amount' => '125.00',
            'actual_amount' => '120.00',
            'fee' => '5.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '35.00',
            'bank_no' => '62220000' . $userId,
            'bank_name' => 'Legacy Status Bank',
            'bank_addr' => 'Shanghai Branch',
            'status' => 0,
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
        ], $overrides));
    }

    private function seedScopedAdmin(int $adminId, int $roleId, array $userIds): Admin
    {
        $now = now()->getTimestamp();
        DB::table('roles')->updateOrInsert(['id' => $roleId], [
            'name' => 'legacy-withdraw-status-export-' . $roleId,
            'guard_type' => 'admin',
            'description' => 'Legacy withdrawal status export scope',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->updateOrInsert(['role_id' => $roleId], [
            'scope_type' => 'custom_users',
            'agent_ids' => null,
            'user_ids' => json_encode($userIds),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $this->permissionIdForRoute('admin_api_exportWithdrawals'),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->updateOrInsert(['id' => $adminId], [
            'role_id' => (string) $roleId,
            'email' => 'legacy-status-export-' . $adminId . '@example.test',
            'username' => 'legacy_status_export_' . $adminId,
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

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

    /** @return array<int, array<int, string|null>> */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $content);
        rewind($stream);
        if (fread($stream, 3) !== "\xEF\xBB\xBF") {
            rewind($stream);
        }

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
