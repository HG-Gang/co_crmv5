<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:53
 */

/**
 * FrontBigNumberControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 BigNumberController 中文逻辑注释与多语言响应：旧入口、登录参数、授权范围、订单查询、密码修改有中文说明，面向用户的错误使用多语言 key。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台大代理控制器中文注释与多语言响应可读性测试。
 *
 * 测试目标：
 * - 只读取 BigNumberController 源码和语言包，不连接真实数据库。
 * - 约束大代理旧入口、登录参数、授权范围、订单查询和密码修改必须有中文逻辑说明。
 * - 约束旧登录 JSON 中面向用户的错误提示必须使用 Laravel 多语言 key，不能继续保留乱码硬编码。
 */
class FrontBigNumberControllerCommentReadabilityTest extends TestCase
{
    public function test_big_number_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/BigNumberController.php')) ?: '';

        $expectedComments = [
            '前台大代理控制器',
            'legacy /user/agents/*',
            'big_agents 表',
            'JwtService：用于签发大代理旧入口和新 big-number API 的访问令牌',
            'loginUid 表示旧前台提交的大代理登录名',
            'loginPassword 表示旧前台提交的大代理登录密码',
            'is_enabled 表示大代理账号是否允许登录',
            'sub_agent_ids 表示大代理可查看的直属代理 user_id 集合',
            'includeDescendants 表示是否把直属代理的下级代理一并纳入查询范围',
            'open 表示是否查询未平仓订单',
            'agentSubList 用于新前台 big-number API 查询当前代理直属下级代理',
            'legacyAgentListResponse 用于旧前台大代理代理列表接口',
            'legacyOrderListResponse 用于旧前台大代理已平仓和未平仓订单接口',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'BigNumberController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_big_number_controller_uses_localized_messages_and_no_legacy_english_title(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/BigNumberController.php')) ?: '';

        $this->assertStringNotContainsString('Big-number agent portal (legacy /user/agents/*).', $source);
        $this->assertStringContainsString("__('auth.password_required')", $source);
        $this->assertStringContainsString("__('auth.failed')", $source);
        $this->assertStringContainsString("__('auth.account_disabled')", $source);
        $this->assertStringContainsString("__('auth.old_password_error')", $source);

        foreach (['璐﹀彿', '鏃犳晥', '瀵嗙爜', '绂佺敤', '閿欒', '�'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'BigNumberController 仍存在疑似乱码硬编码：' . $fragment);
        }
    }

    public function test_big_number_localization_keys_exist_in_both_languages(): void
    {
        $zhAuth = require resource_path('lang/zh-CN/auth.php');
        $enAuth = require resource_path('lang/en/auth.php');

        foreach (['password_required', 'failed', 'account_disabled', 'old_password_error', 'login_success'] as $key) {
            $this->assertArrayHasKey($key, $zhAuth, 'zh-CN/auth.php 缺少语言 key：' . $key);
            $this->assertArrayHasKey($key, $enAuth, 'en/auth.php 缺少语言 key：' . $key);
            $this->assertNotSame('', trim((string) $zhAuth[$key]), 'zh-CN/auth.php 的 ' . $key . ' 不能为空');
            $this->assertNotSame('', trim((string) $enAuth[$key]), 'en/auth.php 的 ' . $key . ' 不能为空');
        }
    }
}
