<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

/**
 * 认证 / 授权服务提供者。
 *
 * 文件功能：
 * - 声明模型与授权策略（Policy）的映射表 $policies。
 * - 在 boot() 阶段注册所有策略。
 *
 * 适用场景：
 * - 应用启动时自动加载；新增模型授权策略时在 $policies 中登记。
 *
 * 方法功能：
 * - boot()：调用 registerPolicies() 注册策略映射，当前无额外授权逻辑。
 *
 * 返回值：
 * - boot() 无业务返回值。
 *
 * 异常或失败场景：
 * - 策略映射配置错误时由 Laravel 授权组件在调用 Gate 时抛出异常。
 */
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * 应用内模型与授权策略（Policy）的映射表。
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * 注册认证/授权服务：调用父类 registerPolicies() 注册 $policies 中的
     * 模型-策略映射；当前无额外授权逻辑。
     *
     * @return void 无返回值。
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
