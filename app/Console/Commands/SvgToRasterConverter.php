<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/14
 * Time: 10:24
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Intervention\Image\ImageManager;

/**
 * 文件功能：SVG 转换为 PNG / JPEG 的控制台命令（支持自定义分辨率 1k/2k + --height，使用 Imagick）
 *
 * Laravel 8 + PHP 8.0 兼容，使用 Intervention Image ^2.7 版本
 *
 * 命令签名：
 *   php artisan svg:convert {svg?} {output?} {format=png} [--width=] [--height=]
 *
 * 参数说明：
 *   {svg?}        - 输入的 SVG 文件路径（可选，默认 docs/svg/example_to_png.svg）
 *   {output?}     - 输出图片路径（可选，默认 storage/app/svg/example_to_png.png）
 *   {format=png}  - 输出格式（png 或 jpeg，默认 png）
 *   {--width=}    - 目标宽度（可选），支持 1k=1000、2k=2000 或自定义像素数（如 1920）
 *   {--height=}   - 目标高度（可选），支持 1k=1000、2k=2000 或自定义像素数
 *                   若仅指定 width，则等比缩放；仅指定 height，则等比缩放；同时指定则精确尺寸（可能轻微变形）
 *
 * 执行后同时返回：图片路径 + base64 编码数据（data URI）
 *
 * 默认输出目录：storage/app/svg/（Laravel 推荐目录）
 * 默认文件名：example_to_png.png（或 .jpg 如果格式为 jpeg）
 *
 * 使用示例：
 *   php artisan svg:convert
 *   php artisan svg:convert --width=1k
 *   php artisan svg:convert --width=1000
 *   php artisan svg:convert --width=2000 --height=2000
 *   php artisan svg:convert docs/svg/example_to_png.svg storage/app/svg/example_svg_to_png.png
 *   php artisan svg:convert docs/svg/example_to_png.svg storage/app/svg/example_svg_to_png.jpg jpeg
 *
 * @author 基于 Intervention Image 的 SVG 转换工具
 */
class SvgToRasterConverter extends Command
{
    /**
     * 命令签名（定义参数和选项）
     *
     * @var string
     */
    protected $signature = 'svg:convert {svg?} {output?} {format=png} {--width=} {--height=}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '将 SVG 转换为 PNG 或 JPEG，默认转换 docs/svg/example_to_png.svg，并返回图片路径 + base64 数据；支持 --width 和 --height 设置自定义分辨率（1k/2k），使用 Imagick 驱动';

    /**
     * 执行命令
     *
     * @return int
     */
    public function handle()
    {
		//$tmpSvg = 'https://s3-us-tozo-test-wr.tozostore.com/Admin/app30/img/summary_1784880500950_1784881828WbBqa.svg';
	    //$outputPath1 = storage_path('app/svg');
		//$svgname = $outputPath1 . '/1111111111111.png';
       // $svgPath    = $this->argument('svg') ?: base_path('docs/svg/test_svg_to_png.svg');
	    //$svgStr = file_get_contents($tmpSvg);
		//file_put_contents($svgname, $svgStr);
		//dd(2222);
       //$svgPath    =$svgname;
	    $svgPath    = $this->argument('svg') ?: base_path('docs/svg/long_text_222222.svg');
        $outputPath = $this->argument('output');
        $format     = strtolower($this->argument('format'));
        $targetWidth = $this->option('width');
        $targetHeight = $this->option('height');

        if (!file_exists($svgPath)) {
            $this->error("❌ SVG 文件不存在: {$svgPath}");
            return 1;
        }

        $this->info("输入 SVG: {$svgPath}");

        try {
            if (!class_exists(\Intervention\Image\ImageManager::class)) {
                $this->error("❌ 请先运行: composer require intervention/image ^2.7");
                return 1;
            }

            $manager = new ImageManager([
                'driver' => 'imagick'
            ]);

            $image = $manager->make($svgPath);

            // 处理自定义分辨率：支持 --width=1k/--width=2000, --height=2k 等
            // 1k=1000px, 2k=2000px
            $widthMap = ['1k' => 1000, '2k' => 2000];
            $heightMap = $widthMap; // 高度也支持相同预设

            $width = !empty($targetWidth)
                ? (isset($widthMap[strtolower($targetWidth)])
                    ? $widthMap[strtolower($targetWidth)]
                    : (int) $targetWidth)
                : null;

            $height = !empty($targetHeight)
                ? (isset($heightMap[strtolower($targetHeight)])
                    ? $heightMap[strtolower($targetHeight)]
                    : (int) $targetHeight)
                : null;

            if ($width !== null || $height !== null) {
                if ($width !== null && $height !== null) {
                    // 精确尺寸（可能轻微拉伸）
                    $image->resize($width, $height);
                    $this->info("✅ 已设置为精确分辨率 {$width}x{$height}px");
                } elseif ($width !== null) {
                    // 仅宽度，等比缩放
                    $image->resize($width, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $this->info("✅ 已按宽度 {$width}px 等比缩放（原图 {$image->width()}x{$image->height()}）");
                } elseif ($height !== null) {
                    // 仅高度，等比缩放
                    $image->resize(null, $height, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $this->info("✅ 已按高度 {$height}px 等比缩放（原图 {$image->width()}x{$image->height()}）");
                }

                // 更新尺寸信息
                $this->info("缩放后尺寸: {$image->width()}x{$image->height()}px");
            } else {
                $this->info("未指定 --width 或 --height，使用原始尺寸");
            }

            // 处理默认输出路径
            $defaultDir = storage_path('app/svg');
            if (empty($outputPath)) {
                if (!file_exists($defaultDir)) {
                    mkdir($defaultDir, 0755, true);
                }
                $ext = (in_array($format, ['jpeg', 'jpg'])) ? 'jpg' : 'png';
                $outputPath = $defaultDir . DIRECTORY_SEPARATOR . 'long_text_222222_2k.' . $ext;
                $this->info("未指定输出路径，默认保存到: {$outputPath}");
            }

            // 确保输出目录存在
            $outputDir = dirname($outputPath);
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // 转换并保存图片
            if (in_array($format, ['jpeg', 'jpg'])) {
                $image->save($outputPath, 90);
                $this->info("✅ 已成功转换为 JPEG: {$outputPath}");
            } else {
                $image->save($outputPath);
                $this->info("✅ 已成功转换为 PNG: {$outputPath}");
            }

            $imagePath = realpath($outputPath);

            if (!file_exists($imagePath)) {
                $this->error("❌ 图片文件不存在");
                return 1;
            }

            // 始终返回图片路径 + base64 编码数据
            $base64 = base64_encode(file_get_contents($imagePath));
            $mime   = mime_content_type($imagePath);

            $result = [
                'image'  => $imagePath,
                'base64' => "data:{$mime};base64,{$base64}"
            ];

            $this->info("✅ 转换完成！");
            $this->info("返回结果: " . json_encode($result, JSON_UNESCAPED_UNICODE));

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ 转换失败: " . $e->getMessage());
            return 1;
        }
    }
}
