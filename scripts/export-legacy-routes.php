<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:29
 */

/**
 * 旧项目路由清单导出脚本。
 *
 * 脚本用途：
 * - 启动旧项目（Laravel 内核）并枚举其全部已注册路由，导出为 JSON 清单
 *   （含 HTTP 方法、URI、路由名、控制器动作），供路由映射审计使用。
 *
 * 运行方式：
 * - php scripts/export-legacy-routes.php --root=<旧项目根目录> --output=<输出json文件>
 * - 退出码：0=成功；2=参数缺失/根目录无效；3=无法创建输出目录；4=无法写入文件。
 */

$options = getopt('', ['root:', 'output:']);
$root = isset($options['root']) ? rtrim($options['root'], "\\/") : '';
$output = $options['output'] ?? '';

if ($root === '' || $output === '' || ! is_file($root . '/bootstrap/autoload.php')) {
    fwrite(STDERR, "Usage: php export-legacy-routes.php --root=<legacy-root> --output=<json-file>\n");
    exit(2);
}

$originalDirectory = getcwd();
if (! preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $output)) {
    $output = $originalDirectory . DIRECTORY_SEPARATOR . $output;
}

$legacyEnvironmentFile = $root . '/.env';
if (is_file($legacyEnvironmentFile)) {
    foreach (file($legacyEnvironmentFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (! preg_match('/^\s*([A-Z_][A-Z0-9_]*)\s*=/', $line, $matches)) {
            continue;
        }

        $key = $matches[1];
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

chdir($root);
require $root . '/bootstrap/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$routes = [];
foreach ($app['router']->getRoutes() as $route) {
    $routes[] = [
        'methods' => array_values($route->methods()),
        'uri' => $route->uri(),
        'name' => $route->getName(),
        'action' => $route->getActionName(),
    ];
}

usort($routes, function (array $left, array $right): int {
    return [$left['uri'], implode(',', $left['methods']), $left['action']]
        <=> [$right['uri'], implode(',', $right['methods']), $right['action']];
});

$directory = dirname($output);
if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
    fwrite(STDERR, "Cannot create output directory: {$directory}\n");
    exit(3);
}

$json = json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($output, $json . PHP_EOL) === false) {
    fwrite(STDERR, "Cannot write route inventory: {$output}\n");
    exit(4);
}

chdir($originalDirectory);
fwrite(STDOUT, 'Exported ' . count($routes) . " routes to {$output}\n");
