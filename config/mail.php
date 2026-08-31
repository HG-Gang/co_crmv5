<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:28
 */

/**
 * 邮件发送配置。
 *
 * 配置用途：
 * - 定义默认邮件驱动（smtp）与各 mailer 的连接参数。
 * - 项目用于找回密码邮件、旧官网意见反馈收件（feedback_to）等场景。
 *
 * 注意：
 * - SMTP 账号密码必须通过环境变量注入；发送失败会抛出异常并写入日志，需配置日志通道。
 * - 本地调试可切换 MAIL_MAILER=log 将邮件写入日志，避免真实外发。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    // 默认邮件驱动：smtp（可通过环境变量切换 log/array 等）。
    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses",
    |            "postmark", "log", "array", "failover"
    |
    */

    // 邮件 mailer 配置总表：每个键是一个 mailer 名，供 Mail::mailer() 按名调用。
    'mailers' => [
        // SMTP 邮件服务器配置（默认使用 Mailgun SMTP 参数）。
        'smtp' => [
            'transport' => 'smtp', // 传输协议。
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'), // SMTP 服务器地址。
            'port' => env('MAIL_PORT', 587), // SMTP 端口（587=STARTTLS，465=SSL）。
            'encryption' => env('MAIL_ENCRYPTION', 'tls'), // 加密方式：tls/ssl/null。
            'username' => env('MAIL_USERNAME'), // SMTP 用户名。
            'password' => env('MAIL_PASSWORD'), // SMTP 密码（生产必须注入）。
            'timeout' => null, // 连接超时（秒），null=使用默认值。
            'auth_mode' => null, // 认证模式，null=自动协商。
        ],

        // AWS SES 邮件驱动（凭证见 config/services.php）。
        'ses' => [
            'transport' => 'ses', // 传输协议：AWS SES SDK 发送。
        ],

        // Mailgun 邮件驱动。
        'mailgun' => [
            'transport' => 'mailgun', // 传输协议：Mailgun HTTP API。
        ],

        // Postmark 邮件驱动。
        'postmark' => [
            'transport' => 'postmark', // 传输协议：Postmark HTTP API。
        ],

        // sendmail 本地发送。
        'sendmail' => [
            'transport' => 'sendmail', // 传输协议：调用本机 sendmail 发送。
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -t -i'), // sendmail 可执行路径。
        ],

        // log 驱动：邮件写入日志（本地调试用）。
        'log' => [
            'transport' => 'log', // 传输协议：仅写日志，不真实发送。
            'channel' => env('MAIL_LOG_CHANNEL'), // 写入的日志通道，null=默认通道。
        ],

        // array 驱动：邮件保存在内存数组（测试用）。
        'array' => [
            'transport' => 'array', // 传输协议：仅写入内存数组，不真实发送。
        ],

        // failover 驱动：按顺序尝试 mailers，前一个失败自动切换下一个。
        'failover' => [
            'transport' => 'failover', // 传输协议：故障转移链。
            'mailers' => [ // 依次尝试的 mailer 名称列表。
                'smtp',
                'log',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    // 全局发件人地址与名称（未显式指定 From 时使用）。
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'), // 全局发件人邮箱地址。
        'name' => env('MAIL_FROM_NAME', 'Example'), // 全局发件人显示名称。
    ],

    // 旧官网意见反馈的业务收件邮箱；默认值保持旧项目 mktg@gmtkg.com 行为，可由环境变量覆盖。
    'feedback_to' => env('MAIL_FEEDBACK_TO', 'mktg@gmtkg.com'),

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    // Markdown 邮件主题与组件路径。
    'markdown' => [
        'theme' => 'default', // 主题名。
        'paths' => [ // Markdown 邮件组件模板查找目录列表。
            resource_path('views/vendor/mail'), // Markdown 邮件组件模板目录。
        ],
    ],

];
