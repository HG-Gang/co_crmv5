<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * 事件广播（Broadcasting）配置。
 *
 * 配置用途：
 * - 定义事件广播默认驱动与各连接参数（pusher/ably/redis/log/null）。
 * - 项目业务目前未使用实时推送，默认驱动为 null（仅写日志、不真正推送）。
 *
 * 注意：
 * - 切换为 pusher/redis 前需确认队列与 WebSocket 服务就绪，否则广播任务会持续失败。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    // 默认广播驱动：null=不推送（仅本地开发/未启用实时推送时的安全默认值）。
    'default' => env('BROADCAST_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    // 广播连接配置总表：每个键是一个连接名，供广播事件按连接投递。
    'connections' => [

        // Pusher 连接（第三方实时消息服务）。
        'pusher' => [
            'driver' => 'pusher', // 驱动类型。
            'key' => env('PUSHER_APP_KEY'), // Pusher 应用 Key。
            'secret' => env('PUSHER_APP_SECRET'), // Pusher 应用 Secret（生产必须注入环境变量）。
            'app_id' => env('PUSHER_APP_ID'), // Pusher 应用 ID。
            'options' => [ // Pusher 客户端附加选项（集群、TLS 等）。
                'cluster' => env('PUSHER_APP_CLUSTER'), // Pusher 集群区域（如 ap1、mt1）。
                'useTLS' => true, // 是否启用 TLS 加密连接（生产必须为 true）。
            ],
        ],

        // Ably 连接（实时消息服务，Key 从环境变量注入）。
        'ably' => [
            'driver' => 'ably', // 驱动类型：Ably 实时消息服务。
            'key' => env('ABLY_KEY'), // Ably API Key（未注入时连接不可用，广播失败）。
        ],

        // Redis 连接（复用默认 Redis 连接做广播，需 Redis 服务可用）。
        'redis' => [
            'driver' => 'redis', // 驱动类型：Redis 发布/订阅。
            'connection' => 'default', // 复用 database.redis 中名为 default 的连接。
        ],

        // log 连接：广播事件写入日志而不真正推送（用于调试）。
        'log' => [
            'driver' => 'log', // 驱动类型：仅写日志。
        ],

        // null 连接：丢弃广播事件（默认值，适合未启用推送的环境）。
        'null' => [
            'driver' => 'null', // 驱动类型：直接丢弃事件。
        ],

    ],

];
