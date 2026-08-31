<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:10
 */

declare(strict_types=1);

/**
 * MySQL 表指纹单元测试。
 *
 * 文件功能：
 * - 校验 MySqlTableFingerprint::digestRows 对相同逻辑行（列序无关）生成稳定摘要，对内容变化生成不同摘要。
 *
 * 适用场景：
 * - 改动夹具表行摘要算法后回归。
 *
 * 入参例子：
 * - digestRows([['id' => 1, 'status' => 'pending', 'amount' => '25.00'], ...])。
 *
 * 返回值：断言通过表示行摘要稳定性与敏感性契约成立。
 *
 * 异常或失败场景：
 * - 相同行摘要不一致或内容变化后摘要未变化时失败。
 */
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\MySqlTableFingerprint;

final class MySqlTableFingerprintTest extends TestCase
{
    /**
     * 校验相同逻辑行的摘要稳定、内容变化后摘要不同。
     *
     * @return void 断言通过不返回值。
     */
    public function test_row_digest_is_stable_for_the_same_logical_rows(): void
    {
        $first = [
            (object) ['id' => 1, 'status' => 'pending', 'amount' => '25.00'],
            (object) ['id' => 2, 'status' => 'completed', 'amount' => '30.00'],
        ];
        $same = [
            ['amount' => '25.00', 'status' => 'pending', 'id' => 1],
            ['status' => 'completed', 'id' => 2, 'amount' => '30.00'],
        ];
        $changed = [
            ['id' => 1, 'status' => 'pending', 'amount' => '25.00'],
            ['id' => 2, 'status' => 'rejected', 'amount' => '30.00'],
        ];

        $this->assertSame(
            MySqlTableFingerprint::digestRows($first),
            MySqlTableFingerprint::digestRows($same)
        );
        $this->assertNotSame(
            MySqlTableFingerprint::digestRows($first),
            MySqlTableFingerprint::digestRows($changed)
        );
    }
}
