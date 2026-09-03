<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * 公共文件上传控制器。
 *
 * 文件功能：
 * - 本控制器提供前后台可复用的通用上传入口，适用于头像、身份证、银行卡、凭证和普通附件。
 * - 上传目录由 type 参数决定，文件最终保存到 public disk 的 `{type}/{日期}` 目录。
 * - MIME 白名单按业务类型收敛，证件和凭证类只允许图片，通用附件额外允许 PDF、Word 和 Excel。
 */
class UploadController extends Controller
{
    /**
     * 处理公共文件上传。
     *
     * 参数逻辑说明：
     * - file 表示上传文件字段，必传，最大 5120KB。
     * - type 表示上传业务类型；为空时默认 general。
     * - avatar 表示头像上传，只允许图片扩展名。
     * - id_card 表示身份证上传，只允许图片扩展名。
     * - bank_card 表示银行卡上传，只允许图片扩展名。
     * - voucher 表示凭证上传，只允许图片扩展名。
     * - general 表示通用附件上传，允许图片、PDF、Word 和 Excel。
     * - $allowedMimes 表示当前业务类型允许的扩展名白名单，用于第二次文件类型校验。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 file 上传文件和 type 上传业务类型。
     * @return \Illuminate\Http\JsonResponse 上传成功返回文件访问地址、存储路径、原始文件名和文件大小。
     */
    public function upload(Request $request)
    {
        $types = ['avatar', 'id_card', 'bank_card', 'voucher', 'general'];

        $request->validate([
            // file：公共上传文件字段，统一限制为 5MB，避免前后台大文件误走通用上传入口。
            'file' => 'required|file|max:5120',
            'type' => ['nullable', 'string', Rule::in($types)],
        ]);

        $type = $request->get('type', 'general');
        $allowedMimes = $this->getAllowedMimes($type);

        $request->validate([
            'file' => 'mimes:' . implode(',', $allowedMimes),
        ]);

        $file = $request->file('file');
        // 上传文件保存到 public disk 的 {type}/{日期} 目录，便于按业务类型和日期做清理或审计。
        $path = $file->store($type . '/' . date('Ymd'), 'public');

        return response()->json([
            'code' => 0,
            'msg' => __('response.uploaded'),
            'data' => [
                'url' => asset(Storage::url($path)),
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * 根据上传业务类型返回允许的 MIME 扩展名白名单。
     *
     * 参数逻辑说明：
     * - avatar、id_card、bank_card、voucher 属于证件与凭证类，只允许图片扩展名 jpeg/png/jpg/gif。
     * - general 属于通用附件，除图片外还允许 pdf、doc、docx、xls、xlsx。
     * - 白名单返回值直接用于 Laravel mimes 校验规则，不包含 MIME type 本身，只包含扩展名。
     *
     * @param string $type 上传业务类型，来源于 upload() 方法校验后的 type 参数。
     * @return array<int, string> 该类型允许的文件扩展名列表。
     */
    protected function getAllowedMimes(string $type): array
    {
        switch ($type) {
            case 'avatar':
            case 'id_card':
            case 'bank_card':
            case 'voucher':
                return ['jpeg', 'png', 'jpg', 'gif'];
            case 'general':
            default:
                return ['jpeg', 'png', 'jpg', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        }
    }
}
