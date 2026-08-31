<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:28
 */

/**
 * 队列配置。
 *
 * 配置用途：
 * - 定义默认队列连接（默认 sync 同步执行）与各连接参数（database/beanstalkd/sqs/redis）。
 * - 项目中的异步任务（Outbox 出站消息、佣金转账 Saga、MT4 同步等）依赖队列驱动。
 *
 * 注意：
 * - sync 连接下任务同步执行，不产生积压但会拖慢请求；生产建议使用 database 或 redis 并常驻 worker。
 * - retry_after 必须大于单任务最长执行时间，否则任务会被并发重复执行导致资金类操作重复入账。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    // 默认队列连接：sync=同步立即执行（默认，适合开发/测试）。
    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    // 队列连接配置总表：每个键是一个连接名，供队列任务按连接派发。
    'connections' => [

        // 同步连接：任务在请求内立即执行。
        'sync' => [
            'driver' => 'sync', // 驱动类型：请求内同步执行，无重试与积压。
        ],

        // 数据库连接：任务写入 jobs 表，由 queue:work 消费。
        'database' => [
            'driver' => 'database', // 驱动类型：数据库持久化队列。
            'table' => 'jobs', // 任务表。
            'queue' => 'default', // 默认队列名。
            'retry_after' => 90, // 任务失败后重试间隔（秒）。
            'after_commit' => false, // 是否在数据库事务提交后才派发任务。
        ],

        // Beanstalkd 连接。
        'beanstalkd' => [
            'driver' => 'beanstalkd', // 驱动类型：Beanstalkd 消息队列服务。
            'host' => 'localhost', // Beanstalkd 主机。
            'queue' => 'default', // 默认队列名。
            'retry_after' => 90, // 重试间隔（秒）。
            'block_for' => 0, // 无任务时阻塞等待秒数。
            'after_commit' => false, // 是否在数据库事务提交后才派发任务。
        ],

        // AWS SQS 连接。
        'sqs' => [
            'driver' => 'sqs', // 驱动类型：AWS SQS 托管队列。
            'key' => env('AWS_ACCESS_KEY_ID'), // AWS 访问密钥 ID。
            'secret' => env('AWS_SECRET_ACCESS_KEY'), // AWS 密钥。
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'), // 队列 URL 前缀。
            'queue' => env('SQS_QUEUE', 'default'), // 队列名。
            'suffix' => env('SQS_SUFFIX'), // 队列名后缀（可选）。
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'), // AWS 区域。
            'after_commit' => false, // 是否在数据库事务提交后才派发任务。
        ],

        // Redis 连接。
        'redis' => [
            'driver' => 'redis', // 驱动类型：Redis 有序集合队列。
            'connection' => 'default', // 使用的 Redis 连接。
            'queue' => env('REDIS_QUEUE', 'default'), // 队列名。
            'retry_after' => 90, // 重试间隔（秒）。
            'block_for' => null, // 无任务时阻塞等待秒数，null=不阻塞。
            'after_commit' => false, // 是否在数据库事务提交后才派发任务。
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    // 失败任务记录配置。
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'), // 失败任务存储驱动（database-uuids 带 UUID）。
        'database' => env('DB_CONNECTION', 'mysql'), // 存储数据库连接。
        'table' => 'failed_jobs', // 失败任务表名。
    ],

];
