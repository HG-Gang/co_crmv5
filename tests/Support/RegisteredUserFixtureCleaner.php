<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:05
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Models\AgentDescendant;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserLoginLog;
use App\Models\UserMt4ProvisioningOutbox;
use App\Models\UserOnline;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 真实注册用户测试夹具清理器。
 *
 * 文件功能：
 * - 按测试明确拥有的业务 user_id 清理注册、登录审计与在线状态记录。
 * - 先物理删除 MT4 Outbox 和子表，再删除 user_logins 父记录，保持未来外键兼容。
 *
 * 安全边界：
 * - 不接受状态、邮箱模糊条件或空值作为删除范围，禁止扩大到非测试数据。
 * - 所有软删除模型均使用 forceDelete，避免 deleted_at 行继续占用唯一键。
 */
final class RegisteredUserFixtureCleaner
{
    /**
     * 物理删除指定测试用户的完整夹具生命周期。
     *
     * @param iterable<int, int|string> $userIds 测试注册响应或测试邮箱查询得到的业务 user_id。
     * @return void 空集合时无操作；删除成功后目标用户在相关表中均不存在。
     *
     * @throws InvalidArgumentException 任一 user_id 不是正整数时抛出，禁止隐式扩大删除条件。
     * @throws \Throwable 任一数据库删除失败时回滚并原样抛出。
     */
    public static function forceDelete(iterable $userIds): void
    {
        $normalizedUserIds = [];
        foreach ($userIds as $userId) {
            if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
                throw new InvalidArgumentException('注册用户测试夹具的 user_id 必须是正整数。');
            }

            $normalizedUserId = (int) $userId;
            if ($normalizedUserId <= 0) {
                throw new InvalidArgumentException('注册用户测试夹具的 user_id 必须大于 0。');
            }

            $normalizedUserIds[$normalizedUserId] = $normalizedUserId;
        }

        if ($normalizedUserIds === []) {
            return;
        }

        $ownedUserIds = array_values($normalizedUserIds);
        DB::transaction(static function () use ($ownedUserIds): void {
            // Outbox 包含加密开户载荷且 user_id 唯一，必须最先物理删除，不能仅写 deleted_at。
            UserMt4ProvisioningOutbox::withTrashed()
                ->whereIn('user_id', $ownedUserIds)
                ->forceDelete();

            // 登录审计和在线状态都引用业务用户，先于登录父记录清理以兼容后续补充物理外键。
            UserLoginLog::withTrashed()->whereIn('user_id', $ownedUserIds)->forceDelete();
            UserOnline::query()->whereIn('user_id', $ownedUserIds)->delete();

            AgentDescendant::withTrashed()->whereIn('descendant_id', $ownedUserIds)->forceDelete();
            AgentDescendant::withTrashed()->whereIn('agent_id', $ownedUserIds)->forceDelete();
            UserAuth::withTrashed()->whereIn('user_id', $ownedUserIds)->forceDelete();
            UserInfo::withTrashed()->whereIn('user_id', $ownedUserIds)->forceDelete();
            UserLogin::withTrashed()->whereIn('user_id', $ownedUserIds)->forceDelete();
        });
    }
}
