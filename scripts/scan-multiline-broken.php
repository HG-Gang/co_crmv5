<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 扫描并报告多行损坏签名的历史诊断脚本。
 *
 * 文件功能：
 * - 定位 function 后换行、参数块拆行等签名损坏模式。
 *
 * 适用场景：
 * - 仅历史诊断用。
 */

// 扫描并报告多行损坏签名：function 后换行、参数块、再单独一行 (
$dirs = ['app', 'tests', 'scripts', 'database', 'routes', 'config'];
$hits = [];
foreach ($dirs as $d) {
    if (! is_dir($d)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $lines = explode("\n", file_get_contents($f->getPathname()));
        $rel = str_replace('D:/Software/PhpProject/Demo/co_crmv5/', '', str_replace('\\', '/', $f->getPathname()));
        for ($i = 0; $i < count($lines); $i++) {
            // 模式: "function " 行尾(无() 后跟参数行... 再 "(" 单独行
            if (preg_match('/function\s+$/', rtrim($lines[$i]))) {
                // 收集到下一个 "(" 单独行
                $block = [$i + 1 => rtrim($lines[$i])];
                for ($j = $i + 1; $j < min($i + 8, count($lines)); $j++) {
                    $block[$j + 1] = rtrim($lines[$j]);
                    if (trim($lines[$j]) === '(') {
                        $hits[] = ['file' => $rel, 'start' => $i + 1, 'block' => $block];
                        $i = $j;
                        break;
                    }
                    if (strpos($lines[$j], ')') !== false) {
                        break;
                    }
                }
            }
        }
    }
}
echo '多行损坏签名: ' . count($hits) . "\n";
foreach ($hits as $h) {
    echo "=== {$h['file']}:{$h['start']} ===\n";
    foreach ($h['block'] as $ln => $txt) {
        echo "  L$ln: $txt\n";
    }
}
