<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * JWT 鉴权配置（tymon/jwt-auth）。
 *
 * 配置用途：
 * - 定义后台（api/admin）与前台（api/front）登录接口签发 JWT 的密钥、有效期与加密算法。
 * - 配套 SingleSignOn 中间件使用：jwt_token_id 字段记录当前有效 token，实现单点登录互踢。
 *
 * 注意：
 * - secret 与 custom_salt 必须通过环境变量注入，切勿提交真实密钥到代码仓库；
 *   密钥泄漏会导致任意伪造 token，属于高危配置项。
 */

return [
    /**
     * JWT Secret
     * JWT 密钥（签发与验签共用，生产环境必须改为足够长的随机串）。
     */
    'secret' => env('JWT_SECRET', 'your-default-secret-key'),

    /**
     * JWT Custom Salt
     * JWT 自定义盐值，用于增强安全性（与 secret 拼接参与签名）。
     */
    'custom_salt' => env('JWT_CUSTOM_SALT', 'co_crmv5_extra_salt_2026'),

    /**
     * Token lifetime in minutes
     * 令牌有效期（分钟）
     * Default: 60 minutes（默认 60 分钟，过期后需用 refresh token 续期或重新登录）
     */
    'ttl' => env('JWT_TTL', 60),

    /**
     * Refresh window in minutes
     * 刷新有效期（分钟）
     * Default: 14 days (20160 minutes)（默认 14 天，超过后无法刷新）
     */
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),

    /**
     * Encryption algorithm
     * 加密算法（HS256 对称签名，需与签发端保持一致）
     */
    'algo' => 'HS256',
];
