<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/10
 * Time: 21:43
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台管理员账号管理控制器。
 *
 * 功能逻辑说明：
 * - 本控制器承载 admin_api_adminList、admin_api_createAdmin、admin_api_updateAdmin、admin_api_deleteAdmin。
 * - 数据来源为 admins 表，是后台登录、角色授权和权限控制的核心账号来源。
 * - role_id 表示绑定的后台角色，对应 roles.id，用于菜单、按钮、接口权限和数据范围控制。
 * - 页面按钮显隐来自 permissions.slug，接口最终仍由 check.permission:admin 按 permissions.api_route 做鉴权。
 *
 * 文件功能：
 * - 后台管理员账号的列表、创建、更新、删除与密码重置；输入为 username/email/password/mobile/role_id/status。
 * - 密码一律 Hash::make 后入库，响应与日志均不包含明文密码。
 *
 * 适用场景：
 * - 后台"管理员管理"页面；列表分页读取 admins 全表，写操作由 check.permission:admin 按 api_route 鉴权。
 *
 * 失败语义：
 * - 校验失败返回 VALIDATION_FAILED；记录不存在返回 DATA_NOT_FOUND；未预期异常统一 serverErrorResponse()。
 */
class AdminController extends AdminBaseController
{
    /**
     * 获取管理员账号列表。
     *
     * 参数逻辑说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，兼容标准分页参数。
     * - limit 表示 Layui 表格每页数量，当前端未提交 per_page 时使用。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page、limit 等分页参数。
     * @return \Illuminate\Http\JsonResponse 管理员账号分页列表响应。
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', $request->input('limit', 15));

        $admins = Admin::query()->paginate($perPage, ['*'], 'page', $page);

        return $this->success($admins, __('admin.admin_list_fetched'));
    }

    /**
     * 创建管理员账号。
     *
     * 参数逻辑说明：
     * - username 表示管理员登录名，对应 admins.username，新增时必须唯一。
     * - email 表示管理员邮箱，对应 admins.email，新增时必须唯一。
     * - password 表示管理员登录密码，新增时必填，写入前必须使用 Hash::make 加密。
     *
     * @param Request $request HTTP 请求对象，承载 username、email、password。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新管理员账号记录。
     *
     * 失败语义：
     * - 校验失败返回 VALIDATION_FAILED；未预期异常统一返回 serverErrorResponse()。
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:50|unique:admins',
                'email'    => 'required|email|unique:admins',
                'password' => 'required|string|min:6',
                'mobile'   => 'nullable|string|max:20',
                'role_id'  => 'nullable|integer|exists:roles,id',
                'status'   => 'nullable|in:0,1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->only(['username', 'email', 'password']);
            $data['password'] = Hash::make($data['password']);
            $data = $this->mergeOptionalAccountFields($request, $data);

            $roleId = $request->filled('role_id')
                ? (int) $request->input('role_id')
                : null;

            $admin = DB::transaction(function () use ($data, $roleId) {
                if ($roleId !== null) {
                    $role = Role::query()->whereKey($roleId)->lockForUpdate()->first();
                    if (!$role) {
                        return null;
                    }
                }

                return Admin::create($data);
            });

            if (!$admin) {
                return $this->error(__('admin.role_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            return $this->success($admin, __('admin.admin_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 更新管理员账号并兼容角色分配。
     *
     * 参数逻辑说明：
     * - id 表示管理员主键，对应 admins.id。
     * - username 表示管理员登录名，编辑时必须排除当前 id 后保持唯一。
     * - email 表示管理员邮箱，编辑时必须排除当前 id 后保持唯一。
     * - password 表示管理员登录密码；password 留空表示编辑时保留原密码。
     * - 编辑时 password 留空表示保留原密码，不会覆盖 admins.password。
     * - role_id 表示绑定的后台角色，直接对应 roles.id。
     * - roles 表示可选角色 ID 数组，数组成员应为 roles.id；仅当 Admin 模型存在 roles 关联时才同步，避免未启用关联时报错。
     *
     * @param Request $request HTTP 请求对象，承载 username、email、password、roles 等更新字段。
     * @param int $id id 表示 admins.id，即路由传入的管理员主键。
     * @return \Illuminate\Http\JsonResponse 更新后的管理员账号记录。
     *
     * 失败语义：
     * - 校验失败返回 VALIDATION_FAILED；记录不存在返回 DATA_NOT_FOUND；未预期异常统一返回 serverErrorResponse()。
     */
    public function update(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateAdminRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $admin = Admin::find($id);
            if (!$admin) {
                return $this->error(__('admin.admin_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:50|unique:admins,username,' . $id,
                'email'    => 'required|email|unique:admins,email,' . $id,
                'password' => 'nullable|string|min:6',
                'mobile'   => 'nullable|string|max:20',
                'role_id'  => 'nullable|integer|exists:roles,id',
                'status'   => 'nullable|in:0,1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->only(['username', 'email']);
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
            $data = $this->mergeOptionalAccountFields($request, $data);

            $roleId = $request->filled('role_id')
                ? (int) $request->input('role_id')
                : null;

            $updated = DB::transaction(function () use ($admin, $data, $roleId, $request) {
                if ($roleId !== null) {
                    $role = Role::query()->whereKey($roleId)->lockForUpdate()->first();
                    if (!$role) {
                        return false;
                    }
                }

                $admin->update($data);

                if ($request->has('roles') && method_exists($admin, 'roles')) {
                    $admin->roles()->sync($request->roles);
                }

                return true;
            });

            if (!$updated) {
                return $this->error(__('admin.role_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            return $this->success($admin, __('admin.admin_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 重置管理员密码。
     *
     * 参数逻辑说明：
     * - resetPassword 用于重置管理员登录密码，不要求提供旧密码；调用权限由 check.permission:admin 按接口路由保证。
     * - id 表示管理员主键，对应 admins.id。
     * - password 表示新的管理员登录密码，必填且至少 6 位，写入前必须使用 Hash::make 加密。
     *
     * @param Request $request HTTP 请求对象，承载 password。
     * @param int $id 路由中的 admins.id。
     * @return \Illuminate\Http\JsonResponse 密码重置结果响应。
     *
     * 失败语义：
     * - 校验失败返回 VALIDATION_FAILED；记录不存在返回 DATA_NOT_FOUND；未预期异常统一返回 serverErrorResponse()。
     */
    public function resetPassword(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateAdminRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
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
            return $this->serverErrorResponse();
        }
    }

    /**
     * 合并管理员账号可选维护字段。
     *
     * @param Request $request HTTP 请求对象，读取 mobile、role_id 和 status。
     * @param array<string, mixed> $data 已通过基础验证的账号字段。
     * @return array<string, mixed> 合并后的可写入 admins 字段。
     */
    private function mergeOptionalAccountFields(Request $request, array $data): array
    {
        if ($request->has('mobile')) {
            $data['mobile'] = $request->input('mobile') === '' ? null : $request->input('mobile');
        }

        if ($request->has('role_id')) {
            $data['role_id'] = $request->input('role_id') === '' ? null : $request->input('role_id');
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $data['status'] = (int) $request->input('status');
        }

        return $data;
    }

    /**
     * 删除管理员账号。
     *
     * 参数逻辑说明：
     * - id 表示管理员主键，对应 admins.id。
     * - 删除入口必须具备 admin_api_deleteAdmin 对应权限配置，避免无权限管理员直接调用接口。
     *
     * @param int $id 路由中的 admins.id。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     *
     * 失败语义：
     * - ID 非法或记录不存在时分别返回 VALIDATION_FAILED / DATA_NOT_FOUND；未预期异常统一返回 serverErrorResponse()。
     */
    public function destroy($id)
    {
        try {
            if ($routeIdError = $this->validateAdminRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $admin = Admin::find($id);
            if (!$admin) {
                return $this->error(__('admin.admin_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $admin->delete();

            return $this->success([], __('admin.admin_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验后台管理员账号路由 ID，避免非严格数字字符串命中 admins.id。
     *
     * @param mixed $id 路由参数中的 admins.id。
     * @return \Illuminate\Http\JsonResponse|null ID 非法时返回统一错误响应，否则返回 null。
     */
    private function validateAdminRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }
}
