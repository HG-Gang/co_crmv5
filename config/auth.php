<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:28
 */

/**
 * Laravel 认证守卫与用户提供者配置。
 *
 * 配置用途：
 * - 定义 web/admin/user 三个会话守卫及其对应的用户模型（User/Admin/UserLogin）。
 * - 项目 API 鉴权主链路使用 JWT + SSO（见 config/jwt.php），本配置服务 Session 会话与
 *   Laravel 自带认证能力（Auth::attempt、密码重置等）。
 *
 * 注意：
 * - guards/providers 的模型变更会影响所有会话登录校验，改动前需确认关联表结构。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    // 认证默认值总表：未显式指定守卫/密码重置时使用的默认配置。
    'defaults' => [
        'guard' => 'web', // 默认守卫（web 会话）。
        'passwords' => 'users', // 默认密码重置配置名。
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent user provider.
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | Supported: "session"
    |
    */

    // 认证守卫配置总表：每个键是一个守卫名，决定请求以何种方式认证。
    'guards' => [
        // 框架默认 web 守卫：基于 Session，用户表 users。
        'web' => [
            'driver' => 'session', // 驱动类型：Session 会话认证。
            'provider' => 'users', // 使用的用户提供者（见 providers.users）。
        ],
        // 后台管理员守卫：基于 Session，用户表 admins（admin_logins 迁移后管理员登录表）。
        'admin' => [
            'driver' => 'session', // 驱动类型：Session 会话认证。
            'provider' => 'admins', // 使用的用户提供者（见 providers.admins）。
        ],
        // 前台用户守卫：基于 Session，用户表 user_logins。
        'user' => [
            'driver' => 'session', // 驱动类型：Session 会话认证。
            'provider' => 'user_logins', // 使用的用户提供者（见 providers.user_logins）。
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | If you have multiple user tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    // 用户提供者配置总表：每个键定义一类认证用户的数据来源（模型或表）。
    'providers' => [
        // 默认用户提供者：Eloquent 模型 App\Models\User。
        'users' => [
            'driver' => 'eloquent', // 驱动类型：Eloquent 模型查询。
            'model' => App\Models\User::class, // 认证用户模型类。
        ],
        // 后台管理员提供者：Eloquent 模型 App\Models\Admin。
        'admins' => [
            'driver' => 'eloquent', // 驱动类型：Eloquent 模型查询。
            'model' => App\Models\Admin::class, // 认证用户模型类。
        ],
        // 前台登录用户提供者：Eloquent 模型 App\Models\UserLogin。
        'user_logins' => [
            'driver' => 'eloquent', // 驱动类型：Eloquent 模型查询。
            'model' => App\Models\UserLogin::class, // 认证用户模型类。
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | You may specify multiple password reset configurations if you have more
    | than one user table or model in the application and you want to have
    | separate password reset settings based on the specific user types.
    |
    | The expire time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    */

    // 密码重置配置总表：每个键是一组按用户类型区分的重置配置。
    'passwords' => [
        // 前台用户密码重置配置。
        'users' => [
            'provider' => 'users', // 使用的用户提供者。
            'table' => 'password_resets', // 重置令牌存储表。
            'expire' => 60, // 令牌有效期（分钟）。
            'throttle' => 60, // 重置请求限流间隔（秒）。
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | times out and the user is prompted to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    // 密码确认页超时时间（秒）：默认 3 小时（10800 秒）。
    'password_timeout' => 10800,

];
