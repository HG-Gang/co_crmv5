<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * 前台上传控制器。
 *
 * 文件功能：
 * - 处理头像、身份证、银行卡、凭证和旧前台兼容上传入口。
 * - 新前台 `/api/front/uploads/*` 与旧前台 `user/upload/file`、`user/multiple/file` 复用本控制器中的文件校验和保存逻辑。
 * - 旧前台上传响应必须保留 `code`、`msg`、`data`、`name`、`path`、`url` 字段，避免历史 Layui 表单上传回调失效。
 *
 * 安全边界：
 * - 文件类型白名单：新接口仅接受 image 且 type 限 avatar/id_card/bank_card（最大 5MB）；旧接口仅接受 jpeg/png/jpg/gif（最大 10MB），由 Laravel image/mimes 校验执行。
 * - 保存目录固定：新接口目录由 type 白名单决定，旧接口固定为 uploads/Bank 与 uploads/IdCard；目录不取自用户输入，避免目录穿透。
 * - 文件名由服务端生成（时间戳 + 随机字节 + 用户 ID + 白名单扩展名），不信任客户端原始文件名，防止覆盖与路径穿越。
 */
class UploadController extends FrontBaseController
{
    /**
     * upload 用于处理新前台通用图片上传。
     *
     * 参数和返回字段含义：
     * - $request：当前 HTTP 请求对象，承载 file 和 type 字段。
     * - file 表示上传文件字段，必须是图片且最大 5MB。
     * - type 表示上传业务类型，只允许 avatar、id_card、bank_card。
     * - avatar 表示头像上传目录。
     * - id_card 表示身份证上传目录。
     * - bank_card 表示银行卡上传目录。
     * - path 表示 public 磁盘中的相对路径。
     * - url 表示浏览器可访问的文件地址。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return \Illuminate\Http\JsonResponse 新前台统一上传响应。
     */
    public function upload(Request $request)
    {
        // 校验阶段：type 白名单 + 真实图片类型 + 5MB 上限，任一不满足即拒绝且不保存任何文件。
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|max:5120',
            'type' => 'required|in:avatar,id_card,bank_card',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $type = $request->type;
        $file = $request->file('file');
        
        // path：保存到 storage/app/public/{type}/ 下的相对路径，前端通过 url 访问。
        $path = $file->store($type, 'public');

        if ($path) {
            return $this->success([
                'path' => $path,
                'url'  => Storage::disk('public')->url($path),
            ], 'response.uploaded');
        }

        return $this->error('response.file_upload_failed', ResponseCode::INTERNAL_ERROR);
    }

    /**
     * singleFileUpload 用于兼容旧前台单文件上传入口。
     *
     * 参数和返回字段含义：
     * - $request：当前 HTTP 请求对象，旧前台通过 file 字段提交单张图片。
     * - file 可能是单个 UploadedFile，也可能是旧表单提交的文件数组；数组时取第一张图片。
     * - code=200 表示旧前台上传成功，code=500 表示旧前台上传失败。
     * - msg 表示旧前台上传结果文案，成功固定为 SUC，失败返回 FAIL 或校验错误。
     * - data 表示旧前台上传结果对象，成功时包含 name、path、url。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 旧前台单文件上传响应。
     */
    public function singleFileUpload(Request $request): JsonResponse
    {
        $file = $request->file('file');
        if (is_array($file)) {
            $file = reset($file);
        }

        if (!$file || !$file->isValid()) {
            return response()->json([
                'code' => 500,
                'msg' => 'FAIL',
                'data' => (object) [],
            ]);
        }

        $validator = Validator::make(['file' => $file], [
            'file' => 'image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'code' => 500,
                'msg' => $validator->errors()->first(),
                'data' => (object) [],
            ]);
        }

        $stored = $this->storeLegacyUpload($file, 'uploads/Bank');

        return response()->json([
            'code' => 200,
            'msg' => 'SUC',
            'data' => $stored,
        ]);
    }

    /**
     * multipleFileUpload 用于兼容旧前台多文件上传入口。
     *
     * 参数和返回字段含义：
     * - $request：当前 HTTP 请求对象，旧前台通过 file 字段提交一组图片。
     * - files 表示旧前台 file 字段上传的文件集合；单文件会被转换成数组统一处理。
     * - data 表示成功保存的文件列表，每一项包含 name、path、url。
     * - 无效文件会被跳过，避免某张图片失败导致整批旧前台上传中断。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return JsonResponse 旧前台多文件上传响应。
     */
    public function multipleFileUpload(Request $request): JsonResponse
    {
        $files = $request->file('file', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        $data = [];
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $validator = Validator::make(['file' => $file], [
                'file' => 'image|mimes:jpeg,png,jpg,gif|max:10240',
            ]);
            if ($validator->fails()) {
                continue;
            }

            $data[] = $this->storeLegacyUpload($file, 'uploads/IdCard');
        }

        return response()->json([
            'code' => 200,
            'msg' => 'SUC',
            'data' => $data,
        ]);
    }

    /**
     * storeLegacyUpload 用于保存旧前台上传文件并生成旧响应字段。
     *
     * 参数和变量含义：
     * - $file：已经通过基础校验的 UploadedFile 文件对象。
     * - directory 表示旧前台文件保存目录，例如 uploads/Bank 或 uploads/IdCard。
     * - userId 表示当前前台登录用户 ID；未登录旧入口保持 0，兼容历史上传文件命名规则。
     * - name 表示最终保存的文件名，由时间、随机串、用户 ID 和扩展名组成。
     * - legacyPath 表示写入 public 磁盘的相对路径。
     *
     * @param mixed $file 上传文件对象。
     * @param string $directory 旧前台文件保存目录。
     * @return array<string, string> 旧前台需要的 name、path、url 字段。
     */
    private function storeLegacyUpload($file, string $directory): array
    {
        $userId = $this->legacyFrontUserId(request());
        // 扩展名只取白名单校验后的原始值，仍强制小写并兜底 jpg，避免大写或异常扩展名造成存储差异。
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        // 文件名由服务端生成：时间戳 + 随机字节 + 用户 ID，不信任客户端文件名，防止目录穿透与文件覆盖。
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '_' . $userId . '.' . $extension;
        $legacyPath = trim($directory, '/') . '/' . $name;

        Storage::disk('public')->putFileAs(trim($directory, '/'), $file, $name);

        return [
            'name' => $name,
            'path' => $legacyPath,
            'url' => '/storage/' . $legacyPath,
        ];
    }
}
