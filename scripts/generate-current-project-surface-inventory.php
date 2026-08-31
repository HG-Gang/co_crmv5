<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 12:03
 */

declare(strict_types=1);

use App\Support\CurrentProjectSurfaceInventory;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

$options = getopt('', ['root::', 'json::', 'markdown::']);
$root = (string) ($options['root'] ?? $projectRoot);
$jsonPath = outputPath((string) ($options['json'] ?? 'storage/app/audits/2026-08-07-current-project-surface-inventory.json'), $projectRoot);
$markdownPath = outputPath((string) ($options['markdown'] ?? 'docs/audits/2026-08-07-current-project-surface-inventory.md'), $projectRoot);

try {
    $scanner = new CurrentProjectSurfaceInventory();
    $inventory = $scanner->inspect($root);
    $inventory['meta'] = [
        'schema_version' => 1,
        'generated_at' => date(DATE_ATOM),
        'root' => realpath($root),
        'evidence_boundary' => 'Read-only filesystem inventory; no application bootstrap and no database connection.',
    ];

    $json = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode current project inventory JSON.');
    }

    writeOutput($jsonPath, $json . PHP_EOL);
    writeOutput($markdownPath, $scanner->toMarkdown($inventory));
    fwrite(STDOUT, sprintf(
        "Current inventory ready: controllers=%d routes=%d blades=%d js=%d css=%d migrations=%d tests=%d.%s",
        $inventory['summary']['controller_files'],
        $inventory['summary']['route_files'],
        $inventory['summary']['blade_files'],
        $inventory['summary']['javascript_files'],
        $inventory['summary']['stylesheet_files'],
        $inventory['summary']['migration_files'],
        $inventory['summary']['test_files'],
        PHP_EOL
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function outputPath(string $path, string $projectRoot): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
        return $path;
    }
    return $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function writeOutput(string $path, string $contents): void
{
    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('Unable to create inventory output directory: ' . $directory);
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to write inventory output: ' . $path);
    }
}
