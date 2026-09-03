<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:40
 */

/**
 * 批量修复文件头部标识块格式
 *
 * 文件功能：
 * - 扫描指定目录下所有PHP文件，修复头部标识块格式错误
 * - 将 "Created by PhpStorm" 修正为 " * Created by PhpStorm"
 *
 * 使用方法：
 * php scripts/fix-header-format.php [目录路径]
 */

$directory = $argv[1] ?? 'app/Http/Controllers';
$baseDir = dirname(__DIR__);
$targetDir = $baseDir . DIRECTORY_SEPARATOR . $directory;

if (!is_dir($targetDir)) {
    echo "错误：目录不存在 {$targetDir}\n";
    exit(1);
}

$fixedCount = 0;
$skippedCount = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetDir)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $filePath = $file->getPathname();
    $content = file_get_contents($filePath);

    // 检测错误格式：/**\n Created by PhpStorm（第二行缺少 * ）
    if (preg_match('/\/\*\*\s*\n\s*Created by PhpStorm\./s', $content)) {
        // 修正格式：在 Created 前添加 " * "
        $newContent = preg_replace(
            '/\/\*\*\s*\n\s*Created by PhpStorm\./s',
            "/**\n * Created by PhpStorm.",
            $content
        );

        if ($newContent !== $content) {
            file_put_contents($filePath, $newContent);
            $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
            echo "✅ 已修复: {$relativePath}\n";
            $fixedCount++;
        }
    } else {
        $skippedCount++;
    }
}

echo "\n====================================\n";
echo "修复完成\n";
echo "====================================\n";
echo "已修复: {$fixedCount} 个文件\n";
echo "已跳过: {$skippedCount} 个文件\n";
