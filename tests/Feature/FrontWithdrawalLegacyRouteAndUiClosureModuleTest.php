<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/19
 * Time: 14:34
 */

/**
 * FrontWithdrawalLegacyRouteAndUiClosureModuleTest
 *
 * 文件功能：
 * - 验证前台出金旧路由与 UI 闭环：管理状态与资金状态分离展示且 pending/unknown 不标已完成、新旧提交路径要求一致的金额/资金密码/幂等/资金化规则、Layui 表格渲染资金状态列。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\Withdrawal\WithdrawalAccountSnapshot;
use App\Support\FrontLegacyData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\ManagesSharedSystemConfigFixtures;
use Tests\TestCase;

class FrontWithdrawalLegacyRouteAndUiClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use ManagesSharedSystemConfigFixtures;

    /**
     * 出金旧路由/页面用例的登录用户 ID。验证旧页面渲染与路由意图对本人数据可见。
     * @var int
     */
    private const VIEWER_ID = 412374201;
    /**
     * 另一业务用户 ID。验证旧页面不泄露他人数据。
     * @var int
     */
    private const OTHER_ID = 412374202;

    /**
     * 改写前的出金相关 system_configs 行快照。tearDown 据此恢复配置原值。
     * @var array<int, array<string, mixed>>|null
     */
    private $configSnapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSnapshot = null;
        try {
            $this->acquireSharedSystemConfigFixtureLock();
            $this->configSnapshot = DB::table('system_configs')
                ->useWritePdo()
                ->whereIn('key', $this->withdrawalConfigKeys())
                ->orderBy('key')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $this->captureSharedSystemConfigFixtureOwnedState(
                $this->withdrawalConfigKeys(),
                $this->configSnapshot
            );

            $this->app->instance(
                WithdrawalAccountSnapshotGateway::class,
                new class implements WithdrawalAccountSnapshotGateway {
                    public function snapshot(int $userId): WithdrawalAccountSnapshot
                    {
                        return new WithdrawalAccountSnapshot('5000.00', '5000.00');
                    }
                }
            );

            $this->deleteFixtureRows(
                [self::VIEWER_ID, self::OTHER_ID],
                [
                    'front-withdraw-task5-' . self::VIEWER_ID . '@example.test',
                    'front-withdraw-task5-' . self::OTHER_ID . '@example.test',
                ]
            );
            $this->allowWithdrawalsForTest();
            $this->insertUserInfo(self::VIEWER_ID, 'withdraw-task5-viewer', 'front-withdraw-task5-' . self::VIEWER_ID . '@example.test', 5000);
            $this->insertUserInfo(self::OTHER_ID, 'withdraw-task5-other', 'front-withdraw-task5-' . self::OTHER_ID . '@example.test', 5000);
            $this->insertUserAuth(self::VIEWER_ID, 'TASK5-BANK-001', 'Task 5 Bank', 'Task 5 Branch');
            $this->insertUserAuth(self::OTHER_ID, 'TASK5-BANK-002', 'Task 5 Bank', 'Task 5 Branch');
        } catch (\Throwable $exception) {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($exception, [
                'clean task5 fixture rows' => function (): void {
                    if ($this->hasSharedSystemConfigFixtureLockState()) {
                        $this->deleteFixtureRows(
                            [self::VIEWER_ID, self::OTHER_ID],
                            [
                                'front-withdraw-task5-' . self::VIEWER_ID . '@example.test',
                                'front-withdraw-task5-' . self::OTHER_ID . '@example.test',
                            ]
                        );
                    }
                },
                'restore withdrawal config snapshot' => function (): void {
                    $this->restoreWithdrawalConfig();
                },
                'parent teardown' => function (): void {
                    parent::tearDown();
                },
                'release shared system config fixture lock' => function (): void {
                    $this->releaseSharedSystemConfigFixtureLock();
                },
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
            'clean task5 fixture rows' => function (): void {
                $this->deleteFixtureRows(
                    [self::VIEWER_ID, self::OTHER_ID],
                    [
                        'front-withdraw-task5-' . self::VIEWER_ID . '@example.test',
                        'front-withdraw-task5-' . self::OTHER_ID . '@example.test',
                    ]
                );
            },
            'restore withdrawal config snapshot' => function (): void {
                $this->restoreWithdrawalConfig();
            },
            'parent teardown' => function (): void {
                parent::tearDown();
            },
            'release shared system config fixture lock' => function (): void {
                $this->releaseSharedSystemConfigFixtureLock();
            },
        ]);
    }

    public function test_history_exposes_admin_status_and_funding_status_separately_and_never_marks_pending_unknown_as_completed(): void
    {
        $viewerPendingId = $this->insertWithdrawRecord(self::VIEWER_ID, 'WDRTASK5P001', 0, 'pending');
        $viewerUnknownId = $this->insertWithdrawRecord(self::VIEWER_ID, 'WDRTASK5U001', 0, 'unknown');
        $viewerDebitedId = $this->insertWithdrawRecord(self::VIEWER_ID, 'WDRTASK5D001', 1, 'debited');
        $otherId = $this->insertWithdrawRecord(self::OTHER_ID, 'WDRTASK5O001', 2, 'debited');

        $login = UserLogin::where('user_id', self::VIEWER_ID)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/withdrawals/history?limit=50');

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $payload = $response->json('data');
        $list = $payload['list'] ?? $payload['data'] ?? [];
        if (is_array($list) && array_key_exists('data', $list) && is_array($list['data'])) {
            $list = $list['data'];
        }
        $rows = collect($list);
        $ids = $rows->pluck('id')->map(static function ($id): int {
            return (int) $id;
        })->all();

        $this->assertContains($viewerPendingId, $ids);
        $this->assertContains($viewerUnknownId, $ids);
        $this->assertContains($viewerDebitedId, $ids);
        $this->assertNotContains($otherId, $ids);

        $byId = $rows->keyBy(static function ($row): int {
            return (int) $row['id'];
        });

        $pending = $byId[$viewerPendingId];
        $this->assertSame(0, (int) $pending['status']);
        $this->assertSame('pending', (string) $pending['funding_status']);
        $this->assertSame(FrontLegacyData::withdrawStatusText(0), (string) $pending['status_text']);
        $this->assertSame(FrontLegacyData::withdrawFundingStatusText('pending'), (string) $pending['funding_status_text']);
        $this->assertStringNotContainsString('Completed', (string) $pending['status_text']);
        $this->assertStringNotContainsString('已完成', (string) $pending['status_text']);
        $this->assertStringNotContainsString('Completed', (string) $pending['funding_status_text']);
        $this->assertStringNotContainsString('已完成', (string) $pending['funding_status_text']);

        $unknown = $byId[$viewerUnknownId];
        $this->assertSame('unknown', (string) $unknown['funding_status']);
        $this->assertSame(FrontLegacyData::withdrawFundingStatusText('unknown'), (string) $unknown['funding_status_text']);
        $this->assertStringNotContainsString('Completed', (string) $unknown['funding_status_text']);
        $this->assertStringNotContainsString('已完成', (string) $unknown['funding_status_text']);

        $debited = $byId[$viewerDebitedId];
        $this->assertSame(1, (int) $debited['status']);
        $this->assertSame('debited', (string) $debited['funding_status']);
        $this->assertSame(FrontLegacyData::withdrawFundingStatusText('debited'), (string) $debited['funding_status_text']);
    }

    public function test_modern_and_legacy_submit_paths_require_the_same_amount_password_idempotency_and_funding_rules(): void
    {
        $login = UserLogin::where('user_id', self::VIEWER_ID)->firstOrFail();
        $payload = [
            'amount' => '100.00',
            'withdraw_amt' => '100.00',
            'password' => 'password',
            'withdraw_password' => 'password',
            'withdraw_psw' => 'password',
            'agree' => 1,
        ];

        $missingKeyModern = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/withdrawals/submissions', $payload);
        $missingKeyLegacy = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/withdraw_request', $payload);
        $missingKeyOtc = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/withdraw_request_OTC', $payload);

        foreach ([$missingKeyModern, $missingKeyLegacy, $missingKeyOtc] as $response) {
            $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
            $this->assertStringContainsString('Idempotency-Key', (string) $response->json('message'));
        }

        $key = 'task5-legacy-idempotency-key-001';
        $first = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/user/withdraw_request', $payload);
        $first->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $orderId = (int) ($first->json('data.id') ?? 0);
        $this->assertGreaterThan(0, $orderId);
        $this->assertSame('pending', (string) ($first->json('data.funding_status') ?? ''));

        $replay = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/user/withdraw_request_OTC', $payload);
        $replay->assertOk()->assertJsonPath('code', ResponseCode::CREATED);
        $this->assertSame($orderId, (int) ($replay->json('data.id') ?? 0));

        $conflict = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/front/withdrawals/submissions', array_merge($payload, [
                'amount' => '120.00',
                'withdraw_amt' => '120.00',
            ]));
        $conflict->assertOk();
        $this->assertNotSame(ResponseCode::CREATED, (int) $conflict->json('code'));

        $this->assertSame(1, DB::table('withdraw_records')->where('user_id', self::VIEWER_ID)->where('idempotency_key', $key)->count());
        $this->assertSame(
            1,
            DB::table('withdraw_settlement_outbox')
                ->where('withdraw_record_id', $orderId)
                ->where('event_type', 'withdraw_debit')
                ->count()
        );
    }

    public function test_layui_history_table_renders_funding_status_column_and_not_only_admin_status(): void
    {
        $source = file_get_contents(base_path('public/js/apps/front/layui/pages.js'));
        $this->assertIsString($source);
        $this->assertStringContainsString("field: 'funding_status_text'", $source);
        $this->assertStringContainsString("field: 'status_text'", $source);
        $this->assertStringContainsString("url: '/api/front/withdrawals/history'", $source);
        $this->assertStringContainsString("headers: {'Idempotency-Key': requestKey}", $source);
    }

    /** @return array<int, string> */
    private function withdrawalConfigKeys(): array
    {
        return [
            'is_open_withdraw',
            'withdrawal_enabled',
            'withdrawal_weekend_enabled',
            'withdrawal_start_time',
            'withdrawal_end_time',
            'withdraw_min_amount',
            'withdraw_max_amount',
            'withdraw_risk_rate_limit',
            'withdraw_check_open',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'withdraw_exchange_rate_cny',
        ];
    }

    private function allowWithdrawalsForTest(): void
    {
        $now = time();
        foreach ([
            'is_open_withdraw' => '1',
            'withdrawal_enabled' => '1',
            'withdrawal_weekend_enabled' => '1',
            'withdrawal_start_time' => '',
            'withdrawal_end_time' => '',
            'withdraw_min_amount' => '10.00',
            'withdraw_max_amount' => '500000.00',
            'withdraw_risk_rate_limit' => '100.00',
            'withdraw_check_open' => '0',
            'withdrawal_fee_rate' => '0',
            'withdrawal_fixed_fee_usd' => '0',
            'withdraw_exchange_rate_cny' => '7.20000000',
        ] as $key => $value) {
            $existing = DB::table('system_configs')->useWritePdo()->where('key', $key)->first();
            if ($existing) {
                DB::table('system_configs')->where('key', $key)->update([
                    'value' => $value,
                    'description' => 'Withdrawal Task 5 fixture',
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('system_configs')->insert([
                'key' => $key,
                'value' => $value,
                'group' => 'general',
                'description' => 'Withdrawal Task 5 fixture',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function restoreWithdrawalConfig(): void
    {
        if ($this->configSnapshot === null) {
            return;
        }

        DB::table('system_configs')
            ->whereIn('key', $this->withdrawalConfigKeys())
            ->delete();

        foreach ($this->configSnapshot as $row) {
            DB::table('system_configs')->insert($row);
        }
        $this->configSnapshot = null;
    }

    private function insertUserInfo(int $userId, string $userName, string $email, float $funds): void
    {
        $now = time();
        $loginId = (int) DB::table('user_logins')->insertGetId([
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
            'phone' => '1392374' . substr((string) $userId, -4),
            'gender' => 1,
            'avatar' => '',
            'level_id' => 0,
            'group_id' => 0,
            'parent_id' => 0,
            'account_type' => 2,
            'family_tree' => '',
            'total_funds' => $funds,
            'used_margin' => 0,
            'avail_margin' => $funds,
            'equity' => $funds,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'margin_amount' => 0,
            'leverage' => 100,
            'cust_vol' => '',
            'pay_provider_id' => 0,
            'equity_ratio' => 0,
            'comm_rate' => 0,
            'is_ecn' => 0,
            'follow_parent_ecn' => 0,
            'auth_status' => 1,
            'is_mt4_synced' => 0,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 1,
            'is_agent_confirmed' => 1,
            'original_group' => '',
            'mt4_group' => '',
            'mt4_code' => 0,
            'trading_mode' => 0,
            'settle_method' => 0,
            'settle_cycle' => 0,
            'country' => '',
            'city' => '',
            'state' => '',
            'address' => '',
            'is_gift_allowed' => 0,
            'data_source' => 0,
            'remark' => '',
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertUserAuth(int $userId, string $bankNo, string $bankName, string $bankAddr): void
    {
        $now = time();
        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_no' => $bankNo,
            'bank_no_tmp' => '',
            'bank_name' => $bankName,
            'bank_name_tmp' => '',
            'bank_card_img' => '',
            'bank_card_back_img' => '',
            'bank_card_img_tmp' => '',
            'bank_card_back_img_tmp' => '',
            'bank_addr' => $bankAddr,
            'bank_addr_tmp' => '',
            'bank_status' => 2,
            'bank_remarks' => '',
            'id_card_no' => 'ID' . $userId,
            'id_card_status' => 2,
            'id_card_front' => '',
            'id_card_back' => '',
            'id_card_remarks' => '',
            'is_bank_synced' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertWithdrawRecord(int $userId, string $orderNo, int $status, string $fundingStatus): int
    {
        $now = time();

        return (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => $userId === self::VIEWER_ID ? 'withdraw-task5-viewer' : 'withdraw-task5-other',
            'mt4_ticket' => '0',
            'apply_amount' => '100.00',
            'actual_amount' => '100.00',
            'fee' => '0.00',
            'exchange_rate' => '7.20000000',
            'rmb_fee' => '0.00',
            'bank_no' => $userId === self::VIEWER_ID ? 'TASK5-BANK-001' : 'TASK5-BANK-002',
            'bank_name' => 'Task 5 Bank',
            'bank_addr' => 'Task 5 Branch',
            'status' => $status,
            'local_order_no' => $orderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => 'task5-history-' . $orderNo,
            'funding_status' => $fundingStatus,
            'funding_payload_hash' => hash('sha256', $orderNo),
            'created_by' => $userId === self::VIEWER_ID ? 'withdraw-task5-viewer' : 'withdraw-task5-other',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        $withdrawIds = DB::table('withdraw_records')->whereIn('user_id', $userIds)->pluck('id')->all();
        if ($withdrawIds !== []) {
            DB::table('withdraw_settlement_outbox')->whereIn('withdraw_record_id', $withdrawIds)->delete();
        }
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->orWhereIn('email', $emails)->delete();
    }
}
