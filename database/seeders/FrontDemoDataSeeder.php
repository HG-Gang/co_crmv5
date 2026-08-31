<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:12
 */

namespace Database\Seeders;

use App\Services\LegacyGroupConfigSynchronizer;
use Database\Seeders\Concerns\WritesRequiredWithdrawalConfigs;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * 前台本地演示数据 Seeder。
 *
 * 文件功能：
 * - 初始化 Blade 前台可直接使用的账户、交易、资金、层级和辅助演示数据。
 * - 当旧库可访问时读取真实参考数据，但不依赖 Node、Vite 或前后端分离运行时。
 * - 交易组导入保留 legacy_group_id 与当前 pair_id 的独立语义，重跑不得清空已有配对。
 */
class FrontDemoDataSeeder extends Seeder
{
    use WritesRequiredWithdrawalConfigs;

    /**
     * 演示用户 ID 基线（10 号固定邀请代理 + 1001/1101/1102 中间层级 + 6001xx 客户组）。
     * 与旧项目/开发库基线保持一致，注册与后台建档用例都按这些 ID 断言；
     * 重跑 seeder 时 resetDemoBusinessData 按该清单清理业务数据，改值会让测试数据指向不存在的用户。
     *
     * @var array<int, int>
     */
    private $demoUserIds = [10, 1001, 1101, 1102, 600101, 600102, 600103, 600104, 600105, 600106];

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
            $this->seedAgentFlowRecords($now, $users);
            $this->seedTrades($now, $users, $legacy['trades'] ?? []);
            $this->seedCommission($now, $users);
            $this->seedAuxiliaryData($now, $users, $groupIds, $legacy);
        });

        $this->command->info('Front demo data seeded. Login: agent@test.com / abc123');
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
            'api_route' => 'front_api_profile',
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
            'api_route' => 'front_api_account_profile',
            'type' => 2,
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_account_balance')->update([
            'name' => '账户余额',
            'route' => '/front/account/balance',
            'api_route' => 'front_api_account_balance',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-rmb',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_voucher')->update([
            'name' => '提交凭证',
            'route' => '/front/account/voucher',
            'api_route' => 'front_api_account_vouchers',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-note',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_cancel')->update([
            'name' => '注销账户',
            'route' => '/front/account/cancel',
            'api_route' => 'front_api_account_cancellation',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-close-fill',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_commission_transfer')->update([
            'name' => '佣金转账',
            'route' => '/front/commission/transfer',
            'api_route' => 'front_api_commissions_history',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-release',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_deposit')->update([
            'name' => '入金',
            'route' => '/front/deposit',
            'api_route' => 'front_api_deposits_history',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-add-circle',
            'status' => 1,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('slug', 'front_withdraw')->update([
            'name' => '出金',
            'route' => '/front/withdraw',
            'api_route' => 'front_api_withdrawals_history',
            'type' => 2,
            'icon' => 'layui-icon layui-icon-reduce-circle',
            'status' => 1,
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
            ['withdrawal_weekend_enabled', '1', 'finance', 'Demo weekend withdrawal switch'],
            ['withdrawal_start_time', '', 'finance', 'Demo withdrawal start time'],
            ['withdrawal_end_time', '', 'finance', 'Demo withdrawal end time'],
            ['withdraw_exchange_rate_cny', '7.05', 'finance', 'Demo CNY withdrawal rate'],
            ['withdraw_min_amount', '50', 'finance', 'Demo min withdrawal amount'],
            ['withdraw_max_amount', '50000', 'finance', 'Demo max withdrawal amount'],
            ['withdraw_risk_rate_limit', '50', 'finance', 'Demo withdrawal risk limit'],
            ['withdraw_check_open', '0', 'finance', 'Demo open-position withdrawal check'],
            ['withdrawal_fee_rate', '0', 'finance', 'Demo withdrawal fee rate'],
            ['withdrawal_fixed_fee_usd', '0', 'finance', 'Demo fixed withdrawal fee'],
            ['download_pc_url', '#', 'front', 'Demo PC download URL'],
            ['download_mobile_url', '#', 'front', 'Demo mobile download URL'],
        ];

        $requiredWithdrawalKeys = [
            'withdrawal_enabled',
            'withdrawal_weekend_enabled',
            'withdrawal_start_time',
            'withdrawal_end_time',
            'withdraw_min_amount',
            'withdraw_max_amount',
            'withdraw_risk_rate_limit',
            'withdraw_check_open',
            'withdrawal_fee_rate',
            'withdrawal_fixed_fee_usd',
            'withdraw_exchange_rate_cny',
        ];

        foreach ($configs as $config) {
            if (in_array($config[0], $requiredWithdrawalKeys, true)) {
                $this->insertMissingSystemConfig($config, $now);
                continue;
            }

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

    /**
     * 初始化标准组并同步全部旧交易组。
     *
     * 标准组更新负载刻意不包含 pair_id，防止 Seeder 重跑清空后台已经配置的配对关系。
     * 旧交易组交给两阶段同步服务处理，legacy_group_id 保存旧身份，pair_id 保存当前自关联主键。
     *
     * @param int $now 当前 Unix 时间戳。
     * @param array<int, array<string, mixed>> $legacyGroups 旧 group_config 原始行。
     * @return array<string, int> 当前组名称到 group_configs.id 的映射。
     */
    private function seedGroupConfigs(int $now, array $legacyGroups = []): array
    {
        $groups = [
            ['name' => 'Agent Standard', 'category' => 1, 'has_commission' => 1, 'is_default' => 1, 'radix' => 50],
            ['name' => 'Customer Standard', 'category' => 2, 'has_commission' => 0, 'is_default' => 1, 'radix' => 50],
            ['name' => 'Customer ECN', 'category' => 2, 'has_commission' => 0, 'is_default' => 0, 'radix' => 35],
        ];

        foreach ($groups as $group) {
            $payload = array_merge($group, [
                'is_enabled' => 1,
                'is_ecn' => $group['name'] === 'Customer ECN' ? 1 : 0,
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $currentId = (int) DB::table('group_configs')->where('name', $group['name'])->value('id');
            if ($currentId > 0) {
                DB::table('group_configs')->where('id', $currentId)->update($payload);
            } else {
                DB::table('group_configs')->insert(array_merge($payload, [
                    'legacy_group_id' => null,
                    'pair_id' => null,
                    'deleted_at' => null,
                ]));
            }
        }

        $normalizedLegacyGroups = [];
        foreach ($legacyGroups as $legacyGroup) {
            $name = trim((string) ($legacyGroup['name'] ?? ''));
            $legacyGroupId = (int) ($legacyGroup['id'] ?? 0);
            if ($name === '' || $legacyGroupId <= 0) {
                continue;
            }

            $normalizedLegacyGroups[] = [
                'legacy_group_id' => $legacyGroupId,
                'legacy_pair_id' => (int) ($legacyGroup['pair_id'] ?? 0) ?: null,
                'name' => $name,
                'category' => (int) ($legacyGroup['category'] ?? 2),
                'has_commission' => (int) ($legacyGroup['has_comm'] ?? 0),
                'is_default' => (int) ($legacyGroup['is_default'] ?? 0),
                'radix' => (float) ($legacyGroup['radix'] ?? 50),
                'is_enabled' => (int) ($legacyGroup['is_enabled'] ?? 1),
                'is_ecn' => (int) ($legacyGroup['is_ecn'] ?? 0),
                'created_by' => (int) ($legacyGroup['created_id'] ?? 0),
                'updated_by' => (int) ($legacyGroup['updated_id'] ?? 0),
                'created_at' => $this->legacyTimestamp($legacyGroup['created_at'] ?? null, $now),
                'updated_at' => $this->legacyTimestamp($legacyGroup['updated_at'] ?? null, $now),
                'deleted_at' => null,
            ];
        }

        if ($normalizedLegacyGroups) {
            app(LegacyGroupConfigSynchronizer::class)
                ->synchronize($normalizedLegacyGroups, $now, true);
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
                        'remark_items' => $this->demoPaymentChannelRemarkItems($channel[1]),
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /** @return array<int, string> */
    private function demoPaymentChannelRemarkItems(string $code): array
    {
        $map = [
            'bank_transfer' => [
                'Minimum transaction limit per trade: $50.',
                'Maximum transaction limit per trade: $80,000.',
                'Upload the bank transfer voucher after payment.',
            ],
            'usdt_trc20' => [
                '1 USD = 1 USDT.',
                'Use the TRC20 network for USDT transfers.',
                'Maximum transaction limit per trade: 500,000 USDT.',
            ],
            'quick_pay' => [
                'Minimum transaction limit per trade: $50.',
                'Maximum transaction limit per trade: $80,000.',
                'Confirm the provider result before submitting another payment.',
            ],
        ];

        return $map[$code] ?? [];
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
            10 => ['email' => 'info@gmtkg.com', 'password' => 'abc123', 'name' => 'Demo Inviter Agent', 'type' => 1, 'parent' => 0, 'level' => $levelIds[1], 'group' => $agentGroup, 'rate' => 65, 'funds' => 0],
            1001 => ['email' => 'agent@test.com', 'password' => 'abc123', 'name' => 'Demo Root Agent', 'type' => 1, 'parent' => 0, 'level' => $levelIds[1], 'group' => $agentGroup, 'rate' => 65, 'funds' => 88000],
            1101 => ['email' => 'subagent1@test.com', 'password' => 'abc123', 'name' => 'Demo Sub Agent A', 'type' => 1, 'parent' => 1001, 'level' => $levelIds[2], 'group' => $agentGroup, 'rate' => 48, 'funds' => 42000],
            1102 => ['email' => 'subagent2@test.com', 'password' => 'abc123', 'name' => 'Demo Sub Agent B', 'type' => 1, 'parent' => 1001, 'level' => $levelIds[2], 'group' => $agentGroup, 'rate' => 45, 'funds' => 39000],
            600101 => ['email' => 'customer1@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 1', 'type' => 2, 'parent' => 1001, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 13200],
            600102 => ['email' => 'customer2@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 2', 'type' => 2, 'parent' => 1001, 'level' => 0, 'group' => $ecnGroup, 'rate' => 0, 'funds' => 8600],
            600103 => ['email' => 'customer3@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 3', 'type' => 2, 'parent' => 1101, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 21500],
            600104 => ['email' => 'customer4@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 4', 'type' => 2, 'parent' => 1101, 'level' => 0, 'group' => $ecnGroup, 'rate' => 0, 'funds' => 9900],
            600105 => ['email' => 'customer5@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 5', 'type' => 2, 'parent' => 1102, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 17300],
            600106 => ['email' => 'customer6@test.com', 'password' => 'abc123', 'name' => 'Demo Customer 6', 'type' => 2, 'parent' => 1102, 'level' => 0, 'group' => $customerGroup, 'rate' => 0, 'funds' => 12100],
        ];

        $legacyUsers = array_values($legacyUsers);
        $index = 0;
        foreach ($users as $userId => $user) {
            if (!empty($legacyUsers[$index])) {
                $users[$userId] = $this->mergeLegacyUser($user, $legacyUsers[$index], $groupIds);
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

    /**
     * 把一条旧用户参考资料合并到固定 Demo 账号。
     *
     * @param array<string, mixed> $user Demo 账号默认资料，group 是当前交易组主键。
     * @param array<string, mixed> $legacy 旧库用户参考资料，mt4_grp 是远端真实组名。
     * @param array<string, int> $groupIds 当前交易组名称到主键的映射。
     * @return array<string, mixed> 合并后的 Demo 用户资料；无法解析组名时保留默认组。
     */
    private function mergeLegacyUser(array $user, array $legacy, array $groupIds = []): array
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
        if (isset($groupIds[$user['mt4_group']])) {
            // MT4 组名是远端真实状态，匹配成功时必须同步当前主键，避免展示字段与业务判断分裂。
            $user['group'] = (int) $groupIds[$user['mt4_group']];
        }
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
        $payload = [
            'email' => $user['email'],
            'password' => Hash::make($user['password']),
            'account_type' => $user['type'],
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '127.0.0.1',
            'last_login_at' => date('Y-m-d H:i:s', $now - 3600),
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $loginId = (int) DB::table('user_logins')
            ->where('user_id', $userId)
            ->where('email', $user['email'])
            ->orderBy('id')
            ->value('id');
        if ($loginId <= 0) {
            $loginId = (int) DB::table('user_logins')->where('user_id', $userId)->orderBy('id')->value('id');
        }

        if ($loginId > 0) {
            DB::table('user_logins')->where('id', $loginId)->update($payload);
        } else {
            $loginId = (int) DB::table('user_logins')->insertGetId(array_merge($payload, [
                'user_id' => $userId,
                'created_at' => $now,
            ]));
        }

        DB::table('user_logins')
            ->where('user_id', $userId)
            ->where('id', '<>', $loginId)
            ->delete();

        return $loginId;
    }

    /**
     * 写入可直接进入前台的演示用户资料与实名认证资料。
     *
     * 参数逻辑说明：
     * - userId 表示业务用户编号，同时作为已完成 MT4 开户的演示账户编号。
     * - loginId 表示 user_logins.id，用于建立登录记录与业务资料的一对一关联。
     * - user 表示演示用户、交易组、资金和旧库参考字段的合并结果。
     * - now 表示本次 Seeder 使用的统一 Unix 时间戳。
     *
     * @param int $userId 业务用户编号，必须为正整数。
     * @param int $loginId 当前用户登录记录主键，必须对应同一个 userId。
     * @param array<string, mixed> $user 演示用户资料及旧库合并数据。
     * @param int $now 写入 created_at、updated_at 使用的 Unix 时间戳。
     * @return void 写入成功不返回值；数据库约束失败时由事务抛出异常并整体回滚。
     */
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
                // 登录闭环要求已同步账户的 MT4 编号与业务 user_id 一致，禁止写成未开户占位值 0。
                'mt4_code' => $userId,
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
                DB::table('deposit_records')->insertOrIgnore([
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
                DB::table('withdraw_records')->insertOrIgnore([
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

    /**
     * 为代理账号补齐入金与出金演示流水。
     *
     * 背景：
     * - seedFinance 只为 type=2 的客户写入 deposit_records 与 withdraw_records，
     *   因此代理登录后「账户流水」的本人相关页签（全部/入金/出金/出金申请）以及
     *   「直属代理入金/直属代理出金」页签都会是空表，演示时无法看到任何数据。
     *
     * 本方法覆盖的页签：
     * - all：当前登录代理自己的入金 + 出金 + 返佣三类流水聚合。
     * - deposit / withdraw / withdraw_apply：当前登录代理自己的 user_id 记录。
     * - direct_agents_deposit / direct_agents_withdraw：直属下级代理（1101、1102）的记录。
     *
     * 数据口径：
     * - 金额、状态、订单号全部为可辨识的演示值，created_at 使用 10 位整数时间戳，与既有 Seeder 一致。
     * - 出金同时覆盖 bank_name 有值（银行转账）与 bank_name 为空（数字货币）两种来源，
     *   保证出金来源筛选下拉的两个选项都能筛出结果。
     * - 使用 insertOrIgnore 并配合 resetDemoBusinessData，重复执行不会产生重复行。
     *
     * @param int $now 当前 10 位时间戳。
     * @param array<int, array<string, mixed>> $users Demo 用户集合，键为业务用户编号。
     * @return void 演示流水直接写入 deposit_records 与 withdraw_records。
     */
    private function seedAgentFlowRecords(int $now, array $users): void
    {
        // 入金状态覆盖旧口径：01=待处理，02=成功，09=失败，保证列表状态列有差异。
        $depositStatuses = ['02', '01', '09'];
        // 出金状态覆盖旧口径：0=待审核，1=审核通过，2=已打款，3=已驳回。
        $withdrawStatuses = [2, 1, 0, 3];
        $agentIndex = 0;

        foreach ($users as $userId => $user) {
            if ((int) ($user['type'] ?? 0) !== 1) {
                continue;
            }

            for ($n = 0; $n < 3; $n++) {
                $created = $now - (($agentIndex * 3 + $n + 1) * 86400) - 3600;
                $amount = 1500 + ($agentIndex * 500) + ($n * 260);
                DB::table('deposit_records')->insertOrIgnore([
                    'user_id' => $userId,
                    'user_name' => $user['name'],
                    'mt4_ticket' => 710000 + $agentIndex * 100 + $n,
                    'amount' => $amount,
                    'actual_amount' => round($amount * 7.12, 2),
                    'exchange_rate' => 7.12,
                    'channel_name' => $n === 1 ? 'USDT TRC20' : 'Bank Transfer',
                    'channel_order_no' => 'AGCH' . $userId . sprintf('%03d', $n),
                    'local_order_no' => 'AGDEP' . date('Ymd', $created) . $userId . sprintf('%02d', $n),
                    'status' => $depositStatuses[$n % count($depositStatuses)],
                    'payment_time' => date('Y-m-d H:i:s', $created + 900),
                    'remarks' => 'Demo agent deposit flow row',
                    'created_by' => $user['name'],
                    'updated_by' => 'Front Demo',
                    'created_at' => $created,
                    'updated_at' => $created + 900,
                ]);
            }

            for ($n = 0; $n < 4; $n++) {
                $created = $now - (($agentIndex * 4 + $n + 1) * 86400) - 7200;
                $amount = 900 + ($agentIndex * 320) + ($n * 150);
                $fee = 8;
                // n=1、n=3 留空 bank_name，表示数字货币出金；其余为银行转账出金。
                $isCrypto = ($n % 2) === 1;
                DB::table('withdraw_records')->insertOrIgnore([
                    'user_id' => $userId,
                    'user_name' => $user['name'],
                    'mt4_ticket' => (string) (810000 + $agentIndex * 100 + $n),
                    'apply_amount' => $amount,
                    'actual_amount' => $amount - $fee,
                    'fee' => $fee,
                    'exchange_rate' => 7.05,
                    'rmb_fee' => round($fee * 7.05, 2),
                    'bank_no' => $isCrypto ? '' : '622200000000' . $userId . sprintf('%02d', $n),
                    'bank_name' => $isCrypto ? '' : 'Demo Bank',
                    'bank_addr' => $isCrypto ? '' : 'Shanghai Branch',
                    'status' => $withdrawStatuses[$n % count($withdrawStatuses)],
                    'local_order_no' => 'AGWDR' . date('Ymd', $created) . $userId . sprintf('%02d', $n),
                    'third_order_no' => 'AGOTC' . $userId . sprintf('%03d', $n),
                    'reject_reason' => $withdrawStatuses[$n % count($withdrawStatuses)] === 3 ? 'Demo review note' : '',
                    'mt4_return_status' => 'OK',
                    'created_by' => $user['name'],
                    'updated_by' => 'Front Demo',
                    'created_at' => $created,
                    'updated_at' => $created + 1200,
                ]);
            }

            $agentIndex++;
        }
    }

    private function seedTrades(int $now, array $users, array $legacyTrades = []): void
    {
        $symbols = ['XAUUSD', 'USOIL', 'EURUSD', 'US30', 'BTCUSD', 'AAPL'];
        $ticket = 900100;
        $legacyTrades = array_values($legacyTrades);
        $hasClosedLegacyTrades = $this->hasClosedLegacyTrades($legacyTrades);
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
                $hasLegacyCloseTime = array_key_exists('close_time', $legacyTrade) && trim((string) $legacyCloseTime) !== '';
                $isOpen = $hasClosedLegacyTrades && $hasLegacyCloseTime ? $this->isLegacyOpenTrade($legacyCloseTime) : ($n % 3 === 0);
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

    /** @param array{0: string, 1: string, 2: string, 3: string} $config */
    private function insertMissingSystemConfig(array $config, int $now): void
    {
        $this->writeRequiredWithdrawalConfig(
            $config[0],
            $config[1],
            $config[2],
            $config[3],
            $now,
            true
        );
    }

    private function hasClosedLegacyTrades(array $legacyTrades): bool
    {
        foreach ($legacyTrades as $trade) {
            if (!array_key_exists('close_time', $trade)) {
                continue;
            }
            if (!$this->isLegacyOpenTrade($trade['close_time'])) {
                return true;
            }
        }

        return false;
    }

    private function isLegacyOpenTrade($legacyCloseTime): bool
    {
        $value = trim((string) $legacyCloseTime);

        return $value === ''
            || strpos($value, '0000-00-00') === 0
            || strpos($value, '1970-01-01') === 0;
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
