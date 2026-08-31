<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/18
 * Time: 16:51
 */

namespace App\Http\Middleware;

use App\Constants\ResponseCode;
use App\Models\BigAgent;
use App\Models\UserLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 旧版前台身份认证中间件。
 *
 * 文件功能：
 * - 恢复旧前台页面与 AJAX 路由的 suser 会话登录边界，同时兼容 Laravel user guard。
 * - 支持大代理（big agent）会话：识别旧版、CrmUI 与 Naive 路由族并跳转对应登录页。
 * - 会话中的 ID 只作为标识，每次请求必须重新查库确认账号仍启用、未注销且业务资料存在后才放行。
 *
 * 安全边界：
 * - 无效会话默认失败关闭，不进入业务控制器；仅改密入口保留旧 1010 错误协议而放行到控制器处理。
 * - AJAX/JSON 失败响应区分“有身份但账号无效（user_not_found）”与“完全无身份（auth_failed）”。
 * - 后台禁用、注销或删除账号后，旧 Cookie 在同一请求边界失效并主动清理会话。
 */
class LegacyFrontAuthenticate
{
    /**
     * 处理旧版前台请求认证：识别请求归属后校验会话有效性，失败按请求类型返回旧 AJAX 协议 JSON 或重定向登录页。
     *
     * @param Request $request 当前请求对象。
     * @param Closure $next 认证通过后的后续处理闭包。
     * @return mixed 认证通过返回后续响应；未认证时返回 JSON 或重定向对应登录页（失败关闭）。
     */
    public function handle(Request $request, Closure $next)
    {
        // 先识别请求归属：大代理入口与普通前台入口使用不同的会话键、登录路由与账号校验方式。
        $isCrmUiBigAgentRequest = $request->is('front-crmui/big-agent/*');
        $isNaiveBigAgentRequest = $request->is('front-naive/big-agent/*');
        $isBigAgentRequest = $request->is('user/agents/*')
            || $isCrmUiBigAgentRequest
            || $isNaiveBigAgentRequest;
        $loginRoute = $isNaiveBigAgentRequest
            ? 'front_naive_big_agent_login'
            : ($isCrmUiBigAgentRequest
                ? 'front_crmui_big_agent_login'
                : ($isBigAgentRequest ? 'agentsLogin' : 'login'));
        $isLegacyPasswordAction = $isBigAgentRequest && $this->isLegacyPasswordAction($request);
        $bigAgentSessionId = $isBigAgentRequest ? $this->sessionBigAgentId($request) : 0;
        $legacyUserSessionId = $isBigAgentRequest ? 0 : $this->sessionUserId($request);

        // 按归属选择会话校验：会话中的 ID 只作为标识，必须重新查库确认账号仍有效，否则失败关闭。
        $authenticated = $isBigAgentRequest
            ? $this->activeBigAgentSession($request, $isLegacyPasswordAction)
            : $this->activeUserSession($request);

        // 密码保存入口必须把无效 session 交给控制器，以保留旧页面的 1010 错误协议；
        // 其它大代理接口仍然在这里 fail-closed，避免无效 session 读取业务数据。
        if ($authenticated || ($isLegacyPasswordAction && $bigAgentSessionId > 0)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            // 保留旧 AJAX 区分：携带的 suser ID 已无有效数据库身份时返回 user_not_found；
            // 完全没有任何可用身份时返回通用认证失败 auth_failed。
            $code = $legacyUserSessionId > 0
                ? ResponseCode::USER_NOT_FOUND
                : ResponseCode::AUTH_FAILED;
            $message = __($code === ResponseCode::USER_NOT_FOUND
                ? 'response.user_not_found'
                : 'response.auth_failed');

            return response()->json([
                'code' => $code,
                'message' => $message,
                'msg' => $message,
                'rows' => [],
                'total' => 0,
                'footer' => [],
                'redirect' => true,
                'redirectUrl' => route($loginRoute),
            ]);
        }

        return redirect()->route($loginRoute);
    }

    /**
     * 从旧 suser 会话中读取用户 ID。
     *
     * @param Request $request 当前请求对象。
     * @return int 会话缺失或格式异常时返回 0（表示无可用身份）。
     */
    private function sessionUserId(Request $request): int
    {
        if (!$request->hasSession()) {
            return 0;
        }

        $user = $request->session()->get('suser');
        if (is_array($user)) {
            return (int) ($user['user_id'] ?? 0);
        }
        if (is_object($user)) {
            return (int) ($user->user_id ?? 0);
        }

        return 0;
    }

    /**
     * 从 bigAgents 会话中读取代理 ID。
     *
     * @param Request $request 当前请求对象。
     * @return int 会话缺失或格式异常时返回 0（表示无可用身份）。
     */
    private function sessionBigAgentId(Request $request): int
    {
        if (!$request->hasSession()) {
            return 0;
        }

        $agent = $request->session()->get('bigAgents');
        if (is_array($agent)) {
            return (int) ($agent['id'] ?? 0);
        }
        if (is_object($agent)) {
            return (int) ($agent->id ?? 0);
        }

        return 0;
    }

    /**
     * 旧 suser 中的 user_id 只是会话标识，不能替代当前数据库状态。
     * 每次进入旧业务路由都确认登录账号仍启用、未注销且业务资料仍存在，
     * 这样后台禁用、注销或删除账号后，旧 Cookie 会在同一请求边界失效。
     */
    private function activeUserSession(Request $request): bool
    {
        // 优先采用 Laravel user guard 的登录态，避免新旧两套会话并存时状态不一致。
        $guardUser = $request->user('user');
        if ($guardUser instanceof UserLogin) {
            if ($guardUser->isActive()) {
                return true;
            }

            // 账号已失效时同时清理两套会话，让旧 Cookie 在同一请求边界失效。
            Auth::guard('user')->logout();
            if ($request->hasSession()) {
                $request->session()->forget('suser');
            }

            return false;
        }

        $userId = $this->sessionUserId($request);
        if ($userId <= 0) {
            return false;
        }

        // 回退校验旧 suser 会话：查库确认账号仍启用、未注销且业务资料存在。
        $active = UserLogin::query()
            ->where('user_id', $userId)
            ->where('is_enabled', 1)
            ->where('is_cancelled', 0)
            ->whereHas('userInfo')
            ->exists();

        // 无效会话从 Session 中清除，避免残留标识反复触发业务查询。
        if (!$active && $request->hasSession()) {
            $request->session()->forget('suser');
        }

        return $active;
    }

    /**
     * 会话中的 ID 只是客户端可篡改的标识，必须重新读取数据库确认账号仍存在、未软删且已启用。
     * 这样后台禁用或删除账号后，旧页面和旧 AJAX 会在同一个请求边界立即失效。
     */
    private function activeBigAgentSession(Request $request, bool $preserveInvalidSession = false): bool
    {
        $agentId = $this->sessionBigAgentId($request);
        if ($agentId <= 0) {
            return false;
        }

        // 会话 ID 可被客户端篡改，必须按 ID 重新查库确认代理仍存在且已启用。
        $active = BigAgent::query()
            ->whereKey($agentId)
            ->where('is_enabled', 1)
            ->exists();

        // 仅在非改密入口清理无效会话；改密入口保留会话以维持旧页面的 1010 错误协议。
        if (!$active && !$preserveInvalidSession && $request->hasSession()) {
            $request->session()->forget('bigAgents');
        }

        return $active;
    }

    /**
     * 判断当前路径是否为旧版大代理改密入口。
     *
     * @param Request $request 当前请求对象。
     * @return bool true=改密入口，该路径下无效会话交给控制器处理以保留旧错误协议。
     */
    private function isLegacyPasswordAction(Request $request): bool
    {
        return in_array($request->path(), [
            'user/agents/changePassword',
            'user/agents/editpsw_save',
        ], true);
    }
}
