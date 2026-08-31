<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:56
 */

namespace App\Services;

use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserLogin;
use Illuminate\Support\Facades\DB;

/**
 * 用户资料兼容服务。
 *
 * 文件功能：
 * - UserLogin 保存登录账号、密码和登录启停状态。
 * - UserInfo 保存用户基础资料，包含姓名、账号类型、代理关系、资金和认证状态等业务字段。
 * - UserAuth 保存实名认证资料，包含身份证、银行卡及其审核状态。
 * - 本服务供旧后台迁移和后台用户模块复用，所有参数必须以当前真实数据表字段含义为准。
 */
class UserService
{
    /**
     * 获取用户完整详情。
     *
     * 逻辑说明：
     * - 先从 user_logins 读取登录账号；登录记录不存在时说明该业务用户无法登录，直接返回空数组。
     * - 再分别读取 user_infos 与 user_auths，组合为后台详情页可直接使用的结构。
     *
     * 参数说明：
     * - $userId 表示业务用户 ID，对应 user_logins.user_id、user_infos.user_id 和 user_auths.user_id。
     *
     * @param int $userId 业务用户 ID。
     * @return array<string, mixed> 返回 login、info、auth 三段用户资料；用户不存在时返回空数组。
     */
    public function getUserDetail(int $userId): array
    {
        $login = UserLogin::where('user_id', $userId)->first();
        if (! $login) {
            return [];
        }

        $info = UserInfo::where('user_id', $userId)->first();
        $auth = UserAuth::where('user_id', $userId)->first();

        return [
            'login' => $login->toArray(),
            'info' => $info ? $info->toArray() : [],
            'auth' => $auth ? $auth->toArray() : [],
        ];
    }

    /**
     * 更新用户基础资料字段。
     *
     * 逻辑说明：
     * - 只更新 user_infos 表，登录邮箱、密码、启停状态等字段不在本方法处理。
     * - 调用方必须先完成字段白名单过滤，避免把页面提交的无关字段直接写入资料表。
     *
     * 参数说明：
     * - $userId 表示业务用户 ID，用于定位 user_infos.user_id。
     * - $data 表示允许写入的用户资料字段集合，例如 user_name、phone、group_id、comm_rate 等。
     *
     * @param int $userId 业务用户 ID。
     * @param array<string, mixed> $data 已经过调用方过滤的资料字段集合。
     * @return bool 更新成功返回 true；资料记录不存在或更新失败返回 false。
     */
    public function updateUserInfo(int $userId, array $data): bool
    {
        $info = UserInfo::where('user_id', $userId)->first();
        if (! $info) {
            return false;
        }

        return $info->update($data);
    }

    /**
     * 更新用户登录启停状态和实名认证状态。
     *
     * 逻辑说明：
     * - is_enabled 表示 user_logins.is_enabled，1=允许登录，0=禁止登录。
     * - auth_status 表示实名认证状态，当前兼容写入 user_auths.status。
     * - 两类状态放在同一个事务中执行，避免登录状态和认证状态部分更新后产生不一致。
     *
     * 参数说明：
     * - $userId 表示业务用户 ID，用于定位 user_logins.user_id 与 user_auths.user_id。
     * - $data 表示状态字段集合，只处理 is_enabled 和 auth_status 两个键。
     *
     * @param int $userId 业务用户 ID。
     * @param array<string, mixed> $data 状态字段集合，允许包含 is_enabled、auth_status。
     * @return bool 全部请求的状态字段更新成功返回 true；任一更新失败返回 false。
     */
    public function updateUserStatus(int $userId, array $data): bool
    {
        return DB::transaction(function () use ($userId, $data) {
            $success = true;

            if (isset($data['is_enabled'])) {
                $success = $success && UserLogin::where('user_id', $userId)->update(['is_enabled' => $data['is_enabled']]);
            }

            if (isset($data['auth_status'])) {
                $success = $success && UserAuth::where('user_id', $userId)->update(['status' => $data['auth_status']]);
            }

            return $success;
        });
    }

    /**
     * 标记用户为已注销。
     *
     * 逻辑说明：
     * - is_cancelled 表示用户注销标记，写入 user_logins.is_cancelled。
     * - 本方法不物理删除用户资料，保留后续审计、资金流水和交易记录关联能力。
     *
     * 参数说明：
     * - $userId 表示业务用户 ID，用于定位 user_logins.user_id。
     *
     * @param int $userId 业务用户 ID。
     * @return bool 成功写入注销标记返回 true；账号不存在或更新失败返回 false。
     */
    public function deleteUser(int $userId): bool
    {
        return (bool) UserLogin::where('user_id', $userId)->update(['is_cancelled' => 1]);
    }
}
