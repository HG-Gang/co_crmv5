<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * Blade 静态路由名引用完整性测试。
 *
 * 文件功能：
 * - 扫描 resources/views、resources/front、resources/admin 下所有 blade 文件。
 * - 提取 route('name') 形式的静态路由名。
 * - 验证每个路由名都在当前路由表中真实注册。
 *
 * 适用场景：
 * - 防止 Blade 模板引用已删除或未注册的路由名，导致运行时异常。
 *
 * 入参例子：
 * - route('front_crmui_login')、route('admin.dashboard')。
 *
 * 返回值：
 * - 无缺失路由名时断言通过；存在缺失时失败消息列出所有缺失项。
 *
 * 异常或失败场景：
 * - 任意 blade 引用未注册的路由名即断言失败。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class BladeStaticRouteReferenceTest extends TestCase
{
    /**
     * 验证所有 blade 引用的静态路由名均已注册。
     */
    public function test_static_blade_route_references_exist(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file->getPathname()) ?: '';

            preg_match_all("/route\(\s*['\"]([A-Za-z0-9_.-]+)['\"]/", $source, $matches);

            foreach (array_unique($matches[1] ?? []) as $routeName) {
                if (! Route::has($routeName)) {
                    $missing[] = $this->relativePath($file) . ' => ' . $routeName;
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, "Blade contains missing static route references:\n" . implode("\n", $missing));
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function bladeFiles(): array
    {
        $files = [];

        foreach (['resources/views', 'resources/front', 'resources/admin'] as $directory) {
            $absoluteDirectory = base_path($directory);

            if (! is_dir($absoluteDirectory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && substr($file->getFilename(), -10) === '.blade.php') {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}
