<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 12:33
 */

namespace Tests\Feature;

use DOMDocument;
use Tests\TestCase;
use Tests\Feature\Concerns\CreatesLegacySmokeUsers;

/**
 * 旧前台仪表盘提交入口运行闭环测试。
 *
 * 文件功能：验证项目1使用 POST 的 user/indexreg 在项目2仍受普通用户旧 Session 边界保护，
 * 匿名请求进入登录页，有效用户请求则渲染同一套服务端 Blade 仪表盘，不创建第二套前端状态。
 */
class FrontLegacyDashboardEntryClosureModuleTest extends TestCase
{
    use CreatesLegacySmokeUsers;

    /**
     * 匿名提交不得绕过 legacy.front.auth 进入仪表盘。
     *
     * @return void
     */
    public function test_anonymous_legacy_indexreg_redirects_to_user_login(): void
    {
        $this->post('/user/indexreg')
            ->assertRedirect('/user/login');
    }

    /**
     * 有效旧 Session 提交后返回外层 Blade，并让 iframe 通过 GET 加载仪表盘内容。
     *
     * 执行结果：
     * - POST /user/indexreg 只负责兼容旧入口并返回页面外壳。
     * - 外壳中的 iframe 必须改用 GET /user/index?frame=1，避免浏览器加载 POST 专用地址时得到 405。
     * - iframe 的 GET 响应必须包含仪表盘页面标识和前台页面脚本，证明整个浏览器链路可执行。
     *
     * @return void
     */
    public function test_authenticated_legacy_indexreg_uses_a_gettable_dashboard_frame(): void
    {
        // 有效旧会话必须对应真实可用用户，中间件才会放行到仪表盘外壳。
        $this->ensureLegacySmokeUser(990001);

        $html = $this->withSession(['suser' => ['user_id' => 990001]])
            ->post('/user/indexreg')
            ->assertOk()
            ->getContent();

        $document = new DOMDocument();
        $previousLibxmlState = libxml_use_internal_errors(true);

        try {
            $this->assertTrue($document->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlState);
        }

        $frame = $document->getElementById('contentFrame');
        $this->assertNotNull($frame, '旧 POST 入口返回的外层页面缺少 contentFrame。');

        $frameUrl = html_entity_decode($frame->getAttribute('src'), ENT_QUOTES | ENT_HTML5);
        $frameUrlParts = parse_url($frameUrl);
        $this->assertIsArray($frameUrlParts);
        $this->assertSame('/user/index', $frameUrlParts['path'] ?? null);

        parse_str($frameUrlParts['query'] ?? '', $frameQuery);
        $this->assertSame('1', (string) ($frameQuery['frame'] ?? ''));

        $frameRequestUri = ($frameUrlParts['path'] ?? '/')
            . (isset($frameUrlParts['query']) ? '?' . $frameUrlParts['query'] : '');

        $this->get($frameRequestUri)
            ->assertOk()
            ->assertSee('data-layui-page="dashboard/index"', false)
            ->assertSee('/js/apps/front/layui/pages.js', false);
    }
}
