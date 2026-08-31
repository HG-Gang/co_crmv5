<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 23:42
 */

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * 界面图标与表情符号策略测试。
 *
 * 文件功能：
 * - 约束 Blade、核心前端 JS、核心前端 CSS 不直接输出表情符号。
 * - 约束当前 Blade 前后台统一加载共享 Lucide 桥接器，不再依赖已退役的 Naive 单页壳。
 *
 * 适用场景：
 * - 用户要求界面统一 Lucide 图标，并且全程禁止表情符号。
 *
 * 返回值：
 * - 测试通过表示当前界面源文件没有可见表情符号，且前后台使用同一套 Lucide 渲染链路。
 * - 测试失败会指出具体文件或违规片段，方便直接定位修复。
 */
class LucideIconAndEmojiPolicyTest extends TestCase
{
    /**
     * 界面源文件不能直接写入表情符号。
     *
     * @return void
     */
    public function test_visible_ui_sources_do_not_contain_emoji_symbols(): void
    {
        foreach ($this->visibleUiSourceFiles() as $label => $path) {
            $source = file_get_contents($path) ?: '';

            $this->assertSame(
                0,
                preg_match('/[\x{2600}-\x{27BF}\x{1F300}-\x{1FAFF}]/u', $source),
                $label . ' 仍包含界面可见表情符号，应改为 Lucide 图标或普通文本。'
            );
        }
    }

    /**
     * 当前 Blade 前后台必须加载共享 Lucide 桥接器。
     *
     * @return void 测试通过表示前台、后台和旧图标兼容入口最终都由 Lucide 渲染。
     */
    public function test_active_blade_shells_load_the_shared_lucide_bridge(): void
    {
        $partial = file_get_contents(resource_path('views/partials/lucide-assets.blade.php')) ?: '';
        $frontLayout = file_get_contents(resource_path('views/front/layouts/app.blade.php')) ?: '';
        $bridge = file_get_contents(public_path('js/shared/lucide-bridge.js')) ?: '';

        $this->assertStringContainsString('/js/vendor/lucide/lucide.min.js', $partial, '共享资源片段缺少本地 Lucide vendor。');
        $this->assertStringContainsString('/js/shared/lucide-bridge.js', $partial, '共享资源片段缺少 Lucide 桥接器。');
        $this->assertStringContainsString("@include('partials.lucide-assets')", $frontLayout, '前台主布局未加载共享 Lucide 资源。');
        $this->assertStringContainsString("element.setAttribute('data-lucide', iconName)", $bridge, '旧 Layui/Font Awesome 图标未转换为 Lucide。');
        $this->assertStringContainsString('window.CrmIcons', $bridge, '动态内容缺少统一的 Lucide 刷新入口。');
    }

    /**
     * 每个直接输出 HTML 文档的 Blade 入口都必须加载共享 Lucide 资源。
     *
     * 页面组件通过 `@extends` 继承布局时不重复加载；只有包含 doctype 的独立壳层需要接入。
     *
     * @return void 测试通过表示登录、注册、旧入口和样例页不会退回字体图标或表情符号。
     */
    public function test_every_standalone_blade_document_loads_shared_lucide_assets(): void
    {
        foreach ($this->visibleUiSourceFiles() as $label => $path) {
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            $source = file_get_contents($path) ?: '';
            if (! preg_match('/<!doctype\s+html/i', $source)) {
                continue;
            }

            $this->assertStringContainsString(
                "@include('partials.lucide-assets')",
                $source,
                $label . ' 是独立 HTML 文档，但没有加载共享 Lucide 资源。'
            );
        }
    }

    /**
     * 业务界面的图标元素必须直接声明 Lucide 名称。
     *
     * 共享桥接器仍保留旧图标映射，用于兼容数据库菜单和第三方组件动态输出；业务 Blade 与应用脚本
     * 不再直接生成 Layui 或 Font Awesome 图标元素，避免字体未加载时出现空白或错误字形。
     *
     * @return void 测试通过表示业务界面的静态和动态 HTML 都以 data-lucide 作为图标来源。
     */
    public function test_application_icon_elements_declare_lucide_names_directly(): void
    {
        foreach ($this->visibleUiSourceFiles() as $label => $path) {
            if ((! str_ends_with($path, '.blade.php') && ! str_ends_with($path, '.js'))
                || str_ends_with($path, 'js' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'lucide-bridge.js')) {
                continue;
            }

            $source = file_get_contents($path) ?: '';

            $this->assertSame(
                0,
                preg_match('/<(?:i|span)\\b[^>]*class=([' . "\"'" . '])[^' . "\"'" . ']*(?:layui-icon(?:-[a-z0-9-]+)?|fa[rsbld]?\\s+fa-[a-z0-9-]+)[^' . "\"'" . ']*\\1/i', $source),
                $label . ' 仍直接输出 Layui 或 Font Awesome 图标元素，应改为 data-lucide。'
            );

            $this->assertSame(
                0,
                preg_match('/>\\s*(?:\\+|×|›|»|&times;|&#215;)\\s*</u', $source),
                $label . ' 仍使用字符充当图标，应改为带可访问名称的 Lucide 图标。'
            );
        }
    }

    /**
     * 业务样式必须绑定 Lucide 的稳定共享 class。
     *
     * Lucide 会把占位元素替换成 SVG；继续使用 `.layui-icon` 会在替换后失去尺寸和间距规则，
     * 因此项目自有 CSS 统一使用桥接器附加的 `.crm-lucide-icon`。
     *
     * @return void 测试通过表示图标样式不会依赖已移除的字体图标节点。
     */
    public function test_application_styles_target_the_shared_lucide_class(): void
    {
        foreach ($this->visibleUiSourceFiles() as $label => $path) {
            if (! str_ends_with($path, '.css')) {
                continue;
            }

            $source = file_get_contents($path) ?: '';

            $this->assertSame(
                0,
                preg_match('/\\.layui-icon(?:[-\\s:.,>+~#\\[]|$)/', $source),
                $label . ' 仍把业务样式绑定到 Layui 字体图标，应改为 .crm-lucide-icon。'
            );
        }
    }

    /**
     * 所有声明出来的 Lucide 图标名都必须能被当前本地 vendor 包解析。
     *
     * @return void
     */
    public function test_declared_lucide_icon_names_exist_in_bundled_vendor(): void
    {
        $vendor = file_get_contents(public_path('js/vendor/lucide/lucide.min.js')) ?: '';

        foreach ($this->declaredLucideIconNames() as $name) {
            $pascalName = $this->lucideExportName($name);

            $this->assertMatchesRegularExpression(
                '/[,.]' . preg_quote($pascalName, '/') . '=/',
                $vendor,
                '当前 Lucide vendor 包不存在图标：' . $name
            );
        }
    }

    /**
     * 收集会直接参与界面渲染的本地源文件。
     *
     * @return array<string, string> key 为可读文件标签，value 为绝对路径。
     */
    private function visibleUiSourceFiles(): array
    {
        $files = [];

        // 项目同时使用默认 views、front 与 admin 三个 Blade 命名空间，必须全部扫描。
        $this->collectSourceFiles(resource_path('views'), ['.blade.php'], $files);
        $this->collectSourceFiles(resource_path('front'), ['.blade.php'], $files);
        $this->collectSourceFiles(resource_path('admin'), ['.blade.php'], $files);
        $this->collectSourceFiles(public_path('css/front'), ['.css'], $files);
        $this->collectSourceFiles(public_path('css/admin'), ['.css'], $files);
        $this->collectSourceFiles(public_path('css/crmui'), ['.css'], $files);
        $this->collectSourceFiles(public_path('css/common'), ['.css'], $files);
        $this->collectSourceFiles(public_path('css/naive-admin'), ['.css'], $files);
        $this->collectSourceFiles(public_path('js/apps/front'), ['.js'], $files);
        $this->collectSourceFiles(public_path('js/apps/admin'), ['.js'], $files);
        $this->collectSourceFiles(public_path('js/apps/crmui'), ['.js'], $files);
        $this->collectSourceFiles(public_path('js/shared'), ['.js'], $files);

        return $files;
    }

    /**
     * 递归收集指定后缀的界面源文件。
     *
     * @param string $directory 扫描目录，例如 resources/views。
     * @param array<int, string> $extensions 需要纳入检查的文件后缀。
     * @param array<string, string> $files 输出文件清单，key 为相对路径。
     * @return void
     */
    private function collectSourceFiles(string $directory, array $extensions, array &$files): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (! $this->hasAllowedExtension($path, $extensions)) {
                continue;
            }

            $files[str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path)] = $path;
        }
    }

    /**
     * 判断文件是否属于本测试关注的界面源文件类型。
     *
     * @param string $path 当前文件绝对路径。
     * @param array<int, string> $extensions 允许的文件后缀。
     * @return bool true 表示纳入扫描，false 表示跳过。
     */
    private function hasAllowedExtension(string $path, array $extensions): bool
    {
        foreach ($extensions as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 汇总前端源码里声明的 Lucide 图标名。
     *
     * @return array<int, string> 去重后的图标名列表。
     */
    private function declaredLucideIconNames(): array
    {
        $names = [];
        $bridgeSource = file_get_contents(public_path('js/shared/lucide-bridge.js')) ?: '';

        // 只读取桥接脚本里的图标映射对象，避免把其它普通配置值误判为 Lucide 图标名。
        foreach (['layuiMap', 'fontAwesomeMap'] as $mapName) {
            if (! preg_match('/var\s+' . preg_quote($mapName, '/') . '\s*=\s*\{(.*?)\};/s', $bridgeSource, $block)) {
                continue;
            }

            preg_match_all("/'[^']+'\s*:\s*'([^']+)'/", $block[1], $matches);
            $names = array_merge($names, $matches[1] ?? []);
        }

        foreach ($this->visibleUiSourceFiles() as $path) {
            $source = file_get_contents($path) ?: '';
            preg_match_all('/data-lucide="([^"]+)"/', $source, $matches);
            $names = array_merge($names, $matches[1] ?? []);

            // 兼容页面局部助手直接声明图标名的写法，并只接受 kebab-case 图标值。
            preg_match_all("/lucideIconHtml\('([^']+)'/", $source, $matches);
            $names = array_merge($names, $matches[1] ?? []);
        }

        $names = array_values(array_unique(array_filter($names, function (string $name): bool {
            return (bool) preg_match('/^[a-z0-9-]+$/', $name);
        })));
        sort($names);

        return $names;
    }

    /**
     * 将 kebab-case 的 data-lucide 名称转换成 vendor 暴露的 PascalCase 名称。
     *
     * @param string $name data-lucide 图标名，例如 circle-check-big。
     * @return string vendor 导出的名称，例如 CircleCheckBig。
     */
    private function lucideExportName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}
