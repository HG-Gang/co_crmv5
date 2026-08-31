<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminAdminsJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 admins 模块 Layui JS 的中文逻辑注释保持可读，并禁止乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台管理员账号 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `public/js/apps/admin/layui/admins/index.js` 是后台管理员账号管理页面的业务脚本。
 * - 该脚本负责管理员列表、账号新增、账号编辑、账号删除、编辑时密码留空不更新以及表格重载后的按钮权限刷新。
 * - 本测试只读取静态 JS 文件，约束中文逻辑注释和乱码黑名单，不连接数据库，也不调用真实后台接口。
 */
class AdminAdminsJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 管理员账号脚本必须说明账号安全边界、接口参数和按钮权限刷新逻辑。
     *
     * @return void
     */
    public function test_admins_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('admins/index.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '管理员账号 admins/index.js 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 管理员账号脚本不能继续保留历史乱码注释。
     *
     * @return void
     */
    public function test_admins_js_does_not_contain_garbled_comment_fragments(): void
    {
        $script = $this->adminLayuiScript('admins/index.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '管理员账号 admins/index.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖管理员账号页面的核心维护边界。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '管理员账号列表',
            '管理员账号属于高敏后台资源',
            'username 表示管理员登录名',
            'password 留空表示编辑时不修改旧密码',
            'id 为空表示新增管理员',
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
            'ç®',
            'è´',
            'æ‰',
            'é‡',
            '鍚',
            '绠',
            '閫',
            '�',
        ];
    }
}
