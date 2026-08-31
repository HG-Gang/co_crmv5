<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
 */

/**
 * 旧项目源码静态盘点工具。
 *
 * 文件功能：
 * - 不引导旧应用的前提下，递归扫描旧项目 app/Http/Controllers 与 resources/views 下的 PHP 与 Blade 文件。
 * - 通过 token 解析提取 Controller 类名、方法、行号及方法体内的请求字段、数据表、视图、响应类型、条件分支数、返回语句数、静态外部调用等证据。
 * - 提取 Blade 页面表单（action/method/字段）、AJAX 地址、外链 JavaScript 及其中的 AJAX 地址、命名路由调用、上传/下载静态命中。
 * - toMarkdown() 把盘点结果渲染为可直接归档的 Markdown 清单。
 *
 * 适用场景：
 * - 旧项目模块迁移前生成源码逻辑清单，供 LegacyImplementationMatrix 与人工核验使用。
 *
 * 入参例子：
 * - inspect('/path/to/legacy-project') -> ['summary' => [...], 'controllers' => [...], 'blades' => [...]]
 * - toMarkdown($inventory, '旧项目')
 *
 * 返回值：
 * - inspect() 返回 summary（文件/方法/表单/AJAX 计数）与 controllers、blades 明细数组。
 * - toMarkdown() 返回 Markdown 文本字符串。
 *
 * 异常或失败场景：
 * - 旧项目缺少 app/Http/Controllers 或 resources/views 目录时抛出 RuntimeException。
 * - 源码文件读取失败时抛出 RuntimeException。
 */
namespace App\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class LegacySourceInventory
{
    /**
     * 静态盘点旧项目源码：扫描 Controllers 与 views 并提取静态证据。
     *
     * @param string $root 旧项目根目录。
     * @return array<string, mixed> summary（计数）与 controllers、blades 明细。
     * @throws RuntimeException 缺少 Controllers/views 目录或文件读取失败时抛出。
     */
    public function inspect(string $root): array
    {
        $root = rtrim($root, "\\/");
        $controllerDirectory = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers';
        $viewDirectory = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

        // 缺少任一必需目录即失败关闭：清单不完整时禁止继续生成误导性报告。
        if (! is_dir($controllerDirectory) || ! is_dir($viewDirectory)) {
            throw new RuntimeException('Legacy project must contain app/Http/Controllers and resources/views.');
        }

        $controllers = [];
        foreach ($this->findFiles($controllerDirectory, 'php') as $path) {
            $controllers[] = $this->inspectController($path, $root);
        }

        $blades = [];
        foreach ($this->findFiles($viewDirectory, 'blade.php') as $path) {
            $blades[] = $this->inspectBlade($path, $root);
        }
        $scriptSources = [];
        foreach ($blades as $blade) {
            $scriptSources = array_merge($scriptSources, $blade['script_sources']);
        }

        return [
            'summary' => [
                'controller_files' => count($controllers),
                'controller_methods' => array_sum(array_map(static function (array $controller): int {
                    return count($controller['methods']);
                }, $controllers)),
                'blade_files' => count($blades),
                'forms' => array_sum(array_map(static function (array $blade): int {
                    return count($blade['forms']);
                }, $blades)),
                'ajax_endpoints' => array_sum(array_map(static function (array $blade): int {
                    return count($blade['ajax_endpoints']);
                }, $blades)),
                'javascript_files' => count(array_unique($scriptSources)),
                'script_ajax_endpoints' => array_sum(array_map(static function (array $blade): int {
                    return count($blade['script_endpoints']);
                }, $blades)),
            ],
            'controllers' => $controllers,
            'blades' => $blades,
        ];
    }

    /**
     * 把盘点结果渲染为 UTF-8 中文 Markdown 清单。
     *
     * @param array<string, mixed> $inventory inspect() 返回的盘点结果。
     * @param string $projectName 项目名（用于标题）。
     * @return string Markdown 文本。
     */
    public function toMarkdown(array $inventory, string $projectName = '旧项目'): string
    {
        $summary = $inventory['summary'] ?? [];
        $lines = [
            '# ' . $projectName . '源码逻辑清单',
            '',
            '> 本清单由旧项目 Controller 与 Blade 静态源码提取生成，只记录已检索到的证据；未检索到不代表逻辑不存在，也不代表新项目已完成迁移。',
            '',
            '## 统计',
            '',
            '- Controller 文件：' . (int) ($summary['controller_files'] ?? 0),
            '- Controller 方法：' . (int) ($summary['controller_methods'] ?? 0),
            '- Blade 文件：' . (int) ($summary['blade_files'] ?? 0),
            '- HTML 表单：' . (int) ($summary['forms'] ?? 0),
            '- AJAX 地址：' . (int) ($summary['ajax_endpoints'] ?? 0),
            '- 外链 JavaScript 文件：' . (int) ($summary['javascript_files'] ?? 0),
            '- 外链 JavaScript AJAX 地址：' . (int) ($summary['script_ajax_endpoints'] ?? 0),
            '',
            '## Controller 方法与静态证据',
            '',
        ];

        foreach ($inventory['controllers'] ?? [] as $controller) {
            $lines[] = '### `' . $this->markdownInline((string) ($controller['class'] ?? '未知类')) . '`';
            $lines[] = '';
            $lines[] = '- 源文件：`' . $this->markdownInline((string) ($controller['path'] ?? '')) . '`';
            $lines[] = '';

            foreach ($controller['methods'] ?? [] as $method) {
                $lines[] = '#### `' . $this->markdownInline(basename(str_replace('\\', '/', (string) ($controller['class'] ?? ''))) . '::' . (string) ($method['name'] ?? '未知方法')) . '`';
                $lines[] = '';
                $lines[] = '- 定义行：' . (int) ($method['line'] ?? 0);
                $lines[] = '- 请求字段：' . $this->markdownList($method['request_fields'] ?? []);
                $lines[] = '- 数据表：' . $this->markdownList($method['tables'] ?? []);
                $lines[] = '- 渲染视图：' . $this->markdownList($method['views'] ?? []);
                $lines[] = '- 响应类型：' . $this->markdownList($method['response_types'] ?? []);
                $lines[] = '- 条件分支数量：' . (int) ($method['conditional_branches'] ?? 0);
                $lines[] = '- 返回语句数量：' . (int) ($method['return_statements'] ?? 0);
                $lines[] = '- 静态外部调用：' . $this->markdownList($method['external_calls'] ?? []);
                $lines[] = '';
            }
        }

        $lines[] = '## Blade 页面、表单与脚本交互';
        $lines[] = '';

        foreach ($inventory['blades'] ?? [] as $blade) {
            $lines[] = '### `' . $this->markdownInline((string) ($blade['path'] ?? '')) . '`';
            $lines[] = '';

            foreach ($blade['forms'] ?? [] as $index => $form) {
                $lines[] = '- 表单 ' . ($index + 1) . '：方法 `'
                    . $this->markdownInline((string) ($form['method'] ?? 'GET'))
                    . '`；地址 `'
                    . $this->markdownInline((string) ($form['action'] ?? ''))
                    . '`；字段：'
                    . $this->markdownList($form['fields'] ?? []);
            }

            if (($blade['forms'] ?? []) === []) {
                $lines[] = '- 表单：未检索到 HTML `<form>`。';
            }

            $lines[] = '- AJAX 地址：' . $this->markdownList($blade['ajax_endpoints'] ?? []);
            $lines[] = '- 外链 JavaScript：' . $this->markdownList($blade['script_sources'] ?? []);
            $lines[] = '- 外链 JavaScript AJAX 地址：' . $this->markdownList($blade['script_endpoints'] ?? []);
            $lines[] = '- 命名路由调用：' . $this->markdownList($blade['route_names'] ?? []);
            $lines[] = '- 上传相关静态命中：' . (int) ($blade['uploads'] ?? 0);
            $lines[] = '- 下载/导出相关静态命中：' . (int) ($blade['downloads'] ?? 0);
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * 解析单个 Controller 文件：提取类名与方法级静态证据。
     *
     * @param string $path 文件绝对路径。
     * @param string $root 旧项目根目录。
     * @return array<string, mixed> Controller 盘点明细。
     */
    private function inspectController(string $path, string $root): array
    {
        $source = $this->read($path);
        $parsed = $this->parsePhpMethods($source);

        return [
            'path' => $this->relativePath($path, $root),
            'class' => $parsed['class'],
            'methods' => array_map(function (array $method): array {
                $body = $method['source'];

                return [
                    'name' => $method['name'],
                    'line' => $method['line'],
                    'request_fields' => $this->requestFields($body),
                    'tables' => $this->uniqueMatches($body, '/(?:DB::table|->table)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/'),
                    'views' => $this->uniqueMatches($body, '/\bview\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/'),
                    'response_types' => $this->responseTypes($body),
                    'conditional_branches' => preg_match_all('/\b(?:if|elseif|switch|case)\b/', $body, $matches) ?: 0,
                    'return_statements' => preg_match_all('/\breturn\b/', $body, $matches) ?: 0,
                    'external_calls' => $this->externalCalls($body),
                ];
            }, $parsed['methods']),
        ];
    }

    /**
     * 解析单个 Blade 文件：表单/AJAX/外链脚本（含脚本内 AJAX）/命名路由/上传下载静态命中。
     *
     * @param string $path 文件绝对路径。
     * @param string $root 旧项目根目录。
     * @return array<string, mixed> Blade 盘点明细。
     */
    private function inspectBlade(string $path, string $root): array
    {
        $source = $this->read($path);
        $forms = [];
        $scriptSources = $this->scriptSources($source);
        $scriptEndpoints = [];
        foreach ($scriptSources as $scriptSource) {
            $scriptPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $scriptSource);
            if (is_file($scriptPath)) {
                $scriptEndpoints = array_merge($scriptEndpoints, $this->ajaxEndpoints($this->read($scriptPath)));
            }
        }

        if (preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = $match[1];
                $body = $match[2];
                $forms[] = [
                    'action' => $this->attribute($attributes, 'action') ?? '',
                    'method' => strtoupper($this->attribute($attributes, 'method') ?? 'GET'),
                    'fields' => $this->formFields($body),
                ];
            }
        }

        return [
            'path' => $this->relativePath($path, $root),
            'forms' => $forms,
            'ajax_endpoints' => $this->ajaxEndpoints($source),
            'script_sources' => $scriptSources,
            'script_endpoints' => array_values(array_unique($scriptEndpoints)),
            'route_names' => $this->uniqueMatches($source, '/\broute\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/'),
            'uploads' => preg_match_all('/\b(?:FormData|upload|file\s*:|type\s*=\s*[\'\"]file)/i', $source, $matches) ?: 0,
            'downloads' => preg_match_all('/\b(?:download|export|window\.location)\b/i', $source, $matches) ?: 0,
        ];
    }

    /**
     * 用 token 流解析 PHP 源码中的类与方法（含命名空间前缀与起止行），
     * 仅取首个顶层类，方法体原样保留供后续静态证据提取。
     *
     * @param string $source 源码文本。
     * @return array{class: string, methods: array<int, array<string, mixed>>} 解析结果。
     */
    private function parsePhpMethods(string $source): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $class = '';
        $methods = [];
        $tokenCount = count($tokens);

        for ($index = 0; $index < $tokenCount; $index++) {
            $token = $tokens[$index];
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->tokenNameUntil($tokens, $index + 1, ';');
                continue;
            }

            if ($token[0] === T_CLASS && $class === '') {
                $className = $this->nextTokenText($tokens, $index + 1, T_STRING);
                $class = ltrim($namespace . '\\' . $className, '\\');
                continue;
            }

            if ($token[0] !== T_FUNCTION) {
                continue;
            }

            $nameIndex = $this->nextTokenIndex($tokens, $index + 1, T_STRING);
            if ($nameIndex === null) {
                continue;
            }

            $openBrace = $this->nextCharacterIndex($tokens, $nameIndex + 1, '{');
            if ($openBrace === null) {
                continue;
            }

            $closeBrace = $this->matchingBraceIndex($tokens, $openBrace);
            if ($closeBrace === null) {
                continue;
            }

            $methods[] = [
                'name' => $tokens[$nameIndex][1],
                'line' => $token[2],
                'source' => $this->tokensToString(array_slice($tokens, $index, $closeBrace - $index + 1)),
            ];
            $index = $closeBrace;
        }

        return ['class' => $class, 'methods' => $methods];
    }

    /**
     * 从源码中匹配 AJAX 端点（url/endpoint 键、$.get/post、ajax()、fetch() 四种模式）。
     *
     * @param string $source 源码文本。
     * @return array<int, string> 去重后的端点列表。
     */
    private function ajaxEndpoints(string $source): array
    {
        $patterns = [
            '/\b(?:url|endpoint)\s*:\s*[\'\"]([^\'\"]+)[\'\"]/i',
            '/\$\.(?:get|post)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
            '/\bajax\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
            '/\bfetch\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
        ];
        $endpoints = [];

        foreach ($patterns as $pattern) {
            $endpoints = array_merge($endpoints, $this->uniqueMatches($source, $pattern));
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * 提取外链本地 JS 文件路径：跳过 CDN/绝对 URL 与内联脚本，兼容 asset() 包装。
     *
     * @param string $source Blade 源码。
     * @return array<int, string> 相对 public 根的 JS 路径列表。
     */
    private function scriptSources(string $source): array
    {
        if (! preg_match_all('/<script\b[^>]*\bsrc\s*=\s*([\'\"])(.*?)\1[^>]*>/is', $source, $matches)) {
            return [];
        }

        $sources = [];
        foreach ($matches[2] as $sourceAttribute) {
            $value = trim((string) $sourceAttribute);
            if (preg_match('/^(?:https?:)?\/\//i', $value)) {
                continue;
            }
            if (preg_match('/(?:^|[^A-Za-z0-9_])(?:URL::)?asset\s*\(\s*[\'\"]\/?([^\'\"]+\.js)[\'\"]/i', $value, $asset)) {
                $sources[] = ltrim($asset[1], '/');
                continue;
            }
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && preg_match('/\.js$/i', $path)) {
                $sources[] = ltrim($path, '/');
            }
        }

        return array_values(array_unique($sources));
    }

    /**
     * 提取方法体内可见的响应类型（view/json/redirect/download）。
     *
     * @param string $source 方法源码。
     * @return array<int, string> 命中的响应类型列表。
     */
    private function responseTypes(string $source): array
    {
        $types = [];
        foreach ([
            'view' => '/\bview\s*\(/',
            'json' => '/(?:response\s*\(\s*\)\s*->\s*json|\bjson\s*\()/',
            'redirect' => '/\bredirect\s*\(/',
            'download' => '/->download\s*\(/',
        ] as $type => $pattern) {
            if (preg_match($pattern, $source)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * 提取静态外部调用（ClassName::method 形态，不含相对引用）。
     *
     * @param string $source 方法源码。
     * @return array<int, string> 调用列表。
     */
    private function externalCalls(string $source): array
    {
        return $this->uniqueMatches($source, '/\b([A-Z][A-Za-z0-9_\\\\]+)::[A-Za-z_][A-Za-z0-9_]*/');
    }

    /**
     * 提取请求字段引用（input/get/has 调用与 $request->xxx 属性形态），按源码顺序去重。
     *
     * @param string $source 方法源码。
     * @return array<int, string> 字段名列表。
     */
    private function requestFields(string $source): array
    {
        $matches = [];
        foreach ([
            '/(?:->(?:input|get|has|filled)|\b(?:Input|Request)::(?:get|input|has))\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
            '/\$request\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\b(?!\s*\()/',
        ] as $pattern) {
            if (preg_match_all($pattern, $source, $found, PREG_OFFSET_CAPTURE)) {
                foreach ($found[1] as $value) {
                    $matches[] = ['name' => $value[0], 'offset' => $value[1]];
                }
            }
        }

        usort($matches, static function (array $left, array $right): int {
            return $left['offset'] <=> $right['offset'];
        });

        $fields = [];
        foreach ($matches as $match) {
            if (! in_array($match['name'], $fields, true)) {
                $fields[] = $match['name'];
            }
        }

        return $fields;
    }

    /**
     * 提取表单字段：仅统计带 name 属性的 input/select/textarea。
     *
     * @param string $source 表单 HTML 片段。
     * @return array<int, string> 字段名列表。
     */
    private function formFields(string $source): array
    {
        $fields = [];
        if (! preg_match_all('/<(?:input|select|textarea)\b([^>]*)>/is', $source, $matches)) {
            return $fields;
        }

        foreach ($matches[1] as $attributes) {
            $name = $this->attribute($attributes, 'name');
            if ($name !== null && $name !== '') {
                $fields[] = $name;
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * 从 HTML 属性字符串中取指定属性值（支持引号与无引号两种写法）。
     *
     * @param string $attributes 属性串。
     * @param string $name 属性名。
     * @return string|null 命中返回属性值，否则 null。
     */
    private function attribute(string $attributes, string $name): ?string
    {
        $pattern = '/\b' . preg_quote($name, '/') . '\s*=\s*(?:([\'\"])(.*?)\1|([^\s>]+))/is';
        if (! preg_match($pattern, $attributes, $matches)) {
            return null;
        }

        return isset($matches[2]) && $matches[2] !== '' ? trim($matches[2]) : trim((string) ($matches[3] ?? ''));
    }

    /**
     * 正则去重提取非空字符串命中。
     *
     * @param string $source 待匹配文本。
     * @param string $pattern 正则（须含捕获组 1）。
     * @return array<int, string> 去重后的命中列表。
     */
    private function uniqueMatches(string $source, string $pattern): array
    {
        if (! preg_match_all($pattern, $source, $matches)) {
            return [];
        }

        return array_values(array_unique(array_filter($matches[1], static function ($value): bool {
            return is_string($value) && $value !== '';
        })));
    }

    /**
     * 递归收集目录下指定后缀的文件（排序保证输出稳定）。
     *
     * @param string $directory 目录路径。
     * @param string $suffix 文件名后缀（如 php / blade.php）。
     * @return array<int, string> 文件绝对路径列表。
     */
    private function findFiles(string $directory, string $suffix): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -strlen($suffix)) === $suffix) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * 读取文件内容；读取失败失败关闭。
     *
     * @param string $path 文件路径。
     * @return string 文件内容。
     * @throws RuntimeException 读取失败时抛出。
     */
    private function read(string $path): string
    {
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Unable to read legacy source: ' . $path);
        }

        return $source;
    }

    /**
     * 把绝对路径转为相对项目根路径（统一正斜杠）。
     *
     * @param string $path 绝对路径。
     * @param string $root 项目根目录。
     * @return string 相对路径。
     */
    private function relativePath(string $path, string $root): string
    {
        return str_replace('\\', '/', ltrim(substr($path, strlen($root)), '\\/'));
    }

    /**
     * 从 token 流指定偏移开始拼接文本，直到遇到终止符（如 ';'）为止。
     *
     * 用于解析 namespace 声明等以符号结尾的 token 序列。
     *
     * @param array<int, string|array<int, string|int>> $tokens token_get_all 返回的原始 token 数组。
     * @param int $offset 起始偏移（含）。
     * @param string $terminator 终止字符，遇到即停止拼接且不包含该字符。
     * @return string 拼接并 trim 后的文本。
     */
    private function tokenNameUntil(array $tokens, int $offset, string $terminator): string
    {
        $name = '';
        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === $terminator) {
                break;
            }
            $name .= is_array($token) ? $token[1] : $token;
        }

        return trim($name);
    }

    /**
     * 从指定偏移向后找第一个指定类型的 token 并返回其文本；找不到时返回空串。
     *
     * @param array<int, string|array<int, string|int>> $tokens token_get_all 返回的原始 token 数组。
     * @param int $offset 起始偏移（不含）。
     * @param int $type 目标 token 类型，如 T_STRING。
     * @return string 命中 token 的文本；未命中返回空串。
     */
    private function nextTokenText(array $tokens, int $offset, int $type): string
    {
        $index = $this->nextTokenIndex($tokens, $offset, $type);

        return $index === null ? '' : $tokens[$index][1];
    }

    /**
     * 从指定偏移向后找第一个指定类型 token 的索引。
     *
     * 遇到 '{' 或 ';' 提前终止并返回 null，避免跨越方法边界误取其他代码段的 token。
     *
     * @param array<int, string|array<int, string|int>> $tokens token_get_all 返回的原始 token 数组。
     * @param int $offset 起始偏移（不含）。
     * @param int $type 目标 token 类型，如 T_STRING。
     * @return int|null 命中索引；未命中返回 null。
     */
    private function nextTokenIndex(array $tokens, int $offset, int $type): ?int
    {
        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            if (is_array($tokens[$index]) && $tokens[$index][0] === $type) {
                return $index;
            }
            if ($tokens[$index] === '{' || $tokens[$index] === ';') {
                return null;
            }
        }

        return null;
    }

    /**
     * 从指定偏移向后找第一个指定字符（如 '{'）的索引。
     *
     * @param array<int, string|array<int, string|int>> $tokens token_get_all 返回的原始 token 数组。
     * @param int $offset 起始偏移（不含）。
     * @param string $character 目标字符。
     * @return int|null 命中索引；未命中返回 null。
     */
    private function nextCharacterIndex(array $tokens, int $offset, string $character): ?int
    {
        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index] === $character) {
                return $index;
            }
        }

        return null;
    }

    /**
     * 从起始花括号位置做深度匹配，返回对应闭合花括号的索引。
     *
     * 用于定位方法体边界；括号不配对时返回 null。
     *
     * @param array<int, string|array<int, string|int>> $tokens token_get_all 返回的原始 token 数组。
     * @param int $openBrace 起始 '{' 的索引。
     * @return int|null 匹配的 '}' 索引；未匹配返回 null。
     */
    private function matchingBraceIndex(array $tokens, int $openBrace): ?int
    {
        $depth = 0;
        for ($index = $openBrace, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index] === '{') {
                $depth++;
            }
            if ($tokens[$index] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * 把 token 数组还原为源码字符串（token 内容与符号字符按原顺序拼接）。
     *
     * @param array<int, string|array<int, string|int>> $tokens token_get_all 返回的原始 token 数组。
     * @return string 还原后的源码文本。
     */
    private function tokensToString(array $tokens): string
    {
        return implode('', array_map(static function ($token): string {
            return is_array($token) ? $token[1] : $token;
        }, $tokens));
    }

    /**
     * 把字符串列表渲染为 Markdown 行内代码列表（中文顿号分隔）。
     *
     * 空列表返回“未检索到”，保证盘点清单中无命中项也能如实呈现。
     *
     * @param array<int, mixed> $values 待渲染的值列表。
     * @return string 渲染后的 Markdown 文本。
     */
    private function markdownList(array $values): string
    {
        if ($values === []) {
            return '未检索到';
        }

        return implode('、', array_map(function ($value): string {
            return '`' . $this->markdownInline((string) $value) . '`';
        }, $values));
    }

    /**
     * 转义 Markdown 行内代码中的反引号，防止盘点内容破坏清单格式。
     *
     * @param string $value 原始文本。
     * @return string 反引号已转义的文本。
     */
    private function markdownInline(string $value): string
    {
        return str_replace('`', '\\`', $value);
    }
}
