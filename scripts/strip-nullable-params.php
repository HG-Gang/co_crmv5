<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 全项目剔除可空参数类型写法的历史修复脚本（v1）。
 *
 * 文件功能：
 * - 在函数/方法签名括号内将可空参数写法替换为带默认值的等价写法（PHP 7.4 兼容），
 *   不触碰属性声明与返回类型。
 *
 * 适用场景：
 * - 仅历史修复用，当前代码无需再执行。
 */
// 全项目剔除可空参数类型写法：?Type $param → Type $param = null（语义等价，PHP 7.4 兼容）。
// 仅在函数/方法签名括号内替换，不触碰属性声明与返回类型。
$dirs = ['app', 'routes', 'config', 'database', 'tests', 'scripts'];
$totalReplaced = 0;
$fileCount = 0;

foreach ($dirs as $d) {
    if (! is_dir($d)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        $changed = 0;
        // 匹配函数/方法签名括号（含闭包），括号内替换可空参数
        $c2 = preg_replace_callback(
            '/function\s+[&]?[\w\\\\]*\s*\(([^)]*)\)/',
            static function ($m) use (&$changed) {
                $params = $m[1];
                $params2 = preg_replace_callback(
                    '/\?\s*([A-Za-z_\\\\][\w\\\\]*|int|string|float|bool|array|callable|iterable|object|self|parent)\s+(\$\w+)(\s*=\s*[^,\)]+)?/',
                    static function ($pm) use (&$changed) {
                        $changed++;
                        $default = $pm[3] ?? '';
                        // 有默认值则保留（移除 ?）；无默认值则补 = null 保持可空语义
                        $default = trim($default) === '' ? ' = null' : $default;
                        return $pm[1] . ' ' . $pm[2] . $default;
                    },
                    $params
                );
                return 'function ' . $m[1] . '(' . $params2 . ')';
            },
            $c
        );
        if ($c2 !== $c && $changed > 0) {
            file_put_contents($f->getPathname(), $c2);
            $fileCount++;
            $totalReplaced += $changed;
            echo "updated: " . str_replace('D:/Software/PhpProject/Demo/co_crmv5/', '', str_replace('\\', '/', $f->getPathname())) . " (+$changed)\n";
        }
    }
}
echo "总计: $fileCount 个文件, $totalReplaced 处替换\n";
