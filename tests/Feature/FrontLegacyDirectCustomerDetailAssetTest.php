<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 11:27
 */

/**
 * FrontLegacyDirectCustomerDetailAssetTest
 *
 * 文件功能：
 * - 验证直属客户详情外部脚本完整保留旧登录历史分页协议：Blade 委托外部资源承载行为，资产保留旧契约。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 旧前台直属客户详情静态资源闭环测试。
 *
 * 业务边界：
 * - Blade 只输出资料、登录历史容器和初始化数据，不允许承载可执行 JavaScript。
 * - 外部脚本继续使用旧接口的 page、rows 请求参数以及 rows、total 响应协议。
 *
 * 验证结果：
 * - 页面不会因移除内联脚本而丢失登录历史分页能力。
 * - 接口地址和 CSRF 令牌通过 HTML data 属性安全传递给外部脚本。
 */
class FrontLegacyDirectCustomerDetailAssetTest extends TestCase
{
    /**
     * 验证 Blade 仅声明外部脚本和登录历史初始化数据。
     *
     * @return void 断言 Blade 不含内联可执行脚本，并保留接口地址与 CSRF 数据属性。
     */
    public function test_blade_delegates_login_history_behavior_to_external_asset(): void
    {
        $blade = file_get_contents(resource_path('views/front/legacy/direct_customer_detail.blade.php')) ?: '';

        $this->assertStringContainsString('data-login-history-url=', $blade);
        $this->assertStringContainsString('data-csrf-token=', $blade);
        $this->assertStringContainsString("asset('js/apps/front/legacy/direct-customer-detail.js')", $blade);
        $this->assertStringNotContainsString('<script>', $blade);
    }

    /**
     * 验证外部脚本完整保留旧登录历史分页协议。
     *
     * @return void 断言脚本读取 data 属性，发送 page、rows，并消费 rows、total 后更新分页。
     */
    public function test_external_asset_preserves_legacy_login_history_contract(): void
    {
        $scriptPath = public_path('js/apps/front/legacy/direct-customer-detail.js');

        $this->assertFileExists($scriptPath);
        $script = is_file($scriptPath) ? (file_get_contents($scriptPath) ?: '') : '';

        $this->assertStringContainsString('dataset.loginHistoryUrl', $script);
        $this->assertStringContainsString('dataset.csrfToken', $script);
        $this->assertStringContainsString("'page=' + encodeURIComponent(currentPage)", $script);
        $this->assertStringContainsString("'&rows=' + encodeURIComponent(pageSize)", $script);
        $this->assertStringContainsString('payload.total', $script);
        $this->assertStringContainsString('payload.rows', $script);
    }
}
