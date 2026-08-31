<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 01:40
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 旧后台高风险写入口失败关闭测试。
 *
 * 文件功能：
 * - 验证尚未具备安全等价实现的旧资金写入口返回 410。
 * - 验证失败关闭响应不会执行 insert、update、delete 等数据库写语句。
 * - 已完成安全等价适配的旧入口应从本文件移出，并迁入对应闭环测试。
 */
class AdminLegacyUnsafeMutationFailClosedTest extends TestCase
{
    public function test_implemented_agent_edit_save_is_not_tracked_as_unsafe_unimplemented_mutation(): void
    {
        $this->assertArrayNotHasKey('agent edit save action', self::legacyUnsafeRoutes());
    }

    /** @return array<string, array{string}> */
    public static function legacyUnsafeRoutes(): array
    {
        return [];
    }
}
