<?php
/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 17:20
 */

/**
 * audit-module-parity
 *
 * 文件功能：
 * - Phase 全量对照审计工具：以旧项目（new_co_gmtk_crmv3，只读）源码为基准做独立清点，
 *   与《旧项目模块逻辑迁移核验矩阵.json》交叉核对，回答「旧项目每个模块的每段逻辑是否都已被新项目覆盖」。
 * - 清点维度一（后端）：旧项目 app/Http/Controllers 全部控制器公共方法（排除构造与魔术方法），
 *   逐一检查是否出现在矩阵的 legacy_action 集合中；未命中的按「基类继承方法/白名单/真缺口」分类输出。
 * - 清点维度二（前端视图）：旧项目 resources/views 的全部 Blade 视图，列出对应新项目的四套 UI 家族
 *   覆盖情况（admin layui/crmui、front layui/crmui），便于人工确认视图级等价。
 * - 只读旧项目与新项目源码，不写任何数据库。
 *
 * 使用方式：
 * - php scripts/audit-module-parity.php
 *
 * 返回值：
 * - exit 0：后端方法全部可在矩阵中找到归属（或属于已知白名单）；exit 1：存在疑似缺口。
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$oldRoot = 'D:/Software/PhpProject/Demo/new_co_gmtk_crmv3';
$matrixPath = $root . '/storage/app/audits/旧项目模块逻辑迁移核验矩阵.json';

if (!is_dir($oldRoot)) {
    fwrite(STDERR, "old project not found: $oldRoot\n");
    exit(1);
}

// ========== 维度一：旧项目控制器公共方法 vs 矩阵 legacy_action 集合 ==========
$matrix = json_decode(file_get_contents($matrixPath), true);
$rows = $matrix['rows'];
$matrixActions = [];
$verified = 0;
foreach ($rows as $row) {
    $action = (string) ($row['legacy_action'] ?? '');
    if ($action !== '') {
        $matrixActions[$action] = true;
    }
    if (($row['verification']['state'] ?? '') === 'verified') {
        $verified++;
    }
}
echo "matrix rows: " . count($rows) . ", verified: $verified, distinct legacy actions: " . count($matrixActions) . PHP_EOL;

// 基类提供的公共方法（继承自框架控制器），子类虽然可调用但不承载业务，允许不出现在矩阵中。
$frameworkMethods = [
    'middleware', 'validate', 'authorize', 'dispatch', 'callAction', 'getMiddleware',
    'resolveMethod', 'resolveClass', 'buildActionCallable', 'getController', 'parseAction',
    'handle', 'execute', 'setupLayout', 'getVendorDir', 'missingMethod', 'getTestCaseName',
];

$oldMethods = [];
$controllersIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($oldRoot . '/app/Http/Controllers'));
foreach ($controllersIterator as $fileInfo) {
    if ($fileInfo->isDir() || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $code = file_get_contents($fileInfo->getPathname());
    if (preg_match_all('/public\s+function\s+(\w+)\s*\(/', $code, $matches, PREG_SET_ORDER) < 1) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($oldRoot) + 1));
    $classPath = 'App\\Http\\Controllers\\' . str_replace(['app/Http/Controllers/', '.php'], '', $relative);
    $classPath = str_replace('/', '\\', $classPath);
    foreach ($matches as $hit) {
        $methodName = $hit[1];
        if (in_array($methodName, ['__construct', '__destruct'], true)) {
            continue;
        }
        $oldMethods[$classPath . '@' . $methodName] = $relative . '@' . $methodName;
    }
}
echo 'old controller public methods (excl. constructor): ' . count($oldMethods) . PHP_EOL;

$unmapped = [];
foreach ($oldMethods as $fqcn => $display) {
    if (isset($matrixActions[$fqcn])) {
        continue;
    }
    $methodName = substr($display, strrpos($display, '@') + 1);
    if (in_array($methodName, $frameworkMethods, true)) {
        continue;
    }
    $unmapped[] = $display;
}
echo 'methods NOT found in matrix: ' . count($unmapped) . PHP_EOL;
foreach ($unmapped as $entry) {
    echo '  UNMAPPED ', $entry, PHP_EOL;
}

// ========== 维度二：旧项目视图 → 新项目四套 UI 家族覆盖 ==========
$oldViewDir = $oldRoot . '/resources/views';
$newFamilyDirs = [
    'admin_layui' => $root . '/resources/admin/layui',
    'admin_crmui' => $root . '/resources/admin/crmui',
    'front_layui' => $root . '/resources/front/layui',
    'front_crmui' => $root . '/resources/front/crmui',
];
$newModules = [];
foreach ($newFamilyDirs as $family => $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir() || substr($fileInfo->getFilename(), -10) !== '.blade.php') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($dir) + 1));
        $moduleKey = strtok($relative, '/');
        $newModules[$family][$moduleKey] = true;
    }
}

$oldViews = [];
$viewIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($oldViewDir));
foreach ($viewIterator as $fileInfo) {
    if ($fileInfo->isDir() || substr($fileInfo->getFilename(), -10) !== '.blade.php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($oldViewDir) + 1));
    $oldViews[] = $relative;
}
sort($oldViews);
echo 'old blade views: ' . count($oldViews) . PHP_EOL;
foreach ($oldViews as $view) {
    $line = '  old view: ' . $view . ' =>';
    foreach ($newFamilyDirs as $family => $dir) {
        $line .= ' ' . $family . ':' . (isset($newModules[$family]) && isset($newModules[$family][strtok($view, '/')]) ? 'Y' : 'N');
    }
    echo $line . PHP_EOL;
}

exit(count($unmapped) === 0 ? 0 : 1);
