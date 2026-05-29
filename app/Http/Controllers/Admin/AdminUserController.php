<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserAuth;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin User Management Controller
 * 后台用户管理控制器
 * 
 * Handles user list, details, updates, and status changes for admin.
 * 为管理员提供用户列表、详情、更新和状态更改功能。
 */
class AdminUserController extends AdminBaseController
{
    /**
     * Paginated user list with filters
     * 分页用户列表（带过滤器）
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userList(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('limit', 15);

        $query = UserInfo::query()->with(['login', 'auth']);

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('email')) {
            $email = $request->email;
            $query->whereHas('login', function($q) use ($email) {
                $q->where('email', 'LIKE', "%{$email}%");
            });
        }

        if ($request->filled('user_name')) {
            $query->where('user_name', 'LIKE', "%{$request->user_name}%");
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        $users = $query->orderByDesc('user_id')->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'list'  => $users->items(),
            'total' => $users->total(),
        ], 'User list fetched');
    }

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        $user = UserInfo::where('user_id', $id)->first();
        if (!$user) {
            return $this->error('User not found', ResponseCode::USER_NOT_FOUND);
        }
        $user->delete();
        return $this->success([], 'User deleted');
    }

    /**
     * Review identity verification
     */
    public function reviewAuth(Request $request)
    {
        $userId = $request->input('user_id');
        $status = $request->input('status'); // 1=Approved, 2=Rejected
        
        $auth = UserAuth::where('user_id', $userId)->first();
        if (!$auth) {
            return $this->error('Auth record not found', ResponseCode::DATA_NOT_FOUND);
        }

        $auth->update([
            'status' => $status,
            'memo'   => $request->input('reason', ''),
        ]);

        UserInfo::where('user_id', $userId)->update(['auth_status' => $status]);

        return $this->success([], 'Auth review completed');
    }

    /**
     * Get user full detail
     * 获取用户完整详情
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userDetail(Request $request)
    {
        $userId = $request->input('user_id');
        $user = UserInfo::with(['login', 'auth'])->where('user_id', $userId)->first();
        
        if (!$user) {
            return $this->error('User not found', ResponseCode::USER_NOT_FOUND);
        }

        return $this->success($user, 'User detail fetched');
    }

    /**
     * Update any user field
     * 更新用户任何字段
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser(Request $request)
    {
        $userId = $request->input('user_id');
        $user = UserInfo::where('user_id', $userId)->first();
        
        if (!$user) {
            return $this->error('User not found', ResponseCode::USER_NOT_FOUND);
        }

        $data = $request->except(['user_id', 'id']);
        $user->update($data);

        return $this->success($user, 'User updated', ResponseCode::UPDATED);
    }

    /**
     * Enable/disable user
     * 启用/禁用用户
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeUserStatus(Request $request)
    {
        $userId = $request->input('user_id');
        $isEnabled = $request->input('is_enabled');
        
        $userLogin = UserLogin::where('user_id', $userId)->first();
        if (!$userLogin) {
            return $this->error('User not found', ResponseCode::USER_NOT_FOUND);
        }

        $userLogin->update(['is_enabled' => $isEnabled]);

        return $this->success([], 'User status updated');
    }
}
