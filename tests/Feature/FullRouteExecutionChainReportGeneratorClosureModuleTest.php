<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 02:07
 */

/**
 * 全量路由执行链报告生成器（scripts/generate-full-route-execution-chain-report.php）的
 * 输出契约回归测试。
 *
 * 文件功能：
 * - 通过 proc_open 以 PHP_BINARY 运行生成器脚本，断言退出码为 0、生成在 120 秒预算内完成、
 *   报告为有效 UTF-8，且每个已注册 HTTP 路由方法对应唯一一个 “### 编号. METHOD `URI` (`name`)”
 *   条目：编号连续（1..N）、签名与当前运行时路由表排序后一一对应、每条目包含中间件链、
 *   Controller/源码位置、Service/Support/Model/DB 链、成功/失败分支、前端消费、测试证据等字段。
 * - 校验 Closure 路由（GET /、GET /admin/admins）使用各自运行时反射源码位置，不得共享缓存分析；
 *   每条目保留自身 return 分支（redirect 与 view 不串）。
 * - 校验旧后台兼容入口追踪：POST /index/admin/cancel/cancel_apply_pass 映射到
 *   admin_api_cancelApplyApprove / CancelApplyController / Mt4ManagerService；旧后台非 API 分支
 *   （login/captcha/logout/Administrators add）返回 HTML/PNG/3xx 而非 JSON；legacy_ 前缀路由
 *   条目必须暴露直接或共享测试证据。
 * - 校验 router fallback 与 side effect 边界口径：未注册 URI 返回 404，仅 LegacyAdminController::handle
 *   的 fallback 分支返回 410 JSON；资金链路按模块使用 outbox/state machine 异步一致性表述。
 * - 校验生成器源码不再使用 $genericMatches 等组级模板复用手段，每条目必须解释自身业务目的与
 *   解决问题，而不是复述路由分组。
 *
 * 适用场景：新增/删除/重命名路由、调整路由分组、改 Controller/Service 或修改生成器脚本后
 * 都应回归本文件，防止报告与运行时路由脱节或条目退化。
 *
 * 入参：无外部参数；用例内直接读取当前 Route 注册表作为期望值，报告输出到系统临时文件后删除。
 *
 * 返回值：无返回值；所有断言通过即表示报告与当前路由表逐方法闭环一致。
 *
 * 失败场景：断言失败表示生成器与运行时路由/源码分析脱节（条目缺失、编号或签名错位、超时、
 * 非 UTF-8 或字段退化），需要同步修复生成器脚本或路由配置后重跑本文件。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class FullRouteExecutionChainReportGeneratorClosureModuleTest extends TestCase
{
    public function test_report_generator_rejects_a_missing_legacy_project_root_in_the_child_process(): void
    {
        $outputPath = tempnam(sys_get_temp_dir(), 'crm-route-report-invalid-');
        $this->assertIsString($outputPath);
        @unlink($outputPath);

        $environment = getenv();
        $environment['LEGACY_PROJECT_ROOT'] = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'crm-missing-legacy-root-' . bin2hex(random_bytes(8));

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('scripts/generate-full-route-execution-chain-report.php'), $outputPath],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment
        );
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (is_file($outputPath)) {
            @unlink($outputPath);
        }

        $this->assertNotSame(0, $exitCode, $stdout . $stderr);
        $this->assertStringContainsString('旧项目目录不存在', $stdout . $stderr);
    }

    public function test_report_generator_emits_one_detailed_entry_for_every_registered_route_method(): void
    {
        $outputPath = tempnam(sys_get_temp_dir(), 'crm-route-report-');
        $this->assertIsString($outputPath);
        @unlink($outputPath);

        try {
            $startedAt = microtime(true);
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, base_path('scripts/generate-full-route-execution-chain-report.php'), $outputPath],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                getenv()
            );
            $this->assertIsResource($process);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            $elapsedSeconds = microtime(true) - $startedAt;

        $this->assertSame(0, $exitCode, $stderr ?: $stdout);
        // 生成预算说明：当前运行时 800 条路由 / 1140 个 HTTP 方法，完整报告约 6MB；
        // 本机实测单次生成 58~66 秒（含 proc_open 子进程开销），因此预算放宽到 120 秒，
        // 避免负载波动导致生成器被误判为超时，同时仍能拦截真正的死循环式退化。
        $this->assertLessThan(
            120.0,
            $elapsedSeconds,
            'A single complete route report must stay within the bounded generation budget.'
        );
        $this->assertFileExists($outputPath);
        $report = (string) file_get_contents($outputPath);
        @unlink($outputPath);
        $this->assertTrue(
            mb_check_encoding($report, 'UTF-8'),
            'The generated Chinese route report must be valid UTF-8.'
        );

        $expectedRoutes = Route::getRoutes()->getRoutes();
        $expectedMethods = 0;
        $expectedSignatures = [];
        foreach ($expectedRoutes as $route) {
            foreach ($route->methods() as $method) {
                $expectedMethods++;
                $expectedSignatures[] = implode('|', [
                    $method,
                    '/' . ltrim($route->uri(), '/'),
                    $route->getName() ?? 'unnamed',
                ]);
            }
        }

        $this->assertStringContainsString(
            '## 项目2当前运行时路由方法详细执行链（含旧 URI 兼容入口）',
            $report
        );
        foreach ([
            '## 项目1：旧项目逻辑基线',
            '## 项目2：新项目闭环实现',
            '## 项目1与项目2总逻辑汇总',
            '## 返回结果中文含义总表',
        ] as $section) {
            $this->assertStringContainsString($section, $report, $section . ' is missing');
        }
        foreach ([
            '中间件链',
            'Controller/源码位置',
            'Service/Support/Model/DB 链',
            '外部依赖',
            '成功分支',
            '失败分支',
            '前端消费',
            '测试证据',
        ] as $field) {
            $this->assertStringContainsString($field, $report, $field . ' is missing');
        }

        preg_match_all('/^### \d+\. /m', $report, $currentHeadingMatches);
        $this->assertCount(
            $expectedMethods,
            $currentHeadingMatches[0],
            'The report must contain exactly one ### heading per registered HTTP route method.'
        );

        preg_match_all(
            '/^### (\d+)\. ([A-Z]+) `([^`]*)` \(`([^`]*)`\)\r?$/m',
            $report,
            $headingMatches,
            PREG_SET_ORDER
        );
        $this->assertCount($expectedMethods, $headingMatches);

        $actualNumbers = [];
        $actualSignatures = [];
        foreach ($headingMatches as $heading) {
            $actualNumbers[] = (int) $heading[1];
            $actualSignatures[] = implode('|', [$heading[2], $heading[3], $heading[4]]);
        }
        $this->assertSame(range(1, $expectedMethods), $actualNumbers);

        sort($expectedSignatures);
        sort($actualSignatures);
        $this->assertSame(
            $expectedSignatures,
            $actualSignatures,
            'Every generated heading must map to exactly one current route method.'
        );

        $closureSources = [];
        foreach ([['GET', '/'], ['GET', '/admin/admins']] as [$method, $uri]) {
            $route = $this->findRoute($expectedRoutes, $method, $uri);
            $uses = $route->getAction('uses');
            $this->assertInstanceOf(\Closure::class, $uses, $method . ' ' . $uri);

            $reflection = new \ReflectionFunction($uses);
            $source = $this->relativeSourcePath(
                (string) $reflection->getFileName(),
                $reflection->getStartLine()
            );
            $entryBlock = $this->routeEntryBlock(
                $report,
                $method,
                $uri,
                $route->getName() ?? 'unnamed'
            );

            $this->assertStringContainsString(
                '- Controller/源码位置：`Closure` -> `' . $source . '`',
                $entryBlock,
                $method . ' ' . $uri . ' must use its own runtime Closure reflection.'
            );
            $closureSources[] = $source;
        }
        $this->assertNotSame(
            $closureSources[0],
            $closureSources[1],
            'Distinct Closure routes must not share a cached source analysis.'
        );

        $entryBlocks = preg_split('/(?=^### \d+\. )/m', $report, -1, PREG_SPLIT_NO_EMPTY);
        $entryBlocks = array_values(array_filter(
            $entryBlocks ?: [],
            static fn (string $block): bool => str_starts_with($block, '### ')
        ));
        $this->assertCount($expectedMethods, $entryBlocks);
        foreach ($entryBlocks as $entryBlock) {
            foreach ([
                'Controller/',
                'Service/Support/Model/DB',
                '业务目的',
                '解决问题',
                '步骤注释',
                '返回结果中文含义',
                'HTTP ',
                '->',
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $entryBlock, $fragment . ' is missing from an entry');
            }
            $this->assertGreaterThanOrEqual(
                14,
                preg_match_all('/^- /m', $entryBlock),
                'Each route method entry must expose all execution-chain fields.'
            );
            $this->assertMatchesRegularExpression(
                '/^- .*HTTP [^`]+ -> [^`]+`\r?$/mu',
                $entryBlock,
                'Each route method entry must include a concrete detailed execution chain.'
            );
        }
        $this->assertStringContainsString('routes=', (string) $stdout);
        $this->assertStringContainsString('methods=', (string) $stdout);
        $this->assertStringContainsString('routes=' . count($expectedRoutes), (string) $stdout);
            $this->assertStringContainsString('methods=' . $expectedMethods, (string) $stdout);
        } finally {
            if (is_string($outputPath) && is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
    }

    public function test_report_keeps_route_specific_closure_return_branches(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();

        $root = $this->findRoute($routes, 'GET', '/');
        $admin = $this->findRoute($routes, 'GET', '/admin/admins');
        $rootBlock = $this->routeEntryBlock($report, 'GET', '/', $root->getName() ?? 'unnamed');
        $adminBlock = $this->routeEntryBlock($report, 'GET', '/admin/admins', $admin->getName() ?? 'unnamed');

        $this->assertStringContainsString("return redirect()->route('front_page_login',", $rootBlock);
        $this->assertStringContainsString("'langId' => request()->input('langId', '1')", $rootBlock);
        $this->assertStringNotContainsString('return redirect(...)', $rootBlock);
        $this->assertStringContainsString("return view('admin_layui::admins.index');", $adminBlock);
        $this->assertStringNotContainsString("front_page_login", $adminBlock);
    }

    public function test_report_traces_legacy_admin_dispatch_to_the_current_target(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();
        $route = $this->findRoute($routes, 'POST', '/index/admin/cancel/cancel_apply_pass');
        $block = $this->routeEntryBlock(
            $report,
            'POST',
            '/index/admin/cancel/cancel_apply_pass',
            $route->getName() ?? 'unnamed'
        );

        $this->assertStringContainsString('admin_api_cancelApplyApprove', $block);
        $this->assertStringContainsString('CancelApplyController', $block);
        $this->assertStringContainsString('Mt4ManagerService', $block);
        $this->assertStringNotContainsString('HTTP 3xx 重定向', $block);
        $this->assertStringNotContainsString('未发现直接 Service/Support/Model/DB 调用', $block);
    }

    public function test_report_traces_legacy_admin_non_api_response_branches(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();

        $this->assertStringContainsString(
            '已注册兼容路由进入 `LegacyAdminController::handle` 后，如目标映射缺失且请求为非 GET/HEAD，Controller fallback 返回 HTTP 410 JSON',
            $report
        );
        $this->assertStringContainsString(
            'Laravel Router 对未注册 URI（例如 `/index/admin/unmapped`）返回 HTTP 404',
            $report
        );
        $this->assertStringContainsString(
            '不会进入数据库或外部资金写链',
            $report
        );

        $login = $this->findRoute($routes, 'GET', '/index/admin/login');
        $loginBlock = $this->routeEntryBlock($report, 'GET', '/index/admin/login', $login->getName() ?? 'unnamed');
        $this->assertStringContainsString('成功返回 HTML/Blade 页面', $loginBlock);
        $this->assertStringContainsString('`admin_layui::auth.login`', $loginBlock);
        $this->assertStringNotContainsString('成功返回 JSON', $loginBlock);

        $captcha = $this->findRoute($routes, 'GET', '/index/admin/captcha');
        $captchaBlock = $this->routeEntryBlock($report, 'GET', '/index/admin/captcha', $captcha->getName() ?? 'unnamed');
        $this->assertStringContainsString('image/png', $captchaBlock);
        $this->assertStringContainsString('PNG', $captchaBlock);
        $this->assertStringContainsString('captcha Session', $captchaBlock);
        $this->assertStringContainsString('captcha_<md5(hash)> Cache', $captchaBlock);
        $this->assertStringNotContainsString('成功返回 JSON', $captchaBlock);

        $logout = $this->findRoute($routes, 'GET', '/index/admin/logout');
        $logoutBlock = $this->routeEntryBlock($report, 'GET', '/index/admin/logout', $logout->getName() ?? 'unnamed');
        $this->assertStringContainsString('无 Bearer', $logoutBlock);
        $this->assertStringContainsString('HTTP 3xx', $logoutBlock);
        $this->assertStringContainsString('admin_api_logout', $logoutBlock);
        $this->assertStringNotContainsString('成功返回 JSON', $logoutBlock);

        $page = $this->findRoute($routes, 'GET', '/index/admin/Administrators/add');
        $pageBlock = $this->routeEntryBlock($report, 'GET', '/index/admin/Administrators/add', $page->getName() ?? 'unnamed');
        $this->assertStringContainsString('成功返回 HTML/Blade 页面', $pageBlock);
        $this->assertStringContainsString('`admin_layui::admins.index`', $pageBlock);
        $this->assertStringNotContainsString('成功返回 JSON', $pageBlock);

        foreach ($routes as $legacyRoute) {
            $legacyName = (string) ($legacyRoute->getName() ?? '');
            if (!str_starts_with($legacyName, 'legacy_')) {
                continue;
            }

            foreach ($legacyRoute->methods() as $legacyMethod) {
                $legacyUri = '/' . ltrim($legacyRoute->uri(), '/');
                $legacyBlock = $this->routeEntryBlock(
                    $report,
                    $legacyMethod,
                    $legacyUri,
                    $legacyName
                );
                $evidence = $this->reportField($legacyBlock, '测试证据');
                $this->assertMatchesRegularExpression(
                    '/(?:测试请求证据|共享契约证据)/u',
                    $evidence,
                    $legacyMethod . ' ' . $legacyUri . ' must expose direct or shared legacy-route test evidence.'
                );
            }
        }
    }

    public function test_report_overview_distinguishes_router_fallback_and_side_effect_boundaries(): void
    {
        $report = $this->generateReport();

        $this->assertStringContainsString(
            '已注册兼容路由进入 `LegacyAdminController::handle` 后，如目标映射缺失且请求为非 GET/HEAD，Controller fallback 返回 HTTP 410 JSON',
            $report
        );
        $this->assertStringContainsString(
            'Laravel Router 对未注册 URI（例如 `/index/admin/unmapped`）返回 HTTP 404；只有直接调用 `LegacyAdminController::handle` 的 fallback 分支才返回 HTTP 410 JSON',
            $report
        );
        $this->assertStringNotContainsString(
            '未映射的旧后台非 GET/HEAD 写请求返回 HTTP 410 JSON，表示入口已停止',
            $report
        );

        $this->assertStringContainsString(
            '资金链路按具体模块使用 outbox/state machine 处理异步一致性',
            $report
        );
        $this->assertStringContainsString(
            '部分 MT4 管理命令仍由 `Mt4ManagerService` 在请求链内同步调用，只有源码明确写入 outbox 的 MT4 流程才经过异步 worker',
            $report
        );
        $this->assertStringNotContainsString(
            '资金和 MT4 副作用统一通过 outbox/state machine',
            $report
        );
    }

    public function test_report_evidence_points_to_request_lines_instead_of_redirect_assertions(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();

        $adminEvidencePath = base_path('tests/Feature/AdminLegacyRouteSemanticClosureTest.php');
        $logoutRequestLine = $this->sourceLineNumber(
            $adminEvidencePath,
            "->get('/index/admin/logout')"
        );
        $adminLoginRedirectLine = $this->sourceLineNumber(
            $adminEvidencePath,
            "assertRedirect('/admin/login')"
        );
        $adminLogin = $this->findRoute($routes, 'GET', '/admin/login');
        $adminLoginBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/admin/login',
            $adminLogin->getName() ?? 'unnamed'
        );
        $adminLoginEvidence = $this->reportField($adminLoginBlock, '测试证据');
        $this->assertStringContainsString(
            'tests/Feature/AdminLegacyRouteSemanticClosureTest.php:' . $logoutRequestLine,
            $adminLoginEvidence
        );
        $this->assertStringNotContainsString(
            'tests/Feature/AdminLegacyRouteSemanticClosureTest.php:' . $adminLoginRedirectLine,
            $adminLoginEvidence
        );

        $frontRedirectPath = base_path('tests/Feature/FrontLegacyRouteCompatibilityTest.php');
        $frontDepositRedirectLine = $this->sourceLineNumber(
            $frontRedirectPath,
            "assertRedirect('/front/deposit?gateway=legacy_default&status=pending')"
        );
        $frontRequestPath = base_path('tests/Feature/LegacyUiReplacementCoverageTest.php');
        $frontDepositRequestLine = $this->sourceLineNumber(
            $frontRequestPath,
            "get('/front/deposit?frame=1')"
        );
        $frontDeposit = $this->findRoute($routes, 'GET', '/front/deposit');
        $frontDepositBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/front/deposit',
            $frontDeposit->getName() ?? 'unnamed'
        );
        $frontDepositEvidence = $this->reportField($frontDepositBlock, '测试证据');
        $this->assertStringContainsString(
            'tests/Feature/LegacyUiReplacementCoverageTest.php:' . $frontDepositRequestLine,
            $frontDepositEvidence
        );
        $this->assertStringNotContainsString(
            'tests/Feature/FrontLegacyRouteCompatibilityTest.php:' . $frontDepositRedirectLine,
            $frontDepositEvidence
        );
    }

    public function test_report_generator_defaults_to_the_required_chinese_report_path(): void
    {
        $source = (string) file_get_contents(base_path('scripts/generate-full-route-execution-chain-report.php'));

        $this->assertStringContainsString(
            "base_path('docs/reports/项目一项目二总逻辑与全量执行链路中文报告.md')",
            $source
        );
        $this->assertStringNotContainsString(
            "base_path('docs/reports/2026-07-19-full-route-execution-chain-report.md')",
            $source
        );
    }

    public function test_report_records_final_goal_metrics_root_route_and_exact_result_meanings(): void
    {
        $report = $this->generateReport(['123456', '789012']);
        $projectOneStart = strpos($report, '## 项目1旧路由逐项对照执行链');
        $projectTwoStart = strpos($report, '## 项目2当前运行时路由方法详细执行链', (int) $projectOneStart);

        $this->assertIsInt($projectOneStart, '项目1逐路由章节缺失。');
        $this->assertIsInt($projectTwoStart, '项目2逐路由章节缺失。');
        $projectOne = substr(
            $report,
            (int) $projectOneStart,
            (int) $projectTwoStart - (int) $projectOneStart
        );

        $this->legacyRouteEntryBlock($projectOne, 'GET', '/');
        $this->assertStringNotContainsString('GET ``', $projectOne);
        $this->assertStringContainsString('- 本目标总耗时（秒）：123456', $report);
        $this->assertStringContainsString('- 本目标总 Token 消耗量：789012', $report);
        $this->assertStringContainsString('`3005`：批量操作成功', $report);
        $this->assertStringContainsString('`3006`：批量操作部分失败', $report);
        $this->assertStringContainsString('`4005`：参数校验失败', $report);
        $this->assertStringContainsString('HTTP `405`：请求方法不允许，未进入写入链路', $report);
        $this->assertStringContainsString('HTTP `410`：旧入口已停止，无安全等价实现且未写入数据', $report);
    }

    public function test_report_emits_one_project_one_entry_for_every_legacy_route_method(): void
    {
        $report = $this->generateReport();
        $legacyRoutes = json_decode(
            (string) file_get_contents(storage_path('app/audits/legacy-routes.json')),
            true
        );
        $this->assertIsArray($legacyRoutes);

        $legacyMethodCount = 0;
        foreach ($legacyRoutes as $legacyRoute) {
            $legacyMethodCount += count((array) ($legacyRoute['methods'] ?? []));
        }

        $sectionStart = strpos($report, '## 项目1旧路由逐项对照执行链');
        $sectionEnd = strpos($report, '## 项目2当前运行时路由方法详细执行链', (int) $sectionStart);
        $this->assertIsInt($sectionStart, 'Project one route section is missing.');
        $this->assertIsInt($sectionEnd, 'Project two route section boundary is missing.');
        $section = substr($report, (int) $sectionStart, (int) $sectionEnd - (int) $sectionStart);

        preg_match_all(
            '/^#### (\d+)\. ([A-Z]+) `([^`]*)` \(`([^`]*)`\)/m',
            $section,
            $headings,
            PREG_SET_ORDER
        );
        $this->assertCount($legacyMethodCount, $headings);
        $this->assertSame(
            range(1, $legacyMethodCount),
            array_map(static fn (array $heading): int => (int) $heading[1], $headings)
        );

        $blocks = preg_split('/(?=^#### \d+\. )/m', $section, -1, PREG_SPLIT_NO_EMPTY);
        $blocks = array_values(array_filter(
            $blocks ?: [],
            static fn (string $block): bool => str_starts_with($block, '#### ')
        ));
        $this->assertCount($legacyMethodCount, $blocks);

        foreach ($blocks as $block) {
            foreach ([
                '旧方法/URI',
                '旧 name/action',
                '项目1源码位置',
                '项目1静态 middleware',
                '项目1 Blade/JS/请求字段证据',
                '项目1 Model/表证据',
                '项目2映射 route/action/status',
                '业务目的',
                '解决问题',
                '步骤注释',
                '返回结果中文含义',
                '成功结果中文含义',
                '失败结果中文含义',
                '详细执行链',
            ] as $field) {
                $this->assertStringContainsString('- ' . $field . '：', $block, $field . ' is missing');
            }
            $this->assertStringContainsString('未检索到', $block);
        }

        $loginBlock = $this->legacyRouteEntryBlock($section, 'GET', 'agents/login');
        $this->assertStringContainsString('Admin\\BigNumberController@agentsLogin', $loginBlock);
        $this->assertStringContainsString('app/Http/routes.php:22', $loginBlock);
        $this->assertStringContainsString('resources/views/user/login/login_gmtk.blade.php', $loginBlock);
        $this->assertStringNotContainsString('olduserpsw', $loginBlock);
    }

    public function test_report_translates_legacy_big_agent_password_numeric_codes(): void
    {
        $report = $this->generateReport();
        $legacyRoutesSectionStart = strpos($report, '## 项目1旧路由逐项对照执行链');
        $projectTwoStart = strpos($report, '## 项目2当前运行时路由方法详细执行链', (int) $legacyRoutesSectionStart);
        $section = substr(
            $report,
            (int) $legacyRoutesSectionStart,
            (int) $projectTwoStart - (int) $legacyRoutesSectionStart
        );
        $block = $this->legacyRouteEntryBlock($section, 'POST', 'user/agents/changePassword');

        foreach ([
            '`0`=修改成功',
            '`1000`=系统错误或参数校验失败',
            '`1010`=用户不存在',
            '`1011`=旧密码错误',
        ] as $meaning) {
            $this->assertStringContainsString($meaning, $block, $meaning . ' is missing');
        }
        $this->assertStringContainsString('新密码长度不足6位或与旧密码相同', $block);
    }

    public function test_report_distinguishes_legacy_payment_notify_from_return(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();
        $notify = $this->findRoute($routes, 'POST', '/user/deposit_btb_notify');
        $return = $this->findRoute($routes, 'GET', '/user/deposit_btb_return');
        $notifyBlock = $this->routeEntryBlock($report, 'POST', '/user/deposit_btb_notify', $notify->getName() ?? 'unnamed');
        $returnBlock = $this->routeEntryBlock($report, 'GET', '/user/deposit_btb_return', $return->getName() ?? 'unnamed');

        $this->assertStringNotContainsString('HTTP 3xx 重定向', $notifyBlock);
        $this->assertStringContainsString('invalid_signature', $notifyBlock);
        $this->assertStringContainsString('PaymentCallbackService', $notifyBlock);
        $this->assertStringContainsString('DB transaction', $notifyBlock);
        $this->assertStringContainsString('FOR UPDATE', $notifyBlock);
        $this->assertStringContainsString('队列/Outbox', $notifyBlock);
        $this->assertStringContainsString('HTTP 3xx 重定向', $returnBlock);
        $this->assertStringContainsString('front_page_deposit', $returnBlock);
        $this->assertStringNotContainsString('DB transaction', $returnBlock);

        $modernNotify = $this->findRoute($routes, 'POST', '/api/front/payment/notify/{gateway}');
        $modernReturn = $this->findRoute($routes, 'GET', '/api/front/payment/return/{gateway}');
        $modernNotifyBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/front/payment/notify/{gateway}',
            $modernNotify->getName() ?? 'unnamed'
        );
        $modernReturnBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/api/front/payment/return/{gateway}',
            $modernReturn->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('通道 ACK', $modernNotifyBlock);
        $this->assertStringNotContainsString('成功返回 JSON', $modernNotifyBlock);
        $this->assertStringContainsString('浏览器同步返回', $modernReturnBlock);
        $this->assertStringNotContainsString('异步通知', $modernReturnBlock);
    }

    public function test_report_marks_head_as_headers_only_and_translates_numeric_response_codes(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();
        $head = $this->findRoute($routes, 'HEAD', '/admin/admins');
        $headBlock = $this->routeEntryBlock($report, 'HEAD', '/admin/admins', $head->getName() ?? 'unnamed');
        $risk = $this->findRoute($routes, 'POST', '/api/admin/riskForceClose/{id}');
        $riskBlock = $this->routeEntryBlock($report, 'POST', '/api/admin/riskForceClose/{id}', $risk->getName() ?? 'unnamed');

        $this->assertStringContainsString('仅返回响应头', $headBlock);
        $this->assertStringNotContainsString('HTML/Blade 页面', $headBlock);
        $this->assertStringNotContainsString('返回 JSON', $headBlock);
        $this->assertStringContainsString('`MT4_SYNC_FAILED`=MT4 同步失败', $riskBlock);
        $this->assertStringNotContainsString('`MT`=业务结果', $riskBlock);
        $this->assertStringContainsString('Mt4RiskForceCloseGateway', $riskBlock);
        $this->assertStringContainsString('Mt4ManagerService', $riskBlock);

        $avatar = $this->findRoute($routes, 'POST', '/api/front/profile/avatar');
        $avatarBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/front/profile/avatar',
            $avatar->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('`UPLOADED`=上传成功', $avatarBlock);

        $transfer = $this->findRoute($routes, 'POST', '/api/front/customers/commission-transfers');
        $transferBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/front/customers/commission-transfers',
            $transfer->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('`INSUFFICIENT_BALANCE`=余额不足', $transferBlock);
        $this->assertStringContainsString('app/Http/routes.php', $report);

        $inviter = $this->findRoute($routes, 'GET', '/api/front/auth/inviter');
        $inviterBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/api/front/auth/inviter',
            $inviter->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('FrontRegisterRuleService', $inviterBlock);
        $this->assertStringContainsString('Models\\UserLogin` -> table `user_logins`', $inviterBlock);
        $this->assertStringContainsString('Models\\UserInfo` -> table `user_infos`', $inviterBlock);

        foreach ([
            ['POST', '/api/admin/exportAgents'],
            ['POST', '/api/admin/exportDepositImports'],
        ] as [$method, $uri]) {
            $route = $this->findRoute($routes, $method, $uri);
            $block = $this->routeEntryBlock($report, $method, $uri, $route->getName() ?? 'unnamed');
            $this->assertStringContainsString('成功返回文件或数据流', $block, $method . ' ' . $uri);
            $this->assertStringNotContainsString('成功返回 JSON', $block, $method . ' ' . $uri);
        }
    }

    public function test_report_records_legacy_admin_method_boundaries_and_special_head_side_effects(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();

        $mutation = $this->findRoute($routes, 'HEAD', '/index/admin/Administrators/del');
        $mutationBlock = $this->routeEntryBlock(
            $report,
            'HEAD',
            '/index/admin/Administrators/del',
            $mutation->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('HTTP 405', $mutationBlock);
        $this->assertStringContainsString('OPERATION_NOT_ALLOWED', $mutationBlock);
        $this->assertStringContainsString('Allow: POST', $mutationBlock);
        $this->assertStringContainsString('不会进入现代 POST 写链', $mutationBlock);
        $this->assertStringNotContainsString('已执行目标 action 的数据库写入', $mutationBlock);

        $projectOneStart = strpos($report, '## 项目1旧路由逐项对照执行链');
        $projectTwoStart = strpos($report, '## 项目2当前运行时路由方法详细执行链', (int) $projectOneStart);
        $projectOne = substr($report, (int) $projectOneStart, (int) $projectTwoStart - (int) $projectOneStart);
        $legacyMutationBlock = $this->legacyRouteEntryBlock(
            $projectOne,
            'HEAD',
            'index/admin/Administrators/del'
        );
        $this->assertStringContainsString('HTTP 405', $legacyMutationBlock);
        $this->assertStringContainsString('OPERATION_NOT_ALLOWED', $legacyMutationBlock);
        $this->assertStringContainsString('不会进入现代 POST 写链', $legacyMutationBlock);

        $captcha = $this->findRoute($routes, 'HEAD', '/index/admin/captcha');
        $captchaBlock = $this->routeEntryBlock(
            $report,
            'HEAD',
            '/index/admin/captcha',
            $captcha->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('HEAD 仍执行验证码生成并写入 captcha Session/Cache', $captchaBlock);
        $this->assertStringContainsString('仅移除响应正文', $captchaBlock);
        $this->assertStringNotContainsString('无响应体探测', $captchaBlock);
        $this->assertStringNotContainsString('仅返回响应头', $captchaBlock);

        $logout = $this->findRoute($routes, 'HEAD', '/index/admin/logout');
        $logoutBlock = $this->routeEntryBlock(
            $report,
            'HEAD',
            '/index/admin/logout',
            $logout->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('HEAD 仍执行 admin guard 注销和 Session 清理', $logoutBlock);
        $this->assertStringContainsString('仅移除响应正文', $logoutBlock);
        $this->assertStringNotContainsString('无响应体探测', $logoutBlock);
        $this->assertStringNotContainsString('仅返回响应头', $logoutBlock);
    }

    public function test_report_does_not_present_mutually_exclusive_payment_adapters_as_a_chain(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();
        $route = $this->findRoute($routes, 'POST', '/api/front/deposits/submissions');
        $block = $this->routeEntryBlock($report, 'POST', '/api/front/deposits/submissions', $route->getName() ?? 'unnamed');

        $this->assertStringNotContainsString('Models\\BaseModel` -> table `base_models`', $block);
        $this->assertFalse(
            str_contains($block, 'Gateways\\BtbAdapter') && str_contains($block, 'Gateways\\WpPayAdapter'),
            'A single request must not claim to execute mutually exclusive payment adapters.'
        );

        $deposit = $this->findRoute($routes, 'POST', '/api/front/deposits/submissions');
        $depositBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/front/deposits/submissions',
            $deposit->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('PaymentOrderService', $depositBlock);
        $this->assertStringContainsString('DB transaction', $depositBlock);
        $this->assertStringContainsString('FOR UPDATE', $depositBlock);
    }

    public function test_report_keeps_dependency_and_evidence_scoped_to_the_current_http_method(): void
    {
        $fixtureName = 'SyntheticRouteEvidenceProbeTest_' . getmypid() . '_' . bin2hex(random_bytes(4)) . '.php';
        $fixturePath = base_path('tests/Feature/' . $fixtureName);
        $fixture = <<<'PHP'
<?php

$requestLookingString = '$this->postJson("/api/admin/commission-transfers/reconciliation-cases")';
$cache->get('/front/login');
PHP;
        try {
            $this->assertNotFalse(file_put_contents($fixturePath, $fixture));
            $report = $this->generateReport();
        } finally {
            @unlink($fixturePath);
        }
        $routes = Route::getRoutes()->getRoutes();

        $list = $this->findRoute($routes, 'POST', '/api/admin/commission-transfers/reconciliation-cases');
        $listBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/admin/commission-transfers/reconciliation-cases',
            $list->getName() ?? 'unnamed'
        );
        $this->assertStringNotContainsString('DB transaction', $listBlock);
        $this->assertStringNotContainsString('FOR UPDATE', $listBlock);
        $this->assertStringNotContainsString('OperationLog', $listBlock);
        $this->assertStringNotContainsString(
            'AdminCommissionTransferReconciliationMigrationRuntimeTest.php',
            $listBlock
        );
        $this->assertStringNotContainsString($fixtureName, $listBlock);

        $frontLogin = $this->findRoute($routes, 'GET', '/front/login');
        $frontLoginBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/front/login',
            $frontLogin->getName() ?? 'unnamed'
        );
        $this->assertStringNotContainsString($fixtureName, $frontLoginBlock);
        $this->assertStringNotContainsString('ExampleTest.php:19', $frontLoginBlock);

        $getDetail = $this->findRoute(
            $routes,
            'GET',
            '/api/admin/commission-transfers/reconciliation-cases/{transfer}'
        );
        $getBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/api/admin/commission-transfers/reconciliation-cases/{transfer}',
            $getDetail->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('pages.js', $getBlock);
        $this->assertStringContainsString('AdminCommissionTransferReconciliationClosureModuleTest.php', $getBlock);
        $this->assertMatchesRegularExpression('/pages\.js:\d+`（GET 前端请求证据）/', $getBlock);

        $head = $this->findRoute(
            $routes,
            'HEAD',
            '/api/admin/commission-transfers/reconciliation-cases/{transfer}'
        );
        $headBlock = $this->routeEntryBlock(
            $report,
            'HEAD',
            '/api/admin/commission-transfers/reconciliation-cases/{transfer}',
            $head->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('- 前端消费：未检索到', $headBlock);
        $this->assertStringContainsString('- 测试证据：未检索到', $headBlock);

        $generator = (string) file_get_contents(base_path('scripts/generate-full-route-execution-chain-report.php'));
        $this->assertStringNotContainsString('$genericMatches', $generator);
    }

    public function test_report_explains_each_route_instead_of_reusing_group_level_templates(): void
    {
        $report = $this->generateReport();
        $routes = Route::getRoutes()->getRoutes();

        $this->assertTrue(
            mb_check_encoding($report, 'UTF-8'),
            'The generated Chinese report must remain valid UTF-8 before Unicode route parsing.'
        );

        preg_match_all(
            '/^### \d+\. (?<method>[A-Z]+) `(?<uri>[^`]*)` \(`[^`]*`\)\r?\n(?<block>.*?)(?=^### |\z)/msu',
            $report,
            $entries,
            PREG_SET_ORDER
        );
        $expectedMethodCount = array_sum(array_map(
            static fn (\Illuminate\Routing\Route $route): int => count($route->methods()),
            $routes
        ));
        $this->assertCount($expectedMethodCount, $entries);

        foreach ($entries as $entry) {
            $signature = 'HTTP ' . $entry['method'] . ' `' . $entry['uri'] . '`';
            $this->assertMatchesRegularExpression(
                '/^- 业务目的：' . preg_quote($signature, '/') . '.+$/mu',
                $entry['block'],
                $signature . ' must explain its own purpose instead of only describing the route group.'
            );
            $this->assertMatchesRegularExpression(
                '/^- 解决问题：' . preg_quote($signature, '/') . '.+$/mu',
                $entry['block'],
                $signature . ' must explain the concrete problem solved by this route.'
            );
        }

        $login = $this->findRoute($routes, 'POST', '/api/front/auth/login');
        $loginBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/front/auth/login',
            $login->getName() ?? 'unnamed'
        );
        $this->assertMatchesRegularExpression('/^- 业务目的：.*登录/mu', $loginBlock);
        $this->assertMatchesRegularExpression('/^- 返回结果中文含义：.*JSON.*业务码/mu', $loginBlock);

        $adminBinding = $this->findRoute($routes, 'POST', '/api/admin/adminAgentBindingList');
        $adminBindingBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/admin/adminAgentBindingList',
            $adminBinding->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('- 成功分支：`return $this->success(...)`', $adminBindingBlock);
        $this->assertStringNotContainsString('`return $this->success([`', $adminBindingBlock);

        $adminPage = $this->findRoute($routes, 'GET', '/admin/admins');
        $adminPageBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/admin/admins',
            $adminPage->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('成功返回 HTML/Blade 页面', $adminPageBlock);
        $this->assertStringContainsString('`admin_layui::admins.index`', $adminPageBlock);
        $this->assertStringNotContainsString('未知外部结果进入人工核对或安全重试', $adminPageBlock);

        $frontLogin = $this->findRoute($routes, 'GET', '/front/login');
        $frontLoginBlock = $this->routeEntryBlock(
            $report,
            'GET',
            '/front/login',
            $frontLogin->getName() ?? 'unnamed'
        );
        $this->assertStringContainsString('`front_layui::auth.login`', $frontLoginBlock);
        $this->assertMatchesRegularExpression('/^- 业务目的：.*渲染.*登录.*页面/mu', $frontLoginBlock);
        $this->assertStringNotContainsString('登录凭据并返回认证结果', $frontLoginBlock);

        foreach ([
            ['POST', '/api/admin/reviewAuth'],
            ['GET', '/api/front/account/balance'],
            ['GET', '/api/front/account/profile'],
        ] as [$method, $uri]) {
            $route = $this->findRoute($routes, $method, $uri);
            $block = $this->routeEntryBlock($report, $method, $uri, $route->getName() ?? 'unnamed');
            $this->assertStringContainsString('成功返回 JSON', $block, $method . ' ' . $uri);
            $this->assertStringNotContainsString('成功返回 HTML/Blade 页面', $block, $method . ' ' . $uri);
        }

        $dashboard = $this->findRoute($routes, 'POST', '/api/admin/dashboard');
        $dashboardBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/api/admin/dashboard',
            $dashboard->getName() ?? 'unnamed'
        );
        $this->assertMatchesRegularExpression('/^- 业务目的：.*查询.*仪表盘/mu', $dashboardBlock);
        $this->assertStringNotContainsString('提交或创建', $dashboardBlock);

        $legacyApprove = $this->findRoute($routes, 'POST', '/index/admin/cancel/cancel_apply_pass');
        $legacyApproveBlock = $this->routeEntryBlock(
            $report,
            'POST',
            '/index/admin/cancel/cancel_apply_pass',
            $legacyApprove->getName() ?? 'unnamed'
        );
        $this->assertMatchesRegularExpression('/^- 业务目的：.*审批.*注销申请/mu', $legacyApproveBlock);

        foreach ([
            ['POST', '/api/admin/createBlacklist', '创建', '查询'],
            ['POST', '/api/admin/updateBlacklist/{id}', '更新', '查询'],
            ['POST', '/api/admin/deleteBlacklist/{id}', '删除', '查询'],
            ['POST', '/api/admin/creditImportTemplate', '下载', '更新'],
            ['POST', '/api/admin/depositImportTemplate', '下载', '提交或创建'],
            ['POST', '/api/admin/withdrawImportTemplate', '下载', '提交或创建'],
            ['POST', '/api/admin/bigNumberTrend', '查询', '提交或创建'],
            ['POST', '/api/admin/cancelApplyList', '查询', '取消或申请取消'],
            ['POST', '/api/admin/createCreditImport', '创建', '更新'],
            ['POST', '/api/admin/updateProfile', '更新', '查询并展示'],
            ['PATCH', '/api/front/profile', '更新', '查询并展示'],
            ['POST', '/api/front/profile/bank-card', '提交', '查询并展示'],
            ['POST', '/api/front/profile/contact-info', '更新', '查询并展示'],
            ['POST', '/api/front/profile/email', '更新', '查询并展示'],
            ['POST', '/api/front/profile/phone', '更新', '查询并展示'],
            ['POST', '/api/front/profile/password', '更新', '查询并展示'],
            ['POST', '/api/front/profile/avatar', '上传', '查询并展示'],
            ['POST', '/api/front/profile/identity', '提交', '查询并展示'],
            ['POST', '/api/front/profile/bank-card-change', '提交', '查询并展示'],
            ['POST', '/api/front/profile/identity-card-uploads', '上传', '更新个人资料'],
            ['POST', '/api/front/profile/bank-card-uploads', '上传', '更新个人资料'],
            ['POST', '/api/front/profile/bank-card-change-uploads', '上传', '更新个人资料'],
            ['POST', '/api/front/profile/bank-card-change/verification-checks', '校验', '更新个人资料'],
            ['POST', '/api/front/profile/bank-card-change/verification-codes', '发送', '审批'],
            ['POST', '/api/front/profile/verification-checks', '校验', '更新个人资料'],
            ['POST', '/api/front/profile/verification-password/verification-codes', '发送', '审批'],
            ['POST', '/api/front/profile/verification-cancellation-checks', '校验', '取消或申请取消'],
            ['POST', '/api/front/profile/verification-cancellation/verification-codes', '发送', '审批'],
            ['GET', '/api/front/commissions/transfer-agent-options', '查询', '校验并提交'],
            ['GET', '/api/front/users/login-history', '查询', '登录凭据'],
            ['POST', '/user/cust/loginHistorySearch/{uid}', '查询', '登录凭据'],
            ['POST', '/index/admin/Administrators/addsave', '创建', '更新管理员账号'],
            ['POST', '/index/admin/Administrators/editsave', '更新', '创建管理员账号'],
        ] as [$method, $uri, $expected, $forbidden]) {
            $route = $this->findRoute($routes, $method, $uri);
            $block = $this->routeEntryBlock($report, $method, $uri, $route->getName() ?? 'unnamed');
            $purpose = $this->reportField($block, '业务目的');
            $this->assertStringContainsString($expected, $purpose, $method . ' ' . $uri);
            $this->assertStringNotContainsString($forbidden, $purpose, $method . ' ' . $uri);
            if ($expected !== '查询') {
                $this->assertStringNotContainsString(
                    '执行“查询',
                    $purpose,
                    $method . ' ' . $uri . ' is a mutation and must not be introduced as a query.'
                );
            }
        }

        $this->assertDoesNotMatchRegularExpression(
            '/^- (?:成功|失败)分支：.*`[^`\r\n]*[\(\[\{]`/mu',
            $report,
            'Branch evidence must use a balanced summary instead of an unterminated source fragment.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^- (?:成功|失败)分支：.*`\s*[?:]\s*ResponseCode::/mu',
            $report,
            'Response codes belong in the translated code list, not as isolated ternary fragments.'
        );

        $this->assertStringContainsString(
            '项目1的旧路由用于逐项映射审计；下方逐方法执行链以项目2当前运行时路由表为准',
            $report
        );
        $this->assertStringContainsString(
            '条是项目1源码的静态映射审计结果，不代表旧项目 Controller 在报告生成时被执行',
            $report
        );
    }

    /** @param array<int, string> $arguments */
    private function generateReport(array $arguments = []): string
    {
        $outputPath = tempnam(sys_get_temp_dir(), 'crm-route-report-semantic-');
        $this->assertIsString($outputPath);
        @unlink($outputPath);

        $pipes = [];
        $process = proc_open(
            array_merge(
                [PHP_BINARY, base_path('scripts/generate-full-route-execution-chain-report.php'), $outputPath],
                $arguments
            ),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            getenv()
        );
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        try {
            $this->assertSame(0, $exitCode, $stderr ?: $stdout);
            $this->assertFileExists($outputPath);

            return (string) file_get_contents($outputPath);
        } finally {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
    }

    /** @param array<int, \Illuminate\Routing\Route> $routes */
    private function findRoute(array $routes, string $method, string $uri): \Illuminate\Routing\Route
    {
        foreach ($routes as $route) {
            $routeUri = '/' . ltrim($route->uri(), '/');
            if ($routeUri === $uri && in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        $this->fail('Missing runtime route ' . $method . ' ' . $uri . '.');
    }

    private function routeEntryBlock(string $report, string $method, string $uri, string $name): string
    {
        $heading = preg_quote($method . ' `' . $uri . '` (`' . $name . '`)', '/');
        $matched = preg_match(
            '/^### \d+\. ' . $heading . '\r?\n(?<block>.*?)(?=^### |\z)/msu',
            $report,
            $matches
        );
        $this->assertSame(1, $matched, 'Missing report block for ' . $method . ' ' . $uri . '.');

        return (string) ($matches['block'] ?? '');
    }

    private function legacyRouteEntryBlock(string $section, string $method, string $uri): string
    {
        $normalizedUri = '/' . ltrim($uri, '/');
        $heading = preg_quote($method . ' `' . $normalizedUri . '`', '/');
        $matched = preg_match(
            '/^#### \d+\. ' . $heading . '.*?\r?\n(?<block>.*?)(?=^#### |\z)/msu',
            $section,
            $matches
        );
        $this->assertSame(1, $matched, 'Missing project one report block for ' . $method . ' ' . $uri . '.');

        return (string) ($matches['block'] ?? '');
    }

    private function reportField(string $block, string $field): string
    {
        $matched = preg_match(
            '/^- ' . preg_quote($field, '/') . '：(?<value>.*)$/mu',
            $block,
            $matches
        );
        $this->assertSame(1, $matched, 'Missing report field ' . $field . '.');

        return trim((string) ($matches['value'] ?? ''));
    }

    private function relativeSourcePath(string $path, int $line): string
    {
        $base = str_replace('\\', '/', base_path()) . '/';
        $normalized = str_replace('\\', '/', $path);
        $relative = str_starts_with($normalized, $base)
            ? substr($normalized, strlen($base))
            : $normalized;

        return $relative . ':' . $line;
    }

    private function sourceLineNumber(string $path, string $needle): int
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines, 'Unable to read source evidence file: ' . $path);

        foreach ($lines as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index + 1;
            }
        }

        $this->fail('Unable to find source evidence line containing: ' . $needle);
    }
}
