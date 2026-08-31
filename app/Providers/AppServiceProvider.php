<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

/**
 * 应用服务提供者。
 *
 * 文件功能：
 * - register() 阶段注册支付网关注册表 PaymentGatewayRegistry 单例。
 * - boot() 阶段设置默认字符串长度、为全部日志通道注入链路标识 Processor、注册前端视图命名空间。
 *
 * 适用场景：
 * - 应用启动时自动加载，无需手动调用。
 *
 * 方法功能：
 * - register()：注册 PaymentGatewayRegistry 为单例，供支付网关按渠道 code 路由适配器。
 * - boot()：Schema 默认字符串长度 191；注册 front_layui / front_crmui / admin_layui / admin_crmui 四个视图命名空间；
 *   调用 attachTraceProcessor() 为日志注入 request_id / trace_id。
 * - attachTraceProcessor()：为默认通道及全部已配置日志通道（含模块 daily 通道）注册 Monolog Processor，
 *   从 RequestTrace::current() 读取链路标识写入每条日志 context。
 *
 * 返回值：
 * - 所有方法均无业务返回值。
 *
 * 异常或失败场景：
 * - 日志通道创建失败时静默跳过（不影响应用启动）。
 */
namespace App\Providers;

use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 注册应用服务：把 PaymentGatewayRegistry 注册为单例，
     * 供支付服务按渠道 code 路由到对应适配器。
     *
     * @return void 无返回值。
     */
    public function register()
    {
        $this->app->singleton(PaymentGatewayRegistry::class, function () {
            return new PaymentGatewayRegistry();
        });
    }

    /**
     * 启动应用服务：
     * - 设置 Schema 默认字符串长度为 191，兼容 utf8mb4 索引长度上限；
     * - 为全部日志通道注入链路标识 Processor；
     * - 注册 front_layui / front_crmui / admin_layui / admin_crmui 四个前端视图命名空间。
     *
     * @return void 无返回值。
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        // 链路追踪全局注入：为所有日志通道（含自定义模块通道）注册 Monolog Processor，
        // 保证 request_id / trace_id 贯穿请求内每一条日志（业务日志、异常日志、模块日志），
        // 便于按 request_id 或 trace_id 检索整个执行链路排查问题。
        $this->attachTraceProcessor();

        $viewNamespaces = [
            'front_layui' => resource_path('front/layui'),
            'front_crmui' => resource_path('front/crmui'),
            'admin_layui' => resource_path('admin/layui'),
            'admin_crmui' => resource_path('admin/crmui'),
        ];

        foreach ($viewNamespaces as $namespace => $path) {
            if (is_dir($path)) {
                view()->addNamespace($namespace, $path);
            }
        }
    }

    /**
     * 为全部日志通道注册链路标识 Processor。
     *
     * 文件功能：
     * - 从 RequestTrace::current() 读取当前请求的 request_id / trace_id，
     *   注入到每一条日志记录的 context 中（包括模块 daily 通道与默认通道）。
     * - 与中间件的 Log::withContext 形成双保险：任何通道、任何代码位置的日志
     *   都必然携带链路标识，支持按 request_id / trace_id 全局检索排查。
     *
     * @return void 无返回值。
     */
    private function attachTraceProcessor(): void
    {
        $processor = function (array $record): array {
            $trace = \App\Support\RequestTrace::current();
            if ($trace['request_id'] !== '' && $trace['trace_id'] !== '') {
                $record['context']['request_id'] = $trace['request_id'];
                $record['context']['trace_id'] = $trace['trace_id'];
            }
            return $record;
        };

        // 默认通道。
        try {
            \Illuminate\Support\Facades\Log::getLogger()->getLogger()->pushProcessor($processor);
        } catch (\Throwable $e) {
            // 通道不可用时静默跳过，不影响启动。
        }

        // 其余已配置通道（含各业务模块 daily 通道）；遍历不能依赖 Logger 可用性，逐条容错。
        $channels = array_keys((array) config('logging.channels', []));
        foreach ($channels as $name) {
            try {
                \Illuminate\Support\Facades\Log::channel($name)->getLogger()->pushProcessor($processor);
            } catch (\Throwable $e) {
                // 通道创建失败不影响启动。
            }
        }
    }
}
