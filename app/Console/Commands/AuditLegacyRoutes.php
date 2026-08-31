<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * 遗留路由审计命令。
 *
 * 文件功能：
 * - 读取旧系统遗留路由清单（legacy route JSON），与当前应用注册的路由
 *   逐条对比，输出匹配状态（matched / intentional_method_restriction /
 *   missing / method_gap 等），并生成 JSON 与 Markdown 审计报告。
 *
 * 适用场景：
 * - 旧系统迁移到新系统后，手动执行以核对旧路由是否全部在新系统中
 *   有对应实现，发现遗漏的路由（gap）。
 *
 * 入参例子：
 * - php artisan legacy-routes:audit storage/legacy-routes.json
 * - php artisan legacy-routes:audit legacy.json --scope=admin
 * - php artisan legacy-routes:audit legacy.json --policy=policy.json --json=out.json --markdown=out.md
 *
 * 返回值：
 * - 0=无遗漏路由（所有旧路由均已匹配或为有意的方法限制）；
 * - 1=存在遗漏路由（gap）；
 * - 2=参数非法（scope 不合法）、文件读取/解析失败或输出文件写入失败。
 *
 * 异常或失败场景：
 * - 遗留路由 JSON 不存在/格式非法、策略 JSON 非法、输出目录无法创建、
 *   JSON 编码失败等均抛出 RuntimeException，命令捕获后输出错误并返回 2。
 */
namespace App\Console\Commands;

use App\Support\LegacyRouteInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class AuditLegacyRoutes extends Command
{
    /** @var string 命令签名：必传遗留路由 JSON 路径，可选 scope/policy/json/markdown。 */
    protected $signature = 'legacy-routes:audit
        {legacy : Legacy route JSON path}
        {--scope=all : all, front, or admin}
        {--policy= : Explicit accepted HTTP method restriction policy JSON path}
        {--json= : JSON output path}
        {--markdown= : Markdown output path}';

    /** @var string 命令说明。 */
    protected $description = 'Compare framework-resolved legacy routes with the current application routes';

    /**
     * 执行命令：读取并过滤遗留路由，与当前路由对比，输出 JSON/Markdown 报告。
     *
     * @param LegacyRouteInventory $inventory 路由对比服务（容器注入）。
     * @return int 0=无遗漏；1=存在遗漏路由；2=参数或文件处理失败。
     * @throws RuntimeException 文件读取/解析失败时抛出（被本方法捕获）。
     */
    public function handle(LegacyRouteInventory $inventory): int
    {
        try {
            $legacyFile = $this->resolvePath((string) $this->argument('legacy'));
            $legacyRoutes = $this->readRoutes($legacyFile);
            $scope = strtolower((string) $this->option('scope'));
            if (! in_array($scope, ['all', 'front', 'admin'], true)) {
                $this->error('Scope must be one of: all, front, admin.');
                return 2;
            }

            $legacyRoutes = $this->filterLegacyRoutes($legacyRoutes, $scope);
            $methodPolicies = $this->readMethodPolicies((string) $this->option('policy'));
            $rows = $inventory->compare($legacyRoutes, $this->currentRoutes(), $methodPolicies);

            $jsonFile = $this->outputPath('json', 'storage/app/audits/legacy-route-audit.json');
            $markdownFile = $this->outputPath('markdown', 'storage/app/audits/legacy-route-audit.md');
            $this->writeFile($jsonFile, $this->encodeJson($rows));
            $this->writeFile($markdownFile, $this->renderMarkdown($rows, $scope));

            $gaps = array_values(array_filter($rows, static function (array $row): bool {
                return ! in_array($row['status'], ['matched', 'intentional_method_restriction'], true);
            }));
            $restrictions = count(array_filter($rows, static function (array $row): bool {
                return $row['status'] === 'intentional_method_restriction';
            }));

            $this->line(sprintf(
                'Audited %d legacy routes: %d matched, %d intentional method restrictions, %d gaps.',
                count($rows),
                count($rows) - $restrictions - count($gaps),
                $restrictions,
                count($gaps)
            ));
            $this->line('JSON: ' . $jsonFile);
            $this->line('Markdown: ' . $markdownFile);

            return $gaps === [] ? 0 : 1;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return 2;
        }
    }

    /**
     * 读取并校验遗留路由 JSON 文件。
     *
     * @param string $path 遗留路由 JSON 文件路径。
     * @return array 路由数组，每项含 methods（数组）与 uri（字符串）。
     * @throws RuntimeException 文件不存在、JSON 非法或行结构不合法时抛出。
     */
    private function readRoutes(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Legacy route JSON does not exist: ' . $path);
        }

        $routes = json_decode((string) file_get_contents($path), true);
        if (! is_array($routes)) {
            throw new RuntimeException('Legacy route JSON is invalid: ' . $path);
        }

        foreach ($routes as $index => $route) {
            if (! is_array($route) || ! isset($route['methods'], $route['uri'])) {
                throw new RuntimeException('Legacy route row is invalid at index ' . $index . '.');
            }
        }

        return $routes;
    }

    /**
     * 读取并校验 HTTP 方法限制策略 JSON。
     *
     * @param string $path 策略 JSON 文件路径；空字符串表示不启用策略。
     * @return array 策略数组：uri => ['accepted_current_methods' => array, 'reason' => string]。
     * @throws RuntimeException 文件不存在、JSON 非法或策略结构不合法时抛出。
     */
    private function readMethodPolicies(string $path): array
    {
        if ($path === '') {
            return [];
        }

        $resolvedPath = $this->resolvePath($path);
        if (! is_file($resolvedPath)) {
            throw new RuntimeException('Route method policy JSON does not exist: ' . $resolvedPath);
        }

        $policies = json_decode((string) file_get_contents($resolvedPath), true);
        if (! is_array($policies)) {
            throw new RuntimeException('Route method policy JSON is invalid: ' . $resolvedPath);
        }

        foreach ($policies as $uri => $policy) {
            if (! is_string($uri)
                || ! is_array($policy)
                || ! isset($policy['accepted_current_methods'])
                || ! is_array($policy['accepted_current_methods'])
                || trim((string) ($policy['reason'] ?? '')) === '') {
                throw new RuntimeException('Route method policy is invalid for URI: ' . $uri);
            }
        }

        return $policies;
    }

    /**
     * 汇总当前应用全部已注册路由。
     *
     * @return array 路由数组，每项含 methods、uri、name、action。
     */
    private function currentRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $routes[] = [
                'methods' => array_values($route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ];
        }

        return $routes;
    }

    /**
     * 按 scope 过滤遗留路由：all 全量；admin 只留 index 前缀；front 留用户端/代理端相关前缀。
     *
     * @param array $routes 遗留路由数组。
     * @param string $scope 过滤范围：all / front / admin。
     * @return array 过滤后的路由数组（重建索引）。
     */
    private function filterLegacyRoutes(array $routes, string $scope): array
    {
        if ($scope === 'all') {
            return array_values($routes);
        }

        return array_values(array_filter($routes, function (array $route) use ($scope): bool {
            $uri = ltrim((string) $route['uri'], '/');
            if ($scope === 'admin') {
                return $uri === 'index' || strpos($uri, 'index/') === 0;
            }

            if ($uri === '') {
                return true;
            }

            foreach ([
                'user/',
                'agents/',
                'en/user/',
                'show/user_detail/',
                'open/order_detail/',
                'close/order_detail/',
            ] as $prefix) {
                if (strpos($uri, $prefix) === 0) {
                    return true;
                }
            }

            $action = (string) ($route['action'] ?? '');
            foreach ([
                '\\Http\\Controllers\\User\\',
                '\\Http\\Controllers\\PayController\\',
                '\\Http\\Controllers\\Admin\\BigNumberController@',
                '\\Http\\Controllers\\HelloWordController@',
            ] as $actionPart) {
                if (strpos($action, $actionPart) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * 将审计结果渲染为 Markdown 报告文本。
     *
     * @param array $rows 对比结果行数组。
     * @param string $scope 审计范围（写入报告头部）。
     * @return string Markdown 文本（含表头与全部数据行）。
     */
    private function renderMarkdown(array $rows, string $scope): string
    {
        $gaps = count(array_filter($rows, static function (array $row): bool {
            return ! in_array($row['status'], ['matched', 'intentional_method_restriction'], true);
        }));
        $restrictions = count(array_filter($rows, static function (array $row): bool {
            return $row['status'] === 'intentional_method_restriction';
        }));

        $lines = [
            '# Legacy Route Inventory Audit',
            '',
            '- Scope: `' . $scope . '`',
            '- Total legacy routes: ' . count($rows),
            '- Matched: ' . (count($rows) - $restrictions - $gaps),
            '- Intentional method restrictions: ' . $restrictions,
            '- Gaps: ' . $gaps,
            '',
            '| Legacy methods | Legacy URI | Legacy action | Status | Missing methods | Decision reason | Current name | Current action |',
            '|---|---|---|---|---|---|---|---|',
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | `%s` | `%s` | `%s` | %s | %s | `%s` | `%s` |',
                $this->escapeMarkdown(implode(',', $row['legacy_methods'])),
                $this->escapeMarkdown($row['legacy_uri']),
                $this->escapeMarkdown((string) $row['legacy_action']),
                $this->escapeMarkdown($row['status']),
                $this->escapeMarkdown(implode(',', $row['missing_methods'])),
                $this->escapeMarkdown((string) ($row['decision_reason'] ?? '')),
                $this->escapeMarkdown((string) $row['current_name']),
                $this->escapeMarkdown((string) $row['current_action'])
            );
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * 将审计结果编码为美化 JSON 文本。
     *
     * @param array $rows 对比结果行数组。
     * @return string JSON 文本（含结尾换行）。
     * @throws RuntimeException JSON 编码失败时抛出。
     */
    private function encodeJson(array $rows): string
    {
        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode route audit JSON.');
        }

        return $json . PHP_EOL;
    }

    /**
     * 解析指定选项的输出路径，未提供时使用默认路径。
     *
     * @param string $option 选项名（json 或 markdown）。
     * @param string $default 默认相对路径。
     * @return string 解析后的绝对路径。
     */
    private function outputPath(string $option, string $default): string
    {
        $value = (string) $this->option($option);
        return $this->resolvePath($value !== '' ? $value : $default);
    }

    /**
     * 将相对路径解析为基于项目根目录的绝对路径。
     *
     * @param string $path 输入路径（支持绝对路径原样返回）。
     * @return string 解析后的绝对路径。
     */
    private function resolvePath(string $path): string
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    /**
     * 写入审计输出文件（目录不存在时自动创建）。
     *
     * @param string $path 目标文件路径。
     * @param string $contents 文件内容。
     * @return void 无返回值。
     * @throws RuntimeException 目录创建失败或文件写入失败时抛出。
     */
    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create audit directory: ' . $directory);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write audit file: ' . $path);
        }
    }

    /**
     * 转义 Markdown 表格特殊字符（竖线、换行）。
     *
     * @param string $value 原始文本。
     * @return string 转义后的文本。
     */
    private function escapeMarkdown(string $value): string
    {
        return str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], $value);
    }
}
