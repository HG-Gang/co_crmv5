<?php

return [
    /**
     * JWT Secret
     * JWT 密钥
     */
    'secret' => env('JWT_SECRET', 'your-default-secret-key'),

    /**
     * JWT Custom Salt
     * JWT 自定义盐值，用于增强安全性
     */
    'custom_salt' => env('JWT_CUSTOM_SALT', 'co_crmv5_extra_salt_2026'),

    /**
     * Token lifetime in minutes
     * 令牌有效期（分钟）
     * Default: 60 minutes
     */
    'ttl' => env('JWT_TTL', 60),

    /**
     * Refresh window in minutes
     * 刷新有效期（分钟）
     * Default: 14 days (20160 minutes)
     */
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),

    /**
     * Encryption algorithm
     * 加密算法
     */
    'algo' => 'HS256',
];
