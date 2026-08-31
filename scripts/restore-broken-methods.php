<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 02:37
 */

/**
 * 恢复被 strip-nullable-params.php 破坏的 PHP 文件（方法名丢失事故恢复工具）。
 *
 * 损坏模式：function <原参数列表>(<新参数列表>) —— 方法名被原参数列表替换。
 * 恢复规则：
 * 1) 匿名函数：伪方法名与参数列表的变量序列一致 -> 恢复为 function (参数列表)。
 * 2) 具名方法：从文件内 $this->xxx( 调用点与外部方法名清单恢复。
 */

$dirs = ['app', 'routes', 'config', 'database', 'tests', 'scripts'];
$fixedAnon = 0;
$fixedNamed = 0;
$unresolved = [];

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
        if (strpos($c, 'function ') === false) {
            continue;
        }

        // 收集文件内 $this->methodName( 调用点（未被破坏的调用处）
        $calledMethods = [];
        if (preg_match_all('/\$this->(\w+)\s*\(/', $c, $m)) {
            $calledMethods = array_unique($m[1]);
        }

        $changed = false;
        // 逐行处理损坏签名
        $lines = explode("\n", $c);
        foreach ($lines as $i => $line) {
            if (! preg_match('/function\s+([^()\s]+(?:\s+[^()\s]+)*)\(/', $line, $m)) {
                continue;
            }
            $fake = trim($m[1]); // 伪方法名（= 原参数列表）
            if (strpos($fake, '$') === false && strpos($fake, ' ') === false) {
                continue; // 正常方法名，跳过
            }
            // 新参数列表 = function 后的第一个括号内容
            if (! preg_match('/function\s+[^()\s]+(?:\s+[^()\s]+)*\(([^)]*)\)/', $line, $pm)) {
                continue;
            }
            $params = $pm[1];

            // 规则 1：匿名函数 —— 伪方法名的变量序列与参数列表变量序列一致
            $fakeVars = [];
            preg_match_all('/\$(\w+)/', $fake, $fv);
            $fakeVars = $fv[1];
            $paramVars = [];
            preg_match_all('/\$(\w+)/', $params, $pv);
            $paramVars = $pv[1];

            if ($fakeVars === $paramVars && count($fakeVars) > 0) {
                // 匿名函数：去掉伪方法名
                $lines[$i] = preg_replace('/function\s+[^()\s]+(?:\s+[^()\s]+)*\(/', 'function (', $line, 1);
                $changed = true;
                $fixedAnon++;
                continue;
            }

            // 规则 2：具名方法 —— 从调用点匹配（伪方法名的第一个 token 是参数类型，无法直接映射；
            // 用参数变量序列 + 调用点方法名唯一性推断）
            // 简化：如果调用点只有一个方法且其参数数量与 params 变量数匹配，则采用
            $paramCount = count($paramVars);
            $candidates = [];
            foreach ($calledMethods as $cm) {
                // 找到该方法定义（未损坏时）的参数数量 —— 损坏后无法直接获取，改用调用点猜测
                $candidates[] = $cm;
            }
            // 保守策略：不自动猜测具名方法名，交由人工/子代理处理
            $rel = str_replace('D:/Software/PhpProject/Demo/co_crmv5/', '', str_replace('\\', '/', $path));
            $unresolved[] = "$rel:" . ($i + 1) . ": " . trim($line);
        }
        if ($changed) {
            file_put_contents($path, implode("\n", $lines));
            echo "fixed-anon: " . str_replace('D:/Software/PhpProject/Demo/co_crmv5/', '', str_replace('\\', '/', $path)) . "\n";
        }
    }
}

echo "\n=== 自动修复匿名函数: $fixedAnon 处 ===\n";
echo "=== 待人工恢复的具名方法: " . count($unresolved) . " 处 ===\n";
file_put_contents('D:/Software/PhpProject/Demo/co_crmv5/storage/app/tmp/unresolved-methods.txt', implode("\n", $unresolved));
foreach (array_slice($unresolved, 0, 40) as $u) {
    echo "  $u\n";
}
