<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

/**
 * 事件服务提供者。
 *
 * 文件功能：
 * - 声明事件与监听器映射 $listen（当前注册 Laravel 默认的 Registered 事件与邮件验证通知监听器）。
 * - 在 boot() 阶段注册全部事件监听器。
 *
 * 适用场景：
 * - 应用启动时自动加载；新增业务事件监听时在 $listen 中登记。
 *
 * 方法功能：
 * - boot()：调用父类注册监听器，当前无额外事件逻辑。
 *
 * 返回值：
 * - boot() 无业务返回值。
 *
 * 异常或失败场景：
 * - 监听器类不存在或事件映射错误时在事件触发阶段抛出异常。
 */
namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 事件与监听器的映射表：由父类在 boot 阶段自动注册。
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * 启动阶段：监听器注册由父类根据 $listen 映射自动完成，
     * 当前无额外事件逻辑。
     *
     * @return void 无返回值。
     */
    public function boot()
    {
        //
    }
}
