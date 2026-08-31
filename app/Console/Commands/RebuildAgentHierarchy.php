<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 01:55
 */

namespace App\Console\Commands;

use App\Services\FamilyTreeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 代理层级重建 Artisan 命令。
 *
 * 文件功能：
 * - agent-hierarchy:rebuild：默认只读审计 family_tree 与 agent_descendants 的派生一致性，
 *   输出缺失/属性错误/额外/家谱不一致等审计结果；审计发现错误时以失败退出且不写库。
 * - 显式 --apply 时先创建带时间戳的可恢复备份表，再经 FamilyTreeService 事务化重建并复检，
 *   重建后审计仍不一致则提示用备份恢复。
 */
class RebuildAgentHierarchy extends Command
{
    /**
     * Artisan 命令签名契约：`php artisan agent-hierarchy:rebuild` 默认进入只读审计，
     * 仅在显式追加 --apply 时才创建备份表并事务化重建 family_tree 与 agent_descendants；
     * 签名与 --apply 开关是运维唯一的安全护栏，变更前必须同步修改操作文档与定时任务注册。
     *
     * @var string
     */
    protected $signature = 'agent-hierarchy:rebuild
        {--apply : 创建备份后事务化重建 family_tree 与 agent_descendants}';

    /**
     * Artisan 命令描述契约：只用于 `artisan list` 展示与运维手册；
     * 必须如实表达“默认只读、--apply 才写入”的边界，避免运维误以为执行命令即会改库。
     *
     * @var string
     */
    protected $description = '审计或重建代理层级派生数据；默认只读，--apply 才写入';

    public function handle(FamilyTreeService $service): int
    {
        $before = $service->auditHierarchy();
        $this->renderAudit('重建前审计', $before);

        if (!empty($before['errors'])) {
            foreach ($before['errors'] as $error) {
                $this->error((string) $error);
            }

            return self::FAILURE;
        }

        if (!$this->option('apply')) {
            $this->line('只读审计完成；未传 --apply，数据库未写入。');

            return !empty($before['valid']) ? self::SUCCESS : self::FAILURE;
        }

        [$relationBackup, $familyTreeBackup] = $this->createBackups();
        $this->line('已创建可恢复备份：' . $relationBackup . '、' . $familyTreeBackup);

        $result = $service->rebuildAllHierarchy();
        $after = $service->auditHierarchy();
        $this->renderAudit('重建后审计', $after);

        if (empty($after['valid'])) {
            $this->error('重建后审计仍不一致，请使用备份表恢复并检查数据。');

            return self::FAILURE;
        }

        $this->info(sprintf(
            '重建完成：%d 个活动用户，%d 条代理闭包关系。',
            (int) $result['users'],
            (int) $result['relations']
        ));

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $audit
     */
    private function renderAudit(string $title, array $audit): void
    {
        $this->line($title);
        $this->table(
            ['活动用户', '预期关系', '实际关系', '缺失', '属性错误', '额外', '家谱不一致', '软删除关系'],
            [[
                (int) ($audit['users'] ?? 0),
                (int) ($audit['expected_relations'] ?? 0),
                (int) ($audit['actual_relations'] ?? 0),
                (int) ($audit['missing'] ?? 0),
                (int) ($audit['mismatch'] ?? 0),
                (int) ($audit['extra'] ?? 0),
                (int) ($audit['family_tree_mismatch'] ?? 0),
                (int) ($audit['soft_deleted_relations'] ?? 0),
            ]]
        );
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function createBackups(): array
    {
        $suffix = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $relationTable = 'agent_descendants_backup_' . $suffix;
        $familyTreeTable = 'user_family_tree_backup_' . $suffix;

        DB::statement("CREATE TABLE `{$relationTable}` LIKE `agent_descendants`");
        DB::statement("INSERT INTO `{$relationTable}` SELECT * FROM `agent_descendants`");
        DB::statement(
            "CREATE TABLE `{$familyTreeTable}` AS "
            . 'SELECT user_id, family_tree, updated_at, deleted_at FROM user_infos'
        );

        return [$relationTable, $familyTreeTable];
    }
}
