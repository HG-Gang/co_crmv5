<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:34
 */

/**
 * scripts/export-legacy-routes.php 导出脚本(LegacyRouteExporterScriptTest)的集成测试。
 *
 * 文件功能:
 * - 用独立的 PHP 7.4 运行时引导旧版 Laravel 项目(legacyRoot),执行导出脚本,
 *   生成框架路由解析后的 legacy 路由 JSON,断言其中包含 user/login 路由且
 *   action 指向 App\Http\Controllers\User\LoginController@loginGmtk。
 *
 * 适用场景:旧版路由导出脚本或旧项目路由注册变化后回归,确保迁移审计
 * 依赖的路由清单仍能由脚本正确生成。
 *
 * 入参例子:脚本经 exec() 以 "php.exe scripts/export-legacy-routes.php --root=<旧项目根> --output=<输出文件>"
 * 方式调用;本测试依赖本机 PHP 7.4 可执行文件与旧项目目录存在。
 *
 * 返回值:子进程退出码 0 且输出 JSON 非空、包含目标路由时断言通过,表示导出链路闭环。
 *
 * 失败场景:退出码非 0、输出文件缺失或路由内容变化都会导致断言失败,
 * 通常意味着导出脚本报错、旧项目无法引导,或旧项目路由已被改动。
 */

namespace Tests\Feature;

use Tests\TestCase;

class LegacyRouteExporterScriptTest extends TestCase
{
    public function test_exporter_generates_framework_resolved_legacy_route_json(): void
    {
        $php = getenv('LEGACY_PHP74_BINARY') ?: 'D:\\Software\\PHP-TOOL\\phpStudy64\\phpstudy_pro\\Extensions\\php\\php7.4.3nts\\php.exe';
        $legacyRoot = getenv('LEGACY_PROJECT_ROOT') ?: dirname(base_path()) . DIRECTORY_SEPARATOR . 'new_co_gmtk_crmv3';
        $script = base_path('scripts/export-legacy-routes.php');
        $output = storage_path('framework/testing/legacy-routes-export-test.json');

        $this->assertFileExists($php, 'PHP 7.4 runtime is required to boot the legacy Laravel application.');
        $this->assertDirectoryExists($legacyRoot);
        $this->assertFileExists($script, 'Legacy route exporter script is missing.');

        if (is_file($output)) {
            unlink($output);
        }

        $command = sprintf(
            '"%s" "%s" "--root=%s" "--output=%s" 2>&1',
            $php,
            $script,
            $legacyRoot,
            $output
        );
        exec($command, $lines, $exitCode);

        $this->assertSame(0, $exitCode, implode(PHP_EOL, $lines));
        $this->assertFileExists($output, implode(PHP_EOL, [
            'Command: ' . $command,
            'Child output: ' . implode(' | ', $lines),
            'PHP cwd: ' . getcwd(),
            'Expected output: ' . $output,
        ]));

        $routes = json_decode((string) file_get_contents($output), true);
        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
        $this->assertTrue(collect($routes)->contains(function (array $route): bool {
            return $route['uri'] === 'user/login'
                && $route['action'] === 'App\\Http\\Controllers\\User\\LoginController@loginGmtk';
        }));

        unlink($output);
    }
}
