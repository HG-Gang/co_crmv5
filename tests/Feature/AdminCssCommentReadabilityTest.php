<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 20:22
 */

/**
 * AdminCssCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台公共 CSS 保持可读中文逻辑注释，并禁止 UTF-8/GBK 错误解码产生的乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台公共 CSS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 用户要求本次涉及的所有文件必须使用可读中文说明功能、逻辑、参数含义和作用。
 * - `public/css/admin/style.css` 是后台 Blade 页面共享的视觉外壳文件，注释需要解释布局容器、
 *   Layui 卡片、表单、表格和弹窗等公共组件的设计意图。
 * - 本测试只约束中文注释可读性，不约束颜色、间距、布局等视觉行为，避免把样式实现细节写死。
 */
class AdminCssCommentReadabilityTest extends TestCase
{
    /**
     * 后台公共 CSS 必须保留可读中文逻辑注释。
     *
     * 参数含义：
     * - $content：后台公共 CSS 文件内容，用于断言关键共享组件说明是否仍为可读中文。
     * - $requiredComments：必须存在的中文说明片段，覆盖后台页面主容器和 Layui 核心组件。
     *
     * @return void
     */
    public function test_admin_common_css_contains_readable_chinese_logic_comments(): void
    {
        $content = file_get_contents(public_path('css/admin/style.css'));

        $requiredComments = [
            '.crm-admin-main：后台业务页面内容容器',
            'Layui 卡片：用于列表筛选区、表格区和详情区',
            'Layui 表单：统一输入框、选择框、日期框',
            'Layui 表格：后台核心列表组件',
            'Layui 弹窗：用于新增、编辑、确认等后台操作',
        ];

        foreach ($requiredComments as $comment) {
            $this->assertStringContainsString($comment, $content);
        }
    }

    /**
     * 后台公共 CSS 不允许继续出现典型中文乱码片段。
     *
     * 参数含义：
     * - $content：后台公共 CSS 文件内容，用于查找 UTF-8/GBK 错误解码后常见的乱码片段。
     * - $mojibakeFragments：常见乱码特征集合，用于快速发现中文注释被错误编码写入。
     *
     * @return void
     */
    public function test_admin_common_css_does_not_contain_mojibake_comment_fragments(): void
    {
        $content = file_get_contents(public_path('css/admin/style.css'));

        $mojibakeFragments = [
            '鐨',
            '鏉',
            '閺',
            '闁',
            '缁',
            '缂',
            '锛',
            '鍚',
            '鍙',
            '�',
        ];

        foreach ($mojibakeFragments as $fragment) {
            $this->assertStringNotContainsString($fragment, $content);
        }
    }
}
