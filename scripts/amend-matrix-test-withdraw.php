<?php
/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 18:20
 */

/**
 * amend-matrix-test-withdraw
 *
 * 文件功能：
 * - 补记 2026-08-29 对照审计发现的唯一矩阵缺行：旧项目 `POST test/withdraw`（HelloWordController@withdraw）。
 * - 同步三处账本：核验矩阵 JSON（新增 476 行 + summary）、legacy-routes.json 底稿、
 *   docs/audits/旧项目路由核验证据.json 的 front_disabled_maintenance_2026_07_26 组（17→18 条）。
 * - 新行与新证据条目完全镜像同组兄弟行（test/deposit）的七维证据结构；新项目侧代码与
 *   FrontLegacyMaintenanceRuntimeClosureModuleTest / FrontLegacyRouteCompatibilityTest 早已覆盖该入口。
 *
 * 使用方式：
 * - php scripts/amend-matrix-test-withdraw.php（幂等：重复运行检测到已存在即跳过）
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');

// ========== 1. 核验矩阵 JSON：插入新行并更新 summary ==========
$matrixPath = $root . '/storage/app/audits/旧项目模块逻辑迁移核验矩阵.json';
$matrix = json_decode(file_get_contents($matrixPath), true);

$exists = false;
$insertAt = null;
foreach ($matrix['rows'] as $index => $row) {
    if ($row['legacy_uri'] === 'test/withdraw' && $row['legacy_method'] === 'POST') {
        $exists = true;
    }
    if ($row['legacy_uri'] === 'test/deposit' && $insertAt === null) {
        $insertAt = $index + 1;
    }
}

if ($exists) {
    echo "matrix already contains test/withdraw\n";
} else {
    $sibling = $matrix['rows'][$insertAt - 1];
    $newRow = $sibling;
    $newRow['legacy_uri'] = 'test/withdraw';
    $newRow['legacy_action'] = 'App\\Http\\Controllers\\HelloWordController@withdraw';
    $newRow['legacy_source'] = 'app/Http/Controllers/HelloWordController.php:59';
    $newRow['legacy_source_references'] = ['app/Http/Controllers/HelloWordController.php:59'];
    $newRow['request_fields'] = ['user_id', 'amt', 'cmt'];
    $newRow['current_name'] = 'legacy_test_withdraw';
    $newRow['current_action'] = 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testWithdraw';
    $newRow['verification']['conclusion'] = '旧前台 test/withdraw 测试出金入口已失败关闭：项目2保留 URI 兼容并统一返回 423 OPERATION_NOT_ALLOWED，不产生数据库、MT4 出金或资金副作用。本行由 2026-08-29 全量对照审计补记（同组 17 条早已核验，本条此前漏记）。';
    $newRow['verification']['verified_at'] = '2026-08-29T18:20:00+08:00';
    $newRow['verification']['current_route']['name'] = 'legacy_test_withdraw';
    $newRow['verification']['current_route']['action'] = 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testWithdraw';
    array_splice($matrix['rows'], $insertAt, 0, [$newRow]);
    $matrix['summary']['legacy_route_methods'] = count($matrix['rows']);
    $matrix['summary']['verified'] = count(array_filter($matrix['rows'], static function ($row): bool {
        return ($row['verification']['state'] ?? '') === 'verified';
    }));
    file_put_contents(
        $matrixPath,
        json_encode($matrix, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo "matrix amended: rows=" . count($matrix['rows']) . ' verified=' . $matrix['summary']['verified'] . PHP_EOL;
}

// ========== 2. legacy-routes.json 底稿：按 URI 序插入 ==========
$legacyRoutesPath = $root . '/storage/app/audits/legacy-routes.json';
$legacyRoutes = json_decode(file_get_contents($legacyRoutesPath), true);
$exists = false;
$insertAt = count($legacyRoutes);
foreach ($legacyRoutes as $index => $entry) {
    if ($entry['uri'] === 'test/withdraw') {
        $exists = true;
    }
    if (strcmp($entry['uri'], 'test/withdraw') > 0 && $insertAt === count($legacyRoutes)) {
        $insertAt = $index;
    }
}
if (!$exists) {
    array_splice($legacyRoutes, $insertAt, 0, [[
        'methods' => ['POST'],
        'uri' => 'test/withdraw',
        'name' => null,
        'action' => 'App\\Http\\Controllers\\HelloWordController@withdraw',
    ]]);
    file_put_contents(
        $legacyRoutesPath,
        json_encode($legacyRoutes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo "legacy-routes amended: total=" . count($legacyRoutes) . PHP_EOL;
} else {
    echo "legacy-routes already contains test/withdraw\n";
}

// ========== 3. 证据 JSON：维护组 17→18 条 ==========
$evidencePath = $root . '/docs/audits/旧项目路由核验证据.json';
$evidence = json_decode(file_get_contents($evidencePath), true);
$amended = false;
foreach ($evidence['verification_groups'] as $index => $group) {
    if (($group['id'] ?? '') !== 'front_disabled_maintenance_2026_07_26') {
        continue;
    }
    $exists = false;
    foreach ($group['routes'] as $route) {
        if (($route['legacy_uri'] ?? '') === 'test/withdraw') {
            $exists = true;
        }
    }
    if (!$exists) {
        $evidence['verification_groups'][$index]['routes'][] = [
            'legacy_method' => 'POST',
            'legacy_uri' => 'test/withdraw',
            'current_route' => [
                'name' => 'legacy_test_withdraw',
                'action' => 'App\\Http\\Controllers\\Front\\LegacyMaintenanceController@testWithdraw',
            ],
        ];
        $amended = true;
        file_put_contents(
            $evidencePath,
            json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
        echo 'evidence group amended: routes=' . count($evidence['verification_groups'][$index]['routes']) . PHP_EOL;
    } else {
        echo "evidence group already contains test/withdraw\n";
    }
}
if (!$amended) {
    echo "evidence group untouched (already amended or not found)\n";
}

echo "done\n";
