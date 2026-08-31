<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 00:34
 */

/**
 * FrontBaseControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台基础控制器对统一响应、多语言消息 key、JWT user guard、旧 session 用户和认证错误边界均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台基础控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 只读取 FrontBaseController 源码，不连接真实数据库。
 * - 约束统一响应、多语言消息 key、JWT user guard、旧 session 用户和认证错误边界必须具备中文逻辑说明。
 */
class FrontBaseControllerCommentReadabilityTest extends TestCase
{
    public function test_front_base_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/FrontBaseController.php')) ?: '';

        $expectedComments = [
            '前台基础控制器',
            '所有前台控制器继承此类，统一复用 ApiResponse 的 success 和 error 响应结构',
            'ApiResponse 会把 response.*、auth.* 等消息 key 交给 Laravel 多语言包翻译',
            'legacyFrontUserId 用于兼容新 JWT 登录态和旧前台 session 登录态',
            'request 表示当前 HTTP 请求对象',
            'userLogin 表示 jwt.auth:user 解析出的 user guard 登录记录',
            'sessionUser 表示旧前台写入 session 的 suser 数据',
            'user_id 表示业务用户 ID',
            'legacyFrontUserLogin 用于返回当前前台登录记录',
            'legacyFrontUserInfo 用于返回当前前台业务用户资料',
            'userInfo 表示 UserLogin 关联的业务用户资料',
            'legacyFrontAuthError 用于返回旧前台兼容认证错误',
            'USER_NOT_FOUND 表示已识别用户 ID 但缺少业务资料',
            'AUTH_FAILED 表示无法识别任何前台登录用户',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'FrontBaseController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_base_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/FrontBaseController.php')) ?: '';

        $legacyEnglishComments = [
            'Front Base Controller',
            'All front controllers extend this class',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'FrontBaseController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
