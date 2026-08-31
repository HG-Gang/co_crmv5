<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:38
 */

/**
 * Mt4ManagerServiceLocalizationTest
 *
 * 文件功能：
 * - 验证 MT4 管理服务错误消息本地化，并对构造参数、命令参数、命令字符串与响应解析变量具备中文业务含义注释。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * MT4 管理服务多语言与中文注释覆盖测试。
 *
 * 功能逻辑说明：
 * - Mt4ManagerService 会被后台资金、账户、风控等模块间接调用，返回数组中的 message 可能直接进入 API 响应。
 * - 因此连接失败、读取超时等用户可见错误不能写死英文，必须通过 resources/lang/response.php 的语言 key 输出。
 * - 同时该服务连接外部 MT4 Socket，参数含义必须清楚，避免后续维护时误改 host、port、key、version、timeout 或命令参数拼接逻辑。
 */
class Mt4ManagerServiceLocalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * MT4 服务错误返回必须使用 response 语言包，不能继续写死英文响应文案。
     *
     * 参数和断言含义：
     * - $servicePath：MT4 Socket 服务源码路径，承载连接、命令拼接和响应解析逻辑。
     * - $serviceContent：服务源码文本，用于确认用户可见 message 已改为 Laravel 多语言调用。
     * - $zhContent/$enContent：中英文响应语言包源码，用于确认新增语言 key 在两套语言中同时存在。
     *
     * @return void
     */
    public function test_mt4_manager_service_uses_localized_error_messages(): void
    {
        $servicePath = app_path('Services/Mt4ManagerService.php');
        $serviceContent = file_get_contents($servicePath);
        $zhContent = file_get_contents(resource_path('lang/zh-CN/response.php'));
        $enContent = file_get_contents(resource_path('lang/en/response.php'));

        $this->assertStringNotContainsString("'message' => 'Connection failed'", $serviceContent);
        $this->assertStringNotContainsString("'message' => 'Read timeout or empty response'", $serviceContent);
        $this->assertStringContainsString("__('response.mt4_connection_failed')", $serviceContent);
        $this->assertStringContainsString("__('response.mt4_read_timeout')", $serviceContent);
        $this->assertStringContainsString("'mt4_connection_failed'", $zhContent);
        $this->assertStringContainsString("'mt4_read_timeout'", $zhContent);
        $this->assertStringContainsString("'mt4_connection_failed'", $enContent);
        $this->assertStringContainsString("'mt4_read_timeout'", $enContent);
    }

    /**
     * MT4 服务核心参数必须有中文逻辑注释，方便按真实业务含义维护。
     *
     * 参数和断言含义：
     * - $requiredPhrases：必须出现在源码中的中文说明片段，覆盖构造参数、命令参数、命令字符串和响应解析变量。
     * - $serviceContent：MT4 服务源码文本，用于静态确认关键变量没有缺少业务含义注释。
     *
     * @return void
     */
    public function test_mt4_manager_service_documents_core_parameters_in_chinese(): void
    {
        $serviceContent = file_get_contents(app_path('Services/Mt4ManagerService.php'));

        $requiredPhrases = [
            '$host 表示 MT4 Manager API 主机地址',
            '$port 表示 MT4 Manager API 端口',
            '$apiKey 表示 MT4 Manager API 授权密钥',
            '$apiVersion 表示 MT4 Manager API 协议版本',
            '$timeout 表示 Socket 连接和读取超时时间',
            '$cmd 表示 MT4 命令名称',
            '$params 表示命令参数键值对',
            '$paramStr 表示转换为 MT4 协议格式后的参数片段',
            '$fullCmd 表示最终写入 Socket 的完整命令字符串',
            '$response 表示 MT4 Socket 返回的原始响应',
            '$parts 表示按协议分隔后的响应字段',
            '$status 表示响应状态',
        ];

        foreach ($requiredPhrases as $phrase) {
            $this->assertStringContainsString($phrase, $serviceContent, $phrase . ' 缺少中文逻辑注释。');
        }
    }
}
