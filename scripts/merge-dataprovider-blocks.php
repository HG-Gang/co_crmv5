<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:55
 */

// 修复：把独立 /** @dataProvider X */ 块删除，并将 @dataProvider X 行插入下一个 docblock 开头。
$dirs = ['app', 'tests', 'scripts', 'database', 'routes', 'config'];
$total = 0;

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
        $c = file_get_contents($path);
        if (strpos($c, '@dataProvider') === false) {
            continue;
        }
        $changed = 0;
        // 匹配: 独立块(可含 内部换行) + 下一个 /** —— 替换为下一个 docblock 直接以 @dataProvider 行开头
        $c2 = preg_replace_callback(
            '/^(\s*)\/\*\* @dataProvider (\w+) \*\/\s*\r?\n(\s*)\/\*\*/m',
            function ($m) use (&$changed) {
                $changed++;
                return $m[3] . "/**\n" . $m[3] . ' * @dataProvider ' . $m[2];
            },
            $c
        );
        if ($changed > 0) {
            file_put_contents($path, $c2);
            $total += $changed;
            echo "fixed: " . str_replace('D:/Software/PhpProject/Demo/co_crmv5/', '', str_replace('\\', '/', $path)) . " (+$changed)\n";
        }
    }
}
echo "总计: $total 处\n";
