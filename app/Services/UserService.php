<?php

namespace App\Services;

use App\Models\UserLogin;
use App\Models\UserInfo;
use App\Models\UserAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Get full user details (login + info + auth)
     * 获取用户完整详情（登录信息+个人资料+实名认证）
     *
     * @param int $userId
     * @return array
     */
    public function getUserDetail(int $userId): array
    {
        $login = UserLogin::where('user_id', $userId)->first();
        if (!$login) {
            return [];
        }

        $info = UserInfo::where('user_id', $userId)->first();
        $auth = UserAuth::where('user_id', $userId)->first();

        return [
            'login' => $login->toArray(),
            'info'  => $info ? $info->toArray() : [],
            'auth'  => $auth ? $auth->toArray() : [],
        ];
    }

    /**
     * Update user information fields
     * 更新用户基本信息
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateUserInfo(int $userId, array $data): bool
    {
        $info = UserInfo::where('user_id', $userId)->first();
        if (!$info) {
            return false;
        }

        return $info->update($data);
    }

    /**
     * Update user status (enabled/disabled, auth_status)
     * 更新用户状态（启用/禁用、认证状态）
     *
     * @param int $userId
     * @param array $data
     * @return bool
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
     * Soft delete a user by setting is_cancelled=1
     * 软删除用户（设置 is_cancelled=1）
     *
     * @param int $userId
     * @return bool
     */
    public function deleteUser(int $userId): bool
    {
        return (bool) UserLogin::where('user_id', $userId)->update(['is_cancelled' => 1]);
    }
}
