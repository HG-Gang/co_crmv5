<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 00:36
 */

/**
 * 遗留前台用户测试夹具。
 *
 * 文件功能：创建前台路由所需的 user_logins 与 user_infos 最小关联数据，
 * 并登记当前测试拥有的 user_id，供异常路径和 tearDown 按外键方向清理。
 *
 * 边界说明：本 Trait 不创建真实 MT4 账户，也不吞掉写入或清理异常；
 * 调用方若会推进 AUTO_INCREMENT，必须在外层持锁并负责恢复表元数据。
 */
namespace Tests\Support;

use App\Models\UserLogin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

trait CreatesLegacyFrontUserFixture
{
    /** @var array<int, int> */
    private $legacyFrontFixtureUserIds = [];

    /**
     * 创建一名可访问遗留前台路由的最小用户。
     *
     * @param int $userId 测试调用方生成并拥有的业务用户 ID。
     * @param int $accountType 旧系统账户类型，1 为代理商，2 为普通用户。
     * @param string $userName 前台展示名称。
     * @return UserLogin 已持久化的登录模型。
     *
     * @throws \Throwable 任一表写入失败时先尝试删除本夹具，再原样抛出。
     */
    protected function createLegacyFrontUserFixture(
        int $userId,
        int $accountType = 2,
        string $userName = 'Legacy Front Fixture User'
    ): UserLogin {
        if (!in_array($userId, $this->legacyFrontFixtureUserIds, true)) {
            // 首次写入前登记所有权，确保非事务旧表发生部分写入时仍能按 user_id 清理。
            $this->legacyFrontFixtureUserIds[] = $userId;
        }

        try {
            $this->deleteLegacyFrontUserFixture($userId);

            $now = time();
            $loginId = (int) DB::table('user_logins')->insertGetId([
                'user_id' => $userId,
                'email' => 'legacy-front-fixture-' . $userId . '-' . bin2hex(random_bytes(4)) . '@example.test',
                'password' => Hash::make('abc123'),
                'account_type' => $accountType,
                'role_id' => 0,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 0,
                'jwt_token_id' => '',
                'last_login_ip' => '',
                'last_login_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            DB::table('user_infos')->insert([
                'user_id' => $userId,
                'login_id' => $loginId,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'avatar' => null,
                'level_id' => 0,
                'group_id' => 0,
                'parent_id' => 0,
                'account_type' => $accountType,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'used_margin' => 0,
                'avail_margin' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'risk_ratio' => 0,
                'margin_amount' => 0,
                'leverage' => 0,
                'cust_vol' => '0',
                'pay_provider_id' => 0,
                'equity_ratio' => 0,
                'comm_rate' => 0,
                'is_ecn' => 0,
                'follow_parent_ecn' => 0,
                'auth_status' => 1,
                'is_mt4_synced' => 1,
                'is_mt4_enabled' => 1,
                'is_mt4_readonly' => 0,
                'is_withdrawal_allowed' => 0,
                'is_deposit_allowed' => 0,
                'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
                'original_group' => '',
                'mt4_group' => '',
                'mt4_code' => 0,
                'trading_mode' => 0,
                'settle_method' => 1,
                'settle_cycle' => 0,
                'country' => '',
                'city' => '',
                'state' => '',
                'address' => '',
                'is_gift_allowed' => 0,
                'data_source' => 0,
                'remark' => 'PHPUnit legacy front authentication fixture',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            return UserLogin::query()->findOrFail($loginId);
        } catch (\Throwable $exception) {
            $this->deleteLegacyFrontUserFixture($userId);
            throw $exception;
        }
    }

    /**
     * 删除当前测试登记的全部遗留前台用户夹具。
     *
     * @return void 全部登记用户清理完成时无返回值。
     */
    protected function cleanupLegacyFrontUserFixtures(): void
    {
        foreach (array_unique($this->legacyFrontFixtureUserIds) as $userId) {
            $this->deleteLegacyFrontUserFixture($userId);
        }

        $this->legacyFrontFixtureUserIds = [];
    }

    /**
     * 按子表到父表顺序物理删除指定用户夹具。
     *
     * @param int $userId 当前测试拥有的业务用户 ID。
     * @return void 两张表均完成删除时无返回值。
     */
    private function deleteLegacyFrontUserFixture(int $userId): void
    {
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
    }
}
