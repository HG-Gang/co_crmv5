<?php
/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 17:00
 */

/**
 * audit-members
 *
 * 文件功能：
 * - 按 docs/中文注释标准-v0.0.3.md §5 的机器可校验口径，审计类属性与类常量的中文注释覆盖率。
 * - 口径：成员声明前必须紧邻一个 PHPDoc 注释块（中间只允许空白、可见性/静态修饰符与属性注解），
 *   且注释块内至少包含一个中日韩统一表意文字；只写类型（如 `@var string`）不算达标。
 * - 输出：审计文件数、成员总数、缺失明细与退出码（缺失即 1），供 `composer run audit-members` 与 CI 使用。
 *
 * 使用方式：
 * - php tools/audit-members.php            审计默认范围并输出明细
 * - php tools/audit-members.php --quiet    只输出统计与退出码
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$targets = [
    $root . '/app',
    $root . '/config',
    $root . '/database/migrations',
    $root . '/database/seeders',
    $root . '/routes',
    $root . '/tests',
];

/** @var array<int, string> $files 审计范围内的全部 PHP 文件（相对根目录的稳定排序）。 */
$files = [];
foreach ($targets as $target) {
    if (!is_dir($target)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir() || $fileInfo->getExtension() !== 'php') {
            continue;
        }
        $files[] = str_replace('\\', '/', $fileInfo->getPathname());
    }
}
sort($files);

$memberPattern = '/^(?:(?:public|protected|private)\s+)(?:(static)\s+)?(const\s+[A-Za-z_]\w*|\$\w+)/';
$filesAudited = 0;
$membersTotal = 0;
$missing = [];

foreach ($files as $file) {
    $code = file_get_contents($file);
    if ($code === false || strpos($code, 'class ') === false) {
        continue;
    }
    $filesAudited++;
    $lines = preg_split('/\r?\n/', $code);
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        $line = trim($lines[$i]);
        // 跳过注释行本身与命名空间/类声明行，避免把类间注释误判为成员注释。
        if ($line === '' || $line[0] === '*' || $line[0] === '/' || $line[0] === '#') {
            continue;
        }
        if (!preg_match($memberPattern, $line)) {
            continue;
        }
        $membersTotal++;
        if (hasChineseDocBlock($lines, $i)) {
            continue;
        }
        $missing[] = str_replace('\\', '/', substr($file, strlen($root) + 1)) . ':' . ($i + 1) . ' ' . $line;
    }
}

/**
 * 判断成员声明行上方是否紧邻含中文的 PHPDoc 注释块。
 *
 * 逻辑说明：
 * - 从声明行的上一行向上回溯，允许空行、可见性之外的注解行（@var/@Deprecated 等 PHPDoc 行）、
 *   注释块的中间行（以 * 开头）与块的结束行（*\/），直到遇到块开始行（/\*\*）或其他代码；
 * - 只要回溯路径中存在 `/**` 开始块且该块内含中日韩表意文字，即视为达标；
 * - 遇到其他代码行、`//` 行注释或文件边界即停止，视为未达标。
 *
 * @param array<int, string> $lines 文件按行拆分后的数组。
 * @param int $declLine 成员声明所在行下标。
 * @return bool true 表示紧邻注释块含中文，达标。
 */
function hasChineseDocBlock(array $lines, int $declLine): bool
{
    $sawOpen = false;
    for ($j = $declLine - 1; $j >= 0; $j--) {
        $text = trim($lines[$j]);
        if ($text === '') {
            continue;
        }
        if (strpos($text, '/**') === 0) {
            $sawOpen = true;
            break;
        }
        if (strpos($text, '*/') !== false || strpos($text, '*') === 0 || strpos($text, '@') === 0) {
            continue;
        }
        // 其他代码（含 // 行注释）直接终止回溯。
        break;
    }
    if (!$sawOpen) {
        return false;
    }
    // 回溯整个注释块，检查是否含中日韩统一表意文字。
    for (; $j < $declLine; $j++) {
        if (preg_match('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u', $lines[$j])) {
            return true;
        }
    }

    return false;
}

$relative = static function (string $path) use ($root): string {
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
};

echo 'files audited: ' . $filesAudited . PHP_EOL;
echo 'members total: ' . $membersTotal . PHP_EOL;
echo 'missing CJK doc: ' . count($missing) . PHP_EOL;

$quiet = in_array('--quiet', $argv, true);
if (!$quiet) {
    foreach ($missing as $entry) {
        echo '  MISSING ', $entry, PHP_EOL;
    }
}

exit(count($missing) === 0 ? 0 : 1);
