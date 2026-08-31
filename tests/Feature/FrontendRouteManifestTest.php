<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 00:39
 */

namespace Tests\Feature;

use App\Support\FrontendRouteManifest;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * 服务端 Blade 前端路由清单回归测试。
 *
 * 文件功能：
 * - 验证浏览器只接收页面跳转所需的命名路由，不泄露后端 API 路由。
 * - 验证普通用户、代理商和后台管理员 Blade 页面引用的路由名称均已注册。
 * - 历史 URL 可以由 Laravel 重定向兼容，但不得作为活动客户端页面继续导出。
 *
 * 执行结果：
 * - 通过表示 Blade 模板、项目自定义脚本与 Laravel 页面路由清单一致。
 * - 失败表示页面引用了不存在的路由、硬编码了业务页面地址或重新暴露了已删除客户端入口。
 */
class FrontendRouteManifestTest extends TestCase
{
    public function test_manifest_exports_front_admin_legacy_and_page_routes_from_registered_routes(): void
    {
        $this->assertTrue(class_exists(FrontendRouteManifest::class), 'Frontend route manifest class must exist.');

        $manifest = FrontendRouteManifest::make();

        $this->assertSame('/user/signIn', $manifest['legacy_user_sign_in']['uri'] ?? null);
        $this->assertSame('/front/dashboard', $manifest['front_page_dashboard']['uri'] ?? null);
        $this->assertSame('/front-crmui/{path}', $manifest['front_crmui_app']['uri'] ?? null);
        $this->assertSame('/front-naive/{path}', $manifest['front_naive_app']['uri'] ?? null);
        $this->assertSame('/admin-crmui/{path}', $manifest['admin_crmui_app']['uri'] ?? null);
        $this->assertArrayNotHasKey('admin_naive_app', $manifest);
    }

    public function test_manifest_preserves_parameter_placeholders_for_javascript_replacement(): void
    {
        $manifest = FrontendRouteManifest::make();

        $this->assertSame('/show/user_detail/{userId}/{role}', $manifest['legacy_user_detail']['uri'] ?? null);
        $this->assertSame('/front/register/{inviter_id}', $manifest['front_page_register']['uri'] ?? null);
    }

    public function test_manifest_keeps_legacy_route_aliases_added_to_name_list(): void
    {
        $manifest = FrontendRouteManifest::make();

        $this->assertSame('/user/signIn', $manifest['user.loginUrl']['uri'] ?? null);
        $this->assertSame('/show/user_detail/{userId}/{role}', $manifest['show.user.info.detail']['uri'] ?? null);
        $this->assertSame('/user/flow/depositFlowSearch', $manifest['front.deposit.flow.search']['uri'] ?? null);
    }

    public function test_manifest_does_not_export_backend_api_routes(): void
    {
        $manifest = FrontendRouteManifest::make();
        $apiNames = [];
        $apiUris = [];

        foreach ($manifest as $name => $route) {
            if (str_starts_with($name, 'front_api_') || str_starts_with($name, 'admin_api_')) {
                $apiNames[] = $name;
            }

            $uri = $route['uri'] ?? '';
            if (str_starts_with($uri, '/api/front/') || str_starts_with($uri, '/api/admin/')) {
                $apiUris[] = $name . ' => ' . $uri;
            }
        }

        sort($apiNames);
        sort($apiUris);

        $this->assertSame([], $apiNames, 'CRM_ROUTES must not export backend API route names; business JS should keep readable /api/... strings.');
        $this->assertSame([], $apiUris, 'CRM_ROUTES must not export backend API URLs; API endpoints belong in page JS or Blade configs.');
    }

    public function test_frontend_referenced_page_route_names_exist_in_manifest(): void
    {
        $manifest = FrontendRouteManifest::make();
        $routeNames = [];
        $paths = [
            base_path('public/js'),
            resource_path('front/layui'),
            resource_path('admin/layui'),
            resource_path('views'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! preg_match('/\.(js|blade\.php)$/', $file->getFilename())) {
                    continue;
                }

                $content = file_get_contents($file->getPathname()) ?: '';
                preg_match_all('/\b(?:crmRoute|CRM\.route|routeUrl|namedRoute)\(\s*[\'\"]([a-z][a-z0-9_.]*_[a-zA-Z0-9_]+)[\'\"]/', $content, $callMatches);

                foreach ($callMatches[1] as $routeName) {
                    if (str_starts_with($routeName, 'front_api_') || str_starts_with($routeName, 'admin_api_')) {
                        continue;
                    }
                    if (in_array($routeName, ['front_theme_badge'], true)) {
                        continue;
                    }

                    $routeNames[$routeName] = $file->getPathname();
                }
            }
        }

        $missing = array_diff(array_keys($routeNames), array_keys($manifest));
        sort($missing);

        $this->assertSame([], $missing, 'Frontend page route names referenced by JS/Blade must exist in the manifest.');
    }

    public function test_layui_module_page_backend_requests_use_explicit_urls(): void
    {
        $source = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';

        $this->assertStringNotContainsString('crmRouteNameFromUrl', $source);
        $this->assertStringNotContainsString('front_api_', $source);
        $this->assertStringContainsString('url: apiUrl', $source);
        $this->assertStringContainsString('url: getSubmitUrl($form)', $source);
        $this->assertStringContainsString('function frontPageRouteUrl(routeName, params)', $source);
    }

    public function test_layui_module_page_internal_navigation_uses_named_routes(): void
    {
        $source = file_get_contents(public_path('js/apps/front/layui/module-page.js')) ?: '';

        $this->assertStringNotContainsString("navigateFrontPage('/front/commission/transfer?frame=1'", $source);
        $this->assertStringNotContainsString("navigateFrontPage('/front/agent/group-change?frame=1'", $source);
        $this->assertStringContainsString('function frontPageRouteUrl(routeName, params)', $source);
        $this->assertStringContainsString('return crmRoute(routeName, params || {}, \'\');', $source);
    }

    public function test_route_helper_does_not_reverse_match_backend_api_urls(): void
    {
        $source = file_get_contents(public_path('js/shared/routes.js')) ?: '';

        $this->assertStringContainsString('function isBackendApiPath(path)', $source);
        $this->assertStringContainsString('if (isBackendApiPath(path))', $source);
        $this->assertStringNotContainsString('apiRouteNameCandidates', $source);
        $this->assertStringNotContainsString("path.indexOf('/api/front/')", $source);
        $this->assertStringNotContainsString("path.indexOf('/api/admin/')", $source);
        $this->assertStringNotContainsString("apiName = candidates[i]", $source);
    }

    public function test_blade_route_helpers_reference_existing_routes(): void
    {
        $routeNames = array_keys(RouteFacade::getRoutes()->getRoutesByName());
        $missing = [];
        $paths = [resource_path('front'), resource_path('admin'), resource_path('views')];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $content = file_get_contents($file->getPathname()) ?: '';
                preg_match_all('/route\(\s*[\'\"]([^\'\"]+)[\'\"]/', $content, $matches);
                foreach ($matches[1] as $routeName) {
                    if (! in_array($routeName, $routeNames, true)) {
                        $missing[] = $routeName . ' @ ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    }
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, 'Blade route() helpers must reference existing Laravel routes.');
    }

    public function test_blade_referenced_local_javascript_files_exist(): void
    {
        $missing = [];
        $paths = [resource_path('front'), resource_path('admin'), resource_path('views')];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $content = file_get_contents($file->getPathname()) ?: '';
                preg_match_all('/asset\(\s*[\'\"]\/?(js\/[^\'\"?]+\.js)[\'\"]\s*\)/', $content, $assetMatches);
                preg_match_all('/<script[^>]+src=[\'\"]\/(js\/[^\'\"?]+\.js)/', $content, $literalMatches);

                foreach (array_merge($assetMatches[1], $literalMatches[1]) as $relativePath) {
                    $absolutePath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
                    if (! is_file($absolutePath)) {
                        $missing[] = $relativePath . ' @ ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    }
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, 'Blade templates must reference existing local JS files.');
    }

    public function test_layui_module_pages_use_readable_hardcoded_api_urls(): void
    {
        $violations = [];
        $explicitApiConfigs = 0;
        $path = resource_path('front/layui');

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname()) ?: '';
            if (preg_match('/[\'\"](?:api|submitApi|editApi)[\'\"]\s*=>\s*[\'\"](?:front_api_|admin_api_)/', $content)) {
                $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
            if (preg_match('/[\'\"](?:api|submitApi|editApi)[\'\"]\s*=>\s*[\'\"]\/api\/(?:front|admin)\//', $content)) {
                $explicitApiConfigs++;
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Layui module API configs must use readable /api/... URLs.');
        $this->assertGreaterThan(0, $explicitApiConfigs, 'Layui module pages should expose backend API URLs directly in Blade config.');
    }

    public function test_layui_blade_business_links_use_named_routes(): void
    {
        $violations = [];
        $paths = [resource_path('front/layui'), resource_path('admin/layui')];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $content = file_get_contents($file->getPathname()) ?: '';
                if (preg_match('/href=["\']\/(?:front|admin)\//', $content)
                    || preg_match('/url\(\s*["\']\/(?:front|admin)\//', $content)) {
                    $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Layui Blade business links must use named page routes.');
    }

    public function test_layui_frame_navigation_script_uses_named_route_paths(): void
    {
        $source = file_get_contents(resource_path('front/layui/layouts/app.blade.php')) ?: '';
        $layoutScript = file_get_contents(public_path('js/apps/front/layui/layout.js')) ?: '';

        $this->assertStringContainsString('frontPagePrefix', $source);
        $this->assertStringContainsString("parse_url(route('front_page_dashboard')", $source);
        $this->assertStringContainsString("parse_url(route('front_page_login')", $source);
        $this->assertStringContainsString("parse_url(route('front_page_register')", $source);
        $this->assertStringNotContainsString("?: '/front/", $source);
        $this->assertStringNotContainsString('a[href^="/front/"]', $source);
        $this->assertStringNotContainsString("href.indexOf('/front/login')", $source);
        $this->assertStringNotContainsString("href.indexOf('/front/register')", $source);
        $this->assertStringNotContainsString('#sideMenu a[href^="/front/"]', $layoutScript);
        $this->assertStringNotContainsString("|| 'front'", $layoutScript);
        $this->assertStringContainsString('frontPagePrefix', $layoutScript);
        $this->assertStringContainsString('buildFrameLinkSelector', $layoutScript);
    }

    public function test_front_controllers_do_not_hardcode_api_route_paths(): void
    {
        $violations = [];
        $path = app_path('Http/Controllers/Front');

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname()) ?: '';
            if (preg_match('/[\'\"]\/api\/front\//', $content)) {
                $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Front controllers should use route generation for generated API links.');
    }

    public function test_front_dashboard_controller_uses_named_routes_for_generated_links(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/DashboardController.php')) ?: '';

        $this->assertStringNotContainsString("url('/front/register/", $source);
        $this->assertStringNotContainsString('/user/news/news_detail/', $source);
        $this->assertStringContainsString("route('front_page_register'", $source);
        $this->assertStringContainsString("route('legacy_user_news_detail'", $source);
    }

    public function test_front_redirects_use_named_page_routes(): void
    {
        $violations = [];
        $files = [base_path('routes/web.php')];
        $path = app_path('Http/Controllers/Front');

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        foreach ($files as $file) {
            $content = file_get_contents($file) ?: '';
            if (preg_match('/redirect\s*\(\s*[\'\"]\/(?:front|front-naive|admin|admin-naive)\//', $content)) {
                $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Frontend redirects should use named page routes.');
    }

    public function test_legacy_theme_badges_are_removed_from_layui_shells(): void
    {
        $files = [
            resource_path('admin/layui/layouts/app.blade.php'),
            public_path('js/apps/admin/layui/layout.js'),
            public_path('css/admin/style.css'),
            resource_path('front/layui/layouts/app.blade.php'),
            public_path('js/apps/front/layui/layout.js'),
        ];
        $violations = [];

        foreach ($files as $file) {
            $content = file_get_contents($file) ?: '';
            if (stripos($content, 'frontThemeBadge') !== false
                || stripos($content, 'adminThemeBadge') !== false
                || stripos($content, 'theme-badge') !== false) {
                $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
            }
        }

        sort($violations);

        $this->assertSame([], $violations, 'Layui shells should not keep legacy theme badge text.');
    }
}
