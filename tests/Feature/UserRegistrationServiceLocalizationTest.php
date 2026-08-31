<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:38
 */

/**
 * UserRegistrationServiceLocalizationTest
 *
 * 文件功能：
 * - 验证注册服务身份证已存在消息使用 response 语言 key，核心参数均有可读中文说明且无乱码或英文标题注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 用户注册服务多语言与中文参数注释覆盖测试。
 *
 * 功能逻辑说明：
 * - UserRegistrationService 同时服务前台代理商注册和普通客户注册。
 * - 注册失败提示会直接返回给前台 Layui/Blade 页面，因此后端消息必须来自语言包，不能保留硬编码中文或半中半英拼接。
 * - 注册链路会写入 user_logins、user_infos、user_auths 和 agent_descendants，关键参数必须有可读中文逻辑说明。
 */
class UserRegistrationServiceLocalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * 身份证号重复提示必须使用 response 语言包 key。
     *
     * 参数和断言含义：
     * - $serviceContent：注册服务源码文本，用于确认旧英文拼接提示已经移除。
     * - $zhContent/$enContent：中英文响应语言包源码，用于确认新增 key 在两套语言中同时存在。
     *
     * @return void
     */
    public function test_id_card_exists_message_uses_response_language_key(): void
    {
        $serviceContent = file_get_contents(app_path('Services/UserRegistrationService.php'));
        $zhContent = file_get_contents(resource_path('lang/zh-CN/response.php'));
        $enContent = file_get_contents(resource_path('lang/en/response.php'));

        $this->assertStringNotContainsString("__('front.id_card_no') . ' already exists'", $serviceContent);
        $this->assertStringContainsString("__('response.id_card_exists')", $serviceContent);
        $this->assertStringContainsString("'id_card_exists'", $zhContent);
        $this->assertStringContainsString("'id_card_exists'", $enContent);
    }

    /**
     * 注册服务核心参数必须有干净可读的中文逻辑说明。
     *
     * 参数和断言含义：
     * - $requiredPhrases：必须出现在源码中的业务参数说明，覆盖注册类型、邀请人、资料表、认证表和代理关系表。
     * - $serviceContent：注册服务源码文本，用于静态确认维护说明完整。
     *
     * @return void
     */
    public function test_user_registration_service_documents_core_parameters_in_readable_chinese(): void
    {
        $serviceContent = file_get_contents(app_path('Services/UserRegistrationService.php'));

        $requiredPhrases = [
            '用户注册服务。',
            '$data 表示注册表单数据',
            '$parentId 表示邀请人的业务 user_id',
            '$accountType 表示注册账号类型',
            '$commissionMode 表示注册返佣模式',
            '$userId 表示新生成的业务用户 ID',
            '$userLogin 表示写入 user_logins 的登录账号记录',
            '$userInfo 表示写入 user_infos 的业务资料记录',
            '$parentInfo 表示邀请人的业务资料',
            '$familyTree 表示代理家族链',
            '$treeIds 表示 family_tree 拆分后的用户链路',
            'agent_descendants 表用于保存代理与下级用户的祖先后代关系',
        ];

        foreach ($requiredPhrases as $phrase) {
            $this->assertStringContainsString($phrase, $serviceContent, $phrase . ' 缺少中文逻辑注释。');
        }
    }

    /**
     * 注册服务注释不得继续保留英文标题或历史编码乱码。
     *
     * 参数和断言含义：
     * - $forbiddenFragments：不允许继续出现在注册服务中的英文占位标题和常见乱码片段。
     * - $serviceContent：注册服务源码文本，用于保证注释质量不会回退。
     *
     * @return void
     */
    public function test_user_registration_service_has_no_mojibake_or_english_title_comments(): void
    {
        $serviceContent = file_get_contents(app_path('Services/UserRegistrationService.php'));

        $forbiddenFragments = [
            'User Registration Service',
            '鐢',
            '璇',
            '鍙',
            '閭',
            '缁',
            '锟',
            '€?',
        ];

        foreach ($forbiddenFragments as $fragment) {
            $this->assertStringNotContainsString($fragment, $serviceContent, '注册服务仍包含不可读注释片段：' . $fragment);
        }
    }
}
