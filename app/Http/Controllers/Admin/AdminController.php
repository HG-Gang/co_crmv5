<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

/**
 * Admin User Management Controller
 * 管理员管理控制器
 */
class AdminController extends AdminBaseController
{
    /**
     * List all admin users
     * 获取所有管理员列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $admins = Admin::query()->paginate($perPage, ['*'], 'page', $page);

        return $this->success($admins, __('admin.admin_list_fetched'));
    }

    /**
     * Create admin user
     * 创建管理员
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:50|unique:admins',
                'email'    => 'required|email|unique:admins',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->only(['username', 'email', 'password']);
            $data['password'] = Hash::make($data['password']);
            
            $admin = Admin::create($data);

            return $this->success($admin, __('admin.admin_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update admin user and assign roles
     * 更新管理员信息并分配角色
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $admin = Admin::find($id);
            if (!$admin) {
                return $this->error(__('admin.admin_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:50|unique:admins,username,' . $id,
                'email'    => 'required|email|unique:admins,email,' . $id,
                'password' => 'sometimes|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->only(['username', 'email']);
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $admin->update($data);

            if ($request->has('roles')) {
                if (method_exists($admin, 'roles')) {
                    $admin->roles()->sync($request->roles);
                }
            }

            return $this->success($admin, __('admin.admin_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Reset admin password
     * 重置管理员密码
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request, $id)
    {
        try {
            $admin = Admin::find($id);
            if (!$admin) {
                return $this->error(__('admin.admin_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $admin->update([
                'password' => Hash::make($request->password),
            ]);

            return $this->success([], __('admin.password_reset_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Delete admin user
     * 删除管理员
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $admin = Admin::find($id);
            if (!$admin) {
                return $this->error(__('admin.admin_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $admin->delete();

            return $this->success([], __('admin.admin_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
