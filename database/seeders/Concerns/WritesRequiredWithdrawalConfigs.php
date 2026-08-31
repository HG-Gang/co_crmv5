<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 提现必需系统配置写入 Trait（供多个 Seeder 复用）。
 *
 * 文件功能：
 * - 按 key 幂等写入/更新 system_configs 中的提现必需配置项。
 * - 可识别并替换迁移阶段写入的占位值（description 匹配 requiredWithdrawalMigrationDescription）。
 * - 并发冲突时最多重试 5 次，保证资金类配置最终一致。
 *
 * 适用场景：
 * - InitialDataSeeder、FrontDemoDataSeeder、LegacyFrontReferenceSeeder 等初始化/迁移 Seeder。
 */

namespace Database\Seeders\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait WritesRequiredWithdrawalConfigs
{
    protected function writeRequiredWithdrawalConfig(
        string $key,
        $value,
        string $group,
        string $description,
        int $now,
        bool $replaceMigrationPlaceholder
    ): void {
        $maxAttempts = 5;
        $pendingUniqueException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $existing = DB::table('system_configs')->where('key', $key)->first();
            if ($existing) {
                if ($existing->deleted_at === null) {
                    $isMigrationPlaceholder = (string) $existing->description
                        === $this->requiredWithdrawalMigrationDescription($key);
                    if (!$isMigrationPlaceholder || !$replaceMigrationPlaceholder) {
                        return;
                    }

                    if ($this->replaceMigrationPlaceholderSnapshot(
                        $existing,
                        $value,
                        $group,
                        $description,
                        $now
                    )) {
                        return;
                    }

                    continue;
                }

                if ($pendingUniqueException !== null) {
                    throw $pendingUniqueException;
                }

                if ($this->restoreSoftDeletedSnapshot($existing, $now)) {
                    return;
                }

                continue;
            }

            if ($pendingUniqueException !== null) {
                throw $pendingUniqueException;
            }

            try {
                $this->insertRequiredWithdrawalConfigRow([
                    'key' => $key,
                    'value' => $value === null ? null : (string) $value,
                    'group' => $group,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
                return;
            } catch (QueryException $exception) {
                if (!$this->isSystemConfigsKeyUniqueViolation($exception)) {
                    throw $exception;
                }

                $winner = DB::table('system_configs')->where('key', $key)->first();
                if ($winner && $winner->deleted_at === null) {
                    $isMigrationPlaceholder = (string) $winner->description
                        === $this->requiredWithdrawalMigrationDescription($key);
                    if (!$isMigrationPlaceholder || !$replaceMigrationPlaceholder) {
                        return;
                    }

                    $pendingUniqueException = $exception;
                    if ($this->replaceMigrationPlaceholderSnapshot(
                        $winner,
                        $value,
                        $group,
                        $description,
                        $now
                    )) {
                        return;
                    }

                    continue;
                }

                throw $exception;
            }
        }

        $current = DB::table('system_configs')->where('key', $key)->first();
        if ($current && $current->deleted_at === null) {
            $isMigrationPlaceholder = (string) $current->description
                === $this->requiredWithdrawalMigrationDescription($key);
            if (!$isMigrationPlaceholder || !$replaceMigrationPlaceholder) {
                return;
            }
        }

        if ($pendingUniqueException !== null) {
            throw $pendingUniqueException;
        }

        throw new \RuntimeException(
            'Unable to stabilize required withdrawal config after 5 attempts: ' . $key
        );
    }

    /** @param array<string, mixed> $attributes */
    protected function insertRequiredWithdrawalConfigRow(array $attributes): void
    {
        DB::table('system_configs')->insert($attributes);
    }

    private function replaceMigrationPlaceholderSnapshot(
        $existing,
        $value,
        string $group,
        string $description,
        int $now
    ): bool {
        $query = DB::table('system_configs')->where('id', (int) $existing->id);
        $query = $this->whereByteExactStringSnapshot($query, $existing);

        return $query
            ->where('created_at', (int) $existing->created_at)
            ->where('updated_at', (int) $existing->updated_at)
            ->whereNull('deleted_at')
            ->update([
                'value' => $value === null ? null : (string) $value,
                'group' => $group,
                'description' => $description,
                'updated_at' => $now,
                'deleted_at' => null,
            ]) === 1;
    }

    private function restoreSoftDeletedSnapshot($existing, int $now): bool
    {
        $query = DB::table('system_configs')->where('id', (int) $existing->id);
        $query = $this->whereByteExactStringSnapshot($query, $existing);

        return $query
            ->where('created_at', (int) $existing->created_at)
            ->where('updated_at', (int) $existing->updated_at)
            ->where('deleted_at', (int) $existing->deleted_at)
            ->update([
                'updated_at' => $now,
                'deleted_at' => null,
            ]) === 1;
    }

    private function whereByteExactStringSnapshot(
        \Illuminate\Database\Query\Builder $query,
        $existing
    ): \Illuminate\Database\Query\Builder {
        if ($query->getConnection()->getDriverName() !== 'mysql') {
            throw new \RuntimeException(
                'Byte-exact required withdrawal config CAS is only implemented for MySQL.'
            );
        }

        return $query
            ->whereRaw('CAST(`key` AS BINARY) <=> CAST(? AS BINARY)', [$existing->key])
            ->whereRaw('CAST(`value` AS BINARY) <=> CAST(? AS BINARY)', [$existing->value])
            ->whereRaw('CAST(`group` AS BINARY) <=> CAST(? AS BINARY)', [$existing->group])
            ->whereRaw('CAST(`description` AS BINARY) <=> CAST(? AS BINARY)', [$existing->description]);
    }

    private function requiredWithdrawalMigrationDescription(string $key): string
    {
        return 'Required withdrawal config added by 2026-07-15 migration: ' . $key;
    }

    private function isSystemConfigsKeyUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return is_array($errorInfo)
            && (string) ($errorInfo[0] ?? '') === '23000'
            && (int) ($errorInfo[1] ?? 0) === 1062
            && strpos((string) ($errorInfo[2] ?? ''), 'system_configs_key_unique') !== false;
    }
}
