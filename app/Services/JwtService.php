<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

/**
 * JWT 鉴权服务 | JWT Authentication Service
 * 
 * 负责生成、解析、刷新和注销 JWT 令牌
 * Responsible for generating, parsing, refreshing, and invalidating JWT tokens
 */
class JwtService
{
    /**
     * JWT 密钥 | JWT Secret
     * @var string
     */
    protected $secret;

    /**
     * 令牌有效期（分钟） | Token lifetime (minutes)
     * @var int
     */
    protected $ttl;

    /**
     * 刷新有效期（分钟） | Refresh window (minutes)
     * @var int
     */
    protected $refreshTtl;

    /**
     * 加密算法 | Encryption algorithm
     * @var string
     */
    protected $algo;

    /**
     * 构造函数：初始化配置，支持自定义盐值增强安全性
     * Constructor: Initialize config, support custom salt for enhanced security
     */
    public function __construct()
    {
        // 实际密钥由核心密钥和自定义盐值拼接而成
        // Actual secret is composed of core secret and custom salt
        $this->secret = config('jwt.secret') . config('jwt.custom_salt');
        $this->ttl = config('jwt.ttl', 60);
        $this->refreshTtl = config('jwt.refresh_ttl', 20160);
        $this->algo = config('jwt.algo', 'HS256');
    }

    /**
     * 生成 JWT 令牌 | Generate JWT token
     *
     * @param array $payload 载荷，应包含 sub (user_id) 和 guard | Payload, should contain sub and guard
     * @return string
     */
    public function generateToken(array $payload): string
    {
        $now = time();
        $jti = Str::random(32);

        $defaultPayload = [
            'iss'   => config('app.url'),   // Issuer 签发者
            'iat'   => $now,                // Issued at 签发时间
            'exp'   => $now + ($this->ttl * 60), // Expiration 过期时间
            'nbf'   => $now,                // Not before 在此之前不可用
            'jti'   => $jti,                // JWT ID 令牌唯一标识
        ];

        $mergedPayload = array_merge($defaultPayload, $payload);

        $token = JWT::encode($mergedPayload, $this->secret, $this->algo);

        // SSO 逻辑：在缓存中存储当前有效的 JTI
        // SSO logic: store the current valid JTI in cache
        if (isset($payload['sub']) && isset($payload['guard'])) {
            $cacheKey = "sso:{$payload['guard']}:{$payload['sub']}";
            Cache::put($cacheKey, $jti, $this->refreshTtl);
        }

        return $token;
    }

    /**
     * 解析并验证令牌 | Parse and validate token
     *
     * @param string $token
     * @return object
     * @throws Exception
     */
    public function parseToken(string $token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));

            // 检查令牌是否在黑名单中
            // Check if token is blacklisted
            if (Cache::has("jwt_blacklist:{$decoded->jti}")) {
                throw new Exception('Token has been invalidated', 4001);
            }

            return $decoded;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 4001);
        }
    }

    /**
     * 在刷新窗口内刷新令牌 | Refresh a token if within the refresh window
     *
     * @param string $token
     * @return string
     * @throws Exception
     */
    public function refreshToken(string $token): string
    {
        try {
            // 为刷新操作允许一定的过期时间偏移量
            // For refresh, we'll allow expired tokens by setting a large leeway
            JWT::$leeway = $this->refreshTtl * 60; 
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
            JWT::$leeway = 0; // 重置偏移量 | Reset leeway

            $payload = (array)$decoded;
            
            // 检查是否在刷新窗口内（从签发时间开始计算）
            // Check if it's within refresh window from iat
            if (time() > ($payload['iat'] + ($this->refreshTtl * 60))) {
                throw new Exception('Token cannot be refreshed, refresh window expired', 4001);
            }

            // 作废旧令牌 | Invalidate old token
            $this->invalidateToken($token);

            // 生成新令牌 | Generate new token
            $newPayload = [
                'sub'   => $payload['sub'],
                'guard' => $payload['guard'] ?? 'user',
            ];

            return $this->generateToken($newPayload);
        } catch (Exception $e) {
            throw new Exception('Token refresh failed: ' . $e->getMessage(), 4001);
        }
    }

    /**
     * 将令牌 JTI 加入黑名单以使其失效 | Invalidate token by adding JTI to blacklist
     *
     * @param string $token
     * @return bool
     */
    public function invalidateToken(string $token): bool
    {
        try {
            $decoded = $this->getPayload($token);
            if ($decoded && isset($decoded->jti)) {
                // 将令牌加入黑名单直到其自然过期
                // Blacklist until it would have expired naturally
                $ttl = $decoded->exp - time();
                if ($ttl > 0) {
                    Cache::put("jwt_blacklist:{$decoded->jti}", true, $ttl);
                }
                
                // 如果是当前 SSO 令牌，也清除缓存
                // Also clear SSO cache if it's the current one
                $cacheKey = "sso:{$decoded->guard}:{$decoded->sub}";
                if (Cache::get($cacheKey) === $decoded->jti) {
                    Cache::forget($cacheKey);
                }
                return true;
            }
        } catch (Exception $e) {
            // 如果已无效则忽略错误 | Ignore error if already invalid
        }
        return false;
    }

    /**
     * 从令牌中获取解码后的载荷（不验证过期时间） | Get decoded payload from token without validating expiration
     *
     * @param string $token
     * @return object|null
     */
    public function getPayload(string $token)
    {
        try {
            // 使用大偏移量以在过期时也能获取载荷
            // Use a large leeway to get payload even if expired
            JWT::$leeway = $this->refreshTtl * 60;
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
            JWT::$leeway = 0;
            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }
}
