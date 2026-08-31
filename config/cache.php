<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:28
 */

/**
 * 缓存配置。
 *
 * 配置用途：
 * - 定义默认缓存存储与各 store（apc/array/database/file/memcached/redis/dynamodb/octane）。
 * - 项目默认使用 file 驱动（本地文件缓存），生产多实例部署建议切换 redis。
 *
 * 注意：
 * - 默认驱动切换会清空既有缓存键（file 与 redis 数据不互通），发布前需评估缓存重建影响。
 * - 验证码、JWT 单点登录互踢等高频读写依赖缓存可用性，缓存故障会导致登录异常。
 */

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that gets used while
    | using this caching library. This connection is used when another is
    | not explicitly specified when executing a given caching function.
    |
    */

    // 默认缓存存储驱动：file=本地文件（默认）。
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "apc", "array", "database", "file",
    |         "memcached", "redis", "dynamodb", "octane", "null"
    |
    */

    // 缓存 store 配置总表：每个键是一个 store 名，供 Cache::store() 按名调用。
    'stores' => [

        // APC 扩展缓存（需要启用 PHP apcu 扩展）。
        'apc' => [
            'driver' => 'apc', // 驱动类型：APC 共享内存缓存。
        ],

        // 数组缓存：请求内有效，适合测试环境（serialize=false 不序列化存储）。
        'array' => [
            'driver' => 'array', // 驱动类型：进程内数组。
            'serialize' => false, // 是否序列化存储值；false 提升性能但不支持跨请求。
        ],

        // 数据库缓存：写入 cache 表，适合无 Redis 的单机环境。
        'database' => [
            'driver' => 'database', // 驱动类型：数据库表存储。
            'table' => 'cache', // 缓存数据表名（由 cache 表迁移创建）。
            'connection' => null, // 使用的数据库连接，null=默认连接。
            'lock_connection' => null, // 锁使用的连接，null=默认连接。
        ],

        // 文件缓存：默认 store，数据存放 storage/framework/cache/data。
        'file' => [
            'driver' => 'file', // 驱动类型：本地文件。
            'path' => storage_path('framework/cache/data'), // 缓存文件目录（绝对路径）。
        ],

        // Memcached 缓存。
        'memcached' => [
            'driver' => 'memcached', // 驱动类型：Memcached 内存缓存。
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'), // 持久连接 ID（可选）。
            'sasl' => [ // SASL 认证凭据（用户名、密码二元组，可选）。
                env('MEMCACHED_USERNAME'), // SASL 用户名（可选）。
                env('MEMCACHED_PASSWORD'), // SASL 密码（可选）。
            ],
            'options' => [ // Memcached 客户端选项（键为 Memcached::OPT_* 常量）。
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [ // Memcached 服务器列表（支持多台做分布式）。
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'), // Memcached 主机。
                    'port' => env('MEMCACHED_PORT', 11211), // Memcached 端口（默认 11211）。
                    'weight' => 100, // 权重（负载均衡比例）。
                ],
            ],
        ],

        // Redis 缓存：连接 cache 连接，锁用 default 连接。
        'redis' => [
            'driver' => 'redis', // 驱动类型：Redis 缓存（生产多实例部署推荐）。
            'connection' => 'cache', // 使用的 Redis 连接名（见 database.redis.cache）。
            'lock_connection' => 'default', // 缓存锁使用的 Redis 连接名。
        ],

        // AWS DynamoDB 缓存。
        'dynamodb' => [
            'driver' => 'dynamodb', // 驱动类型：AWS DynamoDB 表存储。
            'key' => env('AWS_ACCESS_KEY_ID'), // AWS 访问密钥 ID。
            'secret' => env('AWS_SECRET_ACCESS_KEY'), // AWS 访问密钥。
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'), // AWS 区域。
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'), // 缓存表名。
            'endpoint' => env('DYNAMODB_ENDPOINT'), // 自定义端点（本地模拟器用）。
        ],

        // Octane 缓存：随 Octane 进程内存驻留。
        'octane' => [
            'driver' => 'octane', // 驱动类型：Octane 进程内存（仅常驻运行时有效）。
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a RAM based store such as APC or Memcached, there might
    | be other applications utilizing the same cache. So, we'll specify a
    | value to get prefixed to all our keys so we can avoid collisions.
    |
    */

    // 缓存键前缀（默认 app 名称 + _cache，避免多应用共用 Redis/Memcached 时键冲突）。
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),

];
