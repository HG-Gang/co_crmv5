<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "====================================\n";
echo "图片压缩工具\n";
echo "====================================\n\n";

// 查找超过 100KB 的图片
$publicPath = public_path();
$largeImages = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($publicPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $ext = strtolower($file->getExtension());
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $size = $file->getSize();
            if ($size > 100 * 1024) { // 超过 100KB
                $largeImages[] = [
                    'path' => $file->getPathname(),
                    'size' => $size,
                    'relative' => str_replace($publicPath . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                ];
            }
        }
    }
}

// 按大小排序
usort($largeImages, function ($a, $b) {
    return $b['size'] - $a['size'];
});

echo "找到 " . count($largeImages) . " 个超过 100KB 的图片\n\n";

if (count($largeImages) === 0) {
    echo "✓ 没有需要压缩的图片\n";
    exit(0);
}

echo "前 10 个最大的图片:\n";
foreach (array_slice($largeImages, 0, 10) as $idx => $img) {
    $sizeKB = round($img['size'] / 1024, 2);
    echo sprintf("%2d. %s (%.2f KB)\n", $idx + 1, $img['relative'], $sizeKB);
}

echo "\n是否压缩这些图片? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) !== 'y') {
    echo "取消压缩\n";
    exit(0);
}

echo "\n开始压缩...\n\n";

$compressed = 0;
$totalSaved = 0;

foreach ($largeImages as $img) {
    $originalSize = $img['size'];
    $path = $img['path'];
    $backupPath = $path . '.backup';

    // 备份原图
    if (!file_exists($backupPath)) {
        copy($path, $backupPath);
    }

    try {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (extension_loaded('imagick')) {
            // 使用 Imagick 压缩
            $imagick = new Imagick($path);

            // 获取原始尺寸
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            // 如果尺寸超过 1920px，等比缩放
            if ($width > 1920 || $height > 1920) {
                $imagick->resizeImage(1920, 1920, Imagick::FILTER_LANCZOS, 1, true);
            }

            // 设置压缩质量
            $imagick->setImageCompressionQuality(85);

            // 去除元数据
            $imagick->stripImage();

            // 保存
            $imagick->writeImage($path);
            $imagick->clear();
            $imagick->destroy();
        } else {
            // 使用 GD 压缩
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $image = imagecreatefromjpeg($path);
            } elseif ($ext === 'png') {
                $image = imagecreatefrompng($path);
            } else {
                continue;
            }

            if (!$image) {
                continue;
            }

            // 获取原始尺寸
            $width = imagesx($image);
            $height = imagesy($image);

            // 如果尺寸超过 1920px，等比缩放
            if ($width > 1920 || $height > 1920) {
                $ratio = min(1920 / $width, 1920 / $height);
                $newWidth = (int)($width * $ratio);
                $newHeight = (int)($height * $ratio);

                $newImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $newImage;
            }

            // 保存
            if ($ext === 'jpg' || $ext === 'jpeg') {
                imagejpeg($image, $path, 85);
            } elseif ($ext === 'png') {
                imagepng($image, $path, 8);
            }

            imagedestroy($image);
        }

        $newSize = filesize($path);
        $saved = $originalSize - $newSize;
        $totalSaved += $saved;

        if ($saved > 0) {
            $compressed++;
            $percent = round(($saved / $originalSize) * 100, 1);
            echo sprintf(
                "✓ %s: %.2f KB -> %.2f KB (减少 %.1f%%)\n",
                $img['relative'],
                $originalSize / 1024,
                $newSize / 1024,
                $percent
            );
        }
    } catch (Exception $e) {
        echo "✗ {$img['relative']}: 压缩失败 - {$e->getMessage()}\n";
    }
}

echo "\n====================================\n";
echo "压缩完成！\n";
echo "====================================\n";
echo "压缩文件数: {$compressed}\n";
echo "节省空间: " . round($totalSaved / 1024 / 1024, 2) . " MB\n";
echo "\n原图备份在同目录下的 .backup 文件中\n";
