<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:09
 */

namespace App\Traits;

use App\Constants\ResponseCode;
use Illuminate\Http\JsonResponse;

/**
 * 标准 JSON 响应 Trait。
 *
 * 文件功能：
 * - 所有前后台 API 统一返回 code、message、data 三个字段，方便 Layui、Blade 页面脚本和移动端按同一结构处理响应。
 * - message 支持直接传入 Laravel 多语言 key，例如 response.success、admin.role_not_found。
 * - 调用方未传入 message 时，会根据 ResponseCode::messageKey() 自动取 response.* 语言包文案。
 * - data 统一转为对象，避免空数组在前端被误判为列表数据。
 * - 错误码映射：code 统一来自 App\Constants\ResponseCode（1xxx 成功 / 2xxx 业务 / 3xxx 数据操作 /
 *   4xxx 认证授权 / 5xxx 系统错误）；新增错误码必须同步在 ResponseCode::messageKey() 登记，
 *   否则将落到 response.unknown，前端无法按码识别。
 *
 * 失败语义：
 * - 业务失败统一走 error() 返回错误码与文案，不抛异常给前端；
 * - 未预期异常仍应由全局异常处理兜底，本 Trait 不吞异常。
 */
trait ApiResponse
{
    /**
     * 返回成功响应。
     *
     * 参数逻辑说明：
     * - $data 表示接口业务数据，允许传入数组或对象；空数据会返回空对象。
     * - $message 表示多语言消息 key 或已翻译文本；为空时根据 $code 自动读取 response.* 语言包。
     * - $code 表示业务响应码，默认 ResponseCode::SUCCESS。
     *
     * @param array|object $data 接口业务数据。
     * @param string $message 多语言消息 key 或已翻译文本。
     * @param int $code 业务响应码。
     * @return JsonResponse 标准 JSON 响应。
     */
    public function success($data = [], string $message = '', int $code = ResponseCode::SUCCESS): JsonResponse
    {
        $msg = $message ?: __(ResponseCode::messageKey($code));

        return response()->json([
            'code' => $code,
            'message' => __($msg),
            'data' => (object) $data,
        ]);
    }

    /**
     * 返回错误响应。
     *
     * 参数逻辑说明：
     * - $message 表示多语言消息 key 或已翻译文本；为空时根据 $code 自动读取 response.* 语言包。
     * - $code 表示业务错误码，默认 ResponseCode::ERROR。
     * - $data 表示附加错误数据，例如字段校验详情或调试上下文；默认返回空对象。
     *
     * @param string $message 多语言消息 key 或已翻译文本。
     * @param int $code 业务错误码。
     * @param array|object $data 附加错误数据。
     * @return JsonResponse 标准 JSON 响应。
     */
    public function error(string $message = '', int $code = ResponseCode::ERROR, $data = []): JsonResponse
    {
        $msg = $message ?: __(ResponseCode::messageKey($code));

        return response()->json([
            'code' => $code,
            'message' => __($msg),
            'data' => (object) $data,
        ]);
    }
}
