<?php

namespace App\Traits;

use App\Constants\ResponseCode;
use Illuminate\Http\JsonResponse;

/**
 * 标准 JSON 响应 Trait | Standard JSON Response Trait
 * 
 * 所有接口统一返回格式 | All APIs return unified format:
 * {
 *     "code": 1000,      // 状态码（参见 ResponseCode 常量）| Status code (see ResponseCode constants)
 *     "message": "...",   // 多语言提示信息 | i18n message
 *     "data": {}          // 统一为对象 | Unified as object
 * }
 */
trait ApiResponse
{
    /**
     * 成功响应 | Success response
     *
     * @param array|object $data    响应数据 | Response data
     * @param string       $message 消息（支持多语言键） | Message (supports i18n key)
     * @param int          $code    状态码 | Status code
     * @return JsonResponse
     */
    public function success($data = [], string $message = '', int $code = ResponseCode::SUCCESS): JsonResponse
    {
        // 如果没传 message，则根据 code 自动取多语言
        // If no message provided, auto-fetch i18n message by code
        $msg = $message ?: __(ResponseCode::messageKey($code));

        return response()->json([
            'code'    => $code,
            'message' => __($msg),
            'data'    => (object) $data,
        ]);
    }

    /**
     * 错误响应 | Error response
     *
     * @param string       $message 消息（支持多语言键） | Message (supports i18n key)
     * @param int          $code    状态码 | Status code
     * @param array|object $data    附加数据 | Additional data
     * @return JsonResponse
     */
    public function error(string $message = '', int $code = ResponseCode::ERROR, $data = []): JsonResponse
    {
        $msg = $message ?: __(ResponseCode::messageKey($code));

        return response()->json([
            'code'    => $code,
            'message' => __($msg),
            'data'    => (object) $data,
        ]);
    }
}
