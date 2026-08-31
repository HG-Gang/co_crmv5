<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:42
 */

/**
 * MenuServiceCommentReadabilityTest
 *
 * 文件功能：
 * - 验证菜单服务保持可读中文逻辑注释，禁止占位符或乱码注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 菜单服务中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `MenuService` 是后台管理员、前台代理商和普通客户菜单树的统一生成入口。
 * - 该服务同时承担权限过滤、父级菜单保留、多语言标题回填和树形结构转换职责。
 * - 本测试只约束源码注释可读性，不改变菜单查询、权限判断和接口返回结构。
 */
class MenuServiceCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * 菜单服务必须保留可读中文职责和参数说明。
     *
     * 参数含义：
     * - $content：菜单服务源码内容，用于确认核心业务注释是否仍为可读中文。
     * - $requiredTexts：必须存在的中文说明片段，覆盖服务职责、参数含义和关键过滤逻辑。
     *
     * @return void
     */
    public function test_menu_service_contains_readable_chinese_logic_comments(): void
    {
        $content = file_get_contents(app_path('Services/MenuService.php')) ?: '';

        $requiredTexts = [
            '菜单服务。',
            '统一从 permissions 表读取前台和后台菜单',
            '$guardType 表示守卫类型',
            '$permissionIds 表示当前角色拥有的 permissions.id 列表',
            '父级菜单通常只是分组容器',
            '$menus 表示菜单 Eloquent 集合',
            '$locale 表示当前语言标识',
            '菜单来源是 permissions 表',
            'translation_key',
            'breadcrumb_key',
        ];

        foreach ($requiredTexts as $text) {
            $this->assertStringContainsString($text, $content, 'MenuService.php 缺少中文逻辑注释：' . $text);
        }
    }

    /**
     * 菜单服务不能出现英文占位标题或典型中文乱码片段。
     *
     * 参数含义：
     * - $content：菜单服务源码内容，用于扫描旧英文占位和 UTF-8/GBK 错解乱码。
     * - $fragment：单个禁止片段，命中时说明注释仍不符合中文可读要求。
     *
     * @return void
     */
    public function test_menu_service_does_not_contain_placeholder_or_mojibake_comments(): void
    {
        $content = file_get_contents(app_path('Services/MenuService.php')) ?: '';

        foreach ($this->forbiddenFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $content, 'MenuService.php 存在不可读或占位注释片段：' . $fragment);
        }
    }

    /**
     * 禁止出现在菜单服务注释里的片段。
     *
     * @return array<int, string> 禁止片段列表。
     */
    private function forbiddenFragments(): array
    {
        return [
            'Menu Service',
            'Function',
            'Parameter',
            'Returns',
            'Table Name',
            'Relation:',
            '閻',
            '閺',
            '闁',
            '濞',
            '缁',
            '缂',
            '濠',
            '閿',
            '閸',
            '锟',
        ];
    }
}
