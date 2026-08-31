<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台权限字典管理控制器。
 *
 * 文件功能：
 * - 提供权限树查询、权限字典记录的新增、更新和删除。
 *
 * 功能逻辑说明：
 * - permissions 表是前后台菜单、页面、按钮和接口权限的唯一配置来源。
 * - guard_type 区分后台 admin 权限与前台 front 权限，避免后台角色误授权前台菜单。
 * - type 区分权限节点用途：1=目录菜单，2=页面菜单，3=按钮或接口动作。
 * - api_route 保存 Laravel 命名路由，check.permission:admin 会按该配置做接口层鉴权。
 *
 * 安全边界：
 * - 删除存在子权限的节点时禁止删除，避免产生无法挂载的孤儿菜单或按钮权限。
 * - 权限字典变更会影响所有角色授权面，接口访问必须经过 check.permission:admin 鉴权。
 */
class PermissionController extends AdminBaseController
{
    /**
     * 获取权限树。
     *
     * 参数含义：
     * - $request：当前 HTTP 请求对象，用于读取筛选参数。
     * - $guardType：权限守卫类型，admin=后台权限，front=前台权限；为空时返回全部权限字典。
     *
     * @param Request $request 当前请求对象，携带 guard_type 可选筛选条件。
     * @return \Illuminate\Http\JsonResponse 返回树形权限节点，供权限字典页面预览。
     */
    public function permissionTree(Request $request)
    {
        $guardType = $request->input('guard_type');

        $query = Permission::query()->orderBy('sort')->orderBy('id');
        if ($guardType) {
            $query->where('guard_type', $guardType);
        }

        $permissions = $query->get();
        $tree = $this->buildTree($permissions, 0);

        return $this->success($tree, __('admin.permission_tree_fetched'));
    }

    /**
     * 递归构建权限树。
     *
     * 参数含义：
     * - $permissions：已经按 sort/id 排序的权限集合，元素来自 permissions 表。
     * - $parentId：当前递归层级的父级 permissions.id，0 表示根节点。
     *
     * @param \Illuminate\Support\Collection<int, Permission> $permissions 权限集合。
     * @param int $parentId 父级权限 ID。
     * @return array<int, Permission> 当前父级下的子权限节点列表。
     */
    private function buildTree($permissions, $parentId)
    {
        $branch = [];

        // 递归组装：只把 parent_id 等于当前层级的节点挂到本分支，子节点继续递归挂载。
        foreach ($permissions as $permission) {
            if ((int) $permission->parent_id !== (int) $parentId) {
                continue;
            }

            $children = $this->buildTree($permissions, $permission->id);
            if ($children) {
                $permission->setAttribute('children', $children);
            }

            $branch[] = $permission;
        }

        return $branch;
    }

    /**
     * 创建权限字典记录。
     *
     * 参数含义：
     * - name：权限中文名称，供菜单、按钮或权限管理页面展示。
     * - slug：权限稳定标识，供前端 data-permission 和后端角色授权统一引用。
     * - guard_type：权限守卫类型，admin=后台，front=前台。
     * - $type：权限类型，1=目录菜单，2=页面菜单，3=按钮或接口动作。
     * - route：页面路由路径，用于菜单点击跳转。
     * - $apiRoute：接口命名路由，用于 check.permission:admin 反查 permissions.api_route。
     *
     * @param Request $request 当前请求对象，携带权限字典字段。
     * @return \Illuminate\Http\JsonResponse 创建成功后返回 permissions 表新记录。
     */
    public function createPermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|integer|min:0',
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:permissions',
            'guard_type' => 'required|in:admin,front',
            'type' => 'required|in:1,2,3',
            'api_route' => 'nullable|string|max:150',
            'route' => 'nullable|string|max:150',
            'icon' => 'nullable|string|max:100',
            'sort' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $permission = Permission::create($this->permissionPayload($request));

        return $this->success($permission, __('admin.permission_created'), ResponseCode::CREATED);
    }

    /**
     * 更新权限字典记录。
     *
     * 参数含义：
     * - id：待更新的 permissions.id。
     * - slug：更新后的权限稳定标识，同表内必须唯一。
     * - parent_id：父级 permissions.id，用于构建菜单/权限树。
     * - status：启停状态，0=停用后菜单和按钮不应再对角色开放。
     *
     * @param Request $request 当前请求对象，携带权限 ID 和待更新字段。
     * @return \Illuminate\Http\JsonResponse 更新成功后返回最新权限记录。
     */
    public function updatePermission(Request $request)
    {
        if ($permissionIdError = $this->validatePermissionId($request->input('id'))) {
            return $permissionIdError;
        }

        $id = (int) $request->input('id');
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->error(__('admin.permission_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|integer|min:0',
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:permissions,slug,' . $id,
            'guard_type' => 'nullable|in:admin,front',
            'type' => 'nullable|in:1,2,3',
            'api_route' => 'nullable|string|max:150',
            'route' => 'nullable|string|max:150',
            'icon' => 'nullable|string|max:100',
            'sort' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $permission->update($this->permissionPayload($request));

        return $this->success($permission, __('admin.permission_updated'), ResponseCode::UPDATED);
    }

    /**
     * 删除权限字典记录。
     *
     * 参数含义：
     * - $id：路由参数中的 permissions.id，兼容 /deletePermission/{id} 形式。
     * - request.id：请求体中的 permissions.id，兼容旧前端 POST 形式。
     *
     * 逻辑边界：
     * - 存在子权限时禁止删除，避免父菜单被删除后留下无法挂载的孤儿菜单或按钮权限。
     * - 删除入口必须具备 admin_api_deletePermission 对应权限配置，接口层仍由 check.permission:admin 校验。
     *
     * @param Request $request 当前请求对象，用于兼容读取 id。
     * @param int|null $id 路由传入的权限 ID，可为空。
     * @return \Illuminate\Http\JsonResponse 删除成功后返回空数据。
     */
    public function deletePermission(Request $request, $id = null)
    {
        $rawPermissionId = $id ?: $request->input('id');
        if ($permissionIdError = $this->validatePermissionId($rawPermissionId)) {
            return $permissionIdError;
        }

        $permissionId = (int) $rawPermissionId;
        $permission = Permission::find($permissionId);
        if (!$permission) {
            return $this->error(__('admin.permission_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        if (Permission::where('parent_id', $permissionId)->exists()) {
            return $this->error(__('admin.permission_has_children'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $permission->delete();

        return $this->success([], __('admin.permission_deleted'), ResponseCode::DELETED);
    }

    /**
     * 提取权限字典可写字段。
     *
     * 逻辑说明：
     * - 只允许写入 Permission 模型明确支持的字段，避免把请求中的无关参数直接落库。
     * - parent_id、sort、status 在页面未提交时交给模型或数据库默认值处理。
     *
     * @param Request $request 当前请求对象，携带权限字典字段。
     * @return array<string, mixed> 可写入 permissions 表的字段数组。
     */
    private function permissionPayload(Request $request): array
    {
        return $request->only([
            'parent_id',
            'name',
            'slug',
            'api_route',
            'route',
            'icon',
            'type',
            'guard_type',
            'sort',
            'status',
        ]);
    }

    /**
     * 校验权限字典主键，必须为整数。
     *
     * @param mixed $id permissions.id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validatePermissionId($id)
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
