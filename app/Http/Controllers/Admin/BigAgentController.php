<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:49
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\BigAgent;
use App\Models\UserInfo;
use App\Services\AdminDataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * 后台大代理管理控制器。
 *
 * 文件功能：
 * - 本控制器承载 admin_api_bigAgentList、admin_api_createBigAgent、admin_api_updateBigAgent、admin_api_deleteBigAgent。
 * - 数据来源为 big_agents 表，会影响前台大代理登录、下级代理集合和大代理账号启停。
 * - 页面按钮显隐来自 permissions.slug，接口最终仍由 check.permission:admin 按 permissions.api_route 做鉴权。
 */
class BigAgentController extends AdminBaseController
{
    /**
     * 后台数据范围服务：大代理的创建/编辑/删除都会触碰代理树归属（分配子代理、更换父级），
     * 每次写入前必须经它确认目标代理在当前管理员的可见数据范围内；
     * 缺失或被移除时，越权管理员可以把子代理挂到任意代理树下，破坏数据范围隔离。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 注入数据范围服务。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围校验服务,用于确认大代理分配的子代理在管理员可见范围内。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 获取大代理列表。
     *
     * 参数逻辑说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，兼容标准分页参数。
     * - limit 表示 Layui 表格每页数量，当前端未提交 per_page 时使用。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page、limit 等分页参数。
     * @return \Illuminate\Http\JsonResponse 大代理分页列表响应。
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', $request->input('limit', 15));

        $agents = BigAgent::query()->paginate($perPage, ['*'], 'page', $page);

        return $this->success($agents, __('admin.big_agents_fetched'));
    }

    /**
     * 创建大代理账号。
     *
     * 参数逻辑说明：
     * - username 表示大代理登录名，对应 big_agents.username，新增时必须唯一。
     * - password 表示大代理登录密码，新增时必填，写入前必须使用 Hash::make 加密。
     * - is_enabled 表示大代理账号是否启用，对应 big_agents.is_enabled；前台大代理登录会读取该字段。
     * - status 是旧页面历史字段，仅在 is_enabled 缺失时兼容映射，真实入库字段仍是 is_enabled。
     *
     * @param Request $request HTTP 请求对象，承载 username、password、is_enabled 等新增字段。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新大代理记录。
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($this->validationPayload($request), [
                'username' => 'required|string|max:50|unique:big_agents',
                'email' => 'required|email|max:191|unique:big_agents,email',
                'password' => 'required|string|min:6',
                'is_enabled' => 'sometimes|boolean',
                'status' => 'sometimes|boolean',
                'sub_agent_ids' => 'nullable|string|max:500|regex:/^(?:[1-9][0-9]*)(?:,[1-9][0-9]*)*$/',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            if ($assignmentError = $this->validateSubAgentAssignments($request)) {
                return $assignmentError;
            }

            $data = $this->normalizePayload($request, true);
            $data['password'] = Hash::make($data['password']);
            $data['created_by'] = $this->currentAdminName();
            $agent = DB::transaction(static function () use ($data): BigAgent {
                return BigAgent::create($data);
            });

            return $this->success($agent, __('admin.big_agent_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 更新大代理账号。
     *
     * 参数逻辑说明：
     * - id 表示 big_agents.id，用于定位需要编辑的大代理记录。
     * - username 表示大代理登录名，编辑时必须排除当前 id 后保持唯一。
     * - password 表示大代理登录密码；password 留空表示编辑时保留原密码，编辑时 password 留空表示保留原密码，不会覆盖 big_agents.password。
     * - is_enabled 表示大代理账号是否启用，必须与后台 Blade/JS 和前台登录逻辑保持一致。
     *
     * @param Request $request HTTP 请求对象，承载 username、password、is_enabled 等更新字段。
     * @param int $id 路由中的 big_agents.id。
     * @return \Illuminate\Http\JsonResponse 更新后的大代理记录。
     */
    public function update(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateBigAgentRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $agent = BigAgent::find($id);
            if (!$agent) {
                return $this->error(__('admin.big_agent_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($this->validationPayload($request), [
                'username' => 'required|string|max:50|unique:big_agents,username,' . $id,
                'email' => [
                    'sometimes',
                    'email',
                    'max:191',
                    Rule::unique('big_agents', 'email')->ignore($id),
                ],
                'password' => 'nullable|string|min:6',
                'is_enabled' => 'sometimes|boolean',
                'status' => 'sometimes|boolean',
                'sub_agent_ids' => 'nullable|string|max:500|regex:/^(?:[1-9][0-9]*)(?:,[1-9][0-9]*)*$/',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            if ($assignmentError = $this->validateSubAgentAssignments($request)) {
                return $assignmentError;
            }

            $data = $this->normalizePayload($request, false);
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->input('password'));
            }
            $data['created_by'] = $this->currentAdminName();

            $agent = DB::transaction(static function () use ($agent, $data): BigAgent {
                $agent->update($data);

                return $agent->fresh();
            });

            return $this->success($agent, __('admin.big_agent_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 删除大代理账号。
     *
     * 参数逻辑说明：
     * - id 表示大代理主键，对应 big_agents.id。
     * - 删除入口必须具备 admin_api_deleteBigAgent 对应权限配置，避免无权限管理员直接调用接口。
     *
     * @param int $id 路由中的 big_agents.id。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     */
    public function destroy($id)
    {
        try {
            if ($routeIdError = $this->validateBigAgentRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $agent = BigAgent::find($id);
            if (!$agent) {
                return $this->error(__('admin.big_agent_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $agent->delete();

            return $this->success([], __('admin.big_agent_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验大代理路由 ID，避免非严格数字字符串命中 big_agents.id。
     *
     * @param mixed $id 路由参数中的 big_agents.id。
     * @return \Illuminate\Http\JsonResponse|null ID 非法时返回统一错误响应，否则返回 null。
     */
    private function validateBigAgentRouteId($id)
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
     * normalizePayload 用于规范化大代理保存字段。
     *
     * 参数逻辑说明：
     * - $request：当前 HTTP 请求对象，承载后台表单字段。
     * - $includePassword：是否强制读取 password；新增时为 true，编辑时由 update() 单独判断是否填写。
     * - is_enabled 优先读取新字段；若旧页面仍提交 status，则兼容映射到 is_enabled。
     *
     * @param Request $request HTTP 请求对象。
     * @param bool $includePassword 是否包含 password 字段。
     * @return array<string, mixed> 可安全写入 big_agents 表的字段集合。
     */
    private function normalizePayload(Request $request, bool $includePassword): array
    {
        $subAgentIds = $request->input('sub_agent_ids', $request->input('agents', ''));
        if (is_array($subAgentIds)) {
            $subAgentIds = implode(',', array_values(array_filter(array_map(
                static fn ($id): string => trim((string) $id),
                $subAgentIds,
            ), static fn (string $id): bool => $id !== '')));
        } else {
            $subAgentIds = trim((string) $subAgentIds);
            if ($subAgentIds !== '') {
                $subAgentIds = (string) preg_replace('/\s*,\s*/', ',', $subAgentIds);
            }
        }

        $data = [
            'username' => $request->input('username'),
        ];

        if ($includePassword || $request->filled('email')) {
            $data['email'] = $request->input('email');
        }

        if ($includePassword || $request->has('sub_agent_ids') || $request->has('agents')) {
            $data['sub_agent_ids'] = $subAgentIds;
        }

        if ($includePassword || $request->has('is_enabled') || $request->has('status')) {
            $data['is_enabled'] = $request->has('is_enabled')
                ? ($request->boolean('is_enabled') ? 1 : 0)
                : ($request->boolean('status', true) ? 1 : 0);
        }

        if ($includePassword) {
            $data['password'] = $request->input('password');
        }

        return $data;
    }

    /**
     * 将旧字段 agents[] 与新字段 sub_agent_ids 归一化到同一个校验值。
     * 非数字项故意保留在结果中，让 regex 规则返回验证错误而不是静默丢弃。
     */
    private function validationPayload(Request $request): array
    {
        $payload = $request->all();
        $raw = array_key_exists('sub_agent_ids', $payload)
            ? $payload['sub_agent_ids']
            : ($payload['agents'] ?? '');

        $payload['sub_agent_ids'] = $this->normalizeSubAgentIds($raw);

        return $payload;
    }

    /**
     * 把新旧两种 sub_agent_ids 提交形态(逗号字符串或数组)归一化为逗号分隔字符串。
     *
     * 数组项逐个 trim 后拼接;非标量值返回 '__invalid__' 占位,让后续 regex 校验明确失败而非静默丢弃。
     *
     * @param mixed $value 请求中的 sub_agent_ids 或 agents 原始值。
     * @return string 归一化后的逗号分隔 ID 字符串。
     */
    private function normalizeSubAgentIds($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            return implode(',', array_map(
                static fn ($id): string => trim((string) $id),
                array_values($value),
            ));
        }

        if (!is_scalar($value)) {
            return '__invalid__';
        }

        return (string) preg_replace('/\s*,\s*/', ',', trim((string) $value));
    }

    /**
     * 校验大代理可管理根代理集合。
     *
     * 旧后台的复选框只列出有效根代理；这里在 API 边界再次校验，避免调用方绕过页面后写入
     * 不存在、普通客户、下级节点、禁用账号或超出当前管理员数据范围的 ID。
     */
    private function validateSubAgentAssignments(Request $request)
    {
        $raw = $request->has('sub_agent_ids')
            ? $request->input('sub_agent_ids')
            : $request->input('agents', '');
        $normalized = $this->normalizeSubAgentIds($raw);
        if ($normalized === '') {
            return null;
        }

        $ids = array_map('intval', explode(',', $normalized));
        $uniqueIds = array_values(array_unique($ids));
        if (count($ids) !== count($uniqueIds)) {
            return $this->invalidSubAgentAssignmentResponse();
        }

        $validIds = UserInfo::query()
            ->join('user_logins', 'user_logins.id', '=', 'user_infos.login_id')
            ->whereIn('user_infos.user_id', $uniqueIds)
            ->where('user_infos.account_type', 1)
            ->where('user_infos.parent_id', 0)
            ->whereIn('user_infos.auth_status', [0, 1, 2, 4])
            ->where('user_logins.account_type', 1)
            ->where('user_logins.is_enabled', 1)
            ->where('user_logins.is_cancelled', 0)
            ->whereNull('user_logins.deleted_at')
            ->pluck('user_infos.user_id')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->all();
        sort($validIds);
        $expectedIds = $uniqueIds;
        sort($expectedIds);
        if ($validIds !== $expectedIds) {
            return $this->invalidSubAgentAssignmentResponse();
        }

        $admin = $request->user('admin') ?: auth('admin')->user();
        if (!$admin) {
            return $this->invalidSubAgentAssignmentResponse();
        }

        foreach ($uniqueIds as $id) {
            if (!$this->adminDataScopeService->canAccessUser($admin, $id, 'agent')) {
                return $this->invalidSubAgentAssignmentResponse();
            }
        }

        return null;
    }

    /**
     * 统一返回子代理分配非法错误响应。
     *
     * @return \Illuminate\Http\JsonResponse 校验失败响应,提示子代理集合不合法。
     */
    private function invalidSubAgentAssignmentResponse()
    {
        return $this->error(
            __('admin.big_agent_sub_agent_ids_invalid'),
            ResponseCode::VALIDATION_FAILED
        );
    }

    /**
     * 返回当前后台操作者名称，写入 created_by 以保留旧后台的审计语义。
     * 请求体不接受 created_by，避免调用方伪造审计主体。
     */
    private function currentAdminName(): string
    {
        $admin = auth('admin')->user();

        return $admin ? (string) ($admin->username ?? $admin->email ?? '') : '';
    }
}
