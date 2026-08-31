<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:29
 */

/**
 * 数据库连接配置。
 *
 * 配置用途：
 * - 定义默认数据库连接（mysql：co_crmv5）与各连接参数。
 * - 包含旧 CRM 数据库连接 old_crm（crm_db），供旧项目数据迁移任务读取历史数据。
 * - 同时定义 Redis 缓存/队列连接参数。
 *
 * 注意：
 * - 数据库账号密码默认值仅用于本地开发，生产环境必须通过环境变量注入。
 * - strict=true 开启严格模式，SQL 模式差异会影响查询行为（如分组、零日期处理）。
 */

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    // 默认数据库连接：mysql。
    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    // 数据库连接配置总表：每个键是一个连接名，供 DB::connection() / 模型 $connection 按名调用。
    'connections' => [

        // SQLite 连接（本地轻量测试用）。
        'sqlite' => [
            'driver' => 'sqlite', // 驱动类型：SQLite 文件库。
            'url' => env('DATABASE_URL'), // DATABASE_URL 解析连接串（优先于下面的分项配置）。
            'database' => env('DB_DATABASE', database_path('database.sqlite')), // 数据库文件路径。
            'prefix' => '', // 表前缀。
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true), // 是否启用外键约束。
        ],

        // MySQL 主连接（默认业务库 co_crmv5）。
        'mysql' => [
            'driver' => 'mysql', // 驱动类型：MySQL PDO。
            'url' => env('DATABASE_URL'), // DATABASE_URL 解析连接串（优先于下面的分项配置）。
            'host' => env('DB_HOST', '127.0.0.1'), // 数据库主机。
            'port' => env('DB_PORT', '3307'), // 端口（本地默认 3307）。
            'database' => env('DB_DATABASE', 'co_crmv5'), // 数据库名。
            'username' => env('DB_USERNAME', 'root'), // 用户名（生产必须注入）。
            'password' => env('DB_PASSWORD', '123456'), // 密码（生产必须注入）。
            'unix_socket' => env('DB_SOCKET', ''), // Unix Socket 路径（默认走 TCP）。
            'charset' => 'utf8mb4', // 字符集（支持 emoji 与中文）。
            'collation' => 'utf8mb4_unicode_ci', // 排序规则（不区分大小写）。
            'prefix' => '', // 表前缀。
            'prefix_indexes' => true, // 索引是否加表前缀。
            'strict' => true, // 严格模式（SQL 模式 ONLY_FULL_GROUP_BY 等）。
            'engine' => null, // 默认存储引擎（null=数据库默认 InnoDB）。
            'options' => extension_loaded('pdo_mysql') ? array_filter([ // PDO 连接选项（装了 pdo_mysql 才生效）。
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'), // SSL CA 证书路径（可选）。
                PDO::ATTR_EMULATE_PREPARES => true, // 模拟预处理（兼容旧 SQL 写法）。
            ]) : [],
        ],

        // 旧CRM数据库连接（用于数据迁移）
        'old_crm' => [
            'driver' => 'mysql', // 驱动类型：MySQL PDO。
            'host' => env('OLD_DB_HOST', env('DB_HOST', '127.0.0.1')), // 旧库主机（默认同主库）。
            'port' => env('OLD_DB_PORT', env('DB_PORT', '3307')), // 旧库端口。
            'database' => env('OLD_DB_DATABASE', 'crm_db'), // 旧库名（默认 crm_db）。
            'username' => env('OLD_DB_USERNAME', env('DB_USERNAME', 'root')), // 旧库用户名。
            'password' => env('OLD_DB_PASSWORD', env('DB_PASSWORD', '123456')), // 旧库密码。
            'unix_socket' => env('DB_SOCKET', ''), // Unix Socket 路径（默认走 TCP）。
            'charset' => 'utf8mb4', // 字符集（与主库保持一致，避免迁移乱码）。
            'collation' => 'utf8mb4_unicode_ci', // 排序规则（与主库保持一致）。
            'prefix' => '', // 表前缀。
            'prefix_indexes' => true, // 索引是否加表前缀。
            'strict' => true, // 严格模式（与主库保持一致）。
            'engine' => null, // 默认存储引擎（null=数据库默认 InnoDB）。
        ],

        // PostgreSQL 连接（备用）。
        'pgsql' => [
            'driver' => 'pgsql', // 驱动类型：PostgreSQL PDO。
            'url' => env('DATABASE_URL'), // DATABASE_URL 解析连接串。
            'host' => env('DB_HOST', '127.0.0.1'), // 数据库主机。
            'port' => env('DB_PORT', '5432'), // 端口（PostgreSQL 默认 5432）。
            'database' => env('DB_DATABASE', 'forge'), // 数据库名。
            'username' => env('DB_USERNAME', 'forge'), // 用户名。
            'password' => env('DB_PASSWORD', ''), // 密码。
            'charset' => 'utf8', // 字符集。
            'prefix' => '', // 表前缀。
            'prefix_indexes' => true, // 索引是否加表前缀。
            'schema' => 'public', // 默认 schema。
            'sslmode' => 'prefer', // SSL 模式。
        ],

        // SQL Server 连接（备用）。
        'sqlsrv' => [
            'driver' => 'sqlsrv', // 驱动类型：SQL Server PDO。
            'url' => env('DATABASE_URL'), // DATABASE_URL 解析连接串。
            'host' => env('DB_HOST', 'localhost'), // 数据库主机。
            'port' => env('DB_PORT', '1433'), // 端口（SQL Server 默认 1433）。
            'database' => env('DB_DATABASE', 'forge'), // 数据库名。
            'username' => env('DB_USERNAME', 'forge'), // 用户名。
            'password' => env('DB_PASSWORD', ''), // 密码。
            'charset' => 'utf8', // 字符集。
            'prefix' => '', // 表前缀。
            'prefix_indexes' => true, // 索引是否加表前缀。
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    // 迁移记录表名。
    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    // Redis 连接配置总表：供缓存/队列/广播等组件按名复用。
    'redis' => [

        // Redis 客户端：phpredis（默认）。
        'client' => env('REDIS_CLIENT', 'phpredis'), // 底层客户端扩展：phpredis / predis。

        // 客户端公共选项：集群模式与键前缀。
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'), // 集群模式：redis 原生集群。
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'), // 键前缀。
        ],

        // 默认 Redis 连接（队列等使用）。
        'default' => [
            'url' => env('REDIS_URL'), // REDIS_URL 连接串（优先于分项配置）。
            'host' => env('REDIS_HOST', '127.0.0.1'), // Redis 主机。
            'password' => env('REDIS_PASSWORD', null), // Redis 密码（无则 null）。
            'port' => env('REDIS_PORT', '6379'), // Redis 端口。
            'database' => env('REDIS_DB', '0'), // 逻辑库编号。
        ],

        // 缓存专用 Redis 连接。
        'cache' => [
            'url' => env('REDIS_URL'), // REDIS_URL 连接串（优先于分项配置）。
            'host' => env('REDIS_HOST', '127.0.0.1'), // Redis 主机。
            'password' => env('REDIS_PASSWORD', null), // Redis 密码（无则 null）。
            'port' => env('REDIS_PORT', '6379'), // Redis 端口。
            'database' => env('REDIS_CACHE_DB', '1'), // 逻辑库编号（与默认连接隔离）。
        ],

    ],

];
