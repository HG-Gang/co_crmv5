<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 安全剔除函数签名内可空参数写法的历史修复脚本（v2）。
 *
 * 文件功能：
 * - 仅替换参数列表内的可空参数写法为带默认值写法，绝不重建 function 前缀，
 *   避免历史事故（方法名丢失）。
 *
 * 适用场景：
 * - 仅历史修复用，当前代码无需再执行。
 */
// 剔除函数签名内的可空参数写法（?Type $x -> Type $x = null）。
// 安全策略：只替换参数列表内文本，绝不重建 function 前缀（避免历史事故）。
$dirs = ['app', 'tests', 'scripts', 'database', 'routes', 'config'];
$total = 0;

$typePattern = '(?:[A-Za-z_\\\\][\w\\\\]*|int|string|float|bool|array|callable|iterable|object|self|parent)';

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
        if (strpos($c, '?') === false) {
            continue;
        }
        $changed = 0;

        // 找到所有函数签名开括号（function 关键字后的第一个 (）
        if (preg_match_all('/function\s+[&]?[\w\\\\]*\s*\(/', $c, $m, PREG_OFFSET_CAPTURE)) {
            $shifts = 0;
            foreach ($m[0] as $hit) {
                $openPos = $hit[1] + strlen($hit[0]) - 1; // ( 的位置
                // 找匹配的右括号（处理嵌套）
                $depth = 0;
                $end = -1;
                $len = strlen($c);
                for ($p = $openPos; $p < $len; $p++) {
                    $ch = $c[$p];
                    if ($ch === '(') {
                        $depth++;
                    } elseif ($ch === ')') {
                        $depth--;
                        if ($depth === 0) {
                            $end = $p;
                            break;
                        }
                    }
                }
                if ($end < 0) {
                    continue;
                }
                $inner = substr($c, $openPos + 1, $end - $openPos - 1);
                $inner2 = preg_replace_callback(
                    '/\?\s*(' . $typePattern . ')\s+(\$\w+)(\s*=\s*[^,)]+)?/',
                    static function ($pm) {
                        $default = $pm[3] ?? '';
                        $default = trim($default) === '' ? ' = null' : $default;
                        return $pm[1] . ' ' . $pm[2] . $default;
                    },
                    $inner,
                    -1,
                    $n
                );
                if ($n > 0) {
                    $c = substr($c, 0, $openPos + 1) . $inner2 . substr($c, $end);
                    $changed += $n;
                }
            }
        }

        if ($changed > 0) {
            file_put_contents($path, $c);
            $total += $changed;
            echo "updated: " . str_replace('D:/Software/PhpProject/Demo/co_crmv5/', '', str_replace('\\', '/', $path)) . " (+$changed)\n";
        }
    }
}
echo "总计替换: $total 处\n";
