<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 12:03
 */

declare(strict_types=1);

namespace App\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * 当前项目表面资产盘点器。
 *
 * 文件功能：
 * - 按登记分组扫描控制器、路由、JS/CSS、迁移、测试与 Blade 模板，产出项目表面清单，
 *   Blade 根目录必须能映射到页面家族（family），未匹配的 Blade 直接抛异常保证无遗漏分类。
 * - vendor 前缀资产标记 ownership=vendor，与可自由修改的自有文件区分。
 * - 扫描范围以 GROUPS/BLADE_ROOTS 登记为准；新增资产目录必须先登记才会进入清单。
 */
final class CurrentProjectSurfaceInventory
{
    /**
     * 面板资产分组登记表：分组名 => [扫描目录, 文件后缀]。盘点范围（控制器/路由/JS/CSS/迁移/测试）
     * 全部由此收敛，新增资产目录必须先在此登记，否则不会进入盘点结果，表面清单即不完整。
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const GROUPS = [
        'controllers' => ['app/Http/Controllers', '.php'],
        'routes' => ['routes', '.php'],
        'javascript' => ['public/js', '.js'],
        'stylesheets' => ['public/css', '.css'],
        'migrations' => ['database/migrations', '.php'],
        'tests' => ['tests', 'Test.php'],
    ];

    /**
     * Blade 模板根目录到页面家族（family）的映射：resources 下新增 Blade 根目录必须登记于此，
     * 未匹配任何根的 Blade 会在 inspect() 中直接抛 Unclassified Blade 异常，保证清单无遗漏分类。
     *
     * @var array<string, string>
     */
    private const BLADE_ROOTS = [
        'resources/admin/layui' => 'admin_layui',
        'resources/admin/crmui' => 'admin_crmui',
        'resources/front/layui' => 'front_layui',
        'resources/front/crmui' => 'front_crmui',
        'resources/views' => 'shared_views',
    ];

    /**
     * 第三方（vendor）静态资源前缀清单：命中前缀的资产在盘点中标记 ownership=vendor，
     * 其余标记 first_party；用于把“可自由修改的自有文件”与“升级会被覆盖的第三方文件”区分开。
     *
     * @var array<int, string>
     */
    private const VENDOR_ASSET_PREFIXES = [
        'public/js/vendor/',
        'public/css/layui/',
        'public/css/naui/',
    ];

    public function inspect(string $root): array
    {
        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            throw new RuntimeException('Current project root does not exist: ' . $root);
        }

        $files = [];
        foreach (self::GROUPS as $name => [$directory, $suffix]) {
            $files[$name] = $this->records($resolvedRoot, $directory, $suffix);
        }

        $blades = $this->records($resolvedRoot, 'resources', '.blade.php');
        foreach ($blades as &$record) {
            $matchedRoot = null;
            foreach (self::BLADE_ROOTS as $directory => $family) {
                if (strpos($record['path'], $directory . '/') !== 0) {
                    continue;
                }

                $matchedRoot = $directory;
                $record['family'] = $family;
                $record['module'] = $this->moduleFor($record['path'], $directory);
                break;
            }

            if ($matchedRoot === null) {
                throw new RuntimeException('Unclassified Blade file: ' . $record['path']);
            }
        }
        unset($record);
        usort($blades, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);

        $families = array_count_values(array_column($blades, 'family'));
        ksort($families);

        return [
            'summary' => [
                'controller_files' => count($files['controllers']),
                'route_files' => count($files['routes']),
                'blade_files' => count($blades),
                'javascript_files' => count($files['javascript']),
                'stylesheet_files' => count($files['stylesheets']),
                'migration_files' => count($files['migrations']),
                'test_files' => count($files['tests']),
                'blade_families' => $families,
            ],
            'blades' => $blades,
            'files' => $files,
        ];
    }

    public function toMarkdown(array $inventory): string
    {
        $summary = $inventory['summary'] ?? [];
        $lines = [
            '# Current Project Surface Inventory',
            '',
            '- Controllers: ' . (int) ($summary['controller_files'] ?? 0),
            '- Route files: ' . (int) ($summary['route_files'] ?? 0),
            '- Blade files: ' . (int) ($summary['blade_files'] ?? 0),
            '- JavaScript files: ' . (int) ($summary['javascript_files'] ?? 0),
            '- Stylesheet files: ' . (int) ($summary['stylesheet_files'] ?? 0),
            '- Migrations: ' . (int) ($summary['migration_files'] ?? 0),
            '- Tests: ' . (int) ($summary['test_files'] ?? 0),
            '',
            '| Blade | Family | Module | SHA-256 |',
            '|---|---|---|---|',
        ];

        foreach ($inventory['blades'] ?? [] as $blade) {
            $lines[] = sprintf(
                '| `%s` | `%s` | `%s` | `%s` |',
                str_replace('|', '\\|', (string) $blade['path']),
                (string) $blade['family'],
                (string) $blade['module'],
                (string) $blade['sha256']
            );
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function records(string $root, string $relativeDirectory, string $suffix): array
    {
        $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (! is_dir($directory)) {
            throw new RuntimeException('Required inventory directory does not exist: ' . $relativeDirectory);
        }

        $records = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            $filename = $item->getFilename();
            if (! $item->isFile() || substr($filename, -strlen($suffix)) !== $suffix) {
                continue;
            }

            $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            $hash = hash_file('sha256', $item->getPathname());
            if ($hash === false) {
                throw new RuntimeException('Unable to hash inventory file: ' . $path);
            }

            $records[] = [
                'path' => $path,
                'bytes' => $item->getSize(),
                'sha256' => $hash,
                'ownership' => $this->ownershipFor($path),
            ];
        }

        usort($records, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);
        return $records;
    }

    private function moduleFor(string $path, string $bladeRoot): string
    {
        $relative = substr($path, strlen($bladeRoot) + 1);
        $separator = strpos($relative, '/');
        return $separator === false ? '_root' : substr($relative, 0, $separator);
    }

    private function ownershipFor(string $path): string
    {
        foreach (self::VENDOR_ASSET_PREFIXES as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return 'vendor';
            }
        }

        return 'first_party';
    }
}
