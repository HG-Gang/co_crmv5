<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 01:58
 */

/**
 * CommonUploadControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 CommonUploadController 移除英文注释标题、用中文说明上传参数，且上传成功消息使用统一 response 语言包。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 公共上传控制器中文注释与多语言响应测试。
 *
 * 功能逻辑说明：
 * - Common\UploadController 同时可被前台资源风格上传路由和后台通用上传接口复用。
 * - 上传字段、业务类型、MIME 白名单和保存目录必须有中文逻辑说明，避免前后台误传参数。
 * - 上传成功文案应优先使用统一 response 语言包，保持前后台 API 响应口径一致。
 */
class CommonUploadControllerCommentReadabilityTest extends TestCase
{
    /**
     * 公共上传控制器不应保留英文注释标题。
     *
     * @return void
     */
    public function test_common_upload_controller_removes_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Common/UploadController.php')) ?: '';

        foreach ([
            'General file upload endpoint',
            'Handle file upload',
            'Get allowed mime types based on upload type',
            'Store to storage/app/public',
            'Max 5MB',
        ] as $englishComment) {
            $this->assertStringNotContainsString($englishComment, $source);
        }
    }

    /**
     * 公共上传控制器必须说明核心参数和 MIME 白名单边界。
     *
     * @return void
     */
    public function test_common_upload_controller_documents_upload_parameters_in_chinese(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Common/UploadController.php')) ?: '';

        foreach ([
            'file 表示上传文件字段',
            'type 表示上传业务类型',
            'avatar 表示头像上传',
            'id_card 表示身份证上传',
            'bank_card 表示银行卡上传',
            'voucher 表示凭证上传',
            'general 表示通用附件上传',
            '$allowedMimes 表示当前业务类型允许的扩展名白名单',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source);
        }
    }

    /**
     * 上传成功消息必须使用统一 response 语言包。
     *
     * @return void
     */
    public function test_common_upload_controller_uses_response_uploaded_message(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Common/UploadController.php')) ?: '';

        $this->assertStringContainsString("__('response.uploaded')", $source);
        $this->assertStringNotContainsString("__('messages.upload_success')", $source);
    }
}
