<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/17
 * Time: 07:08
 */

/**
 * FrontWithdrawOwnerBoundaryClosureModuleTest
 *
 * 文件功能：
 * - 验证前台出金归属边界：现代与旧提交路径均忽略伪造 user_id 只为当前用户建单、历史仅返回当前用户记录、夹具锁阻止竞争连接。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\WithdrawalAccountSnapshotGateway;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use App\Services\Withdrawal\WithdrawalAccountSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\ManagesSharedSystemConfigFixtures;
use Tests\TestCase;

class FrontWithdrawOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;
    use ManagesSharedSystemConfigFixtures;

    /**
     * 改写前的出金相关 system_configs 行快照。tearDown 据此恢复配置原值。
     * @var array<int, array<string, mixed>>|null
     */
    private $configSnapshot;

    /**
     * 独立的锁观察连接。用 GET_LOCK 尝试获取与夹具相同的锁，验证共享配置夹具锁被 setUp 持有。
     * @var \Illuminate\Database\Connection|null
     */
    private $fixtureLockObserver;

    /**
     * 观察连接尝试获取的锁名，与夹具实际使用的锁名一致，保证观察的是同一把锁。
     * @var string|null
     */
    private $fixtureLockObserverName;

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
                        return new WithdrawalAccountSnapshot('1000.00', '1000.00');
                    }
                }
            );
        } catch (\Throwable $exception) {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($exception, [
                'clean owner fixture rows' => function (): void {
                    if ($this->hasSharedSystemConfigFixtureLockState()) {
                        $this->cleanupOwnerFixtureRows();
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
            'clean owner fixture rows' => function (): void {
                $this->cleanupOwnerFixtureRows();
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
            'assert fixture lock was released' => function (): void {
                $this->assertFixtureLockWasReleased();
            },
        ]);
    }

    public function test_modern_withdraw_submission_ignores_spoofed_user_id_and_creates_current_user_record(): void
    {
        $viewerId = 412370100;
        $otherId = 412370101;
        $viewerEmail = 'front-withdraw-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-withdraw-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->allowWithdrawalsForTest();
        $this->insertUserInfo($viewerId, 'withdraw-boundary-viewer', $viewerEmail, 1000);
        $this->insertUserInfo($otherId, 'withdraw-boundary-other', $otherEmail, 1000);
        $this->insertUserAuth($viewerId, 'VIEWER-WITHDRAW-BANK', 'Viewer Bank', 'Viewer Branch');
        $this->insertUserAuth($otherId, 'OTHER-WITHDRAW-BANK', 'Other Bank', 'Other Branch');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'owner-boundary-modern')
            ->postJson('/api/front/withdrawals/submissions', [
                'amount' => '120.00',
                'password' => 'password',
                'agree' => true,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $this->assertDatabaseHas('withdraw_records', [
            'user_id' => $viewerId,
            'user_name' => 'withdraw-boundary-viewer',
            'apply_amount' => 120,
            'actual_amount' => 120,
            'fee' => 0,
            'exchange_rate' => 7.2,
            'bank_no' => 'VIEWER-WITHDRAW-BANK',
            'bank_name' => 'Viewer Bank',
            'bank_addr' => 'Viewer Branch',
            'status' => 0,
        ]);
        $this->assertDatabaseMissing('withdraw_records', [
            'user_id' => $otherId,
            'apply_amount' => 120,
            'bank_no' => 'VIEWER-WITHDRAW-BANK',
        ]);
        $this->assertSame(
            0,
            DB::table('withdraw_records')->where('user_id', $otherId)->count()
        );
        $this->assertSame(
            0,
            DB::table('withdraw_settlement_outbox as outbox')
                ->join('withdraw_records as withdraw', 'withdraw.id', '=', 'outbox.withdraw_record_id')
                ->where('withdraw.user_id', $otherId)
                ->count()
        );
        $this->assertStringNotContainsString((string) $otherId, $response->getContent());
        $this->assertStringNotContainsString('OTHER-WITHDRAW-BANK', $response->getContent());
    }

    public function test_legacy_withdraw_request_ignores_spoofed_user_id_and_creates_current_user_record(): void
    {
        $viewerId = 412370200;
        $otherId = 412370201;
        $viewerEmail = 'front-withdraw-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-withdraw-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->allowWithdrawalsForTest();
        $this->insertUserInfo($viewerId, 'withdraw-legacy-viewer', $viewerEmail, 1000);
        $this->insertUserInfo($otherId, 'withdraw-legacy-other', $otherEmail, 1000);
        $this->insertUserAuth($viewerId, 'VIEWER-LEGACY-BANK', 'Viewer Legacy Bank', 'Viewer Legacy Branch');
        $this->insertUserAuth($otherId, 'OTHER-LEGACY-BANK', 'Other Legacy Bank', 'Other Legacy Branch');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->withHeader('Idempotency-Key', 'owner-boundary-legacy')
            ->postJson('/user/withdraw_request', [
                'withdraw_amt' => '130.00',
                'withdraw_password' => 'password',
                'agree' => true,
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $this->assertDatabaseHas('withdraw_records', [
            'user_id' => $viewerId,
            'user_name' => 'withdraw-legacy-viewer',
            'apply_amount' => 130,
            'actual_amount' => 130,
            'fee' => 0,
            'bank_no' => 'VIEWER-LEGACY-BANK',
            'bank_name' => 'Viewer Legacy Bank',
            'status' => 0,
        ]);
        $this->assertDatabaseMissing('withdraw_records', [
            'user_id' => $otherId,
            'apply_amount' => 130,
            'bank_no' => 'VIEWER-LEGACY-BANK',
        ]);
        $this->assertSame(
            0,
            DB::table('withdraw_records')->where('user_id', $otherId)->count()
        );
        $this->assertSame(
            0,
            DB::table('withdraw_settlement_outbox as outbox')
                ->join('withdraw_records as withdraw', 'withdraw.id', '=', 'outbox.withdraw_record_id')
                ->where('withdraw.user_id', $otherId)
                ->count()
        );
    }

    public function test_withdraw_history_ignores_spoofed_user_id_and_returns_current_user_records_only(): void
    {
        $viewerId = 412370300;
        $otherId = 412370301;
        $viewerEmail = 'front-withdraw-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-withdraw-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'withdraw-history-viewer', $viewerEmail, 1000);
        $this->insertUserInfo($otherId, 'withdraw-history-other', $otherEmail, 1000);
        $this->insertWithdrawRecord($viewerId, 'withdraw-history-viewer', 'WDR-BOUNDARY-VIEWER', 210, 205, 'VIEWER-HISTORY-BANK');
        $this->insertWithdrawRecord($otherId, 'withdraw-history-other', 'WDR-BOUNDARY-OTHER', 999, 999, 'OTHER-HISTORY-BANK');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/withdrawals/history?user_id=' . $otherId . '&userId=' . $otherId . '&limit=20');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $rows = $response->json('data.list.data');
        $this->assertCount(1, $rows);
        $this->assertSame($viewerId, (int) $rows[0]['userId']);
        $this->assertSame('WDR-BOUNDARY-VIEWER', $rows[0]['order_no']);
        $this->assertStringNotContainsString('WDR-BOUNDARY-OTHER', $response->getContent());
        $this->assertStringNotContainsString('withdraw-history-other', $response->getContent());
        $response->assertJsonPath('data.totalRow.apply_amount', 210);
        $response->assertJsonPath('data.totalRow.actual_amount', 205);
    }

    public function test_final_checklist_records_withdraw_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 237.', $checklist);
        $this->assertStringContainsString('WithdrawController::submitWithdraw', $checklist);
        $this->assertStringContainsString('WithdrawController::withdraw_request', $checklist);
        $this->assertStringContainsString('WithdrawController::withdrawHistory', $checklist);
        $this->assertStringContainsString('/api/front/withdrawals/submissions', $checklist);
        $this->assertStringContainsString('/api/front/withdrawals/history', $checklist);
        $this->assertStringContainsString('user/withdraw_request', $checklist);
        $this->assertStringContainsString('FrontWithdrawOwnerBoundaryClosureModuleTest', $checklist);
    }

    public function test_owner_fixture_lock_blocks_a_competing_test_connection(): void
    {
        $observer = $this->fixtureObserverConnection('withdraw_owner_fixture_observer');
        $this->fixtureLockObserver = $observer;
        $this->fixtureLockObserverName = $this->sharedSystemConfigFixtureAdvisoryLockName();
        $acquired = $observer->selectOne(
            'SELECT GET_LOCK(?, 0) AS acquired',
            [$this->fixtureLockObserverName]
        );

        $this->assertSame(0, (int) $acquired->acquired);
    }

    public function test_owner_cleanup_fails_fast_for_a_non_fixture_user_collision(): void
    {
        $userId = 412370100;
        $fixtureEmail = 'front-withdraw-boundary-' . $userId . '@example.test';
        $this->insertUserInfo($userId, 'withdraw-boundary-viewer', $fixtureEmail, 1000);
        DB::table('user_logins')->where('user_id', $userId)->update([
            'email' => 'real-owner-collision@example.test',
        ]);
        DB::table('user_infos')->where('user_id', $userId)->update([
            'user_name' => 'real-owner-collision',
        ]);
        $failure = null;

        try {
            $this->deleteFixtureRows([$userId], [$fixtureEmail]);
        } catch (\LogicException $exception) {
            $failure = $exception;
        } finally {
            $withdrawIds = DB::table('withdraw_records')->where('user_id', $userId)->pluck('id');
            DB::table('withdraw_settlement_outbox')->whereIn('withdraw_record_id', $withdrawIds)->delete();
            DB::table('withdraw_records')->where('user_id', $userId)->delete();
            DB::table('user_trades')->where('user_id', $userId)->delete();
            DB::table('user_auths')->where('user_id', $userId)->delete();
            DB::table('user_infos')->where('user_id', $userId)->delete();
            DB::table('user_logins')->where('user_id', $userId)->delete();
        }

        $this->assertInstanceOf(\LogicException::class, $failure);
        $this->assertStringContainsString('non-fixture user', $failure->getMessage());
    }

    private function allowWithdrawalsForTest(): void
    {
        $expectedByKey = [];
        foreach ($this->configSnapshot as $row) {
            $expectedByKey[(string) $row['key']] = $row;
        }
        foreach ([
            'withdrawal_enabled' => '1',
            'withdrawal_weekend_enabled' => '1',
            'withdrawal_start_time' => '',
            'withdrawal_end_time' => '',
            'withdraw_min_amount' => '10',
            'withdraw_max_amount' => '500000',
            'withdraw_risk_rate_limit' => '100',
            'withdraw_check_open' => '0',
            'withdrawal_fee_rate' => '0',
            'withdrawal_fixed_fee_usd' => '0',
            'withdraw_exchange_rate_cny' => '7.2',
        ] as $key => $value) {
            $now = (string) time();
            $attributes = [
                'value' => $value,
                'group' => 'withdraw',
                'description' => 'Front withdraw owner boundary test fixture',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
            $owned = $expectedByKey[$key] ?? null;
            if ($owned === null) {
                $id = DB::table('system_configs')->insertGetId(
                    array_merge(['key' => $key], $attributes)
                );
                $expectedByKey[$key] = array_merge(
                    ['id' => (string) $id, 'key' => $key],
                    $attributes
                );
            } else {
                $expected = array_replace($owned, $attributes);
                if ($expected !== $owned) {
                    $affected = $this->ownerSystemConfigFixtureRowQuery($owned)
                        ->update($attributes);
                    if ($affected !== 1) {
                        throw new \RuntimeException(
                            'Owner config ownership changed before fixture update for key '
                            . $key
                            . '; affected '
                            . $affected
                            . '.'
                        );
                    }
                }
                $expectedByKey[$key] = $expected;
            }

            $this->captureSharedSystemConfigFixtureOwnedState(
                $this->withdrawalConfigKeys(),
                array_values($expectedByKey)
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function ownerSystemConfigFixtureRowQuery(array $row)
    {
        $query = DB::table('system_configs')->useWritePdo();
        foreach ($row as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    /** @return array<int, string> */
    private function withdrawalConfigKeys(): array
    {
        return [
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

    private function restoreWithdrawalConfig(): void
    {
        if ($this->configSnapshot === null) {
            return;
        }

        $this->restoreSharedSystemConfigSnapshot(
            $this->withdrawalConfigKeys(),
            $this->configSnapshot,
            false
        );
    }

    private function cleanupOwnerFixtureRows(): void
    {
        $userIds = [412370100, 412370101, 412370200, 412370201, 412370300, 412370301];
        $emails = array_map(static function (int $userId): string {
            return 'front-withdraw-boundary-' . $userId . '@example.test';
        }, $userIds);
        $this->deleteFixtureRows($userIds, $emails);
    }

    private function insertWithdrawRecord(
        int $userId,
        string $userName,
        string $orderNo,
        float $applyAmount,
        float $actualAmount,
        string $bankNo
    ): void {
        $now = time();

        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'apply_amount' => $applyAmount,
            'actual_amount' => $actualAmount,
            'fee' => $applyAmount - $actualAmount,
            'exchange_rate' => 7.2,
            'rmb_fee' => 0,
            'bank_no' => $bankNo,
            'bank_name' => 'Boundary History Bank',
            'bank_addr' => 'Boundary History Branch',
            'status' => 0,
            'local_order_no' => $orderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'created_by' => $userName,
            'updated_by' => '',
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
            'bank_name' => $bankName,
            'bank_card_img' => '',
            'bank_card_img_tmp' => '',
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

    private function insertUserInfo(int $userId, string $userName, string $email, float $funds): void
    {
        $now = time();

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
            'phone' => '1392370' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => $funds,
            'used_margin' => 0,
            'avail_margin' => $funds,
            'equity' => $funds,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'is_withdrawal_allowed' => 0,
            'is_deposit_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        $this->assertOwnerFixtureUserOwnership($userIds);
        $withdrawIds = DB::table('withdraw_records')->whereIn('user_id', $userIds)->pluck('id');
        if ($withdrawIds->isNotEmpty()) {
            DB::table('withdraw_settlement_outbox')->whereIn('withdraw_record_id', $withdrawIds)->delete();
        }
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }

    /** @param array<int, int> $userIds */
    private function assertOwnerFixtureUserOwnership(array $userIds): void
    {
        $markers = [
            412370100 => ['front-withdraw-boundary-412370100@example.test', 'withdraw-boundary-viewer'],
            412370101 => ['front-withdraw-boundary-412370101@example.test', 'withdraw-boundary-other'],
            412370200 => ['front-withdraw-boundary-412370200@example.test', 'withdraw-legacy-viewer'],
            412370201 => ['front-withdraw-boundary-412370201@example.test', 'withdraw-legacy-other'],
            412370300 => ['front-withdraw-boundary-412370300@example.test', 'withdraw-history-viewer'],
            412370301 => ['front-withdraw-boundary-412370301@example.test', 'withdraw-history-other'],
        ];

        foreach ($userIds as $userId) {
            if (!isset($markers[$userId])) {
                throw new \LogicException('Refusing to delete an unknown withdrawal fixture user ID.');
            }
            [$expectedEmail, $expectedUserName] = $markers[$userId];
            $login = DB::table('user_logins')->where('user_id', $userId)->first();
            if ($login && (string) $login->email !== $expectedEmail) {
                throw new \LogicException('Refusing to delete a non-fixture user login collision.');
            }
            $user = DB::table('user_infos')->where('user_id', $userId)->first();
            if ($user && (string) $user->user_name !== $expectedUserName) {
                throw new \LogicException('Refusing to delete a non-fixture user info collision.');
            }
        }
    }

    private function assertFixtureLockWasReleased(): void
    {
        if (!$this->fixtureLockObserver || !$this->fixtureLockObserverName) {
            return;
        }

        $observer = $this->fixtureLockObserver;
        $lockName = $this->fixtureLockObserverName;
        $this->fixtureLockObserver = null;
        $this->fixtureLockObserverName = null;
        $acquired = null;
        try {
            $acquired = $observer->selectOne(
                'SELECT GET_LOCK(?, 0) AS acquired',
                [$lockName],
                false
            );
            $this->assertSame(1, (int) $acquired->acquired);
        } finally {
            if ($acquired && (int) $acquired->acquired === 1) {
                $observer->selectOne(
                    'SELECT RELEASE_LOCK(?) AS released',
                    [$lockName],
                    false
                );
            }
            $observer->disconnect();
        }
    }

    private function fixtureObserverConnection(string $connection)
    {
        config([
            'database.connections.' . $connection => config(
                'database.connections.' . DB::getDefaultConnection()
            ),
        ]);
        DB::purge($connection);

        $database = DB::connection($connection);
        $database->unsetEventDispatcher();

        return $database;
    }
}
