<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * Blade 静态本地资源引用完整性测试。
 *
 * 文件功能：
 * - 扫描 resources/views、resources/front、resources/admin 下所有 blade 文件。
 * - 提取 asset() 调用与 src/href 中引用的本地 css/js/images 资源路径。
 * - 验证每个被引用的本地资源在 public 目录下真实存在。
 *
 * 适用场景：
 * - 防止 Blade 模板引用已删除或未发布的静态资源，导致页面 404。
 *
 * 入参例子：
 * - asset('/css/admin/style.css')、src="/js/apps/admin/layui/pages.js"。
 *
 * 返回值：
 * - 无缺失资源时断言通过；存在缺失时失败消息列出所有缺失项。
 *
 * 异常或失败场景：
 * - 任意 blade 引用不存在的本地资源即断言失败。
 */

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class BladeLocalAssetReferenceTest extends TestCase
{
    /**
     * 验证所有 blade 引用的本地静态资源文件均存在。
     */
    public function test_static_blade_local_assets_exist(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file->getPathname()) ?: '';

            foreach ($this->assetPaths($source) as $assetPath) {
                if (! file_exists(public_path($assetPath))) {
                    $missing[] = $this->relativePath($file) . ' => /' . $assetPath;
                }
            }
        }

        sort($missing);

        $this->assertSame([], array_values(array_unique($missing)), "Blade contains missing local assets:\n" . implode("\n", $missing));
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

    /**
     * @return array<int, string>
     */
    private function assetPaths(string $source): array
    {
        $paths = [];

        preg_match_all("/asset\(\s*['\"]\/?((?:css|js|images)\/[^'\"]+)['\"]\s*\)/", $source, $assetMatches);
        preg_match_all("/(?:src|href)=['\"]\/((?:css|js|images)\/[^'\"]+)['\"]/", $source, $absoluteMatches);

        foreach (array_merge($assetMatches[1] ?? [], $absoluteMatches[1] ?? []) as $path) {
            $paths[] = preg_replace('/[?#].*$/', '', $path);
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}
