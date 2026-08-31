<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/07
 * Time: 19:50
 */

/**
 * AdminZhCnLanguageReadabilityTest
 *
 * 文件功能：
 * - 验证后台中文语言包行为可读中文，且不含典型中文乱码片段。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台 zh-CN 多语言可读性回归测试。
 *
 * 功能逻辑说明：
 * - 用户要求后端也必须支持多语言，Laravel 后端消息主要来自 resources/lang/zh-CN/admin.php。
 * - 该文件曾出现 UTF-8/GBK 错解后的乱码，虽然 PHP 语法可解析，但后台页面和接口提示不可读。
 * - 本测试只约束后台高频 key 和本轮新增 key，避免未来再次把中文语言包写成乱码。
 */
class AdminZhCnLanguageReadabilityTest extends TestCase
{
    /**
     * 后台中文语言包关键文案必须是可读中文。
     *
     * @return void
     */
    public function test_admin_zh_cn_language_lines_are_readable_chinese(): void
    {
        // $messages：Laravel 后台中文语言包数组，供 Blade 和后端接口 __('admin.xxx') 读取。
        $messages = require resource_path('lang/zh-CN/admin.php');

        // $expected：关键后台文案期望值；覆盖登录、菜单、权益汇总、批量导入和风控等当前重点模块。
        $expected = [
            'dashboard' => '控制台',
            'users' => '用户管理',
            'permissions' => '权限管理',
            'menus' => '菜单管理',
            'login_successful' => '登录成功',
            'invalid_credentials' => '账号或密码错误',
            'rights_summary' => '权益汇总',
            'rights_summary_fetched' => '权益汇总获取成功',
            'manual_confirm_rights_settlement' => '手动确认权益结算',
            'rights_settlement_confirmed' => '权益结算已手动确认',
            'settlement_amount' => '结算金额',
            'deposit_imports' => '批量入金导入',
            'withdraw_imports' => '批量出金导入',
            'credit_imports' => '批量信用导入',
            'risk_ip_detail' => '异常IP详情',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $messages[$key] ?? null, 'admin.' . $key . ' 必须是可读中文文案。');
        }
    }

    /**
     * 后台中文语言包不应包含典型中文乱码片段。
     *
     * @return void
     */
    public function test_admin_zh_cn_language_file_has_no_common_mojibake_fragments(): void
    {
        // $content：语言包原始源码，用于检查常见 UTF-8/GBK 错解片段。
        $content = file_get_contents(resource_path('lang/zh-CN/admin.php')) ?: '';

        foreach (['鐨', '鏉', '閺', '娴', '绠', '缁', '閸', '闁', '婢', '�'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $content, 'zh-CN/admin.php 存在疑似乱码片段：' . $fragment);
        }
    }
}
