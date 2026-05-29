<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    /**
     * General file upload endpoint
     * 处理文件上传 | Handle file upload
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $types = ['avatar', 'id_card', 'bank_card', 'voucher', 'general'];
        
        $request->validate([
            'file' => 'required|file|max:5120', // Max 5MB
            'type' => ['nullable', 'string', Rule::in($types)],
        ]);

        $type = $request->get('type', 'general');
        $allowedMimes = $this->getAllowedMimes($type);

        $request->validate([
            'file' => 'mimes:' . implode(',', $allowedMimes),
        ]);

        $file = $request->file('file');
        // Store to storage/app/public/{type}/{date}/
        $path = $file->store($type . '/' . date('Ymd'), 'public');

        return response()->json([
            'code' => 0,
            'msg'  => __('messages.upload_success'),
            'data' => [
                'url'  => asset(Storage::url($path)),
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * Get allowed mime types based on upload type
     * 根据上传类型获取允许的 MIME 类型
     *
     * @param string $type
     * @return array
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
