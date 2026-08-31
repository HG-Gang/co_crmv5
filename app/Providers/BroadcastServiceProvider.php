<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

/**
 * 广播服务提供者。
 *
 * 文件功能：
 * - 注册广播路由并加载 routes/channels.php 中的广播频道授权逻辑。
 *
 * 适用场景：
 * - 应用启动时自动加载；项目当前主要保留 Laravel 默认广播能力。
 *
 * 方法功能：
 * - boot()：调用 Broadcast::routes() 注册广播路由，并引入 channels.php 定义频道授权回调。
 *
 * 返回值：
 * - boot() 无业务返回值。
 *
 * 异常或失败场景：
 * - routes/channels.php 存在语法或逻辑错误时在启动阶段抛出异常。
 */
namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * 注册广播路由并加载 routes/channels.php 中的频道授权回调：
     * - Broadcast::routes() 为客户端广播连接/认证提供路由；
     * - channels.php 定义各频道能否被当前用户订阅的授权逻辑。
     *
     * @return void 无返回值。
     */
    public function boot()
    {
        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
