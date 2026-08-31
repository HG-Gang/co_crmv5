<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminCommonJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台公共 Layui JS 的中文逻辑注释保持可读，并禁止乱码中文注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台公共 Layui JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `public/js/apps/admin/layui/common.js` 是旧版后台 Layui 页面共用模块，负责路由生成、Token 管理、AJAX 封装和旧版 admin i18n 加载。
 * - 该文件会被登录页和后台页面复用，因此中文注释必须准确说明参数含义和登录过期边界，不能继续保留历史乱码。
 * - 本测试只读取静态 JS 文件，不连接数据库。
 */
class AdminCommonJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 后台公共 common.js 必须包含可读中文逻辑注释。
     *
     * @return void
     */
    public function test_admin_common_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('common.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '后台公共 common.js 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 后台公共 common.js 不允许继续出现典型中文乱码。
     *
     * @return void
     */
    public function test_admin_common_js_does_not_contain_garbled_chinese_comments(): void
    {
        $script = $this->adminLayuiScript('common.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '后台公共 common.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于约束公共模块核心逻辑说明。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '后台 Layui 公共模块',
            '通过 PHP 导出的 Laravel 路由名称生成 URL',
            'admin_token 是布局页 CrmAjax 使用的统一键名',
            '登录过期响应码会清理 Token 并跳回后台登录页',
            '按 data-translate 属性应用旧版后台语言包',
            '从 public/js/apps/admin/i18n 加载旧版后台语言包',
        ];
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，覆盖 UTF-8/GBK 错读后的典型字符。
     */
    private function garbledFragments(): array
    {
        return [
            '鍚',
            '妯',
            '閫',
            '璺',
            '缈',
            '绠',
            '鍒',
            '鑾',
            '�',
        ];
    }
}
