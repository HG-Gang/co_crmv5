<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * 密码哈希算法配置。
 *
 * 配置用途：
 * - 定义用户/管理员密码的哈希算法与计算成本参数，影响登录校验与重置密码。
 * - 支持 bcrypt、argon、argon2id 三种驱动。
 *
 * 注意：
 * - 提高 rounds/memory 会增强安全性但降低登录速度，需结合服务器性能权衡；
 *   修改算法参数不会自动重哈希存量密码，登录时按旧参数校验。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. By default, the bcrypt algorithm is
    | used; however, you remain free to modify this option if you wish.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    // 默认哈希驱动：bcrypt（最通用，兼容 PHP 默认扩展）。
    'driver' => 'bcrypt',

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Bcrypt algorithm. This will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    // bcrypt 驱动参数组。
    'bcrypt' => [
        // bcrypt 计算轮数：默认 10，数值越大越耗时越安全（建议 10-12）。
        'rounds' => env('BCRYPT_ROUNDS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Argon algorithm. These will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    // argon 驱动参数组（切到 argon/argon2id 驱动时生效）。
    'argon' => [
        'memory' => 65536, // Argon 内存消耗（KB，默认 64MB）。
        'threads' => 1, // Argon 并行线程数（默认 1）。
        'time' => 4, // Argon 迭代次数（默认 4，越大越耗时）。
    ],

];
