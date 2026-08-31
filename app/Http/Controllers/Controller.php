<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/14
 * Time: 11:52
 */

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

/**
 * 项目控制器基类。
 *
 * 文件功能：
 * - 作为全项目所有控制器的统一基类，组合 Laravel 框架提供的基础控制器能力。
 * - AuthorizesRequests 提供授权校验能力（authorize、authorizeResource 等）。
 * - DispatchesJobs 提供任务分发能力（dispatch、dispatchNow 等）。
 * - ValidatesRequests 提供请求表单校验能力（validate、validateWithBag 等）。
 *
 * 适用场景：
 * - 前台（Front）、后台（Admin）、公共（Common）等所有业务控制器统一继承本类时使用。
 * - 子控制器需要使用方法参数自动注入、表单校验、授权校验或队列任务分发时使用。
 *
 * 入参例子：
 * - 本类不直接接收请求参数，具体入参由各子控制器方法定义。
 * - 示例：子控制器 `public function index(Request $request)` 中的 $request 由 Laravel 容器自动注入。
 *
 * 返回值：
 * - 本类不直接返回响应，具体返回值由各子控制器方法决定。
 *
 * 异常或失败场景：
 * - 子控制器使用 validate 校验失败时抛出 ValidationException，由 Laravel 统一转换为 422 响应。
 * - 子控制器使用 authorize 授权失败时抛出 AuthorizationException，由 Laravel 统一转换为 403 响应。
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * 将 SVG 转换为图片（PNG / JPEG），供外部调用。
     *
     * 入参（query）：
     * - svg    源 SVG 文件路径（本地绝对路径或相对项目根目录）。
     * - format 输出图片后缀，默认 png（支持 png / jpg / jpeg）。
     *
     * 返回值：
     * - 成功：['image' => 图片绝对路径, 'base64' => data URI 字符串]
     * - 失败：404（文件不存在）或 500（转换异常）JSON 响应
     *
     * 使用示例（路由）：
     *   GET /svg-convert?svg=docs/svg/example_to_png.svg&format=png
     */
    public function svgToImage(Request $request)
    {
        $svgPath = $request->query('svg');
        $format  = strtolower((string) $request->query('format', 'png'));

        if (empty($svgPath) || !file_exists($svgPath)) {
            return response()->json(['error' => "SVG 文件不存在: {$svgPath}"], 404);
        }

        try {
            if (!in_array($format, ['png', 'jpg', 'jpeg'], true)) {
                $format = 'png';
            }

            $manager = new ImageManager(['driver' => 'imagick']);
            $image = $manager->make($svgPath);

            $dir = storage_path('app/public/svg');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $ext = in_array($format, ['jpg', 'jpeg'], true) ? 'jpg' : 'png';
            $outputPath = $dir . DIRECTORY_SEPARATOR
                . 'svg_' . md5($svgPath . microtime()) . '.' . $ext;

            $quality = in_array($format, ['jpg', 'jpeg'], true) ? 90 : null;
            $image->save($outputPath, $quality);

            $mime = mime_content_type($outputPath);
            $base64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($outputPath));

            return [
                'image'  => realpath($outputPath),
                'base64' => $base64,
            ];

        } catch (\Exception $e) {
            return response()->json(['error' => 'SVG 转换失败: ' . $e->getMessage()], 500);
        }
    }
	
	/**
	 * SVG 转换为 PNG / JPEG 的控制器方法（支持传入路径、格式、分辨率，并输出 Base64）
	 *
	 * @param string $svgPath SVG 文件路径（必传）
	 * @param string|null $outputPath 输出图片路径（可选，默认 storage/app/svg/xxx.png）
	 * @param string $format 输出格式（png 或 jpeg，默认 png）
	 * @param int $resolution 分辨率（默认 2048px，1k=1000、2k=2048）
	 * @return array
	 *
	 * @author 移植自 SvgToRasterConverter，Laravel 8 + Intervention/Image ^2.7 兼容
	 * @see https://github.com/intervention/image
	 */
	public function svgToImageV2(
		string $svgPath,
		string $outputPath = null,
		string $format = 'png',
		int $resolution = 2048
	): array {
		if (!file_exists($svgPath)) {
			throw new \Exception("❌ SVG 文件不存在: {$svgPath}");
		}
		
		try {
			if (!class_exists(\Intervention\Image\ImageManager::class)) {
				throw new \Exception('❌ 请先运行: composer require intervention/image ^2.7');
			}
			
			// 优先使用 Imagick 驱动（最稳定支持 SVG）
			$manager = new ImageManager(new ImagickDriver());
			
			$image = $manager->make($svgPath);
			
			// 处理分辨率参数（默认 2k = 2048px）
			if ($resolution !== 2048) {
				$image->resize($resolution, $resolution, function ($constraint) {
					$constraint->aspectRatio();
				});
			}
			
			// 处理默认输出路径
			if (empty($outputPath)) {
				$defaultDir = storage_path('app/svg');
				if (!file_exists($defaultDir)) {
					mkdir($defaultDir, 0755, true);
				}
				$ext = (in_array(strtolower($format), ['jpeg', 'jpg'])) ? 'jpg' : 'png';
				$outputPath = $defaultDir . DIRECTORY_SEPARATOR . 'example_to_png.' . $ext;
			}
			
			// 确保输出目录存在
			$outputDir = dirname($outputPath);
			if (!file_exists($outputDir)) {
				mkdir($outputDir, 0755, true);
			}
			
			// 保存图片
			if (in_array(strtolower($format), ['jpeg', 'jpg'])) {
				$image->save($outputPath, 90);
			} else {
				$image->save($outputPath);
			}
			
			// 返回图片路径 + Base64
			$imagePath = realpath($outputPath);
			if (!file_exists($imagePath)) {
				throw new \Exception('❌ 图片文件不存在');
			}
			
			$base64 = base64_encode(file_get_contents($imagePath));
			$mime   = mime_content_type($imagePath);
			
			return [
				'image'  => $imagePath,
				'base64' => "data:{$mime};base64,{$base64}"
			];
			
		} catch (\Exception $e) {
			throw new \Exception("❌ 转换失败: " . $e->getMessage());
		}
	}
}
