<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\OperationLog;
use App\Services\AdminDataScopeService;
use App\Services\UserStatisticsService;
use App\Constants\ResponseCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * 后台代理管理控制器。
 *
 * 功能逻辑说明：
 * - 数据来源为 user_infos 表，account_type=1 表示代理账号，后台只展示和维护代理类型用户。
 * - login、level 关系分别用于补充代理登录账号资料和代理等级资料。
 * - AdminDataScopeService 用于限制不同管理员可查看的代理数据范围，避免普通管理员越权查看代理链路。
 * - 接口权限由 check.permission:admin 按 permissions.api_route 处理，数据权限由本控制器内的数据范围服务二次限制。
 *
 * 文件功能：
 * - 代理列表/导出/详情/下级关系/上级链路、等级与佣金更新、确认与拒绝代理、带统计的旧页面代理列表与直属客户列表。
 * - 输入 agent_id/user_name/page 等；输出分页列表、CSV 或旧格式 HTML 链路（parentPath 兼容旧 Layui 页面）。
 *
 * 适用场景：
 * - 后台"代理管理"页面及旧项目迁移页面；单代理操作前统一经 denyAgentAccessIfNeeded 做数据范围校验。
 *
 * 失败语义：
 * - 越权访问返回 PERMISSION_DENIED；记录不存在返回 USER_NOT_FOUND；未预期异常统一 serverErrorResponse()。
 */
class AgentController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * 参数逻辑说明：
     * - AdminDataScopeService 用于限制不同管理员可查看的代理数据范围。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 后台数据范围服务，用于按管理员角色配置限制代理列表可见范围。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 获取后台代理列表。
     *
     * 参数逻辑说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - agent_id 表示业务代理用户ID，筛选 user_infos.user_id，不是后台管理员 ID。
     * - user_name 表示代理姓名筛选关键字，按 user_infos.user_name 模糊匹配。
     * - account_type=1 表示代理账号，本列表固定只读取代理，不混入普通客户。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page、agent_id、user_name 和当前 admin guard 用户。
     * @return \Illuminate\Http\JsonResponse 代理分页列表响应。
     */
    public function index(Request $request)
    {
        if ($dateFilterError = $this->validateAgentDateFilter($request)) {
            return $dateFilterError;
        }

        if ($agentIdFilterError = $this->validateAgentIdFilter($request)) {
            return $agentIdFilterError;
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = $this->filteredAgentQuery($request);
        $agents = $query->orderByDesc('user_id')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($agents, __('admin.agent_list_fetched'));
    }

    /**
     * 导出当前筛选条件下的代理 CSV。
     *
     * 参数说明：
     * - agent_id 表示业务代理用户 ID，对应 user_infos.user_id。
     * - user_name 表示代理姓名关键字，对 user_infos.user_name 做模糊筛选。
     * - 导出仍复用 AdminDataScopeService，普通管理员只能导出自己可见的数据范围。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选条件和 admin guard 登录管理员。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse 代理 CSV 下载响应。
     */
    public function exportAgents(Request $request)
    {
        if ($dateFilterError = $this->validateAgentDateFilter($request)) {
            return $dateFilterError;
        }

        if ($agentIdFilterError = $this->validateAgentIdFilter($request)) {
            return $agentIdFilterError;
        }

        $rows = [[
            'user_id',
            'user_name',
            'email',
            'phone',
            'parent_id',
            'level_id',
            'comm_rate',
            'auth_status',
            'total_funds',
            'equity',
            'created_at',
        ]];

        $this->filteredAgentQuery($request)
            ->orderByDesc('user_id')
            ->limit(5000)
            ->get()
            ->each(function (UserInfo $agent) use (&$rows) {
                $rows[] = [
                    $agent->user_id,
                    $agent->user_name,
                    optional($agent->login)->email,
                    $agent->phone,
                    $agent->parent_id,
                    $agent->level_id,
                    $agent->comm_rate,
                    $agent->auth_status,
                    $agent->total_funds,
                    $agent->equity,
                    $agent->created_at,
                ];
            });

        return $this->csvDownload('agents_export.csv', $rows);
    }

    /**
     * 获取代理详情及其层级信息。
     *
     * 参数逻辑说明：
     * - agent_id 表示业务代理用户ID，可来自请求体；$agentId 为兼容后续 REST 路由保留的可选路径参数。
     * - account_type=1 表示代理账号，详情接口必须排除普通客户。
     * - login 表示代理登录资料关系，level 表示代理等级关系。
     * - denyAgentAccessIfNeeded 用于按当前管理员数据范围判断是否允许访问指定代理。
     *
     * @param Request $request HTTP 请求对象，承载 agent_id 和当前 admin guard 用户。
     * @param int|null $agentId 可选路径参数中的业务代理用户ID。
     * @return \Illuminate\Http\JsonResponse 代理详情响应。
     */
    public function show(Request $request, $agentId = null)
    {
        // 当前后台路由为 POST /agentDetail，默认从请求体读取 agent_id；保留可选路由参数用于兼容后续 REST 写法。
        $agentId = $agentId ?: $request->input('agent_id');
        $validator = Validator::make(['agent_id' => $agentId], [
            'agent_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $agentId = (int) $agentId;
        $agent = UserInfo::with(['login', 'level'])->where('user_id', $agentId)->where('account_type', 1)->first();
        if (!$agent) {
            return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $accessDenied = $this->denyAgentAccessIfNeeded($request, $agentId);
        if ($accessDenied) {
            return $accessDenied;
        }

        return $this->success($agent, __('admin.agent_detail_fetched'));
    }

    /**
     * 获取指定代理的直接和间接下级代理及客户。
     *
     * 参数逻辑说明：
     * - descendants 用于读取直接和间接下级代理及客户，返回代理链路内可见的下级集合。
     * - agent_id 表示业务代理用户ID，用于查询该代理名下的层级关系。
     * - AgentDescendant 表记录代理层级关系，agent_id 为上级代理，descendantInfo 为下级用户资料关系。
     * - 查询下级前必须先执行 denyAgentAccessIfNeeded，防止无数据范围权限的管理员越权展开代理树。
     *
     * @param Request $request HTTP 请求对象，承载 agent_id 和当前 admin guard 用户。
     * @param int|null $agentId 可选路径参数中的业务代理用户ID。
     * @return \Illuminate\Http\JsonResponse 代理下级关系响应。
     */
    public function descendants(Request $request, $agentId = null)
    {
        try {
            // agent_id 表示要查看下级关系的业务代理用户ID，不是后台管理员ID。
            $agentId = $agentId ?: $request->input('agent_id');
            $validator = Validator::make(['agent_id' => $agentId], [
                'agent_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $agentId = (int) $agentId;
            $accessDenied = $this->denyAgentAccessIfNeeded($request, $agentId);
            if ($accessDenied) {
                return $accessDenied;
            }

            $descendants = $this->normalizedAgentDescendantRows($agentId);

            return $this->success($descendants, __('admin.agent_descendants_fetched'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 返回指定用户在代理链中的上级链路（自顶向下）。
     *
     * 旧业务说明（项目1 Abstract_Basic_Controller@parentPathV2）：
     * - 旧后台订单页以 POST order/v2/parentPath 提交 user_id + event_name，
     *   从该用户沿 parent_id 向上追溯到根节点，再反转输出"根->…->目标"的 HTML 链路。
     * - 旧响应体为 {code:200, msg:SUCCESS, data:{path, tree}}，tree 为带 lay-event
     *   与分等级颜色的 <span> 片段，path 为 '->' 拼接串。
     *
     * 参数逻辑说明：
     * - user_id 表示目标业务用户 ID，兼容旧字段 userId。
     * - event_name 表示旧页面点击回调名，原样注入 span 的 lay-event 属性。
     * - 链路优先使用 family_tree 快照，缺失时沿 parent_id 向上回溯（上限 100 层）。
     * - 数据范围按目标账号类型解析：account_type=1 走代理树，其余按客户树校验。
     *
     * @param Request $request 当前后台请求，承载 user_id/event_name。
     * @return \Illuminate\Http\JsonResponse 旧格式链路响应，保持旧 Layui 页面零改造可用。
     */
    public function parentPath(Request $request)
    {
        try {
            $targetUserId = (int) $request->input('user_id', $request->input('userId'));
            $eventName = trim((string) $request->input('event_name', 'returnPreLevel'));

            $validator = Validator::make(['user_id' => $targetUserId], [
                'user_id' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $target = UserInfo::where('user_id', $targetUserId)->first();
            if (!$target) {
                return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $admin = $request->user('admin');
            if ($admin && !$this->adminDataScopeService->canAccessUser(
                $admin,
                $targetUserId,
                (int) $target->account_type === 1 ? 'agent' : 'user'
            )) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $ids = $this->parentPathUserIds($target);
            $users = UserInfo::whereIn('user_id', $ids)
                ->get()
                ->keyBy('user_id');

            $tree = [];
            foreach ($ids as $id) {
                $user = $users->get($id);
                if (!$user) {
                    continue;
                }

                $color = $this->legacyParentPathGroupColor((int) $user->group_id);
                $tree[] = '<span lay-event="' . e($eventName) . '" style="cursor:pointer;color:' . $color . '; width:100%;" data-user_id="' . (int) $user->user_id . '">' . e($user->user_name) . '[' . (int) $user->user_id . ']' . '</span>';
            }

            return response()->json([
                'code' => 200,
                'msg' => 'SUCCESS',
                'data' => [
                    'path' => implode('->', $tree),
                    'tree' => $tree,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 组装目标用户的上级链路 ID（自顶向下）。
     *
     * 参数逻辑说明：
     * - $target 表示链路目标用户；family_tree 为平台维护的祖先快照，格式如
     *   "1,2,7"（根在前），缺失时才逐级回查 parent_id。
     *
     * @param UserInfo $target 链路目标用户资料。
     * @return array<int, int> 从根节点到目标用户的业务用户 ID 链。
     */
    private function parentPathUserIds(UserInfo $target): array
    {
        return $this->walkParentChainIds($target);
    }

    /**
     * 沿 parent_id 向上回溯祖先（无 family_tree 快照时的兜底）。
     *
     * 参数逻辑说明：
     * - $current 表示当前节点；guard 限制最多回溯 100 层，防止脏数据造成死循环。
     *
     * @param UserInfo $target 起始用户资料。
     * @return array<int, int> 从根节点到起始用户的业务用户 ID 链。
     */
    private function walkParentChainIds(UserInfo $target): array
    {
        $ancestorIds = $target->getAncestorIds();
        if ((int) $target->parent_id > 0 && $ancestorIds === []) {
            return [];
        }

        return array_merge($ancestorIds, [(int) $target->user_id]);
    }

    /**
     * 返回旧页面按分组等级渲染的链路颜色。
     *
     * 参数逻辑说明：
     * - $groupId 表示 user_infos.group_id；映射与项目1 parentPathV2 一致：
     *   1 金(高)、2 金(次高)、3 金(中高)、4 铜(中低)、7 石墨黑(低)，其余浅灰。
     *
     * @param int $groupId 用户分组 ID。
     * @return string 旧页面使用的十六进制颜色值。
     */
    private function legacyParentPathGroupColor(int $groupId): string
    {
        $colors = [
            1 => '#FFD700',
            2 => '#E8B923',
            3 => '#D4A017',
            4 => '#A68A00',
            7 => '#6B7280',
        ];

        return $colors[$groupId] ?? '#9CA3AF';
    }

    /**
     * 更新代理等级。
     *
     * 参数逻辑说明：
     * - agent_id 表示业务代理用户ID，默认从请求体读取。
     * - level 表示代理等级，会写入 user_infos.level_id，影响后台代理展示和前台代理等级相关业务。
     * - account_type=1 表示代理账号，普通客户不能通过该接口更新代理等级。
     * - 更新前必须先通过 denyAgentAccessIfNeeded 校验当前管理员是否能管理该代理。
     *
     * @param Request $request HTTP 请求对象，承载 agent_id、level 和当前 admin guard 用户。
     * @param int|null $agentId 可选路径参数中的业务代理用户ID。
     * @return \Illuminate\Http\JsonResponse 代理等级更新响应。
     */
    public function updateLevel(Request $request, $agentId = null)
    {
        try {
            // 当前后台路由为 POST /updateAgentLevel，默认从请求体读取 agent_id。
            $agentId = $agentId ?: $request->input('agent_id');
            $validator = Validator::make([
                'agent_id' => $agentId,
                'level' => $request->input('level'),
            ], [
                'agent_id' => 'required|integer',
                'level' => 'required|integer|exists:agent_levels,id',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $agentId = (int) $agentId;
            $agent = UserInfo::where('user_id', $agentId)->where('account_type', 1)->first();
            if (!$agent) {
                return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $accessDenied = $this->denyAgentAccessIfNeeded($request, $agentId);
            if ($accessDenied) {
                return $accessDenied;
            }

            $agent->update(['level_id' => $request->level]);

            return $this->success([], __('admin.agent_level_updated'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 更新代理佣金比例。
     *
     * 参数逻辑说明：
     * - agent_id 表示业务代理用户ID，默认从请求体读取。
     * - comm_rate 表示代理佣金比例，会写入 user_infos.comm_rate，取值范围为 0 到 100 的百分数口径：
     *   与 user_infos.comm_rate 整数列、agent_levels.max_commission（85/85）、佣金引擎
     *   CommissionService 的 /100 计算以及旧后台验证（max:100）四方一致；0..1 分数口径会让
     *   佣金计算与建档继承全部失真，属于历史缺陷，2026-08-29 统一修正。
     * - account_type=1 表示代理账号，普通客户不能通过该接口更新返佣比例。
     * - 更新前必须先通过 denyAgentAccessIfNeeded 校验当前管理员是否能管理该代理。
     *
     * @param Request $request HTTP 请求对象，承载 agent_id、comm_rate 和当前 admin guard 用户。
     * @param int|null $agentId 可选路径参数中的业务代理用户ID。
     * @return \Illuminate\Http\JsonResponse 代理佣金比例更新响应。
     */
    public function updateCommission(Request $request, $agentId = null)
    {
        try {
            // 当前后台路由为 POST /updateAgentCommission，默认从请求体读取 agent_id。
            $agentId = $agentId ?: $request->input('agent_id');
            $validator = Validator::make([
                'agent_id' => $agentId,
                'comm_rate' => $request->input('comm_rate'),
            ], [
                'agent_id' => 'required|integer',
                'comm_rate' => 'required|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $agentId = (int) $agentId;
            $agent = UserInfo::where('user_id', $agentId)->where('account_type', 1)->first();
            if (!$agent) {
                return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $accessDenied = $this->denyAgentAccessIfNeeded($request, $agentId);
            if ($accessDenied) {
                return $accessDenied;
            }

            $agent->update(['comm_rate' => $request->comm_rate]);

            return $this->success([], __('admin.agent_commission_updated'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 确认代理等级状态。
     *
     * @param Request $request 当前后台请求，读取 agent_id 和 admin guard 用户。
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmAgent(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $agent = UserInfo::where('user_id', $request->input('agent_id'))->where('account_type', 1)->first();
            if (!$agent) {
                return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $accessDenied = $this->denyAgentAccessIfNeeded($request, $agent->user_id);
            if ($accessDenied) {
                return $accessDenied;
            }

            DB::transaction(function () use ($request, $agent) {
                $beforeConfirmed = (int) $agent->is_agent_confirmed;
                $agent->update([
                    'is_agent_confirmed' => 1,
                    'remark' => '',
                ]);
                $this->writeAgentConfirmationOperationLog($request, $agent, 'confirm', $beforeConfirmed, 1);
            });

            return $this->success([], __('admin.agent_confirmed'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 拒绝代理等级确认并保存原因。
     *
     * @param Request $request 当前后台请求，读取 agent_id、reason 和 admin guard 用户。
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectAgentConfirmation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_id' => 'required|integer',
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $agent = UserInfo::where('user_id', $request->input('agent_id'))->where('account_type', 1)->first();
            if (!$agent) {
                return $this->error(__('admin.agent_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $accessDenied = $this->denyAgentAccessIfNeeded($request, $agent->user_id);
            if ($accessDenied) {
                return $accessDenied;
            }

            $reason = trim((string) $request->input('reason'));

            DB::transaction(function () use ($request, $agent, $reason) {
                $beforeConfirmed = (int) $agent->is_agent_confirmed;
                $agent->update([
                    'is_agent_confirmed' => 0,
                    'remark' => $reason,
                ]);
                $this->writeAgentConfirmationOperationLog($request, $agent, 'reject', $beforeConfirmed, 0, $reason);
            });

            return $this->success([], __('admin.agent_confirmation_rejected'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * denyAgentAccessIfNeeded 用于按当前管理员数据范围判断是否允许访问指定代理。
     *
     * 参数逻辑说明：
     * - $request 表示当前 HTTP 请求对象，用于读取 admin guard 下的登录管理员。
     * - $agentId 表示业务代理用户ID，不是后台管理员 ID。
     * - canAccessUser 返回 false 时直接返回权限不足响应，避免后续详情、下级、等级或佣金逻辑继续执行。
     *
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @param int|string $agentId 业务代理用户ID，不是后台管理员ID。
     * @return \Illuminate\Http\JsonResponse|null 返回 JsonResponse 表示拒绝访问；返回 null 表示允许继续执行业务逻辑。
     */
    private function denyAgentAccessIfNeeded(Request $request, $agentId)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return null;
        }

        if ($this->adminDataScopeService->canAccessUser($admin, $agentId, 'agent')) {
            return null;
        }

        return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
    }

    /**
     * 写入后台代理确认审核日志。
     *
     * @param Request $request 当前后台请求。
     * @param UserInfo $agent 被审核的代理资料。
     * @param string $action 审核动作，confirm=确认，reject=拒绝。
     * @param int $beforeConfirmed 审核前确认状态。
     * @param int $afterConfirmed 审核后确认状态。
     * @param string $reason 拒绝原因。
     * @return void
     */
    private function writeAgentConfirmationOperationLog(
        Request $request,
        UserInfo $agent,
        string $action,
        int $beforeConfirmed,
        int $afterConfirmed,
        string $reason = ''
    ): void {
        $admin = $request->user('admin');
        $prefix = $action === 'reject' ? 'Reject agent confirmation' : 'Confirm agent';
        $content = sprintf(
            '%s user_id:%s; is_agent_confirmed:%s->%s',
            $prefix,
            (int) $agent->user_id,
            $beforeConfirmed,
            $afterConfirmed
        );

        if ($reason !== '') {
            $content .= '; reason:' . $reason;
        }

        OperationLog::create([
            'admin_id' => $admin ? (int) $admin->id : 0,
            'admin_name' => $admin ? (string) $admin->username : '',
            'target_user_id' => (int) $agent->user_id,
            'order_no' => 'agent_confirmation:' . $agent->user_id,
            'content' => $content,
            'ip' => $request->ip() ?: '',
            'action_type' => 0,
        ]);
    }

    /**
     * 组装代理下级关系行（agent_descendants 闭包表 + parent 树兜底）。
     *
     * 数据来源：
     * - 闭包表记录直接/间接下级；parent 树实时展开补充闭包表缺失的新增关系，合并后按 depth 去重排序。
     *
     * @param int $agentId 业务代理用户 ID。
     * @return Collection<int, array<string, mixed>> 下级关系行集合。
     */
    private function normalizedAgentDescendantRows(int $agentId): Collection
    {
        $parentTreeRows = $this->parentTreeDescendantRows($agentId);
        $rows = $parentTreeRows->values();
        $users = UserInfo::whereIn('user_id', $rows->pluck('descendant_id')->all())
            ->get()
            ->keyBy('user_id');

        return $rows->map(function (array $row) use ($users) {
            $user = $users->get($row['descendant_id']);

            return $this->formatAgentDescendantRow($row, $user);
        })->sortBy([
            ['depth', 'asc'],
            ['user_id', 'asc'],
        ])->values();
    }

    /**
     * 沿 user_infos.parent_id 实时展开代理下级树（闭包表缺失时的兜底）。
     *
     * 只把代理节点（account_type=1）继续作为父节点展开，客户作为叶子收集；visited 防止脏数据造成循环。
     *
     * @param int $agentId 业务代理用户 ID。
     * @return Collection<int, array<string, mixed>> 下级关系行集合。
     */
    private function parentTreeDescendantRows(int $agentId): Collection
    {
        if (!UserInfo::where('user_id', $agentId)->where('account_type', 1)->exists()) {
            return collect();
        }

        $rows = collect();
        $frontier = collect([['parent_id' => $agentId, 'depth' => 1]]);
        $visited = [$agentId => true];

        while ($frontier->isNotEmpty()) {
            $depthByParent = $frontier->pluck('depth', 'parent_id')->all();
            $children = UserInfo::whereIn('parent_id', array_keys($depthByParent))
                ->whereIn('account_type', [1, 2])
                ->orderBy('parent_id')
                ->orderBy('user_id')
                ->get();
            $next = [];

            foreach ($children as $child) {
                $userId = (int) $child->user_id;
                if (isset($visited[$userId])) {
                    return collect();
                }

                $parentId = (int) $child->parent_id;
                $depth = (int) ($depthByParent[$parentId] ?? 1);
                if ($depth > UserInfo::MAX_HIERARCHY_DEPTH) {
                    return collect();
                }
                $visited[$userId] = true;

                $rows->push([
                    'agent_id' => $agentId,
                    'descendant_id' => $userId,
                    'descendant_type' => (int) $child->account_type,
                    'is_direct' => $parentId === $agentId ? 1 : 0,
                    'depth' => $depth,
                ]);

                if ((int) $child->account_type === 1) {
                    $next[] = ['parent_id' => $userId, 'depth' => $depth + 1];
                }
            }

            $frontier = collect($next);
        }

        return $rows;
    }

    /**
     * 格式化代理下级行，合并闭包字段与实时用户资料。
     *
     * @param array<string, mixed> $row 闭包表或 parent 树产出的下级行。
     * @param UserInfo|null $user 对应的业务用户资料；缺失时仅保留闭包字段。
     * @return array<string, mixed> 兼容旧页面的下级行结构（含 descendant_info 冗余字段）。
     */
    private function formatAgentDescendantRow(array $row, UserInfo $user = null): array
    {
        $descendantId = (int) $row['descendant_id'];
        $accountType = $user ? (int) $user->account_type : (int) $row['descendant_type'];

        return [
            'id' => $row['id'] ?? null,
            'agent_id' => (int) $row['agent_id'],
            'descendant_id' => $descendantId,
            'descendant_type' => (int) $row['descendant_type'],
            'is_direct' => (int) $row['is_direct'],
            'depth' => (int) $row['depth'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'user_id' => $descendantId,
            'user_name' => $user ? $user->user_name : '',
            'account_type' => $accountType,
            'parent_id' => $user ? (int) $user->parent_id : null,
            'phone' => $user ? $user->phone : null,
            'level_id' => $user ? $user->level_id : null,
            'comm_rate' => $user ? $user->comm_rate : null,
            'auth_status' => $user ? $user->auth_status : null,
            'descendant' => $user,
            'descendant_info' => $user,
        ];
    }

    /**
     * 构建已套用数据范围和页面筛选条件的代理查询。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return Builder 代理资料查询对象。
     */
    private function filteredAgentQuery(Request $request): Builder
    {
        // 需求 14：代理管理不展示登录历史，因此列表预加载只取导出与展示真正需要的登录字段，
        // 不再把 last_login_ip / last_login_at 等登录轨迹带进响应体。
        // 必须保留主键 id：belongsTo(login_id) 的预加载需要用它做关联匹配。
        $query = UserInfo::query()->where('account_type', 1)->with([
            'login' => static function ($relation): void {
                $relation->select(['id', 'user_id', 'email', 'account_type', 'is_enabled']);
            },
        ]);

        if ($request->user('admin')) {
            $query = $this->adminDataScopeService->apply($query, $request->user('admin'), 'agent', 'user_id');
        }

        if ($request->filled('agent_id')) {
            $query->where('user_id', (int) $request->input('agent_id'));
        }

        if ($request->filled('user_name')) {
            $query->where('user_name', 'LIKE', '%' . $request->input('user_name') . '%');
        }

        $this->applyAgentCreatedAtDateFilter($query, $request);

        return $query;
    }

    /**
     * 校验代理列表、导出和统计共用的日期筛选参数。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 start_date 和 end_date。
     * @return \Illuminate\Http\JsonResponse|null 日期非法时返回统一错误响应，否则返回 null。
     */
    private function validateAgentDateFilter(Request $request)
    {
        $validator = Validator::make($request->only(['start_date', 'end_date']), [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验代理列表和导出共用的代理 ID 筛选参数。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 agent_id。
     * @return \Illuminate\Http\JsonResponse|null 代理 ID 非法时返回统一错误响应，否则返回 null。
     */
    private function validateAgentIdFilter(Request $request)
    {
        if (!$request->filled('agent_id')) {
            return null;
        }

        $validator = Validator::make(['agent_id' => $request->input('agent_id')], [
            'agent_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 按代理创建时间应用日期范围筛选。
     *
     * @param Builder $query 代理查询构造器，目标表为 user_infos。
     * @param Request $request 当前 HTTP 请求对象，读取 start_date 和 end_date。
     * @return void
     */
    private function applyAgentCreatedAtDateFilter(Builder $query, Request $request): void
    {
        if ($request->filled('start_date')) {
            $query->where('user_infos.created_at', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('user_infos.created_at', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 输出 CSV 下载响应。
     *
     * @param string $fileName 下载文件名。
     * @param array<int, array<int, mixed>> $rows CSV 行数据。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function csvDownload(string $fileName, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 获取带统计的代理列表（从旧项目AgentControllerV3迁移）。
     *
     * listWithStats() 参数说明：
     * - page：当前页码，默认第1页。
     * - per_page/limit：每页数量，兼容Layui的limit参数，默认15条。
     * - user_id：代理ID，用于精确查询或查询直属下级代理。
     * - user_name：代理姓名，模糊匹配。
     * - user_status：代理状态筛选。
     * - trans_mode：交易模式筛选。
     * - start_date：统计开始日期。
     * - end_date：统计结束日期。
     * - form：是否表单提交查询，1=表单查询，空=默认查询。
     *
     * 功能逻辑说明：
     * - 查询代理基础信息（user_infos表，account_type=1）。
     * - 统计每个代理的返佣、入金、出金数据。
     * - 统计每个代理的直属代理数量和直属客户数量。
     * - 统计每个代理下级的交易数据汇总。
     * - 返回当前页数据 + 汇总统计。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回代理列表及统计数据。
     */
    public function listWithStats(Request $request)
    {
        try {
            // 读取分页参数，per_page 与 Layui 的 limit 双兼容。
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            // 读取筛选条件；form=1 表示表单查询，否则按默认的上下级关系口径查询。
            $userId = $request->input('user_id');
            $userName = $request->input('user_name');
            $userStatus = $request->input('user_status');
            $transMode = $request->input('trans_mode');
            $isFormSubmit = $request->input('form'); // 是否表单提交查询
            $currentAdmin = $request->user('admin');

            if ($dateFilterError = $this->validateAgentDateFilter($request)) {
                return $dateFilterError;
            }

            if ($request->filled('user_id')) {
                $validator = Validator::make(['user_id' => $userId], [
                    'user_id' => 'integer',
                ]);

                if ($validator->fails()) {
                    return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
                }

                $userId = (int) $userId;
            }

            // 只查询有效代理账号，并套用管理员数据范围。
            $query = UserInfo::query()
                ->select([
                    'user_infos.user_id',
                    'user_infos.user_name',
                    'user_infos.parent_id',
                    'user_infos.level_id as group_id',
                    'user_infos.trading_mode as trans_mode',
                    'user_infos.mt4_group as mt4_grp',
                    'user_infos.comm_rate as rights',
                    'user_infos.total_funds as BALANCE',
                    'user_infos.equity as EQUITY',
                    'user_infos.auth_status as user_status',
                    'user_infos.created_at as REGDATE',
                ])
                ->where('user_infos.account_type', 1) // 只查询代理账号
                ->whereIn('user_infos.auth_status', [0, 1, 2, 4]); // 有效代理

            if ($currentAdmin) {
                $query = $this->adminDataScopeService->apply(
                    $query,
                    $currentAdmin,
                    'agent',
                    'user_infos.user_id'
                );
            }

            // 应用筛选条件：表单查询按关键字筛选；默认查询首次显示一级代理（parent_id=0），其余显示指定代理的直属下级。
            if ($isFormSubmit) {
                // 表单提交查询
                if ($userId) {
                    $query->where('user_infos.user_id', $userId);
                }
                if ($userName) {
                    $query->where('user_infos.user_name', 'like', '%' . $userName . '%');
                }
                if ($userStatus && in_array($userStatus, [1, 2])) {
                    $actualStatus = ($userStatus == 2) ? 0 : 1;
                    $query->where('user_infos.auth_status', $actualStatus);
                }
                if ($transMode && in_array($transMode, [1, 2])) {
                    $actualMode = ($transMode == 2) ? 0 : 1;
                    $query->where('user_infos.trading_mode', $actualMode);
                }
            } else {
                // 默认查询逻辑
                if (empty($userId)) {
                    // 第一次进入，显示一级代理（group_id=1或parent_id=0）
                    $query->where('user_infos.parent_id', 0);
                } else {
                    // 查询指定代理的直属下级
                    $query->where('user_infos.parent_id', $userId);
                }
            }

            $this->applyAgentCreatedAtDateFilter($query, $request);

            // 执行分页查询并按创建时间倒序。
            $agents = $query->orderByDesc('user_infos.created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            // 空结果提前返回，避免后续统计空转。
            $agentIds = collect($agents->items())->pluck('user_id')->toArray();

            if (empty($agentIds)) {
                return $this->success([
                    'data' => [],
                    'count' => 0,
                    'totalRow' => $this->getEmptyTotalRow(),
                ], __('admin.agent_list_fetched'));
            }

            // 统计每个代理的直属代理数量；与列表查询共用同一数据范围口径。
            $directAgentCounts = [];
            foreach ($agentIds as $agentId) {
                $directAgentQuery = UserInfo::where('parent_id', $agentId)
                    ->where('account_type', 1)
                    ->whereIn('auth_status', [0, 1, 2, 4]);

                if ($currentAdmin) {
                    $directAgentQuery = $this->adminDataScopeService->apply(
                        $directAgentQuery,
                        $currentAdmin,
                        'agent',
                        'user_infos.user_id'
                    );
                }

                $directAgentCounts[$agentId] = $directAgentQuery->count();
            }

            // 统计每个代理的直属客户数量；同样套用数据范围。
            $directCustomerCounts = [];
            foreach ($agentIds as $agentId) {
                $directCustomerQuery = UserInfo::where('parent_id', $agentId)
                    ->where('account_type', 2)
                    ->whereIn('auth_status', [0, 1, 2, 4]);

                if ($currentAdmin) {
                    $directCustomerQuery = $this->adminDataScopeService->apply(
                        $directCustomerQuery,
                        $currentAdmin,
                        'user',
                        'user_infos.user_id'
                    );
                }

                $directCustomerCounts[$agentId] = $directCustomerQuery->count();
            }

            // 统计每个代理的返佣、入金、出金：按 cmd=6 流水与 comment 关键词识别口径与旧项目一致。
            $agentFinanceStats = [];
            foreach ($agentIds as $agentId) {
                $stats = DB::table('user_trades')
                    ->selectRaw("
                        -- 返佣：CMD=6且备注包含返佣关键词
                        SUM(CASE
                            WHEN cmd = 6 AND (comment LIKE '%-FY' OR comment LIKE '%返佣%' OR comment LIKE '%commission%')
                            THEN profit ELSE 0
                        END) as total_fy,

                        -- 入金：CMD=6，PROFIT>0，不包含返佣关键词
                        SUM(CASE
                            WHEN profit > 0 AND cmd = 6
                            AND comment NOT LIKE '%-FY'
                            AND comment NOT LIKE '%返佣%'
                            AND comment NOT LIKE '%commission%'
                            AND (comment LIKE '%Deposit%' OR comment LIKE '%入金%')
                            THEN profit ELSE 0
                        END) as total_rj,

                        -- 出金：CMD=6，PROFIT<0
                        SUM(CASE
                            WHEN profit < 0 AND cmd = 6
                            THEN profit ELSE 0
                        END) as total_qk
                    ")
                    ->where('user_id', $agentId)
                    ->first();

                $agentFinanceStats[$agentId] = [
                    'total_fy' => number_format($stats->total_fy ?? 0, 2, '.', ''),
                    'total_rj' => number_format($stats->total_rj ?? 0, 2, '.', ''),
                    'total_qk' => number_format($stats->total_qk ?? 0, 2, '.', ''),
                ];
            }

            // 合并代理基础信息与统计结果，输出旧页面字段名（BALANCE/EQUITY/mun 等）。
            $agentsData = collect($agents->items())->map(function ($agent) use ($directAgentCounts, $directCustomerCounts, $agentFinanceStats) {
                $agentId = $agent->user_id;

                return [
                    'user_id' => $agent->user_id,
                    'user_name' => $agent->user_name,
                    'parent_id' => $agent->parent_id,
                    'group_id' => $agent->group_id,
                    'trans_mode' => $agent->trans_mode,
                    'mt4_grp' => $agent->mt4_grp,
                    'rights' => $agent->rights,
                    'user_status' => $agent->user_status,
                    'BALANCE' => number_format($agent->BALANCE, 2, '.', ''),
                    'EQUITY' => number_format($agent->EQUITY, 2, '.', ''),
                    'REGDATE' => date('Y-m-d H:i:s', $agent->REGDATE),
                    'fy_money' => $agentFinanceStats[$agentId]['total_fy'] ?? '0.00',
                    'rj_money' => $agentFinanceStats[$agentId]['total_rj'] ?? '0.00',
                    'qk_money' => $agentFinanceStats[$agentId]['total_qk'] ?? '0.00',
                    'mun' => $directAgentCounts[$agentId] ?? 0, // 直属代理数
                    'user_mun' => $directCustomerCounts[$agentId] ?? 0, // 直属客户数
                    'money' => [
                        'total_fy' => $agentFinanceStats[$agentId]['total_fy'] ?? '0.00',
                        'total_rj' => $agentFinanceStats[$agentId]['total_rj'] ?? '0.00',
                        'total_qk' => $agentFinanceStats[$agentId]['total_qk'] ?? '0.00',
                    ],
                ];
            })->toArray();

            // 汇总当前页所有代理的余额、净值与资金统计，供前端 footer 展示。
            $totalBalance = 0;
            $totalEquity = 0;
            $totalFy = 0;
            $totalRj = 0;
            $totalQk = 0;

            foreach ($agentsData as $agent) {
                $totalBalance += (float) str_replace(',', '', $agent['BALANCE']);
                $totalEquity += (float) str_replace(',', '', $agent['EQUITY']);
                $totalFy += (float) $agent['money']['total_fy'];
                $totalRj += (float) $agent['money']['total_rj'];
                $totalQk += (float) $agent['money']['total_qk'];
            }

            $totalRow = [
                'user_id' => trans('systemlanguage.total'),
                'user_name' => '',
                'BALANCE' => number_format($totalBalance, 2, '.', ''),
                'EQUITY' => number_format($totalEquity, 2, '.', ''),
                'total_fy_zong' => number_format($totalFy, 2, '.', ''),
                'total_rj_zong' => number_format($totalRj, 2, '.', ''),
                'total_qk_zong' => number_format($totalQk, 2, '.', ''),
                'fy_money' => number_format($totalFy, 2, '.', ''),
                'rj_money' => number_format($totalRj, 2, '.', ''),
                'qk_money' => number_format($totalQk, 2, '.', ''),
                'all_total_fy' => number_format($totalFy, 2, '.', ''),
                'all_total_rj' => number_format($totalRj, 2, '.', ''),
                'all_total_qk' => number_format($totalQk, 2, '.', ''),
                'mun_s' => count($agentsData),
            ];

            // 返回列表、总数与汇总行。
            return $this->success([
                'data' => $agentsData,
                'count' => $agents->total(),
                'totalRow' => $totalRow,
            ], __('admin.agent_list_fetched'));

        } catch (\Exception $e) {
            \Log::error('AgentController.listWithStats error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取代理的直属客户列表（从旧项目CustomerList方法迁移）。
     *
     * customerList() 参数说明：
     * - agent_id：代理ID，查询该代理的直属客户。
     *
     * 功能逻辑说明：
     * - 查询指定代理的直属客户列表（parent_id = agent_id，account_type=2）。
     * - 统计每个客户的入金、出金、手续费、盈亏、交易量等数据。
     * - 统计按品种分类的交易量（贵金属、外汇、原油、指数）。
     * - 返回客户列表 + 汇总统计。
     *
     * @param Request $request 当前HTTP请求对象。
     * @param int|null $agentId 代理ID。
     * @return \Illuminate\Http\JsonResponse 返回客户列表及统计数据。
     */
    public function customerList(Request $request, $agentId = null)
    {
        try {
            $agentId = $agentId ?: $request->input('agent_id');

            if (!$agentId) {
                return $this->error('代理ID不能为空', ResponseCode::VALIDATION_FAILED);
            }

            // 查询指定代理的直属普通客户（parent_id=agent_id 且 account_type=2），只保留有效账号。
            $customers = UserInfo::query()
                ->select([
                    'user_infos.user_id',
                    'user_infos.user_name',
                    'user_infos.mt4_group as mt4_grp',
                    'user_infos.total_funds as BALANCE',
                    'user_infos.equity as EQUITY',
                    'user_infos.created_at as REGDATE',
                ])
                ->where('user_infos.parent_id', $agentId)
                ->where('user_infos.account_type', 2) // 只查询普通客户
                ->whereIn('user_infos.auth_status', [0, 1, 2, 4])
                ->orderByDesc('user_infos.created_at')
                ->get();

            if ($customers->isEmpty()) {
                return $this->success([
                    'data' => [],
                    'totalRow' => $this->getEmptyCustomerTotalRow(),
                ], '查询成功');
            }

            // 用 UserStatisticsService 批量补齐每个客户的交易统计字段。
            $statisticsService = new UserStatisticsService();
            $customerIds = $customers->pluck('user_id')->toArray();
            $customerStatistics = $statisticsService->getBatchUserStatistics($customerIds);

            // 合并客户基础信息与统计字段。
            $customersData = $customers->map(function ($customer) use ($customerStatistics) {
                $customerId = $customer->user_id;
                $stats = $customerStatistics[$customerId] ?? [];

                return array_merge([
                    'user_id' => $customer->user_id,
                    'user_name' => $customer->user_name,
                    'mt4_grp' => $customer->mt4_grp,
                    'BALANCE' => number_format($customer->BALANCE, 2, '.', ''),
                    'EQUITY' => number_format($customer->EQUITY, 2, '.', ''),
                    'REGDATE' => date('Y-m-d H:i:s', $customer->REGDATE),
                ], $stats);
            })->toArray();

            // 汇总客户资金与交易统计，供前端 footer 展示。
            $summaryStats = $statisticsService->getSummaryStatistics($customerIds);

            $totalRow = [
                'user_id' => trans('systemlanguage.total'),
                'BALANCE' => $summaryStats['search_total_bal'],
                'EQUITY' => $summaryStats['search_total_eqy'],
                'total_rj_zong' => $summaryStats['search_total_yuerj'],
                'total_qk_zong' => $summaryStats['search_total_yuecj'],
                'all_total_comm_zong' => $summaryStats['search_total_comm'],
                'all_total_profit_zong' => $summaryStats['search_total_profit'],
                'all_total_noble_metal_zong' => $summaryStats['search_total_noble_metal'],
                'all_total_for_exca_zong' => $summaryStats['search_total_for_exca'],
                'all_total_crud_oil_zong' => $summaryStats['search_total_crud_oil'],
                'all_total_index_zong' => $summaryStats['search_total_index'],
                'all_total_volume_zong' => $summaryStats['search_total_volume'],
                'all_total_swaps_zong' => $summaryStats['search_total_swaps'],
            ];

            return $this->success([
                'data' => $customersData,
                'totalRow' => $totalRow,
            ], '查询成功');

        } catch (\Exception $e) {
            \Log::error('AgentController.customerList error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取空的汇总行数据结构。
     *
     * @return array 空汇总行数据。
     */
    private function getEmptyTotalRow(): array
    {
        return [
            'user_id' => trans('systemlanguage.total'),
            'user_name' => '',
            'BALANCE' => '0.00',
            'EQUITY' => '0.00',
            'total_fy_zong' => '0.00',
            'total_rj_zong' => '0.00',
            'total_qk_zong' => '0.00',
            'mun_s' => 0,
        ];
    }

    /**
     * 获取空的客户汇总行数据结构。
     *
     * @return array 空客户汇总行数据。
     */
    private function getEmptyCustomerTotalRow(): array
    {
        return [
            'user_id' => trans('systemlanguage.total'),
            'BALANCE' => '0.00',
            'EQUITY' => '0.00',
            'total_rj_zong' => '0.00',
            'total_qk_zong' => '0.00',
            'all_total_comm_zong' => '0.00',
            'all_total_profit_zong' => '0.00',
            'all_total_noble_metal_zong' => 0,
            'all_total_for_exca_zong' => 0,
            'all_total_crud_oil_zong' => 0,
            'all_total_index_zong' => 0,
            'all_total_volume_zong' => 0,
            'all_total_swaps_zong' => '0.00',
        ];
    }
}
