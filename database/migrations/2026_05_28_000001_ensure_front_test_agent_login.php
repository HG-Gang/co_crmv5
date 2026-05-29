<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class EnsureFrontTestAgentLogin extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('user_logins') || !Schema::hasTable('user_infos')) {
            return;
        }

        $now = time();
        $userId = 1001;
        $email = 'agent@test.com';

        $login = DB::table('user_logins')->where('email', $email)->first();
        if (!$login) {
            $login = DB::table('user_logins')->where('user_id', $userId)->orderBy('id')->first();
        }

        $loginPayload = [
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('agent123'),
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
            $loginId = (int) DB::table('user_logins')->insertGetId(array_merge($loginPayload, [
                'created_at' => $now,
            ]));
        }

        $levelId = (int) (
            DB::table('agent_levels')->where('level_code', 1)->value('id')
            ?: DB::table('agent_levels')->value('id')
            ?: 0
        );
        $groupId = (int) (
            DB::table('group_configs')->where('category', 1)->where('is_default', 1)->value('id')
            ?: DB::table('group_configs')->where('category', 1)->value('id')
            ?: 0
        );

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => 'Demo Root Agent',
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
                'remark' => 'Permanent front test agent login',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        if (Schema::hasTable('user_auths')) {
            $authPayload = [
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
            ];

            if (Schema::hasColumn('user_auths', 'bank_no_tmp')) {
                $authPayload['bank_no_tmp'] = '';
            }
            if (Schema::hasColumn('user_auths', 'bank_name_tmp')) {
                $authPayload['bank_name_tmp'] = '';
            }

            DB::table('user_auths')->updateOrInsert(['user_id' => $userId], $authPayload);
        }

        if (Schema::hasTable('id_sequences')) {
            DB::table('id_sequences')->updateOrInsert(
                ['type' => 'agent'],
                [
                    'current_value' => max($userId, (int) DB::table('user_infos')->where('account_type', 1)->max('user_id')),
                    'prefix' => '',
                    'step' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    public function down()
    {
        // Keep user data intact on rollback.
    }
}
