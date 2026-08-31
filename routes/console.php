<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:26
 */

/**
 * 控制台命令路由文件（Closure 式 Artisan 命令）。
 *
 * 文件功能：
 * - 注册基于 Closure 的 Artisan 控制台命令，每个 Closure 绑定一个命令实例。
 * - 项目内其他正式命令定义在 app/Console/Commands/ 下，本文件仅保留框架默认的 inspire 示例。
 *
 * 运行方式：
 * - 执行 `php artisan inspire` 输出一句激励语录（框架示例）。
 * - 新增轻量调试命令时可在此追加 Artisan::command(...) 定义。
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
