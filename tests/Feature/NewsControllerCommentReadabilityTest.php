<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:38
 */

/**
 * NewsControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台 NewsController 对发布流程职责、请求参数、真实表字段保持可读中文注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 后台新闻公告控制器中文注释可读性测试。
 *
 * 功能说明：
 * - 该测试不执行业务接口，只读取 NewsController 源码。
 * - 目标是确保控制器职责、请求参数、真实表字段和旧英文标题都具备清晰中文逻辑说明。
 */
class NewsControllerCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    public function test_news_controller_keeps_chinese_logic_comments_for_publication_flow(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/NewsController.php')) ?: '';

        foreach ([
            '后台新闻公告控制器',
            'title 表示新闻公告标题',
            'content 表示新闻公告正文内容',
            'page 表示当前页码',
            'per_page 表示每页数量',
            'id 表示 news.id',
            'is_published 表示是否发布',
            'togglePublish 用于切换发布状态',
            '数据来源为 news 表',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'NewsController 缺少中文注释：' . $expectedComment);
        }

        foreach ([
            'News and Announcement Controller',
            'List all news',
            'Create news',
            'Update news',
            'Delete news',
            'Toggle publish status',
        ] as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'NewsController 不应保留旧英文标题注释：' . $legacyEnglishComment);
        }
    }
}
