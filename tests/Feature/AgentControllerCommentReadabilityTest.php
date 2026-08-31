<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 22:25
 */

/**
 * AgentControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 AgentController 源码无旧英文标题，且代理数据来源、筛选参数、层级关系、等级佣金字段与数据范围鉴权均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台代理控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 该测试只读取 AgentController 源码，不访问真实数据库。
 * - 目标是清理旧英文标题，并约束代理数据来源、筛选参数、层级关系、等级佣金字段和数据范围鉴权均有中文逻辑说明。
 */
class AgentControllerCommentReadabilityTest extends TestCase
{
    public function test_agent_controller_keeps_chinese_logic_comments_without_legacy_english_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AgentController.php')) ?: '';

        foreach ([
            '后台代理管理控制器',
            '数据来源为 user_infos 表',
            'account_type=1 表示代理账号',
            'agent_id 表示业务代理用户ID',
            'user_name 表示代理姓名筛选关键字',
            'AdminDataScopeService 用于限制不同管理员可查看的代理数据范围',
            'descendants 用于读取直接和间接下级代理及客户',
            'AgentDescendant 表记录代理层级关系',
            'level 表示代理等级',
            'comm_rate 表示代理佣金比例',
            'denyAgentAccessIfNeeded 用于按当前管理员数据范围判断是否允许访问指定代理',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'AgentController 缺少中文注释：' . $expectedComment);
        }

        foreach ([
            'Agent Management Controller',
            'List agents only',
            'Get agent detail with hierarchy info',
            'Get all direct/indirect sub-agents and customers',
            'Update agent level',
            'Update agent commission rate',
        ] as $legacyEnglishTitle) {
            $this->assertStringNotContainsString($legacyEnglishTitle, $source, 'AgentController 不应保留旧英文标题注释：' . $legacyEnglishTitle);
        }
    }
}
