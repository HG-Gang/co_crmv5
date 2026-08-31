<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:42
 */

/**
 * 后台统计演示数据 Seeder。
 *
 * 文件功能：
 * - 为后台表格的独立统计区块（需求 9）与实时返佣统计图表（需求 16）提供可见的演示数字，
 *   避免本地/演示环境打开页面时统计区块全是 0。
 *
 * 为什么需要它：
 * - FrontDemoDataSeeder 已经覆盖 deposit_records / withdraw_records / user_trades / commission_records，
 *   后台出入金统计因此本来就有数字。
 * - 但实时返佣读的是 `mt4_trades`，且口径依赖 COMMENT 命中旧返佣关键词（DBCN、-FY）。
 *   真实库里这一列为空字符串，所以实时返佣列表与统计图表在演示环境是空的。
 *   本 Seeder 专门补齐这批带返佣 COMMENT 的 MT4 记录。
 *
 * 安全边界（关键）：
 * - 生产环境绝不允许出现演示数字。本 Seeder 只能由 DatabaseSeeder 在双重闸门后调用：
 *   1) app()->environment('local', 'testing')；
 *   2) config('seeding.admin_demo_statistics_enabled') === true（对应 ADMIN_DEMO_STATISTICS_SEEDER_ENABLED）。
 * - 控制器与 Blade 里没有任何硬编码的假数字，统计一律来自真实表查询；
 *   演示数据只通过本 Seeder 落库，因此"关掉开关就只剩真实数据"。
 * - 全部使用 insertOrIgnore 并以 ticket 唯一键去重，重复执行不会产生重复行。
 */

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDemoStatisticsSeeder extends Seeder
{
    /**
     * 演示返佣记录使用的 MT4 订单号区间起点。
     *
     * 逻辑说明：
     * - 固定高位区间，避免与真实同步下来的 MT4 订单号冲突。
     *
     * @var int
     */
    private const DEMO_TICKET_BASE = 920000000;

    /**
     * 演示返佣记录覆盖的天数。
     *
     * @var int
     */
    private const DEMO_DAYS = 30;

    /**
     * 写入后台统计演示数据。
     *
     * @return void
     */
    public function run(): void
    {
        // 双重闸门在 Seeder 内部再校验一次：即使有人用
        // `php artisan db:seed --class=AdminDemoStatisticsSeeder` 绕过 DatabaseSeeder，
        // 演示数据也不可能落进非 local/testing 环境或未开开关的库。
        if (!$this->demoSeedingAllowed()) {
            if ($this->command !== null) {
                $this->command->warn(
                    'Admin demo statistics seeding skipped: requires local/testing env and ADMIN_DEMO_STATISTICS_SEEDER_ENABLED=true.'
                );
            }

            return;
        }

        if (!Schema::hasTable('mt4_trades') || !Schema::hasColumn('mt4_trades', 'comment')) {
            return;
        }

        $logins = $this->demoLogins();
        if ($logins === []) {
            return;
        }

        DB::transaction(function () use ($logins): void {
            $this->seedRebateTrades($logins);
        });

        if ($this->command !== null) {
            $this->command->info('Admin demo statistics seeded (mt4_trades rebate rows for realtime commission charts).');
        }
    }

    /**
     * 判断当前进程是否允许写入后台统计演示数据。
     *
     * 双重闸门：
     * - 环境必须是 local 或 testing。
     * - config('seeding.admin_demo_statistics_enabled') 必须显式为布尔 true。
     *
     * @return bool 允许写入返回 true；任一条件不满足都失败关闭。
     */
    private function demoSeedingAllowed(): bool
    {
        return app()->environment('local', 'testing')
            && config('seeding.admin_demo_statistics_enabled', false) === true;
    }

    /**
     * 读取演示用的 MT4 登录号。
     *
     * 逻辑说明：
     * - 实时返佣按 `mt4_trades.login` 关联业务用户的 `user_infos.mt4_code`，
     *   因此演示记录必须挂在真实存在的 mt4_code 上，后台数据范围过滤才能正常命中。
     *
     * @return array<int, int> 最多 6 个演示 MT4 登录号。
     */
    private function demoLogins(): array
    {
        return DB::table('user_infos')
            ->where('mt4_code', '>', 0)
            ->orderBy('user_id')
            ->limit(6)
            ->pluck('mt4_code')
            ->map(static function ($code): int {
                return (int) $code;
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 写入带旧返佣 COMMENT 关键词的 MT4 余额记录。
     *
     * 数据形态说明：
     * - cmd=6 表示余额类交易，profit>0 表示正向入账，两者叠加 COMMENT 关键词才会进入实时返佣口径。
     * - 一半记录用 DBCN（账户返佣），一半用 -FY（旧 FY 返佣），让来源分布图有两个分片。
     * - 按天分散 30 天，让按天序列图表有连续的 X 轴。
     *
     * @param array<int, int> $logins 演示 MT4 登录号列表。
     * @return void
     */
    private function seedRebateTrades(array $logins): void
    {
        $now = time();
        $rows = [];
        $sequence = 0;

        foreach ($logins as $loginIndex => $login) {
            for ($day = 0; $day < self::DEMO_DAYS; $day++) {
                // 每个账号隔天生成一条，避免演示数据量过大又能覆盖整个日期轴。
                if (($day + $loginIndex) % 2 !== 0) {
                    continue;
                }

                $closeTime = $now - ($day * 86400) - 3600;
                $useDbcn = ($sequence % 2) === 0;
                $ticket = self::DEMO_TICKET_BASE + $sequence;

                $rows[] = [
                    'ticket' => $ticket,
                    'login' => $login,
                    'symbol' => 'REBATE',
                    'cmd' => 6,
                    'volume' => 0,
                    'open_price' => 0,
                    'close_price' => 0,
                    'commission' => 0,
                    'swaps' => 0,
                    // 金额随账号与天数波动，保证图表折线不是一条平直线。
                    'profit' => round(12.5 + ($loginIndex * 3.25) + ($day % 7) * 1.75, 2),
                    'open_time' => $closeTime - 1800,
                    'close_time' => $closeTime,
                    'comment' => $useDbcn
                        ? 'DBCN-' . $login . '-#' . $ticket
                        : 'DEMO-' . $login . '-' . $ticket . '-FY',
                    'modify_time' => $closeTime,
                    'created_at' => $closeTime,
                    'updated_at' => $closeTime,
                ];
                $sequence++;
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            // ticket 是唯一键：重复执行只会被忽略，不会产生重复演示记录。
            DB::table('mt4_trades')->insertOrIgnore($chunk);
        }
    }
}
