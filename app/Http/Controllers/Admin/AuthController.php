<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use App\Models\AdminLoginLog;
use App\Services\JwtService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Admin Authentication Controller
 * 后台管理员认证控制器
 */
class AuthController extends AdminBaseController
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Show login page
     */
    public function showLogin()
    {
        return view("admin.layui.auth.login");
    }

    /**
     * Admin Login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $admin = Admin::where('username', $request->username)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return $this->error('Invalid credentials', ResponseCode::AUTH_FAILED);
        }

        if (method_exists($admin, 'isActive') && !$admin->isActive()) {
            return $this->error('Account disabled', ResponseCode::AUTH_FAILED);
        }

        $token = $this->jwtService->generateToken([
            'sub'   => $admin->id,
            'guard' => 'admin',
        ]);

        $admin->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        AdminLoginLog::create([
            'admin_id'   => $admin->id,
            'login_ip'   => $request->ip(),
            'ip_address' => '',
            'user_agent' => $request->userAgent(),
        ]);

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user'         => [
                'id'       => $admin->id,
                'username' => $admin->username,
            ],
        ], 'Login successful');
    }

    /**
     * Admin Logout
     */
    public function logout(Request $request)
    {
        $token = $request->attributes->get('jwt_token');
        if ($token) {
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], 'Logout successful');
    }

    /**
     * Get admin profile info
     */
    public function profileInfo(Request $request)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error('Not logged in', ResponseCode::AUTH_FAILED);
        }
        return $this->success($admin, 'Query successful');
    }

    /**
     * Update admin profile
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error('Not logged in', ResponseCode::AUTH_FAILED);
        }

        $validator = Validator::make($request->all(), [
            'email'  => 'nullable|email|max:100',
            'mobile' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $updateData = $request->only(['email', 'mobile']);
        if (!empty($updateData)) {
            $admin->update($updateData);
        }

        return $this->success($admin->fresh(), 'Profile updated');
    }

    /**
     * Change Password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $admin = $request->user('admin');

        if (!Hash::check($request->old_password, $admin->password)) {
            return $this->error('Old password incorrect', ResponseCode::OLD_PASSWORD_WRONG);
        }

        $admin->update(['password' => Hash::make($request->password)]);

        $token = $request->attributes->get('jwt_token');
        if ($token) {
            $this->jwtService->invalidateToken($token);
        }

        return $this->success([], 'Password changed');
    }

    /**
     * Upload Avatar
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error('Not logged in', ResponseCode::AUTH_FAILED);
        }

        if ($request->file('avatar')) {
            $path = $request->file('avatar')->store('public/admin/avatars');
            $url = Storage::url($path);
            
            // $admin->update(['avatar' => $url]);
            
            return $this->success(['url' => $url], 'Avatar uploaded');
        }

        return $this->error('Upload failed', ResponseCode::INTERNAL_ERROR);
    }

    /**
     * Refresh Token
     */
    public function refreshToken(Request $request)
    {
        $token = $request->attributes->get('jwt_token');
        if (!$token) {
            return $this->error(__('response.token_missing'), ResponseCode::TOKEN_MISSING);
        }
        try {
            $newToken = $this->jwtService->refreshToken($token);
            return $this->success(['access_token' => $newToken, 'token_type' => 'Bearer']);
        } catch (Exception $e) {
            return $this->error(__('response.token_expired'), ResponseCode::TOKEN_EXPIRED);
        }
    }
}
