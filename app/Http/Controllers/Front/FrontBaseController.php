<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:09
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Http\Controllers\Controller;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 前台基础控制器。
 *
 * 文件功能：
 * - 所有前台控制器继承此类，统一复用 ApiResponse 的 success 和 error 响应结构。
 * - ApiResponse 会把 response.*、auth.* 等消息 key 交给 Laravel 多语言包翻译，保证后端接口支持多语言响应。
 * - 本类额外提供旧前台兼容方法，用于同时识别新 JWT user guard 和旧 session 中的 suser 登录态。
 *
 * 旧前台身份契约：
 * - legacyFrontUserId / legacyFrontUserLogin / legacyFrontUserInfo 是全部前台控制器统一的取身份入口：
 *   优先解析 jwt.auth:user 写入的 user guard 登录记录，其次回退到旧 session 的 suser 数据。
 * - session 中的 suser 由旧模板写入且可能长期有效，因此只信任其中的 user_id；
 *   业务资料一律按 user_id 重新查询 user_logins / user_infos，不使用 session 内嵌的过期快照。
 * - 三个方法返回的业务用户 ID 语义一致（user_logins.user_id 与 user_infos.user_id 相等）。
 */
class FrontBaseController extends Controller
{
    use ApiResponse;

    /**
     * 解析当前前台业务用户 ID。
     *
     * 业务逻辑说明：
     * - legacyFrontUserId 用于兼容新 JWT 登录态和旧前台 session 登录态。
     * - request 表示当前 HTTP 请求对象，可能来自新 API 请求，也可能来自旧 web 路由。
     * - userLogin 表示 jwt.auth:user 解析出的 user guard 登录记录，优先级高于旧 session。
     * - sessionUser 表示旧前台写入 session 的 suser 数据，可能是数组，也可能是对象。
     * - user_id 表示业务用户 ID，对应 user_logins.user_id 和 user_infos.user_id。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return int 当前业务用户 ID；无法识别登录用户时返回 0。
     */
    protected function legacyFrontUserId(Request $request): int
    {
        $userLogin = $request->user('user');
        if ($userLogin && $userLogin->user_id) {
            return (int) $userLogin->user_id;
        }

        // session suser 是旧前台 Cookie 会话的兜底身份，只取 user_id 参与后续查询，信任边界以 user_infos 表为准。
        $sessionUser = $request->session()->get('suser', []);
        if (is_array($sessionUser) && !empty($sessionUser['user_id'])) {
            return (int) $sessionUser['user_id'];
        }

        if (is_object($sessionUser) && !empty($sessionUser->user_id)) {
            return (int) $sessionUser->user_id;
        }

        return 0;
    }

    /**
     * 解析当前前台登录记录。
     *
     * 业务逻辑说明：
     * - legacyFrontUserLogin 用于返回当前前台登录记录。
     * - 优先返回 user guard 已解析出的 UserLogin，避免重复查询数据库。
     * - 当旧前台只有 session suser 时，先通过 legacyFrontUserId 取得 user_id，再查询 user_logins 表。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return UserLogin|null 当前前台登录记录；无法识别用户时返回 null。
     */
    protected function legacyFrontUserLogin(Request $request): ?UserLogin
    {
        $userLogin = $request->user('user');
        if ($userLogin) {
            return $userLogin;
        }

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return null;
        }

        return UserLogin::where('user_id', $userId)->first();
    }

    /**
     * 解析当前前台业务用户资料。
     *
     * 业务逻辑说明：
     * - legacyFrontUserInfo 用于返回当前前台业务用户资料。
     * - userInfo 表示 UserLogin 关联的业务用户资料，对应 user_infos 表。
     * - 如果 UserLogin 已存在但关联未预加载，则按 user_id 再查询 user_infos 表。
     * - 如果只能从旧 session 取得 user_id，也按 user_infos.user_id 查询业务资料。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return UserInfo|null 当前业务用户资料；无法识别用户或资料不存在时返回 null。
     */
    protected function legacyFrontUserInfo(Request $request): ?UserInfo
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if ($userLogin) {
            return $userLogin->userInfo ?: UserInfo::where('user_id', $userLogin->user_id)->first();
        }

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return null;
        }

        // 从 session 取得的 user_id 必须回查 user_infos 表，不直接使用 session 内可能过期的资料快照。
        return UserInfo::where('user_id', $userId)->first();
    }

    /**
     * 返回旧前台兼容认证错误。
     *
     * 业务逻辑说明：
     * - legacyFrontAuthError 用于返回旧前台兼容认证错误。
     * - USER_NOT_FOUND 表示已识别用户 ID 但缺少业务资料，通常是 user_infos 数据缺失。
     * - AUTH_FAILED 表示无法识别任何前台登录用户，通常是 token/session 均不存在或已失效。
     * - 错误消息传入多语言 key，最终由 ApiResponse 和 Laravel 语言包翻译。
     *
     * @param Request $request 当前 HTTP 请求对象，用于判断是否还能解析出业务用户 ID。
     * @return JsonResponse 统一错误响应。
     */
    protected function legacyFrontAuthError(Request $request): JsonResponse
    {
        return $this->legacyFrontUserId($request) > 0
            ? $this->error('auth.user_info_not_found', ResponseCode::USER_NOT_FOUND)
            : $this->error('response.auth_failed', ResponseCode::AUTH_FAILED);
    }
}
