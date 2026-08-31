<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 23:34
 */

namespace Tests\Unit;

use App\Support\RequestTrace;
use PHPUnit\Framework\TestCase;

/**
 * 请求链路标识生成器单元测试。
 *
 * 文件功能：
 * - 验证 ULID 符合官方规范：26 字符、Crockford Base32 字符集、时间前缀递增。
 * - 验证 UUID v4 格式与版本位。
 *
 * 适用场景：
 * - 任何改动 app/Support/RequestTrace.php 后回归。
 *
 * 入参例子：无（直接调用静态方法）。
 *
 * 返回值：断言通过即表示标识生成契约成立。
 *
 * 异常或失败场景：
 * - ULID 长度/字符集/时间排序任一不满足时失败。
 */
final class RequestTraceTest extends TestCase
{
    /**
     * ULID 必须为 26 位且仅含 Crockford Base32 合法字符（排除 I/L/O/U）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_ulid_has_valid_length_and_crockford_charset(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $ulid = RequestTrace::ulid();
            $this->assertSame(26, strlen($ulid), 'ULID 长度必须为 26');
            $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid, 'ULID 必须为 Crockford Base32 字符集');
        }
    }

    /**
     * ULID 时间前缀（前 10 字符）必须随时间单调不减。
     *
     * @return void 断言通过不返回值。
     */
    public function test_ulid_time_prefix_is_monotonic(): void
    {
        $first = substr(RequestTrace::ulid(), 0, 10);
        usleep(2000);
        $second = substr(RequestTrace::ulid(), 0, 10);
        // 同一毫秒内时间前缀允许相等；跨毫秒必须严格递增。
        $this->assertGreaterThanOrEqual(0, strcmp($second, $first), 'ULID 时间前缀必须单调不减');
    }

    /**
     * ULID 生成必须唯一（抽样 500 个无重复）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_ulid_is_unique(): void
    {
        $set = [];
        for ($i = 0; $i < 500; $i++) {
            $set[RequestTrace::ulid()] = true;
        }
        $this->assertCount(500, $set, '500 个 ULID 必须无重复');
    }

    /**
     * UUID v4 必须为 36 位标准格式且版本位为 4。
     *
     * @return void 断言通过不返回值。
     */
    public function test_uuid_v4_format(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $uuid = RequestTrace::uuid();
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $uuid,
                'UUID 必须为 v4 格式（版本位 4、变体位 8/9/a/b）'
            );
        }
    }
}
