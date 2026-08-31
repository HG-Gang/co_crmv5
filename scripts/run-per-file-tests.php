<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 16:30
 */
/**
 * Phase 7 逐文件隔离测试运行器。
 *
 * 文件功能：
 * - 把 tests/Unit 与 tests/Feature 的每个测试文件按目录序以「独立 phpunit 进程」逐个运行。
 * - 与单进程全量串行互补：独立进程能暴露进程内静态状态、共享连接与跨文件残留依赖，
 *   是隔离迁移验收（Phase 7「逐文件隔离测试」）的落地工具。
 * - 逐文件结果与失败摘要写入 storage/logs/per-file-tests-<时间戳>.log，供最终差异报告引用。
 *
 * 失败语义：
 * - 任一文件出现 FAILURES!/ERRORS! 记为 FAIL；退出码为失败文件数（0 表示全部通过）。
 * - 「No tests executed」记为 EMPTY，不计失败。
 *
 * 使用方式（先重建测试库基线）：
 *   php artisan migrate:fresh --seed --env=testing   （或由外层脚本完成）
 *   php scripts/run-per-file-tests.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/tests/Unit/*Test.php'),
    glob($root . '/tests/Feature/*Test.php')
);
$files = array_map(static function (string $file): string {
    return str_replace('\\', '/', $file);
}, $files);
sort($files);

$total = count($files);
if ($total === 0) {
    fwrite(STDERR, "no test files found\n");
    exit(1);
}

$logPath = $root . '/storage/logs/per-file-tests-' . date('Ymd-His') . '.log';
$log = fopen($logPath, 'w');
if ($log === false) {
    fwrite(STDERR, "unable to open log: $logPath\n");
    exit(1);
}

fwrite($log, "per-file isolation run started " . date('Y-m-d H:i:s') . "\n");
fwrite($log, "total files: $total\n");

$failures = [];
$index = 0;
$startedAt = time();

foreach ($files as $file) {
    $index++;
    $relative = substr($file, strlen(str_replace('\\', '/', $root)) + 1);
    $command = 'php ' . escapeshellarg($root . '/vendor/bin/phpunit') . ' --colors=never ' . escapeshellarg($file) . ' 2>&1';
    $output = shell_exec($command);

    if ($output === null) {
        $status = 'FAIL';
        $summary = 'shell_exec returned null';
        $failures[] = $relative;
    } elseif (strpos($output, 'No tests executed') !== false) {
        $status = 'EMPTY';
        $summary = 'no tests executed';
    } elseif (preg_match('/OK \((\d+) tests?, (\d+) assertions?\)/', $output, $match)) {
        $status = 'OK';
        $summary = $match[1] . ' tests / ' . $match[2] . ' assertions';
    } else {
        $status = 'FAIL';
        $lines = preg_split('/\r?\n/', trim($output));
        $summary = implode(' | ', array_slice(array_filter($lines), -6));
        $failures[] = $relative;
    }

    fwrite($log, sprintf("[%d/%d] %s %s :: %s\n", $index, $total, $status, $relative, $summary));

    if ($index % 25 === 0 || $index === $total) {
        $elapsed = time() - $startedAt;
        printf("progress %d/%d elapsed=%ds failures=%d%s\n", $index, $total, $elapsed, count($failures), PHP_EOL);
    }
}

fwrite($log, sprintf("finished in %ds; failures: %d\n", time() - $startedAt, count($failures)));
foreach ($failures as $failure) {
    fwrite($log, "FAILED: $failure\n");
}
fclose($log);

echo "log: $logPath\n";
echo 'result: ' . (count($failures) === 0 ? 'ALL GREEN' : count($failures) . ' failing files') . "\n";

exit(count($failures) === 0 ? 0 : 1);
