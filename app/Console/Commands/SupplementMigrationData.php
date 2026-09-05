<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * 迁移后数据补充命令
 *
 * 文件功能：
 * - 为已迁移的用户创建必需的关联数据
 * - 补充user_auths、countries、payment_channels等基础数据
 *
 * 使用方法：
 * php artisan migrate:supplement-data
 */
class SupplementMigrationData extends Command
{
    protected $signature = 'migrate:supplement-data';
    protected $description = '为迁移后的数据补充必需的关联表数据';

    public function handle()
    {
        $this->info('====================================');
        $this->info('数据补充开始');
        $this->info('====================================');
        $this->newLine();

        DB::beginTransaction();
        try {
            $this->info('步骤1：补充user_auths表...');
            $this->supplementUserAuths();

            $this->info('步骤2：补充countries表...');
            $this->supplementCountries();

            $this->info('步骤3：补充payment_channels表...');
            $this->supplementPaymentChannels();

            $this->info('步骤4：补充mt4_configs表...');
            $this->supplementMt4Configs();

            $this->info('步骤5：补充agent_levels表...');
            $this->ensureAgentLevels();

            $this->info('步骤6：检查system_configs...');
            $this->ensureSystemConfigs();

            DB::commit();

            $this->newLine();
            $this->info('✅ 数据补充完成！');
            $this->printSummary();

            return 0;

        } catch (Exception $e) {
            DB::rollBack();
            $this->error('❌ 数据补充失败：' . $e->getMessage());
            $this->error('文件：' . $e->getFile() . ':' . $e->getLine());
            return 1;
        }
    }

    private function supplementUserAuths()
    {
        // 获取所有没有user_auths记录的用户
        $usersWithoutAuth = DB::table('user_logins')
            ->leftJoin('user_auths', 'user_logins.user_id', '=', 'user_auths.user_id')
            ->whereNull('user_auths.user_id')
            ->pluck('user_logins.user_id');

        if ($usersWithoutAuth->isEmpty()) {
            $this->line('  ✓ 所有用户已有user_auths记录');
            return;
        }

        $now = time();
        $batch = [];

        foreach ($usersWithoutAuth as $userId) {
            $batch[] = [
                'user_id' => $userId,
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
            ];

            // 每1000条批量插入
            if (count($batch) >= 1000) {
                DB::table('user_auths')->insert($batch);
                $this->line('    已处理：' . count($batch) . ' 条...');
                $batch = [];
            }
        }

        // 插入剩余数据
        if (count($batch) > 0) {
            DB::table('user_auths')->insert($batch);
        }

        $this->line('  ✓ 为 ' . count($usersWithoutAuth) . ' 个用户创建了user_auths记录');
    }

    private function supplementCountries()
    {
        $now = time();

        $countries = [
            ['iso_code' => 'CN', 'call_prefix' => 86, 'zone_id' => 8, 'currency_id' => 1],
            ['iso_code' => 'US', 'call_prefix' => 1, 'zone_id' => -5, 'currency_id' => 2],
            ['iso_code' => 'GB', 'call_prefix' => 44, 'zone_id' => 0, 'currency_id' => 3],
            ['iso_code' => 'HK', 'call_prefix' => 852, 'zone_id' => 8, 'currency_id' => 4],
            ['iso_code' => 'TW', 'call_prefix' => 886, 'zone_id' => 8, 'currency_id' => 5],
            ['iso_code' => 'SG', 'call_prefix' => 65, 'zone_id' => 8, 'currency_id' => 6],
            ['iso_code' => 'JP', 'call_prefix' => 81, 'zone_id' => 9, 'currency_id' => 7],
            ['iso_code' => 'KR', 'call_prefix' => 82, 'zone_id' => 9, 'currency_id' => 8],
            ['iso_code' => 'MY', 'call_prefix' => 60, 'zone_id' => 8, 'currency_id' => 9],
            ['iso_code' => 'TH', 'call_prefix' => 66, 'zone_id' => 7, 'currency_id' => 10],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->updateOrInsert(
                ['iso_code' => $country['iso_code']],
                [
                    'call_prefix' => $country['call_prefix'],
                    'zone_id' => $country['zone_id'],
                    'currency_id' => $country['currency_id'],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->line('  ✓ 补充了 ' . count($countries) . ' 个国家数据');
    }

    private function supplementPaymentChannels()
    {
        $now = time();

        $channels = [
            [
                'name' => '银行卡支付',
                'channel_code' => 'bank_card',
                'exchange_rate' => 1.0000,
                'is_enabled' => 1,
                'sort' => 1,
                'config' => json_encode(['min_amount' => 100, 'max_amount' => 50000]),
            ],
            [
                'name' => '在线支付',
                'channel_code' => 'online_payment',
                'exchange_rate' => 1.0000,
                'is_enabled' => 1,
                'sort' => 2,
                'config' => json_encode(['min_amount' => 50, 'max_amount' => 10000]),
            ],
        ];

        foreach ($channels as $channel) {
            DB::table('payment_channels')->updateOrInsert(
                ['channel_code' => $channel['channel_code']],
                array_merge($channel, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $this->line('  ✓ 补充了 ' . count($channels) . ' 个支付渠道');
    }

    private function supplementMt4Configs()
    {
        $now = time();

        DB::table('mt4_configs')->updateOrInsert(
            ['server_name' => 'Demo Server'],
            [
                'ip' => '127.0.0.1',
                'port' => 443,
                'manager_login' => '1000',
                'manager_password' => 'demo_password',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->line('  ✓ 补充了MT4配置');
    }

    private function ensureAgentLevels()
    {
        $count = DB::table('agent_levels')->count();

        if ($count >= 3) {
            $this->line('  ✓ agent_levels表已有数据');
            return;
        }

        $now = time();
        $levels = [
            ['level' => 1, 'name' => 'Level 1', 'commission_rate' => 30.00],
            ['level' => 2, 'name' => 'Level 2', 'commission_rate' => 25.00],
            ['level' => 3, 'name' => 'Level 3', 'commission_rate' => 20.00],
        ];

        foreach ($levels as $level) {
            DB::table('agent_levels')->updateOrInsert(
                ['level' => $level['level']],
                array_merge($level, [
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $this->line('  ✓ 补充了代理等级数据');
    }

    private function ensureSystemConfigs()
    {
        $now = time();

        $configs = [
            ['key' => 'default_language', 'value' => 'zh-CN', 'group' => 'general', 'description' => '默认语言'],
            ['key' => 'crm_preference', 'value' => '{}', 'group' => 'general', 'description' => 'CRM偏好设置'],
        ];

        foreach ($configs as $config) {
            $exists = DB::table('system_configs')->where('key', $config['key'])->exists();

            if (!$exists) {
                DB::table('system_configs')->insert(array_merge($config, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $this->line("  ✓ 新增配置：{$config['key']}");
            }
        }
    }

    private function printSummary()
    {
        $this->info('====================================');
        $this->info('数据统计');
        $this->info('====================================');

        $stats = [
            'user_auths' => DB::table('user_auths')->count(),
            'countries' => DB::table('countries')->count(),
            'payment_channels' => DB::table('payment_channels')->count(),
            'mt4_configs' => DB::table('mt4_configs')->count(),
            'agent_levels' => DB::table('agent_levels')->count(),
            'system_configs' => DB::table('system_configs')->count(),
        ];

        foreach ($stats as $table => $count) {
            $this->line("{$table}: {$count} 条");
        }
    }
}
