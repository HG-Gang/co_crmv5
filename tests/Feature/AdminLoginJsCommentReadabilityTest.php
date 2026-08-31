<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminLoginJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台登录 JS 的中文逻辑注释保持可读，并禁止乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台登录 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 当前后台登录页是 Laravel Blade + Layui 页面，提交动作由 JS 拦截后请求 `/api/admin/login`。
 * - login.js 必须使用 CrmAjax.request，确保后台登录请求也进入全局 Ajax 半透明遮罩和统一 token 写入逻辑。
 * - 本测试约束脚本注释必须说明 email、password、remember 的参数含义，并保留语言切换说明。
 */
class AdminLoginJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 后台登录 JS 必须保留 Ajax 登录参数说明。
     *
     * @return void
     */
    public function test_admin_login_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('auth/login.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '后台登录 login.js 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("url: '/api/admin/login'", $script, '后台登录必须保留清晰可读的硬编码 API 地址。');
        $this->assertStringContainsString('CrmAjax.request({', $script, '后台登录必须使用 CrmAjax.request 进入全局 Ajax 遮罩。');
        $this->assertStringContainsString("CrmAjax.setToken('admin'", $script, '后台登录成功后必须写入 admin JWT Token。');
    }

    /**
     * 后台登录 JS 不应继续包含历史乱码注释。
     *
     * @return void
     */
    public function test_admin_login_js_does_not_contain_garbled_comment_fragments(): void
    {
        $script = $this->adminLayuiScript('auth/login.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '后台登录 login.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 后台登录脚本必须保留的中文逻辑注释片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '后台 Blade 登录页脚本',
            'email 表示管理员登录邮箱',
            'password 表示管理员登录密码',
            'remember 表示是否延长后台登录会话',
            '/api/admin/login',
            'CrmAjax 全局遮罩',
            '切换后台登录页语言',
            'CRM.switchLang',
        ];
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表。
     */
    private function garbledFragments(): array
    {
        return [
            '閸',
            '閻',
            '鐞',
            '缁',
            '閿',
            '鍚',
            '鐧',
        ];
    }
}
