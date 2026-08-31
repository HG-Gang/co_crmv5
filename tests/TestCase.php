<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:12
 */

/**
 * 项目测试基类：所有功能/集成测试的统一入口。
 *
 * 文件功能：
 * - 继承 Illuminate\Foundation\Testing\TestCase，并通过 CreatesApplication
 *   提供 Laravel 应用启动能力，供各测试用例继承。
 *
 * 适用场景：
 * - 新增测试用例都应继承本类，保证应用引导、数据库与配置环境一致。
 *
 * 入参例子：
 * - 无（本文件不含测试方法，仅作为基类被继承）。
 *
 * 返回值：
 * - 无；子类中的断言通过即表示测试闭环。
 *
 * 失败场景：
 * - 本文件不直接产生断言；子类断言失败由 PHPUnit 报告，说明被测行为与预期不符。
 */

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
