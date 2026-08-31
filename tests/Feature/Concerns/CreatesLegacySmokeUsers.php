<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 12:33
 */

namespace Tests\Feature\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 旧前台 smoke 测试共享用户创建器（中文注释标准）。
 *
 * 文件功能：
 * - 为依赖旧前台会话（suser.user_id）的测试创建真实、启用、未注销的用户资料。
 * - 旧前台鉴权中间件 LegacyFrontAuthenticate 要求会话用户必须存在于
 *   user_logins 且关联 user_infos、is_enabled=1、is_cancelled=0，否则 302 跳登录。
 * - 历史测试曾依赖库里残留的 990001 幻影用户，导致套件顺序依赖；本 trait
 *   让每个测试自建数据，行为确定、可在隔离测试库稳定运行。
 *
 * 入参例子：
 * - $this->ensureLegacySmokeUser(990001);
 *
 * 返回值：
 * - 返回新建/已存在用户的 user_logins.id（login_id）。
 *
 * 失败场景：
 * - 数据库连接失败或 user_infos 必填字段缺失时抛出查询异常，禁止静默吞错。
 */
trait CreatesLegacySmokeUsers
{
    /**
     * 确保指定 user_id 存在一条可用的旧前台登录账号与业务资料。
     *
     * @param int $userId 旧前台会话 user_id（suser.user_id）。
     * @param array<string, mixed> $loginOverrides user_logins 额外覆盖字段。
     * @param array<string, mixed> $infoOverrides user_infos 额外覆盖字段。
     * @return int 该用户对应的 user_logins.id。
     */
    protected function ensureLegacySmokeUser(int $userId, array $loginOverrides = [], array $infoOverrides = []): int
    {
        $now = time();

        // 幂等写入登录账号：同一 user_id 重复执行只更新，不产生重复行。
        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $userId],
            array_merge([
                'email' => 'legacy-smoke-' . $userId . '@example.test',
                'password' => Hash::make('smoke123'),
                'account_type' => 1,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 0,
                'jwt_token_id' => '',
                'last_login_ip' => '',
                'last_login_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ], $loginOverrides)
        );

        $loginId = (int) DB::table('user_logins')->where('user_id', $userId)->value('id');

        // 幂等写入业务资料：字段与 FrontDemoDataSeeder 演示用户保持一致，避免页面渲染缺列。
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            array_merge([
                'login_id' => $loginId,
                'user_name' => 'Legacy Smoke User ' . $userId,
                'phone' => '',
                'gender' => 1,
                'avatar' => null,
                'level_id' => 0,
                'group_id' => 0,
                'parent_id' => 0,
                'account_type' => 1,
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
                'is_agent_confirmed' => 1,
                'original_group' => '',
                'mt4_group' => 'demo-agent',
                'mt4_code' => 0,
                'trading_mode' => 0,
                'settle_method' => 1,
                'settle_cycle' => 1,
                'country' => '',
                'city' => '',
                'state' => '',
                'address' => '',
                'is_gift_allowed' => 1,
                'data_source' => 0,
                'remark' => 'legacy smoke test user',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ], $infoOverrides)
        );

        return $loginId;
    }
}
