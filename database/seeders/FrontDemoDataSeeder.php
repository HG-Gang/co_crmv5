<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class FrontDemoDataSeeder extends Seeder
{
    private $demoUserIds = [1001, 1101, 1102, 600101, 600102, 600103, 600104, 600105, 600106];

    public function run()
    {
        $now = time();
        $legacy = $this->legacyReference();

        DB::transaction(function () use ($now, $legacy) {
            $this->seedMenuIcons();
            $this->mergeFrontMenus();
            $this->seedSystemConfigs($now);
            $levelIds = $this->seedAgentLevels($now, $legacy['agent_levels'] ?? []);
            $groupIds = $this->seedGroupConfigs($now, $legacy['group_configs'] ?? []);
            $this->seedPaymentChannels($now);
            $this->seedSymbols($now, $legacy['symbols']);
            $users = $this->seedUsers($now, $levelIds, $groupIds, $legacy['users'] ?? []);
            $this->resetDemoBusinessData($this->demoUserIds);
            $this->seedHierarchy($now, $users);
            $this->seedFinance($now, $users, $legacy);
            $this->seedTrades($now, $users, $legacy['trades'] ?? []);
            $this->seedCommission($now, $users);
            $this->seedAuxiliaryData($now, $users, $groupIds, $legacy);
        });

        $this->command->info('Front demo data seeded. Login: agent@test.com / agent123');
    }

    private function legacyReference(): array
    {
        $reference = [
            'symbols' => [
                ['symbol' => 'XAUUSD', 'group_id' => 1],
                ['symbol' => 'EURUSD', 'group_id' => 2],
                ['symbol' => 'USOIL', 'group_id' => 3],
                ['symbol' => 'US30', 'group_id' => 4],
                ['symbol' => 'BTCUSD', 'group_id' => 5],
                ['symbol' => 'AAPL', 'group_id' => 6],
            ],
            'users' => [],
            'deposits' => [],
            'withdrawals' => [],
            'trades' => [],
            'agent_levels' => [],
            'group_configs' => [],
            'news' => [],
            'vouchers' => [],
        ];

        try {
            $connection = config('database.connections.mysql');
            $connection['database'] = env('OLD_DB_DATABASE', 'hank_zl_data');
            Config::set('database.connections.legacy_crm', $connection);
            DB::purge('legacy_crm');

            $legacySymbols = DB::connection('legacy_crm')
                ->table('symbol_prices')
                ->select('sym_symbol as symbol', DB::raw('MAX(sym_grp_id) as group_id'))
                ->whereNotNull('sym_symbol')
                ->groupBy('sym_symbol')
                ->limit(12)
                ->get()
                ->map(function ($row) {
                    return [
                        'symbol' => (string) $row->symbol,
                        'group_id' => (int) $row->group_id,
                    ];
                })
                ->filter(function ($row) {
                    return $row['symbol'] !== '' && $row['group_id'] > 0;
                })
                ->values()
                ->all();

            if (!empty($legacySymbols)) {
                $reference['symbols'] = array_slice(array_merge($legacySymbols, $reference['symbols']), 0, 12);
            }

            $legacyUsers = [];
            foreach (['agents', 'user'] as $table) {
                $rows = DB::connection('legacy_crm')
                    ->table($table)
                    ->select([
                        'user_id', 'user_name', 'email', 'phone', 'sex', 'user_money', 'group_id',
                        'parent_id', 'family_tree', 'used_bond_money', 'available_bond_money',
                        'cust_vol', 'cust_eqy', 'cust_lvg', 'effective_cdt', 'risk_rate',
                        'bond_money', 'comm_prop', 'bank_no', 'bank_class', 'bank_info',
                        'bank_status', 'IDcard_no', 'IDcard_status', 'mt4_grp', 'original_grp',
                        'is_enc', 'enable', 'enable_readonly', 'is_out_money', 'is_allow_money',
                        'is_confirm_agents_lvg', 'country', 'city', 'state', 'address',
                        'rec_crt_date', 'rec_upd_date',
                    ])
                    ->whereNotNull('user_name')
                    ->where('user_name', '<>', '')
                    ->orderBy('user_id')
                    ->limit($table === 'agents' ? 8 : 16)
                    ->get()
                    ->map(function ($row) use ($table) {
                        $data = (array) $row;
                        $data['_legacy_table'] = $table;
                        return $data;
                    })
                    ->all();
                $legacyUsers = array_merge($legacyUsers, $rows);
            }
            $reference['users'] = $legacyUsers;

            $reference['deposits'] = DB::connection('legacy_crm')
                ->table('deposit_record_log')
                ->orderByDesc('dep_id')
                ->limit(60)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();

            $reference['withdrawals'] = DB::connection('legacy_crm')
                ->table('draw_record_log')
                ->orderByDesc('record_id')
                ->limit(60)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();

            $reference['trades'] = DB::connection('legacy_crm')
                ->table('user_trades')
                ->whereNotNull('symbol')
                ->where('symbol', '<>', '')
                ->orderByDesc('trades_id')
                ->limit(80)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();

            $reference['agent_levels'] = DB::connection('legacy_crm')
                ->table('agent_level')
                ->orderBy('level_id')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();

            $reference['group_configs'] = DB::connection('legacy_crm')
                ->table('group_config')
                ->where('is_enabled', 1)
                ->orderBy('id')
                ->limit(12)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();

            $reference['news'] = DB::connection('legacy_crm')
                ->table('newslist')
                ->where('voided', '1')
                ->orderByDesc('news_id')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();

            $reference['vouchers'] = DB::connection('legacy_crm')
                ->table('voucher_info')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();
        } catch (\Throwable $e) {
            // The old database is optional.  Generated demo rows still follow the
            // old CRM table shapes: user_info, user_trades, deposit/draw logs.
        }

        return $reference;
    }

    private function seedMenuIcons(): void
    {
        $icons = [
            'front_dashboard' => 'layui-icon layui-icon-console',
            'front_profile' => 'layui-icon layui-icon-username',
            'front_profile_info' => 'layui-icon layui-icon-user',
            'front_profile_edit' => 'layui-icon layui-icon-edit',
            'front_change_pwd' => 'layui-icon layui-icon-password',
            'front_change_email' => 'layui-icon layui-icon-email',
            'front_account' => 'layui-icon layui-icon-template-1',
            'front_account_info' => 'layui-icon layui-icon-about',
            'front_account_balance' => 'layui-icon layui-icon-rmb',
            'front_voucher' => 'layui-icon layui-icon-note',
            'front_cancel' => 'layui-icon layui-icon-close-fill',
            'front_deposit_withdraw' => 'layui-icon layui-icon-dollar',
            'front_deposit' => 'layui-icon layui-icon-add-circle',
            'front_withdraw' => 'layui-icon layui-icon-reduce-circle',
            'front_flow' => 'layui-icon layui-icon-list',
            'front_trading' => 'layui-icon layui-icon-chart',
            'front_position_summary' => 'layui-icon layui-icon-table',
            'front_open_orders' => 'layui-icon layui-icon-play',
            'front_closed_orders' => 'layui-icon layui-icon-log',
            'front_agent' => 'layui-icon layui-icon-group',
            'front_agent_sub' => 'layui-icon layui-icon-friends',
            'front_agent_customers' => 'layui-icon layui-icon-user',
            'front_agent_confirm' => 'layui-icon layui-icon-ok-circle',
            'front_group_change' => 'layui-icon layui-icon-transfer',
            'front_commission' => 'layui-icon layui-icon-diamond',
            'front_commission_rt' => 'layui-icon layui-icon-light',
            'front_commission_hist' => 'layui-icon layui-icon-date',
            'front_commission_transfer' => 'layui-icon layui-icon-release',
            'front_gift' => 'layui-icon layui-icon-gift',
            'front_gift_address' => 'layui-icon layui-icon-location',
            'front_gift_list' => 'layui-icon layui-icon-cart',
            'front_news' => 'layui-icon layui-icon-notice',
        ];

        foreach ($icons as $slug => $icon) {
            DB::table('permissions')->where('slug', $slug)->update([
                'icon' => $icon,
                'updated_at' => now(),
            ]);
        }
    }

    private function mergeFrontMenus(): void
    {
        DB::table('permissions')->where('slug', 'front_profile')->update([
            'name' => '个人中心',
            'route' => '/front/profile',
            'api_route' => 'front_api_profileInfo',
            'type' => 2,
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')
            ->whereIn('slug', ['front_profile_info', 'front_profile_edit', 'front_change_pwd', 'front_change_email'])
            ->update([
                'status' => 0,
                'updated_at' => now(),
            ]);

        DB::table('permissions')->where('slug', 'front_account')->update([
            'name' => '账户管理',
            'type' => 1,
            'route' => '',
            'api_route' => '',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_account_info')->update([
            'name' => '账户综合',
            'route' => '/front/account/info',
            'api_route' => 'front_api_accountInfo',
            'type' => 2,
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_account_balance')->update([
            'status' => 0,
            'updated_at' => now(),
        ]);
    }

    private function seedSystemConfigs(int $now): void
    {
        $configs = [
            ['deposit_enabled', '1', 'finance', 'Demo deposit switch'],
            ['deposit_exchange_rate_cny', '7.12', 'finance', 'Demo CNY deposit rate'],
            ['deposit_min_amount', '50', 'finance', 'Demo min deposit amount'],
            ['deposit_max_amount', '500000', 'finance', 'Demo max deposit amount'],
            ['withdrawal_enabled', '1', 'finance', 'Demo withdrawal switch'],
            ['withdraw_exchange_rate_cny', '7.05', 'finance', 'Demo CNY withdrawal rate'],
            ['withdraw_min_amount', '50', 'finance', 'Demo min withdrawal amount'],
            ['withdraw_max_amount', '50000', 'finance', 'Demo max withdrawal amount'],
            ['withdraw_risk_rate_limit', '50', 'finance', 'Demo withdrawal risk limit'],
            ['download_pc_url', '#', 'front', 'Demo PC download URL'],
            ['download_mobile_url', '#', 'front', 'Demo mobile download URL'],
        ];

        foreach ($configs as $config) {
            DB::table('system_configs')->updateOrInsert(
                ['key' => $config[0]],
                [
                    'value' => $config[1],
                    'group' => $config[2],
                    'description' => $config[3],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedAgentLevels(int $now, array $legacyLevels = []): array
    {
        $levels = [
            ['level_code' => 1, 'name' => 'Level 1 Agent', 'max_commission' => 80, 'min_commission' => 50, 'user_commission' => 20],
            ['level_code' => 2, 'name' => 'Level 2 Agent', 'max_commission' => 70, 'min_commission' => 40, 'user_commission' => 15],
            ['level_code' => 3, 'name' => 'Level 3 Agent', 'max_commission' => 60, 'min_commission' => 30, 'user_commission' => 10],
        ];

        foreach ($legacyLevels as $legacyLevel) {
            $code = (int) ($legacyLevel['level_id'] ?? 0);
            if ($code < 1 || $code > 5) {
                continue;
            }
            $levels[] = [
                'level_code' => $code,
                'name' => (string) ($legacyLevel['name'] ?? ('Legacy Level ' . $code)),
                'max_commission' => (int) ($legacyLevel['max_prop'] ?? 80),
                'min_commission' => (int) ($legacyLevel['min_prop'] ?? 40),
                'user_commission' => (int) ($legacyLevel['user_prop'] ?? 0),
            ];
        }

        foreach ($levels as $level) {
            DB::table('agent_levels')->updateOrInsert(
                ['level_code' => $level['level_code']],
                array_merge($level, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        return DB::table('agent_levels')->pluck('id', 'level_code')->map(function ($id) {
            return (int) $id;
        })->all();
    }

    private function seedGroupConfigs(int $now, array $legacyGroups = []): array
    {
        $groups = [
            ['name' => 'Agent Standard', 'category' => 1, 'has_commission' => 1, 'is_default' => 1, 'radix' => 50],
            ['name' => 'Customer Standard', 'category' => 2, 'has_commission' => 0, 'is_default' => 1, 'radix' => 50],
            ['name' => 'Customer ECN', 'category' => 2, 'has_commission' => 0, 'is_default' => 0, 'radix' => 35],
        ];

        foreach ($legacyGroups as $legacyGroup) {
            $name = trim((string) ($legacyGroup['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $groups[] = [
                'name' => 'Legacy ' . $name,
                'category' => (int) ($legacyGroup['category'] ?? 2),
                'has_commission' => (int) ($legacyGroup['has_comm'] ?? 0),
                'is_default' => 0,
                'radix' => (float) ($legacyGroup['radix'] ?? 50),
            ];
        }

        foreach ($groups as $group) {
            DB::table('group_configs')->updateOrInsert(
                ['name' => $group['name']],
                array_merge($group, [
                    'pair_id' => null,
                    'is_enabled' => 1,
                    'is_ecn' => $group['name'] === 'Customer ECN' ? 1 : 0,
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        return DB::table('group_configs')->pluck('id', 'name')->map(function ($id) {
            return (int) $id;
        })->all();
    }

    private function seedPaymentChannels(int $now): void
    {
        $channels = [
            ['Bank Transfer', 'bank_transfer', 7.12, 100],
            ['USDT TRC20', 'usdt_trc20', 1.0, 90],
            ['Quick Pay', 'quick_pay', 7.10, 80],
        ];

        foreach ($channels as $channel) {
            DB::table('payment_channels')->updateOrInsert(
                ['channel_code' => $channel[1]],
                [
                    'name' => $channel[0],
                    'exchange_rate' => $channel[2],
                    'is_enabled' => 1,
                    'sort' => $channel[3],
                    'config' => json_encode([
                        'min_amount' => 50,
                        'max_amount' => $channel[1] === 'usdt_trc20' ? 500000 : 80000,
                        'type' => $channel[1] === 'usdt_trc20' ? 'crypto' : 'fiat',
                        'is_default' => $channel[1] === 'bank_transfer' ? 1 : 0,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedSymbols(int $now, array $symbols): void
    {
        $basePrices = [
            'XAUUSD' => 2368.45,
            'USOIL' => 78.23,
            'EURUSD' => 1.0872,
            'US30' => 39120.50,
            'BTCUSD' => 64250.00,
            'AAPL' => 187.32,
        ];

        foreach ($symbols as $index => $item) {
            $symbol = strtoupper(substr($item['symbol'], 0, 16));
            $price = $basePrices[$symbol] ?? (100 + $index * 7.35);
            $existing = DB::table('symbol_prices')->where('symbol', $symbol)->first();
            $payload = [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => $price,
                'ask' => $price + 0.25,
                'low' => $price - 4.5,
                'high' => $price + 5.5,
                'direction' => $index % 2,
                'digits' => strpos($symbol, 'JPY') !== false ? 3 : 2,
                'spread' => 25,
                'group_id' => max(1, min((int) $item['group_id'], 6)),
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('symbol_prices')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('symbol_prices')->insert(array_merge(['symbol' => $symbol], $payload));
            }
        }
    }

    private function seedUsers(int $now, array $levelIds, array $groupIds, array $legacyUsers = []): array
    {
        $agentGroup = $groupIds['Agent Standard'];
        $customerGroup = $groupIds['Customer Standard'];
        $ecnGroup = $groupIds['Customer ECN'];

        $users = [
            1001 => ['email' => 'agent@test.com', 'password' => 'agent123', 'name' => 'Demo Root Agent', 'type' => 1, 'parent' => 0, 'level' => $levelIds[1], 'group' => $agentGroup, 'rate' => 65, 'funds' => 88000],
            1101 => ['email' => 'subagent1@test.com', 'password' => 'agent123', 'name' => 'Demo Sub Agent A', 'type' => 1, 'parent' => 1001, 'level' => $levelIds[2], 'group' => $agentGroup, 'rate' => 48, 'funds' => 42000],
            1102 => ['email' => 'subagent2@test.com', 'password' => 'agent123', 'name' => 'Demo Sub Agent B', 'type' => 1, 'parent' => 1001, 'level' => $levelIds[2], 'group' => $agentGroup, 'rate' => 45, 'funds' => 39000],
            600101 => ['email' => 'customer1@test.com', 'password' => 'customer123', 'name' => 'Demo Customer 1', 'type' => 2, 'parent' => 1001, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 13200],
            600102 => ['email' => 'customer2@test.com', 'password' => 'customer123', 'name' => 'Demo Customer 2', 'type' => 2, 'parent' => 1001, 'level' => 0, 'group' => $ecnGroup, 'rate' => 0, 'funds' => 8600],
            600103 => ['email' => 'customer3@test.com', 'password' => 'customer123', 'name' => 'Demo Customer 3', 'type' => 2, 'parent' => 1101, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 21500],
            600104 => ['email' => 'customer4@test.com', 'password' => 'customer123', 'name' => 'Demo Customer 4', 'type' => 2, 'parent' => 1101, 'level' => 0, 'group' => $ecnGroup, 'rate' => 0, 'funds' => 9900],
            600105 => ['email' => 'customer5@test.com', 'password' => 'customer123', 'name' => 'Demo Customer 5', 'type' => 2, 'parent' => 1102, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 17300],
            600106 => ['email' => 'customer6@test.com', 'password' => 'customer123', 'name' => 'Demo Customer 6', 'type' => 2, 'parent' => 1102, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 12100],
        ];

        $legacyUsers = array_values($legacyUsers);
        $index = 0;
        foreach ($users as $userId => $user) {
            if (!empty($legacyUsers[$index])) {
                $users[$userId] = $this->mergeLegacyUser($user, $legacyUsers[$index]);
            }
            $index++;
        }

        foreach ($users as $userId => $user) {
            $loginId = $this->upsertLogin($userId, $user, $now);
            $this->upsertUserInfo($userId, $loginId, $user, $now);
            $users[$userId]['login_id'] = $loginId;
        }

        return $users;
    }

    private function mergeLegacyUser(array $user, array $legacy): array
    {
        $sex = (string) ($legacy['sex'] ?? '');

        $user['name'] = $this->legacyString($legacy['user_name'] ?? '', $user['name']);
        $user['legacy_email'] = $this->legacyString($legacy['email'] ?? '', '');
        $user['phone'] = $this->legacyString($legacy['phone'] ?? '', '');
        $user['gender'] = mb_strpos($sex, '女') !== false || strtolower($sex) === 'female' ? 2 : 1;
        $user['funds'] = $this->legacyFloat($legacy['user_money'] ?? null, $user['funds']);
        $user['used_margin'] = $this->legacyFloat($legacy['used_bond_money'] ?? null, $user['funds'] * 0.18);
        $user['avail_margin'] = $this->legacyFloat($legacy['available_bond_money'] ?? null, $user['funds'] * 0.62);
        $user['equity'] = $this->legacyFloat($legacy['cust_eqy'] ?? null, $user['funds']);
        $user['effective_credit'] = $this->legacyFloat($legacy['effective_cdt'] ?? null, $user['funds'] * 0.25);
        $user['risk_ratio'] = $this->legacyFloat($legacy['risk_rate'] ?? null, 120);
        $user['margin_amount'] = $this->legacyFloat($legacy['bond_money'] ?? null, $user['funds'] * 0.2);
        $user['leverage'] = (int) $this->legacyFloat($legacy['cust_lvg'] ?? null, 200);
        $user['cust_vol'] = $this->legacyString($legacy['cust_vol'] ?? '', '0');
        $user['rate'] = (int) $this->legacyFloat($legacy['comm_prop'] ?? null, $user['rate']);
        $user['mt4_group'] = $this->legacyString($legacy['mt4_grp'] ?? '', $user['type'] === 1 ? 'demo-agent' : 'demo-customer');
        $user['original_group'] = $this->legacyString($legacy['original_grp'] ?? '', '');
        $user['is_ecn'] = (int) ($legacy['is_enc'] ?? 0);
        $user['is_mt4_enabled'] = (int) ($legacy['enable'] ?? 1);
        $user['is_mt4_readonly'] = (int) ($legacy['enable_readonly'] ?? 0);
        $user['is_withdrawal_allowed'] = (int) ($legacy['is_out_money'] ?? 0);
        $user['is_deposit_allowed'] = (int) ($legacy['is_allow_money'] ?? 0);
        $user['is_agent_confirmed'] = (int) ($legacy['is_confirm_agents_lvg'] ?? ($user['type'] === 1 ? 1 : 0));
        $user['country'] = $this->legacyString($legacy['country'] ?? '', 'China');
        $user['city'] = $this->legacyString($legacy['city'] ?? '', 'Shanghai');
        $user['state'] = $this->legacyString($legacy['state'] ?? '', 'Shanghai');
        $user['address'] = $this->legacyString($legacy['address'] ?? '', 'Demo address');
        $user['bank_no'] = $this->legacyString($legacy['bank_no'] ?? '', '');
        $user['bank_name'] = $this->legacyString($legacy['bank_class'] ?? '', '');
        $user['bank_addr'] = $this->legacyString($legacy['bank_info'] ?? '', '');
        $user['bank_status'] = (int) ($legacy['bank_status'] ?? 0);
        $user['id_card_no'] = $this->legacyString($legacy['IDcard_no'] ?? '', '');
        $user['id_card_status'] = (int) ($legacy['IDcard_status'] ?? 0);
        $user['legacy_created_at'] = $legacy['rec_crt_date'] ?? null;

        return $user;
    }

    private function legacyString($value, string $fallback = ''): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? $fallback : $value;
    }

    private function legacyFloat($value, float $fallback = 0.0): float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return is_numeric($value) ? (float) $value : $fallback;
    }

    private function legacyTimestamp($value, int $fallback): int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return $fallback;
        }

        $timestamp = strtotime($value);
        return $timestamp ?: $fallback;
    }

    private function upsertLogin(int $userId, array $user, int $now): int
    {
        DB::table('user_logins')->updateOrInsert(
            ['email' => $user['email']],
            [
                'user_id' => $userId,
                'password' => Hash::make($user['password']),
                'account_type' => $user['type'],
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 0,
                'jwt_token_id' => '',
                'last_login_ip' => '127.0.0.1',
                'last_login_at' => date('Y-m-d H:i:s', $now - 3600),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('user_logins')->where('email', $user['email'])->value('id');
    }

    private function upsertUserInfo(int $userId, int $loginId, array $user, int $now): void
    {
        $funds = (float) $user['funds'];
        $familyTree = $user['parent'] ? $this->familyTree((int) $user['parent'], $userId) : (string) $userId;

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => $user['name'],
                'phone' => ($user['phone'] ?? '') ?: ('138' . substr((string) ($userId + 100000000), -8)),
                'gender' => $user['gender'] ?? ($userId % 2 ? 1 : 2),
                'avatar' => null,
                'level_id' => $user['level'],
                'group_id' => $user['group'],
                'parent_id' => $user['parent'],
                'account_type' => $user['type'],
                'family_tree' => $familyTree,
                'total_funds' => $funds,
                'used_margin' => round($user['used_margin'] ?? ($funds * 0.18), 2),
                'avail_margin' => round($user['avail_margin'] ?? ($funds * 0.62), 2),
                'equity' => round($user['equity'] ?? ($funds * (1 + (($userId % 5) - 2) / 100)), 2),
                'effective_credit' => round($user['effective_credit'] ?? ($funds * 0.25), 2),
                'risk_ratio' => $user['risk_ratio'] ?? (120 + ($userId % 9) * 17),
                'margin_amount' => round($user['margin_amount'] ?? ($funds * 0.2), 2),
                'leverage' => $user['leverage'] ?? 200,
                'cust_vol' => $user['cust_vol'] ?? '0',
                'pay_provider_id' => 0,
                'equity_ratio' => 0,
                'comm_rate' => $user['rate'],
                'is_ecn' => $user['is_ecn'] ?? ($user['group'] === 3 ? 1 : 0),
                'follow_parent_ecn' => 0,
                'auth_status' => 1,
                'is_mt4_synced' => 1,
                'is_mt4_enabled' => $user['is_mt4_enabled'] ?? 1,
                'is_mt4_readonly' => $user['is_mt4_readonly'] ?? 0,
                'is_withdrawal_allowed' => $user['is_withdrawal_allowed'] ?? 0,
                'is_deposit_allowed' => $user['is_deposit_allowed'] ?? 0,
                'is_agent_confirmed' => $user['is_agent_confirmed'] ?? ($user['type'] === 1 ? 1 : 0),
                'original_group' => $user['original_group'] ?? '',
                'mt4_group' => $user['mt4_group'] ?? ($user['type'] === 1 ? 'demo-agent' : 'demo-customer'),
                'mt4_code' => 0,
                'trading_mode' => 0,
                'settle_method' => 1,
                'settle_cycle' => 1,
                'country' => $user['country'] ?? 'China',
                'city' => $user['city'] ?? 'Shanghai',
                'state' => $user['state'] ?? 'Shanghai',
                'address' => $user['address'] ?? 'Demo address',
                'is_gift_allowed' => 1,
                'data_source' => 0,
                'remark' => 'Front demo data generated from old CRM hank_zl_data',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $this->legacyTimestamp($user['legacy_created_at'] ?? null, $now - ($userId % 30) * 86400),
                'updated_at' => $now,
            ]
        );

        DB::table('user_auths')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bank_no' => $user['bank_no'] ?? '',
                'bank_name' => $user['bank_name'] ?? '',
                'bank_card_img' => '',
                'bank_card_img_tmp' => '',
                'bank_addr' => $user['bank_addr'] ?? '',
                'bank_addr_tmp' => $user['bank_addr'] ?? '',
                'bank_status' => $user['bank_status'] ?? 0,
                'bank_remarks' => '',
                'id_card_no' => $user['id_card_no'] ?? '',
                'id_card_status' => $user['id_card_status'] ?? 0,
                'id_card_front' => '',
                'id_card_back' => '',
                'id_card_remarks' => '',
                'is_bank_synced' => 0,
                'created_at' => $this->legacyTimestamp($user['legacy_created_at'] ?? null, $now - ($userId % 30) * 86400),
                'updated_at' => $now,
            ]
        );
    }

    private function familyTree(int $parentId, int $userId): string
    {
        $parentTree = (string) DB::table('user_infos')->where('user_id', $parentId)->value('family_tree');
        if ($parentTree === '') {
            $parentTree = (string) $parentId;
        }

        return $parentTree . ',' . $userId;
    }

    private function resetDemoBusinessData(array $userIds): void
    {
        DB::table('agent_descendants')->whereIn('agent_id', $userIds)->orWhereIn('descendant_id', $userIds)->delete();
        DB::table('deposit_records')->whereIn('user_id', $userIds)->delete();
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('commission_records')->whereIn('agent_id', $userIds)->orWhereIn('parent_id', $userIds)->delete();
        DB::table('voucher_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_addresses')->whereIn('user_id', $userIds)->delete();
        DB::table('gift_shipments')->whereIn('user_id', $userIds)->delete();
        DB::table('trans_apply_logs')->whereIn('user_id', $userIds)->orWhereIn('applicant_id', $userIds)->delete();
        DB::table('news')->where('author_name', 'Front Demo')->delete();
    }

    private function seedHierarchy(int $now, array $users): void
    {
        $relations = [
            [1001, 1101, 1, 1, 1],
            [1001, 1102, 1, 1, 1],
            [1001, 600101, 2, 1, 1],
            [1001, 600102, 2, 1, 1],
            [1001, 600103, 2, 0, 2],
            [1001, 600104, 2, 0, 2],
            [1001, 600105, 2, 0, 2],
            [1001, 600106, 2, 0, 2],
            [1101, 600103, 2, 1, 1],
            [1101, 600104, 2, 1, 1],
            [1102, 600105, 2, 1, 1],
            [1102, 600106, 2, 1, 1],
        ];

        foreach ($relations as $relation) {
            DB::table('agent_descendants')->updateOrInsert(
                ['agent_id' => $relation[0], 'descendant_id' => $relation[1]],
                [
                    'descendant_type' => $relation[2],
                    'is_direct' => $relation[3],
                    'depth' => $relation[4],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedFinance(int $now, array $users, array $legacy): void
    {
        $depositStatuses = ['01', '02', '02', '09'];
        $withdrawStatuses = [0, 1, 2, 3];
        $legacyDeposits = array_values($legacy['deposits'] ?? []);
        $legacyWithdrawals = array_values($legacy['withdrawals'] ?? []);
        $i = 0;

        foreach ($users as $userId => $user) {
            if ($user['type'] !== 2) {
                continue;
            }

            for ($n = 0; $n < 3; $n++) {
                $created = $now - (($i + $n + 1) * 86400);
                $legacyDeposit = $legacyDeposits ? $legacyDeposits[($i * 3 + $n) % count($legacyDeposits)] : [];
                $amount = $this->legacyFloat($legacyDeposit['dep_act_amount'] ?? null, 600 + (($i + 1) * 180) + $n * 120);
                $actualAmount = $this->legacyFloat($legacyDeposit['dep_amount'] ?? null, round($amount * 7.12, 2));
                $rate = $this->legacyFloat($legacyDeposit['dep_amt_rate'] ?? null, 7.12);
                $created = $this->legacyTimestamp($legacyDeposit['rec_crt_date'] ?? null, $created);
                DB::table('deposit_records')->insert([
                    'user_id' => $userId,
                    'user_name' => $user['name'],
                    'mt4_ticket' => (int) ($legacyDeposit['dep_mt4_id'] ?? (700000 + $i * 10 + $n)),
                    'amount' => $amount,
                    'actual_amount' => $actualAmount,
                    'exchange_rate' => $rate,
                    'channel_name' => $this->legacyString($legacyDeposit['dep_channel'] ?? '', $n === 1 ? 'USDT TRC20' : 'Bank Transfer'),
                    'channel_order_no' => $this->legacyString($legacyDeposit['dep_channel_no'] ?? '', 'CH' . ($created + $n)),
                    'local_order_no' => $this->legacyString($legacyDeposit['dep_outTrande'] ?? '', 'DEP' . date('Ymd', $created) . sprintf('%04d', $i * 10 + $n)),
                    'status' => $this->legacyString($legacyDeposit['dep_status'] ?? '', $depositStatuses[($i + $n) % count($depositStatuses)]),
                    'payment_time' => date('Y-m-d H:i:s', $this->legacyTimestamp($legacyDeposit['rec_upd_date'] ?? null, $created + 900)),
                    'remarks' => 'Demo deposit mapped from hank_zl_data.deposit_record_log',
                    'created_by' => $this->legacyString($legacyDeposit['rec_crt_user'] ?? '', $user['name']),
                    'updated_by' => $this->legacyString($legacyDeposit['rec_upd_user'] ?? '', 'Front Demo'),
                    'created_at' => $created,
                    'updated_at' => $this->legacyTimestamp($legacyDeposit['rec_upd_date'] ?? null, $created + 900),
                ]);
            }

            for ($n = 0; $n < 2; $n++) {
                $created = $now - (($i + $n + 4) * 86400);
                $legacyWithdrawal = $legacyWithdrawals ? $legacyWithdrawals[($i * 2 + $n) % count($legacyWithdrawals)] : [];
                $amount = $this->legacyFloat($legacyWithdrawal['apply_amount'] ?? null, 240 + (($i + 1) * 60) + $n * 80);
                $actualAmount = $this->legacyFloat($legacyWithdrawal['act_apply_amount'] ?? null, $amount - 5);
                $fee = $this->legacyFloat($legacyWithdrawal['draw_poundage'] ?? null, 5);
                $rate = $this->legacyFloat($legacyWithdrawal['draw_rate'] ?? null, 7.05);
                $created = $this->legacyTimestamp($legacyWithdrawal['rec_crt_date'] ?? null, $created);
                DB::table('withdraw_records')->insert([
                    'user_id' => $userId,
                    'user_name' => $this->legacyString($legacyWithdrawal['user_name'] ?? '', $user['name']),
                    'mt4_ticket' => (string) ($legacyWithdrawal['mt4_trades_no'] ?? (800000 + $i * 10 + $n)),
                    'apply_amount' => $amount,
                    'actual_amount' => $actualAmount,
                    'fee' => $fee,
                    'exchange_rate' => $rate,
                    'rmb_fee' => $this->legacyFloat($legacyWithdrawal['act_pdg_rmb'] ?? null, round($fee * $rate, 2)),
                    'bank_no' => $this->legacyString($legacyWithdrawal['draw_bank_no'] ?? '', '622200000000' . sprintf('%04d', $i * 10 + $n)),
                    'bank_name' => $this->legacyString($legacyWithdrawal['draw_bank_class'] ?? '', 'Demo Bank'),
                    'bank_addr' => $this->legacyString($legacyWithdrawal['draw_bank_info'] ?? '', 'Shanghai Branch'),
                    'status' => (int) ($legacyWithdrawal['apply_status'] ?? $withdrawStatuses[($i + $n) % count($withdrawStatuses)]),
                    'local_order_no' => $this->legacyString($legacyWithdrawal['orderId_LOC'] ?? '', 'WDR' . date('Ymd', $created) . sprintf('%04d', $i * 10 + $n)),
                    'third_order_no' => $this->legacyString($legacyWithdrawal['orderId_OTC'] ?? '', 'OTC' . ($created + $n)),
                    'reject_reason' => $this->legacyString($legacyWithdrawal['apply_remark'] ?? '', $n === 1 ? 'Demo review note' : ''),
                    'mt4_return_status' => $this->legacyString($legacyWithdrawal['mt4_return_status'] ?? '', 'OK'),
                    'created_by' => $this->legacyString($legacyWithdrawal['rec_crt_user'] ?? '', $user['name']),
                    'updated_by' => $this->legacyString($legacyWithdrawal['rec_upd_user'] ?? '', 'Front Demo'),
                    'created_at' => $created,
                    'updated_at' => $this->legacyTimestamp($legacyWithdrawal['rec_upd_date'] ?? null, $created + 1200),
                ]);
            }

            $i++;
        }
    }

    private function seedTrades(int $now, array $users, array $legacyTrades = []): void
    {
        $symbols = ['XAUUSD', 'USOIL', 'EURUSD', 'US30', 'BTCUSD', 'AAPL'];
        $ticket = 900100;
        $legacyTrades = array_values($legacyTrades);
        $customerIds = array_keys(array_filter($users, function ($user) {
            return $user['type'] === 2;
        }));

        foreach ($customerIds as $customerIndex => $userId) {
            for ($n = 0; $n < 6; $n++) {
                $legacyTrade = $legacyTrades ? $legacyTrades[($customerIndex * 6 + $n) % count($legacyTrades)] : [];
                $symbol = strtoupper($this->legacyString($legacyTrade['symbol'] ?? '', $symbols[($customerIndex + $n) % count($symbols)]));
                $openTs = $now - (($customerIndex * 6 + $n + 1) * 43200);
                $openTs = $this->legacyTimestamp($legacyTrade['open_time'] ?? null, $openTs);
                $legacyCloseTime = $legacyTrade['close_time'] ?? null;
                $isOpen = !$legacyCloseTime || strpos((string) $legacyCloseTime, '0000-00-00') === 0 || strpos((string) $legacyCloseTime, '1970-01-01') === 0;
                if (empty($legacyTrade)) {
                    $isOpen = $n % 3 === 0;
                }
                $volume = (int) $this->legacyFloat($legacyTrade['volume'] ?? null, (1 + (($customerIndex + $n) % 6)) * 100);
                $profit = $this->legacyFloat($legacyTrade['profit'] ?? null, $isOpen ? (35 - $n * 8) : (($n % 2 === 0 ? 1 : -1) * (80 + $customerIndex * 12 + $n * 7)));
                $closeTs = $this->legacyTimestamp($legacyCloseTime, $openTs + 7200);

                DB::table('user_trades')->insert([
                    'user_id' => $userId,
                    'ticket' => $ticket++,
                    'symbol' => $symbol,
                    'digits' => (int) ($legacyTrade['digits'] ?? (in_array($symbol, ['EURUSD'], true) ? 5 : 2)),
                    'cmd' => (int) ($legacyTrade['cmd'] ?? ($n % 2)),
                    'volume' => $volume,
                    'open_time' => date('Y-m-d H:i:s', $openTs),
                    'open_price' => $this->legacyFloat($legacyTrade['open_price'] ?? null, $this->basePrice($symbol) + $n * 0.12),
                    'stop_loss' => $this->legacyFloat($legacyTrade['stop_loss'] ?? null, 0),
                    'take_profit' => $this->legacyFloat($legacyTrade['take_profit'] ?? null, 0),
                    'close_time' => $isOpen ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', $closeTs),
                    'expiration' => null,
                    'reason' => (int) ($legacyTrade['reason'] ?? ($n === 5 ? 1 : 0)),
                    'conv_rate1' => $this->legacyFloat($legacyTrade['conv_rate1'] ?? null, 1),
                    'conv_rate2' => $this->legacyFloat($legacyTrade['conv_rate2'] ?? null, 1),
                    'commission' => $this->legacyFloat($legacyTrade['commission'] ?? null, round(-abs($volume / 100) * 3.5, 2)),
                    'commission_agent' => $this->legacyFloat($legacyTrade['commission_agent'] ?? null, round(abs($volume / 100) * 1.2, 2)),
                    'swaps' => $this->legacyFloat($legacyTrade['swaps'] ?? null, round(($n % 2 === 0 ? -1 : 1) * ($volume / 100) * 0.6, 2)),
                    'close_price' => $isOpen ? 0 : $this->legacyFloat($legacyTrade['close_price'] ?? null, $this->basePrice($symbol) + $n * 0.18),
                    'profit' => $profit,
                    'taxes' => $this->legacyFloat($legacyTrade['taxes'] ?? null, 0),
                    'comment' => $this->legacyString($legacyTrade['comment'] ?? '', 'Front demo trade from hank_zl_data.user_trades'),
                    'internal_id' => (int) ($legacyTrade['internal_id'] ?? 0),
                    'margin_rate' => $this->legacyFloat($legacyTrade['margin_rate'] ?? null, 1),
                    'timestamp_val' => (int) ($legacyTrade['timestamp'] ?? $openTs),
                    'magic' => (int) ($legacyTrade['magic'] ?? 0),
                    'gw_volume' => (int) ($legacyTrade['gw_volume'] ?? 0),
                    'gw_open_price' => (int) ($legacyTrade['gw_open_price'] ?? 0),
                    'gw_close_price' => (int) ($legacyTrade['gw_close_price'] ?? 0),
                    'modify_time' => date('Y-m-d H:i:s', $this->legacyTimestamp($legacyTrade['modify_time'] ?? null, $isOpen ? $openTs : $closeTs)),
                    'settlement_status' => $isOpen ? 0 : 1,
                    'settled_at' => $isOpen ? null : date('Y-m-d H:i:s', $closeTs + 400),
                    'created_at' => $openTs,
                    'updated_at' => $isOpen ? $openTs : $closeTs,
                ]);
            }
        }
    }

    private function basePrice(string $symbol): float
    {
        $map = [
            'XAUUSD' => 2368.45,
            'USOIL' => 78.23,
            'EURUSD' => 1.0872,
            'US30' => 39120.50,
            'BTCUSD' => 64250.00,
            'AAPL' => 187.32,
        ];

        return $map[$symbol] ?? 100.00;
    }

    private function seedCommission(int $now, array $users): void
    {
        $records = [
            [1001, 0, 880.55, 12.4, 'mainData'],
            [1001, 0, 420.20, 5.8, 'mainData'],
            [1101, 1001, 160.40, 2.1, 'transfer'],
            [1102, 1001, 130.10, 1.8, 'transfer'],
        ];

        foreach ($records as $index => $record) {
            $created = $now - ($index + 1) * 86400;
            DB::table('commission_records')->insert([
                'unique_id' => md5(implode('-', $record) . '-' . $created),
                'agent_id' => $record[0],
                'parent_id' => $record[1],
                'agent_profit' => $record[2] * 2,
                'agent_volume' => $record[3],
                'equity_value' => 0,
                'equity_diff' => 0,
                'settle_cycle' => 1,
                'mt4_order_id' => 900100 + $index,
                'date_range' => date('Y-m-d', $created) . ' - ' . date('Y-m-d', $now),
                'settle_status' => 2,
                'fee' => 0,
                'swap' => 0,
                'commission_amount' => $record[2],
                'returned_amount' => $record[2],
                'deposit' => 0,
                'real_amount' => $record[2],
                'data_type' => $record[4],
                'manual_reason' => '',
                'remarks' => 'Demo commission from legacy rebate flow',
                'created_by' => 'Front Demo',
                'updated_by' => 'Front Demo',
                'created_at' => $created,
                'updated_at' => $created + 600,
            ]);
        }
    }

    private function seedAuxiliaryData(int $now, array $users, array $groupIds, array $legacy): void
    {
        $legacyVoucher = ($legacy['vouchers'] ?? [])[0] ?? [];
        $voucherCreated = $this->legacyTimestamp($legacyVoucher['rec_crt_date'] ?? null, $now - 7200);
        DB::table('voucher_infos')->insert([
            'user_id' => 1001,
            'images' => $this->legacyString($legacyVoucher['imgs'] ?? '', 'demo/voucher-1.png'),
            'remarks' => $this->legacyString($legacyVoucher['remarks'] ?? '', 'Demo voucher review row'),
            'review_status' => (int) ($legacyVoucher['review_status'] ?? 1),
            'review_message' => $this->legacyString($legacyVoucher['review_msg'] ?? '', 'Approved demo voucher'),
            'created_by' => $this->legacyString($legacyVoucher['rec_crt_user'] ?? '', 'Demo Root Agent'),
            'updated_by' => $this->legacyString($legacyVoucher['rec_upd_user'] ?? '', 'Front Demo'),
            'created_at' => $voucherCreated,
            'updated_at' => $this->legacyTimestamp($legacyVoucher['rec_upd_date'] ?? null, $voucherCreated + 3600),
        ]);

        $addressId = DB::table('user_addresses')->insertGetId([
            'user_id' => 1001,
            'recipient_name' => 'Demo Root Agent',
            'recipient_phone' => '13800138000',
            'recipient_address' => 'Shanghai Demo Road 100',
            'is_default' => 1,
            'created_at' => $now - 86400,
            'updated_at' => $now,
        ]);

        DB::table('gift_shipments')->insert([
            'user_id' => 1001,
            'address_id' => $addressId,
            'recipient_name' => 'Demo Root Agent',
            'recipient_phone' => '13800138000',
            'recipient_address' => 'Shanghai Demo Road 100',
            'sender_name' => 'Front Demo',
            'tracking_number' => 'DEMO-GIFT-1001',
            'gift_name' => 'VIP Gift Box',
            'gift_quantity' => 1,
            'status' => 2,
            'remark' => 'Demo gift shipment',
            'admin_id' => 0,
            'shipped_at' => date('Y-m-d H:i:s', $now - 3600),
            'created_at' => $now - 7200,
            'updated_at' => $now - 3600,
        ]);

        $applyData = [
            'user_id' => 600103,
            'group_id' => $groupIds['Customer ECN'],
            'group_name' => 'Customer ECN',
            'applicant_id' => 1001,
            'applicant_name' => 'Demo Root Agent',
            'status' => 0,
            'reject_reason' => '',
            'created_by' => 'Demo Root Agent',
            'updated_by' => 'Front Demo',
            'created_at' => $now - 43200,
            'updated_at' => $now - 21600,
        ];
        if (Schema::hasColumn('trans_apply_logs', 'origin_group_id')) {
            $applyData['origin_group_id'] = $groupIds['Customer Standard'];
        }
        if (Schema::hasColumn('trans_apply_logs', 'apply_reason')) {
            $applyData['apply_reason'] = 'Demo group change application';
        }
        DB::table('trans_apply_logs')->insert($applyData);

        $news = $legacy['news'] ?? [];
        if (empty($news)) {
            $news = [
                ['news_title' => 'Demo trading schedule notice', 'news_content' => 'Demo notice content for the front news page.', 'news_user' => 'Front Demo'],
                ['news_title' => 'Demo deposit channel maintenance', 'news_content' => 'Demo maintenance content matching old news rows.', 'news_user' => 'Front Demo'],
                ['news_title' => 'Demo rebate settlement completed', 'news_content' => 'Demo rebate settlement announcement.', 'news_user' => 'Front Demo'],
            ];
        }
        foreach ($news as $index => $item) {
            $created = $this->legacyTimestamp($item['rec_crt_date'] ?? null, $now - ($index + 1) * 3600);
            DB::table('news')->insert([
                'title' => $this->legacyString($item['news_title'] ?? '', 'Demo notice'),
                'content' => $this->legacyString($item['news_content'] ?? '', 'Demo notice content'),
                'image' => null,
                'author_id' => 0,
                'author_name' => 'Front Demo',
                'is_published' => 1,
                'created_at' => $created,
                'updated_at' => $this->legacyTimestamp($item['rec_upd_date'] ?? null, $created + 1800),
            ]);
        }
    }
}
