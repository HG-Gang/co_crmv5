<?php

namespace App\Http\Controllers\Admin;

use App\Models\Permission;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Permission Management Controller
 * 后台权限管理控制器
 * 
 * Handles permission tree fetching, creation, updates, and deletion.
 * 为管理员提供权限树获取、创建、更新和删除功能。
 */
class PermissionController extends AdminBaseController
{
    /**
     * Get permission tree
     * 获取权限树
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionTree(Request $request)
    {
        $guardType = $request->input('guard_type'); // 'admin' or 'front'
        
        $query = Permission::orderBy('sort');
        if ($guardType) {
            $query->where('guard_type', $guardType);
        }
        
        $permissions = $query->get();
        $tree = $this->buildTree($permissions, 0);

        return $this->success($tree, 'Permission tree fetched');
    }

    /**
     * Recursive function to build tree
     * 递归构建树形结构函数
     */
    private function buildTree($permissions, $parentId)
    {
        $branch = [];
        foreach ($permissions as $permission) {
            if ($permission->parent_id == $parentId) {
                $children = $this->buildTree($permissions, $permission->id);
                if ($children) {
                    $permission->setAttribute('children', $children);
                }
                $branch[] = $permission;
            }
        }
        return $branch;
    }

    /**
     * Create permission
     * 创建权限
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:permissions',
            'guard_type' => 'required|in:admin,front',
            'type' => 'required|in:1,2,3', // 1=menu, 2=page, 3=button
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $permission = Permission::create($request->all());

        return $this->success($permission, 'Permission created', ResponseCode::CREATED);
    }

    /**
     * Update permission
     * 更新权限
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePermission(Request $request)
    {
        $id = $request->input('id');
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->error('Permission not found', ResponseCode::DATA_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:permissions,slug,' . $id,
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $permission->update($request->all());

        return $this->success($permission, 'Permission updated', ResponseCode::UPDATED);
    }

    /**
     * Delete permission
     * 删除权限
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePermission(Request $request, $id = null)
    {
        $id = $id ?: $request->input('id');
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->error('Permission not found', ResponseCode::DATA_NOT_FOUND);
        }

        // Check for children
        if (Permission::where('parent_id', $id)->exists()) {
            return $this->error('Cannot delete parent permission with children', ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $permission->delete();

        return $this->success([], 'Permission deleted', ResponseCode::DELETED);
    }
}
