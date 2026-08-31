<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 22:13
 */

/**
 * UserRegistrationServiceMessageKeyTest
 *
 * 文件功能：
 * - 验证注册服务业务消息使用语言 key 且 key 在中英文语言文件中存在，最终清单不再把已闭环的注册异常安全响应写成缺口。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 用户注册服务返回消息多语言 key 测试。
 *
 * 功能逻辑说明：
 * - UserRegistrationService 返回的 message 会被前台注册页直接展示。
 * - 后端服务不得返回硬编码中文，否则英文环境无法切换文案。
 * - 本测试要求注册成功、账号类型错误、普通客户缺少邀请人等消息统一来自 register 语言包。
 */
class UserRegistrationServiceMessageKeyTest extends TestCase
{
    /**
     * 注册服务不得保留硬编码中文业务消息。
     *
     * 参数与变量含义：
     * - $serviceContent：用户注册服务源码文本，用于静态检查硬编码消息是否已经迁移。
     * - $forbiddenMessages：旧实现中直接返回的中文消息，任何一个保留都表示多语言未完成。
     *
     * @return void
     */
    public function test_user_registration_service_uses_language_keys_for_business_messages(): void
    {
        $serviceContent = (string) file_get_contents(app_path('Services/UserRegistrationService.php'));

        $this->assertStringContainsString("__('register.success')", $serviceContent);
        $this->assertStringContainsString("__('register.invalid_account_type')", $serviceContent);
        $this->assertStringContainsString("__('register.customer_inviter_required')", $serviceContent);
        $this->assertStringContainsString("__('register.customer_valid_inviter_required')", $serviceContent);

        $forbiddenMessages = [
            '注册成功',
            '账户类型无效',
            '普通客户必须填写邀请人ID',
            '普通客户必须提供有效邀请人ID',
        ];

        foreach ($forbiddenMessages as $message) {
            $this->assertStringNotContainsString($message, $serviceContent, '注册服务仍保留硬编码中文消息：' . $message);
        }
    }

    /**
     * 注册消息 key 必须同时存在于中英文语言包。
     *
     * 参数与变量含义：
     * - $requiredKeys：注册服务依赖的语言包 key。
     * - $zhMessages / $enMessages：中英文 register.php 语言包数组。
     *
     * @return void
     */
    public function test_register_message_keys_exist_in_zh_cn_and_en_language_files(): void
    {
        $requiredKeys = [
            'success',
            'invalid_account_type',
            'customer_inviter_required',
            'customer_valid_inviter_required',
        ];

        $zhMessages = require resource_path('lang/zh-CN/register.php');
        $enMessages = require resource_path('lang/en/register.php');

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $zhMessages, '中文 register.php 缺少 key：' . $key);
            $this->assertArrayHasKey($key, $enMessages, '英文 register.php 缺少 key：' . $key);
            $this->assertNotSame('', trim((string) $zhMessages[$key]));
            $this->assertNotSame('', trim((string) $enMessages[$key]));
        }
    }

    /**
     * 最终清单不能把已闭环的注册异常安全响应继续写成缺口。
     *
     * 参数与变量含义：
     * - $checklist：后台最终执行清单正文，用于约束第 348 节和第 349 节之间的状态描述不能冲突。
     * - $staleText：第 349 节已经闭环后的旧剩余边界，保留会导致后续迁移重复处理同一问题。
     *
     * @return void
     */
    public function test_final_checklist_does_not_keep_stale_registration_exception_gap_text(): void
    {
        $checklist = (string) file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        $this->assertStringContainsString('## 348. 2026-07-11', $checklist);
        $this->assertStringContainsString('## 349. 2026-07-11', $checklist);

        $staleText = '注册异常响应仍需去除底层异常原文';

        $this->assertStringNotContainsString($staleText, $checklist, '最终清单仍保留已闭环的注册异常响应缺口。');
    }
}
