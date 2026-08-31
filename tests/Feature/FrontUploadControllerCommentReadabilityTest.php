<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 00:13
 */

/**
 * FrontUploadControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 UploadController 对新前台上传、旧单文件/多文件上传和 legacy 返回字段均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台上传控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\UploadController 源码，不连接真实数据库，也不写入真实上传文件。
 * - 约束新前台上传、旧前台单文件上传、旧前台多文件上传和 legacy 返回字段必须具备中文逻辑说明。
 */
class FrontUploadControllerCommentReadabilityTest extends TestCase
{
    public function test_front_upload_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/UploadController.php')) ?: '';

        $expectedComments = [
            '前台上传控制器',
            '处理头像、身份证、银行卡、凭证和旧前台兼容上传入口',
            'upload 用于处理新前台通用图片上传',
            'file 表示上传文件字段',
            'type 表示上传业务类型',
            'avatar 表示头像上传目录',
            'id_card 表示身份证上传目录',
            'bank_card 表示银行卡上传目录',
            'path 表示 public 磁盘中的相对路径',
            'url 表示浏览器可访问的文件地址',
            'singleFileUpload 用于兼容旧前台单文件上传入口',
            'file 可能是单个 UploadedFile，也可能是旧表单提交的文件数组',
            'code=200 表示旧前台上传成功',
            'code=500 表示旧前台上传失败',
            'multipleFileUpload 用于兼容旧前台多文件上传入口',
            'files 表示旧前台 file 字段上传的文件集合',
            'storeLegacyUpload 用于保存旧前台上传文件并生成旧响应字段',
            'directory 表示旧前台文件保存目录',
            'userId 表示当前前台登录用户 ID',
            'name 表示最终保存的文件名',
            'legacyPath 表示写入 public 磁盘的相对路径',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front UploadController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_upload_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/UploadController.php')) ?: '';

        $legacyEnglishComments = [
            'Generic Upload Controller',
            'Handles generic file uploads for avatars, ID cards, and bank cards.',
            'Generic upload method',
            'Define storage path: storage/app/public/{type}/',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front UploadController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
