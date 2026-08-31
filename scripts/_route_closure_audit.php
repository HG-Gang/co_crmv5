<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 02:10
 */

/**
 * 路由闭环审计脚本（只读诊断，不参与业务）。
 *
 * 文件功能：把旧项目全部路由（含 Route::group 前缀展开）逐条与新项目运行时路由表
 *           做动作级/URI 级交叉核对，输出真正未映射的动作与 URI，供模块补齐分派使用。
 *
 * 输出：scripts/_route_closure_audit_report.txt（UTF-8）。
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$newRoot = dirname(__DIR__);
$configuredOldRoot = getenv('LEGACY_PROJECT_ROOT');
$oldRootCandidate = is_string($configuredOldRoot) && trim($configuredOldRoot) !== ''
    ? rtrim(trim($configuredOldRoot), "\\/")
    : dirname($newRoot) . DIRECTORY_SEPARATOR . 'new_co_gmtk_crmv3';
$oldRoot = realpath($oldRootCandidate);
if ($oldRoot === false || !is_dir($oldRoot)) {
    throw new RuntimeException('旧项目目录不存在：' . $oldRootCandidate);
}

$oldFiles = [];
foreach (['routes.php', 'routes-admin.php', 'admin.php'] as $routeFile) {
    $path = $oldRoot . DIRECTORY_SEPARATOR . 'app'
        . DIRECTORY_SEPARATOR . 'Http'
        . DIRECTORY_SEPARATOR . $routeFile;
    $realPath = realpath($path);
    if ($realPath === false || !is_file($realPath)) {
        throw new RuntimeException('旧项目关键路由不存在：' . $path);
    }
    $oldFiles[] = $realPath;
}

/**
 * 解析旧路由文件：展开 Route::group 前缀并提取 Route::method(uri, Controller@action)。
 *
 * @param array $files 旧路由文件绝对路径列表。
 * @return array 每条路由含 method/uri/target/prefix 的数组。
 */
function parseOldRoutes(array $files): array
{
    $routes = [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            throw new RuntimeException('旧项目关键路由不存在：' . $file);
        }
        $code = file_get_contents($file);
        // 去除注释，避免误匹配。
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);
        $code = preg_replace('/\/\/[^\n]*/', '', $code);
        // 展开 Route::group(['prefix' => 'xx'], function () { ... }); 为带前缀标记的代码段。
        if (preg_match_all('/Route::group\s*\(\s*\[[^\]]*?[\x27"]prefix[\x27"]\s*=>\s*[\x27"]([^\x27"]+)[\x27"][^\]]*\]\s*,\s*function\s*\(\s*\)\s*\{\s*(.*?)\s*\}\)\s*;/s', $code, $m, PREG_SET_ORDER)) {
            foreach ($m as $group) {
                $prefix = trim($group[1], '/');
                $inner = $group[2];
                if (preg_match_all('/Route::(\w+)\(\s*[\x27"]([^\x27"]+)[\x27"]\s*,\s*[\x27"]([A-Za-z0-9_\\\\]+)@(\w+)[\x27"]/', $inner, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $item) {
                        $routes[] = [
                            'method' => strtoupper($item[1]),
                            'uri' => trim($prefix . '/' . trim($item[2], '/'), '/'),
                            'target' => ltrim($item[3], '\\') . '@' . $item[4],
                            'prefix' => $prefix,
                        ];
                    }
                }
            }
            // 删除已展开的 group 代码段，再解析顶层路由。
            $code = preg_replace('/Route::group\s*\(.*?function\s*\(\s*\)\s*\{.*?\}\s*\)\s*;/s', '', $code);
        }
        if (preg_match_all('/Route::(\w+)\(\s*[\x27"]([^\x27"]+)[\x27"]\s*,\s*[\x27"]([A-Za-z0-9_\\\\]+)@(\w+)[\x27"]/', $code, $m, PREG_SET_ORDER)) {
            foreach ($m as $item) {
                $routes[] = [
                    'method' => strtoupper($item[1]),
                    'uri' => trim($item[2], '/'),
                    'target' => ltrim($item[3], '\\') . '@' . $item[4],
                    'prefix' => '',
                ];
            }
        }
    }
    return $routes;
}

/**
 * URI 归一化：{参数} 一律替换为 {}。
 */
function normUri(string $uri): string
{
    $uri = trim($uri, '/');
    return preg_replace('/\{[^}]*\}/', '{}', $uri);
}

/** 从当前项目运行时路由表构造审计数据，不维护第二份路由清单。 */
function loadCurrentRoutes(string $newRoot): array
{
    $autoloadPath = $newRoot . DIRECTORY_SEPARATOR . 'vendor'
        . DIRECTORY_SEPARATOR . 'autoload.php';
    $bootstrapPath = $newRoot . DIRECTORY_SEPARATOR . 'bootstrap'
        . DIRECTORY_SEPARATOR . 'app.php';
    if (!is_file($autoloadPath) || !is_file($bootstrapPath)) {
        throw new RuntimeException('新项目引导文件不存在：' . $newRoot);
    }

    require_once $autoloadPath;
    $app = require $bootstrapPath;
    $app->make(Kernel::class)->bootstrap();

    $routes = [];
    foreach ($app['router']->getRoutes() as $route) {
        $routes[] = [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'action' => $route->getActionName(),
        ];
    }

    return $routes;
}

$oldRoutes = parseOldRoutes($oldFiles);
$newRoutes = loadCurrentRoutes($newRoot);

// 新路由索引：method+normUri -> true；action 短名（类短名@方法）-> true；方法名集合。
$newByKey = [];
$newByAction = [];
$newMethodSet = [];
$newActions = [];
foreach ($newRoutes as $r) {
    $uri = normUri($r['uri'] ?? '');
    foreach (explode('|', $r['method'] ?? '') as $m) {
        $m = strtoupper(trim($m));
        if ($m === '') {
            continue;
        }
        $newByKey[$m . ' ' . $uri] = true;
        if ($m === 'GET') {
            $newByKey['HEAD ' . $uri] = true;
        }
    }
    $action = $r['action'] ?? '';
    if ($action && $action !== 'Closure') {
        $newActions[] = $action;
        $short = preg_replace('/^.*\\\\/', '', $action);
        $newByAction[$short] = true;
        $methodName = substr($action, strrpos($action, '@') + 1);
        $newMethodSet[$methodName] = true;
    }
}

$lines = [];
$lines[] = '旧路由总数: ' . count($oldRoutes);
$lines[] = '新路由总数: ' . count($newRoutes);
$lines[] = '';

// 1) URI+方法 精确未命中。
$uriMiss = [];
foreach ($oldRoutes as $r) {
    $key = $r['method'] . ' ' . normUri($r['uri']);
    if (!isset($newByKey[$key])) {
        $uriMiss[] = $r;
    }
}
$lines[] = '==== A. URI+方法精确未命中 (' . count($uriMiss) . ') ====';
foreach ($uriMiss as $r) {
    $lines[] = $r['method'] . ' /' . $r['uri'] . ' | ' . $r['target'] . ' | prefix=' . $r['prefix'];
}

// 2) 动作级未命中：旧目标完整类名@方法 未出现在新路由动作中，且方法名也不存在。
$actionMiss = [];
foreach ($oldRoutes as $r) {
    $short = preg_replace('/^.*\\\\/', '', $r['target']);
    $methodName = substr($r['target'], strrpos($r['target'], '@') + 1);
    if (!isset($newByAction[$short]) && !isset($newMethodSet[$methodName])) {
        $actionMiss[$r['target']] = true;
    }
}
$lines[] = '';
$lines[] = '==== B. 动作完全未映射（类短名与方法名均无） (' . count($actionMiss) . ') ====';
foreach (array_keys($actionMiss) as $t) {
    $lines[] = $t;
}

// 3) 方法名存在但类短名不同（可能语义迁移到新控制器）。
$methodOnly = [];
foreach ($oldRoutes as $r) {
    $short = preg_replace('/^.*\\\\/', '', $r['target']);
    $methodName = substr($r['target'], strrpos($r['target'], '@') + 1);
    if (!isset($newByAction[$short]) && isset($newMethodSet[$methodName])) {
        $methodOnly[$r['target']] = true;
    }
}
$lines[] = '';
$lines[] = '==== C. 方法名已存在但类名不同 (' . count($methodOnly) . ') ====';
foreach (array_keys($methodOnly) as $t) {
    $lines[] = $t;
}

$reportPath = __DIR__ . DIRECTORY_SEPARATOR . '_route_closure_audit_report.txt';
if (file_put_contents($reportPath, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
    throw new RuntimeException('无法写入路由闭环审计报告：' . $reportPath);
}
echo implode(PHP_EOL, $lines);
