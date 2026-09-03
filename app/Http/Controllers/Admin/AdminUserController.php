<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\OperationLog;
use App\Models\GroupConfig;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserTrade;
use App\Services\AdminAuthReviewProcessor;
use App\Services\AdminDataScopeService;
use App\Services\FamilyTreeService;
use App\Services\Mt4ManagerService;
use App\Services\UserPasswordService;
use App\Services\UserStatisticsService;
use App\Support\AuthReviewTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 后台用户管理控制器。
 *
 * 功能逻辑说明：
 * - 负责后台用户列表、用户导出、用户详情、实名认证审核、用户资料更新和登录账号启停。
 * - 接口访问权限由 permissions.api_route 与 check.permission:admin 控制，控制器只处理已经通过接口鉴权的请求。
 * - 数据查看范围由 AdminDataScopeService 读取 role_data_scopes 与 admin_agent_bindings 后统一判断。
 * - user_id 在本控制器中始终表示业务用户 ID，对应 user_infos.user_id 与 user_logins.user_id，不是后台管理员 ID。
 *
 * 文件功能：
 * - 后台用户管理的核心控制器：列表/导出/详情/实名审核/资料更新/账号启停。
 * - 列表与导出均套用 AdminDataScopeService 数据范围；资料更新按白名单字段写入并先同步 MT4 再落本地镜像。
 *
 * 适用场景：
 * - 后台"用户管理"页面；exportUsers 导出上限 5000 行。
 *
 * 安全边界：
 * - 单用户操作（详情/审核/资料/启停）先经 denyUserAccessIfNeeded 数据范围校验，越权返回 PERMISSION_DENIED。
 * - 密码、身份证号、银行卡号等敏感字段不写入审计日志明文内容（脱敏标识），不输出到响应体。
 * - MT4 相关字段（只读状态、交易组、杠杆、银行卡快照、层级）一律先远端确认成功才写本地，失败返回 MT4_SYNC_FAILED。
 */
class AdminUserController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * 用户交易统计服务。
     *
     * @var UserStatisticsService
     */
    protected $userStatisticsService;

    /**
     * MT4 网关管理器：封装用户/组/信用的 MT4 服务端调用。本控制器的资料更新、实名审核
     * 等操作要求“先远端成功、再落本地镜像”；本地事务成功而 MT4 侧结果未知时依赖它的
     * 调用结果保证可追溯，缺失或不可用时必须失败关闭（返回 MT4_SYNC_FAILED），不允许只写本地。
     *
     * @var Mt4ManagerService
     */
    protected $mt4Manager;

    /**
     * 管理员实名审核处理器：驱动 admin_auth_review_outboxes 状态机并执行 MT4 侧变更。
     * 审核通过/驳回的落库与远端动作必须由它串行完成，绕过它直接改表会跳过 outbox
     * 审计与失败关闭语义；构造函数允许为 null 时现场组装以兼容旧容器配置。
     *
     * @var AdminAuthReviewProcessor
     */
    private $adminAuthReviewProcessor;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 后台数据范围服务，用于按角色和管理员绑定代理限制用户列表可见范围。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        UserStatisticsService $userStatisticsService,
        Mt4ManagerService $mt4Manager,
        AdminAuthReviewProcessor $adminAuthReviewProcessor = null
    ) {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->userStatisticsService = $userStatisticsService;
        $this->mt4Manager = $mt4Manager;
        $this->adminAuthReviewProcessor = $adminAuthReviewProcessor
            ?: new AdminAuthReviewProcessor($mt4Manager);
    }

    /**
     * 获取分页用户列表。
     *
     * userList() 参数说明：
     * - page 表示当前页码，默认从第 1 页开始。
     * - limit 表示每页数量，Layui table 默认使用该参数控制分页大小。
     * - user_id 表示业务用户 ID，用于精确筛选 user_infos.user_id。
     * - email 表示 user_logins.email 登录邮箱，用于通过 login 关系做模糊搜索。
     * - user_name 表示用户姓名或昵称，用于筛选 user_infos.user_name。
     * - account_type 表示账号类型，1=代理，2=普通客户。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数、筛选参数和 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse 返回 list 与 total，列表已按 AdminDataScopeService 追加数据范围限制。
     */
    public function userList(Request $request)
    {
        if ($dateFilterError = $this->validateUserDateFilter($request)) {
            return $dateFilterError;
        }

        if ($userIdFilterError = $this->validateUserIdFilter($request)) {
            return $userIdFilterError;
        }

        if ($accountTypeFilterError = $this->validateAccountTypeFilter($request)) {
            return $accountTypeFilterError;
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('limit', 15);

        $query = $this->filteredUserQuery($request);
        $users = (clone $query)
            ->orderByDesc('user_id')
            ->paginate($perPage, ['*'], 'page', $page);
        $list = $this->userListRowsWithStatistics($users->items(), $request);
        $totalRow = $this->userListTotalRowWithStatistics($query, $request);

        return $this->success([
            'list' => $list,
            'total' => $users->total(),
            'totalRow' => $totalRow,
        ], __('admin.user_list_fetched'));
    }

    /**
     * 导出当前筛选条件下的用户列表。
     *
     * exportUsers() 参数说明：
     * - user_id、email、user_name、account_type 与 userList() 使用同一筛选口径。
     * - 导出继续套用 AdminDataScopeService，避免通过 CSV 绕过后台数据范围。
     * - 当前阶段导出真实 user_infos 与 user_logins 可支撑字段，不伪造旧项目复杂统计列。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选参数和 admin guard 登录管理员。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse CSV 下载响应。
     */
    public function exportUsers(Request $request)
    {
        if ($dateFilterError = $this->validateUserDateFilter($request)) {
            return $dateFilterError;
        }

        if ($userIdFilterError = $this->validateUserIdFilter($request)) {
            return $userIdFilterError;
        }

        if ($accountTypeFilterError = $this->validateAccountTypeFilter($request)) {
            return $accountTypeFilterError;
        }

        $rows = [
            ['user_id', 'user_name', 'email', 'phone', 'account_type', 'auth_status', 'is_enabled', 'is_cancelled', 'total_funds', 'created_at'],
        ];

        $this->filteredUserQuery($request)
            ->orderByDesc('user_id')
            ->limit(5000)
            ->get()
            ->each(function (UserInfo $user) use (&$rows) {
                $rows[] = [
                    $user->user_id,
                    $user->user_name,
                    optional($user->login)->email,
                    $user->phone,
                    $user->account_type,
                    $user->auth_status,
                    optional($user->login)->is_enabled,
                    optional($user->login)->is_cancelled,
                    $user->total_funds,
                    $user->created_at,
                ];
            });

        return $this->csvDownload('users_export.csv', $rows);
    }

    /**
     * 删除业务用户。
     *
     * 参数说明：
     * - $id 表示业务用户 ID，对应 user_infos.user_id。
     *
     * @param int|string $id 业务用户 ID。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     */
    public function destroy($id)
    {
        $user = UserInfo::where('user_id', $id)->first();
        if (!$user) {
            return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $user->delete();

        return $this->success([], __('admin.user_deleted'));
    }

    /**
     * 审核用户实名认证资料。
     *
     * reviewAuth() 参数说明：
     * - user_id 表示业务用户 ID，对应 user_auths.user_id 与 user_infos.user_id。
     * - status 表示审核结果的兼容字段，1=两项通过，2=两项拒绝。
     * - id_card_decision / bank_decision 表示独立组件结果，1=通过，2=拒绝；至少提交一项，且不能与 status 混用。
     * - reason 表示审核拒绝原因的兼容共享字段；id_card_reason / bank_reason 可分别保存对应组件的拒绝原因。
     *
     * @param Request $request 当前 HTTP 请求对象，承载审核参数和 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse 审核结果响应；越过数据范围时返回 PERMISSION_DENIED。
     */
    public function reviewAuth(Request $request)
    {
        $reviewPayload = $request->only([
            'user_id',
            'status',
            'reason',
            'id_card_decision',
            'bank_decision',
            'id_card_reason',
            'bank_reason',
        ]);
        $validator = Validator::make($reviewPayload, [
            'user_id' => 'required|integer|min:1',
            'status' => 'nullable|integer|in:1,2',
            'reason' => 'nullable|string|max:500',
            'id_card_decision' => 'nullable|integer|in:1,2',
            'bank_decision' => 'nullable|integer|in:1,2',
            'id_card_reason' => 'nullable|string|max:500',
            'bank_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        try {
            $decisions = AuthReviewTransition::normalizeDecisions($reviewPayload);
        } catch (\InvalidArgumentException $exception) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $userId = (int) $request->input('user_id');
        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $accessDenied = $this->denyUserAccessIfNeeded($request, $userId);
        if ($accessDenied) {
            return $accessDenied;
        }

        $statusLabel = $request->filled('status') ? (string) ((int) $request->input('status')) : 'component';
        $idCardDecisionLabel = array_key_exists('id_card_decision', $decisions)
            ? (string) $decisions['id_card_decision']
            : 'none';
        $bankDecisionLabel = array_key_exists('bank_decision', $decisions)
            ? (string) $decisions['bank_decision']
            : 'none';

        try {
            $result = $this->adminAuthReviewProcessor->submit($userId, $decisions, [
                'admin_id' => (int) $admin->id,
                'admin_name' => (string) $admin->username,
                'request_ip' => $request->ip() ?: '',
                'status_label' => $statusLabel,
                'id_card_decision_label' => $idCardDecisionLabel,
                'bank_decision_label' => $bankDecisionLabel,
            ]);
        } catch (Throwable $exception) {
            Log::error('Admin authentication review processing failed.', [
                'user_id' => $userId,
                'admin_id' => (int) $admin->id,
                'exception_class' => get_class($exception),
            ]);

            return $this->serverErrorResponse();
        }

        $resultStatus = (string) ($result['status'] ?? '');
        if ($resultStatus === 'processed') {
            return $this->success([], __('admin.auth_review_completed'));
        }
        if ($resultStatus === 'missing') {
            return $this->error(__('admin.auth_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }
        if ($resultStatus === 'conflict') {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }
        if (in_array($resultStatus, ['pending', 'processing', 'retryable', 'rejected', 'unknown'], true)) {
            return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                'user_id' => $userId,
                'outbox_id' => (int) ($result['outbox_id'] ?? 0),
                'status' => $resultStatus,
                'error_code' => (string) ($result['error_code'] ?? ''),
            ]);
        }

        Log::error('Admin authentication review returned an unsupported state.', [
            'user_id' => $userId,
            'admin_id' => (int) $admin->id,
            'status' => $resultStatus,
        ]);

        return $this->serverErrorResponse();
    }

    /**
     * 获取用户完整详情。
     *
     * userDetail() 参数说明：
     * - user_id 表示业务用户 ID，用于读取 user_infos、user_logins 与 user_auths 关联资料。
     * - 当前管理员只能查看 AdminDataScopeService 判定可访问的数据范围内用户。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 user_id 和 admin guard 登录用户。
     * @return \Illuminate\Http\JsonResponse 用户详情响应；越过数据范围时返回 PERMISSION_DENIED。
     */
    public function userDetail(Request $request)
    {
        $userId = $request->route('user') ?: $request->input('user_id');
        $validator = Validator::make(['user_id' => $userId], [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = (int) $userId;
        $user = UserInfo::with(['login', 'auth'])->where('user_id', $userId)->first();

        if (!$user) {
            return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $accessDenied = $this->denyUserAccessIfNeeded($request, $userId);
        if ($accessDenied) {
            return $accessDenied;
        }

        return $this->success($user, __('admin.user_detail_fetched'));
    }

    /**
     * 更新用户资料字段。
     *
     * updateUser() 参数说明：
     * - user_id 表示业务用户 ID，用于定位 user_infos 记录。
     * - id 为页面或旧接口可能携带的主键兼容参数，保存时会被排除，避免误写主键。
     * - user_name、phone 表示后台可直接维护的基础资料，保存时只写入白名单字段。
     * - email/useremail 表示登录邮箱，写入 user_logins.email 前必须校验格式和唯一性。
     * - id_card_no/userIdcardNo 表示实名身份证号，写入 user_auths.id_card_no 前必须校验用户维度唯一性。
     * - bank_no、bank_class/bank_name、bank_info/bank_addr 表示旧项目已审核银行卡快照，写入前必须先同步 MT4 comment。
     * - isoutmoney/isallowmoney 表示旧项目出入金开关，0=允许，1=不允许，写入 user_infos 对应限制字段。
     * - enablereadonly 表示旧项目 MT4 只读状态，1=锁定交易，0=解除只读，必须先同步 MT4 再写入本地镜像。
     * - userparentId 表示旧项目上级代理字段，只通过旧字段入口调整 user_infos.parent_id 并重建 family_tree 与 agent_descendants。
     * - sex、gift_allowed、userremark 表示旧项目本地资料字段，分别写入 gender、is_gift_allowed、remark。
     * - mt4_group、leverage 表示旧项目 cust_save_info 可修改的 MT4 交易组和杠杆，必须先同步 MT4 成功后才写入本地镜像。
     * - password、password1 表示旧项目资料编辑里的重置密码字段；******** 是旧页面占位符，表示不修改密码。
     * - 账号类型、上级代理、认证状态和资金字段仍必须由各自专用流程维护，避免资料编辑接口越权改动核心业务状态。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 user_id、待更新字段和 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse 更新后的用户资料；MT4 失败返回 MT4_SYNC_FAILED，越过数据范围时返回 PERMISSION_DENIED。
     */
    public function updateUser(Request $request)
    {
        // 先归一化新旧页面字段名（旧 cust_save_info 命名 -> 现代字段），后续校验与写入全部基于归一结果。
        $payload = $this->normalizedUserUpdatePayload($request);
        $userId = $request->route('user') ?: ($payload['user_id'] ?? null);
        unset($payload['user_id']);

        $validator = Validator::make(['user_id' => $userId] + $payload, [
            'user_id' => ['required', 'integer'],
            'legacy_group_id' => ['sometimes', 'integer', 'min:1'],
            'user_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'mt4_group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'leverage' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'max:100'],
            'id_card_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_addr' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_withdrawal_allowed' => ['sometimes', 'in:0,1'],
            'is_deposit_allowed' => ['sometimes', 'in:0,1'],
            'is_mt4_readonly' => ['sometimes', 'in:0,1'],
            'parent_agent_id' => ['sometimes', 'integer', 'min:0'],
            'gender' => ['sometimes', 'in:1,2'],
            'is_gift_allowed' => ['sometimes', 'in:0,1'],
            'remark' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = (int) $userId;
        $user = UserInfo::where('user_id', $userId)->first();

        if (!$user) {
            return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $accessDenied = $this->denyUserAccessIfNeeded($request, $userId);
        if ($accessDenied) {
            return $accessDenied;
        }

        $legacyGroup = null;
        $legacyGroupSelectionSubmitted = array_key_exists('legacy_group_id', $payload)
            || array_key_exists('legacy_group_name', $payload);
        if ($legacyGroupSelectionSubmitted) {
            $rawGroupId = $payload['legacy_group_id'] ?? null;
            $groupName = trim((string) ($payload['legacy_group_name'] ?? $payload['mt4_group'] ?? ''));
            $validGroupId = is_int($rawGroupId)
                || (is_string($rawGroupId) && ctype_digit(trim($rawGroupId)));

            if ((int) $user->account_type !== 2
                || !$validGroupId
                || (int) $rawGroupId <= 0
                || $groupName === ''
            ) {
                return $this->error(__('response.invalid_group'), ResponseCode::VALIDATION_FAILED);
            }

            $legacyGroup = GroupConfig::query()
                ->user()
                ->enabled()
                ->whereKey((int) $rawGroupId)
                ->where('name', $groupName)
                ->first();

            if (!$legacyGroup) {
                return $this->error(__('response.invalid_group'), ResponseCode::VALIDATION_FAILED);
            }

            // 组配置是唯一可信来源：客户端 is_enc 只用于旧页面展示，不能覆盖数据库组属性。
            $payload['mt4_group'] = (string) $legacyGroup->name;
            $payload['leverage'] = (int) $legacyGroup->is_ecn === 1 ? 200 : 100;
        }

        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $familyTreeService = app(FamilyTreeService::class);
        $hierarchyChange = null;
        // 上级代理变更：先做防循环/防越权校验，并预解析新旧层级链，供后续 MT4 同步与事务补偿使用。
        if (array_key_exists('parent_agent_id', $payload)) {
            if ((int) $user->account_type !== 2) {
                return $this->error(__('validation.in', ['attribute' => 'userparentId']), ResponseCode::VALIDATION_FAILED);
            }

            $requestedParentId = (int) $payload['parent_agent_id'];
            if ($requestedParentId !== (int) $user->parent_id) {
                $parentAgentError = $this->validateParentAgentChange($user, $requestedParentId);
                if ($parentAgentError) {
                    return $parentAgentError;
                }

                if ($requestedParentId > 0
                    && !$this->adminDataScopeService->canAccessUser($admin, $requestedParentId, 'agent')
                ) {
                    return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
                }

                try {
                    $targetHierarchy = $familyTreeService->resolveCustomerHierarchy($userId, $requestedParentId);
                    $originalHierarchy = $familyTreeService->resolveCustomerHierarchy($userId, (int) $user->parent_id);
                } catch (\InvalidArgumentException $exception) {
                    Log::warning('后台普通客户上级代理链校验失败。', [
                        'user_id' => $userId,
                        'target_parent_id' => $requestedParentId,
                        'reason' => $exception->getMessage(),
                    ]);

                    return $this->error(__('validation.exists', ['attribute' => 'userparentId']), ResponseCode::VALIDATION_FAILED);
                }

                $hierarchyChange = [
                    'old_parent_id' => (int) $user->parent_id,
                    'old_hierarchy' => $originalHierarchy,
                    'target_parent_id' => $requestedParentId,
                    'target_hierarchy' => $targetHierarchy,
                ];
            }
        }

        $login = null;
        $loginUpdates = [];
        // 登录邮箱变更：先做格式与跨用户唯一性校验，避免邮箱被两个登录账号共用。
        if (array_key_exists('email', $payload)) {
            $login = UserLogin::where('user_id', $userId)->first();
            if (!$login) {
                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $targetEmail = strtolower(trim((string) $payload['email']));
            if ($targetEmail === '') {
                return $this->error(__('validation.required', ['attribute' => 'email']), ResponseCode::VALIDATION_FAILED);
            }

            if (UserLogin::where('email', $targetEmail)->where('user_id', '!=', $userId)->exists()) {
                return $this->error(__('auth.email_exists'), ResponseCode::VALIDATION_FAILED);
            }

            $loginUpdates['email'] = $targetEmail;
        }

        $auth = null;
        $authUpdates = [];
        // 实名资料与银行卡快照变更：已审核银行卡必须先同步 MT4 comment，远端未确认成功则本地不落库。
        $bankSnapshotRequested = $this->bankSnapshotRequested($payload);
        if (array_key_exists('id_card_no', $payload) || $bankSnapshotRequested) {
            $auth = UserAuth::firstOrNew(['user_id' => $userId]);

            if (array_key_exists('id_card_no', $payload)) {
                $targetIdCardNo = trim((string) $payload['id_card_no']);
                if ($targetIdCardNo !== ''
                    && UserAuth::where('id_card_no', $targetIdCardNo)->where('user_id', '!=', $userId)->exists()
                ) {
                    return $this->error(__('validation.unique', ['attribute' => 'id_card_no']), ResponseCode::VALIDATION_FAILED);
                }

                $authUpdates['id_card_no'] = $targetIdCardNo;
            }

            if ($bankSnapshotRequested && $auth->exists && (int) $auth->bank_status === 2) {
                $targetBankSnapshot = $this->targetBankSnapshot($payload, $auth);
                foreach ($targetBankSnapshot as $bankField => $bankValue) {
                    if ($bankValue !== '') {
                        continue;
                    }

                    return $this->error(__('validation.required', ['attribute' => $bankField]), ResponseCode::VALIDATION_FAILED);
                }

                $bankComment = implode('|', [
                    $targetBankSnapshot['bank_no'],
                    $targetBankSnapshot['bank_name'],
                    $targetBankSnapshot['bank_addr'],
                ]);

                // 已审核银行卡是出金和 MT4 备注使用的可信快照；先同步 MT4，避免本地银行卡已经改变但交易端仍显示旧备注。
                try {
                    $mt4CommentResult = $this->mt4Manager->updateComment($userId, $bankComment);
                } catch (Throwable $exception) {
                    Log::error('后台用户银行卡备注同步 MT4 异常。', [
                        'user_id' => $userId,
                        'exception_class' => get_class($exception),
                    ]);
                    $mt4CommentResult = [
                        'status' => 'error',
                        'error_code' => 'transport_exception',
                    ];
                }

                if (!$this->isMt4Success($mt4CommentResult)) {
                    return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                        'user_id' => $userId,
                        'error_code' => (string) ($mt4CommentResult['error_code'] ?? $mt4CommentResult['err'] ?? 'provider_rejected'),
                    ]);
                }

                $authUpdates = array_merge($authUpdates, $targetBankSnapshot, ['is_bank_synced' => 1]);
            }
        }

        $updates = $this->userProfileUpdates($payload);

        if ($legacyGroup !== null) {
            $updates['group_id'] = (int) $legacyGroup->id;
            $updates['is_ecn'] = (int) $legacyGroup->is_ecn;
            if ((int) $legacyGroup->id !== (int) $user->group_id) {
                $updates['original_group'] = trim((string) $user->mt4_group);
            }
        }

        if (array_key_exists('is_mt4_readonly', $payload)) {
            $targetReadonly = (int) $payload['is_mt4_readonly'];

            if ($targetReadonly !== (int) $user->is_mt4_readonly) {
                // MT4 只读状态是真实交易权限，必须先让交易端确认成功，避免后台资料先改但账户仍可交易或仍被锁定。
                try {
                    $mt4ReadonlyResult = $targetReadonly === 1
                        ? $this->mt4Manager->lockUser($userId)
                        : $this->mt4Manager->unlockUser($userId);
                } catch (Throwable $exception) {
                    Log::error('后台用户资料 MT4 只读状态同步异常。', [
                        'user_id' => $userId,
                        'target_readonly' => $targetReadonly,
                        'exception_class' => get_class($exception),
                    ]);
                    $mt4ReadonlyResult = [
                        'status' => 'error',
                        'error_code' => 'transport_exception',
                    ];
                }

                if (!$this->isMt4Success($mt4ReadonlyResult)) {
                    return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                        'user_id' => $userId,
                        'is_mt4_readonly' => $targetReadonly,
                        'error_code' => (string) ($mt4ReadonlyResult['error_code'] ?? $mt4ReadonlyResult['err'] ?? 'provider_rejected'),
                    ]);
                }

                $updates['is_mt4_readonly'] = $targetReadonly;
            }
        }

        $tradingProfileRequested = array_key_exists('mt4_group', $payload) || array_key_exists('leverage', $payload);

        // 交易组与杠杆：目标值先同步 MT4 成功后才写入本地镜像，防止与远端账户资料分叉。
        if ($tradingProfileRequested) {
            $targetGroup = array_key_exists('mt4_group', $payload)
                ? trim((string) $payload['mt4_group'])
                : trim((string) $user->mt4_group);
            $targetLeverage = array_key_exists('leverage', $payload)
                ? (int) $payload['leverage']
                : (int) $user->leverage;

            if ($targetGroup === '') {
                return $this->error(__('validation.required', ['attribute' => 'mt4_group']), ResponseCode::VALIDATION_FAILED);
            }
            if ($targetLeverage <= 0) {
                $targetLeverage = 100;
            }

            if ($targetGroup !== trim((string) $user->mt4_group)
                && $this->hasOpenTradingOrders($userId)
            ) {
                return $this->error(__('front.account_type_error_open_orders'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            // 交易组和杠杆是 MT4 真实账户资料；先确认远端成功，避免本地镜像先写入后与 MT4 状态分叉。
            try {
                $mt4Result = $this->mt4Manager->updateUserTradingProfile($userId, $targetGroup, $targetLeverage);
            } catch (Throwable $exception) {
                Log::error('后台用户资料交易组同步 MT4 异常。', [
                    'user_id' => $userId,
                    'target_group' => $targetGroup,
                    'target_leverage' => $targetLeverage,
                    'exception_class' => get_class($exception),
                ]);
                $mt4Result = [
                    'status' => 'error',
                    'error_code' => 'transport_exception',
                ];
            }

            if (!$this->isMt4Success($mt4Result)) {
                return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                    'user_id' => $userId,
                    'mt4_group' => $targetGroup,
                    'leverage' => $targetLeverage,
                    'error_code' => (string) ($mt4Result['error_code'] ?? $mt4Result['err'] ?? 'provider_rejected'),
                ]);
            }

            $updates['mt4_group'] = $targetGroup;
            $updates['leverage'] = $targetLeverage;
        }

        $hierarchyMt4Synced = false;
        // 层级变更需先推送到 MT4 并记录已同步标记；本地事务一旦失败，据此回滚远端层级。
        if ($hierarchyChange !== null) {
            $targetHierarchy = $hierarchyChange['target_hierarchy'];
            try {
                $mt4HierarchyResult = $this->mt4Manager->updateUserHierarchy(
                    $userId,
                    (int) $hierarchyChange['target_parent_id'],
                    (string) $targetHierarchy['relationship_code']
                );
            } catch (Throwable $exception) {
                Log::error('后台普通客户上级代理同步 MT4 异常。', [
                    'user_id' => $userId,
                    'target_parent_id' => (int) $hierarchyChange['target_parent_id'],
                    'exception_class' => get_class($exception),
                ]);
                $mt4HierarchyResult = [
                    'status' => 'error',
                    'error_code' => 'transport_exception',
                ];
            }

            if (!$this->isMt4Success($mt4HierarchyResult)) {
                return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                    'user_id' => $userId,
                    'parent_id' => (int) $hierarchyChange['target_parent_id'],
                    'error_code' => (string) ($mt4HierarchyResult['error_code'] ?? $mt4HierarchyResult['err'] ?? 'provider_rejected'),
                ]);
            }

            $hierarchyMt4Synced = true;
        }

        $passwordChanged = false;
        // 密码修改会同步到 MT4 登录端；此时若层级已同步到 MT4 而登录账号缺失/同步失败，必须先补偿层级再返回。
        if ($this->passwordChangeRequested($payload)) {
            $userLogin = UserLogin::where('user_id', $userId)->first();
            if (!$userLogin) {
                if ($hierarchyMt4Synced) {
                    $this->compensateMt4Hierarchy($userId, $hierarchyChange);
                }

                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            if (!app(UserPasswordService::class)->change($userLogin, (string) $payload['password'])) {
                if ($hierarchyMt4Synced) {
                    $this->compensateMt4Hierarchy($userId, $hierarchyChange);
                }

                return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                    'user_id' => $userId,
                    'error_code' => 'password_sync_failed',
                ]);
            }
            $passwordChanged = true;
        }

        unset($payload['password']);
        if (empty($updates) && empty($loginUpdates) && empty($authUpdates) && !$passwordChanged && $hierarchyChange === null) {
            return $this->success($user, __('admin.user_updated'), ResponseCode::UPDATED);
        }

        $auditUpdates = $updates;
        if ($hierarchyChange !== null) {
            $auditUpdates['parent_id'] = (int) $hierarchyChange['target_parent_id'];
            $auditUpdates['family_tree'] = (string) $hierarchyChange['target_hierarchy']['family_tree'];
        }
        $content = $this->userUpdateAuditContent($user, $auditUpdates, $passwordChanged, $login, $loginUpdates, $auth, $authUpdates);

        try {
            // 本地事务：行锁复查层级未被并发修改，再写入 user_infos/login/auth 与审计日志，保证多表一致。
            DB::transaction(function () use (
                $user,
                $updates,
                $login,
                $loginUpdates,
                $auth,
                $authUpdates,
                $admin,
                $request,
                $userId,
                $content,
                $familyTreeService,
                $hierarchyChange
            ) {
                $lockedUser = $user;
                $profileUpdates = $updates;

                if ($hierarchyChange !== null) {
                    $lockedUser = UserInfo::query()
                        ->where('user_id', $userId)
                        ->lockForUpdate()
                        ->first();
                    if (!$lockedUser
                        || (int) $lockedUser->account_type !== 2
                        || (int) $lockedUser->parent_id !== (int) $hierarchyChange['old_parent_id']
                    ) {
                        throw new \RuntimeException('客户层级在 MT4 同步后发生并发变化。');
                    }

                    $ancestorIds = $hierarchyChange['target_hierarchy']['ancestor_ids'];
                    if (!empty($ancestorIds)) {
                        UserInfo::query()->whereIn('user_id', $ancestorIds)->lockForUpdate()->get();
                    }

                    $verifiedHierarchy = $familyTreeService->resolveCustomerHierarchy(
                        $userId,
                        (int) $hierarchyChange['target_parent_id']
                    );
                    if ($verifiedHierarchy !== $hierarchyChange['target_hierarchy']) {
                        throw new \RuntimeException('目标代理链在 MT4 同步后发生并发变化。');
                    }

                    $profileUpdates['parent_id'] = (int) $hierarchyChange['target_parent_id'];
                    $profileUpdates['family_tree'] = (string) $verifiedHierarchy['family_tree'];
                }

                if (!empty($profileUpdates)) {
                    $lockedUser->update($profileUpdates + ['updated_by' => (int) $admin->id]);
                }
                if ($hierarchyChange !== null) {
                    $familyTreeService->syncCustomerDescendantRelations(
                        $userId,
                        (int) $lockedUser->account_type,
                        $hierarchyChange['target_hierarchy']['ancestor_ids'],
                        (int) $hierarchyChange['target_parent_id']
                    );
                }
                if ($login !== null && !empty($loginUpdates)) {
                    $login->update($loginUpdates);
                }
                if ($auth !== null && !empty($authUpdates)) {
                    $auth->fill($authUpdates + ['user_id' => $userId]);
                    $auth->save();
                }

                OperationLog::create([
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->username,
                    'target_user_id' => $userId,
                    'order_no' => 'user_update:' . $userId,
                    'content' => $content,
                    'ip' => $request->ip() ?: '',
                    'action_type' => 0,
                ]);
            });
        } catch (Throwable $exception) {
            // 本地事务已回滚：把 MT4 层级补偿回变更前快照；补偿失败只记 critical 日志，接口仍返回服务器错误（fail-closed）。
            $compensationStatus = 'not_required';
            if ($hierarchyMt4Synced) {
                $compensationStatus = $this->compensateMt4Hierarchy($userId, $hierarchyChange)
                    ? 'success'
                    : 'failed';
            }

            Log::error('后台用户资料本地事务失败并已回滚。', [
                'user_id' => $userId,
                'hierarchy_compensation' => $compensationStatus,
                'exception_class' => get_class($exception),
            ]);

            return $this->serverErrorResponse();
        }

        return $this->success($user->fresh(), __('admin.user_updated'), ResponseCode::UPDATED);
    }

    /**
     * 启用或禁用用户登录账号。
     *
     * changeUserStatus() 参数说明：
     * - user_id 表示业务用户 ID，用于定位 user_logins 记录。
     * - is_enabled 表示 user_logins.is_enabled，1=允许登录，0=禁止登录。
     * - 状态变更前仍会调用数据范围校验，防止普通管理员停用范围外账号。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 user_id、is_enabled 和 admin guard 登录管理员。
     * @return \Illuminate\Http\JsonResponse 启停结果响应；越过数据范围时返回 PERMISSION_DENIED。
     */
    public function changeUserStatus(Request $request)
    {
        $userId = $request->route('user') ?: $request->input('user_id');
        $isEnabled = $request->input('is_enabled');

        $validator = Validator::make([
            'user_id' => $userId,
            'is_enabled' => $isEnabled,
        ], [
            'user_id' => 'required|integer',
            'is_enabled' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = (int) $userId;
        $isEnabled = (int) $isEnabled;
        $userLogin = UserLogin::where('user_id', $userId)->first();
        if (!$userLogin) {
            return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $accessDenied = $this->denyUserAccessIfNeeded($request, $userId);
        if ($accessDenied) {
            return $accessDenied;
        }

        // Align with old project: disable/enable must sync MT4 lock before local login flag flips.
        try {
            $mt4Result = $isEnabled === 1
                ? $this->mt4Manager->unlockUser($userId)
                : $this->mt4Manager->lockUser($userId);
        } catch (Throwable $exception) {
            Log::error('Change user status MT4 sync failed.', [
                'user_id' => $userId,
                'is_enabled' => $isEnabled,
                'exception_class' => get_class($exception),
            ]);
            $mt4Result = [
                'status' => 'error',
                'error_code' => 'transport_exception',
            ];
        }

        if (strtolower(trim((string) ($mt4Result['status'] ?? 'error'))) !== 'ok') {
            return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED, [
                'user_id' => $userId,
                'is_enabled' => $isEnabled,
                'error_code' => (string) ($mt4Result['error_code'] ?? 'provider_rejected'),
            ]);
        }

        DB::transaction(function () use ($userLogin, $userId, $isEnabled) {
            $userLogin->update(['is_enabled' => $isEnabled]);
            UserInfo::where('user_id', $userId)->update([
                'is_mt4_enabled' => $isEnabled === 1 ? 1 : 0,
                'is_mt4_readonly' => $isEnabled === 1 ? 0 : 1,
                'updated_at' => time(),
            ]);
        });

        return $this->success([], __('admin.user_status_updated'));
    }

    /**
     * 校验旧字段 userparentId 是否可以作为新的直属上级代理。
     *
     * 业务逻辑说明：
     * - 0 表示平台根节点，允许用户脱离代理树，后续会清理该用户作为后代的闭包关系。
     * - 非 0 上级必须是真实代理账号，避免普通客户被挂成代理节点后污染代理数据范围。
     * - 新上级不能是当前用户自己或当前用户的下级，否则 family_tree 会形成循环链路。
     *
     * @param UserInfo $user 当前被编辑的业务用户资料。
     * @param int $targetParentId 旧字段 userparentId 归一化后的目标直属上级业务用户 ID。
     * @return \Illuminate\Http\JsonResponse|null 返回 JsonResponse 表示拒绝本次修改，null 表示可以继续执行。
     */
    private function validateParentAgentChange(UserInfo $user, int $targetParentId)
    {
        $userId = (int) $user->user_id;
        if ($targetParentId === $userId) {
            return $this->error(__('validation.different', ['attribute' => 'userparentId', 'other' => 'user_id']), ResponseCode::VALIDATION_FAILED);
        }

        if ($targetParentId === 0) {
            return null;
        }

        $parent = UserInfo::where('user_id', $targetParentId)->first();
        if (!$parent || (int) $parent->account_type !== 1) {
            return $this->error(__('validation.exists', ['attribute' => 'userparentId']), ResponseCode::VALIDATION_FAILED);
        }

        if ($this->parentChangeCreatesCycle($userId, $targetParentId)) {
            return $this->error(__('validation.different', ['attribute' => 'userparentId', 'other' => 'descendant']), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 判断新的上级代理是否已经位于当前用户的下级链路中。
     *
     * 这样做的原因：
     * - 仅依赖 family_tree 可能被历史脏数据影响，因此这里沿 parent_id 向上回溯做强校验。
     * - 如果回溯过程中遇到当前用户 ID，说明管理员试图把用户挂到自己的子孙下面，必须拒绝。
     * - 如果遇到既有循环，也直接按风险路径拒绝，避免把错误关系进一步扩大。
     *
     * @param int $userId 当前被编辑的业务用户 ID。
     * @param int $targetParentId 目标直属上级代理业务用户 ID。
     * @return bool true 表示会形成循环，false 表示链路安全。
     */
    private function parentChangeCreatesCycle(int $userId, int $targetParentId): bool
    {
        $cursor = $targetParentId;
        $visited = [];

        while ($cursor > 0) {
            if ($cursor === $userId) {
                return true;
            }
            if (isset($visited[$cursor]) || count($visited) >= UserInfo::MAX_HIERARCHY_DEPTH) {
                return true;
            }

            $visited[$cursor] = true;
            $cursor = (int) UserInfo::where('user_id', $cursor)->value('parent_id');
        }

        return false;
    }

    /**
     * 规范化后台用户资料更新入参。
     *
     * 业务逻辑说明：
     * - 旧项目 cust_save_info 会使用 username、userphoneNo、useremail、userIdcardNo、bank_class、bank_info、isoutmoney、isallowmoney、enablereadonly、userparentId、sex、gift_allowed、userremark、usergrpName、cust_lvg 等字段名。
     * - 新项目页面和 REST 风格接口使用 user_name、phone、email、id_card_no、bank_name、bank_addr、mt4_group、leverage。
     * - 本方法只做字段名归一和轻量 trim，不做数据库写入，避免旧字段直接绕过 updateUser() 的白名单。
     *
     * @param Request $request 当前 HTTP 请求对象，可能包含现代字段、旧字段或 data 嵌套字段。
     * @return array<string, mixed> 归一后的 user_id、user_name、phone、email、认证、银行卡、只读状态、上级代理、本地资料、mt4_group、leverage、password 字段。
     */
    private function normalizedUserUpdatePayload(Request $request): array
    {
        $raw = $request->all();
        $nested = $request->input('data');
        if (is_array($nested)) {
            unset($raw['data']);
            $raw = array_replace($nested, $raw);
        }

        $payload = [];
        $this->copyFirstExisting($payload, $raw, 'user_id', ['user_id', 'userId', 'uid']);
        $this->copyFirstExisting($payload, $raw, 'user_name', ['user_name', 'username']);
        $this->copyFirstExisting($payload, $raw, 'email', ['email', 'useremail', 'user_email']);
        $this->copyFirstExisting($payload, $raw, 'id_card_no', ['id_card_no', 'userIdcardNo', 'IDcard_no']);
        $this->copyFirstExisting($payload, $raw, 'bank_no', ['bank_no', 'userbankNo']);
        $this->copyFirstExisting($payload, $raw, 'bank_name', ['bank_name', 'bank_class']);
        $this->copyFirstExisting($payload, $raw, 'bank_addr', ['bank_addr', 'bank_info']);
        $this->copyFirstExisting($payload, $raw, 'is_withdrawal_allowed', ['isoutmoney']);
        $this->copyFirstExisting($payload, $raw, 'is_deposit_allowed', ['isallowmoney']);
        $this->copyFirstExisting($payload, $raw, 'is_mt4_readonly', ['enablereadonly']);
        $this->copyFirstExisting($payload, $raw, 'parent_agent_id', ['userparentId', 'userParentId']);
        $this->copyFirstExisting($payload, $raw, 'is_gift_allowed', ['gift_allowed']);
        $this->copyFirstExisting($payload, $raw, 'remark', ['userremark']);
        $this->copyFirstExisting($payload, $raw, 'legacy_group_id', ['usergrpId']);
        $this->copyFirstExisting($payload, $raw, 'legacy_group_name', ['usergrpName']);
        $this->copyFirstExisting($payload, $raw, 'mt4_group', ['mt4_group', 'usergrpName', 'group', 'user_grp_name']);
        $this->copyFirstExisting($payload, $raw, 'leverage', ['leverage', 'cust_lvg', 'lvg']);
        $this->copyFirstExisting($payload, $raw, 'password', ['password', 'password1']);

        if (array_key_exists('sex', $raw)) {
            $payload['gender'] = $this->normalizeLegacyGenderValue($raw['sex']);
        }

        if (array_key_exists('phone', $raw)) {
            $payload['phone'] = trim((string) $raw['phone']);
        } elseif (array_key_exists('userphoneNo', $raw)) {
            $phoneCode = trim((string) ($raw['modules'] ?? '86'));
            $phoneNo = trim((string) $raw['userphoneNo']);
            $payload['phone'] = $phoneNo === '' ? '' : $phoneCode . '-' . $phoneNo;
        }

        if (!array_key_exists('leverage', $payload) && array_key_exists('is_enc', $raw) && $raw['is_enc'] !== '') {
            $payload['leverage'] = (int) $raw['is_enc'] === 1 ? 200 : 100;
        }

        if (array_key_exists('email', $payload)) {
            $payload['email'] = strtolower(trim((string) $payload['email']));
        }

        return $payload;
    }

    /**
     * 从多个候选字段中复制第一个存在的值。
     *
     * @param array<string, mixed> $payload 归一后的目标入参数组。
     * @param array<string, mixed> $raw 原始请求数组。
     * @param string $target 目标字段名。
     * @param array<int, string> $candidates 候选字段名，按优先级排序。
     * @return void
     */
    private function copyFirstExisting(array &$payload, array $raw, string $target, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            if (!array_key_exists($candidate, $raw)) {
                continue;
            }

            $payload[$target] = is_string($raw[$candidate])
                ? trim($raw[$candidate])
                : $raw[$candidate];

            return;
        }
    }

    /**
     * 将旧项目性别字段转换为新项目 gender 枚举。
     *
     * 参数含义：
     * - $value 来自旧 Blade 的 sex 字段，常见值为“男”或“女”，也兼容 1/2、male/female。
     * - 返回 1 表示男，返回 2 表示女。
     * - 无法识别的值原样返回，让 updateUser() 的 Validator 统一返回 VALIDATION_FAILED。
     *
     * @param mixed $value 旧项目 sex 字段值。
     * @return mixed 归一化后的 gender 枚举或原始不可识别值。
     */
    private function normalizeLegacyGenderValue($value)
    {
        $gender = is_string($value) ? trim($value) : $value;
        $lowerGender = strtolower((string) $gender);

        if ($gender === '男' || $lowerGender === 'male' || $lowerGender === 'm' || (string) $gender === '1') {
            return 1;
        }

        if ($gender === '女' || $lowerGender === 'female' || $lowerGender === 'f' || (string) $gender === '2') {
            return 2;
        }

        return $gender;
    }

    /**
     * 提取允许直接写入 user_infos 的基础资料字段。
     *
     * 解决的问题：
     * - updateUser() 面向旧后台和新后台共用，必须继续拒绝 account_type、parent_id、资金等现代敏感字段。
     * - 交易组和杠杆需要先经过 MT4 同步分支，不能在基础资料白名单中提前写入。
     * - 出入金开关只接受旧项目 `isoutmoney/isallowmoney` 归一后的字段，现代同名敏感字段继续不从请求中复制。
     * - MT4 只读状态必须先走 lock/unlock 同步分支，本方法不提前把 `is_mt4_readonly` 放入基础资料更新。
     * - 性别、礼品领取和备注只接收旧字段 `sex/gift_allowed/userremark` 归一后的结果，避免现代同名字段绕过白名单。
     *
     * @param array<string, mixed> $payload 归一后的请求参数。
     * @return array<string, mixed> 可直接作为 user_infos 更新值的基础资料字段。
     */
    private function userProfileUpdates(array $payload): array
    {
        $updates = [];
        foreach (['user_name', 'phone'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = (string) $payload[$field];
            }
        }

        foreach (['is_withdrawal_allowed', 'is_deposit_allowed'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = (int) $payload[$field];
            }
        }

        foreach (['gender', 'is_gift_allowed'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = (int) $payload[$field];
            }
        }

        if (array_key_exists('remark', $payload)) {
            $updates['remark'] = (string) $payload['remark'];
        }

        return $updates;
    }

    /**
     * 判断用户是否仍有未平仓交易或挂单。
     *
     * 旧客户资料编辑在调组前会阻断 MT4 命令 0-5 的未平仓订单；本地镜像
     * 使用 close_time 的 MT4 哨兵值表示未平仓，读取只用于失败关闭判断。
     */
    private function hasOpenTradingOrders(int $userId): bool
    {
        return UserTrade::query()
            ->where('user_id', $userId)
            ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
            ->where(function (Builder $query): void {
                $query->whereNull('close_time')
                    ->orWhere('close_time', 0)
                    ->orWhere('close_time', '1970-01-01 00:00:00')
                    ->orWhere('close_time', '1970-01-02 00:00:00');
            })
            ->exists();
    }

    /**
     * 判断本次请求是否提交了银行卡快照字段。
     *
     * 参数逻辑说明：
     * - bank_no 表示银行卡号，来自现代字段或旧字段 bank_no。
     * - bank_name 表示开户银行，旧项目字段名为 bank_class。
     * - bank_addr 表示开户支行或开户地址，旧项目字段名为 bank_info。
     *
     * @param array<string, mixed> $payload 归一后的请求参数。
     * @return bool true 表示至少提交了一个银行卡快照字段；false 表示不进入银行卡分支。
     */
    private function bankSnapshotRequested(array $payload): bool
    {
        return array_key_exists('bank_no', $payload)
            || array_key_exists('bank_name', $payload)
            || array_key_exists('bank_addr', $payload);
    }

    /**
     * 计算已审核银行卡的目标快照。
     *
     * 业务逻辑说明：
     * - 旧项目只在 bank_status=2 时更新正式银行卡字段；未提交的字段沿用当前已审核值。
     * - 返回值会同时用于 MT4 comment 和本地 user_auths 写入，保证两边使用同一份目标快照。
     *
     * @param array<string, mixed> $payload 归一后的请求参数。
     * @param UserAuth $auth 当前用户实名资料。
     * @return array{bank_no: string, bank_name: string, bank_addr: string} 目标银行卡快照。
     */
    private function targetBankSnapshot(array $payload, UserAuth $auth): array
    {
        return [
            'bank_no' => array_key_exists('bank_no', $payload)
                ? trim((string) $payload['bank_no'])
                : trim((string) $auth->bank_no),
            'bank_name' => array_key_exists('bank_name', $payload)
                ? trim((string) $payload['bank_name'])
                : trim((string) $auth->bank_name),
            'bank_addr' => array_key_exists('bank_addr', $payload)
                ? trim((string) $payload['bank_addr'])
                : trim((string) $auth->bank_addr),
        ];
    }

    /**
     * 判断 MT4 返回是否明确成功。
     *
     * @param array<string, mixed> $result MT4 Manager 返回值。
     * @return bool true 表示 status=ok 且 err 为空或等于 0；false 表示远端未确认成功。
     */
    private function isMt4Success(array $result): bool
    {
        if (strtolower(trim((string) ($result['status'] ?? 'error'))) !== 'ok') {
            return false;
        }

        return !array_key_exists('err', $result) || trim((string) $result['err']) === '0';
    }

    /**
     * 在本地事务失败后把 MT4 客户层级补偿回变更前快照。
     *
     * 业务逻辑说明：
     * - update_user 的 zip/cny 是幂等目标值设置，可以使用旧 parent_id 和旧关系码执行反向补偿。
     * - 补偿必须发生在数据库事务已经回滚之后，确保测试和日志看到的本地状态仍是旧层级。
     * - 补偿失败只记录 critical 日志并返回 false，原接口仍返回服务器错误，不能把不一致状态伪装成成功。
     *
     * @param int $userId 需要恢复 MT4 层级的普通客户业务用户 ID。
     * @param array<string, mixed> $hierarchyChange 包含 old_parent_id 和 old_hierarchy.relationship_code 的变更快照。
     * @return bool true 表示 MT4 明确确认补偿成功，false 表示补偿未成功。
     */
    private function compensateMt4Hierarchy(int $userId, array $hierarchyChange): bool
    {
        $oldParentId = (int) ($hierarchyChange['old_parent_id'] ?? 0);
        $oldRelationshipCode = (string) ($hierarchyChange['old_hierarchy']['relationship_code'] ?? '');
        if ($oldRelationshipCode === '') {
            Log::critical('后台普通客户上级代理 MT4 补偿缺少旧关系码。', [
                'user_id' => $userId,
                'old_parent_id' => $oldParentId,
            ]);

            return false;
        }

        try {
            $result = $this->mt4Manager->updateUserHierarchy($userId, $oldParentId, $oldRelationshipCode);
        } catch (Throwable $exception) {
            Log::critical('后台普通客户上级代理 MT4 补偿发生异常。', [
                'user_id' => $userId,
                'old_parent_id' => $oldParentId,
                'exception_class' => get_class($exception),
            ]);

            return false;
        }

        if ($this->isMt4Success($result)) {
            return true;
        }

        Log::critical('后台普通客户上级代理 MT4 补偿未获成功确认。', [
            'user_id' => $userId,
            'old_parent_id' => $oldParentId,
            'error_code' => (string) ($result['error_code'] ?? $result['err'] ?? 'provider_rejected'),
        ]);

        return false;
    }

    /**
     * 判断旧资料编辑请求是否真的要求修改用户密码。
     *
     * 参数逻辑说明：
     * - password/password1 为空表示没有提交新密码。
     * - ******** 是旧 Blade 编辑页用来遮蔽原密码的占位符，必须视为“不修改密码”。
     *
     * @param array<string, mixed> $payload 归一后的请求参数。
     * @return bool true 表示需要调用 UserPasswordService；false 表示跳过密码分支。
     */
    private function passwordChangeRequested(array $payload): bool
    {
        if (!array_key_exists('password', $payload)) {
            return false;
        }

        $password = trim((string) $payload['password']);

        return $password !== '' && $password !== '********';
    }

    /**
     * 生成用户资料更新审计日志内容。
     *
     * @param UserInfo $user 更新前的用户资料模型。
     * @param array<string, mixed> $updates 即将写入 user_infos 的字段和值。
     * @param bool $passwordChanged true 表示本次已通过 UserPasswordService 修改登录密码，日志只记录脱敏标识。
     * @param UserLogin|null $login 更新前的登录账号模型，用于记录 user_logins.email 变更。
     * @param array<string, mixed> $loginUpdates 即将写入 user_logins 的字段和值。
     * @param UserAuth|null $auth 更新前的实名资料模型，用于记录 user_auths 变更。
     * @param array<string, mixed> $authUpdates 即将写入 user_auths 的字段和值。
     * @return string operation_logs.content 文案，包含每个变更字段的新旧值。
     */
    private function userUpdateAuditContent(
        UserInfo $user,
        array $updates,
        bool $passwordChanged = false,
        UserLogin $login = null,
        array $loginUpdates = [],
        UserAuth $auth = null,
        array $authUpdates = []
    ): string
    {
        $changes = [];
        if ($passwordChanged) {
            $changes[] = 'password:changed';
        }

        foreach ($updates as $field => $newValue) {
            if ($field === 'updated_by') {
                continue;
            }
            $oldValue = (string) $user->{$field};
            $newValue = (string) $newValue;
            if ($oldValue === $newValue) {
                continue;
            }
            $changes[] = $field . ':' . $oldValue . '->' . $newValue;
        }

        if ($login !== null) {
            foreach ($loginUpdates as $field => $newValue) {
                $oldValue = (string) $login->{$field};
                $newValue = (string) $newValue;
                if ($oldValue === $newValue) {
                    continue;
                }
                $changes[] = 'login.' . $field . ':' . $oldValue . '->' . $newValue;
            }
        }

        if ($auth !== null) {
            foreach ($authUpdates as $field => $newValue) {
                $oldValue = (string) $auth->{$field};
                $newValue = (string) $newValue;
                if ($oldValue === $newValue) {
                    continue;
                }

                if (in_array($field, ['id_card_no', 'bank_no'], true)) {
                    $changes[] = 'auth.' . $field . ':changed';
                    continue;
                }

                $changes[] = 'auth.' . $field . ':' . $oldValue . '->' . $newValue;
            }
        }

        return 'Update user profile user_id:' . (int) $user->user_id
            . '; changes:' . (empty($changes) ? 'no_changes' : implode('; ', $changes));
    }

    /**
     * 构建已套用数据范围和页面筛选条件的用户查询。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return Builder 用户资料查询对象。
     */
    private function filteredUserQuery(Request $request): Builder
    {
        $query = UserInfo::query()->with(['login', 'auth']);

        if ($request->user('admin')) {
            $query = $this->adminDataScopeService->apply($query, $request->user('admin'), 'user', 'user_id');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('email')) {
            $email = $request->input('email');
            $query->whereHas('login', function (Builder $loginQuery) use ($email) {
                $loginQuery->where('email', 'LIKE', '%' . $email . '%');
            });
        }

        if ($request->filled('user_name')) {
            $query->where('user_name', 'LIKE', '%' . $request->input('user_name') . '%');
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', (int) $request->input('account_type'));
        }

        $this->applyUserCreatedAtDateFilter($query, $request);

        return $query;
    }

    /**
     * 给当前页用户行补齐交易统计字段。
     *
     * 这样做的原因：
     * - 旧项目的用户列表默认就展示入金、出金、净值、手续费、盈亏等统计列。
     * - 新项目如果只返回基础资料，前端表格就无法闭环展示旧项目页面。
     *
     * 返回值：
     * - 每一行在原有 user_infos/login/auth 基础上，额外带上 total_yuerj、total_yuecj、total_net_worth 等字段。
     *
     * @param array<int, UserInfo> $users 当前页用户模型列表。
     * @param Request $request 当前查询请求，用于读取开始/结束日期。
     * @return array<int, array<string, mixed>>
     */
    private function userListRowsWithStatistics(array $users, Request $request): array
    {
        $userIds = collect($users)->pluck('user_id')->map(function ($userId) {
            return (int) $userId;
        })->all();

        [$startDate, $endDate] = $this->statisticsDateRange($request);
        $statistics = $this->userStatisticsService->getBatchUserStatistics($userIds, $startDate, $endDate);

        return collect($users)->map(function (UserInfo $user) use ($statistics) {
            return array_merge(
                $user->toArray(),
                $statistics[(int) $user->user_id] ?? $this->emptyUserStatistics()
            );
        })->values()->all();
    }

    /**
     * 构建列表底部的汇总行。
     *
     * 解决的问题：
     * - Layui 和 CrmUI 需要一个统一的 footer 数据结构。
     * - 汇总行必须和当前筛选条件、当前管理员数据范围保持一致，避免前后端口径不一致。
     *
     * 返回值：
     * - user_id 返回“合计”，其余字段返回对应汇总值；没有数据时返回零值结构。
     *
     * @param Builder $query 当前用户筛选查询。
     * @param Request $request 当前查询请求。
     * @return array<string, mixed>
     */
    private function userListTotalRowWithStatistics(Builder $query, Request $request): array
    {
        $userIds = (clone $query)->pluck('user_infos.user_id')->map(function ($userId) {
            return (int) $userId;
        })->all();
        [$startDate, $endDate] = $this->statisticsDateRange($request);
        $summary = empty($userIds)
            ? []
            : $this->userStatisticsService->getSummaryStatistics($userIds, $startDate, $endDate);

        return [
            'user_id' => trans('systemlanguage.total'),
            'user_name' => '',
            'mt4_balance' => $summary['search_total_bal'] ?? '0.00',
            'mt4_equity' => $summary['search_total_eqy'] ?? '0.00',
            'total_yuerj' => $summary['search_total_yuerj'] ?? '0.00',
            'total_yuecj' => $summary['search_total_yuecj'] ?? '0.00',
            'total_net_worth' => $summary['search_total_net_worth'] ?? '0.00',
            'total_comm' => $summary['search_total_comm'] ?? '0.00',
            'total_profit' => $summary['search_total_profit'] ?? '0.00',
            'total_noble_metal' => $summary['search_total_noble_metal'] ?? 0,
            'total_for_exca' => $summary['search_total_for_exca'] ?? 0,
            'total_crud_oil' => $summary['search_total_crud_oil'] ?? 0,
            'total_index' => $summary['search_total_index'] ?? 0,
            'total_currency' => $summary['search_total_currency'] ?? 0,
            'total_stock' => $summary['search_total_stock'] ?? 0,
            'total_volume' => $summary['search_total_volume'] ?? 0,
            'total_swaps' => $summary['search_total_swaps'] ?? '0.00',
        ];
    }

    /**
     * 解析统计所用的日期范围。
     *
     * 只有开始日期和结束日期都存在时才启用统计过滤，避免只传单边日期时产生歧义。
     *
     * @param Request $request 当前查询请求。
     * @return array{0: string|null, 1: string|null}
     */
    private function statisticsDateRange(Request $request): array
    {
        if (!$request->filled('start_date') || !$request->filled('end_date')) {
            return [null, null];
        }

        return [$request->input('start_date'), $request->input('end_date')];
    }

    /**
     * 返回空统计结构。
     *
     * 作用：
     * - 保证没有交易数据的用户也能拿到完整字段，前端列不会因为字段缺失而报错。
     *
     * @return array<string, mixed>
     */
    private function emptyUserStatistics(): array
    {
        return [
            'total_comm' => '0.00',
            'total_yuerj' => '0.00',
            'total_yuecj' => '0.00',
            'total_volume' => 0,
            'total_swaps' => '0.00',
            'total_profit' => '0.00',
            'total_net_worth' => '0.00',
            'total_noble_metal' => 0,
            'total_for_exca' => 0,
            'total_crud_oil' => 0,
            'total_index' => 0,
            'total_currency' => 0,
            'total_stock' => 0,
        ];
    }

    /**
     * 校验用户列表和导出共用的创建日期筛选参数。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 start_date 和 end_date。
     * @return \Illuminate\Http\JsonResponse|null 日期非法时返回统一错误响应，否则返回 null。
     */
    private function validateUserDateFilter(Request $request)
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
     * 校验用户列表和导出共用的 user_id 筛选参数。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 user_id。
     * @return \Illuminate\Http\JsonResponse|null user_id 非法时返回统一错误响应，否则返回 null。
     */
    private function validateUserIdFilter(Request $request)
    {
        if (!$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验用户列表和导出共用的 account_type 筛选参数。
     *
     * @param Request $request 当前 HTTP 请求对象，读取 account_type。
     * @return \Illuminate\Http\JsonResponse|null account_type 非法时返回统一错误响应，否则返回 null。
     */
    private function validateAccountTypeFilter(Request $request)
    {
        if (!$request->filled('account_type')) {
            return null;
        }

        $validator = Validator::make(['account_type' => $request->input('account_type')], [
            'account_type' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 按用户创建时间应用日期范围筛选。
     *
     * @param Builder $query 用户查询构造器，目标表为 user_infos。
     * @param Request $request 当前 HTTP 请求对象，读取 start_date 和 end_date。
     * @return void
     */
    private function applyUserCreatedAtDateFilter(Builder $query, Request $request): void
    {
        if ($request->filled('start_date')) {
            $query->where('user_infos.created_at', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('user_infos.created_at', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 按当前后台管理员数据范围判断是否可以操作指定业务用户。
     *
     * 越权即失败：管理员数据范围外的一律返回 PERMISSION_DENIED，后续详情、审核、资料修改或启停逻辑不会继续执行。
     *
     * @param Request $request 当前请求对象，用于读取 admin guard 下的登录管理员。
     * @param int|string $userId 业务用户ID，不是后台管理员ID。
     * @return \Illuminate\Http\JsonResponse|null 返回 JsonResponse 表示拒绝访问；返回 null 表示允许继续执行业务逻辑。
     */
    private function denyUserAccessIfNeeded(Request $request, $userId)
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return null;
        }

        if ($this->adminDataScopeService->canAccessUser($admin, $userId, 'user')) {
            return null;
        }

        return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
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
}
