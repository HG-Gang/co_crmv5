<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminMenuJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台菜单管理脚本的中文参数注释保持可读，并禁止乱码中文注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台菜单管理 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 后台菜单管理页维护的是 `permissions` 表中的菜单权限字典，是前后台菜单可控方案的关键入口。
 * - JS 弹窗会把树节点数据回填到表单，因此必须用可读中文说明 `guard_type`、`route`、`icon`、`name` 等参数的含义。
 * - 本测试只读取静态 JS 文件，不连接数据库。
 */
class AdminMenuJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 菜单管理 JS 必须包含可读的中文参数逻辑注释。
     *
     * @return void
     */
    public function test_menu_management_script_keeps_readable_chinese_parameter_comments(): void
    {
        $script = $this->adminLayuiScript('menus/index.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '菜单管理 JS 缺少中文参数注释：' . $fragment);
        }
    }

    /**
     * 菜单管理 JS 不允许继续出现典型中文乱码注释。
     *
     * @return void
     */
    public function test_menu_management_script_does_not_contain_garbled_chinese_comments(): void
    {
        $script = $this->adminLayuiScript('menus/index.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '菜单管理 JS 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于约束菜单弹窗参数说明。
     */
    private function requiredCommentFragments(): array
    {
        return [
            'tree 节点来自 permissions 表',
            '弹窗表单只暴露常用菜单字段',
            'route、icon、name',
            'guard_type 用于区分 admin/front 菜单命名空间',
        ];
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，覆盖当前菜单管理 JS 中已出现过的编码错读字符。
     */
    private function garbledFragments(): array
    {
        return [
            '鑺',
            '鏉',
            '寮圭獥',
            '鍙',
            '鍥',
            '鐨',
            '鍐',
            '銆',
            '�',
        ];
    }
}
