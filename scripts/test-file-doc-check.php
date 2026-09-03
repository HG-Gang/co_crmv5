<?php

$files = [
    'D:\Software\PhpProject\Demo\co_crmv5\app\Http\Controllers\Admin\AdminDashboardController.php',
    'D:\Software\PhpProject\Demo\co_crmv5\app\Http\Controllers\Admin\BatchAmountImportController.php',
    'D:\Software\PhpProject\Demo\co_crmv5\app\Http\Controllers\Admin\GiftController.php',
    'D:\Software\PhpProject\Demo\co_crmv5\app\Http\Controllers\Admin\ProductionController.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $basename = basename($file);

    echo "检查文件: {$basename}\n";

    // 检查是否包含"文件功能"文字
    if (strpos($content, '文件功能') !== false) {
        echo "  ✅ 包含'文件功能'文字\n";
    } else {
        echo "  ❌ 不包含'文件功能'文字\n";
    }

    // 使用检查脚本的正则
    if (preg_match('/\/\*\*[^\/]*文件功能[：:]/s', $content)) {
        echo "  ✅ 正则匹配成功\n";
    } else {
        echo "  ❌ 正则匹配失败\n";

        // 尝试更宽松的匹配
        if (preg_match('/文件功能/u', $content)) {
            echo "  ℹ️  简单匹配成功，可能是注释格式问题\n";

            // 检查注释块
            if (preg_match('/\/\*\*.*?文件功能.*?\*\//s', $content)) {
                echo "  ℹ️  在注释块中找到\n";
            }
        }
    }

    echo "\n";
}
