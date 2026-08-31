<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/13
 * Time: 18:56
 */

/**
 * 应用控制台内核。
 *
 * 文件功能：
 * - 继承 Laravel 控制台内核，负责注册本应用的 Artisan 命令，
 *   并定义定时任务的调度计划（schedule）。
 *
 * 适用场景：
 * - 每次执行 php artisan 命令时由框架自动加载；
 * - 定时调度器（schedule:run，通常由 cron 每分钟触发）执行时，
 *   会按 schedule() 中定义的规则分发到期任务。
 *
 * 入参例子：
 * - php artisan schedule:run            # 执行到期定时任务
 * - php artisan payments:dispatch-deposit-settlements   # 手动执行单个命令
 *
 * 返回值：
 * - 无返回值；命令执行结果通过控制台输出与退出码表达。
 *
 * 异常或失败场景：
 * - 定时任务失败由各命令自身处理，不会中断其他任务的调度。
 */
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 定义应用命令的调度计划。
     *
     * 说明：
     * - 四个资金/开户 outbox 分发命令均每分钟执行一次，并加 5 分钟
     *   防重叠锁（withoutOverlapping），避免上一轮未结束时重复执行。
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule  调度器实例。
     * @return void 无返回值。
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('payments:dispatch-deposit-settlements')
            ->everyMinute()
            ->withoutOverlapping(5);
        $schedule->command('payments:dispatch-withdraw-settlements')
            ->everyMinute()
            ->withoutOverlapping(5);
        $schedule->command('mt4:dispatch-user-provisioning')
            ->everyMinute()
            ->withoutOverlapping(5);
        $schedule->command('mt4:dispatch-admin-auth-reviews')
            ->everyMinute()
            ->withoutOverlapping(5);
        $schedule->command('commission:dispatch-transfers')
            ->everyMinute()
            ->withoutOverlapping(5);
    }

    /**
     * 注册应用的 Artisan 命令。
     *
     * 说明：
     * - 自动加载 app/Console/Commands 目录下的全部命令类；
     * - 引入 routes/console.php 中定义的闭包式/额外命令。
     *
     * @return void 无返回值。
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
