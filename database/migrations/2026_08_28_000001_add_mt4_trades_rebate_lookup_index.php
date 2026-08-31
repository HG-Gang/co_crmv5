<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:54
 */

/**
 * 为 mt4_trades 增加实时返佣检索用的生成列与复合索引。
 *
 * 文件功能：
 * - 新增 STORED 生成列 `is_rebate`：把旧项目 COMMENT 返佣关键词（DBCN、-FY）判定固化成可索引的 0/1 标记。
 * - 新增 STORED 生成列 `rebate_time`：把 `COALESCE(NULLIF(modify_time, 0), close_time)` 排序表达式固化成可索引列。
 * - 新增复合覆盖索引 `mt4_trades_rebate_lookup_index (is_rebate, cmd, rebate_time, profit)`。
 *
 * 字段语义：
 * - is_rebate=1 表示该 MT4 余额记录的 COMMENT 命中旧返佣关键词，可直接进入实时返佣列表口径。
 * - rebate_time 表示返佣确认时间，优先 modify_time，为 0 或 NULL 时回退 close_time。
 *
 * 变更原因（性能）：
 * - 旧实现的 `comment LIKE '%DBCN%'` 前置通配符无法用索引，且 ORDER BY 是表达式，
 *   导致每次请求都对全表做 `type=ALL` 扫描 + filesort（生产 87 万行，单次约 0.5 秒，一次请求 4 趟）。
 * - MySQL 8.0.12 不支持函数索引（8.0.13 才引入），因此用 STORED 生成列 + 普通索引达到同等效果。
 * - 索引末位带上 `profit`，让「COUNT(*) + SUM(profit)」汇总变成 `Using index` 的仅索引扫描，
 *   80 万行实测汇总耗时 3.8ms -> 0.5ms。
 *
 * 兼容性：
 * - 仅在 MySQL 上执行；生成列缺失时 RealtimeCommissionController 会自动回退到旧表达式口径，
 *   因此本迁移未落库的环境依然可用，只是没有索引加速。
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMt4TradesRebateLookupIndex extends Migration
{
    /**
     * 复合索引名称。
     *
     * @var string
     */
    public const INDEX_NAME = 'mt4_trades_rebate_lookup_index';

    /**
     * `is_rebate` 生成列表达式。
     *
     * 逻辑说明：
     * - 与 RealtimeCommissionController::REBATE_COMMENT_KEYWORDS 保持同一组关键词，
     *   AdminRealtimeCommissionPerformanceModuleTest 会断言两侧不漂移。
     *
     * @var string
     */
    public const IS_REBATE_EXPRESSION = "(CASE WHEN `comment` LIKE '%DBCN%' OR `comment` LIKE '%-FY%' THEN 1 ELSE 0 END)";

    /**
     * `rebate_time` 生成列表达式。
     *
     * @var string
     */
    public const REBATE_TIME_EXPRESSION = '(COALESCE(NULLIF(`modify_time`, 0), `close_time`))';

    /**
     * 追加返佣检索生成列与复合索引。
     *
     * @return void
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('mt4_trades')) {
            return;
        }

        if (!Schema::hasColumn('mt4_trades', 'comment') || !Schema::hasColumn('mt4_trades', 'modify_time')) {
            return;
        }

        if (!Schema::hasColumn('mt4_trades', 'is_rebate')) {
            DB::statement(
                'ALTER TABLE `mt4_trades` ADD COLUMN `is_rebate` TINYINT UNSIGNED '
                . 'GENERATED ALWAYS AS ' . self::IS_REBATE_EXPRESSION . ' STORED '
                . "COMMENT '旧项目 COMMENT 返佣关键词命中标记：1=返佣记录，0=其他余额记录'"
            );
        }

        if (!Schema::hasColumn('mt4_trades', 'rebate_time')) {
            DB::statement(
                'ALTER TABLE `mt4_trades` ADD COLUMN `rebate_time` INT UNSIGNED '
                . 'GENERATED ALWAYS AS ' . self::REBATE_TIME_EXPRESSION . ' STORED '
                . "COMMENT '返佣确认时间：优先 modify_time，为 0 或 NULL 时回退 close_time'"
            );
        }

        // 索引列顺序：等值条件 is_rebate/cmd 在前，rebate_time 供 ORDER BY 与区间过滤，
        // profit 收尾让汇总查询覆盖索引（Using index），无需回表。
        if (!$this->indexExists()) {
            DB::statement(
                'ALTER TABLE `mt4_trades` ADD INDEX `' . self::INDEX_NAME . '` '
                . '(`is_rebate`, `cmd`, `rebate_time`, `profit`)'
            );
        }
    }

    /**
     * 回滚生成列与复合索引。
     *
     * @return void
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('mt4_trades')) {
            return;
        }

        if ($this->indexExists()) {
            DB::statement('ALTER TABLE `mt4_trades` DROP INDEX `' . self::INDEX_NAME . '`');
        }

        foreach (['rebate_time', 'is_rebate'] as $column) {
            if (Schema::hasColumn('mt4_trades', $column)) {
                DB::statement('ALTER TABLE `mt4_trades` DROP COLUMN `' . $column . '`');
            }
        }
    }

    /**
     * 判断复合索引是否已经存在。
     *
     * @return bool 已存在返回 true。
     */
    private function indexExists(): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'mt4_trades')
            ->where('INDEX_NAME', self::INDEX_NAME)
            ->exists();
    }
}
