<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

/**
 * AdminAgentLevelsJsCommentReadabilityTest
 *
 * 文件功能：
 * - 验证代理等级模块 Layui JS 的中文逻辑注释保持可读，并禁止乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

/**
 * 后台代理等级 JS 中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `public/js/apps/admin/layui/agent-levels/index.js` 是后台代理等级配置页面的业务脚本。
 * - 该脚本维护代理等级、最大佣金、最小佣金和客户佣金等基础配置，直接影响代理体系与返佣计算。
 * - 本测试只检查静态 JS 注释和乱码黑名单，不连接数据库，也不调用真实代理等级接口。
 *
 * 方法功能：
 * - test_agent_levels_js_keeps_readable_chinese_logic_comments：断言脚本保留指定中文逻辑注释片段。
 * - test_agent_levels_js_does_not_contain_garbled_comment_fragments：断言脚本不含乱码片段。
 *
 * 返回值：
 * - 断言通过返回 void；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若脚本丢失中文注释或残留历史乱码，测试断言失败。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

class AdminAgentLevelsJsCommentReadabilityTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 代理等级脚本必须说明配置来源、字段含义、接口分支和按钮权限刷新。
     *
     * @return void
     */
    public function test_agent_levels_js_keeps_readable_chinese_logic_comments(): void
    {
        $script = $this->adminLayuiScript('agent-levels/index.js');

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $script, '代理等级 agent-levels/index.js 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 代理等级脚本不能继续保留历史乱码注释。
     *
     * @return void
     */
    public function test_agent_levels_js_does_not_contain_garbled_comment_fragments(): void
    {
        $script = $this->adminLayuiScript('agent-levels/index.js');

        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $script, '代理等级 agent-levels/index.js 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖代理等级配置字段和权限边界。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '代理等级参数由后端模型定义',
            'level_code 表示等级编码',
            'max_commission 表示该等级允许的最大佣金比例',
            'min_commission 表示该等级允许的最小佣金比例',
            'user_commission 表示客户侧佣金比例',
            'id 为空表示新增代理等级',
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
            'ä»',
            'ç­',
            'æ‰',
            'é‡',
            '鍚',
            '绠',
            '閫',
            '�',
        ];
    }
}
