<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/10
 * Time: 21:07
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台角色管理控制器。
 *
 * 文件功能：
 * - 提供角色列表、创建、更新、删除和角色权限分配接口。
 *
 * 功能逻辑说明：
 * - roles 表保存角色基础信息，例如角色名称、guard_type、说明和启停状态。
 * - role_permissions 表保存角色与 permissions.id 的授权关系，是菜单、按钮和接口权限的真实授权来源。
 * - 后台管理员角色使用 guard_type=admin；前台代理商和普通客户角色使用 guard_type=front，二者不能混用授权。
 * - 页面按钮显隐由 /api/admin/menus 返回的 permissions.slug 控制，接口访问仍由 check.permission:admin 校验。
 *
 * 安全边界：
 * - 分配权限时只允许同步同 guard_type 下的权限，防止后台角色被授权前台菜单或接口。
 * - 授权结果以 role_permissions 表为准，不写第二套权限状态，避免双数据源漂移。
 */
class RoleController extends AdminBaseController
{
    /**
     * 获取后台角色列表。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，包含 Layui 表格分页参数。
     * - $page：Layui 表格当前页码，对应请求参数 page，默认第 1 页。
     * - $perPage：每页条数，优先读取 per_page，兼容旧页面提交的 limit。
     *
     * @param Request $request 当前请求对象，用于读取分页参数。
     * @return \Illuminate\Http\JsonResponse 返回 list 与 total，供 Layui 表格渲染角色列表。
     */
    public function roleList(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));

        $roles = Role::query()
            ->with(['permissionsRelation' => function ($query) {
                $query->select('permissions.id');
            }])
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
        $items = collect($roles->items())->map(function (Role $role) {
            // permission_ids 表示当前角色已拥有的 permissions.id 数组，供角色授权弹窗回显权限树勾选状态。
            $role->setAttribute('permission_ids', $role->permissionsRelation->pluck('id')->map(function ($id) {
                return (int) $id;
            })->values()->all());

            return $role;
        })->all();

        return $this->success([
            'list' => $items,
            'total' => $roles->total(),
        ], __('admin.role_list_fetched'));
    }

    /**
     * 创建角色。
     *
     * 参数含义：
     * - name：角色稳定名称，例如 super_admin、finance_admin、agent_role。
     * - guard_type：角色所属守卫，admin=后台管理员角色，front=前台用户角色。
     * - description：角色中文说明，供后台角色管理页面识别用途。
     * - status：角色启停状态，1=启用，0=停用。
     *
     * @param Request $request 当前请求对象，携带角色基础字段。
     * @return \Illuminate\Http\JsonResponse 创建成功后返回 roles 表新记录。
     */
    public function createRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:roles',
            'guard_type' => 'required|in:admin,front',
            'description' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $role = Role::create($request->only(['name', 'guard_type', 'description', 'status']));

        return $this->success($role, __('admin.role_created'), ResponseCode::CREATED);
    }

    /**
     * 更新角色基础信息。
     *
     * 参数含义：
     * - id：待更新的 roles.id，必须来自角色列表中的真实记录。
     * - name：更新后的角色稳定名称，同一表内不能重复。
     * - guard_type：角色所属守卫，决定后续 assignPermissions 只能同步同 guard_type 下的权限。
     * - description：角色用途说明。
     * - status：角色启停状态。
     *
     * @param Request $request 当前请求对象，携带角色 ID 和待更新字段。
     * @return \Illuminate\Http\JsonResponse 更新成功后返回最新角色记录。
     */
    public function updateRole(Request $request)
    {
        if ($roleIdError = $this->validateRoleId($request->input('id'))) {
            return $roleIdError;
        }

        $id = (int) $request->input('id');
        $role = Role::find($id);
        if (!$role) {
            return $this->error(__('admin.role_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:roles,name,' . $id,
            'guard_type' => 'required|in:admin,front',
            'description' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $role->update($request->only(['name', 'guard_type', 'description', 'status']));

        return $this->success($role, __('admin.role_updated'), ResponseCode::UPDATED);
    }

    /**
     * 删除角色。
     *
     * 参数含义：
     * - $id：路由参数中的 roles.id，兼容 /deleteRole/{id} 形式。
     * - request.id：请求体中的 roles.id，兼容旧前端 POST 形式。
     *
     * 逻辑边界：
     * - 当前方法只删除角色记录本身；管理员占用、数据范围配置清理等更严格约束后续可单独增加。
     * - 删除入口必须具备 admin_api_deleteRole 对应权限配置，避免无权限管理员直接调用接口。
     *
     * @param Request $request 当前请求对象，用于兼容读取 id。
     * @param int|null $id 路由传入的角色 ID，可为空。
     * @return \Illuminate\Http\JsonResponse 删除成功后返回空数据。
     */
    public function deleteRole(Request $request, $id = null)
    {
        $rawRoleId = $id ?: $request->input('id');
        if ($roleIdError = $this->validateRoleId($rawRoleId)) {
            return $roleIdError;
        }

        $roleId = (int) $rawRoleId;

        return DB::transaction(function () use ($roleId) {
            $role = Role::query()
                ->whereKey($roleId)
                ->lockForUpdate()
                ->first();

            if (!$role) {
                return $this->error(__('admin.role_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            if ($role->admins()->withTrashed()->exists()) {
                return $this->error(__('admin.role_in_use'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            $role->delete();

            return $this->success([], __('admin.role_deleted'), ResponseCode::DELETED);
        });
    }

    /**
     * 为角色分配菜单、按钮和接口权限。
     *
     * 参数含义：
     * - $roleId：待授权的 roles.id，决定要写入 role_permissions.role_id 的角色。
     * - $permissions：前端提交的 permissions.id 数组，每个值都会写入 role_permissions.permission_id。
     *
     * 逻辑边界：
     * - 只允许同步同 guard_type 下的权限，后台角色只能绑定 admin 权限，前台角色只能绑定 front 权限。
     * - 授权结果以 role_permissions 表为准，不再把权限状态写入 roles.permissions JSON，避免形成双数据源。
     * - permissions.slug 继续用于前端菜单和按钮显隐，permissions.api_route 继续用于后端接口鉴权。
     *
     * @param Request $request 当前请求对象，携带 role_id 和 permissions 数组。
     * @return \Illuminate\Http\JsonResponse 授权成功后返回空数据。
     */
    public function assignPermissions(Request $request)
    {
        if ($roleIdError = $this->validateRoleId($request->input('role_id'))) {
            return $roleIdError;
        }

        if ($permissionIdsError = $this->validatePermissionIds($request->input('permissions', []))) {
            return $permissionIdsError;
        }

        $roleId = (int) $request->input('role_id');
        $permissions = array_values(array_unique(array_map('intval', $request->input('permissions', []))));

        return DB::transaction(function () use ($roleId, $permissions) {
            $role = Role::query()->whereKey($roleId)->lockForUpdate()->first();
            if (!$role) {
                return $this->error(__('admin.role_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make([
                'role_id' => $roleId,
                'permissions' => $permissions,
            ], [
                'role_id' => 'required|integer|exists:roles,id',
                'permissions' => 'array',
                'permissions.*' => 'integer|exists:permissions,id',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            // 只保留与角色同 guard_type 的权限，防止把前台权限误授权给后台角色；数量不符说明前端提交了跨守卫权限。
            $validPermissionIds = Permission::query()
                ->whereIn('id', $permissions)
                ->where('guard_type', $role->guard_type)
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            if (count($validPermissionIds) !== count($permissions)) {
                return $this->error(__('admin.permission_not_found'), ResponseCode::VALIDATION_FAILED);
            }

            $role->permissions()->sync($validPermissionIds);

            return $this->success([], __('admin.permissions_assigned'));
        });
    }

    /**
     * 校验角色主键，必须为整数。
     *
     * @param mixed $id roles.id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateRoleId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验权限 ID 数组，逐项必须是正整数，拒绝嵌套数组或对象。
     *
     * @param mixed $permissions 前端提交的 permissions.id 数组。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validatePermissionIds($permissions)
    {
        $validator = Validator::make(['permissions' => $permissions], [
            'permissions' => 'array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        foreach ($permissions as $permissionId) {
            if (is_array($permissionId) || is_object($permissionId)) {
                return $this->error('permissions must be an integer list.', ResponseCode::VALIDATION_FAILED);
            }

            $validator = Validator::make(['permission_id' => trim((string) $permissionId)], [
                'permission_id' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }
        }

        return null;
    }
}
