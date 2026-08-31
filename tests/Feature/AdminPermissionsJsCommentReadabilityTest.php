<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminPermissionsJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证权限管理 JS 的中文逻辑注释保持可读，并禁止乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台权限字典 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `public/js/apps/admin/layui/permissions/index.js` 是后台权限字典页面的树形预览脚本。
 * - 该页面读取 `permissions` 表中的后台权限树，用于核对菜单、按钮和接口权限配置是否存在。
 * - 本测试只检查静态 JS 注释和乱码黑名单，不连接数据库，也不执行真实权限树接口。
 */
class AdminPermissionsJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 权限字典脚本必须说明权限树来源、guard_type 参数和角色授权边界。
     *
     * @return void
     */
    public function test_permissions_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('permissions/index.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '权限字典 permissions/index.js 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 权限字典脚本不能继续保留历史乱码注释。
     *
     * @return void
     */
    public function test_permissions_js_does_not_contain_garbled_comment_fragments(): void
    {
        $script = $this->adminLayuiScript('permissions/index.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '权限字典 permissions/index.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖权限树来源、请求参数和授权边界。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '加载后台权限树',
            'guard_type 表示权限所属守卫',
            'permissions 表中的后台菜单、按钮和接口权限字典',
            '当前页面只做权限树预览',
            '角色授权在角色模块通过 assignPermissions 完成',
            'normalizeTree 将后端权限节点转换为 Layui tree 需要的字段',
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
            '鍔',
            '鍚',
            '鏉',
            '褰',
            'å½',
            'æ',
            '�',
        ];
    }
}
