<?php
/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 17:40
 */

/**
 * classify-unmapped-methods
 *
 * 文件功能：
 * - 配合 scripts/audit-module-parity.php 的深挖脚本：把「旧项目有但矩阵 legacy_action 未命中」的
 *   控制器公共方法按三分类定性：
 *   ① ROUTED-IN-OLD：旧项目路由文件确实指向该方法 → 属对照疑点，需按 URI 到矩阵中核对记录；
 *   ② DEAD-IN-OLD：旧项目无任何路由指向该方法 → 旧项目死代码，不构成新项目缺口；
 *   ③ ALIAS-MATCH：矩阵中存在 legacy_action 写法不同（如类名 V3 后缀差异）但 URI/名称能对上的记录。
 * - 旧项目路由文件：app/Http/admin.php、app/Http/routes-admin.php、app/Http/routes.php（只读）。
 *
 * 使用方式：
 * - php scripts/classify-unmapped-methods.php
 */

declare(strict_types=1);

$oldRoot = 'D:/Software/PhpProject/Demo/new_co_gmtk_crmv3';
$matrixPath = __DIR__ . '/../storage/app/audits/旧项目模块逻辑迁移核验矩阵.json';

// 复用 parity 审计得到未命中集合：直接在此重算（避免中间文件依赖）。
$matrix = json_decode(file_get_contents($matrixPath), true);
$matrixActions = [];
$matrixUris = [];
foreach ($matrix['rows'] as $row) {
    $action = (string) ($row['legacy_action'] ?? '');
    if ($action !== '') {
        $matrixActions[$action] = $row;
    }
    $matrixUris[$row['legacy_method'] . ' ' . $row['legacy_uri']] = $row;
}

$oldMethods = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($oldRoot . '/app/Http/Controllers'));
foreach ($iterator as $fileInfo) {
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
        if (in_array($hit[1], ['__construct', '__destruct'], true)) {
            continue;
        }
        $oldMethods[$classPath . '@' . $hit[1]] = true;
    }
}

// 解析旧项目路由：把 `Controller@method` 形态与 resource/controller 形态都抽出来。
$routedActions = [];
foreach (['app/Http/admin.php', 'app/Http/routes-admin.php', 'app/Http/routes.php'] as $routeFile) {
    $path = $oldRoot . '/' . $routeFile;
    if (!is_file($path)) {
        continue;
    }
    $code = file_get_contents($path);
    // 显式 Controller@method（含 ::class 形态）
    if (preg_match_all('/([A-Za-z_][A-Za-z0-9_\\\\]*)@(\w+)/', $code, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $class = str_replace('\\\\', '\\', $hit[1]);
            if (strpos($class, '\\') === false) {
                $class = 'App\\Http\\Controllers\\' . $class;
            }
            $routedActions[$class . '@' . $hit[2]] = true;
        }
    }
}

foreach ($oldMethods as $action => $ignored) {
    if (isset($matrixActions[$action])) {
        continue;
    }
    $methodName = substr($action, strrpos($action, '@') + 1);
    $framework = ['middleware', 'validate', 'authorize', 'dispatch', 'callAction', 'handle', 'execute', 'setupLayout'];
    if (in_array($methodName, $framework, true)) {
        continue;
    }

    if (isset($routedActions[$action])) {
        echo '① ROUTED-IN-OLD ', $action, PHP_EOL;
        continue;
    }

    // 类名 V3 差异与同名方法兜底：矩阵中存在同名方法的记录则提示 ALIAS 候选。
    $aliasCandidate = 0;
    foreach ($matrixActions as $matrixAction => $row) {
        if (substr($matrixAction, strrpos($matrixAction, '@') + 1) === $methodName) {
            $aliasCandidate++;
        }
    }
    echo $aliasCandidate > 0
        ? '② DEAD-IN-OLD(but matrix has same-name method x' . $aliasCandidate . ') '
        : '② DEAD-IN-OLD ';
    echo $action, PHP_EOL;
}
