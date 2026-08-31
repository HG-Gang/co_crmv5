<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/21
 * Time: 23:42
 */

/**
 * 日志通道配置（Monolog）。
 *
 * 配置用途：
 * - 定义默认日志通道（stack 组合）与各通道（single/daily/slack/papertrail/stderr/syslog 等）。
 * - 项目为前后端各业务模块（front_auth、admin_deposit 等 22 个）配置独立 daily 通道，
 *   由 RequestTraceMiddleware 按 URL 前缀归属写入对应模块日志，便于按模块排查问题。
 *
 * 注意：
 * - 模块日志文件默认保留 7 天，磁盘占用随请求量增长，需关注日志目录空间。
 * - LOG_LEVEL 控制日志详细程度，生产环境可提高为 warning 减少 IO。
 */

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    // 默认日志通道：stack（组合 single 单文件通道）。
    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    // PHP/依赖弃用告警日志通道：null=丢弃（默认，避免噪声）。
    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    // 日志通道配置总表：每个键是一个通道名，供 Log::channel() 按名调用。
    'channels' => [
        // stack 组合通道：聚合多个子通道输出（当前组合 daily，按天滚动防止单文件无限膨胀）。
        'stack' => [
            'driver' => 'stack', // 驱动类型：stack。
            'channels' => ['daily'], // 聚合的子通道列表。
            'ignore_exceptions' => false, // 是否忽略异常记录。
        ],

        // 单文件通道：所有日志写入 laravel.log（不滚动；仅显式 LOG_CHANNEL=single 时使用）。
        'single' => [
            'driver' => 'single', // 驱动类型：single。
            'path' => storage_path('logs/laravel.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => env('LOG_LEVEL', 'debug'), // 记录级别：debug 最详细。
        ],

        // 按天滚动通道：每天一个文件，保留 days 天。
        'daily' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/laravel.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => env('LOG_LEVEL', 'debug'), // 记录级别（debug 最详细，生产可调高减少 IO）。
            'days' => 14, // 保留天数。
        ],

        // Slack 告警通道：critical 及以上级别推送 Slack。
        'slack' => [
            'driver' => 'slack', // 驱动类型：slack。
            'url' => env('LOG_SLACK_WEBHOOK_URL'), // Slack Incoming Webhook 地址。
            'username' => 'Laravel Log', // 推送用户名。
            'emoji' => ':boom:', // 推送表情。
            'level' => env('LOG_LEVEL', 'critical'), // 触发级别。
        ],

        // Papertrail 日志服务通道（UDP）。
        'papertrail' => [
            'driver' => 'monolog', // 驱动类型：monolog。
            'level' => env('LOG_LEVEL', 'debug'), // 记录级别（debug 最详细，生产可调高减少 IO）。
            'handler' => SyslogUdpHandler::class, // Monolog 处理器类。
            'handler_with' => [ // 传给处理器的构造参数。
                'host' => env('PAPERTRAIL_URL'), // Papertrail 主机。
                'port' => env('PAPERTRAIL_PORT'), // Papertrail 端口。
            ],
        ],

        // 标准错误输出通道（CLI/容器环境使用）。
        'stderr' => [
            'driver' => 'monolog', // 驱动类型：monolog。
            'level' => env('LOG_LEVEL', 'debug'), // 记录级别（debug 最详细，生产可调高减少 IO）。
            'handler' => StreamHandler::class, // Monolog 处理器类。
            'formatter' => env('LOG_STDERR_FORMATTER'), // 日志格式化器（可选）。
            'with' => [ // 处理器构造参数。
                'stream' => 'php://stderr', // 输出流：标准错误。
            ],
        ],

        // 系统 syslog 通道。
        'syslog' => [
            'driver' => 'syslog', // 驱动类型：syslog。
            'level' => env('LOG_LEVEL', 'debug'), // 记录级别（debug 最详细，生产可调高减少 IO）。
        ],

        // PHP error_log 通道。
        'errorlog' => [
            'driver' => 'errorlog', // 驱动类型：errorlog。
            'level' => env('LOG_LEVEL', 'debug'), // 记录级别（debug 最详细，生产可调高减少 IO）。
        ],

        // 空通道：丢弃所有日志。
        'null' => [
            'driver' => 'monolog', // 驱动类型：monolog。
            'handler' => NullHandler::class, // Monolog 处理器类。
        ],

        // 紧急日志通道：框架兜底写入 laravel.log。
        'emergency' => [
            'path' => storage_path('logs/laravel.log'), // 日志文件路径（daily 驱动下按天滚动）。
        ],
        // front_auth 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_auth' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_auth/front_auth.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_profile 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_profile' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_profile/front_profile.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_deposit 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_deposit' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_deposit/front_deposit.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_withdraw 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_withdraw' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_withdraw/front_withdraw.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_flow 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_flow' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_flow/front_flow.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_trade 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_trade' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_trade/front_trade.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_position 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_position' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_position/front_position.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_commission 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_commission' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_commission/front_commission.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // front_agent 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'front_agent' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/front_agent/front_agent.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_auth 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_auth' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_auth/admin_auth.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_user 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_user' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_user/admin_user.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_agent 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_agent' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_agent/admin_agent.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_deposit 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_deposit' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_deposit/admin_deposit.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_withdraw 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_withdraw' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_withdraw/admin_withdraw.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_fund 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_fund' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_fund/admin_fund.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_trade 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_trade' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_trade/admin_trade.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_risk 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_risk' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_risk/admin_risk.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_auth_review 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_auth_review' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_auth_review/admin_auth_review.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_cancel 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_cancel' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_cancel/admin_cancel.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_permission 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_permission' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_permission/admin_permission.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_online 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_online' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_online/admin_online.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
        // admin_system 模块接口请求/响应日志（daily 滚动，保留 7 天）。
        'admin_system' => [
            'driver' => 'daily', // 驱动类型：daily。
            'path' => storage_path('logs/admin_system/admin_system.log'), // 日志文件路径（daily 驱动下按天滚动）。
            'level' => 'debug', // 记录级别：debug（模块接口日志需全量记录）。
            'days' => 7, // 日志保留天数（天），超期自动清理。
        ],
    ],

];
