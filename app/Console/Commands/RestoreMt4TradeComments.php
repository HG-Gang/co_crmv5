<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/02
 * Time: 09:00
 */

/**
 * 修复 mt4_trades 表 cmd=6 记录的 comment 字段缺陷。
 *
 * 文件功能：
 * - 从旧库 hank_zl_data.mt4_trades 批量恢复 comment 字段到新库 co_crmv5.mt4_trades；
 * - 流式读取旧库数据，按 ticket 匹配，每批 1000 条使用事务更新；
 * - 支持 DRY RUN 模式验证匹配情况，执行前自动备份。
 *
 * 适用场景：
 * - 修复迁移后 cmd=6（入金/出金/返佣等）记录 comment 字段丢失导致的业务查询异常。
 *
 * 入参例子：
 * - php artisan mt4:restore-comments --dry-run    # 模拟运行，验证匹配数据
 * - php artisan mt4:restore-comments              # 实际执行修复
 *
 * 返回值：
 * - 0=修复成功完成；
 * - 1=用户取消确认或修复过程中抛出异常。
 *
 * 异常或失败场景：
 * - 旧数据库无法连接、新库写入失败等均抛出异常，命令捕获后输出错误并返回 1。
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreMt4TradeComments extends Command
{
    /** @var string 命令签名：--dry-run 模拟运行不实际执行，--force 强制执行不确认。 */
    protected $signature = 'mt4:restore-comments {--dry-run : 模拟运行不实际执行} {--force : 强制执行不确认}';

    /** @var string 命令说明。 */
    protected $description = '从旧库恢复 mt4_trades 表 cmd=6 记录的 comment 字段';

    /** @var int 每批处理记录数。 */
    private const BATCH_SIZE = 1000;

    /**
     * 执行命令：检查数据、备份、流式恢复 comment、验证结果。
     *
     * @return int 0=成功；1=取消或失败。
     */
    public function handle()
    {
        $this->info('========================================');
        $this->info('   MT4 Trades Comment 修复工具');
        $this->info('========================================');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('模拟运行模式，不会实际执行更新。');
            $this->newLine();
        }

        try {
            // 步骤1：检查旧库与新库数据
            $this->info('步骤1: 检查数据库状态...');
            $stats = $this->checkDatabaseStatus();
            $this->newLine();

            if ($stats['newEmptyCount'] === 0) {
                $this->warn('新库中没有需要修复的记录（cmd=6 且 comment 为空）。');
                return 0;
            }

            // 步骤2：确认执行
            if (!$this->option('dry-run')) {
                if (!$this->option('force')) {
                    if (!$this->confirm("确认从旧库恢复 {$stats['newEmptyCount']} 条记录的 comment？", false)) {
                        $this->error('已取消修复');
                        return 1;
                    }
                }
            }

            // 步骤3：备份（仅真实执行时）
            if (!$this->option('dry-run')) {
                $this->info('步骤2: 备份 mt4_trades 表...');
                $this->backupTable();
                $this->newLine();
            }

            // 步骤4：流式恢复 comment
            $stepLabel = $this->option('dry-run') ? '步骤2' : '步骤3';
            $this->info("{$stepLabel}: 流式读取并恢复 comment...");
            $result = $this->restoreComments($this->option('dry-run'));
            $this->newLine();

            // 步骤5：验证结果
            if (!$this->option('dry-run')) {
                $this->info('步骤4: 验证修复结果...');
                $this->validateResult();
                $this->newLine();
            }

            $this->info('========================================');
            if ($this->option('dry-run')) {
                $this->info('【DRY RUN 完成】');
                $this->line("  匹配记录数: {$result['matched']}");
                $this->line("  可恢复 DBCN 标记: {$result['withDbcn']}");
                $this->line("  旧库也为空: {$result['bothEmpty']}");
            } else {
                $this->info('【修复完成】');
                $this->line("  成功更新: {$result['updated']}");
                $this->line("  跳过（旧库也为空）: {$result['skipped']}");
            }
            $this->info('========================================');

            return 0;

        } catch (\Exception $e) {
            $this->error('修复失败：' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * 检查数据库状态并统计记录数。
     *
     * @return array{newEmptyCount: int, oldTotalCount: int, oldWithDbcn: int} 统计数据。
     * @throws \Exception 数据库连接失败时抛出。
     */
    protected function checkDatabaseStatus()
    {
        try {
            // 新库统计：cmd=6 且 comment 为空的记录数
            $newEmptyCount = DB::connection('mysql')
                ->table('mt4_trades')
                ->where('cmd', 6)
                ->where(function ($query) {
                    $query->whereNull('comment')
                          ->orWhere('comment', '');
                })
                ->count();

            // 旧库统计：CMD=6 总数与含 DBCN 标记的数量（旧库字段全大写）
            $oldTotalCount = DB::connection('old_crm')
                ->table('mt4_trades')
                ->where('CMD', 6)
                ->count();

            $oldWithDbcn = DB::connection('old_crm')
                ->table('mt4_trades')
                ->where('CMD', 6)
                ->where('COMMENT', 'like', '%DBCN%')
                ->count();

            $this->line("  新库 cmd=6 空 comment: {$newEmptyCount}");
            $this->line("  旧库 cmd=6 总数: {$oldTotalCount}");
            $this->line("  旧库含 DBCN 标记: {$oldWithDbcn}");

            return compact('newEmptyCount', 'oldTotalCount', 'oldWithDbcn');

        } catch (\Exception $e) {
            throw new \Exception('数据库连接失败: ' . $e->getMessage());
        }
    }

    /**
     * 备份 mt4_trades 表（创建带时间戳的备份表）。
     *
     * @return void 无返回值。
     */
    protected function backupTable()
    {
        $backupTable = 'mt4_trades_backup_' . date('YmdHis');

        // 排除生成列（is_rebate, rebate_time）的列列表
        $columns = 'id, ticket, login, symbol, cmd, volume, open_price, close_price, ' .
                   'commission, swaps, profit, open_time, close_time, comment, modify_time, ' .
                   'created_at, updated_at';

        DB::statement("CREATE TABLE {$backupTable} LIKE mt4_trades");
        DB::statement("INSERT INTO {$backupTable} ({$columns}) SELECT {$columns} FROM mt4_trades WHERE cmd = 6");

        $count = DB::table($backupTable)->count();
        $this->line("  已备份 cmd=6 记录到 {$backupTable} ({$count} 条)");
    }

    /**
     * 流式读取旧库并恢复 comment 字段。
     *
     * @param bool $dryRun 是否模拟运行（true 只统计不写入）。
     * @return array{matched: int, updated: int, skipped: int, withDbcn: int, bothEmpty: int} 统计数据。
     */
    protected function restoreComments($dryRun = false)
    {
        $matched = 0;
        $updated = 0;
        $skipped = 0;
        $withDbcn = 0;
        $bothEmpty = 0;

        // 流式读取旧库 CMD=6 记录（按 TICKET 排序，避免乱序，旧库字段全大写）
        $oldConnection = DB::connection('old_crm');
        $newConnection = DB::connection('mysql');

        $totalCount = $oldConnection->table('mt4_trades')->where('CMD', 6)->count();
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $batchTickets = [];
        $batchComments = [];

        $oldConnection->table('mt4_trades')
            ->where('CMD', 6)
            ->orderBy('TICKET')
            ->chunk(self::BATCH_SIZE, function ($oldRecords) use (
                &$matched, &$updated, &$skipped, &$withDbcn, &$bothEmpty,
                $newConnection, $dryRun, $bar, &$batchTickets, &$batchComments
            ) {
                $batchTickets = [];
                $batchComments = [];

                foreach ($oldRecords as $oldRecord) {
                    $bar->advance();

                    // 旧库字段全大写：TICKET, COMMENT
                    $oldTicket = $oldRecord->TICKET;
                    $oldComment = $oldRecord->COMMENT ?? '';

                    // 检查新库中是否存在且 comment 为空
                    $newRecord = $newConnection->table('mt4_trades')
                        ->where('ticket', $oldTicket)
                        ->where('cmd', 6)
                        ->where(function ($query) {
                            $query->whereNull('comment')
                                  ->orWhere('comment', '');
                        })
                        ->first();

                    if ($newRecord) {
                        $matched++;

                        // 统计旧库 comment 情况
                        if (empty($oldComment)) {
                            $bothEmpty++;
                            $skipped++;
                            continue;
                        }

                        if (stripos($oldComment, 'DBCN') !== false) {
                            $withDbcn++;
                        }

                        // 收集批量更新数据
                        if (!$dryRun) {
                            $batchTickets[] = $oldTicket;
                            $batchComments[$oldTicket] = $oldComment;
                        }
                    }
                }

                // 批量更新（使用 CASE WHEN）
                if (!$dryRun && !empty($batchTickets)) {
                    $this->batchUpdateComments($newConnection, $batchTickets, $batchComments);
                    $updated += count($batchTickets);
                }

                return true; // 继续下一批
            });

        $bar->finish();
        $this->newLine();

        return compact('matched', 'updated', 'skipped', 'withDbcn', 'bothEmpty');
    }

    /**
     * 批量更新 comment 字段（使用 CASE WHEN 语句）。
     *
     * @param \Illuminate\Database\Connection $connection 数据库连接。
     * @param array $tickets ticket 列表。
     * @param array $comments ticket => comment 映射。
     * @return void 无返回值。
     */
    protected function batchUpdateComments($connection, $tickets, $comments)
    {
        if (empty($tickets)) {
            return;
        }

        $connection->transaction(function () use ($connection, $tickets, $comments) {
            // 构建 CASE WHEN 语句
            $cases = [];
            $bindings = [];

            foreach ($tickets as $ticket) {
                $cases[] = 'WHEN ticket = ? THEN ?';
                $bindings[] = $ticket;
                $bindings[] = $comments[$ticket];
            }

            $caseStatement = 'CASE ' . implode(' ', $cases) . ' END';
            $ticketList = implode(',', array_fill(0, count($tickets), '?'));

            $sql = "UPDATE mt4_trades SET comment = {$caseStatement} WHERE ticket IN ({$ticketList})";
            $bindings = array_merge($bindings, $tickets);

            $connection->update($sql, $bindings);
        });
    }

    /**
     * 验证修复结果。
     *
     * @return void 无返回值，输出验证统计。
     */
    protected function validateResult()
    {
        $emptyCount = DB::table('mt4_trades')
            ->where('cmd', 6)
            ->where(function ($query) {
                $query->whereNull('comment')
                      ->orWhere('comment', '');
            })
            ->count();

        $withDbcnCount = DB::table('mt4_trades')
            ->where('cmd', 6)
            ->where('comment', 'like', '%DBCN%')
            ->count();

        $this->line("  剩余空 comment: {$emptyCount}");
        $this->line("  含 DBCN 标记: {$withDbcnCount}");

        if ($withDbcnCount >= 600000) {
            $this->info('  ✓ 验证通过：DBCN 标记数量符合预期（>600,000）');
        } else {
            $this->warn('  ⚠ 警告：DBCN 标记数量低于预期（应 >600,000）');
        }

        if ($emptyCount === 0) {
            $this->info('  ✓ 验证通过：所有 cmd=6 记录已恢复 comment');
        } else {
            $this->warn("  ⚠ 警告：仍有 {$emptyCount} 条记录 comment 为空");
        }
    }
}
