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
 *   （migrations 恢复、MT4 同步标记归零、前台代理商与普通客户密码统一 123456）。
 * - SQL 执行完成后跑迁移正确性断言：行数一致不代表迁移正确，断言不通过时命令返回 1。
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
 * - 0=迁移成功完成、统计输出且全部正确性断言通过；
 * - 1=用户取消、SQL 文件缺失、执行失败，或迁移正确性断言未通过。
 *
 * 异常或失败场景：
 * - 旧库不可达、SQL 文件不存在、执行中途报错时命令输出错误并返回 1；
 * - 迁移正确性断言任一项失败时返回 1：数据已落库但不可用于验收，
 *   典型是行数对齐而列内容丢失（实测发生过 mt4_trades.comment 整列丢失）；
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

        // 5. 补录剩余迁移：SQL 脚本只装载数据，mt4_trades 的生成列索引等结构性追加
        //    由独立迁移文件完成，此处统一跑一次 migrate 让表结构与迁移记录保持一致。
        $this->info('补录剩余 migrations ...');
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--force' => true,
            ]);
            $migrateOutput = trim(\Illuminate\Support\Facades\Artisan::output());
            if ($migrateOutput) {
                $this->line($migrateOutput);
            }
        } catch (\Throwable $e) {
            $this->warn('migrate 执行异常: ' . $e->getMessage());
        }

        // 6. 统计验证：输出关键表行数，方便人工核对迁移完整性。
        $this->outputStats();

        // 7. 迁移正确性断言：行数一致不等于迁移正确，必须逐项校验才能判定成败。
        if (! $this->assertMigrationIntegrity()) {
            $this->error('迁移完成但校验未通过：上述项目必须修复后重跑，当前数据不可用于验收。');
            return 1;
        }

        $this->info('MT4 用户维度同步标记 is_mt4_synced 已统一归零（MT4_USER_SYNC_ENABLED=false，本地为准）');

        return 0;
    }

    /**
     * 迁移正确性断言：逐项校验迁移结果，任一项失败即判定迁移不可用。
     *
     * 为什么需要它（取证依据，不是预防性设计）：
     * - outputStats() 只打印行数，而 2026-09-01 实测证实了一类它抓不到的缺陷：
     *   mt4_trades 中 cmd=6 的行数两库完全一致（均 617352），但 comment 列在新库
     *   100% 为空（旧库 611274 行含 DBCN）。行数对齐掩盖了整列丢失。
     * - 后果是静默的：实时返佣与入金/出金流水按 comment LIKE '%DBCN%' 筛选，
     *   在新库返回空列表且不报错，页面看起来「正常但没数据」。
     * - 因此判据必须落到「列内容」而非「行数」，且失败要让命令退出码非 0，
     *   否则迁移脚本会声称成功。
     *
     * @return bool true=全部通过；false=至少一项失败（调用方据此返回退出码 1）。
     */
    protected function assertMigrationIntegrity(): bool
    {
        $this->newLine();
        $this->info('=== 迁移正确性断言 ===');
        $passed = true;

        foreach ($this->integrityChecks() as $check) {
            try {
                $actual = ($check['actual'])();
                $ok = ($check['assert'])($actual);
            } catch (\Throwable $e) {
                $this->line(sprintf('  [FAIL] %-34s 校验执行异常: %s', $check['name'], $e->getMessage()));
                $passed = false;
                continue;
            }

            $this->line(sprintf(
                '  [%s] %-34s 实测 %s%s',
                $ok ? 'PASS' : 'FAIL',
                $check['name'],
                $actual,
                $ok ? '' : '  期望 ' . $check['expect']
            ));

            if (! $ok) {
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * 迁移必查项清单：每项含名称、实测取值闭包、判定闭包与期望描述。
     *
     * 清单按「已发生过或需求明确要求」筛选，不做预防性堆砌：
     * - comment 列内容：2026-09-01 实测证实的整列丢失，行数校验抓不到。
     * - 密码统一 123456：需求 6 明确指定。
     * - 代理商 ID 起 1001 / 普通客户 ID 起 600001：需求 6 明确指定，
     *   两者共用 user_logins 表，起始段错位会让代理与客户 ID 空间重叠。
     *
     * @return array<int, array{name: string, actual: callable, assert: callable, expect: string}>
     *         必查项清单。
     */
    protected function integrityChecks(): array
    {
        $db = \Illuminate\Support\Facades\DB::connection();

        return [
            [
                'name' => 'mt4_trades 返佣 comment 未丢失',
                'actual' => function () use ($db) {
                    return (int) $db->table('mt4_trades')
                        ->where('cmd', 6)
                        ->where('comment', 'like', '%DBCN%')
                        ->count();
                },
                'assert' => function ($n) {
                    return $n > 0;
                },
                'expect' => '> 0 行（旧库 611274 行含 DBCN；为 0 说明 comment 列在迁移中丢失，返佣与流水将静默返回空）',
            ],
            [
                'name' => '前台密码统一为 123456',
                'actual' => function () use ($db) {
                    $wrong = 0;
                    $db->table('user_logins')->select('id', 'password')->orderBy('id')
                        ->chunk(2000, function ($rows) use (&$wrong) {
                            foreach ($rows as $row) {
                                if (! \Illuminate\Support\Facades\Hash::check('123456', (string) $row->password)) {
                                    $wrong += 1;
                                }
                            }
                        });
                    return $wrong . ' 个账号密码不匹配';
                },
                'assert' => function ($s) {
                    return strpos((string) $s, '0 个') === 0;
                },
                'expect' => '0 个不匹配（需求 6：所有代理商与普通客户密码统一 123456）',
            ],
            // 判据落在 user_id（业务用户 ID，来自 id_sequences）而非自增主键 id：
            // 需求 6 指定的是对外可见的用户 ID，两者在本表是不同字段。
            // 身份用 account_type（1=代理 2=客户），见 create_user_logins_table 字段语义。
            [
                'name' => '代理商 user_id 起始 1001',
                'actual' => function () use ($db) {
                    return (int) $db->table('user_logins')->where('account_type', 1)->min('user_id');
                },
                'assert' => function ($n) {
                    return (int) $n === 1001;
                },
                'expect' => '1001',
            ],
            [
                'name' => '普通客户 user_id 起始 600001',
                'actual' => function () use ($db) {
                    return (int) $db->table('user_logins')->where('account_type', 2)->min('user_id');
                },
                'assert' => function ($n) {
                    return (int) $n === 600001;
                },
                'expect' => '600001',
            ],
            // 代理与客户共用 user_logins，两段 ID 空间重叠会让业务 ID 无法区分身份，
            // 因此除起始值外还须断言两段不交叉。
            [
                'name' => '代理与客户 user_id 区间不重叠',
                'actual' => function () use ($db) {
                    $agentMax = (int) $db->table('user_logins')->where('account_type', 1)->max('user_id');
                    $customerMin = (int) $db->table('user_logins')->where('account_type', 2)->min('user_id');
                    return '代理最大 ' . $agentMax . '，客户最小 ' . $customerMin;
                },
                'assert' => function ($s) use ($db) {
                    $agentMax = (int) $db->table('user_logins')->where('account_type', 1)->max('user_id');
                    $customerMin = (int) $db->table('user_logins')->where('account_type', 2)->min('user_id');
                    return $agentMax > 0 && $customerMin > 0 && $agentMax < $customerMin;
                },
                'expect' => '代理最大 user_id < 客户最小 user_id',
            ],
            // 上面四条只看边界值，换算漏一列照样能全绿：把 user_id 改了、
            // parent_id 没跟着改，MIN 已是 1001、区间也不重叠，但 50 个旧根节点的
            // 下级会指向不存在的号。这条按引用完整性判，直接暴露断链。
            [
                'name' => '代理层级引用无孤儿',
                'actual' => function () use ($db) {
                    return $this->orphanParentCount($db) . ' 个 parent_id 指向不存在的账号';
                },
                'assert' => function () use ($db) {
                    return $this->orphanParentCount($db) === 0;
                },
                'expect' => '0 个',
            ],
            // id_sequences 若被全局最大值覆盖，新注册代理商会拿到客户区间的 ID。
            // 历史数据的四条断言此时全部通过，缺陷只在线上首个新代理商注册时才现形，
            // 所以必须在迁移当场按类型校验序列水位。
            [
                'name' => 'id_sequences 按类型对齐本段水位',
                'actual' => function () use ($db) {
                    return '代理序列 ' . (int) $db->table('id_sequences')->where('type', 'agent')->value('current_value')
                        . '，客户序列 ' . (int) $db->table('id_sequences')->where('type', 'customer')->value('current_value');
                },
                'assert' => function () use ($db) {
                    $agentSeq = (int) $db->table('id_sequences')->where('type', 'agent')->value('current_value');
                    $customerSeq = (int) $db->table('id_sequences')->where('type', 'customer')->value('current_value');
                    $customerMin = (int) $db->table('user_infos')->where('account_type', 2)->min('user_id');

                    // 代理序列必须留在代理段内（下一个号不能越进客户段），
                    // 客户序列必须已越过客户段起点，否则会重发已用 ID。
                    return $agentSeq >= 1001 && $customerMin > 0
                        && $agentSeq < $customerMin && $customerSeq >= $customerMin;
                },
                'expect' => '代理序列 < 客户最小 user_id 且客户序列 >= 客户最小 user_id',
            ],
        ];
    }

    /**
     * 统计 parent_id 指向不存在账号的活跃行数。
     *
     * 只看未软删行：软删账号不参与活跃层级，其 parent_id 保留迁移原值，
     * 与 full_reset_and_migrate.sql §2.6 层级重算的口径一致。
     *
     * @param \Illuminate\Database\ConnectionInterface $db 数据库连接。
     * @return int 孤儿行数，0 表示引用完整。
     */
    protected function orphanParentCount($db)
    {
        return (int) $db->table('user_infos as u')
            ->whereNull('u.deleted_at')
            ->where('u.parent_id', '>', 0)
            ->whereNotExists(function ($query) {
                $query->select($query->raw(1))
                    ->from('user_infos as p')
                    ->whereColumn('p.user_id', 'u.parent_id')
                    ->whereNull('p.deleted_at');
            })
            ->count();
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
