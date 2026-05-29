<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserAuth;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * User Management Controller
 * 用户管理控制器
 */
class UserController extends AdminBaseController
{
    /**
     * List all users (agents + customers)
     * 获取所有用户列表 (代理 + 客户)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = UserInfo::query()->with(['login', 'auth']);

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by email
        if ($request->filled('email')) {
            $email = $request->email;
            $query->whereHas('login', function($q) use ($email) {
                $q->where('email', 'LIKE', "%{$email}%");
            });
        }

        // Filter by user_name
        if ($request->filled('user_name')) {
            $query->where('user_name', 'LIKE', "%{$request->user_name}%");
        }

        // Filter by account_type
        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        // Filter by auth_status
        if ($request->filled('auth_status')) {
            $query->where('auth_status', $request->auth_status);
        }

        $users = $query->orderByDesc('user_id')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($users, __('admin.user_list_fetched'));
    }

    /**
     * Get user detail
     * 获取用户详情
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($userId)
    {
        $user = UserInfo::with(['login', 'auth'])->where('user_id', $userId)->first();
        
        if (!$user) {
            return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        return $this->success($user, __('admin.user_detail_fetched'));
    }

    /**
     * Update user info
     * 更新用户信息
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $userId)
    {
        try {
            $user = UserInfo::where('user_id', $userId)->first();
            if (!$user) {
                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'user_name' => 'sometimes|string|max:100',
                'phone'     => 'sometimes|string|max:20',
                'group_id'  => 'sometimes|integer',
                'comm_rate' => 'sometimes|numeric|min:0|max:1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->only([
                'user_name', 'phone', 'group_id', 'comm_rate', 'trading_mode',
                'agent_level', 'parent_id', 'remarks'
            ]);

            $user->update($data);

            return $this->success($user, __('admin.user_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Enable/disable user login and set auth_status
     * 启用/禁用用户登录并设置实名状态
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $userId)
    {
        try {
            $user = UserInfo::where('user_id', $userId)->first();
            if (!$user) {
                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            if ($request->has('is_enabled')) {
                UserLogin::where('user_id', $userId)->update(['is_enabled' => $request->is_enabled]);
            }

            if ($request->has('auth_status')) {
                $user->update(['auth_status' => $request->auth_status]);
            }

            return $this->success([], __('admin.user_status_updated'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Review identity verification
     * 审核实名认证 (银行卡及身份证)
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reviewAuth(Request $request, $userId)
    {
        try {
            $auth = UserAuth::where('user_id', $userId)->first();
            if (!$auth) {
                return $this->error(__('admin.auth_record_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:1,2',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $status = $request->input('status'); // 1=Approved, 2=Rejected
            $reason = $request->input('reason', '');

            $auth->update([
                'status' => $status,
                'memo'   => $reason,
            ]);

            UserInfo::where('user_id', $userId)->update(['auth_status' => $status]);

            return $this->success([], __('admin.auth_review_completed'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Soft delete user
     * 软删除用户
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($userId)
    {
        try {
            $user = UserInfo::where('user_id', $userId)->first();
            if (!$user) {
                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $user->update(['is_cancelled' => 1]);
            $user->delete(); // If SoftDeletes is used in model

            return $this->success([], __('admin.user_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
