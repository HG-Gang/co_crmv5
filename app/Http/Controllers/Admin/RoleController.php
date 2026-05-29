<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\Permission;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Role Management Controller
 * 后台角色管理控制器
 * 
 * Handles role list, creation, updates, deletion, and permission assignments.
 * 为管理员提供角色列表、创建、更新、删除和权限分配功能。
 */
class RoleController extends AdminBaseController
{
    /**
     * List all roles
     * 获取角色列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function roleList(Request $request)
    {
        $page = $request->input('page', 1);
        // Layui tableCommon sends per_page, while older table pages may still
        // submit limit.  Accept both names so shared table config can be used
        // without breaking legacy callers.
        $perPage = $request->input('per_page', $request->input('limit', 15));

        $roles = Role::query()->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'list'  => $roles->items(),
            'total' => $roles->total(),
        ], 'Role list fetched');
    }

    /**
     * Create role
     * 创建角色
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:roles',
            'guard_type' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $role = Role::create($request->only(['name', 'guard_type', 'description', 'status']));

        return $this->success($role, 'Role created', ResponseCode::CREATED);
    }

    /**
     * Update role
     * 更新角色
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRole(Request $request)
    {
        $id = $request->input('id');
        $role = Role::find($id);
        if (!$role) {
            return $this->error('Role not found', ResponseCode::DATA_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:roles,name,' . $id,
            'guard_type' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $role->update($request->only(['name', 'guard_type', 'description', 'status']));

        return $this->success($role, 'Role updated', ResponseCode::UPDATED);
    }

    /**
     * Delete role
     * 删除角色
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteRole(Request $request, $id = null)
    {
        $id = $id ?: $request->input('id');
        $role = Role::find($id);
        if (!$role) {
            return $this->error('Role not found', ResponseCode::DATA_NOT_FOUND);
        }

        $role->delete();

        return $this->success([], 'Role deleted', ResponseCode::DELETED);
    }

    /**
     * Assign permissions to role
     * 为角色分配权限
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermissions(Request $request)
    {
        $roleId = $request->input('role_id');
        $permissions = $request->input('permissions', []);
        
        $role = Role::find($roleId);
        if (!$role) {
            return $this->error('Role not found', ResponseCode::DATA_NOT_FOUND);
        }
        
        $role->permissions()->sync($permissions);
        
        return $this->success([], 'Permissions assigned');
    }
}
