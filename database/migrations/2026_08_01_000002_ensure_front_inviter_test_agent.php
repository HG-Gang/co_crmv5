<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 13:17
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * 确保全新数据库存在固定前台邀请代理（user_id=10）。
 *
 * 文件功能：
 * - 旧项目与开发库基线中 user_id=10（info@gmtkg.com）是注册用例固定使用的邀请人；
 *   全新库 migrate:fresh --seed 后必须同样存在，否则注册、后台建档等闭环用例
 *   会返回 4005 Inviter not found。
 * - 与 2026_05_28_000001_ensure_front_test_agent_login 采用相同的
 *   updateOrInsert 幂等策略，重复执行不会产生重复行。
 *
 * 入参例子：
 * - 无入参；迁移执行时自动创建或修复。
 *
 * 返回值：
 * - up 执行后 user_logins/user_infos/user_auths 必然存在 user_id=10 的启用代理。
 * - down 保留数据（演示账号属于验收基线，不做破坏性回滚）。
 */
class EnsureFrontInviterTestAgent extends Migration
{
    /**
     * 创建或修复前台邀请测试代理。
     *
     * @return void 无返回值；执行后邀请人可被注册接口正常校验。
     */
    public function up()
    {
        if (!Schema::hasTable('user_logins') || !Schema::hasTable('user_infos')) {
            return;
        }

        $now = time();
        $userId = 10;
        $email = 'info@gmtkg.com';

        $login = DB::table('user_logins')->where('user_id', $userId)->first();
        $loginPayload = [
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('abc123'),
            'account_type' => 1,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'updated_at' => $now,
            'deleted_at' => null,
        ];

        if ($login) {
            DB::table('user_logins')->where('id', $login->id)->update($loginPayload);
            $loginId = (int) $login->id;
        } else {
            $loginId = (int) DB::table('user_logins')->insertGetId(
                array_merge($loginPayload, ['created_at' => $now])
            );
        }

        // migrate:fresh 阶段 seeder 尚未运行，agent_levels 可能为空表；此时必须幂等创建
        // level_code=1 的基础行，否则 user 10 会携带 level_id=0，注册链路的关系码解析
        // 将抛出「代理未配置有效等级」（2026-08-28/29 全量串行 27 条失败根因）。
        // 字段值与 InitialDataSeeder 的一级代理基线一致，seeder 随后 updateOrInsert 补齐。
        $levelId = (int) DB::table('agent_levels')->where('level_code', 1)->value('id');
        if ($levelId <= 0) {
            $levelId = (int) DB::table('agent_levels')->insertGetId([
                'level_code' => 1,
                'name' => '一级代理',
                'max_commission' => 80,
                'min_commission' => 60,
                'user_commission' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
        $groupId = (int) (
            DB::table('group_configs')->where('category', 1)->where('is_default', 1)->value('id')
            ?: DB::table('group_configs')->where('category', 1)->value('id')
            ?: 0
        );

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => 'Demo Inviter Agent',
                'phone' => '',
                'gender' => 1,
                'avatar' => null,
                'level_id' => $levelId,
                'group_id' => $groupId,
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
                'comm_rate' => 65,
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
                'mt4_code' => $userId,
                'trading_mode' => 0,
                'settle_method' => 1,
                'settle_cycle' => 1,
                'country' => '',
                'city' => '',
                'state' => '',
                'address' => '',
                'is_gift_allowed' => 1,
                'data_source' => 0,
                'remark' => 'Permanent front inviter test agent',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        if (Schema::hasTable('user_auths')) {
            DB::table('user_auths')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'bank_no' => '',
                    'bank_name' => '',
                    'bank_card_img' => '',
                    'bank_card_img_tmp' => '',
                    'bank_addr' => '',
                    'bank_addr_tmp' => '',
                    'bank_status' => 0,
                    'bank_remarks' => '',
                    'id_card_no' => '',
                    'id_card_status' => 0,
                    'id_card_front' => '',
                    'id_card_back' => '',
                    'id_card_remarks' => '',
                    'is_bank_synced' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * 回滚策略：演示账号属于验收基线，不回滚。
     *
     * @return void 无返回值。
     */
    public function down()
    {
        // 保留演示账号，避免回滚迁移记录后破坏本地验收入口。
    }
}
