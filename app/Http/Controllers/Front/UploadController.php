<?php

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Generic Upload Controller
 * 通用上传控制器
 * 
 * Handles generic file uploads for avatars, ID cards, and bank cards.
 * 处理头像、身份证和银行卡的通用文件上传。
 */
class UploadController extends FrontBaseController
{
    /**
     * Generic upload method
     * 通用上传方法
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|max:5120',
            'type' => 'required|in:avatar,id_card,bank_card',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $type = $request->type;
        $file = $request->file('file');
        
        // Define storage path: storage/app/public/{type}/
        // 定义存储路径: storage/app/public/{type}/
        $path = $file->store($type, 'public');

        if ($path) {
            return $this->success([
                'path' => $path,
                'url'  => Storage::disk('public')->url($path),
            ], 'response.uploaded');
        }

        return $this->error('response.file_upload_failed', ResponseCode::INTERNAL_ERROR);
    }
}
