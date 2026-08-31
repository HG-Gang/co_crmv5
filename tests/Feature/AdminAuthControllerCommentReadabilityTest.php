<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * AdminAuthControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证后台认证控制器源码保持可读中文逻辑注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

/**
 * 后台认证控制器中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `App\Http\Controllers\Admin\AuthController` 负责后台管理员登录、登出、资料读取、资料更新、改密、头像上传和 Token 刷新。
 * - 该控制器是后台鉴权入口，注释必须解释请求参数、JWT 载荷、登录日志、Token 失效和白名单接口边界。
 * - 本测试只检查源代码注释和接口名可读性，不连接真实数据库，也不执行真实登录。
 *
 * 方法功能：
 * - test_admin_auth_controller_keeps_readable_chinese_logic_comments：断言控制器保留中文逻辑注释、admin guard 载荷、登录日志与多语言消息。
 *
 * 返回值：
 * - 断言通过返回 void；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若控制器丢失中文注释、出现英文硬编码消息或含乱码片段，测试断言失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class AdminAuthControllerCommentReadabilityTest extends TestCase
{
    /**
     * AuthController 必须保留覆盖后台认证入口的中文逻辑注释和参数说明。
     *
     * @return void
     */
    public function test_admin_auth_controller_keeps_readable_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AuthController.php')) ?: '';

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $source, 'AuthController 缺少中文逻辑注释：' . $fragment);
        }

        $this->assertStringContainsString("'guard' => 'admin'", $source, '后台 JWT 载荷必须明确使用 admin guard。');
        $this->assertStringContainsString("AdminLoginLog::create", $source, '后台登录成功后必须保留登录日志写入。');
        $this->assertStringContainsString("__('response.old_password_wrong')", $source, '旧密码错误必须使用后端多语言语言包。');
        $this->assertStringNotContainsString("'Old password incorrect'", $source, '旧密码错误不能保留英文硬编码消息。');
        $this->assertDoesNotContainGarbledFragments($source, 'AuthController.php');
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖登录、资料、改密、头像和 Token 刷新参数边界。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '后台管理员认证控制器',
            'username 表示后台管理员登录名',
            'password 表示后台管理员登录密码',
            'sub 表示 admins.id',
            'guard 固定为 admin',
            'AdminLoginLog 记录登录审计信息',
            'jwt_token 表示当前请求解析出的后台 JWT',
            'profileInfo 返回当前登录管理员资料',
            'email 表示管理员邮箱',
            'mobile 表示管理员手机号',
            'old_password 表示当前旧密码',
            'password_confirmation 表示新密码确认值',
            '修改密码成功后使当前 Token 失效',
            'avatar 表示上传的管理员头像文件',
            'refreshToken 使用当前有效 Token 换取新 Token',
            'admin_api_login',
            'admin_api_refreshToken',
            'check.permission:admin',
        ];
    }

    /**
     * 断言目标文本不包含常见乱码片段。
     *
     * @param string $content 被检查的文件内容。
     * @param string $label 错误消息中的文件标签，用于快速定位失败文件。
     * @return void
     */
    private function assertDoesNotContainGarbledFragments(string $content, string $label): void
    {
        foreach ($this->garbledFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $content, $label . ' 仍包含乱码片段：' . $fragment);
        }
    }

    /**
     * 常见乱码片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，用于发现历史编码错乱的中文注释。
     */
    private function garbledFragments(): array
    {
        return [
            '�',
            'å…',
            'é‡',
            'æ‰',
            'é”',
            '鍚',
            '绠',
            '閫',
            '鐢',
            '鏉',
            '鑿',
            '娉',
            '杩',
        ];
    }
}
