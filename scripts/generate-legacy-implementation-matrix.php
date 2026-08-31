<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 12:01
 */

declare(strict_types=1);

use App\Support\LegacyImplementationMatrix;

/**
 * 旧项目模块迁移核验矩阵命令行生成器。
 *
 * 文件功能：读取旧路由、当前映射、旧源码清单和持久化业务核验证据，输出 JSON 与中文 Markdown。
 * 任一证据重复、缺项或与当前路由不一致时返回非零退出码，防止生成带有错误完成状态的报告。
 */
$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

$options = getopt('', [
    'legacy-routes::',
    'route-audit::',
    'source-inventory::',
    'verification-evidence::',
    'json::',
    'markdown::',
]);
$legacyRoutesPath = resolvePath((string) ($options['legacy-routes'] ?? 'storage/app/audits/legacy-routes.json'), $projectRoot);
$routeAuditPath = resolvePath((string) ($options['route-audit'] ?? 'storage/app/audits/current-legacy-route-audit.json'), $projectRoot);
$sourceInventoryPath = resolvePath((string) ($options['source-inventory'] ?? 'storage/app/audits/旧项目源码逻辑清单.json'), $projectRoot);
$verificationEvidencePath = resolvePath((string) ($options['verification-evidence'] ?? 'docs/audits/旧项目路由核验证据.json'), $projectRoot);
$jsonPath = resolvePath((string) ($options['json'] ?? 'storage/app/audits/旧项目模块逻辑迁移核验矩阵.json'), $projectRoot);
$markdownPath = resolvePath((string) ($options['markdown'] ?? 'docs/audits/旧项目模块逻辑迁移核验矩阵.md'), $projectRoot);

try {
    $builder = new LegacyImplementationMatrix();
    $matrix = $builder->build(
        readJsonArray($legacyRoutesPath),
        readJsonArray($routeAuditPath),
        readJsonArray($sourceInventoryPath),
        readJsonArray($verificationEvidencePath)
    );
    $matrix['meta'] = [
        'generated_at' => date(DATE_ATOM),
        'evidence_boundary' => '路由映射与静态源码关联底稿；needs_manual_business_review 不代表项目2业务已完成。',
        'legacy_routes' => $legacyRoutesPath,
        'route_audit' => $routeAuditPath,
        'source_inventory' => $sourceInventoryPath,
        'verification_evidence' => $verificationEvidencePath,
    ];

    writeFile($jsonPath, json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    writeFile($markdownPath, $builder->toMarkdown($matrix));

    fwrite(STDOUT, sprintf(
        "已生成迁移核验矩阵：旧路由方法 %d，已完成业务核验 %d，待人工业务核验 %d，旧源码证据未解决 %d，项目2路由未匹配 %d。%s",
        $matrix['summary']['legacy_route_methods'],
        $matrix['summary']['verified'],
        $matrix['summary']['needs_manual_business_review'],
        $matrix['summary']['unresolved_legacy_source'],
        $matrix['summary']['unmatched_current_route'],
        PHP_EOL
    ));
    fwrite(STDOUT, "JSON：{$jsonPath}" . PHP_EOL);
    fwrite(STDOUT, "Markdown：{$markdownPath}" . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function resolvePath(string $path, string $projectRoot): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
        return $path;
    }

    return $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function readJsonArray(string $path): array
{
    if (! is_file($path)) {
        throw new RuntimeException('输入文件不存在：' . $path);
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (! is_array($data)) {
        throw new RuntimeException('JSON 无法解析或根节点不是数组：' . $path);
    }

    return $data;
}

function writeFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('无法创建输出目录：' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('无法写入输出文件：' . $path);
    }
}
