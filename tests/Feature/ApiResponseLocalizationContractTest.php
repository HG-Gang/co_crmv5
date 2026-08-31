<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 21:20
 */

/**
 * ApiResponseLocalizationContractTest
 *
 * 文件功能：
 * - 验证公共响应层契约：每个 ResponseCode 都有 zh_CN 与 en 语言条目，响应类注释保持可读中文且无历史乱码与英文占位。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Tests\TestCase;

/**
 * 公共 API 响应多语言契约测试。
 *
 * 功能逻辑说明：
 * - ApiResponse 是后台和前台接口统一返回 message 的入口。
 * - ResponseCode::messageKey() 必须把每个业务状态码映射到 response.* 语言键。
 * - 中英文 response 语言包必须同时包含这些 key，避免接口在英文或中文环境下返回原始 key。
 */
class ApiResponseLocalizationContractTest extends TestCase
{
    /**
     * 所有响应状态码必须拥有中英文语言包映射。
     *
     * 参数与变量含义：
     * - $codes：ResponseCode 中定义的全部整数状态码，包含别名常量去重后的结果。
     * - $code：当前被检查的响应状态码。
     * - $messageKey：ResponseCode::messageKey() 返回的多语言 key，必须以 response. 开头。
     * - $shortKey：去掉 response. 前缀后的语言包数组键。
     * - $zhMessages / $enMessages：中文和英文 response.php 语言包内容。
     *
     * @return void
     */
    public function test_every_response_code_has_zh_cn_and_en_language_entry(): void
    {
        $codes = $this->responseCodes();
        $zhMessages = require resource_path('lang/zh-CN/response.php');
        $enMessages = require resource_path('lang/en/response.php');

        $this->assertGreaterThan(30, count($codes), 'ResponseCode 状态码数量异常，可能没有读取到公共响应码。');

        foreach ($codes as $code) {
            $messageKey = ResponseCode::messageKey($code);
            $this->assertStringStartsWith('response.', $messageKey, $code . ' 必须映射到 response.* 语言键。');
            $this->assertNotSame('response.unknown', $messageKey, $code . ' 不能回退到 unknown，必须声明明确业务文案。');

            $shortKey = substr($messageKey, strlen('response.'));
            $this->assertArrayHasKey($shortKey, $zhMessages, $messageKey . ' 缺少中文语言包文案。');
            $this->assertArrayHasKey($shortKey, $enMessages, $messageKey . ' 缺少英文语言包文案。');
        }
    }

    /**
     * ApiResponse 和 ResponseCode 注释必须说明多语言消息入口，不得保留历史乱码。
     *
     * 参数与变量含义：
     * - $files：需要检查的公共响应层文件路径。
     * - $content：当前文件源码。
     * - $forbiddenFragments：禁止回流的历史编码乱码和英文占位片段。
     *
     * @return void
     */
    public function test_api_response_and_response_code_comments_are_readable_chinese(): void
    {
        $files = [
            app_path('Traits/ApiResponse.php'),
            app_path('Constants/ResponseCode.php'),
        ];

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);

            $this->assertStringContainsString('多语言', $content, $file . ' 必须说明多语言消息处理逻辑。');
            $this->assertStringContainsString('参数逻辑说明', $content, $file . ' 必须说明参数含义。');

            foreach ($this->forbiddenFragments() as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $file . ' 仍包含历史乱码或英文占位片段：' . $fragment);
            }
        }
    }

    /**
     * 读取 ResponseCode 中定义的全部响应状态码。
     *
     * 参数与变量含义：
     * - $reflection：ResponseCode 的反射对象，用于读取类常量。
     * - $codes：去重后的整数状态码列表；别名常量会被合并。
     *
     * @return array<int, int> 响应状态码列表。
     */
    private function responseCodes(): array
    {
        $reflection = new \ReflectionClass(ResponseCode::class);
        $codes = array_values(array_unique(array_filter($reflection->getConstants(), 'is_int')));
        sort($codes);

        return $codes;
    }

    /**
     * 返回公共响应层禁止出现的历史乱码和英文占位片段。
     *
     * @return array<int, string> 禁止出现的文本片段。
     */
    private function forbiddenFragments(): array
    {
        return [
            'Standard JSON Response Trait',
            'Unified Response Status Code Constants',
            'All APIs return unified format',
            'Get the i18n message key',
            'supports i18n key',
            '鐘舵',
            '鎴愬',
            '鏁版嵁',
            '璁よ瘉',
            '鏉冮檺',
            '澶辫触',
            '鍝嶅簲',
        ];
    }
}
