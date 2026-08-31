<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/14
 * Time: 11:58
 */

namespace App\Support;

use Intervention\Image\ImageManager;

/**
 * 文件功能：SVG 转 PNG / JPEG 独立工具类（供任意地方调用）。
 *
 * 兼容 Laravel 8 + Intervention/Image ^2.7（imagick 驱动）。
 *
 * 使用示例：
 *   $result = SvgConverter::convert('docs/svg/example_to_png.svg');
 *   $result = SvgConverter::convert('docs/svg/example_to_png.svg', null, 'jpeg', 2048);
 *   $result = SvgConverter::convert('docs/svg/example_to_png.svg', storage_path('app/svg/out.png'));
 *
 * 返回：
 *   ['image' => 图片绝对路径, 'base64' => data URI 字符串]
 */
class SvgConverter
{
    /**
     * 将 SVG 转换为图片并返回路径 + Base64。
     *
     * @param string      $svgPath   源 SVG 文件路径（必传）
     * @param string|null $outputPath 输出图片路径（可选，默认 storage/app/svg/example_to_png.ext）
     * @param string      $format    输出格式（png / jpg / jpeg，默认 png）
     * @param int|null    $resolution 目标宽度（px），等比缩放；默认 null 不缩放（1k=1000、2k=2048 由调用方换算）
     * @return array{image:string, base64:string}
     * @throws \Exception SVG 不存在、转换失败或图片生成失败
     */
    public static function convert(string $svgPath, ?string $outputPath = null, string $format = 'png', ?int $resolution = null): array
    {
        if (!file_exists($svgPath)) {
            throw new \Exception("SVG 文件不存在: {$svgPath}");
        }

        try {
            $format = strtolower($format);
            if (!in_array($format, ['png', 'jpg', 'jpeg'], true)) {
                $format = 'png';
            }

            if (!class_exists(ImageManager::class)) {
                throw new \Exception('请先运行: composer require intervention/image ^2.7');
            }

            $manager = new ImageManager(['driver' => 'imagick']);
            $image = $manager->make($svgPath);

            // 按目标宽度等比缩放（不传或为 0 时保持原尺寸）
            if (!empty($resolution) && $resolution > 0 && $resolution !== $image->width()) {
                $image->resize($resolution, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }

            // 默认输出路径
            if (empty($outputPath)) {
                $defaultDir = storage_path('app/svg');
                if (!is_dir($defaultDir)) {
                    mkdir($defaultDir, 0755, true);
                }
                $ext = in_array($format, ['jpg', 'jpeg'], true) ? 'jpg' : 'png';
                $outputPath = $defaultDir . DIRECTORY_SEPARATOR . 'example_to_png.' . $ext;
            }

            // 确保输出目录存在
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // 保存图片
            $image->save($outputPath, in_array($format, ['jpg', 'jpeg'], true) ? 90 : null);

            $imagePath = realpath($outputPath);
            if (!file_exists($imagePath)) {
                throw new \Exception('图片文件不存在');
            }

            $mime = mime_content_type($imagePath);
            $base64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($imagePath));

            return [
                'image'  => $imagePath,
                'base64' => $base64,
            ];

        } catch (\Exception $e) {
            throw new \Exception('转换失败: ' . $e->getMessage());
        }
    }
}
