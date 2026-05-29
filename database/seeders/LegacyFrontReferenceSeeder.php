<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class LegacyFrontReferenceSeeder extends Seeder
{
    private $legacy;
    private $counts = [];

    public function run()
    {
        $this->connectLegacy();
        $now = time();

        DB::transaction(function () use ($now) {
            $this->seedAgentLevels($now);
            $this->seedGroupConfigs($now);
            $this->seedSymbolPrices($now);
            $this->seedSpreadConfigs($now);
            $this->seedSystemConfigs($now);
            $this->seedPaymentChannels($now);
            $this->seedNews($now);
        });

        foreach ($this->counts as $name => $count) {
            $this->command->info($name . ': ' . $count);
        }
    }

    private function connectLegacy(): void
    {
        $connection = config('database.connections.mysql');
        $connection['host'] = env('LEGACY_DB_HOST', '127.0.0.1');
        $connection['port'] = env('LEGACY_DB_PORT', '3307');
        $connection['database'] = env('LEGACY_DB_DATABASE', 'hank_zl_data');
        $connection['username'] = env('LEGACY_DB_USERNAME', 'root');
        $connection['password'] = env('LEGACY_DB_PASSWORD', '123456');
        $connection['charset'] = 'utf8mb4';
        $connection['collation'] = 'utf8mb4_unicode_ci';

        Config::set('database.connections.legacy_front_reference', $connection);
        DB::purge('legacy_front_reference');
        $this->legacy = DB::connection('legacy_front_reference');

        $this->legacy->getPdo();
    }

    private function seedAgentLevels(int $now): void
    {
        $rows = $this->legacy->table('agent_level')->orderBy('level_id')->get();

        foreach ($rows as $row) {
            DB::table('agent_levels')->updateOrInsert(
                ['level_code' => (int) $row->level_id],
                [
                    'name' => (string) $row->name,
                    'max_commission' => (int) $row->max_prop,
                    'min_commission' => (int) $row->min_prop,
                    'user_commission' => (int) $row->user_prop,
                    'created_at' => $this->timestamp($row->created_at ?? null, $now),
                    'updated_at' => $this->timestamp($row->updated_at ?? null, $now),
                    'deleted_at' => $this->nullableTimestamp($row->deleted_at ?? null),
                ]
            );
        }

        $this->counts['agent_levels'] = $rows->count();
    }

    private function seedGroupConfigs(int $now): void
    {
        $rows = $this->legacy->table('group_config')->orderBy('id')->get();

        foreach ($rows as $row) {
            DB::table('group_configs')->updateOrInsert(
                ['pair_id' => (int) $row->pair_id],
                [
                    'name' => (string) $row->name,
                    'radix' => (float) $row->radix,
                    'category' => (int) $row->category,
                    'has_commission' => (int) $row->has_comm,
                    'is_enabled' => (int) $row->is_enabled,
                    'is_ecn' => (int) $row->is_ecn,
                    'is_default' => (int) $row->is_default,
                    'created_by' => (int) ($row->created_id ?? 0),
                    'updated_by' => (int) ($row->updated_id ?? 0),
                    'created_at' => $this->timestamp($row->created_at ?? null, $now),
                    'updated_at' => $this->timestamp($row->updated_at ?? null, $now),
                    'deleted_at' => $this->nullableTimestamp($row->deleted_at ?? null),
                ]
            );
        }

        $this->counts['group_configs'] = $rows->count();
    }

    private function seedSymbolPrices(int $now): void
    {
        $rows = $this->legacy->table('symbol_prices')->orderBy('sym_id')->get();

        foreach ($rows as $row) {
            $symbol = trim((string) $row->sym_symbol);
            if ($symbol === '') {
                continue;
            }

            DB::table('symbol_prices')->updateOrInsert(
                ['symbol' => $symbol],
                [
                    'time' => $this->dateTime($row->sym_time ?? null, $now),
                    'bid' => (float) $row->sym_bid,
                    'ask' => (float) $row->sym_ask,
                    'low' => (float) $row->sym_low,
                    'high' => (float) $row->sym_high,
                    'direction' => (int) $row->sym_direction,
                    'digits' => (int) $row->sym_digits,
                    'spread' => (float) $row->sym_spread,
                    'group_id' => (int) $row->sym_grp_id,
                    'status' => (int) $row->voided,
                    'modify_time' => $this->dateTime($row->sym_modify_time ?? null, $now),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $this->counts['symbol_prices'] = $rows->count();
    }

    private function seedSpreadConfigs(int $now): void
    {
        $rows = $this->legacy->table('symbol_spread')->orderBy('id')->get();

        foreach ($rows as $row) {
            DB::table('spread_configs')->updateOrInsert(
                [
                    'spread' => (float) $row->spread,
                    'agent_group_id' => (int) $row->agent_group_id,
                ],
                [
                    'spread_ratio' => (float) $row->spread_ratio,
                    'status' => (int) $row->voided,
                    'created_at' => $this->timestamp($row->rec_crt_date ?? null, $now),
                    'updated_at' => $this->timestamp($row->rec_upd_date ?? null, $now),
                    'deleted_at' => null,
                ]
            );
        }

        $this->counts['spread_configs'] = $rows->count();
    }

    private function seedSystemConfigs(int $now): void
    {
        $system = (array) $this->legacy->table('system_config')->where('voided', '1')->first();
        $params = $this->legacy->table('system_param')->where('voided', '1')->get()->keyBy('para_name');

        foreach ($system as $key => $value) {
            if (is_int($key) || in_array($key, ['sys_id', 'voided', 'rec_crt_user', 'rec_upd_user', 'rec_crt_date', 'rec_upd_date'], true)) {
                continue;
            }
            $this->putConfig($key, $value, 'legacy_system', 'Imported from hank_zl_data.system_config.' . $key, $now);
        }

        if (!empty($system)) {
            $this->putConfig('deposit_exchange_rate_cny', $system['sys_deposit_rate'] ?? '7.0', 'finance', 'Legacy default deposit CNY rate', $now);
            $this->putConfig('withdraw_exchange_rate_cny', $system['sys_draw_rate'] ?? '6.8', 'finance', 'Legacy withdrawal CNY rate', $now);
            $this->putConfig('withdraw_risk_rate_limit', $system['sys_draw_risk'] ?? '100', 'finance', 'Legacy withdrawal risk-rate limit', $now);
            $this->putConfig('withdrawal_fixed_fee_usd', $system['sys_poundage_money'] ?? '0', 'finance', 'Legacy fixed withdrawal fee', $now);
            $this->putConfig('withdrawal_fee_rate', '0', 'finance', 'Legacy non-OTC withdrawal fee rate', $now);
            $this->putConfig('withdraw_percent_limit', $system['sys_draw_perc'] ?? '100', 'finance', 'Legacy withdrawal percent limit', $now);
            $this->putConfig('margin_percent_limit', $system['sys_margin_perc'] ?? '0', 'risk', 'Legacy margin percent limit', $now);
            $this->putConfig('withdraw_apply_end_date', $system['apply_end_date'] ?? '', 'finance', 'Legacy apply_end_date value', $now);
            $this->putConfig('trades_start', $system['trades_start'] ?? '0', 'trade', 'Legacy realtime commission task switch', $now);
            $this->putConfig('trades_whs_exp_zero', $system['trades_whs_exp_zero'] ?? '0', 'trade', 'Legacy zero-expiration task switch', $now);
            $this->putConfig('sys_deposit_rate4', '1.0', 'legacy_system', 'Default legacy crypto channel 4 rate', $now);
            $this->putConfig('sys_deposit_rate5', '1.0', 'legacy_system', 'Default legacy crypto channel 5 rate', $now);
        }

        foreach ($params as $name => $row) {
            $this->putConfig('legacy_system_param_' . $name, json_encode($this->paramPayload($row), JSON_UNESCAPED_UNICODE), 'legacy_param', 'Imported from hank_zl_data.system_param.' . $name, $now);
        }

        $this->mapGlobalRules($params, $now);
        $this->mapAmountLimits($params, $now);

        $this->counts['system_configs'] = count($system) ? count($system) + $params->count() : $params->count();
    }

    private function mapGlobalRules($params, int $now): void
    {
        $depositGlobal = $params->get('GLOBALDEPOSITRULE');
        $withdrawGlobal = $params->get('GLOBALWITHDRAWRULE');
        $depositRule = $params->get('DEPOSITRULE');
        $withdrawRule = $params->get('WITHDRAWRULE');

        $this->putConfig('deposit_enabled', $this->isLegacyAllowed($depositGlobal) ? '1' : '0', 'finance', 'Mapped from GLOBALDEPOSITRULE', $now);
        $this->putConfig('withdrawal_enabled', $this->isLegacyAllowed($withdrawGlobal) ? '1' : '0', 'finance', 'Mapped from GLOBALWITHDRAWRULE', $now);

        [$depositStart, $depositEnd] = $this->ruleRange($depositRule, '00:00:00,23:59:59');
        [$withdrawStart, $withdrawEnd] = $this->ruleRange($withdrawRule, '09:00:00,16:30:00');

        $this->putConfig('deposit_start_time', $depositStart, 'finance', 'Mapped from DEPOSITRULE weekday range', $now);
        $this->putConfig('deposit_end_time', $depositEnd, 'finance', 'Mapped from DEPOSITRULE weekday range', $now);
        $this->putConfig('withdrawal_start_time', $withdrawStart, 'finance', 'Mapped from WITHDRAWRULE weekday range', $now);
        $this->putConfig('withdrawal_end_time', $withdrawEnd, 'finance', 'Mapped from WITHDRAWRULE weekday range', $now);
        $this->putConfig('deposit_weekend_enabled', $this->ruleAllowsWeekend($depositRule) ? '1' : '0', 'finance', 'Mapped from DEPOSITRULE weekend ranges', $now);
    }

    private function mapAmountLimits($params, int $now): void
    {
        $enabledMins = [];
        $enabledMaxes = [];

        for ($id = 1; $id <= 11; $id++) {
            $row = $params->get('PAYMENT_CHANNEL_' . $id);
            if (!$row || (string) ($row->para_data0 ?? '0') !== '1') {
                continue;
            }
            $min = (float) ($row->para_data1 ?? 0);
            if ($min > 0) {
                $enabledMins[] = $min;
            }
            $enabledMaxes[] = $this->legacyChannelMax($id);
        }

        $this->putConfig('deposit_min_amount', $enabledMins ? (string) min($enabledMins) : '10', 'finance', 'Minimum enabled legacy channel amount', $now);
        $this->putConfig('deposit_max_amount', $enabledMaxes ? (string) max($enabledMaxes) : '500000', 'finance', 'Maximum enabled legacy channel amount', $now);
        $this->putConfig('withdraw_min_amount', '300', 'finance', 'Legacy withdrawal page minimum amount', $now);
        $this->putConfig('withdraw_max_amount', '500000', 'finance', 'Legacy withdrawal maximum amount fallback', $now);
    }

    private function seedPaymentChannels(int $now): void
    {
        $params = $this->legacy->table('system_param')
            ->where('voided', '1')
            ->where('para_name', 'like', 'PAYMENT_CHANNEL_%')
            ->orderBy('sys_id')
            ->get();
        $system = (array) $this->legacy->table('system_config')->where('voided', '1')->first();
        $legacyCodes = [];

        foreach ($params as $row) {
            $id = (int) str_replace('PAYMENT_CHANNEL_', '', (string) $row->para_name);
            if ($id < 1 || $id > 11) {
                continue;
            }

            $legacyCodes[] = (string) $id;
            $config = [
                'label_key' => $this->channelLabelKey($id),
                'min_amount' => (float) ($row->para_data1 ?? 0),
                'max_amount' => $this->legacyChannelMax($id),
                'daily_amount' => $row->para_data2 !== null ? (float) $row->para_data2 : null,
                'is_default' => (int) ($row->para_data5 ?? 0),
                'type' => in_array($id, [4, 5], true) ? 'crypto' : 'fiat',
                'type_label_key' => in_array($id, [4, 5], true) ? 'front.channel_type_crypto' : 'front.channel_type_fiat',
                'legacy_para_name' => (string) $row->para_name,
            ];

            DB::table('payment_channels')->updateOrInsert(
                ['channel_code' => (string) $id],
                [
                    'name' => 'Legacy Channel ' . $id,
                    'exchange_rate' => $this->channelRate($id, $system),
                    'is_enabled' => (int) ($row->para_data0 ?? 0),
                    'sort' => (int) ($row->para_data6 ?? (100 - $id)),
                    'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                    'created_at' => $this->timestamp($row->rec_crt_date ?? null, $now),
                    'updated_at' => $this->timestamp($row->rec_upd_date ?? null, $now),
                    'deleted_at' => null,
                ]
            );
        }

        DB::table('payment_channels')
            ->whereIn('channel_code', ['bank_transfer', 'usdt_trc20', 'quick_pay'])
            ->update(['is_enabled' => 0, 'updated_at' => $now]);

        $this->counts['payment_channels'] = count($legacyCodes);
    }

    private function seedNews(int $now): void
    {
        $rows = $this->legacy->table('newslist')->where('voided', '1')->orderBy('news_id')->get();

        foreach ($rows as $row) {
            DB::table('news')->updateOrInsert(
                ['id' => (int) $row->news_id],
                [
                    'title' => (string) $row->news_title,
                    'content' => (string) $row->news_content,
                    'image' => null,
                    'author_id' => 0,
                    'author_name' => (string) ($row->news_user ?: $row->rec_crt_user),
                    'is_published' => 1,
                    'created_at' => $this->timestamp($row->rec_crt_date ?? null, $now),
                    'updated_at' => $this->timestamp($row->rec_upd_date ?? null, $now),
                    'deleted_at' => null,
                ]
            );
        }

        $this->counts['news'] = $rows->count();
    }

    private function putConfig(string $key, $value, string $group, string $description, int $now): void
    {
        DB::table('system_configs')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $value === null ? null : (string) $value,
                'group' => $group,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function paramPayload($row): array
    {
        return [
            'data0' => $row->para_data0,
            'data1' => $row->para_data1,
            'data2' => $row->para_data2,
            'data3' => $row->para_data3,
            'data4' => $row->para_data4,
            'data5' => $row->para_data5,
            'data6' => $row->para_data6,
            'remark' => $row->para_remark,
        ];
    }

    private function isLegacyAllowed($row): bool
    {
        return !$row || (string) ($row->para_data0 ?? '0') === '0';
    }

    private function ruleRange($row, string $fallback): array
    {
        $range = $row->para_data1 ?? $row->para_data0 ?? $fallback;
        $parts = array_map('trim', explode(',', (string) $range));

        return [
            $parts[0] ?? '00:00:00',
            $parts[1] ?? '23:59:59',
        ];
    }

    private function ruleAllowsWeekend($row): bool
    {
        if (!$row) {
            return true;
        }

        foreach (['para_data0', 'para_data6'] as $field) {
            $range = (string) ($row->{$field} ?? '');
            if ($range !== '' && $range !== '00:00:00,00:00:00') {
                return true;
            }
        }

        return false;
    }

    private function channelRate(int $id, array $system): float
    {
        if (in_array($id, [4, 5], true)) {
            return 1.0;
        }

        $key = $id === 1 ? 'sys_deposit_rate' : 'sys_deposit_rate' . $id;

        return (float) ($system[$key] ?? $system['sys_deposit_rate'] ?? 7.0);
    }

    private function channelLabelKey(int $id): string
    {
        $map = [
            1 => 'front.channel_one',
            2 => 'front.channel_two',
            3 => 'front.channel_three',
            4 => 'front.crypto_currency',
            5 => 'front.crypto_currency_two',
            6 => 'front.wechat_pay_one',
            7 => 'front.alipay_one',
            8 => 'front.channel_five',
            9 => 'front.channel_six',
            10 => 'front.alipay_two',
            11 => 'front.wechat_pay_two',
        ];

        return $map[$id] ?? 'front.payment_channel';
    }

    private function legacyChannelMax(int $id): int
    {
        $map = [
            1 => 6800,
            2 => 30000,
            3 => 80000,
            4 => 500000,
            5 => 500000,
            6 => 6800,
            7 => 6800,
            8 => 14000,
            9 => 80000,
            10 => 6800,
            11 => 6800,
        ];

        return $map[$id] ?? 500000;
    }

    private function timestamp($value, int $fallback): int
    {
        if (!$value) {
            return $fallback;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ?: $fallback;
    }

    private function nullableTimestamp($value): ?int
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ?: null;
    }

    private function dateTime($value, int $fallback): string
    {
        $timestamp = $this->timestamp($value, $fallback);

        return date('Y-m-d H:i:s', $timestamp);
    }
}
