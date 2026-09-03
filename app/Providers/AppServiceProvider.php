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
 * - boot() 阶段设置默认字符串长度、注册迁移目标库守卫、为全部日志通道注入链路标识 Processor、
 *   注册前端视图命名空间。
 *
 * 适用场景：
 * - 应用启动时自动加载，无需手动调用。
 *
 * 方法功能：
 * - register()：注册 PaymentGatewayRegistry 为单例，供支付网关按渠道 code 路由适配器。
 * - boot()：Schema 默认字符串长度 191；注册 front_layui / front_crmui / admin_layui / admin_crmui 四个视图命名空间；
 *   调用 guardMigrationTargetDatabase() 拦截误连正式库的迁移；
 *   调用 attachTraceProcessor() 为日志注入 request_id / trace_id。
 * - guardMigrationTargetDatabase()：监听 MigrationsStarted，校验连接的真实库名，
 *   仅放行 co_crmv5_test 与 :memory:，其余抛 RuntimeException 中止；
 *   ALLOW_PRODUCTION_MIGRATION=true 为人工放行开关。
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

        // 迁移目标库守卫：必须在任何 DDL 之前拦截误连正式库的迁移。
        $this->guardMigrationTargetDatabase();

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
     * 迁移目标库守卫：迁移开始前校验实际连接的数据库名，非允许库直接中止。
     *
     * 为什么需要它：
     * - 项目约束「只有 co_crmv5_test 可写，co_crmv5 与 hank_zl_data 禁写」此前只写在
     *   docs/audits 文档里，没有任何代码层拦截，因此挡不住误操作。
     * - 具体事故：本项目没有 .env.testing 文件，`artisan migrate --env=testing`
     *   会静默回落到 .env（DB_DATABASE=co_crmv5），于是「看起来在写测试库」的命令
     *   实际把 ALTER TABLE 打在了 872 万行的正式表上。而 PHPUnit 走的是
     *   phpunit.xml 里 force="true" 的覆盖，与 --env 完全是两条路径，
     *   「命令行带了 --env=testing」并不构成任何保证。
     * - 靠「下次记得先确认连接」不是修复，只有在 DDL 之前失败才是。
     *
     * 判据取连接的真实库名而非 APP_ENV：库名是 DDL 真正落地的位置，
     * 环境变量只是推断来源，两者不一致时正是事故发生的场景。
     *
     * 白名单是显式的：只放行测试库与内存 SQLite（部分单测用），其余一律中止。
     * 正式库需要变更时走人工放行开关 ALLOW_PRODUCTION_MIGRATION=true，
     * 让「动正式库」成为必须显式声明的动作，而不是默认可能发生的意外。
     *
     * @return void 无返回值。
     */
    private function guardMigrationTargetDatabase(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Database\Events\MigrationsStarted::class,
            static function (): void {
                if (env('ALLOW_PRODUCTION_MIGRATION') === true
                    || env('ALLOW_PRODUCTION_MIGRATION') === 'true') {
                    return;
                }

                $connection = \Illuminate\Support\Facades\DB::connection();
                $database = (string) $connection->getDatabaseName();

                // 内存 SQLite 的库名就是 ':memory:'，不落盘，无需保护。
                $allowed = ['co_crmv5_test', ':memory:'];
                if (in_array($database, $allowed, true)) {
                    return;
                }

                throw new \RuntimeException(
                    '迁移被守卫中止：当前连接指向数据库 [' . $database . ']，'
                    . '本项目只允许对 ' . implode(' / ', $allowed) . ' 执行迁移。'
                    . ' 注意 --env=testing 在缺少 .env.testing 时会回落到 .env，'
                    . '并不保证连到测试库。确需变更正式库请显式设置'
                    . ' ALLOW_PRODUCTION_MIGRATION=true 后重跑。'
                );
            }
        );
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
