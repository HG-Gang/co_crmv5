<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:59
 */

declare(strict_types=1);

namespace App\Services\Legacy;

use Illuminate\Http\Request;
use LogicException;
use RuntimeException;

/**
 * 遗留表单意图管理服务。
 *
 * 文件功能：
 * - 签发短生命周期、绑定会话的表单提交意图令牌（nonce），防止 CSRF 和重复提交。
 * - 校验表单提交时的意图令牌是否有效（目的、用户、会话一致且在有效期内）。
 *
 * 适用场景：
 * - 旧版表单（Legacy）提交时，先生成意图令牌嵌入表单，提交时校验。
 *
 * 入参例子：
 * - issue: purpose='legacy_deposit_form', userId=10001。
 * - validate: purpose='legacy_deposit_form', userId=10001, nonce='abc123...'（64 位十六进制）。
 *
 * 返回值：
 * - issue(): 64 位十六进制意图令牌字符串。
 * - validate(): true 表示令牌有效，false 表示无效或过期。
 *
 * 异常或失败场景：
 * - LogicException：无活跃会话、purpose 或 userId 非法、时钟异常。
 * - 令牌无效时返回 false 不抛异常。
 */
final class LegacyFormIntentService
{
    /**
     * 会话内保存表单意图令牌的键名。所有遗留表单共享同一个会话槽位；
     * 改名会让已嵌入表单的旧令牌全部失效并丢弃在途提交，属于破坏性契约变更。
     *
     * @var string
     */
    public const SESSION_KEY = 'legacy_form_intents';

    /**
     * 意图令牌默认有效期（秒），固定 900 = 15 分钟。用户打开表单到提交的合理上限：
     * 过短会让慢速填写者提交失败，过长则拉宽 CSRF/重复提交的可用窗口。
     *
     * @var int
     */
    public const TTL_SECONDS = 900;

    /**
     * 单会话同时保留的意图令牌上限，固定 12。超出即淘汰最旧的，
     * 防止恶意刷签发把会话存储撑大；正常用户同时打开的遗留表单不会超过该数量。
     *
     * @var int
     */
    public const MAX_INTENTS = 12;

    /**
     * 实际生效的令牌有效期（秒）。构造时可覆盖默认 TTL_SECONDS，默认值仅在未显式传入时使用，
     * 保证常规装配零配置、测试/特殊场景可调短以验证过期分支。
     *
     * @var int
     */
    private $ttlSeconds;

    /**
     * 实际生效的会话令牌容量上限。可按构造参数覆盖 MAX_INTENTS，
     * 默认与常量一致；容量语义（超出淘汰最旧）不随覆盖改变。
     *
     * @var int
     */
    private $maxIntents;

    /**
     * 时钟闭包：返回当前 Unix 时间戳（秒），默认 time()。所有有效期判定都经它取时，
     * 注入固定时钟后过期/未过期能在测试中完全复现，不受真实时间流逝影响。
     *
     * @var callable
     */
    private $clock;

    /**
     * nonce 生成闭包：返回 64 位小写十六进制随机令牌，默认 random_bytes(32) 转 hex。
     * 令牌不可预测性由它保证；注入的实现若输出不合规格，issue 时立即 RuntimeException 失败关闭，
     * 绝不让弱令牌进入会话。
     *
     * @var callable
     */
    private $nonceGenerator;

    /**
     * 构造意图管理服务。
     *
     * 支持注入时钟与 nonce 生成器便于测试；注入的 nonce 生成器返回结果必须为 64 位小写十六进制，
     * 否则 issue 时抛 RuntimeException 失败关闭，防止不可预测的弱令牌进入会话。
     *
     * @param int $ttlSeconds 令牌有效期，单位秒，默认 900。
     * @param int $maxIntents 会话内最多同时保留的令牌数，默认 12，超出时淘汰最旧的。
     * @param callable|null $clock 返回当前 Unix 时间戳（秒）的闭包，默认 time()。
     * @param callable|null $nonceGenerator 返回随机 nonce 的闭包，默认 random_bytes(32) 转十六进制。
     * @throws LogicException 有效期或最大令牌数小于 1 时抛出。
     */
    public function __construct(
        int $ttlSeconds = self::TTL_SECONDS,
        int $maxIntents = self::MAX_INTENTS,
        callable $clock = null,
        callable $nonceGenerator = null
    ) {
        if ($ttlSeconds < 1 || $maxIntents < 1) {
            throw new LogicException('Legacy form intent limits must be positive.');
        }

        $this->ttlSeconds = $ttlSeconds;
        $this->maxIntents = $maxIntents;
        $this->clock = $clock ?? static function (): int {
            return time();
        };
        $this->nonceGenerator = $nonceGenerator ?? static function (): string {
            return bin2hex(random_bytes(32));
        };
    }

    /**
     * 签发绑定会话的表单意图令牌。
     *
     * 令牌同时绑定 purpose、user_id 与 session_id，嵌入表单后在提交时由 validate 校验；
     * 会话内令牌数达到上限时按签发顺序淘汰最旧的，避免会话无限膨胀。
     *
     * @param Request $request 当前请求，其 session 用于读写意图集合。
     * @param string $purpose 意图用途标识，仅允许小写字母开头、长度不超过 32 的 [a-z0-9_-] 组合。
     * @param int $userId 发起意图的业务用户 ID，必须大于 0。
     * @return string 64 位小写十六进制 nonce 令牌。
     * @throws LogicException 会话不可用或 purpose/userId 非法时抛出。
     * @throws RuntimeException nonce 生成器返回不安全格式时抛出。
     */
    public function issue(Request $request, string $purpose, int $userId): string
    {
        $sessionId = $this->bindingSessionId($request);
        $this->assertBinding($purpose, $userId);
        $now = $this->now();
        $intents = $this->activeIntents($request, $now);

        do {
            $nonce = (string) ($this->nonceGenerator)();
        } while (array_key_exists($nonce, $intents));

        if (!preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
            throw new RuntimeException('Legacy form intent generator returned an unsafe nonce.');
        }

        while (count($intents) >= $this->maxIntents) {
            $oldest = array_key_first($intents);
            if ($oldest === null) {
                break;
            }
            unset($intents[$oldest]);
        }

        $intents[$nonce] = [
            'purpose' => $purpose,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'issued_at' => $now,
        ];
        $request->session()->put(self::SESSION_KEY, $intents);

        return $nonce;
    }

    /**
     * 校验表单提交的意图令牌是否有效。
     *
     * 令牌必须存在、未过期且 purpose/user_id/session_id 全部匹配才算有效；
     * 校验过程中会顺带清理过期与非法条目，任一条件不满足都返回 false，不抛异常。
     *
     * @param Request $request 当前请求，其 session 用于读取意图集合。
     * @param string $purpose 期望的意图用途，必须与签发时一致。
     * @param int $userId 期望的业务用户 ID，必须与签发时一致。
     * @param string $nonce 表单提交的 64 位小写十六进制令牌。
     * @return bool true 表示令牌有效可继续业务；false 表示令牌缺失、过期、格式非法或绑定不一致。
     * @throws LogicException 会话不可用或 purpose/userId 非法时抛出。
     */
    public function validate(Request $request, string $purpose, int $userId, string $nonce): bool
    {
        $sessionId = $this->bindingSessionId($request);
        $this->assertBinding($purpose, $userId);
        if (!preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
            return false;
        }

        $intents = $this->activeIntents($request, $this->now());
        $entry = $intents[$nonce] ?? null;
        $request->session()->put(self::SESSION_KEY, $intents);
        if (!is_array($entry)) {
            return false;
        }

        return (string) ($entry['purpose'] ?? '') === $purpose
            && (int) ($entry['user_id'] ?? 0) === $userId
            && hash_equals((string) ($entry['session_id'] ?? ''), $sessionId);
    }

    /**
     * 从会话中取出未过期且结构合法的意图集合。
     *
     * 逐条过滤：格式非法的 nonce、过期或签发时间在未来的条目、字段类型不符的条目都会被丢弃，
     * 返回的数组按签发时间保留会话内的活跃意图。
     *
     * @param Request $request 当前请求，其 session 用于读取意图集合。
     * @param int $now 当前 Unix 时间戳（秒），用于计算过期。
     * @return array<string, array{purpose: string, user_id: int, session_id: string, issued_at: int}> 活跃意图映射，键为 nonce。
     */
    private function activeIntents(Request $request, int $now): array
    {
        $stored = $request->session()->get(self::SESSION_KEY, []);
        if (!is_array($stored)) {
            return [];
        }

        $active = [];
        foreach ($stored as $nonce => $entry) {
            if (!is_string($nonce) || !is_array($entry)) {
                continue;
            }
            $issuedAt = filter_var($entry['issued_at'] ?? null, FILTER_VALIDATE_INT);
            if ($issuedAt === false || $issuedAt > $now || ($now - $issuedAt) >= $this->ttlSeconds) {
                continue;
            }
            if (!preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
                continue;
            }
            if (!is_string($entry['purpose'] ?? null)
                || !is_numeric($entry['user_id'] ?? null)
                || !is_string($entry['session_id'] ?? null)) {
                continue;
            }
            $active[$nonce] = [
                'purpose' => (string) $entry['purpose'],
                'user_id' => (int) $entry['user_id'],
                'session_id' => (string) $entry['session_id'],
                'issued_at' => (int) $issuedAt,
            ];
        }

        return $active;
    }

    /**
     * 获取当前会话 ID 作为令牌绑定依据。
     *
     * 无活跃会话或会话 ID 为空时抛 LogicException 失败关闭，
     * 防止令牌在没有会话隔离的环境下被签发或校验。
     *
     * @param Request $request 当前请求。
     * @return string 非空会话 ID。
     * @throws LogicException 无活跃会话或会话 ID 不可用时抛出。
     */
    private function bindingSessionId(Request $request): string
    {
        if (!$request->hasSession()) {
            throw new LogicException('Legacy form intents require an active session.');
        }

        $sessionId = trim((string) $request->session()->getId());
        if ($sessionId === '') {
            throw new LogicException('Legacy form intent session id is unavailable.');
        }

        return $sessionId;
    }

    /**
     * 校验意图绑定的 purpose 与 userId 是否合法。
     *
     * purpose 必须匹配小写字母开头的短标识且 userId 必须为正整数，
     * 否则抛 LogicException 失败关闭，避免非法绑定写入会话。
     *
     * @param string $purpose 意图用途标识。
     * @param int $userId 业务用户 ID。
     * @return void
     * @throws LogicException 绑定非法时抛出。
     */
    private function assertBinding(string $purpose, int $userId): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $purpose) || $userId <= 0) {
            throw new LogicException('Legacy form intent binding is invalid.');
        }
    }

    /**
     * 读取当前时钟时间戳。
     *
     * @return int 当前 Unix 时间戳（秒）。
     * @throws LogicException 注入时钟返回负数时抛出，避免负数时间戳污染过期判断。
     */
    private function now(): int
    {
        $now = (int) ($this->clock)();
        if ($now < 0) {
            throw new LogicException('Legacy form intent clock returned an invalid time.');
        }

        return $now;
    }
}
