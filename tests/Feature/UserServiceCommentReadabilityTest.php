<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:38
 */

/**
 * UserServiceCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 UserService 移除纯英文注释片段，并对关键参数和字段的中文业务含义进行说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 用户服务中文逻辑注释可读性测试。
 *
 * 功能逻辑说明：
 * - UserService 是后台用户详情、资料更新、状态启停和注销兼容服务。
 * - 即使部分控制器当前直接操作模型，该服务仍必须保留清晰中文注释，避免后续迁移时误用字段。
 * - user_id、is_enabled、auth_status、is_cancelled 等参数必须说明真实数据表含义。
 */
class UserServiceCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * UserService 不应继续保留英文功能注释。
     *
     * @return void
     */
    public function test_user_service_removes_english_only_comment_fragments(): void
    {
        $source = file_get_contents(app_path('Services/UserService.php')) ?: '';

        foreach ([
            'Get full user details',
            'Update user information fields',
            'Update user status',
            'Soft delete a user',
        ] as $englishFragment) {
            $this->assertStringNotContainsString($englishFragment, $source);
        }
    }

    /**
     * UserService 必须说明关键参数和字段的中文业务含义。
     *
     * @return void
     */
    public function test_user_service_documents_core_parameter_meaning_in_chinese(): void
    {
        $source = file_get_contents(app_path('Services/UserService.php')) ?: '';

        foreach ([
            '$userId 表示业务用户 ID',
            '$data 表示允许写入的用户资料字段集合',
            'is_enabled 表示 user_logins.is_enabled',
            'auth_status 表示实名认证状态',
            'is_cancelled 表示用户注销标记',
            'UserLogin 保存登录账号、密码和登录启停状态',
            'UserInfo 保存用户基础资料',
            'UserAuth 保存实名认证资料',
        ] as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source);
        }
    }
}
