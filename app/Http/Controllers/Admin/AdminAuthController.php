<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */
namespace App\Http\Controllers\Admin;

use App\Models\AdminLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 后台 Blade 登录控制器。
 *
 * 功能逻辑说明：
 * - 该控制器只服务传统 Laravel Blade 后台登录页，不负责前后端分离 JWT 登录接口。
 * - showLogin 展示后台登录页，并阻止已登录管理员重复进入登录页。
 * - doLogin 处理后台登录表单，校验 email、password、remember 后写入后台会话。
 * - logout 退出后台 Blade 会话，并清理 Session 与重新生成 CSRF Token，避免旧表单令牌继续使用。
 *
 * 文件功能：
 * - 输入：Blade 登录表单的 email、password、remember；输出：登录页视图、控制台跳转或登录页回退。
 * - 登录成功写入 admin guard 会话，并记录 AdminLoginLog 审计日志与最后登录信息。
 *
 * 适用场景：
 * - 后台网页登录/退出；与前后端分离 JWT 登录入口（AuthController）并存，互不影响。
 *
 * 安全边界：
 * - attempt 固定附加 status=1，禁用管理员无法登录；失败统一返回 auth.failed，不泄漏账号是否存在或禁用原因。
 * - 密码不写入日志、Session 与响应；登录成功 regenerate 会话并记录审计日志。
 */
class AdminAuthController extends AdminBaseController
{
    /**
     * showLogin 展示后台登录页。
     *
     * 功能逻辑说明：
     * - 已通过 admin guard 登录的管理员直接跳转控制台，避免重复登录。
     * - 未登录管理员返回 `admin_layui::auth.login` 现代 Layui Blade 模板。
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View 登录页视图或控制台跳转响应。
     */
    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin_page_dashboard');
        }

        return view('admin_layui::auth.login');
    }

    /**
     * doLogin 处理后台登录表单。
     *
     * 参数与字段含义：
     * - $request 表示当前登录表单请求，承载 email、password、remember 与当前 Session。
     * - email 表示管理员登录邮箱，对应 `admins.email`，同时作为登录失败时回填的账号字段。
     * - password 表示管理员登录密码，仅用于 Auth 校验，不写入日志和响应内容。
     * - remember 表示是否延长后台登录会话，勾选后由 Laravel guard 写入记住登录状态。
     *
     * 功能逻辑说明：
     * - 表单验证消息统一读取当前语言环境的 `validation.*` 与 `auth.*` 文案，避免固定中文 locale。
     * - 登录成功后重新生成 Session ID，降低会话固定攻击风险。
     * - AdminLoginLog 记录后台登录审计日志，保存管理员 ID 和登录 IP，便于安全追踪。
     * - 登录成功后同步更新管理员最后登录 IP 和登录次数。
     *
     * @param Request $request 当前后台登录表单请求对象。
     * @return \Illuminate\Http\RedirectResponse 登录成功跳转控制台，失败返回登录页并携带错误。
     */
    public function doLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required'    => __('validation.required', ['attribute' => __('auth.email')]),
            'password.required' => __('validation.required', ['attribute' => __('auth.password_label')]),
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        // attempt 固定要求 status=1：禁用管理员无法登录；失败统一回 auth.failed，不区分账号不存在、密码错误或已禁用。
        if (Auth::guard('admin')->attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'status' => 1], $remember)) {
            // 登录成功后重新生成 Session ID，防止会话固定攻击复用旧会话标识。
            $request->session()->regenerate();
            $admin = Auth::guard('admin')->user();

            // AdminLoginLog 记录后台登录审计日志，用于追踪管理员登录时间、来源 IP 和异常登录排查。
            AdminLoginLog::create([
                'admin_id'    => $admin->id,
                'login_ip'    => $request->ip(),
                'ip_location' => '',
            ]);

            // 更新最后登录信息，便于后台管理员列表展示登录状态和安全审计。
            $admin->update([
                'last_login_ip'  => $request->ip(),
                'login_num'      => $admin->login_num + 1,
            ]);

            return redirect()->intended(route('admin_page_dashboard'));
        }

        return back()->withErrors(['email' => __('auth.failed')])->withInput($request->only('email'));
    }

    /**
     * logout 退出后台 Blade 会话。
     *
     * 参数与字段含义：
     * - $request 表示当前退出登录请求，承载需要失效的 Session 与 CSRF Token。
     *
     * 功能逻辑说明：
     * - 先从 admin guard 注销当前管理员。
     * - 再使当前 Session 失效并重新生成 CSRF Token，避免退出后的旧令牌继续访问后台表单。
     *
     * @param Request $request 当前退出登录请求对象。
     * @return \Illuminate\Http\RedirectResponse 退出后跳转后台登录页。
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin_page_login');
    }
}
