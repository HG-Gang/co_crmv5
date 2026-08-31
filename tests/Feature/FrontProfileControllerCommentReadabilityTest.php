<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/08
 * Time: 23:41
 */

/**
 * FrontProfileControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 ProfileController 对资料读取/更新、改密、改邮箱、头像、实名、银行卡、换绑、销户校验、关系链和文件 URL 处理均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台个人资料控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 本测试只读取 Front\ProfileController 源码，不连接真实数据库。
 * - 约束资料读取/更新、改密、改邮箱、头像、实名、银行卡、换绑、销户校验、关系链和文件 URL 处理必须有中文逻辑说明。
 */
class FrontProfileControllerCommentReadabilityTest extends TestCase
{
    public function test_front_profile_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/ProfileController.php')) ?: '';

        $expectedComments = [
            '前台用户资料控制器',
            '处理资料读取、资料更新、密码修改、邮箱修改、头像上传、实名认证、银行卡认证、银行卡换绑、销户校验、关系链查询和旧前台资料接口兼容',
            'profileInfo 用于返回当前前台用户资料',
            'login 表示 user_logins 登录表资料',
            'info 表示 user_infos 业务资料',
            'auth 表示 user_auths 认证资料',
            'avatar_url 表示浏览器可直接访问的头像地址',
            'updateProfile 用于更新当前前台用户基础资料',
            'user_name 表示用户姓名',
            'phone 表示联系电话',
            'id_card_no 表示身份证号',
            'gender 表示性别',
            'address 表示联系地址',
            'changePassword 用于修改当前前台用户登录密码',
            'old_password 表示当前旧密码',
            'password_confirmation 表示新密码确认值',
            'user_editpsw_save 用于兼容旧前台修改密码入口',
            'olduserpsw 表示旧前台提交的旧密码',
            'newuserpsw 表示旧前台提交的新密码',
            'changeEmail 用于修改当前前台登录邮箱',
            'verify_phone 表示用于校验身份的手机号',
            'current_email 表示当前邮箱',
            'new_email 表示新邮箱',
            'uploadAvatar 用于上传当前前台用户头像',
            'avatar 表示新版头像上传文件字段',
            'submitIdentity 用于提交实名认证资料',
            'id_card_front 表示身份证正面图片',
            'id_card_back 表示身份证反面图片',
            'submitBankCard 用于提交银行卡认证资料',
            'bank_name 表示开户银行',
            'bank_no 表示银行卡号',
            'bank_card_back_img 表示银行卡反面图片',
            'submitBankChange 用于提交银行卡换绑资料',
            'bank_name_tmp 表示待审核的新开户银行',
            'bank_status=3 表示银行卡换绑待审核',
            'cancelVerifyInfo 用于校验销户前的手机号、邮箱和身份证号',
            'cancelVerifyPassSendCode 用于发送销户验证邮件验证码',
            'relationShip 用于返回代理关系链文本',
            'relationShipHtmlV2 用于兼容旧前台代理关系链 HTML 接口',
            'resolveAvatarUrl 用于统一头像浏览器 URL 规则',
            'storeProfileFile 用于保存资料认证文件',
            'legacyBankCardUpload 用于兼容旧前台银行卡上传入口',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front ProfileController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_profile_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/ProfileController.php')) ?: '';

        $legacyEnglishComments = [
            'Front User Profile Controller',
            'Handles profile information, updates, password/email changes, and avatar uploads.',
            'Get current user profile info',
            'Update user profile',
            'Change Password',
            'Change Email',
            'Upload Avatar',
            'Delete old avatar',
            'Resolve avatar browser URL',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front ProfileController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
