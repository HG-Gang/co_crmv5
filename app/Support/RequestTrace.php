<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:03
 */

namespace App\Support;

use Illuminate\Support\Str;

/**
 * 请求链路追踪标识生成器（request_id / trace_id）。
 *
 * 文件功能：
 * - request_id 使用标准 ULID（Crockford Base32，26 字符：48 位毫秒时间戳 + 80 位随机）。
 * - trace_id 使用标准 UUID v4（复用 Laravel Str::uuid，依赖 ramsey/uuid）。
 * - 不依赖 symfony/uid 扩展包，纯 PHP 实现 ULID，保证任何环境可用。
 *
 * 适用场景：
 * - RequestTraceMiddleware 在请求入口生成并注入响应头与响应体。
 *
 * 入参例子：
 * - RequestTrace::ulid()  -> 01J3X8Y2Z3ABCDEFGHJKLMNPQ
 * - RequestTrace::uuid()  -> 550e8400-e29b-41d4-a716-446655440000
 *
 * 返回值：
 * - ulid(): string 26 位大写 Crockford Base32 字符串。
 * - uuid(): string 36 位标准 UUID v4 字符串。
 *
 * 异常或失败场景：
 * - random_bytes 失败时抛出 \RuntimeException（系统级随机源不可用时）。
 */
final class RequestTrace
{
    /** @var array<string, string> 当前请求链路标识（贯穿该请求内所有通道日志）。 */
    private static array $current = ['request_id' => '', 'trace_id' => ''];

    /**
     * 记录当前请求链路标识（由中间件在请求入口调用）。
     *
     * @param string $requestId ULID。
     * @param string $traceId UUID。
     * @return void 无返回值。
     */
    public static function begin(string $requestId, string $traceId): void
    {
        self::$current = ['request_id' => $requestId, 'trace_id' => $traceId];
    }

    /**
     * 获取当前请求链路标识。
     *
     * 说明：Monolog Processor 从该方法读取并注入到所有日志通道，
     * 保证控制器/服务/中间件/异常处理中的每一条日志都携带 request_id / trace_id。
     *
     * @return array<string, string> 当前链路标识；CLI/队列等无请求场景返回空标识。
     */
    public static function current(): array
    {
        return self::$current;
    }

    /**
     * 生成 ULID（26 字符，时间有序、可排序）。
     *
     * 实现说明：完全遵循官方 ULID 规范参考实现——
     * - 时间部分：48 位毫秒时间戳 -> 10 个 Base32 字符（高位到低位）。
     * - 随机部分：80 bit（10 字节）-> 16 个 Base32 字符（每累积 5 bit 输出一个字符）。
     * - 余位处理：不足 5 bit 时左移补齐后输出。
     *
     * @return string ULID 字符串。
     *
     * @throws \RuntimeException 随机源不可用时抛出。
     */
    public static function ulid(): string
    {
        // Crockford's Base32 字母表（官方标准，排除了 I, L, O, U），保证 ULID 可排序且无歧义字符。
        $base32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

        // 时间部分：48 位毫秒时间戳按 32 进制逐位编码为 10 个字符（高位在前），保证时间有序性。
        $timestampMs = (int) floor(microtime(true) * 1000);
        $timePart = '';
        $tempTime = $timestampMs;
        for ($i = 9; $i >= 0; $i--) {
            $mod = $tempTime % 32;
            $timePart = $base32[$mod] . $timePart;
            $tempTime = (int) ($tempTime / 32);
        }

        // 随机部分：80 bit（10 字节）通过位缓冲每 5 bit 输出一个字符，共 16 字符。
        $randomBytes = random_bytes(10);
        $randomPart = '';

        $buffer = 0;
        $bufferBits = 0;

        for ($i = 0; $i < 10; $i++) {
            // 每个字节进缓冲 8 bit；足 5 bit 即取高 5 bit 输出，直到不足 5 bit。
            $buffer = ($buffer << 8) | ord($randomBytes[$i]);
            $bufferBits += 8;

            while ($bufferBits >= 5) {
                $bufferBits -= 5;
                $index = ($buffer >> $bufferBits) & 0x1F; // 取高 5 bit。
                $randomPart .= $base32[$index];
            }
        }

        // 结尾剩余不足 5 bit 的缓冲左移补齐后输出最后一位，保证 16 字符不缺失。
        if ($bufferBits > 0) {
            $index = ($buffer << (5 - $bufferBits)) & 0x1F;
            $randomPart .= $base32[$index];
        }

        // 10 位时间 + 16 位随机 = 26 位 ULID。
        return $timePart . $randomPart;
    }

    /**
     * 生成 UUID v4。
     *
     * @return string 36 位 UUID 字符串。
     */
    public static function uuid(): string
    {
        return (string) Str::uuid();
    }

}
