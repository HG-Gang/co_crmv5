<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 15:35
 */

/**
 * AdminDataScopeJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证数据范围 Layui 脚本的中文逻辑注释保持可读，并禁止乱码中文注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台数据范围 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 数据范围页面是后台权限体系中较复杂的页面，JS 内的注释需要解释 Layui 表格刷新、角色数据范围和管理员代理绑定的参数含义。
 * - 本测试只读取静态 JS 文件，不连接数据库，确保中文逻辑注释保持 UTF-8 可读，避免再次出现乱码片段。
 */
class AdminDataScopeJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 数据范围 JS 必须保留关键中文逻辑注释。
     *
     * @return void
     */
    public function test_data_scope_layui_script_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('data-scopes/index.js');

        foreach ($this->requiredReadableCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '数据范围 JS 缺少关键中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 数据范围 JS 不允许继续出现常见中文编码乱码。
     *
     * @return void
     */
    public function test_data_scope_layui_script_does_not_contain_garbled_chinese_comments(): void
    {
        $script = $this->adminLayuiScript('data-scopes/index.js');

        foreach ($this->garbledChineseFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '数据范围 JS 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于证明参数和权限逻辑仍有中文说明。
     */
    private function requiredReadableCommentFragments(): array
    {
        return [
            '表格列名、状态徽标和弹窗标题都从运行时语言包读取',
            'Layui 表格重载会重新生成操作列按钮',
            '打开角色数据范围弹窗',
            'row.data_scope 是后端 role_data_scopes 关联配置',
            '打开管理员代理绑定弹窗',
            '新增时传入空字段对象',
            'role_data_scopes.scope_type',
        ];
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，覆盖替换字符和 GBK/UTF-8 错读后的典型字符。
     */
    private function garbledChineseFragments(): array
    {
        return [
            '�',
            '锛',
            '鍚',
            '绠',
            '瑙',
            '鏁',
            '鐢',
            '瀛',
            '后�',
            '锟',
        ];
    }
}
