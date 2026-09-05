<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * 补充配置数据迁移命令
 *
 * 补充以下缺失的配置数据：
 * - group_config → group_configs (24条缺失)
 * - mt4_config → mt4_configs (6条缺失)
 * - symbol_spread → spread_configs (35条全缺失)
 *
 * 使用方法：
 * php artisan migrate:supplement-configs [--force] [--verify-only]
 */
class SupplementConfigData extends Command
{
    protected $signature = 'migrate:supplement-configs
                            {--force : 强制执行，覆盖已存在数据}
                            {--verify-only : 仅验证，不执行迁移}';

    protected $description = '补充缺失的配置数据（组配置、MT4配置、点差配置）';

    private $oldDb = 'old_crm';
    private $newDb = 'mysql';
    private $stats = [
        'group_configs_added' => 0,
        'mt4_configs_added' => 0,
        'spread_configs_added' => 0,
    ];

    public function handle()
    {
        $this->info('====================================');
        $this->info('配置数据补充迁移');
        $this->info('====================================');
        $this->newLine();

        try {
            // 验证旧库连接
            $this->checkOldDatabaseConnection();

            if ($this->option('verify-only')) {
                $this->verifyOnly();
                return 0;
            }

            // 执行迁移
            $this->migrateGroupConfigs();
            $this->newLine();

            $this->migrateMt4Configs();
            $this->newLine();

            $this->migrateSpreadConfigs();
            $this->newLine();

            $this->printSummary();

            return 0;

        } catch (Exception $e) {
            $this->error('❌ 迁移失败：' . $e->getMessage());
            $this->error('文件：' . $e->getFile() . ':' . $e->getLine());
            $this->error('堆栈：' . $e->getTraceAsString());
            return 1;
        }
    }

    private function checkOldDatabaseConnection()
    {
        try {
            DB::connection($this->oldDb)->getPdo();
            $this->info('✅ 旧库连接正常');
        } catch (Exception $e) {
            throw new Exception('旧库连接失败，请检查 .env 中的 OLD_DB_* 配置');
        }
    }

    private function verifyOnly()
    {
        $this->info('【验证模式】检查数据缺口...');
        $this->newLine();

        // 检查 group_configs
        $oldGroupCount = DB::connection($this->oldDb)->table('group_config')->count();
        $newGroupCount = DB::table('group_configs')->count();
        $groupGap = $oldGroupCount - $newGroupCount;

        $this->line("组配置 (group_configs):");
        $this->line("  旧库：{$oldGroupCount} 条");
        $this->line("  新库：{$newGroupCount} 条");
        $this->line("  缺口：" . ($groupGap > 0 ? "❌ {$groupGap} 条" : "✅ 无"));
        $this->newLine();

        // 检查 mt4_configs
        $oldMt4Count = DB::connection($this->oldDb)->table('mt4_config')->count();
        $newMt4Count = DB::table('mt4_configs')->count();
        $mt4Gap = $oldMt4Count - $newMt4Count;

        $this->line("MT4配置 (mt4_configs):");
        $this->line("  旧库：{$oldMt4Count} 条");
        $this->line("  新库：{$newMt4Count} 条");
        $this->line("  缺口：" . ($mt4Gap > 0 ? "❌ {$mt4Gap} 条" : "✅ 无"));
        $this->newLine();

        // 检查 spread_configs
        $oldSpreadCount = DB::connection($this->oldDb)->table('symbol_spread')->count();
        $newSpreadCount = DB::table('spread_configs')->count();
        $spreadGap = $oldSpreadCount - $newSpreadCount;

        $this->line("点差配置 (spread_configs):");
        $this->line("  旧库：{$oldSpreadCount} 条");
        $this->line("  新库：{$newSpreadCount} 条");
        $this->line("  缺口：" . ($spreadGap > 0 ? "❌ {$spreadGap} 条" : "✅ 无"));
        $this->newLine();

        $totalGap = $groupGap + $mt4Gap + $spreadGap;
        if ($totalGap > 0) {
            $this->warn("总计缺失：{$totalGap} 条配置数据");
            $this->info("执行 php artisan migrate:supplement-configs 进行补充");
        } else {
            $this->info("✅ 所有配置数据完整，无需补充");
        }
    }

    private function migrateGroupConfigs()
    {
        $this->info('迁移组配置 (group_configs)...');

        $oldConfigs = DB::connection($this->oldDb)
            ->table('group_config')
            ->whereNull('deleted_at')
            ->get();

        if ($oldConfigs->isEmpty()) {
            $this->warn('⚠️  旧库 group_config 表为空');
            return;
        }

        $bar = $this->output->createProgressBar($oldConfigs->count());
        $bar->start();

        foreach ($oldConfigs as $old) {
            try {
                $exists = DB::table('group_configs')
                    ->where('name', $old->name)
                    ->exists();

                if ($exists && !$this->option('force')) {
                    $bar->advance();
                    continue;
                }

                $data = [
                    'pair_id' => $old->pair_id,
                    'name' => $old->name,
                    'radix' => $old->radix ?? 50.00,
                    'category' => $old->category ?? 2,
                    'has_commission' => $old->has_comm ?? 0,
                    'is_enabled' => $old->is_enabled ?? 1,
                    'is_ecn' => $old->is_ecn ?? 0,
                    'is_default' => $old->is_default ?? 0,
                    'created_by' => $old->created_id ?? 0,
                    'updated_by' => $old->updated_id ?? 0,
                    'created_at' => isset($old->created_at) ? strtotime($old->created_at) : time(),
                    'updated_at' => isset($old->updated_at) ? strtotime($old->updated_at) : time(),
                ];

                if ($this->option('force') && $exists) {
                    DB::table('group_configs')
                        ->where('name', $old->name)
                        ->update($data);
                } else {
                    DB::table('group_configs')->insert($data);
                }

                $this->stats['group_configs_added']++;
                $bar->advance();

            } catch (Exception $e) {
                $bar->advance();
                $this->newLine();
                $this->error("  ❌ 迁移组配置 {$old->name} 失败：" . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ 组配置迁移完成，新增 {$this->stats['group_configs_added']} 条");
    }

    private function migrateMt4Configs()
    {
        $this->info('迁移 MT4 配置 (mt4_configs)...');

        // mt4_config 表是键值对结构，需要转换为记录结构
        $this->info('  ⚠️  mt4_config 表为键值对结构，跳过自动迁移');
        $this->info('  提示：如需迁移，请在后台管理界面手动配置 MT4 服务器');

        // 示例：如果要迁移，需要先解析出服务器配置
        // CONFIG=1 可能是 server_name, CONFIG=2 可能是 ip, 等等
        // 这需要了解旧系统的 CONFIG 编号规则

        $this->newLine();
        return;
    }

    private function migrateSpreadConfigs()
    {
        $this->info('迁移点差配置 (spread_configs)...');

        $oldSpreads = DB::connection($this->oldDb)
            ->table('symbol_spread')
            ->where('voided', 0)
            ->get();

        if ($oldSpreads->isEmpty()) {
            $this->warn('⚠️  旧库 symbol_spread 表为空');
            return;
        }

        $bar = $this->output->createProgressBar($oldSpreads->count());
        $bar->start();

        foreach ($oldSpreads as $old) {
            try {
                // symbol_spread 表只有 spread, agent_group_id, spread_ratio
                // 检查是否已存在相同配置
                $exists = DB::table('spread_configs')
                    ->where('agent_group_id', $old->agent_group_id)
                    ->where('spread', $old->spread)
                    ->exists();

                if ($exists && !$this->option('force')) {
                    $bar->advance();
                    continue;
                }

                $data = [
                    'spread' => $old->spread ?? 0,
                    'agent_group_id' => $old->agent_group_id,
                    'spread_ratio' => $old->spread_ratio ?? 1.0,
                    'status' => 1,
                    'created_at' => isset($old->rec_crt_date) ? strtotime($old->rec_crt_date) : time(),
                    'updated_at' => isset($old->rec_upd_date) ? strtotime($old->rec_upd_date) : time(),
                ];

                if ($this->option('force') && $exists) {
                    DB::table('spread_configs')
                        ->where('agent_group_id', $old->agent_group_id)
                        ->where('spread', $old->spread)
                        ->update($data);
                } else {
                    DB::table('spread_configs')->insert($data);
                }

                $this->stats['spread_configs_added']++;
                $bar->advance();

            } catch (Exception $e) {
                $bar->advance();
                $this->newLine();
                $this->error("  ❌ 迁移点差配置失败：" . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ 点差配置迁移完成，新增 {$this->stats['spread_configs_added']} 条");
    }

    private function printSummary()
    {
        $this->info('====================================');
        $this->info('迁移统计');
        $this->info('====================================');
        $this->line("组配置新增：{$this->stats['group_configs_added']} 条");
        $this->line("MT4配置新增：{$this->stats['mt4_configs_added']} 条");
        $this->line("点差配置新增：{$this->stats['spread_configs_added']} 条");
        $total = array_sum($this->stats);
        $this->newLine();
        $this->info("✅ 总计新增：{$total} 条配置数据");
    }
}
