<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:36
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\ManagesSharedSystemConfigFixtures;
use Tests\TestCase;

/**
 * 出金必需配置迁移与共享快照闭环测试。
 *
 * 文件功能：验证迁移、Seeder、旧配置兼容及共享 system_configs 夹具能够幂等执行并完整恢复现场。
 */
class WithdrawalRequiredConfigMigrationClosureModuleTest extends TestCase
{
    use ManagesSharedSystemConfigFixtures {
        requestSharedSystemConfigFixtureLock as private requestSharedSystemConfigFixtureLockFromTrait;
        requestSharedSystemConfigFixtureLockRelease as private requestSharedSystemConfigFixtureLockReleaseFromTrait;
    }

    /**
     * setUp 捕获的提现必需配置键（managedConfigKeys）原始行快照。
     * tearDown 据此把被迁移/用例改写的 system_configs 恢复为原值；个别用例会临时过滤快照来构造"行已被删"的场景。
     * @var array<int, array<string, mixed>>
     */
    private $configSnapshot = [];

    /**
     * 本夹具拥有处置权的配置行集合。初始与 configSnapshot 相同，随后被 captureKnownOwnedConfigRows 不断收窄，
     * 保证清理与断言只针对夹具自己的行，不碰共享库中他人写入的数据。
     * @var array<int, array<string, mixed>>
     */
    private $ownedConfigRows = [];

    /**
     * restoreConfigSnapshot 已尝试 UPDATE 恢复的 key 清单。断言其等于全部受管键，
     * 证明每个必需键的恢复路径都被真正走到而不是被静默跳过。
     * @var array<int, string>
     */
    private $restoreAttemptedKeys = [];

    /**
     * 模拟恢复 INSERT 失败的目标 key；null 表示不注入失败。
     * 用于驱动"恢复行已缺失需要补插"路径上的异常分支。
     * @var string|null
     */
    private $restoreInsertFailureKey;

    /**
     * 剩余的模拟 INSERT 失败次数（倒计时）。注入一次后清零，保证失败只发生在预设的那一次尝试上。
     * @var int
     */
    private $restoreInsertFailuresRemaining = 0;

    /**
     * 恢复流程按顺序尝试 INSERT 的 key 清单。断言其顺序与重复次数，
     * 验证失败重试确实再次走到了补插这一步。
     * @var array<int, string>
     */
    private $restoreInsertAttemptedKeys = [];

    /**
     * key => 永久失败原因的映射。恢复循环遇到这些 key 直接抛错并聚合进清理失败报告，
     * 验证清理在部分失败时仍会继续处理其余键且最终上报。
     * @var array<string, string>
     */
    private $restorePermanentInsertFailures = [];

    /**
     * 传给 setLegacySource 的旧版 seeder 数据源参数快照，同时供 legacy 替身按表返回行。
     * 记录它便于断言替身返回的数据确实来自用例预设。
     * @var array<int, array<string, mixed>>
     */
    private $legacySourceParams = [];

    /**
     * 覆写 GET_LOCK 的返回值（0 表示获取失败）；null 表示走真实锁。
     * 用例借它模拟获取共享夹具锁失败，验证失败关闭路径；用例结束必须置回 null。
     * @var int|null
     */
    private $sharedFixtureLockAcquireResultOverride;

    /**
     * 覆写 RELEASE_LOCK 的返回值（0 表示释放失败）；null 表示走真实锁。
     * 用例借它模拟释放共享夹具锁失败，验证清理失败上报路径；用例结束必须置回 null。
     * @var int|null
     */
    private $sharedFixtureLockReleaseResultOverride;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acquireSharedSystemConfigFixtureLock();
        try {
            $this->configSnapshot = DB::table('system_configs')
                ->useWritePdo()
                ->whereIn('key', $this->managedConfigKeys())
                ->orderBy('key')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $this->ownedConfigRows = $this->configSnapshot;
            $this->captureSharedSystemConfigFixtureOwnedState(
                $this->managedConfigKeys(),
                $this->ownedConfigRows
            );
        } catch (\Throwable $exception) {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($exception, [
                'release shared system config fixture lock' => function (): void {
                    $this->releaseSharedSystemConfigFixtureLock();
                },
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
            'restore required system config snapshot' => function (): void {
                $this->restoreConfigSnapshot();
            },
            'parent teardown' => function (): void {
                parent::tearDown();
            },
            'release shared system config fixture lock' => function (): void {
                $this->releaseSharedSystemConfigFixtureLock();
            },
        ]);
    }

    public function test_migration_restores_deleted_required_configs_without_overwriting_administrator_values(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'administrator',
            'Administrator-owned active value',
            1710000001,
            1710000002,
            null
        );
        $this->insertConfig(
            'withdraw_check_open',
            '1',
            'administrator',
            'Administrator-owned soft-deleted value',
            1710000011,
            1710000012,
            1710000013
        );

        $migration = $this->requiredConfigMigration();
        $this->runRequiredConfigMigrationUp($migration);

        $this->assertSame(
            $this->requiredKeys(),
            DB::table('system_configs')
                ->whereIn('key', $this->requiredKeys())
                ->whereNull('deleted_at')
                ->orderByRaw("FIELD(`key`, '" . implode("','", $this->requiredKeys()) . "')")
                ->pluck('key')
                ->all()
        );
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '0',
            'group' => 'administrator',
            'description' => 'Administrator-owned active value',
            'created_at' => 1710000001,
            'updated_at' => 1710000002,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdraw_check_open',
            'value' => '1',
            'group' => 'administrator',
            'description' => 'Administrator-owned soft-deleted value',
            'created_at' => 1710000011,
            'deleted_at' => null,
        ]);

        $afterFirstRun = $this->requiredRows();
        $this->runRequiredConfigMigrationUp($migration);

        $this->assertSame($afterFirstRun, $this->requiredRows());
        $this->assertSame(11, count($afterFirstRun));
        foreach ($afterFirstRun as $row) {
            $this->assertMatchesRegularExpression('/^[0-9]{10}$/', (string) $row['created_at']);
            $this->assertMatchesRegularExpression('/^[0-9]{10}$/', (string) $row['updated_at']);
        }
    }

    public function test_migration_defaults_required_switch_values(): void
    {
        $this->clearRequiredConfigs();

        $this->runRequiredConfigMigrationUp();

        $this->assertSame([
            'withdrawal_enabled' => '0',
            'withdrawal_weekend_enabled' => '0',
            'withdraw_check_open' => '1',
        ], DB::table('system_configs')
            ->whereIn('key', [
                'withdrawal_enabled',
                'withdrawal_weekend_enabled',
                'withdraw_check_open',
            ])
            ->orderByRaw("FIELD(`key`, 'withdrawal_enabled', 'withdrawal_weekend_enabled', 'withdraw_check_open')")
            ->pluck('value', 'key')
            ->all());
    }

    public function test_front_demo_seeder_replaces_migration_placeholders(): void
    {
        $this->clearRequiredConfigs();
        $this->runRequiredConfigMigrationUp();

        $this->invokeSeederMethodAndCaptureOwnedState(
            new \Database\Seeders\FrontDemoDataSeeder(),
            'seedSystemConfigs',
            1710000700
        );

        $this->assertMigrationPlaceholdersWereReplaced($this->standardSeederDefaults(), 'Demo ');
    }

    public function test_initial_seeder_replaces_migration_placeholders(): void
    {
        $this->clearRequiredConfigs();
        $this->runRequiredConfigMigrationUp();

        $this->invokeSeederMethodAndCaptureOwnedState(
            new \Database\Seeders\InitialDataSeeder(),
            'seedRequiredWithdrawalConfigs',
            1710000800
        );

        $this->assertMigrationPlaceholdersWereReplaced($this->standardSeederDefaults(), 'Initial ');
    }

    public function test_front_demo_seeder_updates_migration_placeholder_fee_rate(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_fee_rate',
            '8.50',
            'migration',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_fee_rate',
            1710001501,
            1710001502,
            null
        );

        $this->invokeSeederMethodAndCaptureOwnedState(
            new \Database\Seeders\FrontDemoDataSeeder(),
            'seedSystemConfigs',
            1710001510
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_fee_rate',
            'value' => '0',
            'group' => 'finance',
            'description' => 'Demo withdrawal fee rate',
            'created_at' => 1710001501,
            'updated_at' => 1710001510,
            'deleted_at' => null,
        ]);
    }

    public function test_initial_seeder_updates_migration_placeholder_fee_rate(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_fee_rate',
            '8.75',
            'migration',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_fee_rate',
            1710001521,
            1710001522,
            null
        );

        $this->invokeSeederMethodAndCaptureOwnedState(
            new \Database\Seeders\InitialDataSeeder(),
            'seedRequiredWithdrawalConfigs',
            1710001530
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_fee_rate',
            'value' => '0',
            'group' => 'finance',
            'description' => 'Initial withdrawal fee rate',
            'created_at' => 1710001521,
            'updated_at' => 1710001530,
            'deleted_at' => null,
        ]);
    }

    public function test_legacy_reference_seeder_maps_global_withdraw_rule(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'migration',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            1710001541,
            1710001542,
            null
        );
        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource($seeder, $this->validLegacyWithdrawalParams());

        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001550
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '1',
            'group' => 'finance',
            'description' => 'Mapped from GLOBALWITHDRAWRULE',
            'created_at' => 1710001541,
            'updated_at' => 1710001550,
            'deleted_at' => null,
        ]);
    }

    public function test_front_demo_seeder_preserves_administrator_takeover(): void
    {
        $this->assertSeederPreservesAdministratorTakeover(
            new \Database\Seeders\FrontDemoDataSeeder(),
            'seedSystemConfigs',
            1710001580,
            'withdraw_required_config_front_demo_placeholder_racer'
        );
    }

    public function test_initial_seeder_preserves_administrator_takeover(): void
    {
        $this->assertSeederPreservesAdministratorTakeover(
            new \Database\Seeders\InitialDataSeeder(),
            'seedRequiredWithdrawalConfigs',
            1710001580,
            'withdraw_required_config_initial_placeholder_racer'
        );
    }

    public function test_legacy_reference_seeder_preserves_administrator_takeover(): void
    {
        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource($seeder, $this->validLegacyWithdrawalParams());

        $this->assertSeederPreservesAdministratorTakeover(
            $seeder,
            'seedSystemConfigs',
            1710001580,
            'withdraw_required_config_legacy_placeholder_racer'
        );
    }

    public function test_seeder_final_cas_miss_administrator_takeover(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'migration',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            1710001911,
            1710001912,
            null
        );

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_seeder_final_read_racer');
        $targetSelects = 0;
        $takeoverCompleted = false;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$targetSelects,
            &$takeoverCompleted
        ): void {
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($targetSelects <= 4) {
                $racer->table('system_configs')
                    ->where('key', 'withdrawal_enabled')
                    ->update(['updated_at' => 1710001920 + $targetSelects]);
                return;
            }
            if ($targetSelects !== 5) {
                return;
            }

            $takeoverCompleted = true;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update([
                    'value' => '9',
                    'group' => 'administrator',
                    'description' => 'Administrator takeover on final CAS miss',
                    'created_at' => 1710001931,
                    'updated_at' => 1710001932,
                    'deleted_at' => null,
                ]);
        });
        $connection->setEventDispatcher($dispatcher);

        $failure = null;
        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710001940
            );
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710001940, [
            'value' => '9',
            'group' => 'administrator',
            'description' => 'Administrator takeover on final CAS miss',
            'created_at' => 1710001931,
            'updated_at' => 1710001932,
            'deleted_at' => null,
        ], true);
        $this->assertTrue($takeoverCompleted, 'The fifth CAS miss must be won by the administrator.');
        $this->assertNull(
            $failure,
            $failure ? get_class($failure) . ': ' . $failure->getMessage() : ''
        );
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '9',
            'group' => 'administrator',
            'description' => 'Administrator takeover on final CAS miss',
            'created_at' => 1710001931,
            'updated_at' => 1710001932,
            'deleted_at' => null,
        ]);
        $this->assertSame(6, $targetSelects, 'The exhausted CAS loop must perform one final state read.');
    }

    public function test_seeder_unique_race_keeps_administrator_winner(): void
    {
        $this->clearRequiredConfigs();

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_seeder_insert_racer');
        $winnerInserted = false;
        $targetSelects = 0;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$winnerInserted,
            &$targetSelects
        ): void {
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($winnerInserted) {
                return;
            }

            $winnerInserted = true;
            $racer->table('system_configs')->insert([
                'key' => 'withdrawal_enabled',
                'value' => '7',
                'group' => 'administrator',
                'description' => 'Administrator unique-race winner',
                'created_at' => 1710001591,
                'updated_at' => 1710001592,
                'deleted_at' => null,
            ]);
        });
        $connection->setEventDispatcher($dispatcher);

        $failure = null;
        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710001600
            );
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710001600, [
            'value' => '7',
            'group' => 'administrator',
            'description' => 'Administrator unique-race winner',
            'created_at' => 1710001591,
            'updated_at' => 1710001592,
            'deleted_at' => null,
        ], true);
        $this->assertTrue($winnerInserted, 'The racer must insert after the missing-row select.');
        $this->assertNull(
            $failure,
            $failure ? get_class($failure) . ': ' . $failure->getMessage() : ''
        );
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '7',
            'group' => 'administrator',
            'description' => 'Administrator unique-race winner',
            'created_at' => 1710001591,
            'updated_at' => 1710001592,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, DB::table('system_configs')->where('key', 'withdrawal_enabled')->count());
        $this->assertSame(2, $targetSelects, 'A unique-key race must re-read its winner.');
    }

    public function test_seeder_placeholder_unique_race_replaces_placeholder(): void
    {
        $this->clearRequiredConfigs();

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_placeholder_unique_racer');
        $winnerInserted = false;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$winnerInserted
        ): void {
            if ($winnerInserted
                || stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $winnerInserted = true;
            $racer->table('system_configs')->insert([
                'key' => 'withdrawal_enabled',
                'value' => '0',
                'group' => 'finance',
                'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
                'created_at' => 1710001981,
                'updated_at' => 1710001982,
                'deleted_at' => null,
            ]);
        });
        $connection->setEventDispatcher($dispatcher);

        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710001990
            );
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710001990, [
            'value' => '1',
            'group' => 'finance',
            'description' => 'Demo withdrawal switch',
            'created_at' => 1710001981,
            'updated_at' => 1710001990,
            'deleted_at' => null,
        ], true);
        $this->assertTrue($winnerInserted, 'The racer must win with an active migration placeholder.');
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '1',
            'group' => 'finance',
            'description' => 'Demo withdrawal switch',
            'created_at' => 1710001981,
            'updated_at' => 1710001990,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, DB::table('system_configs')->where('key', 'withdrawal_enabled')->count());
    }

    public function test_seeder_byte_level_cas_miss_preserves_migration_row(): void
    {
        $this->clearRequiredConfigs();
        $migrationDescription =
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled';
        $administratorDescription =
            'required withdrawal config added by 2026-07-15 migration: withdrawal_enabled';
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'migration',
            $migrationDescription,
            1710002001,
            1710002002,
            null
        );

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_binary_cas_racer');
        $descriptionChanged = false;
        $targetSelects = 0;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            $administratorDescription,
            &$descriptionChanged,
            &$targetSelects
        ): void {
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($descriptionChanged) {
                return;
            }

            $descriptionChanged = true;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update(['description' => $administratorDescription]);
        });
        $connection->setEventDispatcher($dispatcher);

        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710002010
            );
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710002010, [
            'value' => '0',
            'group' => 'migration',
            'description' => $administratorDescription,
            'created_at' => 1710002001,
            'updated_at' => 1710002002,
            'deleted_at' => null,
        ], true);
        $this->assertTrue($descriptionChanged, 'The racer must change only description bytes.');
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '0',
            'group' => 'migration',
            'description' => $administratorDescription,
            'created_at' => 1710002001,
            'updated_at' => 1710002002,
            'deleted_at' => null,
        ]);
        $this->assertSame(2, $targetSelects, 'A byte-level CAS miss must re-read the current row.');
    }

    public function test_seeder_cas_insert_failure_propagates(): void
    {
        $this->clearRequiredConfigs();
        $expected = $this->systemConfigsKeyUniqueException();
        $seeder = new class($expected) extends \Database\Seeders\FrontDemoDataSeeder {
            /**
             * seeder 替身预埋的插入异常。首次 insert 时抛出，驱动 CAS 插入失败必须原样向上传播的断言。
             * @var \Illuminate\Database\QueryException
             */
            private $insertFailure;

            public function __construct(\Illuminate\Database\QueryException $insertFailure)
            {
                $this->insertFailure = $insertFailure;
            }

            protected function insertRequiredWithdrawalConfigRow(array $attributes): void
            {
                throw $this->insertFailure;
            }
        };

        $actual = null;
        try {
            $this->invokeSeederMethod($seeder, 'seedSystemConfigs', 1710001950);
        } catch (\Illuminate\Database\QueryException $exception) {
            $actual = $exception;
        }

        $this->captureKnownFrontDemoRaceResult(1710001950, null, false);
        $this->assertSame($expected, $actual);
        $this->assertSame(0, DB::table('system_configs')->where('key', 'withdrawal_enabled')->count());
    }

    public function test_seeder_soft_deleted_winner_unique_failure_propagates(): void
    {
        $this->clearRequiredConfigs();
        $expected = $this->systemConfigsKeyUniqueException();
        $racer = $this->independentConnection('withdraw_required_config_seeder_soft_unique_racer');
        $seeder = new class($expected, $racer) extends \Database\Seeders\FrontDemoDataSeeder {
            /**
             * seeder 替身预埋的插入异常。在软删除赢家占位场景下抛出，验证唯一键冲突向上传播。
             * @var \Illuminate\Database\QueryException
             */
            private $insertFailure;

            /**
             * 独立竞态连接。在 seeder 的 CAS 检查与插入之间以另一会话写入软删除行，构造真实并发冲突窗口。
             * @var \Illuminate\Database\Connection
             */
            private $racer;

            public function __construct(
                \Illuminate\Database\QueryException $insertFailure,
                $racer
            ) {
                $this->insertFailure = $insertFailure;
                $this->racer = $racer;
            }

            protected function insertRequiredWithdrawalConfigRow(array $attributes): void
            {
                $this->racer->table('system_configs')->insert([
                    'key' => $attributes['key'],
                    'value' => '4',
                    'group' => 'administrator',
                    'description' => 'Soft-deleted unique-race winner',
                    'created_at' => 1710001961,
                    'updated_at' => 1710001962,
                    'deleted_at' => 1710001963,
                ]);

                throw $this->insertFailure;
            }
        };

        $actual = null;
        try {
            $this->invokeSeederMethod($seeder, 'seedSystemConfigs', 1710001970);
        } catch (\Illuminate\Database\QueryException $exception) {
            $actual = $exception;
        } finally {
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710001970, [
            'value' => '4',
            'group' => 'administrator',
            'description' => 'Soft-deleted unique-race winner',
            'created_at' => 1710001961,
            'updated_at' => 1710001962,
            'deleted_at' => 1710001963,
        ], false);
        $this->assertSame($expected, $actual);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '4',
            'group' => 'administrator',
            'description' => 'Soft-deleted unique-race winner',
            'created_at' => 1710001961,
            'updated_at' => 1710001962,
            'deleted_at' => 1710001963,
        ]);
    }

    public function test_seeder_pending_winner_deleted_before_cas_propagates_unique_failure(): void
    {
        $this->clearRequiredConfigs();
        $expected = $this->systemConfigsKeyUniqueException();
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_pending_unique_missing_racer');
        $targetSelects = 0;
        $targetCasUpdates = 0;
        $winnerDeleted = false;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$targetSelects,
            &$targetCasUpdates,
            &$winnerDeleted
        ): void {
            if (stripos((string) $event->sql, 'update') === 0
                && stripos((string) $event->sql, 'system_configs') !== false
                && in_array('withdrawal_enabled', $event->bindings, true)) {
                $targetCasUpdates++;
                return;
            }
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($targetSelects !== 2) {
                return;
            }

            $winnerDeleted = true;
            $racer->table('system_configs')->where('key', 'withdrawal_enabled')->delete();
        });
        $connection->setEventDispatcher($dispatcher);
        $seeder = new class($expected, $racer) extends \Database\Seeders\FrontDemoDataSeeder {
            /**
             * seeder 替身预埋的插入异常。在 pending 赢家被删除的场景下抛出，验证唯一键冲突传播。
             * @var \Illuminate\Database\QueryException
             */
            private $insertFailure;

            /**
             * 独立竞态连接。在 CAS 检查后、插入前删除 pending 占位行，构造"赢家消失"的并发窗口。
             * @var \Illuminate\Database\Connection
             */
            private $racer;

            public function __construct(
                \Illuminate\Database\QueryException $insertFailure,
                $racer
            ) {
                $this->insertFailure = $insertFailure;
                $this->racer = $racer;
            }

            protected function insertRequiredWithdrawalConfigRow(array $attributes): void
            {
                $this->racer->table('system_configs')->insert([
                    'key' => $attributes['key'],
                    'value' => '0',
                    'group' => 'finance',
                    'description' => 'Required withdrawal config added by 2026-07-15 migration: ' . $attributes['key'],
                    'created_at' => 1710002021,
                    'updated_at' => 1710002022,
                    'deleted_at' => null,
                ]);

                throw $this->insertFailure;
            }
        };

        $actual = null;
        try {
            $this->invokeSeederMethod($seeder, 'seedSystemConfigs', 1710002030);
        } catch (\Illuminate\Database\QueryException $exception) {
            $actual = $exception;
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710002030, null, false);
        $this->assertTrue($winnerDeleted, 'The immediate placeholder winner must become missing before CAS.');
        $this->assertGreaterThanOrEqual(1, $targetCasUpdates, 'The pending winner must reach a real CAS update.');
        $this->assertSame($expected, $actual);
        $this->assertSame(0, DB::table('system_configs')->where('key', 'withdrawal_enabled')->count());
    }

    public function test_seeder_pending_winner_soft_deleted_before_cas_propagates_unique_failure(): void
    {
        $this->clearRequiredConfigs();
        $expected = $this->systemConfigsKeyUniqueException();
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_pending_unique_soft_racer');
        $targetSelects = 0;
        $targetCasUpdates = 0;
        $winnerSoftDeleted = false;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$targetSelects,
            &$targetCasUpdates,
            &$winnerSoftDeleted
        ): void {
            if (stripos((string) $event->sql, 'update') === 0
                && stripos((string) $event->sql, 'system_configs') !== false
                && in_array('withdrawal_enabled', $event->bindings, true)) {
                $targetCasUpdates++;
                return;
            }
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($targetSelects !== 2) {
                return;
            }

            $winnerSoftDeleted = true;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update(['deleted_at' => 1710002043]);
        });
        $connection->setEventDispatcher($dispatcher);
        $seeder = new class($expected, $racer) extends \Database\Seeders\FrontDemoDataSeeder {
            /**
             * seeder 替身预埋的插入异常。在 pending 赢家被软删除的场景下抛出，验证唯一键冲突传播。
             * @var \Illuminate\Database\QueryException
             */
            private $insertFailure;

            /**
             * 独立竞态连接。在 CAS 检查后软删除 pending 占位行，构造"赢家被软删"的并发窗口。
             * @var \Illuminate\Database\Connection
             */
            private $racer;

            public function __construct(
                \Illuminate\Database\QueryException $insertFailure,
                $racer
            ) {
                $this->insertFailure = $insertFailure;
                $this->racer = $racer;
            }

            protected function insertRequiredWithdrawalConfigRow(array $attributes): void
            {
                $this->racer->table('system_configs')->insert([
                    'key' => $attributes['key'],
                    'value' => '0',
                    'group' => 'finance',
                    'description' => 'Required withdrawal config added by 2026-07-15 migration: ' . $attributes['key'],
                    'created_at' => 1710002041,
                    'updated_at' => 1710002042,
                    'deleted_at' => null,
                ]);

                throw $this->insertFailure;
            }
        };

        $actual = null;
        try {
            $this->invokeSeederMethod($seeder, 'seedSystemConfigs', 1710002050);
        } catch (\Illuminate\Database\QueryException $exception) {
            $actual = $exception;
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710002050, [
            'value' => '0',
            'group' => 'finance',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            'created_at' => 1710002041,
            'updated_at' => 1710002042,
            'deleted_at' => 1710002043,
        ], false);
        $this->assertTrue($winnerSoftDeleted, 'The immediate placeholder winner must become soft-deleted before CAS.');
        $this->assertGreaterThanOrEqual(1, $targetCasUpdates, 'The pending winner must reach a real CAS update.');
        $this->assertSame($expected, $actual);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '0',
            'group' => 'finance',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            'created_at' => 1710002041,
            'updated_at' => 1710002042,
            'deleted_at' => 1710002043,
        ]);
    }

    public function test_seeder_cas_exhaustion_propagates_unique_failure(): void
    {
        $this->clearRequiredConfigs();
        $expected = $this->systemConfigsKeyUniqueException();
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_pending_unique_exhaustion_racer');
        $targetSelects = 0;
        $targetCasUpdates = 0;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$targetSelects,
            &$targetCasUpdates
        ): void {
            if (stripos((string) $event->sql, 'update') === 0
                && stripos((string) $event->sql, 'system_configs') !== false
                && in_array('withdrawal_enabled', $event->bindings, true)) {
                $targetCasUpdates++;
                return;
            }
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($targetSelects < 2) {
                return;
            }

            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update(['updated_at' => 1710002070 + $targetSelects]);
        });
        $connection->setEventDispatcher($dispatcher);
        $seeder = new class($expected, $racer) extends \Database\Seeders\FrontDemoDataSeeder {
            /**
             * seeder 替身预埋的插入异常。CAS 重试耗尽后仍抛出，验证耗尽路径也把唯一键冲突原样上抛。
             * @var \Illuminate\Database\QueryException
             */
            private $insertFailure;

            /**
             * 独立竞态连接。每轮 CAS 重试间隙持续写入竞争行，构造重试必然耗尽的场景。
             * @var \Illuminate\Database\Connection
             */
            private $racer;

            public function __construct(
                \Illuminate\Database\QueryException $insertFailure,
                $racer
            ) {
                $this->insertFailure = $insertFailure;
                $this->racer = $racer;
            }

            protected function insertRequiredWithdrawalConfigRow(array $attributes): void
            {
                $this->racer->table('system_configs')->insert([
                    'key' => $attributes['key'],
                    'value' => '0',
                    'group' => 'finance',
                    'description' => 'Required withdrawal config added by 2026-07-15 migration: ' . $attributes['key'],
                    'created_at' => 1710002061,
                    'updated_at' => 1710002062,
                    'deleted_at' => null,
                ]);

                throw $this->insertFailure;
            }
        };

        $actual = null;
        try {
            $this->invokeSeederMethod($seeder, 'seedSystemConfigs', 1710002080);
        } catch (\Illuminate\Database\QueryException $exception) {
            $actual = $exception;
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710002080, [
            'value' => '0',
            'group' => 'finance',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            'created_at' => 1710002061,
            'updated_at' => 1710002077,
            'deleted_at' => null,
        ], false);
        $this->assertSame($expected, $actual);
        $this->assertSame(5, $targetCasUpdates);
        $this->assertSame(7, $targetSelects, 'Five CAS attempts must be followed by one final state read.');
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '0',
            'group' => 'finance',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            'created_at' => 1710002061,
            'updated_at' => 1710002077,
            'deleted_at' => null,
        ]);
    }

    public function test_seeder_tombstone_takeover_on_stale_cas(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '5',
            'administrator',
            'Administrator tombstone before race',
            1710001611,
            1710001612,
            1710001613
        );

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_seeder_tombstone_racer');
        $takeoverCompleted = false;
        $targetSelects = 0;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$takeoverCompleted,
            &$targetSelects
        ): void {
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($takeoverCompleted) {
                return;
            }

            $takeoverCompleted = true;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update([
                    'value' => '6',
                    'group' => 'administrator',
                    'description' => 'Administrator takeover of tombstone',
                    'created_at' => 1710001621,
                    'updated_at' => 1710001622,
                    'deleted_at' => null,
                ]);
        });
        $connection->setEventDispatcher($dispatcher);

        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710001630
            );
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710001630, [
            'value' => '6',
            'group' => 'administrator',
            'description' => 'Administrator takeover of tombstone',
            'created_at' => 1710001621,
            'updated_at' => 1710001622,
            'deleted_at' => null,
        ], true);
        $this->assertTrue($takeoverCompleted, 'The racer must take over the selected tombstone.');
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '6',
            'group' => 'administrator',
            'description' => 'Administrator takeover of tombstone',
            'created_at' => 1710001621,
            'updated_at' => 1710001622,
            'deleted_at' => null,
        ]);
        $this->assertSame(2, $targetSelects, 'A stale tombstone CAS must re-read the current row.');
    }

    public function test_seeder_changed_tombstone_replaced_exactly_once(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '5',
            'administrator',
            'Administrator tombstone before change',
            1710001661,
            1710001662,
            1710001663
        );

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_seeder_changed_tombstone_racer');
        $tombstoneChanged = false;
        $targetSelects = 0;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$tombstoneChanged,
            &$targetSelects
        ): void {
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($tombstoneChanged) {
                return;
            }

            $tombstoneChanged = true;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update([
                    'value' => '6',
                    'group' => 'administrator',
                    'description' => 'Administrator changed tombstone',
                    'created_at' => 1710001671,
                    'updated_at' => 1710001672,
                    'deleted_at' => 1710001673,
                ]);
        });
        $connection->setEventDispatcher($dispatcher);

        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710001680
            );
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710001680, [
            'value' => '6',
            'group' => 'administrator',
            'description' => 'Administrator changed tombstone',
            'created_at' => 1710001671,
            'updated_at' => 1710001680,
            'deleted_at' => null,
        ], true);
        $this->assertTrue($tombstoneChanged, 'The racer must replace the selected tombstone.');
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '6',
            'group' => 'administrator',
            'description' => 'Administrator changed tombstone',
            'created_at' => 1710001671,
            'updated_at' => 1710001680,
            'deleted_at' => null,
        ]);
        $this->assertSame(2, $targetSelects, 'The changed tombstone must be re-read exactly once.');
    }

    public function test_seeder_tombstone_continuous_writes_exhaustion(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '5',
            'administrator',
            'Administrator tombstone under continuous writes',
            1710001691,
            1710001692,
            1710001693
        );

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_seeder_exhaustion_racer');
        $targetSelects = 0;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$targetSelects
        ): void {
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update([
                    'updated_at' => 1710001700 + $targetSelects,
                    'deleted_at' => 1710001800 + $targetSelects,
                ]);
        });
        $connection->setEventDispatcher($dispatcher);

        $failure = null;
        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710001900
            );
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->captureKnownFrontDemoRaceResult(1710001900, [
            'value' => '5',
            'group' => 'administrator',
            'description' => 'Administrator tombstone under continuous writes',
            'created_at' => 1710001691,
            'updated_at' => 1710001706,
            'deleted_at' => 1710001806,
        ], false);
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame(
            'Unable to stabilize required withdrawal config after 5 attempts: withdrawal_enabled',
            $failure->getMessage()
        );
        $this->assertSame(6, $targetSelects);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '5',
            'group' => 'administrator',
            'description' => 'Administrator tombstone under continuous writes',
            'created_at' => 1710001691,
            'updated_at' => 1710001706,
            'deleted_at' => 1710001806,
        ]);
    }

    public function test_seeder_unrelated_insert_error_propagates_without_unique_handling(): void
    {
        $this->clearRequiredConfigs();

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_seeder_unrelated_error_racer');
        $winnerInserted = false;
        $tableLocked = false;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $connection,
            $racer,
            &$winnerInserted,
            &$tableLocked
        ): void {
            if ($winnerInserted
                || stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $winnerInserted = true;
            $racer->table('system_configs')->insert([
                'key' => 'withdrawal_enabled',
                'value' => '8',
                'group' => 'administrator',
                'description' => 'Administrator winner before unrelated insert error',
                'created_at' => 1710001641,
                'updated_at' => 1710001642,
                'deleted_at' => null,
            ]);
            $connection->statement('LOCK TABLES system_configs READ');
            $tableLocked = true;
        });
        $connection->setEventDispatcher($dispatcher);

        $failure = null;
        try {
            $this->invokeSeederMethod(
                new \Database\Seeders\FrontDemoDataSeeder(),
                'seedSystemConfigs',
                1710001650
            );
        } catch (\Illuminate\Database\QueryException $exception) {
            $failure = $exception;
        } finally {
            try {
                if ($tableLocked) {
                    $connection->statement('UNLOCK TABLES');
                }
            } finally {
                if ($originalDispatcher) {
                    $connection->setEventDispatcher($originalDispatcher);
                } else {
                    $connection->unsetEventDispatcher();
                }
                $racer->disconnect();
            }
        }

        $this->captureKnownFrontDemoRaceResult(1710001650, [
            'value' => '8',
            'group' => 'administrator',
            'description' => 'Administrator winner before unrelated insert error',
            'created_at' => 1710001641,
            'updated_at' => 1710001642,
            'deleted_at' => null,
        ], false);
        $this->assertTrue($winnerInserted, 'The racer must make the key readable before the insert error.');
        $this->assertInstanceOf(\Illuminate\Database\QueryException::class, $failure);
        $this->assertNotSame(1062, (int) ($failure->errorInfo[1] ?? 0));
        $this->assertStringNotContainsString(
            'system_configs_key_unique',
            (string) ($failure->errorInfo[2] ?? '')
        );
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '8',
            'group' => 'administrator',
            'description' => 'Administrator winner before unrelated insert error',
            'created_at' => 1710001641,
            'updated_at' => 1710001642,
            'deleted_at' => null,
        ]);
    }

    public function test_front_demo_seeder_preserves_administrator_fee_rate(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_fee_rate',
            '4.25',
            'administrator',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_fee_rate',
            1710001401,
            1710001402,
            1710001403
        );

        $this->invokeSeederMethodAndCaptureOwnedState(
            new \Database\Seeders\FrontDemoDataSeeder(),
            'seedSystemConfigs',
            1710001410
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_fee_rate',
            'value' => '4.25',
            'group' => 'administrator',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_fee_rate',
            'created_at' => 1710001401,
            'updated_at' => 1710001410,
            'deleted_at' => null,
        ]);
    }

    public function test_initial_seeder_preserves_administrator_fee_rate(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_fee_rate',
            '4.50',
            'administrator',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_fee_rate',
            1710001421,
            1710001422,
            1710001423
        );

        $this->invokeSeederMethodAndCaptureOwnedState(
            new \Database\Seeders\InitialDataSeeder(),
            'seedRequiredWithdrawalConfigs',
            1710001430
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_fee_rate',
            'value' => '4.50',
            'group' => 'administrator',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_fee_rate',
            'created_at' => 1710001421,
            'updated_at' => 1710001430,
            'deleted_at' => null,
        ]);
    }

    public function test_legacy_reference_seeder_preserves_administrator_value(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '7',
            'administrator',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            1710001441,
            1710001442,
            1710001443
        );
        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource($seeder, $this->validLegacyWithdrawalParams());

        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001450
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '7',
            'group' => 'administrator',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            'created_at' => 1710001441,
            'updated_at' => 1710001450,
            'deleted_at' => null,
        ]);
    }

    public function test_migration_concurrent_insert_winner_kept(): void
    {
        $this->clearRequiredConfigs();
        $file = database_path('migrations/2026_07_15_000001_ensure_required_withdrawal_configs.php');
        $this->assertFileExists($file);
        require_once $file;

        $migration = new class extends \EnsureRequiredWithdrawalConfigs {
            /**
             * 对 withdrawal_fee_rate 调用 preserveOrRestoreExisting 的次数。第一次检查时以并发者身份插入行，
             * 模拟"并发插入赢家"竞态，断言迁移保留赢家并恢复数据。
             * @var int
             */
            private $targetChecks = 0;

            protected function preserveOrRestoreExisting(string $key, int $now): bool
            {
                if ($key !== 'withdrawal_fee_rate') {
                    return parent::preserveOrRestoreExisting($key, $now);
                }

                $this->targetChecks++;
                if ($this->targetChecks === 1) {
                    DB::table('system_configs')->insert([
                        'key' => $key,
                        'value' => '1.75',
                        'group' => 'concurrent-owner',
                        'description' => 'Concurrent insert winner',
                        'created_at' => 1710000301,
                        'updated_at' => 1710000302,
                        'deleted_at' => null,
                    ]);

                    return false;
                }

                return parent::preserveOrRestoreExisting($key, $now);
            }

            public function targetChecks(): int
            {
                return $this->targetChecks;
            }
        };

        $this->runRequiredConfigMigrationUp($migration, [
            'withdrawal_fee_rate' => [
                'value' => '1.75',
                'group' => 'concurrent-owner',
                'description' => 'Concurrent insert winner',
                'created_at' => 1710000301,
                'updated_at' => 1710000302,
                'deleted_at' => null,
            ],
        ]);

        $this->assertSame(2, $migration->targetChecks());
        $this->assertSame(1, DB::table('system_configs')->where('key', 'withdrawal_fee_rate')->count());
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_fee_rate',
            'value' => '1.75',
            'group' => 'concurrent-owner',
            'description' => 'Concurrent insert winner',
            'created_at' => 1710000301,
            'updated_at' => 1710000302,
            'deleted_at' => null,
        ]);
    }

    public function test_migration_cas_race_preserves_administrator_tombstone(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_fee_rate',
            '3.75',
            'administrator',
            'Administrator-owned stale tombstone value',
            1710001300,
            1710001301,
            1710001302
        );

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection('withdraw_required_config_cas_racer');
        $tombstoneChanged = false;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$tombstoneChanged
        ): void {
            if ($tombstoneChanged
                || stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_fee_rate', $event->bindings, true)) {
                return;
            }

            $tombstoneChanged = true;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_fee_rate')
                ->update(['deleted_at' => 1710001303]);
        });
        $connection->setEventDispatcher($dispatcher);

        try {
            $this->runRequiredConfigMigrationUp();
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $this->assertTrue($tombstoneChanged, 'The racer must change the tombstone after the migration select.');
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_fee_rate',
            'value' => '3.75',
            'group' => 'administrator',
            'description' => 'Administrator-owned stale tombstone value',
            'created_at' => 1710001300,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, DB::table('system_configs')->where('key', 'withdrawal_fee_rate')->count());
    }

    public function test_migration_down_keeps_required_configs(): void
    {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'administrator',
            'Administrator-owned active value',
            1710000101,
            1710000102,
            null
        );
        $this->insertConfig(
            'withdraw_check_open',
            '1',
            'administrator',
            'Administrator-owned soft-deleted value',
            1710000111,
            1710000112,
            1710000113
        );
        $migration = $this->requiredConfigMigration();
        $this->runRequiredConfigMigrationUp($migration);
        $this->updateOwnedConfigRow('withdrawal_fee_rate', [
            'value' => '1.25',
            'updated_at' => time() + 5,
        ]);

        $this->runRequiredConfigMigrationDown($migration);

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '0',
            'description' => 'Administrator-owned active value',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdraw_check_open',
            'value' => '1',
            'description' => 'Administrator-owned soft-deleted value',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_fee_rate',
            'value' => '1.25',
            'deleted_at' => null,
        ]);
        $this->assertSame(3, DB::table('system_configs')->whereIn('key', $this->requiredKeys())->count());
    }

    public function test_front_demo_seeder_idempotent(): void
    {
        $this->deleteOwnedConfigKeys($this->frontDemoConfigKeys());
        $this->insertSeederPreservationFixtures();

        $seeder = new \Database\Seeders\FrontDemoDataSeeder();
        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710000400
        );

        $this->assertStandardSeederResult();
        $afterFirstRun = $this->configRows($this->frontDemoConfigKeys());
        $this->assertCount(17, $afterFirstRun);

        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710000400
        );

        $this->assertSame($afterFirstRun, $this->configRows($this->frontDemoConfigKeys()));
    }

    public function test_initial_seeder_idempotent(): void
    {
        $this->clearRequiredConfigs();
        $this->insertSeederPreservationFixtures();

        $seeder = new \Database\Seeders\InitialDataSeeder();
        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedRequiredWithdrawalConfigs',
            1710000500
        );

        $this->assertStandardSeederResult();
        $afterFirstRun = $this->configRows($this->requiredKeys());

        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedRequiredWithdrawalConfigs',
            1710000500
        );

        $this->assertSame($afterFirstRun, $this->configRows($this->requiredKeys()));
    }

    public function test_restore_owned_state_handles_updated_config_id(): void
    {
        $autoIncrementBefore = $this->systemConfigAutoIncrement();
        $this->assertNotEmpty($this->configSnapshot);
        $snapshot = $this->configSnapshot[0];
        $key = (string) $snapshot['key'];
        $replacementId = $this->unusedSystemConfigFixtureId();

        $this->updateOwnedConfigRow($key, [
            'id' => $replacementId,
            'value' => 'fixture-restore-mutated-value',
            'group' => 'fixture-restore-mutated-group',
            'description' => 'fixture-restore-mutated-description',
            'created_at' => 1700000001,
            'updated_at' => 1700000002,
            'deleted_at' => 1700000003,
        ]);

        $queries = [];
        DB::listen(static function ($event) use (&$queries): void {
            if (stripos((string) $event->sql, 'system_configs') !== false) {
                $queries[] = [
                    'sql' => (string) $event->sql,
                    'bindings' => $event->bindings,
                ];
            }
        });

        $this->restoreConfigSnapshot();

        $deleteQueries = array_values(array_filter(
            $queries,
            static function (array $query) use ($key): bool {
                return preg_match('/^\s*delete\s+/i', $query['sql']) === 1
                    && in_array($key, $query['bindings'], true);
            }
        ));
        $updateQueries = array_values(array_filter(
            $queries,
            static function (array $query) use ($key): bool {
                return preg_match('/^\s*update\s+/i', $query['sql']) === 1
                    && in_array($key, $query['bindings'], true);
            }
        ));

        $this->assertSame([], $deleteQueries, 'Snapshot-present rows must not be deleted during restore.');
        $this->assertCount(1, $updateQueries, 'Snapshot-present rows must be restored with one UPDATE.');
        $this->assertSame(
            $snapshot,
            (array) DB::table('system_configs')->where('key', $key)->first(),
            'The UPDATE must restore every snapshotted field.'
        );
        $this->assertSame($autoIncrementBefore, $this->systemConfigAutoIncrement());
    }

    public function test_restore_owned_state_inserts_missing_snapshot_row(): void
    {
        $autoIncrementBefore = $this->systemConfigAutoIncrement();
        $this->assertNotEmpty($this->configSnapshot);
        $snapshot = $this->configSnapshot[0];
        $key = (string) $snapshot['key'];
        $this->deleteOwnedConfigKeys([$key]);

        $failure = null;
        try {
            $this->restoreConfigSnapshot();
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            if (DB::table('system_configs')->where('key', $key)->count() === 0) {
                $this->insertOwnedConfigRow($snapshot);
            }
        }

        $this->assertNull($failure, 'A missing snapshot row must be restored with INSERT.');
        $this->assertSame(
            $snapshot,
            (array) DB::table('system_configs')->where('key', $key)->first(),
            'INSERT must restore every snapshotted field.'
        );
        $this->assertSame($autoIncrementBefore, $this->systemConfigAutoIncrement());
    }

    public function test_restore_owned_state_retries_transient_insert_failure(): void
    {
        $autoIncrementBefore = $this->systemConfigAutoIncrement();
        $this->assertNotEmpty($this->configSnapshot);
        $snapshot = $this->configSnapshot[0];
        $key = (string) $snapshot['key'];
        $this->deleteOwnedConfigKeys([$key]);
        $this->restoreInsertFailureKey = $key;
        $this->restoreInsertFailuresRemaining = 1;
        $this->restoreInsertAttemptedKeys = [];

        $failure = null;
        try {
            $this->restoreConfigSnapshot();
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            $this->restoreInsertFailureKey = null;
            $this->restoreInsertFailuresRemaining = 0;
            if (DB::table('system_configs')->where('key', $key)->count() === 0) {
                $this->insertOwnedConfigRow($snapshot);
            }
        }

        $this->assertNull($failure, 'A transient INSERT failure must be retried.');
        $this->assertSame([$key, $key], $this->restoreInsertAttemptedKeys);
        $this->assertSame(
            $snapshot,
            (array) DB::table('system_configs')->where('key', $key)->first(),
            'A transient INSERT failure must not leave the snapshot row missing.'
        );
        $this->assertSame($autoIncrementBefore, $this->systemConfigAutoIncrement());
    }

    public function test_restore_owned_state_permanent_insert_failure_fails_closed(): void
    {
        $autoIncrementBefore = $this->systemConfigAutoIncrement();
        $snapshotByKey = [];
        foreach ($this->configSnapshot as $row) {
            $snapshotByKey[(string) $row['key']] = $row;
        }

        $failureKeys = [];
        foreach ($this->managedConfigKeys() as $key) {
            if (array_key_exists($key, $snapshotByKey)) {
                $failureKeys[] = $key;
            }
            if (count($failureKeys) === 2) {
                break;
            }
        }
        $this->assertCount(2, $failureKeys);

        $failureReasons = [
            $failureKeys[0] => 'Simulated permanent INSERT failure for the first config.',
            $failureKeys[1] => 'Simulated permanent INSERT failure for the second config.',
        ];
        $this->deleteOwnedConfigKeys($failureKeys);
        $this->restoreAttemptedKeys = [];
        $this->restoreInsertAttemptedKeys = [];
        $this->restorePermanentInsertFailures = $failureReasons;

        $failure = null;
        try {
            $this->restoreConfigSnapshot();
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            $this->restorePermanentInsertFailures = [];
            foreach ($failureKeys as $key) {
                if (DB::table('system_configs')->where('key', $key)->count() === 0) {
                    $this->insertOwnedConfigRow($snapshotByKey[$key]);
                }
            }
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame($this->managedConfigKeys(), $this->restoreAttemptedKeys);
        $this->assertSame([
            $failureKeys[0],
            $failureKeys[0],
            $failureKeys[1],
            $failureKeys[1],
        ], $this->restoreInsertAttemptedKeys);
        foreach ($failureReasons as $key => $reason) {
            $this->assertStringContainsString($key, $failure->getMessage());
            $this->assertStringContainsString($reason, $failure->getMessage());
        }
        $this->assertStringContainsString('final snapshot mismatch', strtolower($failure->getMessage()));
        $this->assertInstanceOf(\RuntimeException::class, $failure->getPrevious());
        $this->assertSame($failureReasons[$failureKeys[0]], $failure->getPrevious()->getMessage());
        $this->assertSame($autoIncrementBefore, $this->systemConfigAutoIncrement());
    }

    /**
     * 验证快照恢复只删除快照建立后新增的受管配置行。
     *
     * @return void 成功时新增行被精确删除、原始配置和 AUTO_INCREMENT 完整恢复；所有权或删除条件错误时测试失败。
     */
    public function test_restore_owned_state_deletes_rows_added_after_snapshot(): void
    {
        $autoIncrementBefore = $this->systemConfigAutoIncrement();
        $originalSnapshot = $this->configSnapshot;
        $this->assertNotEmpty($originalSnapshot, 'The shared fixture snapshot must contain a managed row.');
        $key = (string) $originalSnapshot[0]['key'];

        // 先按已捕获所有权删除原行，再从本次模拟快照移除该 key；
        // 这样无论真实数据库是否已经补齐全部必需配置，都能稳定构造“快照后新增”的场景。
        $this->deleteOwnedConfigKeys([$key]);
        $this->configSnapshot = array_values(array_filter(
            $originalSnapshot,
            static function (array $row) use ($key): bool {
                return (string) $row['key'] !== $key;
            }
        ));

        $insertedRow = [
            'id' => $this->unusedSystemConfigFixtureId(),
            'key' => $key,
            'value' => 'fixture-added-value',
            'group' => 'fixture-added-group',
            'description' => 'Row added after the shared fixture snapshot.',
            'created_at' => 1700000101,
            'updated_at' => 1700000102,
            'deleted_at' => null,
        ];
        $queries = [];
        DB::listen(static function ($event) use (&$queries): void {
            if (stripos((string) $event->sql, 'system_configs') !== false) {
                $queries[] = [
                    'sql' => (string) $event->sql,
                    'bindings' => $event->bindings,
                ];
            }
        });

        $failure = null;
        $restoreQueries = [];
        $rowCountAfterRestore = null;
        try {
            $this->insertOwnedConfigRow($insertedRow);
            $this->restoreConfigSnapshot();
            $restoreQueries = $queries;
            $rowCountAfterRestore = DB::table('system_configs')->where('key', $key)->count();
        } catch (\Throwable $exception) {
            $failure = $exception;
            $restoreQueries = $queries;
        } finally {
            // 第一轮恢复故意以“缺少 key”的模拟快照为准；断言取证后必须还原真实原始快照，避免污染后续测试或本地数据库。
            $this->configSnapshot = $originalSnapshot;
            $this->restoreConfigSnapshot();
        }

        $deleteQueries = array_values(array_filter(
            $restoreQueries,
            static function (array $query): bool {
                return preg_match('/^\s*delete\s+/i', $query['sql']) === 1;
            }
        ));

        $this->assertNull($failure, 'A row absent from the snapshot must be deleted during restore.');
        $this->assertSame(0, $rowCountAfterRestore);
        $this->assertSame(
            $originalSnapshot[0],
            (array) DB::table('system_configs')->where('key', $key)->first(),
            'The original managed row must be restored after the isolated deletion assertion.'
        );
        $this->assertCount(1, $deleteQueries);
        $this->assertSame([
            (string) $insertedRow['created_at'],
            (string) $insertedRow['description'],
            (string) $insertedRow['group'],
            (string) $insertedRow['id'],
            $key,
            (string) $insertedRow['updated_at'],
            (string) $insertedRow['value'],
        ], $deleteQueries[0]['bindings']);
        $this->assertSame($autoIncrementBefore, $this->systemConfigAutoIncrement());
    }

    public function test_fixture_lock_acquire_failure_fails_closed_and_cleans_state(): void
    {
        $this->assertTrue(
            method_exists($this, 'acquireSharedSystemConfigFixtureLock'),
            'The shared fixture helper must own advisory lock acquisition.'
        );

        $this->releaseSharedSystemConfigFixtureLock();
        $this->sharedFixtureLockAcquireResultOverride = 0;
        $failure = null;
        $stateAfterFailure = true;
        try {
            $this->acquireSharedSystemConfigFixtureLock();
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            $stateAfterFailure = $this->hasSharedSystemConfigFixtureLockState();
            $this->sharedFixtureLockAcquireResultOverride = null;
            $this->acquireSharedSystemConfigFixtureLockAndCaptureOwnedState();
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame('Unable to acquire the shared system config fixture lock.', $failure->getMessage());
        $this->assertFalse($stateAfterFailure);
    }

    public function test_fixture_lock_name_stable_and_held(): void
    {
        $lockName = 'wdr:test-fixture:' . substr(hash('sha256', DB::getDatabaseName()), 0, 40);
        $this->assertSame($lockName, $this->sharedSystemConfigFixtureAdvisoryLockName());

        $connectionName = 'shared_system_config_fixture_lock_contender';
        $contender = $this->independentConnection($connectionName);
        try {
            $result = $contender->selectOne(
                'SELECT GET_LOCK(?, 0) AS acquired',
                [$lockName],
                false
            );
            $this->assertNotNull($result);
            $this->assertSame(0, (int) $result->acquired);
        } finally {
            $contender->disconnect();
            DB::purge($connectionName);
        }
    }

    public function test_fixture_lock_release_failure_fails_closed_and_disconnects(): void
    {
        $connection = $this->sharedSystemConfigFixtureLockConnection;
        $this->assertNotNull($connection);
        $this->sharedFixtureLockReleaseResultOverride = 0;
        $failure = null;
        $stateAfterFailure = true;
        $rawPdoAfterFailure = true;
        try {
            $this->releaseSharedSystemConfigFixtureLock();
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            $stateAfterFailure = $this->hasSharedSystemConfigFixtureLockState();
            $rawPdoAfterFailure = $connection->getRawPdo();
            $this->sharedFixtureLockReleaseResultOverride = null;
            $this->acquireSharedSystemConfigFixtureLockAndCaptureOwnedState();
        }

        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertStringContainsString('advisory lock release failed', $failure->getMessage());
        $this->assertStringContainsString('was not owned by its stored connection', $failure->getMessage());
        $this->assertFalse($stateAfterFailure);
        $this->assertNull($rawPdoAfterFailure);
    }

    public function test_restore_owned_state_restores_auto_increment_after_id_advance(): void
    {
        $this->assertNotEmpty($this->configSnapshot);
        $snapshot = $this->configSnapshot[0];
        $key = (string) $snapshot['key'];
        $before = $this->systemConfigAutoIncrement();
        $this->updateOwnedConfigRow($key, ['id' => $before + 1000]);
        $this->assertGreaterThan($before, $this->systemConfigAutoIncrement());

        $this->restoreConfigSnapshot();

        $this->assertSame($snapshot, (array) DB::table('system_configs')->where('key', $key)->first());
        $this->assertSame($before, $this->systemConfigAutoIncrement());
    }

    public function test_shared_fixture_trait_used_by_front_suites(): void
    {
        foreach ([
            self::class,
            FrontPaymentFakeProviderSmokeTest::class,
            FrontUiRegressionTest::class,
            FrontWithdrawSettlementClosureModuleTest::class,
            FrontWithdrawOwnerBoundaryClosureModuleTest::class,
        ] as $testClass) {
            $this->assertContains(
                ManagesSharedSystemConfigFixtures::class,
                class_uses_recursive($testClass),
                $testClass . ' must use the shared system config fixture manager.'
            );
        }
    }

    public function test_lifecycle_cleanup_collects_all_failures(): void
    {
        $this->assertTrue(
            method_exists($this, 'runSharedSystemConfigFixtureLifecycleCleanup'),
            'The shared fixture manager must expose one lifecycle cleanup runner.'
        );

        $primary = new \RuntimeException('primary setup failure');
        $attempted = [];
        $failure = null;
        try {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($primary, [
                'restore snapshot' => static function () use (&$attempted): void {
                    $attempted[] = 'restore snapshot';
                    throw new \RuntimeException('restore failure');
                },
                'parent teardown' => static function () use (&$attempted): void {
                    $attempted[] = 'parent teardown';
                    throw new \LogicException('parent failure');
                },
                'release lock' => static function () use (&$attempted): void {
                    $attempted[] = 'release lock';
                    throw new \UnexpectedValueException('release failure');
                },
            ]);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        $this->assertSame(
            ['restore snapshot', 'parent teardown', 'release lock'],
            $attempted
        );
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame($primary, $failure->getPrevious());
        foreach ([
            'primary setup failure',
            'restore snapshot',
            'restore failure',
            'parent teardown',
            'parent failure',
            'release lock',
            'release failure',
        ] as $messagePart) {
            $this->assertStringContainsString($messagePart, $failure->getMessage());
        }

        $primaryOnly = new \DomainException('primary-only failure');
        $primaryOnlyAttempted = false;
        $primaryOnlyFailure = null;
        try {
            $this->runSharedSystemConfigFixtureLifecycleCleanup($primaryOnly, [
                'successful cleanup' => static function () use (&$primaryOnlyAttempted): void {
                    $primaryOnlyAttempted = true;
                },
            ]);
        } catch (\Throwable $exception) {
            $primaryOnlyFailure = $exception;
        }
        $this->assertTrue($primaryOnlyAttempted);
        $this->assertSame($primaryOnly, $primaryOnlyFailure);

        $firstCleanupFailure = new \RuntimeException('first cleanup failure');
        $noPrimaryFailure = null;
        try {
            $this->runSharedSystemConfigFixtureLifecycleCleanup(null, [
                'first cleanup' => static function () use ($firstCleanupFailure): void {
                    throw $firstCleanupFailure;
                },
                'second cleanup' => static function (): void {
                    throw new \RuntimeException('second cleanup failure');
                },
            ]);
        } catch (\Throwable $exception) {
            $noPrimaryFailure = $exception;
        }
        $this->assertInstanceOf(\RuntimeException::class, $noPrimaryFailure);
        $this->assertSame($firstCleanupFailure, $noPrimaryFailure->getPrevious());
        $this->assertStringContainsString('first cleanup failure', $noPrimaryFailure->getMessage());
        $this->assertStringContainsString('second cleanup failure', $noPrimaryFailure->getMessage());
    }

    public function test_shared_fixture_suites_wire_lifecycle_cleanup(): void
    {
        foreach ([
            self::class,
            FrontPaymentFakeProviderSmokeTest::class,
            FrontUiRegressionTest::class,
            FrontWithdrawSettlementClosureModuleTest::class,
            FrontWithdrawOwnerBoundaryClosureModuleTest::class,
        ] as $testClass) {
            foreach (['setUp', 'tearDown'] as $method) {
                $reflection = new \ReflectionMethod($testClass, $method);
                $lines = file($reflection->getFileName());
                $source = implode('', array_slice(
                    $lines,
                    $reflection->getStartLine() - 1,
                    $reflection->getEndLine() - $reflection->getStartLine() + 1
                ));
                if (strpos($source, 'runSharedSystemConfigFixtureLifecycleCleanup') !== false) {
                    $this->addToAssertionCount(1);
                    continue;
                }
                $this->assertStringContainsString(
                    'cleanupSettlementFixtureLifecycle',
                    $source,
                    $testClass . '::' . $method . ' must delegate to its lifecycle owner.'
                );
                $wrapper = new \ReflectionMethod(
                    $testClass,
                    'cleanupSettlementFixtureLifecycle'
                );
                $wrapperLines = file($wrapper->getFileName());
                $wrapperSource = implode('', array_slice(
                    $wrapperLines,
                    $wrapper->getStartLine() - 1,
                    $wrapper->getEndLine() - $wrapper->getStartLine() + 1
                ));
                $this->assertStringContainsString(
                    'runSharedSystemConfigFixtureLifecycleCleanup',
                    $wrapperSource,
                    $testClass . ' lifecycle owner must call the common cleanup runner.'
                );
            }
        }
    }

    public function test_front_suite_lifecycle_source_contract(): void
    {
        foreach ([
            FrontWithdrawSettlementClosureModuleTest::class,
            FrontWithdrawOwnerBoundaryClosureModuleTest::class,
        ] as $testClass) {
            $setUp = new \ReflectionMethod($testClass, 'setUp');
            $setUpLines = file($setUp->getFileName());
            $setUpSource = implode('', array_slice(
                $setUpLines,
                $setUp->getStartLine() - 1,
                $setUp->getEndLine() - $setUp->getStartLine() + 1
            ));
            $setUpLifecycleSource = $setUpSource;
            if (strpos($setUpSource, 'cleanupSettlementFixtureLifecycle') !== false) {
                $lifecycle = new \ReflectionMethod(
                    $testClass,
                    'cleanupSettlementFixtureLifecycle'
                );
                $lifecycleLines = file($lifecycle->getFileName());
                $setUpLifecycleSource .= implode('', array_slice(
                    $lifecycleLines,
                    $lifecycle->getStartLine() - 1,
                    $lifecycle->getEndLine() - $lifecycle->getStartLine() + 1
                ));
            }
            foreach ([
                'acquireSharedSystemConfigFixtureLock',
                'runSharedSystemConfigFixtureLifecycleCleanup',
                'restoreWithdrawalConfig',
                'releaseSharedSystemConfigFixtureLock',
            ] as $expectedCall) {
                $this->assertStringContainsString(
                    $expectedCall,
                    $setUpLifecycleSource,
                    $testClass . '::setUp must call ' . $expectedCall . '.'
                );
            }

            $tearDown = new \ReflectionMethod($testClass, 'tearDown');
            $tearDownLines = file($tearDown->getFileName());
            $tearDownSource = implode('', array_slice(
                $tearDownLines,
                $tearDown->getStartLine() - 1,
                $tearDown->getEndLine() - $tearDown->getStartLine() + 1
            ));
            $tearDownLifecycleSource = $tearDownSource;
            if (strpos($tearDownSource, 'cleanupSettlementFixtureLifecycle') !== false) {
                $lifecycle = new \ReflectionMethod(
                    $testClass,
                    'cleanupSettlementFixtureLifecycle'
                );
                $lifecycleLines = file($lifecycle->getFileName());
                $tearDownLifecycleSource .= implode('', array_slice(
                    $lifecycleLines,
                    $lifecycle->getStartLine() - 1,
                    $lifecycle->getEndLine() - $lifecycle->getStartLine() + 1
                ));
            }
            foreach ([
                'runSharedSystemConfigFixtureLifecycleCleanup',
                'restoreWithdrawalConfig',
                'parent::tearDown',
                'releaseSharedSystemConfigFixtureLock',
                'assertFixtureLockWasReleased',
            ] as $expectedCall) {
                $this->assertStringContainsString(
                    $expectedCall,
                    $tearDownLifecycleSource,
                    $testClass . '::tearDown must call ' . $expectedCall . '.'
                );
            }

            $this->assertTrue(
                method_exists($testClass, 'restoreWithdrawalConfig'),
                $testClass . ' must expose one config restore path.'
            );
            $restore = new \ReflectionMethod($testClass, 'restoreWithdrawalConfig');
            $restoreLines = file($restore->getFileName());
            $restoreSource = implode('', array_slice(
                $restoreLines,
                $restore->getStartLine() - 1,
                $restore->getEndLine() - $restore->getStartLine() + 1
            ));
            $this->assertStringContainsString(
                'restoreSharedSystemConfigSnapshot',
                $restoreSource,
                $testClass . ' must delegate config restore to the shared helper.'
            );

            if ($testClass === FrontWithdrawOwnerBoundaryClosureModuleTest::class) {
                $this->assertTrue(
                    strpos($setUpSource, 'cleanupOwnerFixtureRows')
                        < strpos($setUpSource, 'restoreWithdrawalConfig')
                    && strpos($setUpSource, 'restoreWithdrawalConfig')
                        < strpos($setUpSource, 'parent::tearDown')
                    && strpos($setUpSource, 'parent::tearDown')
                        < strpos($setUpSource, 'releaseSharedSystemConfigFixtureLock'),
                    'Owner setup failure cleanup must restore rows, rollback, then release.'
                );
                $this->assertTrue(
                    strpos($tearDownSource, 'cleanupOwnerFixtureRows')
                        < strpos($tearDownSource, 'restoreWithdrawalConfig')
                    && strpos($tearDownSource, 'restoreWithdrawalConfig')
                        < strpos($tearDownSource, 'parent::tearDown')
                    && strpos($tearDownSource, 'parent::tearDown')
                        < strpos($tearDownSource, 'releaseSharedSystemConfigFixtureLock')
                    && strpos($tearDownSource, 'releaseSharedSystemConfigFixtureLock')
                        < strpos($tearDownSource, 'assertFixtureLockWasReleased'),
                    'Owner teardown must restore rows, rollback, release, then run the observer.'
                );
                $this->assertMatchesRegularExpression(
                    '/restoreSharedSystemConfigSnapshot\s*\([^;]+,\s*false\s*\)/s',
                    $restoreSource,
                    'Owner row restore must defer AUTO_INCREMENT until shared lock release.'
                );
            }
        }
    }

    public function test_auto_increment_read_uses_stats_expiry_only_on_mysql8(): void
    {
        $connectionFactory = static function (string $version) {
            return new class($version) {
                /**
                 * 假连接返回的数据库服务器版本号。驱动分支：仅 MySQL 8 才执行 SET information_schema_stats_expiry。
                 * @var string
                 */
                private $version;

                /**
                 * 假连接记录的 statement() SQL 清单。断言 stats_expiry SET 语句的出现次数符合版本分支预期。
                 * @var array<int, string>
                 */
                public $statements = [];

                /**
                 * 假连接记录的 selectOne() SQL 清单。断言版本查询与 information_schema 自增查询恰好各执行一次。
                 * @var array<int, string>
                 */
                public $selects = [];

                public function __construct(string $version)
                {
                    $this->version = $version;
                }

                public function statement(string $sql): bool
                {
                    $this->statements[] = $sql;

                    return true;
                }

                public function selectOne(string $sql, array $bindings = [], bool $useReadPdo = true)
                {
                    $this->selects[] = $sql;
                    if (stripos($sql, 'version()') !== false) {
                        return (object) ['version' => $this->version];
                    }
                    if (stripos($sql, 'information_schema.tables') !== false) {
                        return (object) ['auto_increment' => '224'];
                    }

                    throw new \RuntimeException('Unexpected fake connection SELECT: ' . $sql);
                }
            };
        };

        foreach ([
            ['version' => '5.7.44-log', 'set_count' => 0],
            ['version' => '10.6.18-MariaDB', 'set_count' => 0],
            ['version' => '8.0.12', 'set_count' => 1],
        ] as $case) {
            $connection = $connectionFactory($case['version']);
            $this->assertSame(224, $this->readSharedSystemConfigFixtureAutoIncrement($connection));
            $statsExpiryStatements = array_values(array_filter(
                $connection->statements,
                static function (string $sql): bool {
                    return stripos($sql, 'information_schema_stats_expiry') !== false;
                }
            ));
            $this->assertCount($case['set_count'], $statsExpiryStatements, $case['version']);
            $this->assertCount(2, $connection->selects, $case['version']);
        }

        $invalidConnection = $connectionFactory('unparseable-server-version');
        $failure = null;
        try {
            $this->readSharedSystemConfigFixtureAutoIncrement($invalidConnection);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertStringContainsString('Unable to parse database server version', $failure->getMessage());
    }

    public function test_legacy_seeder_empty_source_falls_back_to_defaults(): void
    {
        $this->clearRequiredConfigs();

        $legacy = new class {
            public function table(string $table)
            {
                return new class($table) {
                    /**
                     * legacy seeder 空数据源替身记住的表名；first() 恒返回 null，模拟旧库无源数据时回退默认值。
                     * @var string
                     */
                    private $table;

                    public function __construct(string $table)
                    {
                        $this->table = $table;
                    }

                    public function where(string $column, $value)
                    {
                        return $this;
                    }

                    public function first()
                    {
                        return null;
                    }

                    public function get()
                    {
                        return collect();
                    }
                };
            }
        };

        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $legacyProperty = new \ReflectionProperty($seeder, 'legacy');
        $legacyProperty->setAccessible(true);
        $legacyProperty->setValue($seeder, $legacy);
        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710000200
        );

        $actual = DB::table('system_configs')
            ->whereIn('key', $this->requiredKeys())
            ->whereNull('deleted_at')
            ->pluck('value', 'key')
            ->all();
        ksort($actual);
        $expected = [
            'withdrawal_enabled' => '0',
            'withdrawal_weekend_enabled' => '0',
            'withdrawal_start_time' => '09:00:00',
            'withdrawal_end_time' => '16:30:00',
            'withdraw_min_amount' => '300',
            'withdraw_max_amount' => '500000',
            'withdraw_risk_rate_limit' => '100',
            'withdraw_check_open' => '1',
            'withdrawal_fee_rate' => '0',
            'withdrawal_fixed_fee_usd' => '0',
            'withdraw_exchange_rate_cny' => '6.8',
        ];
        ksort($expected);

        $this->assertSame($expected, $actual);
    }

    public function test_legacy_seeder_invalid_time_ranges_ignored(): void
    {
        $this->clearRequiredConfigs();
        $this->runRequiredConfigMigrationUp();
        $before = $this->requiredRows();
        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource(
            $seeder,
            $this->legacyWithdrawalParams(null, '', ' ', '')
        );

        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001460
        );

        $this->assertSame($before, $this->requiredRows());
    }

    public function test_legacy_seeder_invalid_ranges_keep_migration_values(): void
    {
        $this->clearRequiredConfigs();
        $this->runRequiredConfigMigrationUp();
        $before = $this->requiredRows();
        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource(
            $seeder,
            $this->legacyWithdrawalParams(
                '2',
                '25:00:00,26:00:00',
                'not-a-range',
                '24:00:00,25:00:00'
            )
        );

        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001470
        );

        $this->assertSame($before, $this->requiredRows());
    }

    public function test_legacy_seeder_maps_deposit_rule_without_legacy_source(): void
    {
        $this->deleteOwnedConfigKeys(['deposit_enabled']);
        $this->insertConfig(
            'deposit_enabled',
            '9',
            'administrator',
            'Administrator active deposit switch',
            1710001481,
            1710001482,
            null
        );
        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource($seeder, []);

        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001490
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'deposit_enabled',
            'value' => '1',
            'group' => 'finance',
            'description' => 'Mapped from GLOBALDEPOSITRULE',
            'created_at' => 1710001490,
            'updated_at' => 1710001490,
            'deleted_at' => null,
        ]);
    }

    public function test_legacy_seeder_preserves_all_administrator_values_idempotent(): void
    {
        $this->clearRequiredConfigs();
        $activeFixtures = [
            ['withdrawal_enabled', '0', 'Administrator active switch', 1710001001, 1710001002],
            ['withdrawal_start_time', '10:15:00', 'Administrator active start', 1710001011, 1710001012],
            ['withdraw_min_amount', '888', 'Administrator active minimum', 1710001021, 1710001022],
            ['withdraw_risk_rate_limit', '66', 'Administrator active risk limit', 1710001031, 1710001032],
            ['withdrawal_fee_rate', '2.25', 'Administrator active fee', 1710001041, 1710001042],
            ['withdraw_exchange_rate_cny', '7.35', 'Administrator active exchange rate', 1710001051, 1710001052],
        ];
        $softDeletedFixtures = [
            ['withdrawal_weekend_enabled', '0', 'Administrator deleted weekend', 1710001061, 1710001062, 1710001063],
            ['withdrawal_end_time', '11:45:00', 'Administrator deleted end', 1710001071, 1710001072, 1710001073],
            ['withdraw_max_amount', '7777', 'Administrator deleted maximum', 1710001081, 1710001082, 1710001083],
            ['withdraw_check_open', '0', 'Administrator deleted open check', 1710001091, 1710001092, 1710001093],
            ['withdrawal_fixed_fee_usd', '9.99', 'Administrator deleted fixed fee', 1710001101, 1710001102, 1710001103],
        ];
        foreach ($activeFixtures as $fixture) {
            $this->insertConfig($fixture[0], $fixture[1], 'administrator', $fixture[2], $fixture[3], $fixture[4], null);
        }
        foreach ($softDeletedFixtures as $fixture) {
            $this->insertConfig(
                $fixture[0],
                $fixture[1],
                'administrator',
                $fixture[2],
                $fixture[3],
                $fixture[4],
                $fixture[5]
            );
        }

        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource($seeder, $this->validLegacyWithdrawalParams());
        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001100
        );

        foreach ($activeFixtures as $fixture) {
            $this->assertDatabaseHas('system_configs', [
                'key' => $fixture[0],
                'value' => $fixture[1],
                'group' => 'administrator',
                'description' => $fixture[2],
                'created_at' => $fixture[3],
                'updated_at' => $fixture[4],
                'deleted_at' => null,
            ]);
        }
        foreach ($softDeletedFixtures as $fixture) {
            $this->assertDatabaseHas('system_configs', [
                'key' => $fixture[0],
                'value' => $fixture[1],
                'group' => 'administrator',
                'description' => $fixture[2],
                'created_at' => $fixture[3],
                'updated_at' => 1710001100,
                'deleted_at' => null,
            ]);
        }
        $this->assertSame(11, DB::table('system_configs')->whereIn('key', $this->requiredKeys())->count());

        $afterFirstRun = $this->configRows($this->requiredKeys());
        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001110
        );

        $this->assertSame($afterFirstRun, $this->configRows($this->requiredKeys()));
    }

    public function test_legacy_seeder_valid_mapping_contract(): void
    {
        $this->clearRequiredConfigs();
        $this->runRequiredConfigMigrationUp();

        $seeder = new \Database\Seeders\LegacyFrontReferenceSeeder();
        $this->setLegacySource($seeder, $this->validLegacyWithdrawalParams());
        $this->invokeSeederMethodAndCaptureOwnedState(
            $seeder,
            'seedSystemConfigs',
            1710001200
        );

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '1',
            'description' => 'Mapped from GLOBALWITHDRAWRULE',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_weekend_enabled',
            'value' => '1',
            'description' => 'Mapped from WITHDRAWRULE weekend ranges',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_start_time',
            'value' => '09:15:00',
            'description' => 'Mapped from WITHDRAWRULE weekday range',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_end_time',
            'value' => '17:45:00',
            'description' => 'Mapped from WITHDRAWRULE weekday range',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdraw_min_amount',
            'value' => '300',
            'description' => 'Legacy withdrawal page minimum amount',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdraw_max_amount',
            'value' => '500000',
            'description' => 'Legacy withdrawal maximum amount fallback',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdraw_check_open',
            'value' => '1',
            'description' => 'Required withdrawal config added by 2026-07-15 migration: withdraw_check_open',
        ]);
    }

    /** @return array<int, string> */
    private function requiredKeys(): array
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

    /** @return array<int, string> */
    private function managedConfigKeys(): array
    {
        return array_values(array_unique(array_merge($this->requiredKeys(), [
            'deposit_enabled',
            'deposit_exchange_rate_cny',
            'deposit_min_amount',
            'deposit_max_amount',
            'deposit_start_time',
            'deposit_end_time',
            'deposit_weekend_enabled',
            'download_pc_url',
            'download_mobile_url',
            'legacy_system_param_GLOBALWITHDRAWRULE',
            'legacy_system_param_WITHDRAWRULE',
        ])));
    }

    /** @return array<int, string> */
    private function frontDemoConfigKeys(): array
    {
        return [
            'deposit_enabled',
            'deposit_exchange_rate_cny',
            'deposit_min_amount',
            'deposit_max_amount',
            'withdrawal_enabled',
            'withdrawal_weekend_enabled',
            'withdrawal_start_time',
            'withdrawal_end_time',
            'withdraw_exchange_rate_cny',
            'withdraw_min_amount',
            'withdraw_max_amount',
            'withdraw_risk_rate_limit',
            'withdraw_check_open',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'download_pc_url',
            'download_mobile_url',
        ];
    }

    private function insertSeederPreservationFixtures(): void
    {
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'administrator',
            'Administrator-owned active value',
            1710000601,
            1710000602,
            null
        );
        $this->insertConfig(
            'withdraw_check_open',
            '1',
            'administrator',
            'Administrator-owned soft-deleted value',
            1710000611,
            1710000612,
            1710000613
        );
    }

    private function assertStandardSeederResult(): void
    {
        $activeKeys = DB::table('system_configs')
            ->whereIn('key', $this->requiredKeys())
            ->whereNull('deleted_at')
            ->orderByRaw("FIELD(`key`, '" . implode("','", $this->requiredKeys()) . "')")
            ->pluck('key')
            ->all();

        $this->assertSame($this->requiredKeys(), $activeKeys);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '0',
            'group' => 'administrator',
            'description' => 'Administrator-owned active value',
            'created_at' => 1710000601,
            'updated_at' => 1710000602,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdraw_check_open',
            'value' => '1',
            'group' => 'administrator',
            'description' => 'Administrator-owned soft-deleted value',
            'created_at' => 1710000611,
            'deleted_at' => null,
        ]);
        $this->assertSame(11, DB::table('system_configs')->whereIn('key', $this->requiredKeys())->count());
    }

    /** @param array<string, string> $expectedValues */
    private function assertMigrationPlaceholdersWereReplaced(array $expectedValues, string $descriptionPrefix): void
    {
        $actualValues = DB::table('system_configs')
            ->whereIn('key', $this->requiredKeys())
            ->pluck('value', 'key')
            ->all();
        ksort($actualValues);
        ksort($expectedValues);

        $this->assertSame($expectedValues, $actualValues);
        $this->assertSame(0, DB::table('system_configs')
            ->whereIn('key', $this->requiredKeys())
            ->where('description', 'like', 'Required withdrawal config added by 2026-07-15 migration:%')
            ->count());
        $this->assertSame(11, DB::table('system_configs')
            ->whereIn('key', $this->requiredKeys())
            ->where('description', 'like', $descriptionPrefix . '%')
            ->count());
    }

    /** @return array<string, string> */
    private function standardSeederDefaults(): array
    {
        return [
            'withdrawal_enabled' => '1',
            'withdrawal_weekend_enabled' => '1',
            'withdrawal_start_time' => '',
            'withdrawal_end_time' => '',
            'withdraw_min_amount' => '50',
            'withdraw_max_amount' => '50000',
            'withdraw_risk_rate_limit' => '50',
            'withdraw_check_open' => '0',
            'withdrawal_fee_rate' => '0',
            'withdrawal_fixed_fee_usd' => '0',
            'withdraw_exchange_rate_cny' => '7.05',
        ];
    }

    private function invokeSeederMethod(object $seeder, string $method, int $now): void
    {
        $reflection = new \ReflectionMethod($seeder, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($seeder, $now);
    }

    private function invokeSeederMethodAndCaptureOwnedState(
        object $seeder,
        string $method,
        int $now
    ): void {
        $beforeRows = $this->ownedConfigRows;
        $this->invokeSeederMethod($seeder, $method, $now);

        if ($seeder instanceof \Database\Seeders\FrontDemoDataSeeder
            && $method === 'seedSystemConfigs') {
            $this->ownedConfigRows =
                $this->captureSharedSystemConfigFixtureOwnedStateAfterFrontDemoSeeder(
                    $this->managedConfigKeys(),
                    $beforeRows,
                    $this->sharedFrontDemoSystemConfigFixtureDefinitions(),
                    $now,
                    $now
                );

            return;
        }
        if ($seeder instanceof \Database\Seeders\InitialDataSeeder
            && $method === 'seedRequiredWithdrawalConfigs') {
            $this->captureRequiredConfigWriterResultForDefinitions(
                $this->initialRequiredConfigDefinitions(),
                $now
            );

            return;
        }
        if ($seeder instanceof \Database\Seeders\LegacyFrontReferenceSeeder
            && $method === 'seedSystemConfigs') {
            $this->captureKnownLegacySystemConfigResult($seeder, $now);

            return;
        }

        throw new \LogicException(
            'No owned-state transition is defined for seeder '
            . get_class($seeder)
            . '::'
            . $method
            . '.'
        );
    }

    private function captureKnownLegacySystemConfigResult(
        object $seeder,
        int $now,
        array $knownOverrides = []
    ): void
    {
        $beforeByKey = $this->ownedConfigRowsByKey();
        $actualByKey = [];
        foreach ($this->configRows($this->managedConfigKeys()) as $row) {
            $actualByKey[(string) $row['key']] = $row;
        }
        $expectedByKey = $beforeByKey;
        $usedIds = [];
        foreach ($beforeByKey as $row) {
            $usedIds[(string) $row['id']] = true;
        }
        $managed = array_fill_keys($this->managedConfigKeys(), true);
        $insertExpected = function (
            string $key,
            string $value,
            string $group,
            string $description
        ) use (&$actualByKey, &$usedIds, $now): array {
            $actual = $actualByKey[$key] ?? null;
            $id = $actual === null ? '' : (string) $actual['id'];
            if (!ctype_digit($id) || isset($usedIds[$id])) {
                throw new \RuntimeException(
                    'Legacy config writer generated an invalid id for ' . $key . '.'
                );
            }
            $usedIds[$id] = true;

            return [
                'id' => $id,
                'key' => $key,
                'value' => $value,
                'group' => $group,
                'description' => $description,
                'created_at' => (string) $now,
                'updated_at' => (string) $now,
                'deleted_at' => null,
            ];
        };
        $upsert = function (
            string $key,
            $value,
            string $group,
            string $description
        ) use (&$expectedByKey, $managed, $insertExpected, $now): void {
            if (!isset($managed[$key])) {
                return;
            }
            $stringValue = $value === null ? null : (string) $value;
            if (isset($expectedByKey[$key])) {
                $expectedByKey[$key] = array_replace($expectedByKey[$key], [
                    'value' => $stringValue,
                    'group' => $group,
                    'description' => $description,
                    'created_at' => (string) $now,
                    'updated_at' => (string) $now,
                    'deleted_at' => null,
                ]);

                return;
            }
            $expectedByKey[$key] = $insertExpected(
                $key,
                (string) $stringValue,
                $group,
                $description
            );
        };
        $required = function (
            string $key,
            $value,
            string $group,
            string $description,
            bool $replace
        ) use (&$expectedByKey, $insertExpected, $now): void {
            $stringValue = $value === null ? null : (string) $value;
            $before = $expectedByKey[$key] ?? null;
            if ($before !== null && $before['deleted_at'] !== null) {
                $expectedByKey[$key] = array_replace($before, [
                    'updated_at' => (string) $now,
                    'deleted_at' => null,
                ]);

                return;
            }
            if ($before !== null) {
                $isPlaceholder = (string) $before['description'] ===
                    'Required withdrawal config added by 2026-07-15 migration: ' . $key;
                if ($isPlaceholder && $replace) {
                    $expectedByKey[$key] = array_replace($before, [
                        'value' => $stringValue,
                        'group' => $group,
                        'description' => $description,
                        'updated_at' => (string) $now,
                        'deleted_at' => null,
                    ]);
                }

                return;
            }
            $expectedByKey[$key] = $insertExpected(
                $key,
                (string) $stringValue,
                $group,
                $description
            );
        };
        $invoke = static function (object $target, string $method, array $arguments) {
            $reflection = new \ReflectionMethod($target, $method);
            $reflection->setAccessible(true);

            return $reflection->invokeArgs($target, $arguments);
        };

        $params = collect($this->legacySourceParams)
            ->map(static function (array $row): object {
                return (object) $row;
            })
            ->keyBy('para_name');
        $withdrawGlobal = $params->get('GLOBALWITHDRAWRULE');
        $withdrawRule = $params->get('WITHDRAWRULE');
        $depositGlobal = $params->get('GLOBALDEPOSITRULE');
        $depositRule = $params->get('DEPOSITRULE');
        $withdrawGlobalValue = $invoke(
            $seeder,
            'validWithdrawalGlobalValue',
            [$withdrawGlobal]
        );
        $withdrawRange = $invoke($seeder, 'validWithdrawalRuleRange', [$withdrawRule]);
        $hasWithdrawGlobal = $withdrawGlobalValue !== null;
        $hasWithdrawRule = $withdrawRange !== null;

        $required('withdraw_exchange_rate_cny', '6.8', 'finance', 'Legacy withdrawal CNY rate', false);
        $required('withdraw_risk_rate_limit', '100', 'finance', 'Legacy withdrawal risk-rate limit', false);
        $required('withdrawal_fixed_fee_usd', '0', 'finance', 'Legacy fixed withdrawal fee', false);
        $required('withdrawal_fee_rate', '0', 'finance', 'Legacy non-OTC withdrawal fee rate', false);

        foreach ($params as $name => $row) {
            $payload = [
                'data0' => $row->para_data0,
                'data1' => $row->para_data1,
                'data2' => $row->para_data2,
                'data3' => $row->para_data3,
                'data4' => $row->para_data4,
                'data5' => $row->para_data5,
                'data6' => $row->para_data6,
                'remark' => $row->para_remark,
            ];
            $upsert(
                'legacy_system_param_' . $name,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                'legacy_param',
                'Imported from hank_zl_data.system_param.' . $name
            );
        }

        $upsert(
            'deposit_enabled',
            $invoke($seeder, 'isLegacyAllowed', [$depositGlobal]) ? '1' : '0',
            'finance',
            'Mapped from GLOBALDEPOSITRULE'
        );
        $required(
            'withdrawal_enabled',
            $withdrawGlobalValue === '0' ? '1' : '0',
            'finance',
            'Mapped from GLOBALWITHDRAWRULE',
            $hasWithdrawGlobal
        );
        [$depositStart, $depositEnd] = $invoke(
            $seeder,
            'ruleRange',
            [$depositRule, '00:00:00,23:59:59']
        );
        [$withdrawStart, $withdrawEnd] = $withdrawRange ?? ['09:00:00', '16:30:00'];
        $upsert('deposit_start_time', $depositStart, 'finance', 'Mapped from DEPOSITRULE weekday range');
        $upsert('deposit_end_time', $depositEnd, 'finance', 'Mapped from DEPOSITRULE weekday range');
        $required('withdrawal_start_time', $withdrawStart, 'finance', 'Mapped from WITHDRAWRULE weekday range', $hasWithdrawRule);
        $required('withdrawal_end_time', $withdrawEnd, 'finance', 'Mapped from WITHDRAWRULE weekday range', $hasWithdrawRule);
        $upsert(
            'deposit_weekend_enabled',
            $invoke($seeder, 'ruleAllowsWeekend', [$depositRule]) ? '1' : '0',
            'finance',
            'Mapped from DEPOSITRULE weekend ranges'
        );
        $required(
            'withdrawal_weekend_enabled',
            $hasWithdrawRule && $invoke($seeder, 'ruleAllowsWeekend', [$withdrawRule]) ? '1' : '0',
            'finance',
            'Mapped from WITHDRAWRULE weekend ranges',
            $hasWithdrawRule
        );
        $required('withdraw_check_open', '1', 'finance', 'Legacy safe default open-position withdrawal check', false);

        $enabledMins = [];
        $enabledMaxes = [];
        for ($id = 1; $id <= 11; ++$id) {
            $row = $params->get('PAYMENT_CHANNEL_' . $id);
            if (!$row || (string) ($row->para_data0 ?? '0') !== '1') {
                continue;
            }
            $min = (float) ($row->para_data1 ?? 0);
            if ($min > 0) {
                $enabledMins[] = $min;
            }
            $enabledMaxes[] = $invoke($seeder, 'legacyChannelMax', [$id]);
        }
        $upsert(
            'deposit_min_amount',
            $enabledMins ? (string) min($enabledMins) : '10',
            'finance',
            'Minimum enabled legacy channel amount'
        );
        $upsert(
            'deposit_max_amount',
            $enabledMaxes ? (string) max($enabledMaxes) : '500000',
            'finance',
            'Maximum enabled legacy channel amount'
        );
        $hasWithdrawalRules = $hasWithdrawGlobal || $hasWithdrawRule;
        $required('withdraw_min_amount', '300', 'finance', 'Legacy withdrawal page minimum amount', $hasWithdrawalRules);
        $required('withdraw_max_amount', '500000', 'finance', 'Legacy withdrawal maximum amount fallback', $hasWithdrawalRules);

        foreach ($knownOverrides as $key => $row) {
            if ($row === null) {
                unset($expectedByKey[$key]);
            } else {
                $expectedByKey[$key] = $row;
            }
        }

        $this->captureKnownOwnedConfigRows(array_values($expectedByKey));
    }

    /**
     * @param array<string, array{value: string, group: string, description: string, replace: bool}> $definitions
     */
    private function captureRequiredConfigWriterResultForDefinitions(
        array $definitions,
        int $now,
        array $knownOverrides = []
    ): void
    {
        $beforeByKey = $this->ownedConfigRowsByKey();
        $actualByKey = [];
        foreach ($this->configRows($this->managedConfigKeys()) as $row) {
            $actualByKey[(string) $row['key']] = $row;
        }
        $usedIds = [];
        foreach ($beforeByKey as $row) {
            $usedIds[(string) $row['id']] = true;
        }
        $expectedByKey = $beforeByKey;
        foreach ($definitions as $key => $definition) {
            $before = $beforeByKey[$key] ?? null;
            if ($before !== null && $before['deleted_at'] !== null) {
                $expectedByKey[$key] = array_replace($before, [
                    'updated_at' => (string) $now,
                    'deleted_at' => null,
                ]);
                continue;
            }
            if ($before !== null) {
                $isPlaceholder = (string) $before['description'] ===
                    'Required withdrawal config added by 2026-07-15 migration: ' . $key;
                if ($isPlaceholder && $definition['replace']) {
                    $expectedByKey[$key] = array_replace($before, [
                        'value' => $definition['value'],
                        'group' => $definition['group'],
                        'description' => $definition['description'],
                        'updated_at' => (string) $now,
                        'deleted_at' => null,
                    ]);
                }
                continue;
            }

            $actual = $actualByKey[$key] ?? null;
            $id = $actual === null ? '' : (string) $actual['id'];
            if (!ctype_digit($id) || isset($usedIds[$id])) {
                throw new \RuntimeException(
                    'Required config writer generated an invalid id for ' . $key . '.'
                );
            }
            $usedIds[$id] = true;
            $expectedByKey[$key] = [
                'id' => $id,
                'key' => $key,
                'value' => $definition['value'],
                'group' => $definition['group'],
                'description' => $definition['description'],
                'created_at' => (string) $now,
                'updated_at' => (string) $now,
                'deleted_at' => null,
            ];
        }
        foreach ($knownOverrides as $key => $row) {
            if ($row === null) {
                unset($expectedByKey[$key]);
            } else {
                $expectedByKey[$key] = $row;
            }
        }
        $this->captureKnownOwnedConfigRows(array_values($expectedByKey));
    }

    /** @return array<string, array{value: string, group: string, description: string, replace: bool}> */
    private function initialRequiredConfigDefinitions(): array
    {
        return [
            'withdrawal_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Initial withdrawal switch', 'replace' => true],
            'withdrawal_weekend_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Initial weekend withdrawal switch', 'replace' => true],
            'withdrawal_start_time' => ['value' => '', 'group' => 'finance', 'description' => 'Initial withdrawal start time', 'replace' => true],
            'withdrawal_end_time' => ['value' => '', 'group' => 'finance', 'description' => 'Initial withdrawal end time', 'replace' => true],
            'withdraw_min_amount' => ['value' => '50', 'group' => 'finance', 'description' => 'Initial minimum withdrawal amount', 'replace' => true],
            'withdraw_max_amount' => ['value' => '50000', 'group' => 'finance', 'description' => 'Initial maximum withdrawal amount', 'replace' => true],
            'withdraw_risk_rate_limit' => ['value' => '50', 'group' => 'finance', 'description' => 'Initial withdrawal risk-rate limit', 'replace' => true],
            'withdraw_check_open' => ['value' => '0', 'group' => 'finance', 'description' => 'Initial open-position withdrawal check', 'replace' => true],
            'withdrawal_fee_rate' => ['value' => '0', 'group' => 'finance', 'description' => 'Initial withdrawal fee rate', 'replace' => true],
            'withdrawal_fixed_fee_usd' => ['value' => '0', 'group' => 'finance', 'description' => 'Initial fixed withdrawal fee', 'replace' => true],
            'withdraw_exchange_rate_cny' => ['value' => '7.05', 'group' => 'finance', 'description' => 'Initial withdrawal CNY rate', 'replace' => true],
        ];
    }

    private function systemConfigsKeyUniqueException(): \Illuminate\Database\QueryException
    {
        $message = "Duplicate entry 'withdrawal_enabled' for key 'system_configs_key_unique'";
        $previous = new \PDOException($message, 1062);
        $previous->errorInfo = ['23000', 1062, $message];

        return new \Illuminate\Database\QueryException(
            'insert into `system_configs`',
            [],
            $previous
        );
    }

    /**
     * 运行指定 seeder 方法后断言管理员接管值被保留。
     *
     * 预先写入活跃与软删除两行管理员接管配置，执行 seeder 后验证管理员
     * value/group/description 不被 seeder 默认值覆盖（软删除行仅复活）。
     */
    private function assertSeederPreservesAdministratorTakeover(
        object $seeder,
        string $method,
        int $now,
        string $markerKey
    ): void {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'administrator',
            'Administrator-owned active value',
            1710001581,
            1710001582,
            null
        );
        $this->insertConfig(
            'withdraw_check_open',
            '1',
            'administrator',
            'Administrator-owned soft-deleted value',
            1710001591,
            1710001592,
            1710001593
        );

        $this->invokeSeederMethodAndCaptureOwnedState($seeder, $method, $now);

        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '0',
            'group' => 'administrator',
            'description' => 'Administrator-owned active value',
            'created_at' => 1710001581,
            'updated_at' => 1710001582,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdraw_check_open',
            'value' => '1',
            'group' => 'administrator',
            'description' => 'Administrator-owned soft-deleted value',
            'created_at' => 1710001591,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->assertSame(
            11,
            DB::table('system_configs')->whereIn('key', $this->requiredKeys())->count()
        );
    }

    private function assertSeederAdministratorTakeover(
        object $seeder,
        string $method,
        int $now,
        string $racerConnectionName
    ): void {
        $this->clearRequiredConfigs();
        $this->insertConfig(
            'withdrawal_enabled',
            '0',
            'migration',
            'Required withdrawal config added by 2026-07-15 migration: withdrawal_enabled',
            1710001561,
            1710001562,
            null
        );

        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $racer = $this->independentConnection($racerConnectionName);
        $takeoverCompleted = false;
        $targetSelects = 0;
        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) use (
            $racer,
            &$takeoverCompleted,
            &$targetSelects
        ): void {
            if (stripos((string) $event->sql, 'select') !== 0
                || stripos((string) $event->sql, 'system_configs') === false
                || !in_array('withdrawal_enabled', $event->bindings, true)) {
                return;
            }

            $targetSelects++;
            if ($takeoverCompleted) {
                return;
            }

            $takeoverCompleted = true;
            $racer->table('system_configs')
                ->where('key', 'withdrawal_enabled')
                ->update([
                    'value' => '9',
                    'group' => 'administrator',
                    'description' => 'Administrator takeover after seeder select',
                    'created_at' => 1710001571,
                    'updated_at' => 1710001572,
                    'deleted_at' => null,
                ]);
        });
        $connection->setEventDispatcher($dispatcher);

        try {
            $this->invokeSeederMethod($seeder, $method, $now);
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
            $racer->disconnect();
        }

        $targetOverride = $this->knownCurrentConfigRow('withdrawal_enabled', [
            'value' => '9',
            'group' => 'administrator',
            'description' => 'Administrator takeover after seeder select',
            'created_at' => 1710001571,
            'updated_at' => 1710001572,
            'deleted_at' => null,
        ]);
        if ($seeder instanceof \Database\Seeders\FrontDemoDataSeeder) {
            $this->ownedConfigRows =
                $this->captureSharedSystemConfigFixtureOwnedStateAfterFrontDemoSeeder(
                    $this->managedConfigKeys(),
                    $this->ownedConfigRows,
                    $this->sharedFrontDemoSystemConfigFixtureDefinitions(),
                    $now,
                    $now,
                    ['withdrawal_enabled' => $targetOverride]
                );
        } elseif ($seeder instanceof \Database\Seeders\InitialDataSeeder) {
            $this->captureRequiredConfigWriterResultForDefinitions(
                $this->initialRequiredConfigDefinitions(),
                $now,
                ['withdrawal_enabled' => $targetOverride]
            );
        } else {
            $this->captureKnownLegacySystemConfigResult(
                $seeder,
                $now,
                ['withdrawal_enabled' => $targetOverride]
            );
        }

        $this->assertTrue($takeoverCompleted, 'The racer must take over after the seeder select.');
        $this->assertDatabaseHas('system_configs', [
            'key' => 'withdrawal_enabled',
            'value' => '9',
            'group' => 'administrator',
            'description' => 'Administrator takeover after seeder select',
            'created_at' => 1710001571,
            'updated_at' => 1710001572,
            'deleted_at' => null,
        ]);
        $this->assertSame(2, $targetSelects, 'A stale CAS must re-read the current owner.');
    }

    /** @param array<int, array<string, mixed>> $params */
    private function setLegacySource(object $seeder, array $params): void
    {
        $this->legacySourceParams = $params;
        $legacy = new class($params) {
            /**
             * legacy 替身构造时捕获的源参数行集合；table() 链据此返回预设数据，模拟旧版数据源。
             * @var array<int, object>
             */
            private $params;

            /** @param array<int, array<string, mixed>> $params */
            public function __construct(array $params)
            {
                $this->params = array_map(static function (array $row): object {
                    return (object) $row;
                }, $params);
            }

            public function table(string $table)
            {
                return new class($table, $this->params) {
                    /**
                     * legacy 替身 query builder 记住的表名，用于按表挑选返回的参数行。
                     * @var string
                     */
                    private $table;

                    /**
                     * legacy 替身 query builder 持有的参数行集合；first() 返回其中与表匹配的行，模拟旧库读出的配置。
                     * @var array<int, object>
                     */
                    private $params;

                    /** @param array<int, object> $params */
                    public function __construct(string $table, array $params)
                    {
                        $this->table = $table;
                        $this->params = $params;
                    }

                    public function where(string $column, $value)
                    {
                        return $this;
                    }

                    public function first()
                    {
                        return null;
                    }

                    public function get()
                    {
                        return $this->table === 'system_param' ? collect($this->params) : collect();
                    }
                };
            }
        };

        $legacyProperty = new \ReflectionProperty($seeder, 'legacy');
        $legacyProperty->setAccessible(true);
        $legacyProperty->setValue($seeder, $legacy);
    }

    private function validLegacyWithdrawalParams(): array
    {
        return $this->legacyWithdrawalParams(
            '0',
            '10:00:00,12:00:00',
            '09:15:00,17:45:00',
            '10:00:00,12:00:00'
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyWithdrawalParams(
        string $globalValue = null,
        string $data0 = null,
        string $data1 = null,
        string $data6 = null
    ): array {
        $defaults = [
            'para_data0' => null,
            'para_data1' => null,
            'para_data2' => null,
            'para_data3' => null,
            'para_data4' => null,
            'para_data5' => null,
            'para_data6' => null,
            'para_remark' => null,
        ];

        return [
            array_merge($defaults, [
                'para_name' => 'GLOBALWITHDRAWRULE',
                'para_data0' => $globalValue,
            ]),
            array_merge($defaults, [
                'para_name' => 'WITHDRAWRULE',
                'para_data0' => $data0,
                'para_data1' => $data1,
                'para_data6' => $data6,
            ]),
        ];
    }

    /**
     * @param array<int, string> $keys
     * @return array<int, array<string, mixed>>
     */
    private function configRows(array $keys): array
    {
        return DB::table('system_configs')
            ->whereIn('key', $keys)
            ->orderBy('key')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->all();
    }

    private function unusedSystemConfigFixtureId(): int
    {
        $usedIds = array_fill_keys(
            array_map('intval', DB::table('system_configs')->pluck('id')->all()),
            true
        );
        $maxId = (int) DB::table('system_configs')->max('id');
        for ($candidate = 1; $candidate < $maxId; ++$candidate) {
            if (!array_key_exists($candidate, $usedIds)) {
                return $candidate;
            }
        }

        $candidate = max($maxId + 1, $this->systemConfigAutoIncrement());
        if (!array_key_exists($candidate, $usedIds)) {
            return $candidate;
        }

        throw new \RuntimeException('Unable to reserve an unused system_configs fixture id.');
    }

    private function systemConfigAutoIncrement(): int
    {
        return $this->readSharedSystemConfigFixtureAutoIncrement(DB::connection());
    }

    private function restoreConfigSnapshot(): void
    {
        $this->restoreSharedSystemConfigSnapshot($this->managedConfigKeys(), $this->configSnapshot);
        $this->captureKnownOwnedConfigRows($this->configSnapshot);
    }

    /** @param array<string, mixed> $row */
    protected function insertSharedSystemConfigSnapshotRow(array $row): void
    {
        $key = (string) $row['key'];
        $this->restoreInsertAttemptedKeys[] = $key;
        if (array_key_exists($key, $this->restorePermanentInsertFailures)) {
            throw new \RuntimeException($this->restorePermanentInsertFailures[$key]);
        }
        if ($this->restoreInsertFailureKey === $key && $this->restoreInsertFailuresRemaining > 0) {
            --$this->restoreInsertFailuresRemaining;
            throw new \RuntimeException('Simulated transient config restore INSERT failure: ' . $key);
        }

        DB::table('system_configs')->insert($row);
    }

    protected function beforeRestoreSharedSystemConfigKey(string $key): void
    {
        $this->restoreAttemptedKeys[] = $key;
    }

    protected function requestSharedSystemConfigFixtureLock($connection, string $lockName)
    {
        if ($this->sharedFixtureLockAcquireResultOverride !== null) {
            return (object) ['acquired' => $this->sharedFixtureLockAcquireResultOverride];
        }

        return $this->requestSharedSystemConfigFixtureLockFromTrait($connection, $lockName);
    }

    protected function requestSharedSystemConfigFixtureLockRelease($connection, string $lockName)
    {
        if ($this->sharedFixtureLockReleaseResultOverride !== null) {
            return (object) ['released' => $this->sharedFixtureLockReleaseResultOverride];
        }

        return $this->requestSharedSystemConfigFixtureLockReleaseFromTrait($connection, $lockName);
    }

    private function requiredConfigMigration()
    {
        $file = database_path('migrations/2026_07_15_000001_ensure_required_withdrawal_configs.php');
        $this->assertFileExists($file);
        require_once $file;

        return new \EnsureRequiredWithdrawalConfigs();
    }

    private function runRequiredConfigMigrationUp(
        $migration = null,
        array $knownOverrideFields = []
    ): void
    {
        $migration = $migration ?? $this->requiredConfigMigration();
        $beforeByKey = $this->ownedConfigRowsByKey();
        $startedAt = time();
        $migration->up();
        $finishedAt = time();
        $actualByKey = [];
        foreach ($this->configRows($this->managedConfigKeys()) as $row) {
            $actualByKey[(string) $row['key']] = $row;
        }
        $changedKeys = [];
        foreach ($this->migrationConfigDefaults() as $key => $_value) {
            if (array_key_exists($key, $knownOverrideFields)) {
                continue;
            }
            $before = $beforeByKey[$key] ?? null;
            if ($before === null || $before['deleted_at'] !== null) {
                $changedKeys[] = $key;
            }
        }
        $now = null;
        foreach ($changedKeys as $key) {
            $actual = $actualByKey[$key] ?? null;
            if ($actual === null || !ctype_digit((string) $actual['updated_at'])) {
                throw new \RuntimeException(
                    'Required config migration did not produce a timestamped row for ' . $key . '.'
                );
            }
            $candidate = (string) $actual['updated_at'];
            if ((int) $candidate < $startedAt || (int) $candidate > $finishedAt) {
                throw new \RuntimeException(
                    'Required config migration timestamp is outside its known window.'
                );
            }
            if ($now !== null && $candidate !== $now) {
                throw new \RuntimeException(
                    'Required config migration used inconsistent timestamps.'
                );
            }
            $now = $candidate;
        }

        $expectedByKey = $beforeByKey;
        $usedIds = [];
        foreach ($beforeByKey as $row) {
            $usedIds[(string) $row['id']] = true;
        }
        foreach ($this->migrationConfigDefaults() as $key => $value) {
            $before = $beforeByKey[$key] ?? null;
            if ($before !== null && $before['deleted_at'] === null) {
                continue;
            }
            if ($before !== null) {
                $expectedByKey[$key] = array_replace($before, [
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
                continue;
            }

            $actual = $actualByKey[$key] ?? null;
            $id = $actual === null ? '' : (string) $actual['id'];
            if (!ctype_digit($id) || isset($usedIds[$id]) || $now === null) {
                throw new \RuntimeException(
                    'Required config migration generated an invalid id for ' . $key . '.'
                );
            }
            $usedIds[$id] = true;
            $expectedByKey[$key] = [
                'id' => $id,
                'key' => $key,
                'value' => $value,
                'group' => 'finance',
                'description' =>
                    'Required withdrawal config added by 2026-07-15 migration: ' . $key,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }
        foreach ($knownOverrideFields as $key => $fields) {
            $expectedByKey[$key] = $this->knownCurrentConfigRow($key, $fields);
        }
        $this->captureKnownOwnedConfigRows(array_values($expectedByKey));
    }

    private function runRequiredConfigMigrationDown($migration): void
    {
        $expectedByKey = $this->ownedConfigRowsByKey();
        foreach ($this->migrationConfigDefaults() as $key => $value) {
            $row = $expectedByKey[$key] ?? null;
            if ($row !== null
                && (string) $row['value'] === $value
                && (string) $row['group'] === 'finance'
                && (string) $row['description'] ===
                    'Required withdrawal config added by 2026-07-15 migration: ' . $key
                && $row['deleted_at'] === null
                && (string) $row['created_at'] === (string) $row['updated_at']) {
                unset($expectedByKey[$key]);
            }
        }

        $migration->down();
        $this->captureKnownOwnedConfigRows(array_values($expectedByKey));
    }

    /** @return array<string, string> */
    private function migrationConfigDefaults(): array
    {
        return [
            'withdrawal_enabled' => '0',
            'withdrawal_weekend_enabled' => '0',
            'withdrawal_start_time' => '',
            'withdrawal_end_time' => '',
            'withdraw_min_amount' => '50',
            'withdraw_max_amount' => '50000',
            'withdraw_risk_rate_limit' => '50',
            'withdraw_check_open' => '1',
            'withdrawal_fee_rate' => '0',
            'withdrawal_fixed_fee_usd' => '0',
            'withdraw_exchange_rate_cny' => '7.05',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function requiredRows(): array
    {
        return DB::table('system_configs')
            ->whereIn('key', $this->requiredKeys())
            ->orderBy('key')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->all();
    }

    private function clearRequiredConfigs(): void
    {
        $this->deleteOwnedConfigKeys($this->requiredKeys());
    }

    private function insertConfig(
        string $key,
        string $value,
        string $group,
        string $description,
        int $createdAt,
        int $updatedAt,
        int $deletedAt = null
    ): void {
        $id = DB::table('system_configs')->insertGetId([
            'key' => $key,
            'value' => $value,
            'group' => $group,
            'description' => $description,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'deleted_at' => $deletedAt,
        ]);
        $byKey = $this->ownedConfigRowsByKey();
        $byKey[$key] = [
            'id' => (string) $id,
            'key' => $key,
            'value' => $value,
            'group' => $group,
            'description' => $description,
            'created_at' => (string) $createdAt,
            'updated_at' => (string) $updatedAt,
            'deleted_at' => $deletedAt === null ? null : (string) $deletedAt,
        ];
        $this->captureKnownOwnedConfigRows(array_values($byKey));
    }

    /** @return array<string, array<string, mixed>> */
    private function ownedConfigRowsByKey(): array
    {
        $byKey = [];
        foreach ($this->ownedConfigRows as $row) {
            $byKey[(string) $row['key']] = $row;
        }

        return $byKey;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function captureKnownOwnedConfigRows(array $rows): void
    {
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) $left['key'], (string) $right['key']);
        });
        $this->captureSharedSystemConfigFixtureOwnedState(
            $this->managedConfigKeys(),
            $rows
        );
        $this->ownedConfigRows = $rows;
    }

    private function recaptureOwnedConfigRows(): void
    {
        $this->acquireSharedSystemConfigFixtureLock();
        $this->captureSharedSystemConfigFixtureOwnedState(
            $this->managedConfigKeys(),
            $this->ownedConfigRows
        );
    }

    /** @param array<int, string> $keys */
    private function deleteOwnedConfigKeys(array $keys): void
    {
        $this->ownedConfigRows = $this->deleteSharedSystemConfigFixtureOwnedRows(
            $this->managedConfigKeys(),
            $this->ownedConfigRows,
            $keys
        );
    }

    /** @param array<string, mixed> $attributes */
    private function updateOwnedConfigRow(string $key, array $attributes): void
    {
        $byKey = $this->ownedConfigRowsByKey();
        if (!isset($byKey[$key])) {
            throw new \LogicException('No owned system config row exists for key ' . $key . '.');
        }
        foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] !== null) {
                $attributes[$column] = (string) $attributes[$column];
            }
        }
        $owned = $byKey[$key];
        $expected = array_replace($owned, $attributes);
        if ($expected !== $owned) {
            $query = DB::table('system_configs')->useWritePdo();
            foreach ($owned as $column => $value) {
                if ($value === null) {
                    $query->whereNull($column);
                } else {
                    $query->where($column, $value);
                }
            }
            $affected = $query->update($attributes);
            if ($affected !== 1) {
                throw new \RuntimeException(
                    'Owned system config row changed before update for key '
                    . $key
                    . '; affected '
                    . $affected
                    . '.'
                );
            }
        }
        $byKey[$key] = $expected;
        $this->captureKnownOwnedConfigRows(array_values($byKey));
    }

    /** @param array<string, mixed> $row */
    private function insertOwnedConfigRow(array $row): void
    {
        if (!array_key_exists('id', $row) || !array_key_exists('key', $row)) {
            throw new \InvalidArgumentException('Known system config insert requires id and key.');
        }
        foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = (string) $row[$column];
            }
        }
        $key = (string) $row['key'];
        $byKey = $this->ownedConfigRowsByKey();
        if (isset($byKey[$key])) {
            throw new \LogicException('An owned system config row already exists for ' . $key . '.');
        }

        DB::table('system_configs')->insert($row);
        $byKey[$key] = $row;
        $this->captureKnownOwnedConfigRows(array_values($byKey));
    }

    /**
     * @param array<string, mixed> $expectedFields
     * @return array<string, mixed>
     */
    private function knownCurrentConfigRow(string $key, array $expectedFields): array
    {
        $row = DB::table('system_configs')->useWritePdo()->where('key', $key)->first();
        if ($row === null) {
            throw new \RuntimeException('Expected known system config row is missing: ' . $key . '.');
        }
        $actual = (array) $row;
        foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            if ($actual[$column] !== null) {
                $actual[$column] = (string) $actual[$column];
            }
            if (array_key_exists($column, $expectedFields)
                && $expectedFields[$column] !== null) {
                $expectedFields[$column] = (string) $expectedFields[$column];
            }
        }
        $expectedFields['key'] = $key;
        foreach ($expectedFields as $column => $value) {
            if (!array_key_exists($column, $actual) || $actual[$column] !== $value) {
                throw new \RuntimeException(
                    'Known system config row mismatch for ' . $key . ' column ' . $column . '.'
                );
            }
        }
        if (array_diff(array_keys($actual), array_merge(['id'], array_keys($expectedFields))) !== []) {
            throw new \InvalidArgumentException(
                'Known system config expectation is not a complete row signature for ' . $key . '.'
            );
        }

        return $actual;
    }

    /** @param array<string, mixed>|null $targetFields */
    private function captureKnownFrontDemoRaceResult(
        int $now,
        array $targetFields = null,
        bool $completed
    ): void {
        $definitions = $this->sharedFrontDemoSystemConfigFixtureDefinitions();
        if (!$completed) {
            $definitions = array_slice($definitions, 0, 4, true);
        }
        $override = $targetFields === null
            ? null
            : $this->knownCurrentConfigRow('withdrawal_enabled', $targetFields);
        $this->ownedConfigRows =
            $this->captureSharedSystemConfigFixtureOwnedStateAfterFrontDemoSeeder(
                $this->managedConfigKeys(),
                $this->ownedConfigRows,
                $definitions,
                $now,
                $now,
                ['withdrawal_enabled' => $override]
            );
    }

    private function independentConnection(string $name)
    {
        config([
            'database.connections.' . $name => config(
                'database.connections.' . DB::getDefaultConnection()
            ),
        ]);
        DB::purge($name);
        $connection = DB::connection($name);
        $connection->unsetEventDispatcher();

        return $connection;
    }
}
