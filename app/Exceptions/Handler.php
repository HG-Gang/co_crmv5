<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:53
 */

/**
 * 全局异常处理器。
 *
 * 文件功能：
 * - 统一注册不报告、不闪回的异常类型清单（dontReport / dontFlash）。
 * - 重写 render() 为所有异常响应注入 request_id / trace_id，保证接口返回契约对异常响应同样成立。
 * - 重写 unauthenticated() 按守卫类型（admin / user）跳转到对应登录页或返回 JSON 401。
 *
 * 适用场景：
 * - 应用内任何未捕获异常的统一出口；路由级 404/405 等异常也经过本类渲染。
 *
 * 方法功能：
 * - register()：注册异常处理回调，当前无自定义 reportable 逻辑。
 * - render(Request $request, Throwable $e)：调用父类渲染后补充链路标识，并写入 X-Request-Id / X-Trace-Id 响应头。
 * - unauthenticated(Request $request, AuthenticationException $exception)：期望 JSON 时返回 401 JSON；
 *   admin 守卫跳转后台登录页，其余守卫跳转前台登录页。
 *
 * 返回值：
 * - render() 返回注入链路标识后的 Symfony Response。
 * - unauthenticated() 返回 JSON 401 响应或重定向响应。
 *
 * 异常或失败场景：
 * - 中间件未执行到的路由级异常（404/405）缺少链路标识时，本类兜底生成新的 request_id / trace_id。
 */
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * 不报告（不上报日志）的异常类型列表。
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * 验证异常时不闪回（flash）到 Session 的输入字段名，避免密码等敏感值出现在页面上。
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * 注册全局异常处理回调。
     *
     * @return void 当前未注册自定义 reportable 逻辑，保留框架默认行为（按 dontReport 列表决定是否上报）。
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * 渲染异常响应并注入请求链路标识。
     *
     * 方法说明：
     * - 异常响应（如 401/403/404/405/422/500）由本方法统一渲染；
     *   在此补上 request_id / trace_id（由 RequestTraceMiddleware 在请求入口生成并存于 request attributes），
     *   保证“每一个接口的返回都带上 request_id 和 trace_id”的契约对异常响应同样成立。
     * - 失败语义：本方法不吞异常、不改变父类渲染出的异常类型与状态码，只增强响应；父类渲染失败时异常继续上抛。
     *
     * @param \Illuminate\Http\Request $request 当前请求对象。
     * @param Throwable $e 抛出的异常。
     * @return \Symfony\Component\HttpFoundation\Response 注入链路标识后的响应。
     */
    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);

        // 中间件在路由匹配之后才执行，因此 404/405 等路由级异常拿不到中间件生成的标识；
        // 此处兜底生成，保证“每一个接口的返回都带上 request_id 和 trace_id”的契约对全部异常响应成立。
        $requestId = (string) $request->attributes->get('crm_request_id', '');
        $traceId = (string) $request->attributes->get('crm_trace_id', '');

        if ($requestId === '' || $traceId === '') {
            $requestId = \App\Support\RequestTrace::ulid();
            $traceId = \App\Support\RequestTrace::uuid();
            $request->attributes->set('crm_request_id', $requestId);
            $request->attributes->set('crm_trace_id', $traceId);
        }

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                $data['request_id'] = $requestId;
                $data['trace_id'] = $traceId;
                $response->setData($data);
            }
        }

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }

    /**
     * 处理未认证异常：按请求类型与守卫返回 JSON 401 或跳转登录页。
     *
     * 失败语义：
     * - 期望 JSON 的请求返回 401 JSON（文案固定 Unauthenticated.）。
     * - 页面请求按守卫跳转：admin 守卫跳后台登录页，user 及其它守卫跳前台登录页。
     *
     * @param \Illuminate\Http\Request $request 当前请求对象。
     * @param AuthenticationException $exception 未认证异常，携带触发的守卫列表。
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $guard = data_get($exception->guards(), 0);

        switch ($guard) {
            case 'admin':
                return redirect()->guest(route('admin_page_login'));
            case 'user':
            default:
                return redirect()->guest(route('front_page_login'));
        }
    }
}
