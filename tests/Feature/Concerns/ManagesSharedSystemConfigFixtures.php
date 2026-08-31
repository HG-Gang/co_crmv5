<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 05:06
 */

declare(strict_types=1);

/**
 * 共享系统配置夹具管理 trait。
 *
 * 文件功能：
 * - 为依赖 system_configs 表的测试提供可复用的夹具生命周期：加锁、快照、恢复、AUTO_INCREMENT 还原。
 * - 支持嵌套夹具场景下按稳定身份回退更新，保证外层生命周期能把配置恢复成快照。
 *
 * 适用场景：
 * - 前端 Demo 种子数据等会改动 system_configs 的测试，用于隔离共享配置。
 *
 * 入参例子：
 * - runSharedSystemConfigFixtureLifecycleCleanup($primary, ['step' => fn() => ...])。
 * - captureSharedSystemConfigFixtureOwnedState($managedKeys, $expectedRows)。
 *
 * 返回值：
 * - 恢复成功返回 void；失败抛出 RuntimeException 汇总各步骤错误。
 *
 * 异常或失败场景：
 * - 锁未获取、所有权变化、快照不匹配、AUTO_INCREMENT 还原失败时抛出异常。
 */

namespace Tests\Feature\Concerns;

use Illuminate\Support\Facades\DB;

trait ManagesSharedSystemConfigFixtures
{
    /**
     * 持有共享配置夹具建议锁（GET_LOCK）的专用连接。MySQL 命名锁与连接绑定，
     * 必须用同一连接加锁与释放；null 表示尚未加锁。
     * @var \Illuminate\Database\Connection|null
     */
    private $sharedSystemConfigFixtureLockConnection;

    /**
     * 锁连接在 Laravel 连接管理器中的注册名。释放后据此 purge 连接，避免残留连接占用数据库会话。
     * @var string|null
     */
    private $sharedSystemConfigFixtureLockConnectionName;

    /**
     * 共享配置夹具的锁名。所有需要改写共享 system_configs 的测试类用同一把锁互斥，防止并行运行互相覆盖配置。
     * @var string|null
     */
    private $sharedSystemConfigFixtureLockName;

    /**
     * 是否已成功获取锁。释放与清理逻辑据此判断是否有锁可放，防止对未持有的锁执行 RELEASE_LOCK。
     * @var bool
     */
    private $sharedSystemConfigFixtureLockAcquired = false;

    /**
     * system_configs 表的 AUTO_INCREMENT 基线。恢复动作延迟到锁释放阶段执行，
     * 避免与并发插入的赢家行冲突。
     * @var int|null
     */
    private $sharedSystemConfigFixtureAutoIncrementSnapshot;

    /**
     * 夹具拥有处置权的配置行状态（key => 行或 null）。null 表示该键夹具接管时不存在（需删除）。
     * 恢复时只处理这些键，不碰共享库中他人写入的行。
     * @var array<string, array<string, mixed>|null>|null
     */
    private $sharedSystemConfigFixtureOwnedState;

    /**
     * @param array<string, callable> $steps
     */
    protected function runSharedSystemConfigFixtureLifecycleCleanup(
        \Throwable $primary = null,
        array $steps
    ): void {
        $cleanupFailures = [];
        foreach ($steps as $label => $step) {
            try {
                $step();
            } catch (\Throwable $exception) {
                $cleanupFailures[] = [
                    'label' => (string) $label,
                    'exception' => $exception,
                ];
            }
        }

        if ($cleanupFailures === []) {
            if ($primary !== null) {
                throw $primary;
            }

            return;
        }

        $messages = [];
        if ($primary !== null) {
            $messages[] = 'primary: ' . $primary->getMessage();
        }
        foreach ($cleanupFailures as $failure) {
            $messages[] = $failure['label'] . ': ' . $failure['exception']->getMessage();
        }

        throw new \RuntimeException(
            'Shared system config fixture lifecycle cleanup failed: ' . implode(' | ', $messages),
            0,
            $primary !== null ? $primary : $cleanupFailures[0]['exception']
        );
    }

    /**
     * @param array<int, string> $managedKeys
     * @param array<int, array<string, mixed>> $snapshot
     */
    protected function restoreSharedSystemConfigSnapshot(
        array $managedKeys,
        array $snapshot,
        bool $restoreAutoIncrement = true
    ): void {
        $managedKeys = $this->normalizeSharedSystemConfigManagedKeys($managedKeys);
        $snapshotByKey = $this->indexSharedSystemConfigRows($managedKeys, $snapshot, 'snapshot');
        $ownedState = $this->sharedSystemConfigFixtureOwnedState;
        if ($ownedState === null || array_keys($ownedState) !== $managedKeys) {
            throw new \LogicException(
                'The shared system config fixture owned state is unavailable or incomplete.'
            );
        }

        $failureMessages = [];
        $firstFailure = null;
        foreach ($managedKeys as $key) {
            try {
                $this->beforeRestoreSharedSystemConfigKey($key);
                $snapshotRow = $snapshotByKey[$key] ?? null;
                $this->restoreSharedSystemConfigKey($key, $snapshotRow, $ownedState[$key]);
                $this->sharedSystemConfigFixtureOwnedState[$key] = $snapshotRow;
            } catch (\Throwable $exception) {
                if ($firstFailure === null) {
                    $firstFailure = $exception;
                }
                $failureMessages[] = $key . ': ' . $exception->getMessage();
            }
        }

        try {
            $expected = $this->sortSharedSystemConfigRows(array_values($snapshotByKey));
            $actual = $this->readSharedSystemConfigRowsFromWritePdo($managedKeys);
            if ($actual !== $expected) {
                $failureMessages[] = 'final snapshot mismatch: expected '
                    . json_encode($expected)
                    . ', actual '
                    . json_encode($actual);
            }
        } catch (\Throwable $exception) {
            if ($firstFailure === null) {
                $firstFailure = $exception;
            }
            $failureMessages[] = 'final snapshot verification failed: ' . $exception->getMessage();
        }

        if ($restoreAutoIncrement) {
            try {
                $this->restoreSharedSystemConfigFixtureAutoIncrement();
            } catch (\Throwable $exception) {
                if ($firstFailure === null) {
                    $firstFailure = $exception;
                }
                $failureMessages[] = 'AUTO_INCREMENT restore failed: ' . $exception->getMessage();
            }
        }

        if ($failureMessages !== []) {
            throw new \RuntimeException(
                'Shared system config fixture restore failed: ' . implode(' | ', $failureMessages),
                0,
                $firstFailure
            );
        }

        $this->sharedSystemConfigFixtureOwnedState = null;
    }

    /**
     * @param array<int, string> $managedKeys
     * @param array<int, array<string, mixed>> $expectedRows
     */
    protected function captureSharedSystemConfigFixtureOwnedState(
        array $managedKeys,
        array $expectedRows
    ): void {
        if (!$this->sharedSystemConfigFixtureLockAcquired) {
            throw new \LogicException(
                'The shared system config fixture lock must be acquired before owned-state capture.'
            );
        }

        $managedKeys = $this->normalizeSharedSystemConfigManagedKeys($managedKeys);
        $expectedByKey = $this->indexSharedSystemConfigRows(
            $managedKeys,
            $expectedRows,
            'owned state'
        );
        $expected = $this->sortSharedSystemConfigRows(array_values($expectedByKey));
        $actual = $this->readSharedSystemConfigRowsFromWritePdo($managedKeys);
        if ($actual !== $expected) {
            throw new \RuntimeException(
                'Shared system config fixture owned-state capture mismatch: expected '
                . json_encode($expected)
                . ', actual '
                . json_encode($actual)
                . '.'
            );
        }

        $ownedState = [];
        foreach ($managedKeys as $key) {
            $ownedState[$key] = $expectedByKey[$key] ?? null;
        }
        $this->sharedSystemConfigFixtureOwnedState = $ownedState;
    }

    /**
     * @param array<int, string> $managedKeys
     * @param array<int, array<string, mixed>> $ownedRows
     * @param array<int, string> $deleteKeys
     * @return array<int, array<string, mixed>>
     */
    protected function deleteSharedSystemConfigFixtureOwnedRows(
        array $managedKeys,
        array $ownedRows,
        array $deleteKeys
    ): array {
        $managedKeys = $this->normalizeSharedSystemConfigManagedKeys($managedKeys);
        $byKey = $this->indexSharedSystemConfigRows($managedKeys, $ownedRows, 'owned state');
        $managed = array_fill_keys($managedKeys, true);
        foreach ($deleteKeys as $key) {
            $key = (string) $key;
            if (!isset($managed[$key])) {
                throw new \InvalidArgumentException(
                    'Cannot delete unmanaged shared system config key ' . $key . '.'
                );
            }
            if (isset($byKey[$key])) {
                $affected = $this->sharedSystemConfigOwnedRowQuery($byKey[$key])->delete();
                if ($affected !== 1) {
                    throw new \RuntimeException(
                        'Shared system config ownership changed before deletion for key '
                        . $key
                        . '; affected '
                        . $affected
                        . '.'
                    );
                }
                unset($byKey[$key]);
            }
            $this->captureSharedSystemConfigFixtureOwnedState(
                $managedKeys,
                array_values($byKey)
            );
        }

        return $this->sortSharedSystemConfigRows(array_values($byKey));
    }

    /**
     * @param array<int, string> $managedKeys
     * @param array<int, array<string, mixed>> $beforeRows
     * @param array<string, array{value: string, group: string, description: string, required: bool}> $definitions
     * @return array<int, array<string, mixed>>
     */
    protected function captureSharedSystemConfigFixtureOwnedStateAfterFrontDemoSeeder(
        array $managedKeys,
        array $beforeRows,
        array $definitions,
        int $startedAt,
        int $finishedAt,
        array $knownOverrides = []
    ): array {
        $managedKeys = $this->normalizeSharedSystemConfigManagedKeys($managedKeys);
        $beforeByKey = $this->indexSharedSystemConfigRows(
            $managedKeys,
            $beforeRows,
            'pre-seeder owned state'
        );
        $managed = array_fill_keys($managedKeys, true);
        foreach (array_keys($definitions) as $definitionKey) {
            if (!isset($managed[$definitionKey])) {
                throw new \InvalidArgumentException(
                    'Front demo system config definitions contain unmanaged key '
                    . $definitionKey
                    . '.'
                );
            }
        }

        $usedIds = [];
        foreach ($beforeByKey as $row) {
            $usedIds[(string) $row['id']] = true;
        }
        $actualRows = $this->readSharedSystemConfigRowsFromWritePdo($managedKeys);
        $actualByKey = [];
        foreach ($actualRows as $row) {
            $actualByKey[(string) $row['key']] = $row;
        }
        $clockRow = $actualByKey['deposit_enabled'] ?? null;
        if ($clockRow === null
            || !ctype_digit((string) $clockRow['updated_at'])
            || (int) $clockRow['updated_at'] < $startedAt
            || (int) $clockRow['updated_at'] > $finishedAt) {
            throw new \RuntimeException('Front demo seeder timestamp is outside its known window.');
        }
        $now = (string) $clockRow['updated_at'];
        $expectedByKey = $beforeByKey;
        foreach ($definitions as $key => $definition) {
            $before = $beforeByKey[$key] ?? null;
            if ($definition['required'] && $before !== null) {
                if ($before['deleted_at'] !== null) {
                    $expected = array_replace($before, [
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                } elseif ((string) $before['description'] ===
                    'Required withdrawal config added by 2026-07-15 migration: ' . $key) {
                    $expected = array_replace($before, [
                        'value' => $definition['value'],
                        'group' => $definition['group'],
                        'description' => $definition['description'],
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                } else {
                    $expected = $before;
                }
            } elseif ($before !== null) {
                $expected = array_replace($before, [
                    'value' => $definition['value'],
                    'group' => $definition['group'],
                    'description' => $definition['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $actual = $actualByKey[$key] ?? null;
                $id = $actual === null ? '' : (string) $actual['id'];
                if (!ctype_digit($id) || isset($usedIds[$id])) {
                    throw new \RuntimeException(
                        'Front demo seeder generated an invalid id for key ' . $key . '.'
                    );
                }
                $usedIds[$id] = true;
                $expected = [
                    'id' => $id,
                    'key' => $key,
                    'value' => $definition['value'],
                    'group' => $definition['group'],
                    'description' => $definition['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            $expectedByKey[$key] = $this->normalizeSharedSystemConfigRow($expected);
        }
        foreach ($knownOverrides as $key => $row) {
            if (!isset($managed[$key])) {
                throw new \InvalidArgumentException(
                    'Front demo known override contains unmanaged key ' . $key . '.'
                );
            }
            if ($row === null) {
                unset($expectedByKey[$key]);
            } else {
                $expectedByKey[$key] = $this->normalizeSharedSystemConfigRow($row);
            }
        }
        $expectedRows = $this->sortSharedSystemConfigRows(array_values($expectedByKey));
        if ($actualRows !== $expectedRows) {
            throw new \RuntimeException(
                'Front demo seeder config result did not match its known mutation contract.'
            );
        }
        $this->captureSharedSystemConfigFixtureOwnedState($managedKeys, $expectedRows);

        return $expectedRows;
    }

    /** @return array<string, array{value: string, group: string, description: string, required: bool}> */
    protected function frontDemoSystemConfigDefinitions(): array
    {
        return [
            'deposit_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo deposit switch', 'required' => false],
            'deposit_exchange_rate_cny' => ['value' => '7.12', 'group' => 'finance', 'description' => 'Demo CNY deposit rate', 'required' => false],
            'deposit_min_amount' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo min deposit amount', 'required' => false],
            'deposit_max_amount' => ['value' => '500000', 'group' => 'finance', 'description' => 'Demo max deposit amount', 'required' => false],
            'withdrawal_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo withdrawal switch', 'required' => true],
            'withdrawal_weekend_enabled' => ['value' => '1', 'group' => 'finance', 'description' => 'Demo weekend withdrawal switch', 'required' => true],
            'withdrawal_start_time' => ['value' => '', 'group' => 'finance', 'description' => 'Demo withdrawal start time', 'required' => true],
            'withdrawal_end_time' => ['value' => '', 'group' => 'finance', 'description' => 'Demo withdrawal end time', 'required' => true],
            'withdraw_exchange_rate_cny' => ['value' => '7.05', 'group' => 'finance', 'description' => 'Demo CNY withdrawal rate', 'required' => true],
            'withdraw_min_amount' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo min withdrawal amount', 'required' => true],
            'withdraw_max_amount' => ['value' => '50000', 'group' => 'finance', 'description' => 'Demo max withdrawal amount', 'required' => true],
            'withdraw_risk_rate_limit' => ['value' => '50', 'group' => 'finance', 'description' => 'Demo withdrawal risk limit', 'required' => true],
            'withdraw_check_open' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo open-position withdrawal check', 'required' => true],
            'withdrawal_fee_rate' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo withdrawal fee rate', 'required' => true],
            'withdrawal_fixed_fee_usd' => ['value' => '0', 'group' => 'finance', 'description' => 'Demo fixed withdrawal fee', 'required' => true],
            'download_pc_url' => ['value' => '#', 'group' => 'front', 'description' => 'Demo PC download URL', 'required' => false],
            'download_mobile_url' => ['value' => '#', 'group' => 'front', 'description' => 'Demo mobile download URL', 'required' => false],
        ];
    }

    /**
     * 共享前端 demo 系统配置夹具定义（frontDemoSystemConfigDefinitions 的共享语义别名）。
     *
     * @return array<string, array{value: string, group: string, description: string, required: bool}> 配置定义。
     */
    protected function sharedFrontDemoSystemConfigFixtureDefinitions(): array
    {
        return $this->frontDemoSystemConfigDefinitions();
    }

    protected function acquireSharedSystemConfigFixtureLock(): void
    {
        if ($this->hasSharedSystemConfigFixtureLockState()) {
            throw new \LogicException('The shared system config fixture lock already has connection state.');
        }

        $connectionName = 'shared_system_config_fixture_lock_' . substr(
            hash('sha256', static::class . ':' . spl_object_hash($this)),
            0,
            32
        );
        config([
            'database.connections.' . $connectionName => config(
                'database.connections.' . DB::getDefaultConnection()
            ),
        ]);
        DB::purge($connectionName);

        $connection = DB::connection($connectionName);
        $connection->unsetEventDispatcher();
        $lockName = $this->sharedSystemConfigFixtureAdvisoryLockName();
        $this->sharedSystemConfigFixtureLockConnection = $connection;
        $this->sharedSystemConfigFixtureLockConnectionName = $connectionName;
        $this->sharedSystemConfigFixtureLockName = $lockName;

        try {
            $result = $this->requestSharedSystemConfigFixtureLock($connection, $lockName);
            if (!$result || (int) $result->acquired !== 1) {
                throw new \RuntimeException('Unable to acquire the shared system config fixture lock.');
            }

            $this->sharedSystemConfigFixtureLockAcquired = true;
            $this->sharedSystemConfigFixtureAutoIncrementSnapshot =
                $this->readSharedSystemConfigFixtureAutoIncrement($connection);
        } catch (\Throwable $exception) {
            try {
                $this->releaseSharedSystemConfigFixtureLock();
            } catch (\Throwable $releaseException) {
                throw new \RuntimeException(
                    'Shared system config fixture lock setup cleanup failed: '
                    . $releaseException->getMessage(),
                    0,
                    $exception
                );
            }
            throw $exception;
        }
    }

    /**
     * 获取共享系统配置夹具锁并捕获 owned state。
     *
     * 组合 acquireSharedSystemConfigFixtureLock 与 captureSharedSystemConfigFixtureOwnedState；
     * 锁获取失败时由 acquire 抛出 RuntimeException；捕获失败时回滚释放锁后抛出原异常。
     */
    protected function acquireSharedSystemConfigFixtureLockAndCaptureOwnedState(): void
    {
        $this->acquireSharedSystemConfigFixtureLock();
        try {
            $this->captureSharedSystemConfigFixtureOwnedState(
                $this->managedConfigKeys(),
                $this->ownedConfigRows
            );
        } catch (\Throwable $exception) {
            try {
                $this->releaseSharedSystemConfigFixtureLock();
            } catch (\Throwable $releaseException) {
                throw new \RuntimeException(
                    'Shared system config fixture owned-state capture cleanup failed: '
                    . $releaseException->getMessage(),
                    0,
                    $exception
                );
            }
            throw $exception;
        }
    }

    protected function releaseSharedSystemConfigFixtureLock(): void
    {
        $connection = $this->sharedSystemConfigFixtureLockConnection;
        $connectionName = $this->sharedSystemConfigFixtureLockConnectionName;
        $lockName = $this->sharedSystemConfigFixtureLockName;
        $acquired = $this->sharedSystemConfigFixtureLockAcquired;
        $failureMessages = [];
        $firstFailure = null;

        try {
            if ($connection !== null && $acquired
                && $this->sharedSystemConfigFixtureAutoIncrementSnapshot !== null) {
                try {
                    $this->restoreSharedSystemConfigFixtureAutoIncrement();
                } catch (\Throwable $exception) {
                    $firstFailure = $exception;
                    $failureMessages[] = 'AUTO_INCREMENT restore failed: ' . $exception->getMessage();
                }
            }

            if ($connection !== null && $lockName !== null && $acquired) {
                try {
                    $released = $this->requestSharedSystemConfigFixtureLockRelease(
                        $connection,
                        $lockName
                    );
                    if (!$released || (int) $released->released !== 1) {
                        throw new \RuntimeException(
                            'The shared system config fixture lock was not owned by its stored connection.'
                        );
                    }
                } catch (\Throwable $exception) {
                    if ($firstFailure === null) {
                        $firstFailure = $exception;
                    }
                    $failureMessages[] = 'advisory lock release failed: ' . $exception->getMessage();
                }
            }
        } finally {
            try {
                if ($connection !== null) {
                    $connection->disconnect();
                }
            } finally {
                $this->sharedSystemConfigFixtureLockConnection = null;
                $this->sharedSystemConfigFixtureLockConnectionName = null;
                $this->sharedSystemConfigFixtureLockName = null;
                $this->sharedSystemConfigFixtureLockAcquired = false;
                $this->sharedSystemConfigFixtureAutoIncrementSnapshot = null;
                $this->sharedSystemConfigFixtureOwnedState = null;
                if ($connectionName !== null) {
                    DB::purge($connectionName);
                }
            }
        }

        if ($failureMessages !== []) {
            throw new \RuntimeException(
                'Shared system config fixture lock release failed: ' . implode(' | ', $failureMessages),
                0,
                $firstFailure
            );
        }
    }

    protected function hasSharedSystemConfigFixtureLockState(): bool
    {
        return $this->sharedSystemConfigFixtureLockConnection !== null
            || $this->sharedSystemConfigFixtureLockConnectionName !== null
            || $this->sharedSystemConfigFixtureLockName !== null
            || $this->sharedSystemConfigFixtureLockAcquired
            || $this->sharedSystemConfigFixtureAutoIncrementSnapshot !== null
            || $this->sharedSystemConfigFixtureOwnedState !== null;
    }

    protected function sharedSystemConfigFixtureAdvisoryLockName(): string
    {
        return 'wdr:test-fixture:' . substr(
            hash('sha256', DB::getDatabaseName()),
            0,
            40
        );
    }

    protected function requestSharedSystemConfigFixtureLock($connection, string $lockName)
    {
        return $connection->selectOne(
            'SELECT GET_LOCK(?, 10) AS acquired',
            [$lockName],
            false
        );
    }

    protected function requestSharedSystemConfigFixtureLockRelease($connection, string $lockName)
    {
        return $connection->selectOne(
            'SELECT RELEASE_LOCK(?) AS released',
            [$lockName],
            false
        );
    }

    private function restoreSharedSystemConfigFixtureAutoIncrement(): void
    {
        $connection = $this->sharedSystemConfigFixtureLockConnection;
        $expected = $this->sharedSystemConfigFixtureAutoIncrementSnapshot;
        if ($connection === null || !$this->sharedSystemConfigFixtureLockAcquired || $expected === null) {
            throw new \RuntimeException(
                'The shared system config fixture AUTO_INCREMENT snapshot is unavailable.'
            );
        }

        $actual = $this->readSharedSystemConfigFixtureAutoIncrement($connection);
        if ($actual === $expected) {
            return;
        }

        $connection->statement(
            'ALTER TABLE `system_configs` AUTO_INCREMENT = ' . $expected
        );
        $actual = $this->readSharedSystemConfigFixtureAutoIncrement($connection);
        if ($actual !== $expected) {
            throw new \RuntimeException(
                'system_configs AUTO_INCREMENT mismatch: expected '
                . $expected
                . ', actual '
                . $actual
                . '.'
            );
        }
    }

    protected function readSharedSystemConfigFixtureAutoIncrement($connection): int
    {
        try {
            $versionStatus = $connection->selectOne(
                'SELECT VERSION() AS version',
                [],
                false
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                'Unable to read database server version: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
        if (!$versionStatus || !isset($versionStatus->version)) {
            throw new \RuntimeException('Unable to read database server version.');
        }

        $version = trim((string) $versionStatus->version);
        if (preg_match('/(\d+)\.(\d+)(?:\.\d+)?/', $version, $matches) !== 1) {
            throw new \RuntimeException('Unable to parse database server version: ' . $version);
        }

        $isMariaDb = stripos($version, 'mariadb') !== false;
        $major = (int) $matches[1];
        if (!$isMariaDb && $major >= 8) {
            $connection->statement('SET SESSION information_schema_stats_expiry = 0');
        }

        $status = $connection->selectOne(
            'SELECT AUTO_INCREMENT AS auto_increment '
            . 'FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['system_configs'],
            false
        );
        if (!$status || $status->auto_increment === null) {
            throw new \RuntimeException('Unable to read system_configs AUTO_INCREMENT.');
        }

        return (int) $status->auto_increment;
    }

    /**
     * @param array<string, mixed>|null $snapshotRow
     * @param array<string, mixed>|null $ownedRow
     */
    private function restoreSharedSystemConfigKey(
        string $key,
        array $snapshotRow = null,
        array $ownedRow = null
    ): void {
        if ($ownedRow === null) {
            if ($snapshotRow === null) {
                $current = $this->readSharedSystemConfigRowFromWritePdo($key);
                if ($current !== null) {
                    throw new \RuntimeException(
                        'Owned absence changed before restore for system config key ' . $key . '.'
                    );
                }

                return;
            }

            $this->insertMissingSharedSystemConfigSnapshotRow($key, $snapshotRow);

            return;
        }

        if ($snapshotRow === null) {
            $affected = $this->sharedSystemConfigOwnedRowQuery($ownedRow)->delete();
            if ($affected !== 1) {
                throw new \RuntimeException(
                    'Owned row changed before delete for system config key '
                    . $key
                    . '; affected '
                    . $affected
                    . '.'
                );
            }

            return;
        }

        if ($ownedRow === $snapshotRow) {
            $current = $this->readSharedSystemConfigRowFromWritePdo($key);
            if ($current !== $ownedRow) {
                throw new \RuntimeException(
                    'Owned row changed before verification for system config key ' . $key . '.'
                );
            }

            return;
        }

        $affected = $this->sharedSystemConfigOwnedRowQuery($ownedRow)->update($snapshotRow);
        if ($affected !== 1) {
            // 嵌套夹具实例可能只改动了 created_at/updated_at：严格全列匹配会失败。
            // 按 id+key+description 复核行仍归本夹具所有后，允许按稳定身份回退更新，
            // 保证外层生命周期能把配置恢复成快照，同时不放松对行身份的保护。
            $current = $this->readSharedSystemConfigRowFromWritePdo($key);
            if ($current !== null
                && (string) $current['id'] === (string) $ownedRow['id']
                && (string) $current['key'] === (string) $ownedRow['key']
                && (string) $current['description'] === (string) $ownedRow['description']) {
                $update = $snapshotRow;
                unset($update['id'], $update['key']);
                $affected = DB::table('system_configs')
                    ->useWritePdo()
                    ->where('id', (string) $ownedRow['id'])
                    ->where('key', (string) $ownedRow['key'])
                    ->update($update);
                if ($affected === 1) {
                    return;
                }
            }
            throw new \RuntimeException(
                'Owned row changed before update for system config key '
                . $key
                . '; affected '
                . $affected
                . '.'
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function insertMissingSharedSystemConfigSnapshotRow(string $key, array $row): void
    {
        $firstFailure = null;
        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $this->insertSharedSystemConfigSnapshotRow($row);

                return;
            } catch (\Throwable $exception) {
                if ($firstFailure === null) {
                    $firstFailure = $exception;
                }
                if ($this->readSharedSystemConfigRowFromWritePdo($key) !== null) {
                    throw new \RuntimeException(
                        'Owned absence changed before insert for system config key ' . $key . '.',
                        0,
                        $exception
                    );
                }
                if ($attempt === 2) {
                    throw $firstFailure;
                }
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function sharedSystemConfigOwnedRowQuery(array $row)
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

    /** @return array<string, mixed>|null */
    private function readSharedSystemConfigRowFromWritePdo(string $key): ?array
    {
        $row = DB::table('system_configs')->useWritePdo()->where('key', $key)->first();

        return $row === null ? null : $this->normalizeSharedSystemConfigRow((array) $row);
    }

    /**
     * @param array<int, string> $managedKeys
     * @return array<int, array<string, mixed>>
     */
    private function readSharedSystemConfigRowsFromWritePdo(array $managedKeys): array
    {
        return DB::table('system_configs')
            ->useWritePdo()
            ->whereIn('key', $managedKeys)
            ->orderBy('key')
            ->get()
            ->map(function ($row): array {
                return $this->normalizeSharedSystemConfigRow((array) $row);
            })
            ->all();
    }

    /**
     * @param array<int, string> $managedKeys
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexSharedSystemConfigRows(
        array $managedKeys,
        array $rows,
        string $label
    ): array {
        $managed = array_fill_keys($managedKeys, true);
        $byKey = [];
        foreach ($rows as $row) {
            if (!array_key_exists('id', $row) || !array_key_exists('key', $row)) {
                throw new \InvalidArgumentException(
                    'Shared system config ' . $label . ' row must contain id and key.'
                );
            }
            $key = (string) $row['key'];
            if (!isset($managed[$key])) {
                throw new \InvalidArgumentException(
                    'Shared system config ' . $label . ' contains unmanaged key ' . $key . '.'
                );
            }
            if (array_key_exists($key, $byKey)) {
                throw new \InvalidArgumentException(
                    'Shared system config ' . $label . ' contains duplicate key ' . $key . '.'
                );
            }
            $byKey[$key] = $this->normalizeSharedSystemConfigRow($row);
        }

        return $byKey;
    }

    /** @param array<int, string> $managedKeys @return array<int, string> */
    private function normalizeSharedSystemConfigManagedKeys(array $managedKeys): array
    {
        $normalized = array_values(array_map('strval', $managedKeys));
        if ($normalized === [] || count(array_unique($normalized)) !== count($normalized)) {
            throw new \InvalidArgumentException(
                'Shared system config managed keys must be nonempty and unique.'
            );
        }

        return $normalized;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeSharedSystemConfigRow(array $row): array
    {
        foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = (string) $row[$column];
            }
        }
        ksort($row);

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortSharedSystemConfigRows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) $left['key'], (string) $right['key']);
        });

        return $rows;
    }

    protected function beforeRestoreSharedSystemConfigKey(string $key): void
    {
    }

    /** @param array<string, mixed> $row */
    protected function insertSharedSystemConfigSnapshotRow(array $row): void
    {
        DB::table('system_configs')->insert($row);
    }
}
