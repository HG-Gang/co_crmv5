<?php
/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 18:00
 */

/**
 * audit-old-routes-coverage
 *
 * 文件功能：
 * - 独立复刻旧项目路由清点：解析旧项目三个路由文件（admin.php / routes-admin.php / routes.php），
 *   处理 Route::group 前缀嵌套，收集全部 HTTP 方法 + URI + 动作三元组；
 * - 与《旧项目模块逻辑迁移核验矩阵.json》按 方法+URI 逐一比对，输出「旧路由存在但矩阵缺行」与
 *   「矩阵有行但旧路由已不存在」两个方向的差异清单。
 * - 目的：验证矩阵 475 行是否完整覆盖旧项目全部路由（矩阵是权威账本，任何缺行都必须补记）。
 *
 * 使用方式：
 * - php scripts/audit-old-routes-coverage.php
 *
 * 返回值：
 * - exit 0：双向无差异；exit 1：存在差异（明细已输出）。
 */

declare(strict_types=1);

$oldRoot = 'D:/Software/PhpProject/Demo/new_co_gmtk_crmv3';
$matrixPath = __DIR__ . '/../storage/app/audits/旧项目模块逻辑迁移核验矩阵.json';

$matrix = json_decode(file_get_contents($matrixPath), true);
$matrixKeys = [];
foreach ($matrix['rows'] as $row) {
    $matrixKeys[strtoupper($row['legacy_method']) . ' ' . $row['legacy_uri']] = $row;
}

/**
 * 解析单个旧路由文件的 Route:: 语句，跟踪 group 前缀栈。
 *
 * 逻辑说明：
 * - 旧路由文件是简单的 Laravel 5.x 风格：一行一条 Route::verb('uri', 'Action')，
 *   或 Route::group(['prefix' => 'xxx'], function () { ... });
 * - 用栈处理 group：遇到 group 入栈其 prefix（可能多段用 / 拼接），遇到 group 闭包结束 `});` 出栈；
 * - 输出三元组：METHOD 完整URI => Action（Action 只保留短类名形态，供与矩阵 legacy_action 尾段比对）。
 *
 * @param string $path 路由文件路径。
 * @param array<string, bool> $out 结果集合（键为 METHOD uri，值为 action）。
 */
function parseRouteFile(string $path, array &$out): void
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $prefixStack = [];
    $inBlockComment = false;
    foreach ($lines as $line) {
        $work = $line;
        // 块注释状态机：注释内的任何 Route:: 语句（如旧项目 routes.php:74-76 的禁用组）必须忽略。
        if ($inBlockComment) {
            $end = strpos($work, '*/');
            if ($end === false) {
                continue;
            }
            $work = substr($work, $end + 2);
            $inBlockComment = false;
        }
        // 去除行内块注释起点之后的内容与行注释。
        if (($pos = strpos($work, '/*')) !== false) {
            $work = substr($work, 0, $pos);
            $inBlockComment = true;
        }
        if (($pos = strpos($work, '//')) !== false) {
            $work = substr($work, 0, $pos);
        }
        $trimmed = trim($work);
        if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === '*') {
            continue;
        }
        if (preg_match('/Route::group\(\s*\[[^\]]*[\'"]prefix[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', $trimmed, $m)) {
            $prefixStack[] = trim($m[1], '/');
            continue;
        }
        // 旧项目的 admin 路由组使用动态前缀 route_prefix()（app/common/function.php:102 返回 /index/admin）；
        // 矩阵按解析后的完整 URI 记录，这里按同一取值入栈，保证两边口径一致。
        if (strpos($trimmed, "Route::group") !== false && strpos($trimmed, 'route_prefix()') !== false) {
            $prefixStack[] = 'index/admin';
            continue;
        }
        if (preg_match('/^\}\);/', $trimmed) && count($prefixStack) > 0) {
            array_pop($prefixStack);
            continue;
        }
        if (preg_match('/Route::(get|post|put|patch|delete|any)\(\s*[\'"]([^\'"]*)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $trimmed, $m)) {
            $verbs = strtolower($m[1]) === 'any'
                ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
                : [strtoupper($m[1])];
            $uri = implode('/', array_filter(array_merge($prefixStack, [trim($m[2], '/')])));
            foreach ($verbs as $verb) {
                $out[$verb . ' ' . $uri] = $m[3];
            }
        }
    }
}

$oldRoutes = [];
foreach (['app/Http/admin.php', 'app/Http/routes-admin.php', 'app/Http/routes.php'] as $routeFile) {
    $path = $oldRoot . '/' . $routeFile;
    if (is_file($path)) {
        parseRouteFile($path, $oldRoutes);
    }
}
echo 'old routes parsed: ' . count($oldRoutes) . PHP_EOL;

$missingInMatrix = [];
$extraInMatrix = [];
foreach ($oldRoutes as $key => $action) {
    if (isset($matrixKeys[$key])) {
        continue;
    }
    // 动作兼容：矩阵 legacy_action 是 FQCN；此处按短名尾段宽松比对（key 完全不匹配才报）。
    $missingInMatrix[$key] = $action;
}
foreach ($matrixKeys as $key => $row) {
    if (!isset($oldRoutes[$key])) {
        $extraInMatrix[$key] = (string) ($row['legacy_action'] ?? '');
    }
}

echo 'old routes MISSING from matrix: ' . count($missingInMatrix) . PHP_EOL;
foreach ($missingInMatrix as $key => $action) {
    echo '  MISSING-IN-MATRIX ', $key, ' => ', $action, PHP_EOL;
}
echo 'matrix rows without matching old route (informational): ' . count($extraInMatrix) . PHP_EOL;
foreach ($extraInMatrix as $key => $action) {
    echo '  EXTRA-IN-MATRIX ', $key, ' => ', $action, PHP_EOL;
}

exit((count($missingInMatrix) + count($extraInMatrix) > 0) ? 1 : 0);
