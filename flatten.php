<?php
/**
 * Usage:
 *   php flatten.php <源目录> <目标目录>
 *
 * 递归读取源目录下所有文件，平铺复制到目标目录。
 * 文件名冲突时自动重命名（追加父目录名 / 序号）。
 */

if (PHP_SAPI !== 'cli') {
    exit("请在命令行中运行: php flatten.php <源目录> <目标目录>\n");
}

if ($argc < 3) {
    exit("用法: php flatten.php <源目录> <目标目录>\n");
}

$srcDir = rtrim($argv[1], '/\\');
$dstDir = rtrim($argv[2], '/\\');

if (!is_dir($srcDir)) {
    exit("源目录不存在: $srcDir\n");
}

if (!is_dir($dstDir) && !mkdir($dstDir, 0777, true) && !is_dir($dstDir)) {
    exit("无法创建目标目录: $dstDir\n");
}

/**
 * 处理重名冲突，返回可用的目标文件名
 */
function uniqueName(string $name, string $dstDir): string
{
    $base  = pathinfo($name, PATHINFO_FILENAME);
    $ext   = pathinfo($name, PATHINFO_EXTENSION);
    $final = $name;
    $i     = 1;
    while (file_exists($dstDir . DIRECTORY_SEPARATOR . $final)) {
        $final = $base . '_' . $i . ($ext !== '' ? '.' . $ext : '');
        $i++;
    }
    return $final;
}

$count     = 0;
$skipped   = 0;
$conflicts = 0;

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $realPath = $file->getRealPath();
    if ($realPath === false) {
        $skipped++;
        continue;
    }

    $name     = $file->getFilename();
    $relPath  = ltrim(substr($realPath, strlen($srcDir)), '/\\');
    $parent   = dirname($relPath);
    $target   = $name;

    // 重名时优先用父目录名区分，再不行加序号
    if (file_exists($dstDir . DIRECTORY_SEPARATOR . $target)) {
        if ($parent !== '.' && $parent !== '' && $parent !== DIRECTORY_SEPARATOR) {
            $base  = pathinfo($name, PATHINFO_FILENAME);
            $ext   = pathinfo($name, PATHINFO_EXTENSION);
            $flatParent = str_replace(['\\', '/'], '_', $parent);
            $cand  = $flatParent . '_' . $name;
            if (!file_exists($dstDir . DIRECTORY_SEPARATOR . $cand)) {
                $target = $cand;
            } else {
                $target = uniqueName($cand, $dstDir);
            }
        } else {
            $target = uniqueName($name, $dstDir);
        }
        $conflicts++;
    }

    $dest = $dstDir . DIRECTORY_SEPARATOR . $target;

    if (copy($realPath, $dest)) {
        $count++;
        echo "已复制: $relPath  =>  $target\n";
    } else {
        $skipped++;
        echo "复制失败: $relPath\n";
    }
}

echo "\n完成: 共复制 $count 个文件";
if ($conflicts > 0) echo "，其中 $conflicts 个重名文件已自动重命名";
if ($skipped > 0) echo "，$skipped 个文件失败";
echo "\n";