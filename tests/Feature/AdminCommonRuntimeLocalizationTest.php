<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/04
 * Time: 17:09
 */

/**
 * AdminCommonRuntimeLocalizationTest
 *
 * 文件功能：
 * - 验证后台登录过期提示存在于旧版 admin i18n 中英文语言包，且公共脚本不再保留英文运行时兜底文案。
 * - 输入：语言包数组与渲染后的响应/脚本文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖业务逻辑与路由契约（由各模块功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;

/**
 * 后台公共运行时多语言测试。
 *
 * 功能逻辑说明：
 * - 后台公共 Layui 脚本会处理登录过期、网络错误、主题切换等所有页面都可能触发的运行时提示。
 * - 这些提示必须从语言包读取，不能在中文后台界面中因为英文兜底而出现不可切换文案。
 * - 本测试只检查静态 JS 与语言包契约，不连接数据库。
 */
class AdminCommonRuntimeLocalizationTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 后台公共脚本不能继续保留英文兜底提示。
     *
     * @return void
     */
    public function test_admin_common_scripts_do_not_keep_english_runtime_fallback_text(): void
    {
        $commonScript = $this->adminLayuiScript('common.js');
        $layoutScript = $this->adminLayuiScript('layout.js');

        $this->assertStringNotContainsString("'Session expired'", $commonScript, '后台登录过期提示必须读取 admin i18n 的 login_expired。');
        $this->assertStringNotContainsString("'Theme applied'", $layoutScript, '后台主题切换提示必须读取 common.success。');

        $this->assertStringContainsString("CRM.t('login_expired')", $commonScript);
        $this->assertStringContainsString("CrmLang.t('common.success')", $layoutScript);
    }

    /**
     * 后台登录过期提示必须存在于旧版 admin i18n 中英文语言包。
     *
     * @return void
     */
    public function test_admin_login_expired_key_exists_in_legacy_admin_i18n_files(): void
    {
        $zhSource = file_get_contents(public_path('js/apps/admin/i18n/zh-CN.js')) ?: '';
        $enSource = file_get_contents(public_path('js/apps/admin/i18n/en.js')) ?: '';

        $this->assertStringContainsString("'login_expired'", $zhSource, '后台中文 i18n 缺少 login_expired。');
        $this->assertStringContainsString("'login_expired'", $enSource, '后台英文 i18n 缺少 login_expired。');
    }
}
