<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/06
 * Time: 22:20
 */

/**
 * AdminBladeUiTest
 *
 * 文件功能：
 * - 验证后台 Blade UI 回归：后台页面由 Laravel Blade 模板渲染，外壳包含现代管理台结构且中文文案 UTF-8 可读。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 Blade UI 回归测试。
 *
 * 测试目标：
 * - 后台页面必须由 Laravel Blade 模板直接渲染。
 * - 后台外壳必须包含现代管理台结构，作为参考 Vben/Naive/Ant/Arco 后的 Blade 实现基础。
 * - 外壳中的中文文案必须保持 UTF-8 可读，不能继续输出乱码。
 */
class AdminBladeUiTest extends TestCase
{
    /**
     * 后台控制台必须渲染现代 Blade 工作台外壳。
     *
     * @return void
     */
    public function test_admin_dashboard_renders_modern_blade_shell(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('crm-admin-workbench', false);
        $response->assertSee('crm-admin-topbar', false);
        $response->assertSee('后台工作台', false);
        $response->assertSee('data-render-mode="blade"', false);
    }
}
