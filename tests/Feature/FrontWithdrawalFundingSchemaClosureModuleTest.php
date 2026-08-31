<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:59
 */

/**
 * FrontWithdrawalFundingSchemaClosureModuleTest
 *
 * 文件功能：
 * - 验证前台出金资金化 schema 加固：迁移加固 withdraw_records 与 outbox、重建旧 MyISAM 行与订单号、合并缺列为单条 ALTER、索引修复与前缀唯一索引、非空非法 outbox 失败关闭、孤儿出金重建 outbox 表。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class FrontWithdrawalFundingSchemaClosureModuleTest extends TestCase
{
    /**
     * 生成当前会话使用的出金 schema 建议锁名称。
     *
     * 逻辑说明：
     * - 默认与历史运行器一致；PHPUNIT_LOCK_SUFFIX 用于避免与
     *   外部校验运行器（co_crmv5_verify 库）的同一全局锁名互相阻塞。
     *
     * @return string 当前会话的 schema 锁名。
     */
    private function schemaLockName(): string
    {
        return 'co_crmv5_front_withdrawal_schema_task1' . (string) getenv('PHPUNIT_LOCK_SUFFIX');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $acquired = (int) DB::selectOne(
            'SELECT GET_LOCK(?, 30) AS acquired',
            [$this->schemaLockName()]
        )->acquired;
        if ($acquired !== 1) {
            $this->fail('Could not acquire the withdrawal schema test lock.');
        }
    }

    protected function tearDown(): void
    {
        try {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$this->schemaLockName()]);
        } finally {
            parent::tearDown();
        }
    }

    public function test_schema_lock_held_by_current_connection(): void
    {
        $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $lockOwner = DB::selectOne("SELECT IS_USED_LOCK('" . $this->schemaLockName() . "') AS owner")->owner;

        $this->assertSame($connectionId, (int) $lockOwner);
    }

    public function test_migration_hardens_withdraw_records_and_outbox_schema(): void
    {
        $this->migration()->up();

        $this->assertSame('InnoDB', $this->engine('withdraw_records'));
        $this->assertSame('InnoDB', $this->engine('withdraw_settlement_outbox'));

        foreach (['apply_amount', 'actual_amount', 'fee', 'rmb_fee'] as $column) {
            $this->assertSame('decimal(18,2)', strtolower((string) $this->column('withdraw_records', $column)->COLUMN_TYPE));
        }
        $this->assertSame('decimal(18,8)', strtolower((string) $this->column('withdraw_records', 'exchange_rate')->COLUMN_TYPE));
        $this->assertSame('datetime', strtolower((string) $this->column('withdraw_records', 'refund_time')->DATA_TYPE));

        foreach (['idempotency_key', 'funding_status', 'funding_payload_hash', 'refund_mt4_ticket', 'funding_error_code'] as $column) {
            $this->assertTrue(Schema::hasColumn('withdraw_records', $column), "Missing withdraw_records.{$column}");
        }

        $this->assertSame(['local_order_no'], $this->indexColumns('withdraw_records', 'withdraw_records_local_order_no_unique'));
        $this->assertSame(0, $this->indexNonUnique('withdraw_records', 'withdraw_records_local_order_no_unique'));
        $this->assertSame(['idempotency_key', 'user_id'], $this->indexColumns('withdraw_records', 'withdraw_records_idempotency_user_unique'));
        $this->assertSame(0, $this->indexNonUnique('withdraw_records', 'withdraw_records_idempotency_user_unique'));

        $id = $this->column('withdraw_settlement_outbox', 'id');
        $this->assertSame('bigint(20) unsigned', strtolower((string) $id->COLUMN_TYPE));
        $this->assertSame('NO', strtoupper((string) $id->IS_NULLABLE));
        $this->assertStringContainsString('auto_increment', strtolower((string) $id->EXTRA));
        $this->assertSame(['id'], $this->primaryColumns('withdraw_settlement_outbox'));
        $this->assertSame(
            ['event_type', 'withdraw_record_id'],
            $this->indexColumns('withdraw_settlement_outbox', 'withdraw_settlement_outbox_event_withdraw_unique')
        );
        $this->assertSame(0, $this->indexNonUnique('withdraw_settlement_outbox', 'withdraw_settlement_outbox_event_withdraw_unique'));
        $this->assertSame(
            ['status', 'available_at'],
            $this->indexColumns('withdraw_settlement_outbox', 'withdraw_settlement_outbox_ready_index')
        );
        $this->assertSame(['local_order_no'], $this->indexColumns('withdraw_settlement_outbox', 'withdraw_settlement_outbox_order_index'));
    }

    public function test_migration_rebuilds_legacy_myisam_rows_and_generates_order_numbers(): void
    {
        $this->migration()->up();

        $this->withRenamedTable('withdraw_records', function (): void {
            DB::statement(
                'CREATE TABLE withdraw_records ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
                . 'user_id INT NOT NULL,'
                . 'local_order_no VARCHAR(200) NULL,'
                . 'apply_amount DECIMAL(12,2) NULL,'
                . 'actual_amount DOUBLE NULL,'
                . 'fee DOUBLE NULL,'
                . 'exchange_rate DOUBLE NULL,'
                . 'rmb_fee DOUBLE NULL'
                . ') ENGINE=MyISAM'
            );
            DB::table('withdraw_records')->insert([
                ['id' => 1, 'user_id' => 99110001, 'local_order_no' => null, 'apply_amount' => null, 'actual_amount' => null, 'fee' => null, 'exchange_rate' => null, 'rmb_fee' => null],
                ['id' => 2, 'user_id' => 99110002, 'local_order_no' => '', 'apply_amount' => null, 'actual_amount' => null, 'fee' => null, 'exchange_rate' => null, 'rmb_fee' => null],
            ]);

            $this->migration()->up();

            $this->assertSame('InnoDB', $this->engine('withdraw_records'));
            $rows = DB::table('withdraw_records')->orderBy('id')->get();
            $this->assertSame('LEGACY-WDR-1', $rows[0]->local_order_no);
            $this->assertSame('LEGACY-WDR-2', $rows[1]->local_order_no);
            $this->assertSame('0.00', (string) $rows[0]->apply_amount);
            $this->assertSame('0.00000000', (string) $rows[0]->exchange_rate);
            $this->assertSame('unknown', $rows[0]->funding_status);
            $this->assertNull($rows[0]->refund_time);
            $this->assertTrue(Schema::hasColumn('withdraw_records', 'idempotency_key'));
            $this->assertTrue(Schema::hasColumn('withdraw_records', 'funding_error_code'));
        });
    }

    /**
     * @dataProvider multipleMissingColumnTableProvider
     */
    public function test_migration_combines_multiple_missing_columns_into_single_alter(
        string $table,
        array $missingColumns
    ): void {
        $this->migration()->up();
        $capture = false;
        $queries = [];
        DB::listen(static function ($query) use (&$capture, &$queries): void {
            if (!$capture) {
                return;
            }
            $queries[] = (string) $query->sql;
        });
        $withdrawRecord = DB::table('withdraw_records')->orderBy('id')->first();
        $this->assertNotNull($withdrawRecord);

        $this->withRenamedTable($table, function () use (
            $table,
            $missingColumns,
            $withdrawRecord,
            &$capture,
            &$queries
        ): void {
            $this->createSafePartialTableWithMultipleMissingColumns($table, $withdrawRecord);
            try {
                $capture = true;
                $this->migration()->up();
            } finally {
                $capture = false;
            }

            $addStatements = $this->columnAddStatements($queries, $table, $missingColumns);
            $this->assertCount(1, $addStatements);
            $statement = $addStatements[0];
            foreach ($missingColumns as $column) {
                $this->assertTrue(
                    strpos($statement, "add {$column} ") !== false
                    || strpos($statement, "add column {$column} ") !== false,
                    "Missing {$column} from combined ALTER: {$statement}"
                );
            }
            $this->assertSame(count($missingColumns), substr_count($statement, ' add '));
        });
    }

    public function multipleMissingColumnTableProvider(): array
    {
        return [
            'withdraw records' => [
                'withdraw_records',
                [
                    'idempotency_key',
                    'funding_status',
                    'funding_payload_hash',
                    'refund_mt4_ticket',
                    'refund_time',
                    'funding_error_code',
                ],
            ],
            'withdraw settlement outbox' => [
                'withdraw_settlement_outbox',
                [
                    'status',
                    'attempts',
                    'available_at',
                    'locked_at',
                    'processed_at',
                    'provider_reference',
                    'last_error_code',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
            ],
        ];
    }

    public function test_migration_trims_oversized_local_order_numbers(): void
    {
        $this->migration()->up();
        $trimmedOrderNo = str_repeat('T', 200);
        $paddedOrderNo = '  ' . $trimmedOrderNo . '  ';

        $this->withRenamedTable('withdraw_records', function () use ($trimmedOrderNo, $paddedOrderNo): void {
            $this->createPermissiveWithdrawPreflightFixture(
                ['local_order_no' => $paddedOrderNo],
                null,
                'funding_error_code'
            );

            $this->withRenamedTable('withdraw_settlement_outbox', function () use ($trimmedOrderNo): void {
                Schema::create('withdraw_settlement_outbox', function (Blueprint $table): void {
                    $table->engine = 'InnoDB';
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('withdraw_record_id');
                    $table->string('local_order_no', 255)->nullable();
                    $table->string('event_type', 50);
                    $table->string('status', 30)->nullable();
                    $table->unsignedInteger('attempts')->nullable();
                    $table->char('payload_hash', 64);
                });
                $outboxId = DB::table('withdraw_settlement_outbox')->insertGetId([
                    'withdraw_record_id' => 99150001,
                    'local_order_no' => null,
                    'event_type' => 'task1_trimmed_recovery',
                    'status' => null,
                    'attempts' => null,
                    'payload_hash' => hash('sha256', 'task1-trimmed-recovery'),
                ]);

                $this->migration()->up();

                $this->assertSame(
                    $trimmedOrderNo,
                    DB::table('withdraw_records')->where('id', 99150001)->value('local_order_no')
                );
                $this->assertSame(
                    $trimmedOrderNo,
                    DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->value('local_order_no')
                );
            });
        });
    }

    public function test_migration_repairs_conflicting_index_definitions(): void
    {
        $this->migration()->up();
        $withdrawIndexes = [
            'withdraw_records_local_order_no_unique',
            'withdraw_records_idempotency_user_unique',
        ];
        $outboxIndexes = [
            'withdraw_settlement_outbox_event_withdraw_unique',
            'withdraw_settlement_outbox_ready_index',
            'withdraw_settlement_outbox_order_index',
        ];

        try {
            foreach ($withdrawIndexes as $index) {
                $this->dropIndexIfPresent('withdraw_records', $index);
            }
            foreach ($outboxIndexes as $index) {
                $this->dropIndexIfPresent('withdraw_settlement_outbox', $index);
            }
            DB::statement('ALTER TABLE withdraw_records ADD INDEX withdraw_records_local_order_no_unique (user_id)');
            DB::statement('ALTER TABLE withdraw_records ADD INDEX withdraw_records_idempotency_user_unique (user_id, idempotency_key)');
            DB::statement('ALTER TABLE withdraw_settlement_outbox ADD INDEX withdraw_settlement_outbox_event_withdraw_unique (withdraw_record_id, event_type)');
            DB::statement('ALTER TABLE withdraw_settlement_outbox ADD INDEX withdraw_settlement_outbox_ready_index (available_at, status)');
            DB::statement('ALTER TABLE withdraw_settlement_outbox ADD INDEX withdraw_settlement_outbox_order_index (status)');

            $this->migration()->up();

            $this->assertSame(['local_order_no'], $this->indexColumns('withdraw_records', 'withdraw_records_local_order_no_unique'));
            $this->assertSame(0, $this->indexNonUnique('withdraw_records', 'withdraw_records_local_order_no_unique'));
            $this->assertSame(['idempotency_key', 'user_id'], $this->indexColumns('withdraw_records', 'withdraw_records_idempotency_user_unique'));
            $this->assertSame(0, $this->indexNonUnique('withdraw_records', 'withdraw_records_idempotency_user_unique'));
            $this->assertSame(['event_type', 'withdraw_record_id'], $this->indexColumns('withdraw_settlement_outbox', 'withdraw_settlement_outbox_event_withdraw_unique'));
            $this->assertSame(['status', 'available_at'], $this->indexColumns('withdraw_settlement_outbox', 'withdraw_settlement_outbox_ready_index'));
            $this->assertSame(['local_order_no'], $this->indexColumns('withdraw_settlement_outbox', 'withdraw_settlement_outbox_order_index'));
        } finally {
            foreach ($withdrawIndexes as $index) {
                $this->dropIndexIfPresent('withdraw_records', $index);
            }
            foreach ($outboxIndexes as $index) {
                $this->dropIndexIfPresent('withdraw_settlement_outbox', $index);
            }
            $this->migration()->up();
        }
    }

    public function test_migration_normalizes_ready_index_and_drops_equivalents(): void
    {
        $required = 'withdraw_settlement_outbox_ready_index';
        $equivalentA = 'withdraw_settlement_outbox_task1_ready_a';
        $equivalentB = 'withdraw_settlement_outbox_task1_ready_b';

        try {
            $this->migration()->up();
            foreach ([$required, $equivalentA, $equivalentB] as $index) {
                $this->dropIndexIfPresent('withdraw_settlement_outbox', $index);
            }
            DB::statement(
                "ALTER TABLE withdraw_settlement_outbox ADD INDEX {$equivalentA} (status, available_at)"
            );
            DB::statement(
                "ALTER TABLE withdraw_settlement_outbox ADD INDEX {$equivalentB} (status, available_at)"
            );

            $this->migration()->up();

            $this->assertSame(
                ['status', 'available_at'],
                $this->indexColumns('withdraw_settlement_outbox', $required)
            );
            $this->assertSame([], $this->indexColumns('withdraw_settlement_outbox', $equivalentA));
            $this->assertSame([], $this->indexColumns('withdraw_settlement_outbox', $equivalentB));
        } finally {
            foreach ([$required, $equivalentA, $equivalentB] as $index) {
                $this->dropIndexIfPresent('withdraw_settlement_outbox', $index);
            }
            $this->migration()->up();
        }
    }

    public function test_prefix_index_repaired_for_local_order_no_unique(): void
    {
        $this->assertPrefixIndexRepaired(
            'withdraw_records',
            'withdraw_records_local_order_no_unique',
            'withdraw_records_local_order_no_unique',
            'ALTER TABLE withdraw_records ADD UNIQUE INDEX withdraw_records_local_order_no_unique (local_order_no(100))',
            ['local_order_no']
        );
    }

    public function test_prefix_index_repaired_for_idempotency_user_unique(): void
    {
        $this->assertPrefixIndexRepaired(
            'withdraw_records',
            'withdraw_records_idempotency_user_unique',
            'withdraw_records_task1_idempotency_prefix_unique',
            'ALTER TABLE withdraw_records ADD UNIQUE INDEX withdraw_records_task1_idempotency_prefix_unique (idempotency_key(50), user_id)',
            ['idempotency_key', 'user_id']
        );
    }

    public function test_prefix_index_repaired_for_event_withdraw_unique(): void
    {
        $this->assertPrefixIndexRepaired(
            'withdraw_settlement_outbox',
            'withdraw_settlement_outbox_event_withdraw_unique',
            'withdraw_settlement_outbox_event_withdraw_unique',
            'ALTER TABLE withdraw_settlement_outbox ADD UNIQUE INDEX withdraw_settlement_outbox_event_withdraw_unique (event_type(25), withdraw_record_id)',
            ['event_type', 'withdraw_record_id']
        );
    }

    public function test_migration_restores_auto_increment_primary_key_when_missing(): void
    {
        $this->migration()->up();

        $this->withRenamedTable('withdraw_settlement_outbox', function (): void {
            Schema::create('withdraw_settlement_outbox', function (Blueprint $table): void {
                $table->string('event_type', 50);
            });

            $this->migration()->up();

            $id = $this->column('withdraw_settlement_outbox', 'id');
            $this->assertSame('bigint(20) unsigned', strtolower((string) $id->COLUMN_TYPE));
            $this->assertSame('NO', strtoupper((string) $id->IS_NULLABLE));
            $this->assertStringContainsString('auto_increment', strtolower((string) $id->EXTRA));
            $this->assertSame(['id'], $this->primaryColumns('withdraw_settlement_outbox'));
        });
    }

    public function test_migration_repairs_empty_legacy_primary_key_outbox(): void
    {
        $this->migration()->up();

        $this->withRenamedTable('withdraw_settlement_outbox', function (): void {
            DB::statement(
                'CREATE TABLE withdraw_settlement_outbox ('
                . 'legacy_key BIGINT UNSIGNED NOT NULL PRIMARY KEY,'
                . 'event_type VARCHAR(50) NOT NULL'
                . ') ENGINE=InnoDB'
            );

            try {
                $this->migration()->up();
            } catch (RuntimeException $exception) {
                $this->fail('Empty outbox legacy primary key should be repairable: ' . $exception->getMessage());
            }

            $id = $this->column('withdraw_settlement_outbox', 'id');
            $this->assertSame('bigint(20) unsigned', strtolower((string) $id->COLUMN_TYPE));
            $this->assertSame('NO', strtoupper((string) $id->IS_NULLABLE));
            $this->assertStringContainsString('auto_increment', strtolower((string) $id->EXTRA));
            $this->assertSame(['id'], $this->primaryColumns('withdraw_settlement_outbox'));
            $this->assertTrue(Schema::hasColumn('withdraw_settlement_outbox', 'legacy_key'));
        });
    }

    public function test_migration_fails_closed_on_nonempty_invalid_outbox_primary_key(): void
    {
        $this->migration()->up();
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->dropColumn('funding_error_code');
        });
        try {
            $this->withRenamedTable('withdraw_settlement_outbox', function (): void {
                DB::statement(
                    'CREATE TABLE withdraw_settlement_outbox ('
                    . 'id INT NULL,'
                    . 'legacy_key INT NOT NULL PRIMARY KEY,'
                    . 'event_type VARCHAR(50) NOT NULL'
                    . ') ENGINE=InnoDB'
                );
                DB::table('withdraw_settlement_outbox')->insert(['id' => 7, 'legacy_key' => 1, 'event_type' => 'withdraw_debit']);

                try {
                    $this->migration()->up();
                    $this->fail('Non-empty outbox with an invalid primary key must fail closed.');
                } catch (RuntimeException $exception) {
                    $this->assertStringContainsString('id', strtolower($exception->getMessage()));
                }
                $this->assertFalse(Schema::hasColumn('withdraw_settlement_outbox', 'withdraw_record_id'));
                $this->assertFalse(Schema::hasColumn('withdraw_records', 'funding_error_code'));
            });
        } finally {
            $this->migration()->up();
        }
    }

    /**
     * @dataProvider requiredOutboxIdentityColumnProvider
     */
    public function test_migration_fails_closed_on_missing_required_outbox_identity_column(string $missingColumn): void
    {
        $this->migration()->up();
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->dropColumn('funding_error_code');
        });

        try {
            $this->withRenamedTable('withdraw_settlement_outbox', function () use ($missingColumn): void {
                $withdrawRecord = DB::table('withdraw_records')->orderBy('id')->first();
                $this->assertNotNull($withdrawRecord);
                $this->createNonemptyOutboxMissingColumn(
                    $missingColumn,
                    (int) $withdrawRecord->id,
                    (string) $withdrawRecord->local_order_no
                );

                $withdrawSchemaBefore = $this->createTableSql('withdraw_records');
                $withdrawRowsBefore = $this->tableRows('withdraw_records');
                $outboxSchemaBefore = $this->createTableSql('withdraw_settlement_outbox');
                $outboxRowsBefore = $this->tableRows('withdraw_settlement_outbox');
                $exception = null;

                try {
                    $this->migration()->up();
                } catch (RuntimeException $caught) {
                    $exception = $caught;
                }

                $this->assertInstanceOf(RuntimeException::class, $exception);
                $this->assertStringContainsString($missingColumn, $exception->getMessage());
                $this->assertSame($withdrawSchemaBefore, $this->createTableSql('withdraw_records'));
                $this->assertSame($withdrawRowsBefore, $this->tableRows('withdraw_records'));
                $this->assertSame($outboxSchemaBefore, $this->createTableSql('withdraw_settlement_outbox'));
                $this->assertSame($outboxRowsBefore, $this->tableRows('withdraw_settlement_outbox'));
            });
        } finally {
            $this->migration()->up();
        }
    }

    public function requiredOutboxIdentityColumnProvider(): array
    {
        return [
            'withdraw record id' => ['withdraw_record_id'],
            'local order number' => ['local_order_no'],
            'event type' => ['event_type'],
            'payload hash' => ['payload_hash'],
        ];
    }

    /**
     * @dataProvider unrecoverableOutboxIdentityValueProvider
     */
    public function test_migration_fails_closed_on_unrecoverable_outbox_identity_value(
        string $invalidColumn,
        $invalidValue,
        bool $useUnmatchedWithdrawRecord = false
    ): void {
        $this->migration()->up();
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->dropColumn('funding_error_code');
        });

        try {
            $this->withRenamedTable('withdraw_settlement_outbox', function () use (
                $invalidColumn,
                $invalidValue,
                $useUnmatchedWithdrawRecord
            ): void {
                $withdrawRecord = DB::table('withdraw_records')->orderBy('id')->first();
                $this->assertNotNull($withdrawRecord);
                $withdrawRecordId = $useUnmatchedWithdrawRecord
                    ? (int) DB::table('withdraw_records')->max('id') + 99140000
                    : (int) $withdrawRecord->id;
                $this->createNonemptyOutboxWithInvalidIdentityValue(
                    $invalidColumn,
                    $invalidValue,
                    $withdrawRecordId,
                    (string) $withdrawRecord->local_order_no
                );

                $this->assertFalse(Schema::hasColumn('withdraw_records', 'funding_error_code'));
                $withdrawSchemaBefore = $this->createTableSql('withdraw_records');
                $withdrawRowsBefore = $this->tableRows('withdraw_records');
                $outboxSchemaBefore = $this->createTableSql('withdraw_settlement_outbox');
                $outboxRowsBefore = $this->tableRows('withdraw_settlement_outbox');
                $exception = null;

                try {
                    $this->migration()->up();
                } catch (RuntimeException $caught) {
                    $exception = $caught;
                }

                $this->assertInstanceOf(RuntimeException::class, $exception);
                $this->assertSame($withdrawSchemaBefore, $this->createTableSql('withdraw_records'));
                $this->assertSame($withdrawRowsBefore, $this->tableRows('withdraw_records'));
                $this->assertSame($outboxSchemaBefore, $this->createTableSql('withdraw_settlement_outbox'));
                $this->assertSame($outboxRowsBefore, $this->tableRows('withdraw_settlement_outbox'));
                $this->assertFalse(Schema::hasColumn('withdraw_records', 'funding_error_code'));
                $this->assertStringContainsString($invalidColumn, $exception->getMessage());
            });
        } finally {
            $this->migration()->up();
        }
    }

    public function unrecoverableOutboxIdentityValueProvider(): array
    {
        return [
            'null withdraw record id' => ['withdraw_record_id', null],
            'zero withdraw record id' => ['withdraw_record_id', 0],
            'null event type' => ['event_type', null],
            'blank event type' => ['event_type', '   '],
            'null payload hash' => ['payload_hash', null],
            'blank payload hash' => ['payload_hash', '   '],
            'null local order without matching withdrawal' => ['local_order_no', null, true],
        ];
    }

    /**
     * @dataProvider invalidWithdrawPreflightProvider
     */
    public function test_invalid_withdraw_preflight_rows_fail_closed(
        string $expectedContract,
        array $firstOverrides,
        array $secondOverrides = null,
        string $sentinelColumn = 'funding_error_code'
    ): void {
        $this->migration()->up();

        $this->withRenamedTable('withdraw_records', function () use (
            $expectedContract,
            $firstOverrides,
            $secondOverrides,
            $sentinelColumn
        ): void {
            $this->createPermissiveWithdrawPreflightFixture(
                $firstOverrides,
                $secondOverrides,
                $sentinelColumn
            );
            $this->assertFalse(Schema::hasColumn('withdraw_records', $sentinelColumn));
            $withdrawSchemaBefore = $this->createTableSql('withdraw_records');
            $withdrawRowsBefore = $this->tableRows('withdraw_records');
            $outboxSchemaBefore = $this->createTableSql('withdraw_settlement_outbox');
            $outboxRowsBefore = $this->tableRows('withdraw_settlement_outbox');
            $exception = null;

            try {
                $this->migration()->up();
            } catch (\Throwable $caught) {
                $exception = $caught;
            }

            $this->assertInstanceOf(RuntimeException::class, $exception);
            $this->assertSame($withdrawSchemaBefore, $this->createTableSql('withdraw_records'));
            $this->assertSame($withdrawRowsBefore, $this->tableRows('withdraw_records'));
            $this->assertSame($outboxSchemaBefore, $this->createTableSql('withdraw_settlement_outbox'));
            $this->assertSame($outboxRowsBefore, $this->tableRows('withdraw_settlement_outbox'));
            $this->assertFalse(Schema::hasColumn('withdraw_records', $sentinelColumn));
            $this->assertStringContainsString('withdraw_records', $exception->getMessage());
            $this->assertStringContainsString($expectedContract, $exception->getMessage());
        });
    }

    public function invalidWithdrawPreflightProvider(): array
    {
        $decimalOverflow = '10000000000000000';
        $decimalScaleOverflow = '1.001';

        return [
            'apply amount integer overflow' => ['apply_amount', ['apply_amount' => $decimalOverflow]],
            'apply amount scale overflow' => ['apply_amount', ['apply_amount' => $decimalScaleOverflow]],
            'actual amount integer overflow' => ['actual_amount', ['actual_amount' => $decimalOverflow]],
            'actual amount scale overflow' => ['actual_amount', ['actual_amount' => $decimalScaleOverflow]],
            'fee integer overflow' => ['fee', ['fee' => $decimalOverflow]],
            'fee scale overflow' => ['fee', ['fee' => $decimalScaleOverflow]],
            'rmb fee integer overflow' => ['rmb_fee', ['rmb_fee' => $decimalOverflow]],
            'rmb fee scale overflow' => ['rmb_fee', ['rmb_fee' => $decimalScaleOverflow]],
            'exchange rate integer overflow' => ['exchange_rate', ['exchange_rate' => '10000000000']],
            'exchange rate scale overflow' => ['exchange_rate', ['exchange_rate' => '1.000000001']],
            'trimmed local order too long' => ['local_order_no', ['local_order_no' => str_repeat('L', 201)]],
            'case insensitive trimmed local order duplicate' => [
                'local_order_no',
                ['local_order_no' => 'Task1-Duplicate'],
                ['local_order_no' => ' task1-duplicate '],
            ],
            'generated legacy local order conflict' => [
                'LEGACY-WDR',
                ['local_order_no' => null],
                ['local_order_no' => 'legacy-wdr-99150001'],
            ],
            'idempotency key too long' => ['idempotency_key', ['idempotency_key' => str_repeat('I', 101)]],
            'duplicate user idempotency key' => [
                'idempotency_key',
                ['user_id' => 99150001, 'idempotency_key' => 'task1-duplicate-key'],
                ['user_id' => 99150001, 'idempotency_key' => 'task1-duplicate-key'],
            ],
            'funding status too long' => ['funding_status', ['funding_status' => str_repeat('S', 31)]],
            'funding payload hash is not hexadecimal' => ['funding_payload_hash', ['funding_payload_hash' => 'not-a-sha256-hash']],
            'refund mt4 ticket is negative' => ['refund_mt4_ticket', ['refund_mt4_ticket' => '-1']],
            'funding error code too long' => [
                'funding_error_code',
                ['funding_error_code' => str_repeat('E', 101)],
                null,
                'refund_time',
            ],
        ];
    }

    /**
     * @dataProvider invalidOutboxPreflightProvider
     */
    public function test_invalid_outbox_preflight_rows_fail_closed(
        string $expectedContract,
        array $firstOverrides,
        array $secondOverrides = null,
        bool $useLongLinkedOrder = false
    ): void {
        $this->migration()->up();

        if ($useLongLinkedOrder) {
            $this->withRenamedTable('withdraw_records', function () use (
                $expectedContract,
                $firstOverrides,
                $secondOverrides
            ): void {
                $this->createPermissiveWithdrawPreflightFixture(
                    ['local_order_no' => str_repeat('W', 201)],
                    null,
                    'funding_error_code'
                );
                $this->assertOutboxPreflightFailure(
                    $expectedContract,
                    $firstOverrides,
                    $secondOverrides,
                    99150001,
                    str_repeat('W', 201)
                );
            });

            return;
        }

        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->dropColumn('funding_error_code');
        });
        try {
            $withdrawRecord = DB::table('withdraw_records')->orderBy('id')->first();
            $this->assertNotNull($withdrawRecord);
            $this->assertOutboxPreflightFailure(
                $expectedContract,
                $firstOverrides,
                $secondOverrides,
                (int) $withdrawRecord->id,
                (string) $withdrawRecord->local_order_no
            );
        } finally {
            $this->migration()->up();
        }
    }

    public function invalidOutboxPreflightProvider(): array
    {
        $unsignedRangeRows = static function (string $column): array {
            return [$column, [$column => -1], [$column => '4294967296']];
        };

        return [
            'backfilled local order too long' => ['local_order_no', ['local_order_no' => null], null, true],
            'event type too long' => ['event_type', ['event_type' => str_repeat('E', 51)]],
            'status too long' => ['status', ['status' => str_repeat('S', 31)]],
            'attempts outside unsigned integer range' => $unsignedRangeRows('attempts'),
            'available at outside unsigned integer range' => $unsignedRangeRows('available_at'),
            'locked at outside unsigned integer range' => $unsignedRangeRows('locked_at'),
            'processed at outside unsigned integer range' => $unsignedRangeRows('processed_at'),
            'created at outside unsigned integer range' => $unsignedRangeRows('created_at'),
            'updated at outside unsigned integer range' => $unsignedRangeRows('updated_at'),
            'deleted at outside unsigned integer range' => $unsignedRangeRows('deleted_at'),
            'payload hash is not hexadecimal' => ['payload_hash', ['payload_hash' => 'not-a-sha256-hash']],
            'provider reference too long' => ['provider_reference', ['provider_reference' => str_repeat('P', 101)]],
            'last error code too long' => ['last_error_code', ['last_error_code' => str_repeat('E', 101)]],
            'duplicate event and withdrawal identity' => [
                'event_type,withdraw_record_id',
                [],
                ['event_type' => 'withdraw_debit'],
            ],
        ];
    }

    public function test_migration_preserves_nullable_funding_fields(): void
    {
        $this->migration()->up();
        $userId = (int) DB::table('withdraw_records')->max('user_id') + 99110000;
        $orderNo = 'TASK1-NULL-' . uniqid('', true);
        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => 'task1-null',
            'apply_amount' => '10.00',
            'actual_amount' => '10.00',
            'fee' => '0.00',
            'exchange_rate' => '1.00000000',
            'rmb_fee' => '0.00',
            'local_order_no' => $orderNo,
            'idempotency_key' => null,
            'funding_payload_hash' => null,
            'funding_error_code' => null,
            'refund_time' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        try {
            $this->migration()->up();
            $row = DB::table('withdraw_records')->where('local_order_no', $orderNo)->first();
            $this->assertNull($row->idempotency_key);
            $this->assertNull($row->funding_payload_hash);
            $this->assertNull($row->funding_error_code);
            $this->assertNull($row->refund_time);
        } finally {
            DB::table('withdraw_records')->where('local_order_no', $orderNo)->delete();
        }
    }

    public function test_migration_recreates_missing_outbox_table_for_orphan_withdrawals(): void
    {
        $this->migration()->up();
        $orderNo = 'TASK1-ORPHAN-' . uniqid('', true);
        $withdrawId = DB::table('withdraw_records')->insertGetId([
            'user_id' => (int) DB::table('withdraw_records')->max('user_id') + 99120000,
            'user_name' => 'task1-orphan',
            'apply_amount' => '10.00',
            'actual_amount' => '10.00',
            'fee' => '0.00',
            'exchange_rate' => '1.00000000',
            'rmb_fee' => '0.00',
            'local_order_no' => $orderNo,
            'funding_status' => 'pending',
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        try {
            $this->withRenamedTable('withdraw_settlement_outbox', function () use ($withdrawId): void {
                $this->assertFalse(Schema::hasTable('withdraw_settlement_outbox'));

                $this->migration()->up();

                $this->assertTrue(Schema::hasTable('withdraw_settlement_outbox'));
                $this->assertSame(
                    'unknown',
                    DB::table('withdraw_records')->where('id', $withdrawId)->value('funding_status')
                );
            });
        } finally {
            DB::table('withdraw_records')->where('id', $withdrawId)->delete();
        }
    }

    public function test_migration_backfills_outbox_local_order_no_and_defaults(): void
    {
        $this->migration()->up();
        $orderNo = 'TASK1-OUTBOX-BACKFILL-' . uniqid('', true);
        $withdrawId = null;

        try {
            $withdrawId = (int) DB::table('withdraw_records')->insertGetId([
                'user_id' => (int) DB::table('withdraw_records')->max('user_id') + 99130000,
                'user_name' => 'task1-outbox-backfill',
                'apply_amount' => '10.00',
                'actual_amount' => '10.00',
                'fee' => '0.00',
                'exchange_rate' => '1.00000000',
                'rmb_fee' => '0.00',
                'local_order_no' => $orderNo,
                'funding_status' => 'unknown',
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $outboxSchemaBefore = $this->createTableSql('withdraw_settlement_outbox');
            $outboxRowsBefore = $this->tableRows('withdraw_settlement_outbox');

            $this->withRenamedTable('withdraw_settlement_outbox', function () use ($withdrawId, $orderNo): void {
                Schema::create('withdraw_settlement_outbox', function (Blueprint $table): void {
                    $table->engine = 'InnoDB';
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('withdraw_record_id');
                    $table->string('event_type', 50);
                    $table->char('payload_hash', 64);
                    $table->string('local_order_no', 200)->nullable();
                    $table->string('status', 30)->nullable();
                    $table->unsignedInteger('attempts')->nullable();
                });
                $outboxId = DB::table('withdraw_settlement_outbox')->insertGetId([
                    'withdraw_record_id' => $withdrawId,
                    'event_type' => 'withdraw_debit',
                    'payload_hash' => hash('sha256', 'task1-outbox-backfill-' . $withdrawId),
                    'local_order_no' => null,
                    'status' => null,
                    'attempts' => null,
                ]);

                $this->migration()->up();

                $row = DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->first();
                $this->assertNotNull($row);
                $this->assertSame($orderNo, $row->local_order_no);
                $this->assertSame('pending', $row->status);
                $this->assertSame(0, (int) $row->attempts);

                $localOrderNo = $this->column('withdraw_settlement_outbox', 'local_order_no');
                $status = $this->column('withdraw_settlement_outbox', 'status');
                $attempts = $this->column('withdraw_settlement_outbox', 'attempts');
                $this->assertSame('NO', strtoupper((string) $localOrderNo->IS_NULLABLE));
                $this->assertSame('NO', strtoupper((string) $status->IS_NULLABLE));
                $this->assertSame('NO', strtoupper((string) $attempts->IS_NULLABLE));
                $this->assertSame('pending', (string) $status->COLUMN_DEFAULT);
                $this->assertSame('0', (string) $attempts->COLUMN_DEFAULT);
            });

            $this->assertSame($outboxSchemaBefore, $this->createTableSql('withdraw_settlement_outbox'));
            $this->assertSame($outboxRowsBefore, $this->tableRows('withdraw_settlement_outbox'));
        } finally {
            if ($withdrawId !== null) {
                DB::table('withdraw_records')->where('id', $withdrawId)->delete();
            }
        }

        $this->assertFalse(DB::table('withdraw_records')->where('id', $withdrawId)->exists());
    }

    public function test_withdraw_record_and_outbox_models_expose_funding_contract(): void
    {
        $record = new WithdrawRecord();
        $casts = $record->getCasts();
        foreach ([
            'user_id' => 'integer',
            'refund_mt4_ticket' => 'integer',
            'refund_time' => 'datetime',
        ] as $field => $cast) {
            $this->assertArrayHasKey($field, $casts);
            $this->assertSame($cast, $casts[$field]);
        }
        $preciseDecimals = [
            'apply_amount' => '9999999999999999.99',
            'actual_amount' => '9999999999999999.99',
            'fee' => '9999999999999999.99',
            'rmb_fee' => '9999999999999999.99',
            'exchange_rate' => '9999999999.99999999',
        ];
        foreach (array_keys($preciseDecimals) as $field) {
            $this->assertArrayNotHasKey($field, $casts);
        }
        $record->setRawAttributes($preciseDecimals);
        foreach ($preciseDecimals as $field => $value) {
            $this->assertSame($value, $record->getAttribute($field));
        }
        foreach (['idempotency_key', 'funding_status', 'funding_payload_hash', 'refund_mt4_ticket', 'refund_time', 'funding_error_code'] as $field) {
            $this->assertContains($field, $record->getFillable());
        }
        $settlementOutboxes = $record->settlementOutboxes();
        $this->assertInstanceOf(HasMany::class, $settlementOutboxes);
        $this->assertSame('withdraw_record_id', $settlementOutboxes->getForeignKeyName());
        $this->assertSame('id', $settlementOutboxes->getLocalKeyName());

        $outbox = new WithdrawSettlementOutbox();
        foreach (['withdraw_record_id', 'local_order_no', 'event_type', 'status', 'attempts', 'payload_hash', 'available_at', 'locked_at', 'processed_at', 'provider_reference', 'last_error_code'] as $field) {
            $this->assertContains($field, $outbox->getFillable());
        }
        $outboxCasts = $outbox->getCasts();
        foreach ([
            'withdraw_record_id' => 'integer',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'processed_at' => 'datetime',
        ] as $field => $cast) {
            $this->assertArrayHasKey($field, $outboxCasts);
            $this->assertSame($cast, $outboxCasts[$field]);
        }
        $withdrawRecord = $outbox->withdrawRecord();
        $this->assertInstanceOf(BelongsTo::class, $withdrawRecord);
        $this->assertSame('withdraw_record_id', $withdrawRecord->getForeignKeyName());
        $this->assertSame('id', $withdrawRecord->getOwnerKeyName());
    }

    public function test_outbox_timestamp_columns_roundtrip_as_unix_timestamps(): void
    {
        $this->migration()->up();
        $withdrawRecord = DB::table('withdraw_records')->orderBy('id')->first();
        $this->assertNotNull($withdrawRecord);
        $moments = [
            'available_at' => Carbon::create(2026, 7, 15, 10, 11, 12, 'UTC'),
            'locked_at' => Carbon::create(2026, 7, 15, 10, 12, 13, 'UTC'),
            'processed_at' => Carbon::create(2026, 7, 15, 10, 13, 14, 'UTC'),
        ];
        $eventType = 'task1_roundtrip_' . substr(hash('sha256', uniqid('', true)), 0, 24);
        $outboxId = null;

        try {
            $outbox = new WithdrawSettlementOutbox([
                'withdraw_record_id' => (int) $withdrawRecord->id,
                'local_order_no' => (string) $withdrawRecord->local_order_no,
                'event_type' => $eventType,
                'status' => 'pending',
                'attempts' => 0,
                'payload_hash' => hash('sha256', $eventType),
                'available_at' => $moments['available_at'],
                'locked_at' => $moments['locked_at'],
                'processed_at' => $moments['processed_at'],
            ]);
            $outbox->save();
            $outboxId = (int) $outbox->getKey();

            $raw = DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->first();
            $this->assertNotNull($raw);
            foreach ($moments as $field => $expected) {
                $this->assertSame($expected->getTimestamp(), (int) $raw->{$field});
            }

            $reloaded = WithdrawSettlementOutbox::query()->findOrFail($outboxId);
            foreach ($moments as $field => $expected) {
                $this->assertInstanceOf(Carbon::class, $reloaded->{$field});
                $this->assertSame($expected->getTimestamp(), $reloaded->{$field}->getTimestamp());
            }
        } finally {
            if ($outboxId !== null) {
                DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->delete();
            }
        }

        $this->assertFalse(DB::table('withdraw_settlement_outbox')->where('id', $outboxId)->exists());
    }

    private function migration()
    {
        $path = database_path('migrations/2026_07_12_000001_harden_withdrawal_funding.php');
        if (!is_file($path)) {
            $this->fail('Task 1 migration has not been implemented.');
        }
        require_once $path;

        return new \HardenWithdrawalFunding();
    }

    private function engine(string $table): string
    {
        return (string) DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->value('ENGINE');
    }

    private function column(string $table, string $name)
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $name)
            ->first();
    }

    private function indexColumns(string $table, string $name): array
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->where('Key_name', $name)
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
    }

    private function indexNonUnique(string $table, string $name): ?int
    {
        $row = collect(DB::select("SHOW INDEX FROM {$table}"))->firstWhere('Key_name', $name);

        return $row ? (int) $row->Non_unique : null;
    }

    private function primaryColumns(string $table): array
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->where('Key_name', 'PRIMARY')
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
    }

    private function indexSubParts(string $table, string $name): array
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->where('Key_name', $name)
            ->sortBy('Seq_in_index')
            ->map(static function ($row): ?int {
                return $row->Sub_part === null ? null : (int) $row->Sub_part;
            })
            ->values()
            ->all();
    }

    private function dropIndexIfPresent(string $table, string $name): void
    {
        if ($this->indexColumns($table, $name) !== []) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$name}");
        }
    }

    private function assertPrefixIndexRepaired(
        string $table,
        string $requiredName,
        string $createdName,
        string $createSql,
        array $expectedColumns
    ): void {
        try {
            $this->migration()->up();
            $this->dropIndexIfPresent($table, $requiredName);
            if ($createdName !== $requiredName) {
                $this->dropIndexIfPresent($table, $createdName);
            }
            DB::statement($createSql);

            $this->migration()->up();

            $this->assertSame($expectedColumns, $this->indexColumns($table, $requiredName));
            $this->assertSame(0, $this->indexNonUnique($table, $requiredName));
            $this->assertSame(
                array_fill(0, count($expectedColumns), null),
                $this->indexSubParts($table, $requiredName)
            );
            if ($createdName !== $requiredName) {
                $this->assertSame([], $this->indexColumns($table, $createdName));
            }
        } finally {
            $this->dropIndexIfPresent($table, $requiredName);
            if ($createdName !== $requiredName) {
                $this->dropIndexIfPresent($table, $createdName);
            }
            $this->migration()->up();
        }
    }

    private function createNonemptyOutboxMissingColumn(
        string $missingColumn,
        int $withdrawRecordId,
        string $localOrderNo
    ): void {
        Schema::create('withdraw_settlement_outbox', function (Blueprint $table) use ($missingColumn): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            if ($missingColumn !== 'withdraw_record_id') {
                $table->unsignedBigInteger('withdraw_record_id');
            }
            if ($missingColumn !== 'local_order_no') {
                $table->string('local_order_no', 200);
            }
            if ($missingColumn !== 'event_type') {
                $table->string('event_type', 50);
            }
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            if ($missingColumn !== 'payload_hash') {
                $table->char('payload_hash', 64);
            }
            $table->unsignedInteger('available_at')->nullable();
            $table->unsignedInteger('locked_at')->nullable();
            $table->unsignedInteger('processed_at')->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
        });

        $row = [
            'withdraw_record_id' => $withdrawRecordId,
            'local_order_no' => $localOrderNo,
            'event_type' => 'withdraw_debit',
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => hash('sha256', 'task1-required-' . $missingColumn),
            'created_at' => time(),
            'updated_at' => time(),
        ];
        unset($row[$missingColumn]);
        DB::table('withdraw_settlement_outbox')->insert($row);
    }

    private function createNonemptyOutboxWithInvalidIdentityValue(
        string $invalidColumn,
        $invalidValue,
        int $withdrawRecordId,
        string $localOrderNo
    ): void {
        Schema::create('withdraw_settlement_outbox', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('withdraw_record_id')->nullable();
            $table->string('local_order_no', 200)->nullable();
            $table->string('event_type', 50)->nullable();
            $table->char('payload_hash', 64)->nullable();
        });

        $row = [
            'withdraw_record_id' => $withdrawRecordId,
            'local_order_no' => $localOrderNo,
            'event_type' => 'withdraw_debit',
            'payload_hash' => hash('sha256', 'task1-invalid-' . $invalidColumn),
        ];
        $row[$invalidColumn] = $invalidValue;
        DB::table('withdraw_settlement_outbox')->insert($row);
    }

    private function createPermissiveWithdrawPreflightFixture(
        array $firstOverrides,
        array $secondOverrides = null,
        string $sentinelColumn
    ): void {
        Schema::create('withdraw_records', function (Blueprint $table) use ($sentinelColumn): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->text('local_order_no')->nullable();
            $table->text('apply_amount')->nullable();
            $table->text('actual_amount')->nullable();
            $table->text('fee')->nullable();
            $table->text('exchange_rate')->nullable();
            $table->text('rmb_fee')->nullable();
            $table->text('idempotency_key')->nullable();
            $table->text('funding_status')->nullable();
            $table->text('funding_payload_hash')->nullable();
            $table->text('refund_mt4_ticket')->nullable();
            if ($sentinelColumn !== 'refund_time') {
                $table->dateTime('refund_time')->nullable();
            }
            if ($sentinelColumn !== 'funding_error_code') {
                $table->text('funding_error_code')->nullable();
            }
        });

        $first = array_merge($this->validWithdrawPreflightRow(99150001), $firstOverrides);
        if ($sentinelColumn !== 'refund_time') {
            $first['refund_time'] = null;
        }
        if ($sentinelColumn !== 'funding_error_code' && !array_key_exists('funding_error_code', $first)) {
            $first['funding_error_code'] = null;
        }
        DB::table('withdraw_records')->insert($first);

        if ($secondOverrides !== null) {
            $second = array_merge($this->validWithdrawPreflightRow(99150002), $secondOverrides);
            if ($sentinelColumn !== 'refund_time') {
                $second['refund_time'] = null;
            }
            if ($sentinelColumn !== 'funding_error_code' && !array_key_exists('funding_error_code', $second)) {
                $second['funding_error_code'] = null;
            }
            DB::table('withdraw_records')->insert($second);
        }
    }

    private function validWithdrawPreflightRow(int $id): array
    {
        return [
            'id' => $id,
            'user_id' => $id,
            'local_order_no' => 'TASK1-PREFLIGHT-' . $id,
            'apply_amount' => '10.00',
            'actual_amount' => '10.00',
            'fee' => '0.00',
            'exchange_rate' => '1.00000000',
            'rmb_fee' => '0.00',
            'idempotency_key' => null,
            'funding_status' => 'unknown',
            'funding_payload_hash' => null,
            'refund_mt4_ticket' => null,
        ];
    }

    private function assertOutboxPreflightFailure(
        string $expectedContract,
        array $firstOverrides,
        array $secondOverrides = null,
        int $withdrawRecordId,
        string $localOrderNo
    ): void {
        $this->withRenamedTable('withdraw_settlement_outbox', function () use (
            $expectedContract,
            $firstOverrides,
            $secondOverrides,
            $withdrawRecordId,
            $localOrderNo
        ): void {
            $this->createPermissiveOutboxPreflightFixture(
                $firstOverrides,
                $secondOverrides,
                $withdrawRecordId,
                $localOrderNo
            );
            $this->assertFalse(Schema::hasColumn('withdraw_records', 'funding_error_code'));
            $withdrawSchemaBefore = $this->createTableSql('withdraw_records');
            $withdrawRowsBefore = $this->tableRows('withdraw_records');
            $outboxSchemaBefore = $this->createTableSql('withdraw_settlement_outbox');
            $outboxRowsBefore = $this->tableRows('withdraw_settlement_outbox');
            $exception = null;

            try {
                $this->migration()->up();
            } catch (\Throwable $caught) {
                $exception = $caught;
            }

            $this->assertInstanceOf(RuntimeException::class, $exception);
            $this->assertSame($withdrawSchemaBefore, $this->createTableSql('withdraw_records'));
            $this->assertSame($withdrawRowsBefore, $this->tableRows('withdraw_records'));
            $this->assertSame($outboxSchemaBefore, $this->createTableSql('withdraw_settlement_outbox'));
            $this->assertSame($outboxRowsBefore, $this->tableRows('withdraw_settlement_outbox'));
            $this->assertFalse(Schema::hasColumn('withdraw_records', 'funding_error_code'));
            $this->assertStringContainsString('withdraw_settlement_outbox', $exception->getMessage());
            $this->assertStringContainsString($expectedContract, $exception->getMessage());
        });
    }

    private function createPermissiveOutboxPreflightFixture(
        array $firstOverrides,
        array $secondOverrides = null,
        int $withdrawRecordId,
        string $localOrderNo
    ): void {
        Schema::create('withdraw_settlement_outbox', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('withdraw_record_id');
            $table->text('local_order_no')->nullable();
            $table->text('event_type')->nullable();
            $table->text('status')->nullable();
            $table->bigInteger('attempts')->nullable();
            $table->text('payload_hash')->nullable();
            $table->bigInteger('available_at')->nullable();
            $table->bigInteger('locked_at')->nullable();
            $table->bigInteger('processed_at')->nullable();
            $table->text('provider_reference')->nullable();
            $table->text('last_error_code')->nullable();
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            $table->bigInteger('deleted_at')->nullable();
        });

        $first = array_merge(
            $this->validOutboxPreflightRow($withdrawRecordId, $localOrderNo, 'withdraw_debit'),
            $firstOverrides
        );
        DB::table('withdraw_settlement_outbox')->insert($first);
        if ($secondOverrides !== null) {
            $second = array_merge(
                $this->validOutboxPreflightRow($withdrawRecordId, $localOrderNo, 'withdraw_refund'),
                $secondOverrides
            );
            DB::table('withdraw_settlement_outbox')->insert($second);
        }
    }

    private function validOutboxPreflightRow(
        int $withdrawRecordId,
        string $localOrderNo,
        string $eventType
    ): array {
        return [
            'withdraw_record_id' => $withdrawRecordId,
            'local_order_no' => $localOrderNo,
            'event_type' => $eventType,
            'status' => 'pending',
            'attempts' => 0,
            'payload_hash' => hash('sha256', $eventType . '-' . $withdrawRecordId),
            'available_at' => null,
            'locked_at' => null,
            'processed_at' => null,
            'provider_reference' => null,
            'last_error_code' => null,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ];
    }

    private function createSafePartialTableWithMultipleMissingColumns(string $table, $withdrawRecord): void
    {
        if ($table === 'withdraw_records') {
            Schema::create('withdraw_records', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->integer('user_id');
                $table->string('local_order_no', 200);
                $table->decimal('apply_amount', 18, 2);
                $table->decimal('actual_amount', 18, 2)->default(0);
                $table->decimal('fee', 18, 2)->default(0);
                $table->decimal('exchange_rate', 18, 8)->default(0);
                $table->decimal('rmb_fee', 18, 2)->default(0);
            });
            DB::table('withdraw_records')->insert([
                'user_id' => 99160001,
                'local_order_no' => 'TASK1-MULTI-ADD-WITHDRAW',
                'apply_amount' => '10.00',
                'actual_amount' => '10.00',
                'fee' => '0.00',
                'exchange_rate' => '1.00000000',
                'rmb_fee' => '0.00',
            ]);

            return;
        }

        Schema::create('withdraw_settlement_outbox', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('withdraw_record_id');
            $table->string('local_order_no', 200);
            $table->string('event_type', 50);
            $table->char('payload_hash', 64);
        });
        DB::table('withdraw_settlement_outbox')->insert([
            'withdraw_record_id' => (int) $withdrawRecord->id,
            'local_order_no' => (string) $withdrawRecord->local_order_no,
            'event_type' => 'task1_multi_add',
            'payload_hash' => hash('sha256', 'task1-multi-add-outbox'),
        ]);
    }

    private function columnAddStatements(array $queries, string $table, array $columns): array
    {
        return collect($queries)
            ->map(static function (string $sql): string {
                return strtolower((string) preg_replace('/\s+/', ' ', str_replace('`', '', trim($sql))));
            })
            ->filter(static function (string $sql) use ($table, $columns): bool {
                if (strpos($sql, "alter table {$table} ") !== 0) {
                    return false;
                }
                if (preg_match('/\badd\s+(unique\s+)?index\b/', $sql) === 1) {
                    return false;
                }
                foreach ($columns as $column) {
                    if (
                        strpos($sql, "add {$column} ") !== false
                        || strpos($sql, "add column {$column} ") !== false
                    ) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    private function createTableSql(string $table): string
    {
        $row = DB::selectOne("SHOW CREATE TABLE {$table}");

        return (string) $row->{'Create Table'};
    }

    private function tableRows(string $table): array
    {
        return DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(static function ($row): array {
                return (array) $row;
            })
            ->values()
            ->all();
    }

    private function withRenamedTable(string $table, callable $callback): void
    {
        $testName = method_exists($this, 'name') ? $this->name() : $this->getName();
        $backup = $table . '_t1_' . getmypid() . '_' . substr(md5((string) $testName), 0, 8);
        if (Schema::hasTable($backup)) {
            throw new RuntimeException("Refusing to overwrite existing schema-test backup table {$backup}.");
        }
        DB::statement("RENAME TABLE {$table} TO {$backup}");
        try {
            $callback();
        } finally {
            if (!Schema::hasTable($backup)) {
                throw new RuntimeException("Schema-test backup table {$backup} is missing; current table was not dropped.");
            }
            Schema::dropIfExists($table);
            DB::statement("RENAME TABLE {$backup} TO {$table}");
        }
    }
}
