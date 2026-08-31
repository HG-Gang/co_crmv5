<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:53
 */

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * JWT 鉴权服务。
 *
 * 文件功能：
 * - 本服务负责前台 user 与后台 admin 共用的 JWT 生成、解析、刷新和失效。
 * - JWT 载荷中的 sub 表示登录主体 ID，guard 表示认证守卫，jti 表示令牌唯一编号。
 * - SSO 缓存只保存当前有效 jti；旧令牌或黑名单令牌会被解析阶段拦截。
 * - 抛给上层中间件或控制器的业务异常必须使用多语言 key，避免服务层写死英文响应文案。
 *
 * 安全边界：
 * - 签名密钥只存在于服务端配置与内存，不写入日志、接口响应或异常消息。
 * - 解析失败、过期、黑名单命中统一抛认证异常并失败关闭，不向调用方暴露密钥或校验细节。
 * - 刷新窗口从 iat 签发时间计算，超窗必须重新登录；刷新前先作废旧 token，避免新旧令牌并存。
 */
class JwtService
{
    /**
     * @var string $secret 表示 JWT 签名密钥，由 jwt.secret 与 jwt.custom_salt 拼接得到。
     */
    protected $secret;

    /**
     * @var int $ttl 表示访问令牌有效期，单位为分钟。
     */
    protected $ttl;

    /**
     * @var int $refreshTtl 表示刷新窗口有效期，单位为分钟。
     */
    protected $refreshTtl;

    /**
     * @var string $algo 表示 JWT 签名算法，例如 HS256。
     */
    protected $algo;

    /**
     * 构造 JWT 服务并读取认证配置。
     *
     * @return void
     */
    public function __construct()
    {
        // 签名密钥由核心密钥和自定义盐值拼接，避免只依赖单一配置项。
        $this->secret = config('jwt.secret') . config('jwt.custom_salt');
        $this->ttl = config('jwt.ttl', 60);
        $this->refreshTtl = config('jwt.refresh_ttl', 20160);
        $this->algo = config('jwt.algo', 'HS256');
    }

    /**
     * 生成 JWT 令牌。
     *
     * 参数含义：
     * - $payload 表示业务载荷，至少应包含 sub 和 guard。
     * - $jti 表示令牌唯一编号，用于 SSO 当前令牌校验和黑名单失效控制。
     * - $mergedPayload 表示最终写入 JWT 的完整载荷，包含 iss、iat、exp、nbf、jti 和业务载荷。
     * - $cacheKey 表示单点登录缓存键，格式为 sso:{guard}:{sub}。
     *
     * @param array<string, mixed> $payload 业务载荷。
     * @return string JWT 字符串。
     */
    public function generateToken(array $payload): string
    {
        $now = time();
        $jti = Str::random(32);

        $defaultPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + ($this->ttl * 60),
            'nbf' => $now,
            'jti' => $jti,
        ];

        $mergedPayload = array_merge($defaultPayload, $payload);
        $token = JWT::encode($mergedPayload, $this->secret, $this->algo);

        // SSO 逻辑：同一 guard 与 sub 只保留最新 jti，旧 token 会在 SingleSignOn 中间件被识别为冲突。
        if (isset($payload['sub']) && isset($payload['guard'])) {
            $cacheKey = "sso:{$payload['guard']}:{$payload['sub']}";
            Cache::put($cacheKey, $jti, $this->refreshTtl);
        }

        return $token;
    }

    /**
     * 解析并验证 JWT 令牌。
     *
     * 参数含义：
     * - $token 表示待解析或待刷新的 JWT 字符串。
     * - $decoded 表示解码后的 JWT 载荷对象，用于读取 sub、guard、jti、exp 等字段。
     *
     * @param string $token 待解析的 JWT 字符串。
     * @return object 解码后的 JWT 载荷对象。
     * @throws Exception 解析失败、过期或令牌已失效时抛出认证异常。
     */
    public function parseToken(string $token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));

            // 黑名单命中表示该 token 已主动退出或改密后失效，不能继续作为有效登录态使用。
            if (Cache::has("jwt_blacklist:{$decoded->jti}")) {
                throw new Exception(__('response.jwt_token_invalidated'), 4001);
            }

            return $decoded;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 4001);
        }
    }

    /**
     * 在刷新窗口内刷新 JWT 令牌。
     *
     * 参数含义：
     * - $token 表示待解析或待刷新的 JWT 字符串。
     * - $decoded 表示解码后的 JWT 载荷对象。
     * - $payload 表示从旧 token 转成数组后的完整载荷。
     * - $newPayload 表示刷新后新令牌的业务载荷，只保留 sub 与 guard。
     *
     * @param string $token 待刷新的 JWT 字符串。
     * @return string 新生成的 JWT 字符串。
     * @throws Exception token 超出刷新窗口或刷新失败时抛出认证异常。
     */
    public function refreshToken(string $token): string
    {
        try {
            // 刷新允许读取已过期但仍在刷新窗口内的 token，因此临时放大 JWT leeway。
            JWT::$leeway = $this->refreshTtl * 60;
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
            JWT::$leeway = 0;

            $payload = (array) $decoded;

            // 刷新窗口从 iat 签发时间开始计算，超过 refreshTtl 后必须重新登录。
            if (time() > ($payload['iat'] + ($this->refreshTtl * 60))) {
                throw new Exception(__('response.jwt_refresh_window_expired'), 4001);
            }

            // 刷新成功前先作废旧 token，避免旧 token 与新 token 并存。
            $this->invalidateToken($token);

            // 新 token 只继承登录主体和 guard，不继承旧 token 的过期时间和 jti。
            $newPayload = [
                'sub' => $payload['sub'],
                'guard' => $payload['guard'] ?? 'user',
            ];

            return $this->generateToken($newPayload);
        } catch (Exception $e) {
            throw new Exception(__('response.jwt_refresh_failed') . ': ' . $e->getMessage(), 4001);
        }
    }

    /**
     * 将 JWT 的 jti 加入黑名单，使指定令牌失效。
     *
     * 参数含义：
     * - $token 表示待解析或待刷新的 JWT 字符串。
     * - $decoded 表示解码后的 JWT 载荷对象。
     * - $cacheKey 表示单点登录缓存键，当前 token 是最新 SSO token 时会同步删除。
     *
     * @param string $token 待失效的 JWT 字符串。
     * @return bool true=成功写入黑名单，false=令牌无效或无法解析。
     */
    public function invalidateToken(string $token): bool
    {
        try {
            $decoded = $this->getPayload($token);
            if ($decoded && isset($decoded->jti)) {
                // 黑名单只需要保留到 token 自然过期，避免长期堆积无效缓存。
                $ttl = $decoded->exp - time();
                if ($ttl > 0) {
                    Cache::put("jwt_blacklist:{$decoded->jti}", true, $ttl);
                }

                // 如果当前 token 正是 SSO 缓存中的最新 token，则退出时同步清除 SSO 状态。
                $cacheKey = "sso:{$decoded->guard}:{$decoded->sub}";
                if (Cache::get($cacheKey) === $decoded->jti) {
                    Cache::forget($cacheKey);
                }

                return true;
            }
        } catch (Exception $e) {
            // token 已无效或无法解析时，失效操作保持幂等，直接返回 false。
        }

        return false;
    }

    /**
     * 从 JWT 中读取载荷，不按普通访问令牌过期时间拦截。
     *
     * 参数含义：
     * - $token 表示待解析或待刷新的 JWT 字符串。
     * - $decoded 表示解码后的 JWT 载荷对象。
     *
     * @param string $token 待读取载荷的 JWT 字符串。
     * @return object|null 解码成功返回载荷对象，失败返回 null。
     */
    public function getPayload(string $token)
    {
        try {
            // 读取载荷用于退出和刷新场景，允许在 refreshTtl 窗口内解析已过期 token。
            JWT::$leeway = $this->refreshTtl * 60;
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
            JWT::$leeway = 0;

            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }
}
