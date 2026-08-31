<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:10
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\AdminAgentBinding;
use App\Models\Role;
use App\Models\RoleDataScope;
use App\Models\UserInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台数据范围配置控制器。
 *
 * 文件功能：
 * - 这里只负责维护数据库配置，不在控制器里直接拼接业务列表 SQL。
 * - 角色数据范围写入 role_data_scopes 表，决定某个角色进入接口后能看哪些数据。
 * - 管理员代理绑定写入 admin_agent_bindings 表，决定某个管理员被授权管理哪些代理树节点。
 * - 真实业务查询统一交给 AdminDataScopeService 读取这些表配置后再约束列表和单条操作。
 *
 * 适用场景：
 * - 后台数据权限管理页面：角色数据范围配置与管理员代理绑定维护。
 * - 输入为页面表单提交的 role_id/scope_type/admin_id/agent_id 等配置参数，输出为对应配置表记录。
 * - 本控制器只校验参数合法性并落库，不拼接任何业务列表 SQL。
 */
class DataScopeController extends AdminBaseController
{
    /**
     * 获取角色数据范围配置列表。
     *
     * @param Request $request 请求参数：
     *                         page=当前页码，从 1 开始；
     *                         per_page/limit=每页数量，Layui 默认会传 per_page；
     *                         guard_type 固定按后台 admin 过滤，避免前后台角色混用。
     * @return \Illuminate\Http\JsonResponse 返回 list 与 total，供数据范围管理页渲染角色配置表。
     */
    public function roleDataScopeList(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('limit', 15))));

        $roles = Role::query()
            ->where('guard_type', 'admin')
            ->with('dataScope')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'list' => $roles->items(),
            'total' => $roles->total(),
        ], __('admin.data_scope_list_fetched'));
    }

    /**
     * 保存角色数据范围配置。
     *
     * @param Request $request 请求参数：
     *                         role_id=角色 ID，对应 roles.id；
     *                         scope_type=数据范围类型，支持 all/self/created/agent_tree/custom_agents/custom_users；
     *                         agent_ids=指定代理 ID 集合，支持数组或英文逗号分隔字符串；
     *                         user_ids=指定用户 ID 集合，支持数组或英文逗号分隔字符串；
     *                         status=启用状态，1 启用，0 禁用。
     * @return \Illuminate\Http\JsonResponse 返回保存后的 role_data_scopes 记录。
     */
    public function saveRoleDataScope(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|integer|exists:roles,id',
            'scope_type' => 'required|string|in:all,self,created,agent_tree,custom_agents,custom_users',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        if ($agentIdsError = $this->validateIdListField($request, 'agent_ids')) {
            return $agentIdsError;
        }

        if ($userIdsError = $this->validateIdListField($request, 'user_ids')) {
            return $userIdsError;
        }

        // 按 role_id 唯一键 upsert 整体替换配置，避免新旧字段残留或半份覆盖。
        $scope = RoleDataScope::updateOrCreate(
            ['role_id' => (int) $request->input('role_id')],
            [
                'scope_type' => $request->input('scope_type'),
                'agent_ids' => $this->parseIdList($request->input('agent_ids')),
                'user_ids' => $this->parseIdList($request->input('user_ids')),
                'status' => (int) $request->input('status', 1),
            ]
        );

        return $this->success($scope, __('admin.data_scope_saved'), ResponseCode::UPDATED);
    }

    /**
     * 获取管理员代理绑定列表。
     *
     * @param Request $request 请求参数：
     *                         page=当前页码；
     *                         per_page/limit=每页数量；
     *                         admin_id=可选管理员 ID，用于只查看某个管理员的代理绑定。
     * @return \Illuminate\Http\JsonResponse 返回管理员、代理资料和绑定状态，供 Blade 页面维护。
     */
    public function adminAgentBindingList(Request $request)
    {
        if ($adminIdError = $this->validateAdminIdFilter($request)) {
            return $adminIdError;
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('limit', 15))));

        $query = AdminAgentBinding::query()
            ->with(['admin', 'agent'])
            ->orderByDesc('id');

        if ($request->filled('admin_id')) {
            $query->where('admin_id', (int) $request->input('admin_id'));
        }

        $bindings = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'list' => $bindings->items(),
            'total' => $bindings->total(),
        ], __('admin.admin_agent_bindings_fetched'));
    }

    /**
     * 保存管理员代理绑定。
     *
     * @param Request $request 请求参数：
     *                         admin_id=后台管理员 ID，对应 admins.id；
     *                         agent_id=代理业务用户 ID，对应 user_infos.user_id 且 account_type=1；
     *                         binding_type=绑定类型，primary 主绑定，extra 额外授权；
     *                         status=启用状态，1 启用，0 禁用。
     * @return \Illuminate\Http\JsonResponse 返回保存后的 admin_agent_bindings 记录。
     */
    public function saveAdminAgentBinding(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'admin_id' => 'required|integer|exists:admins,id',
            'agent_id' => 'required|integer',
            'binding_type' => 'nullable|string|in:primary,extra',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        // 只允许把真实代理账号（account_type=1）绑定给管理员，防止客户账号被当作代理树节点授权。
        $agentExists = UserInfo::where('user_id', (int) $request->input('agent_id'))
            ->where('account_type', 1)
            ->exists();

        if (! $agentExists) {
            return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        // 同一 admin+agent 组合唯一，重复提交视为切换 binding_type/status，不会产生重复绑定行。
        $binding = AdminAgentBinding::updateOrCreate(
            [
                'admin_id' => (int) $request->input('admin_id'),
                'agent_id' => (int) $request->input('agent_id'),
            ],
            [
                'binding_type' => $request->input('binding_type', 'primary'),
                'status' => (int) $request->input('status', 1),
            ]
        );

        return $this->success($binding, __('admin.admin_agent_binding_saved'), ResponseCode::CREATED);
    }

    /**
     * 删除管理员代理绑定。
     *
     * @param Request $request 请求参数：
     *                         id=admin_agent_bindings 主键 ID。
     * @return \Illuminate\Http\JsonResponse 返回删除结果，删除后 AdminDataScopeService 不再读取该绑定。
     */
    public function deleteAdminAgentBinding(Request $request)
    {
        if ($idError = $this->validateBindingId($request->input('id'))) {
            return $idError;
        }

        $binding = AdminAgentBinding::find((int) $request->input('id'));

        if (! $binding) {
            return $this->error(__('admin.admin_agent_binding_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $binding->delete();

        return $this->success([], __('admin.admin_agent_binding_deleted'), ResponseCode::DELETED);
    }

    /**
     * 校验列表 admin_id 筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
     */
    private function validateAdminIdFilter(Request $request)
    {
        if (!$request->filled('admin_id')) {
            return null;
        }

        $validator = Validator::make(['admin_id' => $request->input('admin_id')], [
            'admin_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验 agent_ids/user_ids 字段的每个元素都必须为正整数。
     *
     * @param Request $request 当前请求对象。
     * @param string $field 待校验字段名，agent_ids 或 user_ids。
     * @return \Illuminate\Http\JsonResponse|null 任一元素非法即返回错误响应并终止保存，通过时返回 null。
     */
    private function validateIdListField(Request $request, string $field)
    {
        if (!$request->filled($field)) {
            return null;
        }

        foreach ($this->normalizeIdList($request->input($field)) as $item) {
            if (is_array($item) || is_object($item)) {
                return $this->error($field . ' must be an integer list.', ResponseCode::VALIDATION_FAILED);
            }

            $id = trim((string) $item);
            if ($id === '') {
                continue;
            }

            $validator = Validator::make([$field => $id], [
                $field => 'integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }
        }

        return null;
    }

    /**
     * 校验绑定主键必须为整数，避免字符串被强制转换后误删其他绑定。
     *
     * @param mixed $id 页面提交的 admin_agent_bindings 主键。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，通过时返回 null。
     */
    private function validateBindingId($id)
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
     * 将页面提交的 ID 集合转为去空、去重后的正整数数组。
     *
     * @param mixed $value 页面输入值，支持数组、英文逗号分隔字符串或空值。
     * @return array 正整数 ID 数组；非法值直接丢弃，让调用方得到明确的有效集合。
     */
    private function parseIdList($value)
    {
        $ids = [];

        foreach ($this->normalizeIdList($value) as $item) {
            $id = (int) trim((string) $item);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 把数组或英文逗号分隔字符串统一转为数组。
     *
     * @param mixed $value 页面输入值。
     * @return array 数组输入原样返回，其余按英文逗号切分。
     */
    private function normalizeIdList($value): array
    {
        return is_array($value) ? $value : explode(',', (string) $value);
    }
}
