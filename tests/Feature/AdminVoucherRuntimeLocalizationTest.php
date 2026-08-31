<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminVoucherRuntimeLocalizationTest
 *
 * 文件功能：
 * - 验证凭证预览运行时文案走语言包，且所需语言 key 在中英文语言包中均存在。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台凭证审核页面运行时多语言测试。
 *
 * 功能逻辑说明：
 * - 凭证审核页面的图片预览链接和预览弹窗标题由 `public/js/apps/admin/layui/vouchers/index.js` 动态生成。
 * - 这些运行时文案必须读取 `CrmLang` 语言包，避免中文后台界面出现英文兜底文案。
 * - 本测试不连接真实数据库，只检查后台 Layui JS 与运行时语言包的静态契约。
 */
class AdminVoucherRuntimeLocalizationTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 凭证图片预览运行时文案必须使用语言包，不能写死英文兜底。
     *
     * 参数含义：
     * - $script：凭证审核后台 Layui 脚本源码。
     * - $forbiddenText：不允许继续出现在 JS 中的英文运行时兜底文案。
     *
     * @return void
     */
    public function test_voucher_preview_runtime_text_uses_language_pack(): void
    {
        $script = $this->adminLayuiScript('vouchers/index.js');

        foreach (["|| 'View'", "|| 'Voucher Images'"] as $forbiddenText) {
            $this->assertStringNotContainsString($forbiddenText, $script, '凭证审核 JS 仍存在英文兜底文案：' . $forbiddenText);
        }

        $this->assertStringContainsString("CrmLang.t('common.view')", $script);
        $this->assertStringContainsString("CrmLang.t('front.voucher_images')", $script);
    }

    /**
     * 凭证预览需要的运行时语言 key 必须存在于中英文语言包。
     *
     * @return void
     */
    public function test_voucher_preview_language_keys_exist(): void
    {
        $zhSource = file_get_contents(public_path('js/shared/lang/common/zh-CN.js')) ?: '';
        $enSource = file_get_contents(public_path('js/shared/lang/common/en.js')) ?: '';

        foreach (['view:', 'voucher_images:'] as $leafKey) {
            $this->assertStringContainsString($leafKey, $zhSource, '中文运行时语言包缺少 key：' . $leafKey);
            $this->assertStringContainsString($leafKey, $enSource, '英文运行时语言包缺少 key：' . $leafKey);
        }
    }
}
