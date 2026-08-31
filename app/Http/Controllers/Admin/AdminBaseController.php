<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */
/**
 * 后台基类控制器。
 *
 * 功能逻辑说明：
 * - 所有后台控制器继承此类，统一复用 ApiResponse 的 success() 与 error() 响应格式。
 * - 后台 API 返回给前端的 message 必须来自语言包或明确业务文案，避免散落硬编码。
 * - 未预期异常统一通过 serverErrorResponse() 返回多语言服务端错误文案，不直接暴露异常原始消息。
 *
 * 文件功能：
 * - 后台控制器的公共基类；自身不承载路由动作，只提供统一的 JSON 响应与失败关闭能力。
 *
 * 子类契约：
 * - 业务可预期错误由子类在控制器内返回具体的 `admin.*`、`response.*` 或模块语言包 key；
 *   只有未预期异常才调用 serverErrorResponse()。
 * - serverErrorResponse() 不接收异常对象作为响应内容，保证 SQL、文件路径、第三方细节或内部类名不会泄露给前端。
 *
 * 适用场景：
 * - 后台 API 控制器继承本类，获得 ApiResponse 统一响应格式与多语言失败语义。
 */
namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;

class AdminBaseController extends Controller
{
    use ApiResponse;

    /**
     * 后台服务端异常响应。
     *
     * 参数与功能说明：
     * - 该方法用于 catch 分支中处理未预期异常，统一返回 `response.server_error` 当前语言环境文案。
     * - 不接收异常对象作为返回内容，避免把 SQL、文件路径、第三方接口细节或内部类名泄露给前端。
     * - 业务可预期错误仍应在控制器内返回具体的 `admin.*`、`response.*` 或模块语言包 key。
     *
     * @return JsonResponse 后台统一 JSON 错误响应，code 固定为 ResponseCode::SERVER_ERROR。
     */
    protected function serverErrorResponse(): JsonResponse
    {
        return $this->error(__('response.server_error'), ResponseCode::SERVER_ERROR);
    }
}
