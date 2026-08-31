<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:39
 */

/**
 * MT4 交易服务器接入配置。
 *
 * 配置用途：
 * - 定义 MT4 Manager API 的连接地址、鉴权凭证、超时与重试策略。
 * - 兼容旧部署环境变量命名：新 MT4_* 优先，缺失时回退读取 MT4_MANAGER_*。
 *
 * 注意：
 * - api_key 为 MT4 管理器连接密钥，必须通过环境变量注入，禁止硬编码。
 * - enabled=false 时任何 MT4 远端操作都会被拒绝（fail-closed），仅本地/测试环境使用。
 * - user_sync_enabled 控制用户维度的同步业务（开户预配、出入金结算、佣金转账等），
 *   与 enabled（MT4 API 连接开关）相互独立。
 */

return [
    // Keep both env names: new MT4_* and old MT4_MANAGER_* from legacy deployment.
    // MT4 服务器主机地址（兼容旧环境变量 MT4_MANAGER_HOST，默认本机 127.0.0.1）。
    'host' => env('MT4_HOST', env('MT4_MANAGER_HOST', '127.0.0.1')),
    // MT4 Manager API 端口（默认 3490）。
    'port' => env('MT4_PORT', env('MT4_MANAGER_PORT', 3490)),
    // MT4 管理器 API 密钥（生产必须从环境变量注入）。
    'api_key' => env('MT4_API_KEY', env('MT4_MANAGER_API_KEY', '')),
    // MT4 API 协议版本号（默认 000005，与 MT4 服务端版本匹配）。
    'api_version' => env('MT4_API_VERSION', env('MT4_MANAGER_API_VERSION', '000005')),
    // 单次请求超时时间（秒，默认 30）。
    'timeout' => env('MT4_TIMEOUT', env('MT4_MANAGER_TIMEOUT', 30)),
    // 失败重试次数（默认 3 次）。
    'retries' => env('MT4_RETRIES', env('MT4_MANAGER_RETRIES', 3)),
    // 重试间隔（秒，默认 1）。
    'retry_delay' => env('MT4_RETRY_DELAY', env('MT4_MANAGER_RETRY_DELAY', 1)),
    // Default false so unit tests never open real sockets unless explicitly enabled.
    // MT4 API 连接总开关：默认 false，单元测试不会真实建连；生产开启后才会调用远端 MT4。
    'enabled' => env('MT4_ENABLED', false),

    // 用户与 MT4 同步全局开关：
    // true = 用户注册开户预配、资料同步、出入金/信用结算、佣金转账等均与 MT4 同步；
    // false（默认）= 关闭所有“用户 ↔ MT4”的同步动作（fail-closed：系统以本地数据为准，
    //         同步类 Outbox 保持 pending，扫描器零派发，不调用任何 MT4 远端操作）。
    // 说明：与上方 enabled 不同——enabled 控制 MT4 API 连接本身，本开关控制用户维度同步业务。
    'user_sync_enabled' => env('MT4_USER_SYNC_ENABLED', false),
];
