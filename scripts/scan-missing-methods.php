<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 扫描所有方法名缺失位置的历史诊断脚本。
 *
 * 文件功能：
 * - 找出 public/private/protected/static function ( 定义处方法名缺失的位置，
 *   并收集每个文件可用的方法名候选（$this->xxx( 调用点）。
 *
 * 适用场景：
 * - 仅历史诊断用。
 */

// 扫描所有"方法名缺失"位置（public/private/protected/static function (），
// 并收集每文件可用的方法名候选（$this->xxx( 调用点）。
$dirs = ['app', 'routes', 'config', 'database', 'tests', 'scripts'];
$missing = [];

foreach ($dirs as $d) {
    if (! is_dir($d)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $path = $f->getPathname();
        $rel = str_replace('D:/Software/PhpProject/Demo/co_crmv5/', '', str_replace('\\', '/', $path));
        $lines = explode("\n", file_get_contents($path));

        // 收集文件内 $this->xxx( 调用（未损坏处）
        $calls = [];
        foreach ($lines as $line) {
            if (preg_match_all('/\$this->(\w+)\s*\(/', $line, $m)) {
                foreach ($m[1] as $name) {
                    $calls[$name] = true;
                }
            }
        }
        // 也收集 self:: 与 static::
        foreach ($lines as $line) {
            if (preg_match_all('/\b(?:self|static)::(\w+)\s*\(/', $line, $m)) {
                foreach ($m[1] as $name) {
                    $calls[$name] = true;
                }
            }
        }

        foreach ($lines as $i => $line) {
            // 方法名缺失: public/private/protected/static function ( 或 = function ( 后原本有名字的场景
            if (preg_match('/^\s*(public|private|protected|static|final\s+public|final\s+private|final\s+protected)\s+function\s*\(/', $line)
                || preg_match('/^\s*(public|private|protected)\s+(static\s+)?function\s*\(/', $line)) {
                $missing[] = [
                    'file' => $rel,
                    'line' => $i + 1,
                    'text' => trim($line),
                    'calls' => array_keys($calls),
                ];
            }
        }
    }
}

echo '方法名缺失位置: ' . count($missing) . "\n";
$byFile = [];
foreach ($missing as $m) {
    $byFile[$m['file']][] = $m;
}
foreach ($byFile as $file => $items) {
    echo "=== $file (" . count($items) . " 处) ===\n";
    foreach ($items as $it) {
        echo "  L{$it['line']}: {$it['text']}\n";
    }
}
file_put_contents('D:/Software/PhpProject/Demo/co_crmv5/storage/app/tmp/missing-methods.txt', json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n清单已写入 storage/app/tmp/missing-methods.txt\n";
