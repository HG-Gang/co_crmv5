<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends AdminBaseController
{
    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin_page_dashboard');
        }
        return view('admin.auth.login');
    }

    public function doLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required'    => __('auth.email') . __('common.required', [], 'zh_CN'),
            'password.required' => __('auth.password_label') . ' 不能为空',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (Auth::guard('admin')->attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'status' => 1], $remember)) {
            $request->session()->regenerate();
            $admin = Auth::guard('admin')->user();

            // 记录登录日志
            \App\Models\AdminLoginLog::create([
                'admin_id'    => $admin->id,
                'login_ip'    => $request->ip(),
                'ip_location' => '',
            ]);

            // 更新最后登录信息
            $admin->update([
                'last_login_ip'  => $request->ip(),
                'login_num'      => $admin->login_num + 1,
            ]);

            return redirect()->intended(route('admin_page_dashboard'));
        }

        return back()->withErrors(['email' => __('auth.failed')])->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin_page_login');
    }
}
