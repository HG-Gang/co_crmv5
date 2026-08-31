<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:13
 */

/**
 * 新库全量重置与旧库数据迁移命令。
 *
 * 文件功能：
 * - 执行 database/sql/full_reset_and_migrate.sql 单文件：清空 co_crmv5 全部业务表并重建
 *   最新表结构（AUTO_INCREMENT 归 1）→ 从旧库 hank_zl_data 全量迁移数据 → 尾部修正
 *   （migrations 恢复、MT4 同步标记归零、前后端密码统一 abc123）。
 * - 与 SQL 文件同步配套：用户可二选一 —— 手动用 mysql 客户端执行 SQL 文件，
 *   或执行本命令（内部同样执行该 SQL 文件）。
 * - 执行前必须交互确认（或 --yes 跳过确认）；--dry-run 只做预检不写库。
 *
 * 适用场景：
 * - 新项目环境切换/验收时，把旧项目 DB 全量数据装载到新项目 DB 进行真实数据验证。
 *
 * 入参例子：
 * - php artisan db:full-reset-and-migrate              # 交互确认后执行
 * - php artisan db:full-reset-and-migrate --yes        # 跳过交互确认直接执行
 * - php artisan db:full-reset-and-migrate --dry-run    # 只检查 SQL 文件与旧库连通性
 * - php artisan db:full-reset-and-migrate --sql=/path/to.sql  # 指定自定义 SQL 文件
 *
 * 返回值：
 * - 0=迁移成功完成并输出统计验证；
 * - 1=用户取消、SQL 文件缺失或执行失败。
 *
 * 异常或失败场景：
 * - 旧库不可达、SQL 文件不存在、执行中途报错时命令输出错误并返回 1；
 * - 注意：执行中途失败不会自动回滚（DDL 隐式提交），需人工排查后重跑
 *   （重跑是幂等的：再次执行会先 DROP 全部表再重建）。
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

class FullResetAndMigrateDatabase extends Command
{
    /** @var string 命令签名：--yes 跳过确认，--dry-run 预检，--sql 指定 SQL 文件。 */
    protected $signature = 'db:full-reset-and-migrate
        {--yes : 跳过交互确认直接执行}
        {--dry-run : 只做预检（文件、旧库连通性），不写库}
        {--sql= : 指定要执行的 SQL 文件路径（默认 database/sql/full_reset_and_migrate.sql）}';

    /** @var string 命令说明。 */
    protected $description = '新库全量重置（清零+装最新表结构）+ 旧库全量数据迁移（配套 full_reset_and_migrate.sql）';

    /**
     * 默认 SQL 文件路径（相对项目根）。可通过 --sql 覆盖。
     */
    protected function defaultSqlFile(): string
    {
        return database_path('sql/full_reset_and_migrate.sql');
    }

    /**
     * 执行命令：预检 → 确认 → 执行 SQL → 统计验证。
     *
     * @return int 0=成功；1=取消或失败。
     */
    public function handle()
    {
        $sqlFile = $this->option('sql') ?: $this->defaultSqlFile();

        // 1. 预检：SQL 文件必须存在。
        if (! is_file($sqlFile)) {
            $this->error("SQL 文件不存在: {$sqlFile}");
            return 1;
        }
        $this->info("SQL 文件: {$sqlFile}（" . number_format(filesize($sqlFile)) . ' 字节）');

        // 2. 预检：旧库连通性（读取 old_crm 连接配置，仅探测不迁移）。
        try {
            $oldCount = \Illuminate\Support\Facades\DB::connection('old_crm')
                ->table('user')
                ->where('voided', '1')
                ->count();
            $this->info("旧库连接正常（hank_zl_data），user 有效记录: {$oldCount}");
        } catch (\Throwable $e) {
            $this->error('旧库连接失败: ' . $e->getMessage());
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run 预检完成：SQL 文件存在、旧库可连通。未执行任何写操作。');
            return 0;
        }

        // 3. 危险操作确认。
        if (! $this->option('yes')) {
            $this->warn('警告：本命令将清空 co_crmv5 全部业务表并重建，属于不可逆操作！');
            if (! $this->confirm('确认继续执行全量重置与数据迁移？', false)) {
                $this->error('已取消操作');
                return 1;
            }
        }

        // 4. 执行 SQL 文件（PDO 多语句执行；DDL 隐式提交，失败需人工排查后重跑）。
        $this->info('开始执行迁移 SQL ...');
        $started = microtime(true);

        try {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        } catch (\Throwable $e) {
            $this->error('SQL 执行失败: ' . $e->getMessage());
            return 1;
        }

        $elapsed = round(microtime(true) - $started, 1);
        $this->info("SQL 执行完成，耗时 {$elapsed} 秒");

        // 5. 统计验证：输出关键表行数，方便人工核对迁移完整性。
        $this->outputStats();

        // 6. 确认密码与 MT4 开关状态。
        $this->info('前后端用户密码已统一为: abc123（user_logins / admins / big_agents）');
        $this->info('MT4 用户维度同步标记 is_mt4_synced 已统一归零（MT4_USER_SYNC_ENABLED=false，本地为准）');

        return 0;
    }

    /**
     * 输出迁移后关键表行数统计，供人工核对数据完整性。
     *
     * @return void 无返回值，仅输出统计行。
     */
    protected function outputStats()
    {
        $tables = [
            'user_logins' => '前台登录账号（旧 user+agents）',
            'user_infos' => '用户信息主表',
            'user_auths' => '用户认证资料',
            'user_trades' => '本地交易镜像',
            'mt4_trades' => 'MT4 交易记录',
            'mt4_users' => 'MT4 用户',
            'deposit_records' => '入金记录',
            'withdraw_records' => '出金记录',
            'agent_descendants' => '代理树后代',
            'operation_logs' => '后台操作日志',
            'sys_dicts' => '系统字典',
            'user_onlines' => '用户在线状态',
        ];

        $this->newLine();
        $this->info('=== 迁移后统计验证 ===');
        foreach ($tables as $table => $label) {
            try {
                $count = \Illuminate\Support\Facades\DB::table($table)->count();
                $this->line(sprintf('  %-22s %-24s %d 行', $table, $label, $count));
            } catch (\Throwable $e) {
                $this->line(sprintf('  %-22s %-24s 查询失败: %s', $table, $label, $e->getMessage()));
            }
        }
    }
}
