<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:29
 */

/**
 * 应用基础配置（Laravel 主配置）。
 *
 * 配置用途：
 * - 定义应用名称、环境、调试开关、URL、时区、语言、加密密钥、服务提供者与门面别名。
 * - 项目固定使用 Asia/Shanghai 时区与 zh-CN 语言；数据库默认连接 co_crmv5（MySQL）。
 *
 * 注意：
 * - APP_KEY 为加密与 Session/Cookie 签名密钥，生产环境必须使用 `php artisan key:generate` 生成随机串；
 *   密钥变更会导致所有已加密数据（含会话）无法解密。
 * - APP_DEBUG=true 会向访问者暴露堆栈与配置信息，严禁在生产环境开启。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    // 应用名称（用于通知、日志、缓存前缀等场景）。
    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    // 运行环境：local/development/staging/production（影响日志级别等行为）。
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    // 调试模式：true 显示详细错误堆栈（仅限本地），生产必须 false。
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    // 应用根 URL（生成绝对链接、邮件链接等使用）。
    'url' => env('APP_URL', 'http://localhost'),

    // 静态资源 CDN 前缀（可选，留空使用应用 URL）。
    'asset_url' => env('ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    // 应用默认时区：Asia/Shanghai（北京时间，与旧项目一致）。
    'timezone' => 'Asia/Shanghai',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    // 默认语言：zh-CN（简体中文）。
    'locale' => 'zh-CN',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    // 兜底语言：en（当前语言缺少翻译时回退）。
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    // Faker 假数据语言：en_US（种子数据生成时使用）。
    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    // 加密密钥（32 位随机串，生产必须由 key:generate 生成，禁止默认值）。
    'key' => env('APP_KEY'),

    // 加密算法：AES-256-CBC（与 APP_KEY 配套使用）。
    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    // 自动注册的服务提供者列表：框架核心提供者 + 第三方包（Debugbar）+ 应用提供者。
    'providers' => [

        /*
         * Laravel 框架核心服务提供者...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * 第三方包服务提供者...
         */
	    Barryvdh\Debugbar\ServiceProvider::class,

        /*
         * 应用自身服务提供者...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\Mt4ServiceProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    // 门面类别名：提供全局短名访问（Cache、DB、Log 等），Mt4ManagerApi 为项目自定义 MT4 门面。
    'aliases' => [

        'App' => Illuminate\Support\Facades\App::class, // 应用容器与路径访问门面。
        'Arr' => Illuminate\Support\Arr::class, // 数组工具门面。
        'Artisan' => Illuminate\Support\Facades\Artisan::class, // Artisan 命令行门面。
        'Auth' => Illuminate\Support\Facades\Auth::class, // 认证守卫门面。
        'Blade' => Illuminate\Support\Facades\Blade::class, // Blade 模板编译门面。
        'Broadcast' => Illuminate\Support\Facades\Broadcast::class, // 事件广播门面。
        'Bus' => Illuminate\Support\Facades\Bus::class, // 任务/命令总线门面。
        'Cache' => Illuminate\Support\Facades\Cache::class, // 缓存门面。
        'Config' => Illuminate\Support\Facades\Config::class, // 配置仓库门面。
        'Cookie' => Illuminate\Support\Facades\Cookie::class, // Cookie 门面。
        'Crypt' => Illuminate\Support\Facades\Crypt::class, // 加解密门面。
        'Date' => Illuminate\Support\Facades\Date::class, // 日期时间门面（Carbon）。
        'DB' => Illuminate\Support\Facades\DB::class, // 数据库门面。
        'Eloquent' => Illuminate\Database\Eloquent\Model::class, // Eloquent ORM 基类别名。
        'Event' => Illuminate\Support\Facades\Event::class, // 事件调度门面。
        'File' => Illuminate\Support\Facades\File::class, // 文件系统门面。
        'Gate' => Illuminate\Support\Facades\Gate::class, // 授权策略门面。
        'Hash' => Illuminate\Support\Facades\Hash::class, // 密码哈希门面。
        'Http' => Illuminate\Support\Facades\Http::class, // HTTP 客户端门面。
        'Js' => Illuminate\Support\Js::class, // PHP 值转 JavaScript 门面。
        'Lang' => Illuminate\Support\Facades\Lang::class, // 多语言翻译门面。
        'Log' => Illuminate\Support\Facades\Log::class, // 日志门面。
        'Mail' => Illuminate\Support\Facades\Mail::class, // 邮件发送门面。
        'Notification' => Illuminate\Support\Facades\Notification::class, // 通知发送门面。
        'Password' => Illuminate\Support\Facades\Password::class, // 密码重置门面。
        'Queue' => Illuminate\Support\Facades\Queue::class, // 队列门面。
        'RateLimiter' => Illuminate\Support\Facades\RateLimiter::class, // 限流器门面。
        'Redirect' => Illuminate\Support\Facades\Redirect::class, // 重定向响应门面。
        // 'Redis' => Illuminate\Support\Facades\Redis::class, // Redis 门面（已注释停用，走项目自定义连接）。
        'Request' => Illuminate\Support\Facades\Request::class, // 请求门面。
        'Response' => Illuminate\Support\Facades\Response::class, // 响应门面。
        'Route' => Illuminate\Support\Facades\Route::class, // 路由门面。
        'Schema' => Illuminate\Support\Facades\Schema::class, // 数据库结构门面。
        'Session' => Illuminate\Support\Facades\Session::class, // 会话门面。
        'Storage' => Illuminate\Support\Facades\Storage::class, // 存储门面。
        'Str' => Illuminate\Support\Str::class, // 字符串工具门面。
        'URL' => Illuminate\Support\Facades\URL::class, // URL 生成门面。
        'Validator' => Illuminate\Support\Facades\Validator::class, // 校验器门面。
        'View' => Illuminate\Support\Facades\View::class, // 视图门面。
        'Mt4ManagerApi' => App\Facades\Mt4ManagerApi::class, // 项目自定义 MT4 管理接口门面。

    ],

];
