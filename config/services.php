<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * 第三方服务凭证配置。
 *
 * 配置用途：
 * - 集中存放 Mailgun、Postmark、AWS SES 等邮件/云服务的访问凭证。
 * - 供 Laravel 邮件驱动与相关服务类读取。
 *
 * 注意：
 * - 所有凭证均从环境变量读取，禁止在代码或 .env 之外明文保存；
 *   生产环境凭证泄漏可能导致邮件被滥用或云资源产生额外费用。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // Mailgun 邮件服务配置（使用 mailgun 邮件驱动时生效）。
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'), // Mailgun 发信域名。
        'secret' => env('MAILGUN_SECRET'), // Mailgun API 密钥（生产必须注入）。
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'), // API 端点，默认美国区。
    ],

    // Postmark 邮件服务配置（使用 postmark 驱动时生效）。
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'), // Postmark 服务器 Token。
    ],

    // AWS SES 邮件服务配置（使用 ses 驱动时生效）。
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'), // AWS 访问 Key ID。
        'secret' => env('AWS_SECRET_ACCESS_KEY'), // AWS 访问密钥（生产必须注入）。
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'), // AWS 区域，默认 us-east-1。
    ],

];
