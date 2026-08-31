<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 确保提现必需系统配置存在。
 *
 * 文件功能：
 * - 幂等写入提现开关、时段、限额、费率、汇率等必需配置（system_configs 数据）。
 *
 * 字段语义：
 * - 仅操作 system_configs 字典数据，不涉及表结构；重复执行安全，回滚不删除配置。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EnsureRequiredWithdrawalConfigs extends Migration
{
    /**
     * 迁移写入配置时的 description 前缀。写死迁移日期 2026-07-15：审计时可直接看出该配置
     * 由本迁移写入而非人工维护；保留既有同 key 配置时该前缀用于识别“本迁移创建过”的记录。
     */
    private const DESCRIPTION_PREFIX = 'Required withdrawal config added by 2026-07-15 migration: ';

    public function up(): void
    {
        $now = time();

        foreach ($this->defaults() as $key => $value) {
            if ($this->preserveOrRestoreExisting($key, $now)) {
                continue;
            }

            try {
                DB::table('system_configs')->insert([
                    'key' => $key,
                    'value' => $value,
                    'group' => 'finance',
                    'description' => self::DESCRIPTION_PREFIX . $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            } catch (QueryException $exception) {
                // A concurrent migration may have won the unique-key insert.
                // Re-read the exact key and preserve/reactivate it; unrelated errors still surface.
                if (!$this->preserveOrRestoreExisting($key, $now)) {
                    throw $exception;
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->defaults() as $key => $value) {
            DB::table('system_configs')
                ->where('key', $key)
                ->where('value', $value)
                ->where('group', 'finance')
                ->where('description', self::DESCRIPTION_PREFIX . $key)
                ->whereNull('deleted_at')
                ->whereColumn('created_at', 'updated_at')
                ->delete();
        }
    }

    protected function preserveOrRestoreExisting(string $key, int $now): bool
    {
        $existing = DB::table('system_configs')->where('key', $key)->first();
        if (!$existing) {
            return false;
        }

        if ($existing->deleted_at !== null) {
            $restored = DB::table('system_configs')
                ->where('id', (int) $existing->id)
                ->where('deleted_at', (int) $existing->deleted_at)
                ->update([
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            if ($restored === 1) {
                return true;
            }

            $current = DB::table('system_configs')->where('key', $key)->first();

            return $current !== null && $current->deleted_at === null;
        }

        return true;
    }

    /** @return array<string, string> */
    private function defaults(): array
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
}
