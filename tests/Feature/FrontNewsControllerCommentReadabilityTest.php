<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 00:24
 */

/**
 * FrontNewsControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 NewsController 对新闻列表、旧前台列表、详情 HTML、多语言标题回退和筛选参数均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台新闻公告控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 只读取 Front\NewsController 源码，不连接真实数据库，不依赖 news/news_langs 真实数据。
 * - 约束新闻公告列表、旧前台列表、详情 HTML、多语言标题内容回退和筛选参数必须具备中文逻辑说明。
 */
class FrontNewsControllerCommentReadabilityTest extends TestCase
{
    public function test_front_news_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/NewsController.php')) ?: '';

        $expectedComments = [
            '前台新闻公告控制器',
            '处理前台新闻公告列表、旧前台新闻搜索、旧前台新闻详情 HTML 和 news_langs 多语言回退',
            'newsList 用于返回新前台新闻公告分页数据',
            'page 表示当前页码',
            'per_page 表示每页新闻数量',
            'X-Locale 表示前端当前语言',
            'title 表示新闻标题筛选关键字',
            'translatedIds 表示 news_langs 中命中当前语言标题的新闻 ID 集合',
            'author_name 表示按作者名称模糊筛选',
            'paginator 表示 Laravel 分页对象',
            'news_langs 记录存在时优先使用翻译标题和翻译内容',
            'newsListSearch 用于兼容旧前台新闻列表搜索接口',
            'rows 表示旧前台表格数据行',
            'total 表示旧前台分页总数',
            'newsDetail 用于渲染旧前台新闻详情 HTML',
            'newsId 表示新闻主表 news.id',
            'Schema::hasTable 用于兼容缺少 news_langs 表的部署环境',
            'crm-legacy-news 表示旧前台详情页 HTML 容器',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front NewsController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_news_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/NewsController.php')) ?: '';

        $legacyEnglishComments = [
            'Front News Controller',
            'Paginated published news list.',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front NewsController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
