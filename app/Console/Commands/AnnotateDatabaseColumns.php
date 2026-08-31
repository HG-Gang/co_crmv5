<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 04:23
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * 数据库表与字段全中文注释命令。
 *
 * 文件功能：
 * - 全库 77 张表补充“表作用注释”（TABLE_COMMENT，全中文）。
 * - 字段注释统一为全中文：
 *   1) 将历史“中文 | English”双语注释提取中文部分（去除英文对照）；
 *   2) 将纯英文注释按翻译映射转为中文；
 *   3) 为缺失注释的字段按 database/sql/column-comments-map.php 补充中文注释。
 * - 幂等：已满足“全中文且非空”的字段/表自动跳过，可重复执行。
 *
 * 适用场景：
 * - 开发/测试/生产库信息补全。
 *
 * 用法：
 * - php artisan db:annotate-columns                    # 直接执行
 * - php artisan db:annotate-columns --export=path.sql  # 仅导出 SQL 不执行
 * - php artisan db:annotate-columns --dry-run          # 预览将要变更的语句
 *
 * 返回值：
 * - 控制台输出统计：双语转中文数、英文翻译数、缺失补充数、表注释数、跳过数。
 *
 * 异常或失败场景：
 * - information_schema 读取失败或 ALTER 执行失败时输出错误并继续（不中断全量）。
 */
class AnnotateDatabaseColumns extends Command
{
    /** @var string 命令签名。 */
    protected $signature = 'db:annotate-columns
        {--export= : 导出 SQL 文件路径（仅导出不执行）}
        {--dry-run : 仅预览变更语句，不执行}
        {--full : 导出/执行全量语句（含已满足注释的字段，用于生成完整可执行 SQL）}';

    /** @var string 命令说明。 */
    protected $description = '为全部表与字段补充/统一全中文注释（双语转中文、英文翻译、缺失补充、表注释）';

    /** 纯英文注释翻译映射（与代码逻辑强相关）。 */
    private const ENGLISH_OVERRIDES = [
        'ID' => '主键标识',
        'IP' => '操作者 IP 地址',
        'JWT Token ID' => 'JWT 令牌标识（SSO 会话绑定）',
        'Cancellation reason submitted by user' => '用户提交的销户原因',
        'legacy_comm_summary/legacy_spread_comm_summary' => '计算类型：legacy_comm_summary=旧佣金汇总 / legacy_spread_comm_summary=旧点差返佣汇总',
        'pending/processing/settled/retryable/rejected/unknown/not_payable' => '状态：pending=待处理 / processing=处理中 / settled=已结算 / retryable=可重试 / rejected=已拒绝 / unknown=未知 / not_payable=不可支付',
        'MT4 comment' => 'MT4 订单备注（服务器侧注释）',
        'MT4 modify time' => 'MT4 修改时间',
        'Original group ID before transfer apply' => '转组申请前的原组别标识',
        'Application reason submitted by agent' => '代理提交的转组申请原因',
    ];

    /** @var bool 全量模式：已满足注释的字段也生成语句（导出完整 SQL 用）。 */
    private bool $full = false;

    /** @var PDO 数据库连接。 */
    private PDO $pdo;

    /** @var array<string, string> 补充注释映射（表名.字段名 => 中文注释）。 */
    private array $columnMap;

    /** @var array<string, string> 表注释映射（表名 => 中文注释）。 */
    private array $tableMap;

    /** @var array<int, string> 待执行 SQL。 */
    private array $sql = [];

    /** @var array<string, int> 统计。 */
    private array $stats = ['bilingual_to_cn' => 0, 'english_translated' => 0, 'missing_filled' => 0, 'table_comment' => 0, 'skipped' => 0];

    /**
     * 执行命令。
     *
     * @return int 0=成功。
     */
    public function handle(): int
    {
        $mapFile = database_path('sql/column-comments-map.php');
        if (! is_file($mapFile)) {
            $this->error("注释映射文件不存在: {$mapFile}");
            return 1;
        }
        $map = require $mapFile;
        $this->columnMap = $map['columns'] ?? [];
        $this->tableMap = $map['tables'] ?? [];

        $this->pdo = DB::connection()->getPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->full = (bool) $this->option('full');

        $this->processTables();
        $this->processColumns();

        $export = $this->option('export');
        if ($export) {
            $this->writeSqlFile((string) $export);
        } elseif (! $this->option('dry-run')) {
            $this->executeSql();
        }

        $this->printStats();
        return 0;
    }

    /**
     * 为全部表补充表作用注释。
     *
     * @return void 无返回值。
     */
    private function processTables(): void
    {
        $tables = $this->pdo->query("SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tables as $t) {
            $name = $t['TABLE_NAME'];
            $comment = trim((string) $t['TABLE_COMMENT']);
            $target = $this->tableMap[$name] ?? null;
            // 表注释为空或与目标不一致时才更新（保证全中文且最新）。
            if ($target === null) {
                continue;
            }
            if ($comment === $target) {
                $this->stats['skipped']++;
                if ($this->full) {
                    // 全量模式：已一致的也导出，保证 SQL 文件可独立完整执行。
                    $this->sql[] = sprintf('ALTER TABLE `%s` COMMENT = %s;', $name, $this->quote($target));
                    $this->stats['table_comment']++;
                }
                continue;
            }
            $sql = sprintf('ALTER TABLE `%s` COMMENT = %s;', $name, $this->quote($target));
            $this->sql[] = $sql;
            $this->stats['table_comment']++;
        }
    }

    /**
     * 为全部字段统一全中文注释。
     *
     * @return void 无返回值。
     */
    private function processColumns(): void
    {
        $rows = $this->pdo->query(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, GENERATION_EXPRESSION, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT
             FROM information_schema.columns WHERE table_schema = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $col) {
            $table = $col['TABLE_NAME'];
            $column = $col['COLUMN_NAME'];
            $current = trim((string) $col['COLUMN_COMMENT']);
            $target = $this->resolveTargetComment($table, $column, $current);

            if ($target === null) {
                $this->stats['skipped']++;
                continue;
            }
            if ($target === $current) {
                $this->stats['skipped']++;
                if ($this->full) {
                    // 全量模式：已为中文的也导出，保证 SQL 文件可独立完整执行。
                    $this->sql[] = $this->buildModifySql($col, $target);
                    $this->stats['missing_filled']++;
                }
                continue;
            }

            $this->sql[] = $this->buildModifySql($col, $target);
            $this->stats[$this->classifyChange($current, $target)]++;
        }
    }

    /**
     * 计算目标注释：双语提取中文 / 英文翻译 / 缺失补充 / 无需变更。
     *
     * @param string $table 表名。
     * @param string $column 字段名。
     * @param string $current 当前注释。
     * @return string|null 目标注释；null 表示无需变更。
     */
    private function resolveTargetComment(string $table, string $column, string $current): ?string
    {
        // 1) 双语注释：取第一个 " | " 前的中文部分，去掉外层方括号。
        if (strpos($current, ' | ') !== false) {
            $cn = trim(explode(' | ', $current, 2)[0]);
            $cn = trim($cn, " \t\n\r\0\x0B[]");
            return $cn === '' ? null : $this->normalizeChinese($cn);
        }

        // 2) 纯英文注释：查翻译映射。
        if (preg_match('/^[A-Za-z0-9 _\-\.\/]+$/u', $current)) {
            $translated = self::ENGLISH_OVERRIDES[$current] ?? null;
            return $translated === null ? null : $this->normalizeChinese($translated);
        }

        // 3) 空注释：查补充映射。
        if ($current === '') {
            $filled = $this->columnMap[$table . '.' . $column] ?? null;
            return $filled === null ? null : $this->normalizeChinese($filled);
        }

        // 4) 已为中文：规范化“中文ID”为“中文标识”；全量模式下直接返回供导出。
        if ($this->full) {
            return $this->normalizeChinese($current);
        }
        $normalized = $this->normalizeChinese($current);

        return $normalized === $current ? null : $normalized;
    }

    /**
     * 全中文规范化：中文后独立使用的 ID 统一为“标识”，保留 JWT/MT4 等组合术语。
     *
     * @param string $comment 原始注释。
     * @return string 规范化后的注释。
     */
    private function normalizeChinese(string $comment): string
    {
        return preg_replace('/([\x{4e00}-\x{9fff}])\s?ID/u', '$1标识', $comment) ?? $comment;
    }

    /**
     * 按变更类型分类统计。
     *
     * @param string $old 旧注释。
     * @param string $new 新注释。
     * @return string 统计键名。
     */
    private function classifyChange(string $old, string $new): string
    {
        if ($old !== '' && strpos($old, ' | ') !== false) {
            return 'bilingual_to_cn';
        }
        if (preg_match('/^[A-Za-z0-9 _\-\.\/]+$/u', $old)) {
            return 'english_translated';
        }
        return 'missing_filled';
    }

    /**
     * 重建 MODIFY COLUMN 语句（保留类型/可空/默认/自增/字符集）。
     *
     * @param array<string, mixed> $col information_schema 列信息。
     * @param string $comment 新注释。
     * @return string ALTER TABLE 语句。
     */
    private function buildModifySql(array $col, string $comment): string
    {
        $def = '`' . $col['COLUMN_NAME'] . '` ' . $col['COLUMN_TYPE'];

        // 字符类型补充字符集与排序规则。
        if (in_array($col['COLUMN_TYPE'], ['text', 'longtext', 'mediumtext', 'tinytext'], true) || strpos((string) $col['COLUMN_TYPE'], 'varchar') === 0 || strpos((string) $col['COLUMN_TYPE'], 'char') === 0) {
            if (! empty($col['COLLATION_NAME'])) {
                $def .= ' CHARACTER SET ' . $col['CHARACTER_SET_NAME'] . ' COLLATE ' . $col['COLLATION_NAME'];
            }
        }

        $generationExpression = trim((string) ($col['GENERATION_EXPRESSION'] ?? ''));
        if ($generationExpression !== '') {
            // MariaDB exposes quoted literals in information_schema with a backslash
            // escape; copying that representation into ALTER TABLE is invalid SQL.
            $generationExpression = str_replace("\\'", "'", $generationExpression);
            $storage = stripos((string) $col['EXTRA'], 'STORED GENERATED') !== false
                ? 'STORED'
                : 'VIRTUAL';
            $def .= ' GENERATED ALWAYS AS (' . $generationExpression . ') ' . $storage;
        } else {
            $def .= $col['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL';

            // 默认值（timestamp CURRENT_TIMESTAMP 与数字/字符串默认值）。
            if ($col['COLUMN_DEFAULT'] !== null) {
                $def .= ' DEFAULT ' . $this->quoteDefault($col['COLUMN_DEFAULT']);
            }

            if (strpos((string) $col['EXTRA'], 'auto_increment') !== false) {
                $def .= ' AUTO_INCREMENT';
            }
        }

        $def .= ' COMMENT ' . $this->quote($comment);

        return sprintf('ALTER TABLE `%s` MODIFY COLUMN %s;', $col['TABLE_NAME'], $def);
    }

    /**
     * 默认值转 SQL 字面量（区分字符串与表达式）。
     *
     * @param string $default 默认值原文。
     * @return string SQL 字面量。
     */
    private function quoteDefault(string $default): string
    {
        if (strtoupper($default) === 'CURRENT_TIMESTAMP' || strpos($default, '(') !== false) {
            return $default;
        }
        return $this->quote($default);
    }

    /**
     * 单引号转义并包裹。
     *
     * @param string $value 原始值。
     * @return string 引号包裹后的 SQL 片段。
     */
    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * 执行全部 SQL。
     *
     * @return void 无返回值。
     */
    private function executeSql(): void
    {
        foreach ($this->sql as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (\Throwable $e) {
                $this->error("执行失败: {$sql} => {$e->getMessage()}");
            }
        }
    }

    /**
     * 导出 SQL 文件。
     *
     * @param string $path 输出路径。
     * @return void 无返回值。
     */
    private function writeSqlFile(string $path): void
    {
        $head = "-- 数据库表与字段全中文注释 SQL（由 db:annotate-columns 生成）\n"
            . "-- 生成时间: " . date('Y-m-d H:i:s') . "\n"
            . "-- 说明: 双语注释取中文、英文注释翻译、缺失字段补充、全表补充表注释\n\n";
        $body = implode("\n", $this->sql);
        file_put_contents($path, $head . $body . "\n");
        $this->info("SQL 已导出: {$path}（" . count($this->sql) . " 条语句）");
    }

    /**
     * 输出统计。
     *
     * @return void 无返回值。
     */
    private function printStats(): void
    {
        $s = $this->stats;
        $this->info(sprintf(
            '完成：双语转中文 %d | 英文翻译 %d | 缺失补充 %d | 表注释 %d | 跳过 %d | 语句总数 %d',
            $s['bilingual_to_cn'],
            $s['english_translated'],
            $s['missing_filled'],
            $s['table_comment'],
            $s['skipped'],
            count($this->sql)
        ));
    }
}
