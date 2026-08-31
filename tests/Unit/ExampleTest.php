<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:10
 */

/**
 * Laravel 默认示例单元测试。
 *
 * 文件功能：
 * - 校验 PHPUnit 基础断言链路可用（框架自带的冒烟测试）。
 *
 * 适用场景：
 * - 新环境首次跑单测时验证测试基础设施；可随时删除。
 *
 * 入参例子：无。
 *
 * 返回值：断言通过表示测试环境可正常运行。
 *
 * 异常或失败场景：
 * - PHPUnit 配置或环境异常时失败。
 */
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * 基础示例断言：恒真断言。
     *
     * @return void 断言通过不返回值。
     */
    public function test_example()
    {
        $this->assertTrue(true);
    }
}
