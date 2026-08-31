<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/05
 * Time: 23:51
 */

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseGuard;

/**
 * Laravel 功能测试应用启动入口。
 *
 * 文件功能：
 * - 引导 Laravel Console Kernel，使路由、服务容器与配置进入测试状态。
 * - 在业务服务提供者注册前核对数据库身份和 MT4 开关，阻止危险配置继续运行。
 *
 * 安全边界：
 * - 身份核对只读取已加载配置，不建立数据库或 MT4 连接。
 * - 核对失败直接抛异常，禁止回退到 .env 的正式业务库。
 */
trait CreatesApplication
{
    /**
     * 创建并校验当前 PHPUnit 使用的 Laravel 应用实例。
     *
     * @return \Illuminate\Foundation\Application 已完成测试身份核对的应用实例。
     *
     * @throws \RuntimeException 环境、数据库身份或 MT4 开关不符合测试白名单时抛出。
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // 监听点位于 LoadConfiguration 之后、RegisterProviders 之前，危险配置无法先触发提供者副作用。
        TestDatabaseGuard::registerBeforeProviders($app);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
