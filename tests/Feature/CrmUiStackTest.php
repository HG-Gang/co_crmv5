<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 16:55
 */

/**
 * CrmUI 技术栈（Stack）整体契约测试。
 *
 * 文件功能：
 * - 验证 CrmUI 注册了独立的命名空间、资产、路由与页面，且与 layui/naive 互不依赖。
 * - 验证所有 CrmUI 页面路由可渲染、业务绑定完整且无未解析翻译键。
 * - 验证移动端 CSS 对指标卡片防溢出布局。
 * - 验证大代理商 CrmUI 使用旧版会话边界与受限接口。
 *
 * 适用场景：
 * - CrmUI 双端技术栈完整性与页面渲染的回归测试。
 *
 * 入参例子：
 * - GET /front-crmui/login、/admin-crmui/users、/front-crmui/big-agent/login 等页面。
 *
 * 返回值：
 * - 各断言通过表示命名空间、资产、路由、页面、翻译均符合契约。
 *
 * 异常或失败场景：
 * - 缺文件、缺路由、页面渲染失败、出现未解析翻译键或内联脚本时断言失败。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CrmUiStackTest extends TestCase
{
    /**
     * 验证 CrmUI 注册独立命名空间、资产、路由与页面。
     */
    public function test_crmui_registers_independent_namespaces_assets_routes_and_pages(): void
    {
        $requiredFiles = [
            app_path('Http/Controllers/CrmUi/Front/PageController.php'),
            app_path('Http/Controllers/CrmUi/Admin/PageController.php'),
            resource_path('front/crmui/layouts/app.blade.php'),
            resource_path('front/crmui/layouts/auth.blade.php'),
            resource_path('admin/crmui/layouts/app.blade.php'),
            resource_path('admin/crmui/layouts/auth.blade.php'),
            resource_path('admin/crmui/authentications/detail.blade.php'),
            resource_path('front/crmui/partials/module-page.blade.php'),
            resource_path('admin/crmui/partials/module-page.blade.php'),
            resource_path('lang/zh-CN/crmui.php'),
            resource_path('lang/en/crmui.php'),
            public_path('css/crmui/tokens.css'),
            public_path('css/crmui/front.css'),
            public_path('css/crmui/admin.css'),
            public_path('js/apps/crmui/front.js'),
            public_path('js/apps/crmui/admin.js'),
        ];

        foreach ($requiredFiles as $file) {
            $this->assertFileExists($file, 'CrmUI must be independent from layui and naive: ' . $file);
        }

        $this->assertTrue(view()->exists('front_crmui::dashboard.index'));
        $this->assertTrue(view()->exists('admin_crmui::users.index'));
        $this->assertTrue(view()->exists('admin_crmui::authentications.detail'));

        foreach ([
            'front_crmui_login',
            'front_crmui_register',
            'front_crmui_app',
            'admin_crmui_login',
            'admin_crmui_app',
        ] as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName), 'Missing CrmUI route: ' . $routeName);
        }

        $frontCss = file_get_contents(public_path('css/crmui/front.css')) ?: '';
        $adminCss = file_get_contents(public_path('css/crmui/admin.css')) ?: '';
        $frontJs = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $adminJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        $this->assertStringContainsString('@media (max-width: 1199px)', $frontCss);
        $this->assertStringContainsString('@media (max-width: 767px)', $frontCss);
        $this->assertStringContainsString('@media (max-width: 1199px)', $adminCss);
        $this->assertStringContainsString('@media (max-width: 767px)', $adminCss);
        $this->assertStringContainsString('layui.define', $frontJs);
        $this->assertStringContainsString("exports('crmuiFront'", $frontJs);
        $this->assertStringContainsString('layui.define', $adminJs);
        $this->assertStringContainsString("exports('crmuiAdmin'", $adminJs);

        $frontPages = $this->bladePages(resource_path('front/crmui'));
        $adminPages = $this->bladePages(resource_path('admin/crmui'));
        $legacyFrontPages = $this->bladePages(resource_path('front/layui'));
        $legacyAdminPages = $this->bladePages(resource_path('admin/layui'));
        $adminEmbeddedSurfaces = [
            '/admin-crmui/withdrawals' => 'data-crmui-row-action="detail"',
        ];

        foreach ($adminEmbeddedSurfaces as $path => $contract) {
            $this->get($path)
                ->assertOk()
                ->assertSee($contract, false);
        }

        $this->assertGreaterThanOrEqual(count($legacyFrontPages), count($frontPages), 'CrmUI front must cover the existing front blade surface.');
        $this->assertGreaterThanOrEqual(
            count($legacyAdminPages),
            count($adminPages) + count($adminEmbeddedSurfaces),
            'CrmUI admin must cover each existing admin surface with a page or an explicit embedded workflow.'
        );

        foreach (array_merge($frontPages, $adminPages) as $file) {
            $contents = file_get_contents($file) ?: '';
            $this->assertStringNotContainsString('<script>', $contents, 'CrmUI Blade must not contain inline executable JavaScript: ' . $file);
            $this->assertStringNotContainsString('onclick=', strtolower($contents), 'CrmUI Blade must keep event logic in JS files: ' . $file);
        }

        foreach ([
            '/front-crmui/login' => 'data-crmui-auth="front-login"',
            '/front-crmui/dashboard' => 'data-crmui-page="front.dashboard"',
            '/front-crmui/profile' => 'data-crmui-page="front.profile"',
            '/front-crmui/deposit' => 'data-crmui-page="front.deposit"',
            '/admin-crmui/login' => 'data-crmui-auth="admin-login"',
            '/admin-crmui/dashboard' => 'data-crmui-page="admin.dashboard"',
            '/admin-crmui/users' => 'data-crmui-page="admin.users"',
            '/admin-crmui/deposits' => 'data-crmui-page="admin.deposits"',
            '/admin-crmui/withdrawals' => 'data-crmui-page="admin.withdrawals"',
        ] as $uri => $marker) {
            $response = $this->get($uri);

            $response->assertOk();
            $response->assertSee($marker, false);
            $response->assertSee('/css/crmui/tokens.css', false);
            $response->assertSee('/js/apps/crmui/', false);
        }

        $this->assertIsString(__('crmui.actions.search', [], 'zh-CN'));
        $this->assertIsString(__('crmui.actions.search', [], 'en'));
    }

    public function test_admin_crmui_action_modal_respects_its_hidden_state(): void
    {
        $adminCss = file_get_contents(public_path('css/crmui/admin.css')) ?: '';
        $modulePage = file_get_contents(resource_path('admin/crmui/partials/module-page.blade.php')) ?: '';

        $this->assertStringContainsString('data-crmui-action-modal hidden', $modulePage);
        $this->assertMatchesRegularExpression(
            '~\.crmui-admin-body\s+\.crmui-modal\[hidden\]\s*\{[^}]*display:\s*none;[^}]*\}~s',
            $adminCss,
            'The admin modal display rule must not override the native hidden contract.'
        );
    }

    /**
     * 验证 CrmUI 实名认证详情使用专用页面，并对非法动态路径关闭失败。
     */
    public function test_admin_crmui_authentication_detail_routes_render_dedicated_modes_and_fail_closed(): void
    {
        $layuiBodyAttributePattern = '/<body\b[^>]*(?<![A-Za-z0-9_-])data-ui-family\s*=\s*["\']layui["\']/i';
        $this->assertMatchesRegularExpression(
            $layuiBodyAttributePattern,
            '<body data-ui-family = "layui">'
        );
        $this->assertDoesNotMatchRegularExpression(
            $layuiBodyAttributePattern,
            '<body x-data-ui-family="layui">'
        );

        $show = $this->get('/admin-crmui/authentications/984205/detail/show');

        $show->assertOk()
            ->assertSee('data-crmui-page="admin.authentication_detail"', false)
            ->assertSee('data-visual-c-reference="admin.authentication_detail"', false)
            ->assertSee('data-crmui-auth-detail="1"', false)
            ->assertSee('data-crmui-auth-user-id="984205"', false)
            ->assertSee('data-crmui-auth-mode="show"', false)
            ->assertSee('/api/admin/authDetail', false)
            ->assertSee('/api/admin/reviewAuth', false)
            ->assertSee('data-crmui-auth-state="loading"', false)
            ->assertSee('data-crmui-auth-state="error"', false)
            ->assertSee('data-crmui-auth-state="empty"', false)
            ->assertSee('data-crmui-auth-state="content"', false)
            ->assertSee('data-lucide="loader-circle"', false)
            ->assertSee('/css/crmui/admin.css', false)
            ->assertDontSee('data-crmui-page="admin.dashboard"', false)
            ->assertDontSee('data-crmui-auth-review-form', false)
            ->assertDontSee('/css/layui/visual-c.css', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('onclick=', false);
        $this->assertDoesNotMatchRegularExpression(
            $layuiBodyAttributePattern,
            $show->getContent()
        );

        $auth = $this->get('/admin-crmui/authentications/984206/detail/auth');

        $auth->assertOk()
            ->assertSee('data-crmui-page="admin.authentication_detail"', false)
            ->assertSee('data-crmui-auth-user-id="984206"', false)
            ->assertSee('data-crmui-auth-mode="auth"', false)
            ->assertSee('data-crmui-auth-review-form', false)
            ->assertSee('name="id_card_decision"', false)
            ->assertSee('name="id_card_reason"', false)
            ->assertSee('name="bank_decision"', false)
            ->assertSee('name="bank_reason"', false)
            ->assertSee('maxlength="500"', false)
            ->assertDontSee('/css/layui/visual-c.css', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('onclick=', false);
        $this->assertDoesNotMatchRegularExpression(
            $layuiBodyAttributePattern,
            $auth->getContent()
        );

        $detailBlade = file_get_contents(resource_path('admin/crmui/authentications/detail.blade.php'));
        $adminCss = file_get_contents(public_path('css/crmui/admin.css'));
        $this->assertNotFalse($detailBlade);
        $this->assertNotFalse($adminCss);
        $this->assertStringNotContainsString('admin_layui::', $detailBlade);
        $this->assertDoesNotMatchRegularExpression('/(?:class=["\'][^"\']*layui-|\.layui-)/', $detailBlade);
        $this->assertStringNotContainsString('.layui-', $adminCss);

        foreach ([
            '/admin-crmui/authentications/0/detail/show',
            '/admin-crmui/authentications/not-a-user/detail/show',
            '/admin-crmui/authentications/984207/detail/invalid',
            '/admin-crmui/authentications/984207/detail',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    /**
     * 验证普通用户、代理商与管理员的 CrmUI 页面均可渲染，并一次性汇总所有未解析翻译键。
     *
     * 返回结果：所有页面应返回成功响应且缺失键集合为空；失败消息会列出完整键名，避免测试只修复首个页面后才暴露下一个缺口。
     */
    /**
     * 验证所有 CrmUI 页面路由可渲染且无未解析翻译键。
     */
    public function test_all_crmui_page_routes_render_without_breaking_business_bindings(): void
    {
        $rawTranslationKeys = [];

        foreach ($this->frontPaths() as $path) {
            $response = $this->get('/front-crmui/' . $path);

            $response->assertOk();
            $response->assertSee('data-crmui-page="front.', false);
            $response->assertSee('data-api-url=', false);
            $rawTranslationKeys = array_merge($rawTranslationKeys, $this->rawCrmUiTranslationKeys($response->getContent(), 'front-crmui/' . $path));
        }

        foreach ($this->adminPaths() as $path) {
            $response = $this->get('/admin-crmui/' . $path);

            $response->assertOk();
            $response->assertSee('data-crmui-page="admin.', false);
            $response->assertSee('data-api-url=', false);
            $rawTranslationKeys = array_merge($rawTranslationKeys, $this->rawCrmUiTranslationKeys($response->getContent(), 'admin-crmui/' . $path));
        }

        $this->assertSame([], array_values(array_unique($rawTranslationKeys)), 'CrmUI 页面存在未解析翻译键。');
    }

    /**
     * 验证移动端 CSS 指标卡片堆叠防止溢出。
     */
    public function test_crmui_mobile_css_stacks_metrics_to_prevent_overflow(): void
    {
        $frontCss = file_get_contents(public_path('css/crmui/front.css')) ?: '';

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*767px\)[\s\S]*?\.crmui-metrics\s*\{[\s\S]*?grid-template-columns:\s*1fr;/',
            $frontCss
        );
    }

    /**
     * 验证大代理商 CrmUI 使用旧版会话边界与受限接口。
     */
    public function test_big_agent_crmui_uses_the_legacy_session_boundary_and_scoped_endpoints(): void
    {
        foreach ([
            'front_crmui_big_agent_login',
            'front_crmui_big_agent_dashboard',
            'front_crmui_big_agent_app',
            'front_naive_big_agent_login',
            'front_naive_big_agent_dashboard',
            'front_naive_big_agent_app',
        ] as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName), 'Missing big-agent dual-UI route: ' . $routeName);
        }

        foreach ([
            resource_path('front/crmui/big-agent/dashboard.blade.php'),
            resource_path('front/crmui/big-agent/list.blade.php'),
            resource_path('front/crmui/big-agent/password.blade.php'),
        ] as $file) {
            $this->assertFileExists($file, 'Big-agent CrmUI page is missing: ' . $file);
        }

        $login = $this->get('/front-crmui/big-agent/login');
        $login->assertOk();
        $login->assertSee('data-crmui-auth-legacy-session="1"', false);
        $login->assertSee('name="loginUid"', false);
        $login->assertSee('name="loginPassword"', false);
        $login->assertSee('/user/agents/signIn', false);
        $login->assertSee('/user/agents/captcha', false);

        $dashboardRoute = Route::getRoutes()->getByName('front_crmui_big_agent_dashboard');
        $this->assertContains('legacy.front.auth', $dashboardRoute->middleware());
        $this->get('/front-crmui/big-agent/dashboard')
            ->assertRedirect('/front-crmui/big-agent/login');

        $naiveDashboardRoute = Route::getRoutes()->getByName('front_naive_big_agent_dashboard');
        $this->assertContains('legacy.front.auth', $naiveDashboardRoute->middleware());
        $this->get('/front-naive/big-agent/dashboard')
            ->assertRedirect('/front-naive/big-agent/login');

        $naiveLogin = $this->get('/front-naive/big-agent/login');
        $naiveLogin->assertOk()
            ->assertSee('data-ui-family="naive"', false)
            ->assertSee('data-success-url="http://localhost/front-naive/big-agent/dashboard"', false)
            ->assertSee('/user/agents/signIn', false);

        $source = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $this->assertStringContainsString('data-crmui-auth-legacy-session', $source);
        $this->assertStringContainsString('allowLegacy: legacySessionAuth', $source);
    }

    /**
     * @return array<int, string>
     */
    private function bladePages(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                continue;
            }

            $path = $file->getPathname();
            if (strpos($path, DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }
            if (strpos($path, DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    /**
     * 提取页面中仍以原始键形式出现的 CrmUI 文案。
     *
     * @param string $content 已完成 Blade 渲染的 HTML。
     * @param string $path 当前页面路径，用于在失败结果中定位来源。
     * @return array<int, string> 带页面路径的未解析翻译键；没有缺口时返回空数组。
     */
    private function rawCrmUiTranslationKeys(string $content, string $path): array
    {
        preg_match_all(
            '/\bcrmui\.(?:common|fields|metrics|actions|panels|tabs|options|confirms|front|admin)\.[A-Za-z0-9_.-]+/',
            $content,
            $matches
        );

        $keys = [];
        foreach (array_unique($matches[0] ?? []) as $key) {
            $keys[] = $path . ': ' . $key;
        }

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    private function frontPaths(): array
    {
        return [
            'dashboard',
            'profile',
            'profile/edit',
            'profile/change-password',
            'profile/change-email',
            'account/info',
            'account/balance',
            'account/voucher',
            'account/cancel',
            'deposit',
            'withdraw',
            'flow',
            'position/summary',
            'order/open',
            'order/closed',
            'agent/sub',
            'agent/customers',
            'agent/confirm-level',
            'agent/group-change',
            'commission/realtime',
            'commission/history',
            'commission/transfer',
            'gift/address',
            'gift/list',
            'news',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function adminPaths(): array
    {
        return [
            'dashboard',
            'users',
            'users/10001',
            'roles',
            'permissions',
            'menus',
            'data-scopes',
            'agents',
            'online-users',
            'authentications',
            'authentications/984205/detail/show',
            'authentications/984206/detail/auth',
            'productions',
            'gifts',
            'deposits',
            'deposit-imports',
            'withdrawals',
            'withdraw-imports',
            'withdraw-flows',
            'undeposit-flows',
            'never-deposit-users',
            'rights-summary',
            'position-summary',
            'commissions',
            'realtime-commissions',
            'credit-imports',
            'agent-levels',
            'group-configs',
            'system-configs',
            'exchange-rates',
            'channels',
            'admins',
            'news',
            'vouchers',
            'risk',
            'blacklist',
            'cancel-applies',
            'trades',
            'big-agents',
            'profile/edit',
            'profile/change-password',
        ];
    }
}
