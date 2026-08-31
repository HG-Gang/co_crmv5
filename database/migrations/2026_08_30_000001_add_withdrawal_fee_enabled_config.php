<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/30
 * Time: 23:10
 */

/**
 * 新增「是否扣取出金手续费」总开关配置。
 *
 * 文件功能：
 * - 幂等写入 system_configs.withdrawal_fee_enabled，使「是否扣手续费」与「扣多少」成为两个
 *   独立可配置维度：开关关闭时无论固定费与费率配成多少都不扣，开关开启时才按金额计算。
 *
 * 为什么需要独立开关：
 * - 此前只有 withdrawal_fee_rate 与 withdrawal_fixed_fee_usd 两个金额键，
 *   要停收手续费只能把两个金额都改成 0；一旦运营想临时停收又保留原费率标准，就必须
 *   先记下原值再清零、恢复时再填回，极易配错且无审计痕迹。
 * - 项目1 线上出金路径手续费恒为 0（其收费分支在 UserWithdrawController 中被注释掉），
 *   而项目2 的 LegacyFrontReferenceSeeder 会把旧库 sys_poundage_money 映射成生效固定费，
 *   两者口径不同。有了开关后，这个差异可由运营按业务口径显式决定，而不是被 seeder 隐式决定。
 *
 * 默认值取 '1'（开启）的原因：
 * - 保持既有库的行为完全不变 —— 已配 5.00 固定费的库升级后仍照旧扣费，不产生资金口径突变；
 * - 全新库的两个金额键默认都是 '0'，开关为 '1' 时算出的费用同样是 0，行为一致；
 * - 因此本迁移对任何环境都是零行为变更，「是否停收」交由管理员在后台显式操作。
 *
 * 字段语义：
 * - 仅操作 system_configs 字典数据，不涉及表结构；重复执行安全，回滚只删除本迁移自建且未被人工改动的行。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AddWithdrawalFeeEnabledConfig extends Migration
{
    /**
     * 本迁移写入的配置键：出金手续费总开关，'1' 表示扣取、'0' 表示不扣。
     */
    private const CONFIG_KEY = 'withdrawal_fee_enabled';

    /**
     * 默认值 '1'（开启）。取值理由见文件头说明：保证对既有库与全新库都是零行为变更。
     */
    private const DEFAULT_VALUE = '1';

    /**
     * 配置分组，与既有出金配置保持一致，便于后台按 finance 分组集中维护。
     */
    private const CONFIG_GROUP = 'finance';

    /**
     * description 前缀。写死迁移日期便于审计时区分「迁移写入」与「人工维护」，
     * 同时作为 down() 判断该行是否仍为本迁移原始状态的依据之一。
     */
    private const DESCRIPTION = 'Withdrawal fee master switch added by 2026-08-30 migration: withdrawal_fee_enabled';

    /**
     * 幂等写入开关配置。
     *
     * 已存在同 key 时一律保留人工配置值，只在被软删除的情况下恢复，
     * 绝不覆盖运营已设置的开关状态。
     *
     * @return void
     */
    public function up(): void
    {
        $now = time();

        if ($this->preserveOrRestoreExisting($now)) {
            return;
        }

        try {
            DB::table('system_configs')->insert([
                'key' => self::CONFIG_KEY,
                'value' => self::DEFAULT_VALUE,
                'group' => self::CONFIG_GROUP,
                'description' => self::DESCRIPTION,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        } catch (QueryException $exception) {
            // 并发迁移可能已抢先插入同一唯一键；重读该键并保留/恢复，
            // 非唯一键冲突的其它错误仍然抛出，不掩盖真实故障。
            if (!$this->preserveOrRestoreExisting($now)) {
                throw $exception;
            }
        }
    }

    /**
     * 回滚：只删除仍处于本迁移原始状态的行。
     *
     * 值、分组、说明任一被人工改动，或创建与更新时间已不一致（说明被编辑过），
     * 都视为运营正在使用该配置而保留不删，避免回滚误删生产配置。
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('system_configs')
            ->where('key', self::CONFIG_KEY)
            ->where('value', self::DEFAULT_VALUE)
            ->where('group', self::CONFIG_GROUP)
            ->where('description', self::DESCRIPTION)
            ->whereNull('deleted_at')
            ->whereColumn('created_at', 'updated_at')
            ->delete();
    }

    /**
     * 保留已存在的配置行；若被软删除则恢复为启用状态。
     *
     * @param int $now 10 位时间戳，用于恢复时写入 updated_at。
     * @return bool true 表示该键已存在（已保留或已恢复），调用方无需再插入。
     */
    protected function preserveOrRestoreExisting(int $now): bool
    {
        $existing = DB::table('system_configs')->where('key', self::CONFIG_KEY)->first();
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

            // 恢复未命中说明有并发写入抢先处理；重读确认最终状态是否已启用。
            $current = DB::table('system_configs')->where('key', self::CONFIG_KEY)->first();

            return $current !== null && $current->deleted_at === null;
        }

        return true;
    }
}
