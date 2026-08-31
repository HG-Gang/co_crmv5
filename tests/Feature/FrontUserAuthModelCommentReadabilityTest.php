<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:35
 */

/**
 * FrontUserAuthModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台用户认证相关模型保持可读中文注释，禁止乱码或英文占位片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台用户认证与资料模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - `UserLogin` 保存前台登录账号、密码、角色绑定、JWT 标识和账号启停状态。
 * - `UserInfo` 保存前台业务用户资料、代理层级、资金字段和 MT4 同步状态。
 * - `User` 是 Laravel 默认用户模型，仍需说明旧兼容边界和角色权限判断。
 * - `UserLoginLog` 保存前台登录审计记录。
 * - 本测试只读取源码，不连接数据库，用于避免核心用户模型继续保留乱码或英文占位注释。
 */
class FrontUserAuthModelCommentReadabilityTest extends TestCase
{
    /**
     * 前台用户认证相关模型必须包含可读中文职责、字段和参数说明。
     *
     * 参数与变量含义：
     * - $userLoginModel：`UserLogin` 模型源码内容。
     * - $userInfoModel：`UserInfo` 模型源码内容。
     * - $userModel：`User` 模型源码内容。
     * - $userLoginLogModel：`UserLoginLog` 模型源码内容。
     * - $combined：四个模型源码拼接结果，用于统一检查乱码片段和英文占位注释。
     * - $fragment：必须存在的中文说明片段。
     *
     * @return void
     */
    public function test_front_user_auth_models_contain_readable_chinese_logic_comments(): void
    {
        $userLoginModel = file_get_contents(app_path('Models/UserLogin.php')) ?: '';
        $userInfoModel = file_get_contents(app_path('Models/UserInfo.php')) ?: '';
        $userModel = file_get_contents(app_path('Models/User.php')) ?: '';
        $userLoginLogModel = file_get_contents(app_path('Models/UserLoginLog.php')) ?: '';
        $combined = $userLoginModel . $userInfoModel . $userModel . $userLoginLogModel;

        foreach ($this->requiredUserLoginFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $userLoginModel, 'UserLogin 模型缺少中文说明：' . $fragment);
        }

        foreach ($this->requiredUserInfoFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $userInfoModel, 'UserInfo 模型缺少中文说明：' . $fragment);
        }

        foreach ($this->requiredUserFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $userModel, 'User 模型缺少中文说明：' . $fragment);
        }

        foreach ($this->requiredUserLoginLogFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $userLoginLogModel, 'UserLoginLog 模型缺少中文说明：' . $fragment);
        }

        foreach ($this->forbiddenFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $combined, '前台用户模型仍存在乱码或英文占位片段：' . $fragment);
        }
    }

    /**
     * UserLogin 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredUserLoginFragments(): array
    {
        return [
            '用户登录模型',
            'user_logins 表保存前台登录账号、密码哈希、角色绑定和登录状态',
            'role_id 表示绑定的 roles.id',
            'jwt_token_id 表示当前有效 JWT 的唯一编号',
            'account_type 表示账号类型',
            'is_enabled 表示账号是否启用',
            'is_cancelled 表示账号是否注销',
            '关联前台角色',
            '关联用户资料',
            'isActive',
        ];
    }

    /**
     * UserInfo 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredUserInfoFragments(): array
    {
        return [
            '用户业务资料模型',
            'user_infos 表保存前台业务用户资料、代理层级、资金字段和 MT4 状态',
            'user_id 表示业务用户 ID',
            'login_id 表示 user_logins.id',
            'parent_id 表示上级代理业务用户 ID',
            'family_tree 表示代理家谱链',
            'account_type 表示账号类型',
            'auth_status 表示实名认证状态',
            'getAncestorIds',
            'isAgent',
            'isCustomer',
        ];
    }

    /**
     * User 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredUserFragments(): array
    {
        return [
            'Laravel 默认前台用户兼容模型',
            '当前业务登录主体优先使用 UserLogin',
            'role_id 表示绑定的 roles.id',
            '$slug 表示 permissions.slug',
            '委托给 Role::hasPermission',
        ];
    }

    /**
     * UserLoginLog 模型必须保留的中文说明片段。
     *
     * @return array<int, string> 注释片段列表。
     */
    private function requiredUserLoginLogFragments(): array
    {
        return [
            '前台用户登录日志模型',
            'user_login_logs 表记录前台用户登录审计信息',
            'login_id 表示 user_logins.id',
            'user_id 表示业务用户 ID',
            'login_ip 表示登录 IP',
            'user_agent 表示登录浏览器或客户端标识',
        ];
    }

    /**
     * 不允许继续出现的乱码或英文占位片段。
     *
     * @return array<int, string> 禁止片段列表。
     */
    private function forbiddenFragments(): array
    {
        return [
            '鐢ㄦ埛',
            '鍓嶅彴',
            '鍏宠仈',
            '鏄惁',
            'Mass Assignable Fields',
            'Hidden Fields for Serialization',
            'Attribute Casting',
            'Relation:',
            'User Model',
            'Responsible for',
            'Stores user',
            'Check if user has permission',
            'Get direct sub-agents',
        ];
    }
}
