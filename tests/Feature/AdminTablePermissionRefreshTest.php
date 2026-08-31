<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:55
 */

/**
 * 后台 Layui 表格工具栏权限可见性刷新机制的功能测试。
 *
 * 文件功能：
 * - 扫描 resource_path('admin/layui') 下所有 blade 模板中的表格工具栏。
 * - 验证每个带 data-permission 且被表格引用的工具栏脚本都调用了 CrmAdminPermissions.refresh。
 *
 * 适用场景：
 * - 后台列表页按钮权限随表格重新渲染后仍能保持正确显隐。
 *
 * 入参例子：
 * - 无接口入参，测试直接遍历 admin/layui 下的 .blade.php 与对应 .js 脚本。
 *
 * 返回值：
 * - 用例通过即表示所有权限绑定工具栏均包含权限刷新调用。
 *
 * 异常或失败场景：
 * - 存在未调用 CrmAdminPermissions.refresh 的工具栏时断言失败并列出对应文件。
 */

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

class AdminTablePermissionRefreshTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    // 遍历收集到的权限工具栏用例，断言其脚本均包含权限可见性刷新调用。
    public function test_admin_table_action_toolbars_refresh_permission_visibility_after_render(): void
    {
        $cases = $this->collectPermissionToolbarCases();
        $missingRefreshCalls = [];

        $this->assertNotEmpty($cases, 'Admin Blade pages must expose at least one permission-bound Layui toolbar.');

        foreach ($cases as $toolbarId => $jsPath) {
            $jsContent = $this->adminLayuiScript($jsPath);

            if (strpos($jsContent, 'CrmAdminPermissions.refresh') === false) {
                $missingRefreshCalls[] = $toolbarId . ' => ' . $jsPath;
            }
        }

        $this->assertEmpty(
            $missingRefreshCalls,
            "These Layui table toolbars do not refresh permission visibility:\n" . implode("\n", $missingRefreshCalls)
        );
    }

    /**
     * @return array<string, string> key=toolbar id, value=admin layui relative script path
     */
    private function collectPermissionToolbarCases(): array
    {
        $cases = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('admin/layui')));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                continue;
            }

            $bladeContent = file_get_contents($file->getPathname()) ?: '';
            preg_match_all(
                '/<script\s+type="text\/html"\s+id="([^"]+)"[^>]*>([\s\S]*?)<\/script>/',
                $bladeContent,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $toolbarId = $match[1];
                $templateContent = $match[2];

                if (strpos($templateContent, 'data-permission=') === false) {
                    continue;
                }

                $jsPath = $this->resolveModuleJsPath($file->getPathname());
                $jsContent = $this->adminLayuiScript($jsPath);

                if ($jsContent === '') {
                    continue;
                }

                if (strpos($jsContent, "toolbar: '#" . $toolbarId . "'") === false) {
                    continue;
                }

                $cases[$toolbarId] = $jsPath;
            }
        }

        ksort($cases);

        return $cases;
    }

    private function resolveModuleJsPath(string $bladePath): string
    {
        $relativePath = str_replace(resource_path('admin/layui') . DIRECTORY_SEPARATOR, '', $bladePath);

        return str_replace(DIRECTORY_SEPARATOR, '/', str_replace('.blade.php', '.js', $relativePath));
    }
}
