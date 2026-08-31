<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminDepositsJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证入金模块 Layui JS 的中文逻辑注释保持可读，并禁止乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台入金审核 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `public/js/apps/admin/layui/deposits/index.js` 是后台入金审核页面的业务脚本。
 * - 入金审核直接影响客户资金记录，因此搜索参数、审核按钮、记录主键和按钮权限刷新必须有可读中文说明。
 * - 本测试只检查静态 JS 注释和乱码黑名单，不连接数据库，也不调用真实入金审核接口。
 */
class AdminDepositsJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 入金审核脚本必须说明列表职责、搜索参数、审核动作、记录主键和按钮权限来源。
     *
     * @return void
     */
    public function test_deposits_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('deposits/index.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '入金审核 deposits/index.js 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 入金审核脚本不能继续保留历史乱码注释。
     *
     * @return void
     */
    public function test_deposits_js_does_not_contain_garbled_comment_fragments(): void
    {
        $script = $this->adminLayuiScript('deposits/index.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '入金审核 deposits/index.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖入金审核参数、动作和权限边界。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '入金审核列表',
            'user_id 表示入金所属用户',
            'status 表示入金审核状态',
            'amount 表示入金申请金额',
            'approve 表示审核通过入金记录',
            'reject 表示驳回入金记录',
            'id 表示入金记录主键',
            '重新应用按钮权限',
            'permissions.slug',
        ];
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，用于发现历史编码错乱的中文注释。
     */
    private function garbledFragments(): array
    {
        return [
            'å…',
            'é‡',
            'æ‰',
            'é”',
            '鍚',
            '绠',
            '閫',
            '�',
        ];
    }
}
