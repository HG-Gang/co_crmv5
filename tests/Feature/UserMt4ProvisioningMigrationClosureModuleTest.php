<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:59
 */

/**
 * UserMt4ProvisioningMigrationClosureModuleTest
 *
 * 文件功能：
 * - 验证用户 MT4 开通迁移闭环：outbox 表结构与索引、索引修复保留数据行、重复 user_id 阻断、部分 schema 修复与失败关闭、夹具回调失败还原表、清理步骤收集全部失败。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\MySqlFixtureMutex;
use Tests\TestCase;

final class UserMt4ProvisioningMigrationClosureModuleTest extends TestCase
{
    /**
     * 被测迁移创建的 outbox 表名（user_mt4_provisioning_outbox）。断言其结构与索引符合契约。
     * @var string
     */
    private const TABLE = 'user_mt4_provisioning_outbox';

    /**
     * MySqlFixtureMutex 实例。串行化共享测试库上的夹具准备与清理，避免并行进程互相踩踏。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
    }

    private function abortFixtureSetup(\Throwable $cause): void
    {
        $failures = [];
        try {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        } catch (\Throwable $exception) {
            $failures[] = 'mutex_release: ' . $exception->getMessage();
        } finally {
            $this->fixtureMutex = null;
        }
        try {
            parent::tearDown();
        } catch (\Throwable $exception) {
            $failures[] = 'parent_teardown: ' . $exception->getMessage();
        }
        if ($failures !== []) {
            throw new RuntimeException(
                'MT4 migration fixture setup failed: ' . implode(' | ', $failures),
                0,
                $cause
            );
        }

        throw $cause;
    }

    protected function tearDown(): void
    {
        $failures = [];
        $firstFailure = null;
        try {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        } catch (\Throwable $exception) {
            $firstFailure = $exception;
            $failures[] = 'mutex_release: ' . $exception->getMessage();
        } finally {
            $this->fixtureMutex = null;
        }
        try {
            parent::tearDown();
        } catch (\Throwable $exception) {
            $firstFailure = $firstFailure ?? $exception;
            $failures[] = 'parent_teardown: ' . $exception->getMessage();
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'MT4 migration fixture teardown failed: ' . implode(' | ', $failures),
                0,
                $firstFailure
            );
        }
    }

    public function test_outbox_table_schema_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable(self::TABLE), 'The MT4 provisioning outbox migration must be applied.');

        $columns = Schema::getColumnListing(self::TABLE);
        foreach ([
            'id', 'user_login_id', 'user_info_id', 'user_id', 'status', 'attempts',
            'reconciliation_attempts', 'payload_ciphertext', 'payload_hash',
            'available_at', 'locked_at', 'processed_at', 'provider_reference',
            'last_error_code', 'created_at', 'updated_at', 'deleted_at',
        ] as $column) {
            $this->assertContains($column, $columns);
        }

        $engine = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->value('ENGINE');
        $this->assertSame('InnoDB', $engine);
        $this->assertSame(['id'], $this->primaryColumns());
        $this->assertSame(['user_id'], $this->indexColumns('user_mt4_provisioning_user_unique'));
        $this->assertSame(['status', 'available_at'], $this->indexColumns('user_mt4_provisioning_ready_index'));
        $this->assertSame(['status', 'locked_at'], $this->indexColumns('user_mt4_provisioning_stale_index'));
    }

    public function test_migration_repairs_indexes_and_preserves_rows(): void
    {
        $this->requireTable();
        $original = $this->tableFingerprint(self::TABLE);
        $originalBackups = $this->backupTablesFingerprint();
        $fixtureUserId = $this->unusedUserId();
        $fixtureId = null;

        try {
            $fixtureId = (int) DB::table(self::TABLE)->insertGetId([
                'user_login_id' => $fixtureUserId,
                'user_info_id' => $fixtureUserId,
                'user_id' => $fixtureUserId,
                'status' => 'unknown',
                'attempts' => 2,
                'reconciliation_attempts' => 1,
                'payload_ciphertext' => null,
                'payload_hash' => hash('sha256', 'migration-fixture-' . $fixtureUserId),
                'available_at' => time() - 1,
                'locked_at' => null,
                'processed_at' => null,
                'provider_reference' => null,
                'last_error_code' => 'fixture',
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ]);
            $this->dropIndexIfPresent('user_mt4_provisioning_user_unique');
            $this->dropIndexIfPresent('user_mt4_provisioning_ready_index');
            $this->dropIndexIfPresent('user_mt4_provisioning_stale_index');
            DB::statement(
                'ALTER TABLE ' . self::TABLE
                . ' ADD INDEX user_mt4_provisioning_ready_index (available_at, status)'
            );
            $this->migration()->up();

            $row = DB::table(self::TABLE)->where('id', $fixtureId)->first();
            $this->assertSame('unknown', $row->status);
            $this->assertSame(2, (int) $row->attempts);
            $this->assertSame(['user_id'], $this->indexColumns('user_mt4_provisioning_user_unique'));
            $this->assertSame(['status', 'available_at'], $this->indexColumns('user_mt4_provisioning_ready_index'));
            $this->assertSame(['status', 'locked_at'], $this->indexColumns('user_mt4_provisioning_stale_index'));
        } finally {
            if ($fixtureId !== null) {
                DB::table(self::TABLE)->where('id', $fixtureId)->delete();
            }
            $this->migration()->up();
            $this->restoreAutoIncrement(self::TABLE, $original['auto_increment']);
            $this->assertSame($original, $this->tableFingerprint(self::TABLE));
            $this->assertSame($originalBackups, $this->backupTablesFingerprint());
        }
    }

    public function test_migration_blocks_duplicate_user_id(): void
    {
        $this->requireTable();
        $original = $this->tableFingerprint(self::TABLE);
        $originalBackups = $this->backupTablesFingerprint();
        $duplicateUserId = $this->unusedUserId();
        $insertedIds = [];

        try {
            $this->dropIndexIfPresent('user_mt4_provisioning_user_unique');
            foreach ([0, 1] as $offset) {
                $referenceId = $duplicateUserId + $offset;
                $insertedIds[] = (int) DB::table(self::TABLE)->insertGetId([
                    'user_login_id' => $referenceId,
                    'user_info_id' => $referenceId,
                    'user_id' => $duplicateUserId,
                    'status' => 'unknown',
                    'attempts' => 1,
                    'reconciliation_attempts' => 0,
                    'payload_ciphertext' => null,
                    'payload_hash' => hash('sha256', (string) $referenceId),
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
            }
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('duplicate user_id');
            $this->migration()->up();
        } finally {
            if ($insertedIds !== []) {
                DB::table(self::TABLE)->whereIn('id', $insertedIds)->delete();
            }
            $this->migration()->up();
            $this->restoreAutoIncrement(self::TABLE, $original['auto_increment']);
            $this->assertSame($original, $this->tableFingerprint(self::TABLE));
            $this->assertSame($originalBackups, $this->backupTablesFingerprint());
        }
    }

    public function test_migration_repairs_partial_schema(): void
    {
        $this->withPartialOutbox(function (): void {
            DB::statement('CREATE TABLE ' . self::TABLE . ' ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
                . 'user_id INT NULL,'
                . 'status VARCHAR(5) NULL,'
                . 'payload_hash VARCHAR(10) NULL'
                . ') ENGINE=MyISAM');

            $this->migration()->up();

            $this->assertSame('bigint unsigned', $this->semanticType((string) $this->column('user_id')->COLUMN_TYPE));
            $this->assertSame('NO', $this->column('user_id')->IS_NULLABLE);
            $this->assertSame('varchar(40)', $this->semanticType((string) $this->column('status')->COLUMN_TYPE));
            $this->assertSame('pending', $this->column('status')->COLUMN_DEFAULT);
            $this->assertSame('char(64)', $this->semanticType((string) $this->column('payload_hash')->COLUMN_TYPE));
            $this->assertSame('YES', $this->column('payload_hash')->IS_NULLABLE);
            $this->assertTrue(Schema::hasColumn(self::TABLE, 'payload_ciphertext'));
            $this->assertTrue(Schema::hasColumn(self::TABLE, 'reconciliation_attempts'));
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', self::TABLE)
                ->value('ENGINE');
            $this->assertSame('InnoDB', $engine);
        });
    }

    public function test_migration_fails_closed_on_nonempty_partial_schema(): void
    {
        $this->withPartialOutbox(function (): void {
            DB::statement('CREATE TABLE ' . self::TABLE . ' ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
                . 'status VARCHAR(40) NOT NULL DEFAULT \'pending\''
                . ') ENGINE=InnoDB');
            DB::table(self::TABLE)->insert(['status' => 'pending']);

            try {
                $this->migration()->up();
                $this->fail('Nonempty partial provisioning outbox must fail before repair DDL.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('required', strtolower($exception->getMessage()));
            }

            $this->assertFalse(Schema::hasColumn(self::TABLE, 'user_id'));
            $this->assertFalse(Schema::hasColumn(self::TABLE, 'payload_ciphertext'));
        });
    }

    public function test_partial_fixture_callback_failure_restores_tables(): void
    {
        $this->requireTable();
        $leakedBackup = $this->newBackupTableName();
        if (Schema::hasTable($leakedBackup)) {
            $this->fail('The MT4 provisioning failure fixture backup already exists.');
        }
        $original = $this->tableFingerprint(self::TABLE);
        $originalBackups = $this->backupTablesFingerprint();

        try {
            $callbackFailure = null;
            try {
                $this->withPartialOutbox(function () use ($leakedBackup): void {
                    DB::statement('CREATE TABLE `' . $leakedBackup . '` (id INT) ENGINE=InnoDB');

                    throw new RuntimeException('Intentional MT4 partial fixture callback failure.');
                });
            } catch (\Throwable $exception) {
                $callbackFailure = $exception;
            }

            $this->assertInstanceOf(RuntimeException::class, $callbackFailure);
            $this->assertSame(
                'Intentional MT4 partial fixture callback failure.',
                $callbackFailure->getMessage()
            );
            $this->assertSame($original, $this->tableFingerprint(self::TABLE));
            $this->assertSame($originalBackups, $this->backupTablesFingerprint());
        } finally {
            Schema::dropIfExists($leakedBackup);
        }
    }

    public function test_partial_fixture_backup_restoration_failure_surfaces(): void
    {
        $this->requireTable();
        $existingBackup = $this->newBackupTableName();
        $primaryFailure = new RuntimeException('Intentional callback failure after backup mutation.');

        try {
            DB::statement(
                'CREATE TABLE `' . $existingBackup . '` ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
                . 'marker VARCHAR(32) NOT NULL'
                . ') ENGINE=InnoDB'
            );
            DB::table($existingBackup)->insert(['marker' => 'original']);
            $before = $this->backupTablesFingerprint();
            $this->assertArrayHasKey($existingBackup, $before);
            foreach (['show_create', 'rows', 'checksum', 'auto_increment'] as $field) {
                $this->assertArrayHasKey($field, $before[$existingBackup]);
            }

            $failure = null;
            try {
                $this->withPartialOutbox(function () use ($existingBackup, $primaryFailure): void {
                    DB::table($existingBackup)->insert(['marker' => 'mutated']);

                    throw $primaryFailure;
                });
            } catch (\Throwable $exception) {
                $failure = $exception;
            }

            $this->assertInstanceOf(RuntimeException::class, $failure);
            $this->assertSame($primaryFailure, $failure->getPrevious());
            $this->assertStringContainsString('restoration failed after callback failure', $failure->getMessage());
            $this->assertSame(2, DB::table($existingBackup)->count());
        } finally {
            Schema::dropIfExists($existingBackup);
        }
    }

    public function test_run_cleanup_steps_collects_all_failures(): void
    {
        $this->assertTrue(method_exists($this, 'runCleanupSteps'));

        $attempted = [];
        $first = new \DomainException('first migration cleanup failure');
        $failure = null;
        try {
            $this->runCleanupSteps('migration cleanup', [
                'first' => function () use (&$attempted, $first): void {
                    $attempted[] = 'first';
                    throw $first;
                },
                'second' => function () use (&$attempted): void {
                    $attempted[] = 'second';
                },
                'third' => function () use (&$attempted): void {
                    $attempted[] = 'third';
                    throw new \RuntimeException('second migration cleanup failure');
                },
            ]);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        $this->assertSame(['first', 'second', 'third'], $attempted);
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame($first, $failure->getPrevious());
        $this->assertStringContainsString('first migration cleanup failure', $failure->getMessage());
        $this->assertStringContainsString('second migration cleanup failure', $failure->getMessage());
    }

    public function test_setup_releases_mutex_before_parent_teardown(): void
    {
        $this->assertTrue(
            property_exists($this, 'fixtureMutex'),
            'Migration fixtures must own a MySqlFixtureMutex.'
        );
        $source = static function (string $method): string {
            $reflection = new \ReflectionMethod(self::class, $method);
            $lines = file($reflection->getFileName());

            return implode('', array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1
            ));
        };
        $abort = $source('abortFixtureSetup');
        $setUp = $source('setUp');
        $tearDown = $source('tearDown');

        $this->assertStringContainsString('new MySqlFixtureMutex', $setUp);
        $this->assertStringContainsString('->acquire()', $setUp);
        $this->assertStringContainsString('abortFixtureSetup', $setUp);
        $this->assertStringContainsString('->releaseWithDisconnectFallback()', $abort);
        $this->assertStringContainsString('->releaseWithDisconnectFallback()', $tearDown);
        $this->assertStringContainsString('parent::tearDown()', $tearDown);
        $this->assertTrue(
            strpos($tearDown, '->releaseWithDisconnectFallback()') < strpos($tearDown, 'parent::tearDown()'),
            'The migration mutex must be released before parent teardown destroys the container.'
        );
    }

    private function requireTable(): void
    {
        $this->assertTrue(Schema::hasTable(self::TABLE));
    }

    private function migration()
    {
        require_once database_path('migrations/2026_07_19_000002_create_user_mt4_provisioning_outbox.php');

        return new \CreateUserMt4ProvisioningOutbox();
    }

    private function dropIndexIfPresent(string $name): void
    {
        if ($this->indexColumns($name) !== []) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' DROP INDEX `' . $name . '`');
        }
    }

    private function indexColumns(string $name): array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return [];
        }

        return collect(DB::select('SHOW INDEX FROM ' . self::TABLE))
            ->where('Key_name', $name)
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
    }

    private function primaryColumns(): array
    {
        return collect(DB::select('SHOW INDEX FROM ' . self::TABLE))
            ->where('Key_name', 'PRIMARY')
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
    }

    private function column(string $name)
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('COLUMN_NAME', $name)
            ->first();
    }

    private function semanticType(string $type): string
    {
        return (string) preg_replace(
            '/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/',
            '$1',
            strtolower($type)
        );
    }

    /** @return array<string, mixed> */
    private function tableFingerprint(string $table): array
    {
        return [
            'show_create' => $this->createTableSql($table),
            'rows' => DB::table($table)->count(),
            'checksum' => $this->tableChecksum($table),
            'auto_increment' => $this->autoIncrement($table),
            'payload_hash_nullable' => $this->payloadHashNullable($table),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function backupTablesFingerprint(): array
    {
        $fingerprints = [];
        foreach ($this->backupTableNames() as $table) {
            $fingerprints[$table] = $this->tableFingerprint($table);
        }

        return $fingerprints;
    }

    /** @return array<int, string> */
    private function backupTableNames(): array
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'like', self::TABLE . '_provisioning_test_backup%')
            ->orderBy('TABLE_NAME')
            ->pluck('TABLE_NAME')
            ->map(static function ($table): string {
                return (string) $table;
            })
            ->all();
    }

    private function payloadHashNullable(string $table): ?string
    {
        $nullable = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'payload_hash')
            ->value('IS_NULLABLE');

        return $nullable === null ? null : (string) $nullable;
    }

    private function withPartialOutbox(callable $callback): void
    {
        $backup = $this->newBackupTableName();
        $original = $this->tableFingerprint(self::TABLE);
        $originalBackups = $this->backupTablesFingerprint();
        $callbackFailure = null;
        $restoreFailure = null;
        DB::statement('RENAME TABLE `' . self::TABLE . '` TO `' . $backup . '`');
        try {
            try {
                $callback();
            } catch (\Throwable $exception) {
                $callbackFailure = $exception;
            }
        } finally {
            try {
                $this->runCleanupSteps('MT4 provisioning fixture restoration', [
                    'drop partial outbox' => function (): void {
                        Schema::dropIfExists(self::TABLE);
                    },
                    'restore original outbox' => function () use ($backup): void {
                        DB::statement('RENAME TABLE `' . $backup . '` TO `' . self::TABLE . '`');
                    },
                    'restore outbox AUTO_INCREMENT' => function () use ($backup, $original): void {
                        if (Schema::hasTable($backup) || !Schema::hasTable(self::TABLE)) {
                            throw new RuntimeException(
                                'Cannot restore MT4 provisioning AUTO_INCREMENT before the original table is back.'
                            );
                        }
                        $this->restoreAutoIncrement(self::TABLE, $original['auto_increment']);
                    },
                    'drop new backup tables' => function () use ($originalBackups, $backup): void {
                        $this->dropNewBackupTables($originalBackups, [$backup]);
                    },
                    'verify original outbox fingerprint' => function () use ($original): void {
                        $this->assertSame($original, $this->tableFingerprint(self::TABLE));
                    },
                    'verify backup fingerprints' => function () use ($originalBackups): void {
                        $this->assertSame($originalBackups, $this->backupTablesFingerprint());
                    },
                ]);
            } catch (\Throwable $exception) {
                $restoreFailure = $exception;
            }
        }

        if ($restoreFailure !== null) {
            if ($callbackFailure !== null) {
                throw new RuntimeException(
                    'MT4 provisioning fixture restoration failed after callback failure: '
                    . $restoreFailure->getMessage(),
                    0,
                    $callbackFailure
                );
            }
            throw $restoreFailure;
        }
        if ($callbackFailure !== null) {
            throw $callbackFailure;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $originalBackups
     * @param array<int, string> $protectedTables
     */
    private function dropNewBackupTables(array $originalBackups, array $protectedTables = []): void
    {
        $originalNames = array_keys($originalBackups);
        $protected = array_fill_keys($protectedTables, true);
        $newBackups = array_values(array_diff($this->backupTableNames(), $originalNames));
        $steps = [];
        foreach ($newBackups as $table) {
            if (isset($protected[$table])) {
                continue;
            }
            $steps['drop backup table ' . $table] = function () use ($table): void {
                Schema::dropIfExists($table);
            };
        }

        $this->runCleanupSteps('MT4 provisioning backup cleanup', $steps);
    }

    /**
     * @param array<string, callable(): void> $steps
     */
    private function runCleanupSteps(string $scope, array $steps): void
    {
        $failures = [];
        $firstFailure = null;
        foreach ($steps as $label => $step) {
            try {
                $step();
            } catch (\Throwable $exception) {
                if ($firstFailure === null) {
                    $firstFailure = $exception;
                }
                $failures[] = $label . ': ' . $exception->getMessage();
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(
                $scope . ' failed: ' . implode(' | ', $failures),
                0,
                $firstFailure
            );
        }
    }

    private function newBackupTableName(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = self::TABLE . '_provisioning_test_backup_' . bin2hex(random_bytes(4));
            if (!Schema::hasTable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to reserve an isolated MT4 provisioning backup table.');
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = random_int(470000000, 479999998);
            if (!DB::table(self::TABLE)
                ->where('user_id', $candidate)
                ->orWhereIn('user_login_id', [$candidate, $candidate + 1])
                ->orWhereIn('user_info_id', [$candidate, $candidate + 1])
                ->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to reserve an isolated MT4 provisioning migration fixture.');
    }

    private function createTableSql(string $table): string
    {
        $row = DB::selectOne('SHOW CREATE TABLE `' . $table . '`');
        if ($row === null) {
            throw new RuntimeException('Unable to inspect MT4 provisioning outbox schema.');
        }
        $values = array_values((array) $row);

        return (string) ($values[1] ?? '');
    }

    private function tableChecksum(string $table): string
    {
        $row = DB::selectOne('CHECKSUM TABLE `' . $table . '`');
        if ($row === null) {
            throw new RuntimeException('Unable to checksum MT4 provisioning outbox data.');
        }
        $values = array_values((array) $row);

        return (string) ($values[1] ?? '');
    }

    private function autoIncrement(string $table): ?int
    {
        DB::statement('SET SESSION information_schema_stats_expiry = 0');
        $value = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->value('AUTO_INCREMENT');

        return $value === null ? null : (int) $value;
    }

    private function restoreAutoIncrement(string $table, int $value = null): void
    {
        if ($value === null) {
            return;
        }

        DB::statement('SET SESSION information_schema_stats_expiry = 0');
        $maxId = DB::table($table)->max('id');
        if ($maxId !== null && $value <= (int) $maxId) {
            throw new RuntimeException(
                'Refusing to lower MT4 provisioning AUTO_INCREMENT below an existing id value.'
            );
        }
        DB::statement('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . $value);
    }
}
