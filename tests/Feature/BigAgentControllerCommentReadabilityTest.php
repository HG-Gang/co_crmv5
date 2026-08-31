<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 22:18
 */

/**
 * BigAgentControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 BigAgentController 无旧英文标题，且大代理账号保存边界有中文逻辑短句说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台大代理控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 该测试只读取 BigAgentController 源码，不访问数据库。
 * - 目标是清理控制器旧英文标题，并补齐大代理账号保存边界的中文短句。
 */
class BigAgentControllerCommentReadabilityTest extends TestCase
{
    public function test_big_agent_controller_keeps_chinese_logic_comments_without_legacy_english_title(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/BigAgentController.php')) ?: '';

        foreach ([
            '后台大代理管理控制器',
            '数据来源为 big_agents 表',
            'id 表示 big_agents.id',
            'username 表示大代理登录名',
            'password 表示大代理登录密码',
            'password 留空表示编辑时保留原密码',
            'is_enabled 表示大代理账号是否启用',
            'status 是旧页面历史字段',
            'normalizePayload 用于规范化大代理保存字段',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'BigAgentController 缺少中文注释：' . $expectedComment);
        }

        $this->assertStringNotContainsString(
            'Big Agent Management Controller',
            $source,
            'BigAgentController 不应保留旧英文标题注释。'
        );
    }
}
