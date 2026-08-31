<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 12:01
 */

/**
 * 旧项目源码逻辑清单生成器。
 *
 * 脚本用途：
 * - 静态扫描旧项目目录（默认 D:\Software\PhpProject\Demo\new_co_gmtk_crmv3）的 PHP 控制器、
 *   Blade 模板、表单与 AJAX 地址，输出 JSON 与中文 Markdown 清单，供迁移矩阵核验使用。
 * - 只做静态读取，不启动旧项目、不执行旧代码、不访问旧数据库。
 *
 * 运行方式：
 * - php scripts/generate-legacy-source-inventory.php [--legacy-root=旧项目根目录]
 *   [--json=输出json] [--markdown=输出md] [--project-name=项目名]
 */

declare(strict_types=1);

use App\Support\LegacySourceInventory;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

$options = getopt('', ['legacy-root:', 'json:', 'markdown:', 'project-name::']);
$legacyRoot = rtrim((string) ($options['legacy-root'] ?? 'D:\\Software\\PhpProject\\Demo\\new_co_gmtk_crmv3'), "\\/");
$jsonPath = resolveOutputPath(
    (string) ($options['json'] ?? 'storage/app/audits/旧项目源码逻辑清单.json'),
    $projectRoot
);
$markdownPath = resolveOutputPath(
    (string) ($options['markdown'] ?? 'docs/audits/旧项目源码逻辑清单.md'),
    $projectRoot
);
$projectName = (string) ($options['project-name'] ?? '项目1旧项目');

if (! is_dir($legacyRoot)) {
    fwrite(STDERR, "旧项目目录不存在：{$legacyRoot}" . PHP_EOL);
    exit(2);
}

try {
    $scanner = new LegacySourceInventory();
    $inventory = $scanner->inspect($legacyRoot);
    $inventory['meta'] = [
        'generated_at' => date(DATE_ATOM),
        'legacy_root' => $legacyRoot,
        'evidence_boundary' => '仅静态读取旧项目 PHP 与 Blade 源码；不启动旧项目、不执行旧代码、不访问旧数据库。',
    ];

    writeFile($jsonPath, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    writeFile($markdownPath, $scanner->toMarkdown($inventory, $projectName));

    fwrite(STDOUT, sprintf(
        "已生成旧项目源码逻辑清单：Controller %d 个、方法 %d 个、Blade %d 个、表单 %d 个、AJAX 地址 %d 个。%s",
        $inventory['summary']['controller_files'],
        $inventory['summary']['controller_methods'],
        $inventory['summary']['blade_files'],
        $inventory['summary']['forms'],
        $inventory['summary']['ajax_endpoints'],
        PHP_EOL
    ));
    fwrite(STDOUT, "JSON：{$jsonPath}" . PHP_EOL);
    fwrite(STDOUT, "Markdown：{$markdownPath}" . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function resolveOutputPath(string $path, string $projectRoot): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
        return $path;
    }

    return $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function writeFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('无法创建输出目录：' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('无法写入清单文件：' . $path);
    }
}
