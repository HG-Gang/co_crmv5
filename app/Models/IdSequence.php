<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 00:56
 */

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * ID 序列模型。
 *
 * 文件功能：
 * - id_sequences 表保存业务用户编号生成状态，用于代理和客户注册时生成稳定的业务 user_id。
 * - type 表示序列类型，例如 agent=代理编号，customer=客户编号。
 * - current_value 表示当前已发放的最大编号。
 * - prefix 表示编号前缀，当前逻辑保留该字段但 nextId() 只返回数值编号。
 * - step 表示每次递增步长，默认通常为 1。
 */
class IdSequence extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 id_sequences。
     */
    protected $table = 'id_sequences';

    /**
     * 获取指定业务类型的下一个编号。
     *
     * 功能逻辑说明：
     * - $type 表示需要生成编号的业务类型，当前注册链路主要传入 agent 或 customer。
     * - lockForUpdate() 用于保证并发生成编号时不会重复，同一事务内锁定对应序列行后再递增。
     * - 序列不存在时会按业务类型初始化：agent 从 1000 开始，customer 从 600000 开始。
     * - 返回值是写回 current_value 后的新编号，调用方可直接作为业务 user_id 使用。
     *
     * @param string $type 表示需要生成编号的业务类型，例如 agent 或 customer。
     * @return int 返回当前业务类型生成后的最新编号。
     * @throws \RuntimeException 数据库事务或写入失败时由底层异常向上抛出。
     */
    public static function nextId(string $type): int
    {
        return DB::transaction(function () use ($type) {
            $seq = self::where('type', $type)->lockForUpdate()->first();
            $accountType = $type === 'agent' ? 1 : 2;
            $existingMax = (int) UserInfo::withTrashed()
                ->where('account_type', $accountType)
                ->max('user_id');
            $startValue = $type === 'agent' ? 1000 : 600000;

            if (!$seq) {
                // 序列不存在时按业务类型创建初始值，确保旧项目代理和客户编号区间不混用。
                $seq = self::create([
                    'type'          => $type,
                    'current_value' => max($startValue, $existingMax),
                    'step'          => 1
                ]);
            }

            $step = max(1, (int) $seq->step);
            $nextVal = max((int) $seq->current_value, $startValue, $existingMax) + $step;
            while (self::businessUserIdExists($nextVal)) {
                $nextVal += $step;
            }

            $seq->update(['current_value' => $nextVal]);
            return $nextVal;
        });
    }

    /**
     * 检查业务用户编号是否已被任一注册数据占用。
     *
     * 历史迁移或测试数据可能跨越代理、客户的预设编号区间，因此不能只比较
     * 当前账户类型的最大编号。逐个检查候选值可保留两套序列，同时满足全局唯一约束。
     */
    private static function businessUserIdExists(int $userId): bool
    {
        return UserInfo::withTrashed()->where('user_id', $userId)->exists()
            || UserLogin::withTrashed()->where('user_id', $userId)->exists()
            || UserMt4ProvisioningOutbox::withTrashed()->where('user_id', $userId)->exists();
    }
}
