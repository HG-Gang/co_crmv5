<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:14
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 旧项目交易组配置同步服务。
 *
 * 文件功能：
 * - 第一阶段按 legacy_group_id 认领或新增当前 group_configs 记录。
 * - 第二阶段把旧 legacy_pair_id 转换为当前 group_configs.id，并写入 pair_id。
 * - 可按 user_infos.mt4_group 修复历史迁移中反向映射的 group_id。
 *
 * 入参示例：
 * - legacy_group_id=1、legacy_pair_id=15、name=GMTK。
 *
 * 返回值：
 * - 返回以旧 group_config.id 为键、当前 group_configs.id 为值的映射数组。
 *
 * 失败场景：
 * - 旧主键、组名无效时忽略该行。
 * - 配对旧主键不存在于同批输入时抛出异常并回滚，禁止生成半套配对关系。
 */
class LegacyGroupConfigSynchronizer
{
    /**
     * 同步一批已经标准化的旧交易组数据。
     *
     * 每行必须提供 legacy_group_id、legacy_pair_id、name、radix、category、has_commission、
     * is_enabled、is_ecn、is_default、created_by、updated_by、created_at、updated_at 和 deleted_at。
     * legacy_pair_id 可以为 null，表示该旧组没有配对组。
     *
     * @param array<int, array<string, mixed>> $legacyGroups 标准化后的旧交易组数据。
     * @param int $fallbackTimestamp 缺少有效时间时使用的 10 位 Unix 时间戳。
     * @param bool $repairUserAssignments true 表示按 mt4_group 修复用户当前组主键。
     * @return array<int, int> 旧交易组主键到当前交易组主键的映射。
     *
     * @throws RuntimeException 当旧配对主键没有对应输入记录时抛出。
     */
    public function synchronize(array $legacyGroups, int $fallbackTimestamp, bool $repairUserAssignments = true): array
    {
        $prepared = $this->prepareRows($legacyGroups, $fallbackTimestamp);

        return DB::transaction(function () use ($prepared, $repairUserAssignments) {
            $currentIds = [];

            // 第一阶段只确定每条旧记录的当前身份，不能提前写尚未转换的 pair_id。
            foreach ($prepared as $legacyGroupId => $group) {
                $currentIds[$legacyGroupId] = $this->upsertIdentity($group);
            }

            // 第二阶段在映射完整后，把旧配对主键转换为当前表自关联主键。
            foreach ($prepared as $legacyGroupId => $group) {
                $legacyPairId = $group['legacy_pair_id'];
                if ($legacyPairId !== null && !isset($currentIds[$legacyPairId])) {
                    throw new RuntimeException(
                        '旧交易组 ' . $legacyGroupId . ' 的配对主键 ' . $legacyPairId . ' 不存在于同步数据中。'
                    );
                }

                DB::table('group_configs')
                    ->where('id', $currentIds[$legacyGroupId])
                    ->update([
                        'pair_id' => $legacyPairId === null ? null : $currentIds[$legacyPairId],
                    ]);
            }

            // 修复用户组主键同样依赖完整映射，必须在 pair_id 写入后才能执行。
            if ($repairUserAssignments) {
                $this->repairUserAssignments($prepared, $currentIds);
            }

            ksort($currentIds);

            return $currentIds;
        });
    }

    /**
     * 过滤无效行并统一字段类型。
     *
     * @param array<int, array<string, mixed>> $legacyGroups 原始标准化行。
     * @param int $fallbackTimestamp 默认时间戳。
     * @return array<int, array<string, mixed>> 以 legacy_group_id 为键的有效行。
     */
    private function prepareRows(array $legacyGroups, int $fallbackTimestamp): array
    {
        $prepared = [];

        foreach ($legacyGroups as $legacyGroup) {
            $legacyGroupId = (int) ($legacyGroup['legacy_group_id'] ?? 0);
            $name = trim((string) ($legacyGroup['name'] ?? ''));
            if ($legacyGroupId <= 0 || $name === '') {
                continue;
            }

            $legacyPairId = (int) ($legacyGroup['legacy_pair_id'] ?? 0);
            $prepared[$legacyGroupId] = [
                'legacy_group_id' => $legacyGroupId,
                'legacy_pair_id' => $legacyPairId > 0 ? $legacyPairId : null,
                'name' => $name,
                'radix' => (float) ($legacyGroup['radix'] ?? 50),
                'category' => (int) ($legacyGroup['category'] ?? 2),
                'has_commission' => (int) ($legacyGroup['has_commission'] ?? 0),
                'is_enabled' => (int) ($legacyGroup['is_enabled'] ?? 1),
                'is_ecn' => (int) ($legacyGroup['is_ecn'] ?? 0),
                'is_default' => (int) ($legacyGroup['is_default'] ?? 0),
                'created_by' => (int) ($legacyGroup['created_by'] ?? 0),
                'updated_by' => (int) ($legacyGroup['updated_by'] ?? 0),
                'created_at' => $this->timestamp($legacyGroup['created_at'] ?? null, $fallbackTimestamp),
                'updated_at' => $this->timestamp($legacyGroup['updated_at'] ?? null, $fallbackTimestamp),
                'deleted_at' => $this->nullableTimestamp($legacyGroup['deleted_at'] ?? null),
            ];
        }

        ksort($prepared);

        return $prepared;
    }

    /**
     * 认领旧 Demo 行或按旧身份新增当前交易组。
     *
     * @param array<string, mixed> $group 一条已标准化旧交易组。
     * @return int 当前 group_configs.id。
     */
    private function upsertIdentity(array $group): int
    {
        $legacyGroupId = (int) $group['legacy_group_id'];
        $currentId = (int) DB::table('group_configs')
            ->where('legacy_group_id', $legacyGroupId)
            ->value('id');

        if ($currentId <= 0) {
            // 兼容旧 Demo Seeder 已经写入的 “Legacy {组名}”，认领原行可避免重复数据。
            $currentId = (int) DB::table('group_configs')
                ->whereNull('legacy_group_id')
                ->whereIn('name', [$group['name'], 'Legacy ' . $group['name']])
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$group['name']])
                ->value('id');
        }

        $payload = $group;
        // pair_id 不在第一阶段写入：映射未完整前不能引用可能不存在的目标主键。
        unset($payload['legacy_pair_id']);

        if ($currentId > 0) {
            DB::table('group_configs')->where('id', $currentId)->update($payload);

            return $currentId;
        }

        // 新增行 pair_id 先置空，待第二阶段映射完整后再回填。
        return (int) DB::table('group_configs')->insertGetId(array_merge($payload, [
            'pair_id' => null,
        ]));
    }

    /**
     * 按 MT4 真实组名修复用户所属当前组主键。
     *
     * @param array<int, array<string, mixed>> $prepared 有效旧组数据。
     * @param array<int, int> $currentIds 旧主键到当前主键映射。
     * @return void
     */
    private function repairUserAssignments(array $prepared, array $currentIds): void
    {
        foreach ($prepared as $legacyGroupId => $group) {
            DB::table('user_infos')
                ->where('mt4_group', $group['name'])
                ->where('group_id', '<>', $currentIds[$legacyGroupId])
                ->update(['group_id' => $currentIds[$legacyGroupId]]);
        }
    }

    /**
     * 把时间值转换为 10 位 Unix 时间戳。
     *
     * @param mixed $value 数字时间戳或可解析日期字符串。
     * @param int $fallback 无效值时的默认时间戳。
     * @return int 有效时间戳。
     */
    private function timestamp($value, int $fallback): int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        $timestamp = strtotime(trim((string) ($value ?? '')));

        return $timestamp ?: $fallback;
    }

    /**
     * 把可空删除时间转换为 10 位 Unix 时间戳。
     *
     * @param mixed $value 数字时间戳、日期字符串或空值。
     * @return int|null 有效删除时间；空值返回 null。
     */
    private function nullableTimestamp($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ?: null;
    }
}
