<?php
/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 16:40
 */

/**
 * add_file_headers
 *
 * 文件功能：
 * - 按 docs/中文注释标准-v0.0.3.md §4 的强制要求，为缺失 PhpStorm 头部标识块的源文件幂等插入标识块。
 * - 支持三类文件：PHP（块位于 `<?php` 之后、文件级功能说明之前）、Blade（`{{-- --}}` 置于文件首）、
 *   JavaScript（`//` 置于文件首，保持 'use strict' 等语义不变）。
 * - 工具保持 LF 行尾与无 BOM 的 UTF-8 编码；已有标识块的文件跳过，保证幂等。
 *
 * 使用方式：
 * - php tools/add_file_headers.php            为缺失文件插入（幂等，已有的跳过）
 * - php tools/add_file_headers.php --check    只报告缺失，不修改（CI 使用）
 *
 * 返回值：
 * - `--check` 模式：无缺失 exit 0，有缺失 exit 1 并列出文件清单。
 * - 插入模式：全部处理成功 exit 0，任一文件写入失败 exit 1。
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$targets = [
    $root . '/app',
    $root . '/config',
    $root . '/database/migrations',
    $root . '/database/seeders',
    $root . '/routes',
    $root . '/scripts',
    $root . '/tests',
    $root . '/tools',
    $root . '/resources',
    $root . '/public/js',
];
$checkOnly = in_array('--check', $argv, true);

/** @var array<int, string> $files 审计范围内的全部可注释源文件（PHP/Blade/JS）。 */
$files = [];
foreach ($targets as $target) {
    if (!is_dir($target)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            continue;
        }
        $extension = strtolower($fileInfo->getExtension());
        if (!in_array($extension, ['php', 'js', 'blade.php'], true) && !($extension === 'php' && substr($fileInfo->getFilename(), -10) === '.blade.php')) {
            continue;
        }
        $path = str_replace('\\', '/', $fileInfo->getPathname());
        // blade.php 以 php 扩展名被上面捕获；此处统一按扩展名分类。
        if (!in_array($extension, ['php', 'js'], true)) {
            continue;
        }
        $files[] = $path;
    }
}
$files = array_values(array_unique($files));
sort($files);

$missing = [];
$fixed = 0;
$failed = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        $failed[] = $file . ' (unreadable)';
        continue;
    }
    if (strpos($content, 'Created by PhpStorm') !== false) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
    if ($checkOnly) {
        $missing[] = $relative;
        continue;
    }
    $header = buildHeader($file, $relative);
    $newContent = prependHeader($file, $content, $header);
    if ($newContent === null) {
        $failed[] = $relative . ' (unsupported shape)';
        continue;
    }
    if (file_put_contents($file, $newContent) === false) {
        $failed[] = $relative . ' (write failed)';
        continue;
    }
    $fixed++;
}

/**
 * 按标准 §4 组装标识块文本；日期时间取文件修改时间作为创建时间的最佳近似。
 *
 * @param string $file 文件绝对路径。
 * @param string $relative 相对根目录路径（仅用于失败信息）。
 * @return string 形如标准示例的标识块（不含尾随空行）。
 */
function buildHeader(string $file, string $relative): string
{
    $mtime = @filemtime($file) ?: time();
    $date = date('Y/m/d', $mtime);
    $time = date('H:i', $mtime);
    $lines = [
        'Created by PhpStorm.',
        'Project name co_crmv5.',
        'User: Huang Gang',
        'Date: ' . $date,
        'Time: ' . $time,
    ];

    return implode("\n", $lines);
}

/**
 * 依据文件类型把标识块插入到语义安全的位置。
 *
 * 逻辑说明：
 * - PHP：块位于 `<?php` 行之后（保持 declare 等语句仍为首个语句），前后各空一行；
 *   无 `<?php` 的 PHP 文件视为不支持的形状，交由调用方记录失败。
 * - Blade：`{{-- ... --}}` 置于文件最前（Blade 注释不输出到渲染结果）。
 * - JS：`// ...` 置于文件最前；若首行是 shebang 或 'use strict' 之前的其他语句也不受影响
 *   （注释行不改变语义）。
 *
 * @param string $file 文件绝对路径。
 * @param string $content 原始内容。
 * @param string $header 标识块文本（多行）。
 * @return string|null 插入后的完整内容；不支持的形状返回 null。
 */
function prependHeader(string $file, string $content, string $header): ?string
{
    $isBlade = substr($file, -10) === '.blade.php';
    if ($isBlade) {
        $block = "{{--\n" . $header . "\n--}}\n";

        return $block . $content;
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($extension === 'js') {
        $lines = explode("\n", $header);
        $block = "// " . implode("\n// ", $lines) . "\n";

        return $block . $content;
    }

    if (preg_match('/^<\?php\r?\n/', $content) === 1) {
        $block = "\n/**\n " . str_replace("\n", "\n * ", $header) . "\n */\n";

        return preg_replace('/^(<\?php\r?\n)/', '$1' . $block, $content, 1);
    }

    // UTF-8 BOM 依标准必须剥离（LF 行尾、无 BOM 的 UTF-8）；剥离后按普通 PHP 文件插入。
    if (strncmp($content, "\xEF\xBB\xBF<?php", 8) === 0) {
        $stripped = substr($content, 3);
        $withHeader = prependHeader($file, $stripped, $header);

        return $withHeader === null ? null : $withHeader;
    }

    return null;
}

if ($checkOnly) {
    echo 'missing headers: ' . count($missing) . PHP_EOL;
    foreach ($missing as $entry) {
        echo '  MISSING ', $entry, PHP_EOL;
    }
    exit(count($missing) === 0 ? 0 : 1);
}

echo 'files scanned: ' . count($files) . PHP_EOL;
echo 'headers inserted: ' . $fixed . PHP_EOL;
echo 'failed: ' . count($failed) . PHP_EOL;
foreach ($failed as $entry) {
    echo '  FAILED ', $entry, PHP_EOL;
}

exit(count($failed) === 0 ? 0 : 1);
