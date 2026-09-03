<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\AgentLevel;
use App\Models\CommissionRecord;
use App\Models\DepositRecord;
use App\Models\GroupConfig;
use App\Models\SystemConfig;
use App\Models\TransApplyLog;
use App\Models\UserInfo;
use App\Models\UserLoginLog;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use App\Services\FamilyTreeService;
use App\Services\CommissionTransfer\CommissionTransferService;
use App\Services\Legacy\LegacyFormIntentService;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use DomainException;
use Throwable;

/**
 * 前台代理管理控制器。
 *
 * 文件功能：
 * - 处理下级代理列表、直属客户列表、代理统计、等级确认、客户组别变更、用户详情和旧前台兼容入口。
 * - 所有代理与客户列表都从 agent_descendants、user_infos 等真实数据表读取，并通过 canViewUser 限制当前代理可见范围。
 * - 旧前台兼容入口保留旧参数名和响应结构，新版 Blade + Layui/Naive 页面继续复用同一套后端数据来源。
 *
 * 安全边界：
 * - 所有代理/客户列表与详情都以当前登录代理为根计算可见作用域（canViewUser / userScopeIds），
 *   请求参数（userId / username / puid 等）只能在作用域内收窄，不能扩大可见范围。
 * - 佣金转账与组别变更目标必须是当前代理的直属下级（isDirectTransferTarget），树内可见不等于直属可操作。
 * - 目标用户 ID 一律经 strictPositiveIntegerInput 严格解析，避免 (int) 强转把非法输入命中其他用户。
 * - 组别变更申请在事务内锁定客户行与目标组行，并发提交不会产生重复待审申请。
 */
class AgentController extends FrontBaseController
{
    /**
     * 代理树服务。
     *
     * @var FamilyTreeService
     */
    protected $familyTreeService;

    /**
     * 佣金转账服务：代理把可用佣金转账给直属下级的 Saga 业务封装（参数校验、幂等、状态推进全在其内部）。
     * 转账入口只做编排与错误码映射；缺失或被替换为直连写库实现会绕过幂等键与出账/入账流水约束，造成重复转账。
     *
     * @var CommissionTransferService
     */
    protected $commissionTransferService;

    /**
     * 构造前台代理管理控制器。
     *
     * @param FamilyTreeService $familyTreeService 代理树统计服务，用于汇总下级代理、客户数量和交易统计。
     */
    public function __construct(
        FamilyTreeService $familyTreeService,
        CommissionTransferService $commissionTransferService
    )
    {
        $this->familyTreeService = $familyTreeService;
        $this->commissionTransferService = $commissionTransferService;
    }

    /**
     * subList 用于返回当前代理可见的下级代理列表。
     *
     * 参数逻辑说明：
     * - parent_id 表示当前要展开查询的代理业务用户 ID；为空时默认查询当前登录代理。
     * - direct_only 表示是否只查询直属下级，1=直属代理，0 或未传=包含代理树全部下级。
     * - descendant_type=1 表示下级代理，对应 agent_descendants.descendant_type。
     * - userId、username、userstatus 表示旧前台代理列表筛选字段。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选参数、分页参数和当前代理身份。
     * @return JsonResponse 当前代理可见的下级代理分页列表。
     */
    public function subList(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $user->user_id;

        // 先保留现代 direct_only 请求语义，再由旧入口范围解析补充 userPId/user_pid 的直属规则。
        $directOnly = $request->has('direct_only') && $request->direct_only == 1;
        [$queryAgentId, $directOnly] = $this->legacyAgentParentScope($request, $agentId);
        if ($queryAgentId !== $agentId) {
            if (!$this->canViewUser($agentId, $queryAgentId)) {
                return $this->success(
                    ['list' => [], 'data' => [], 'count' => 0, 'totalRow' => []],
                    'response.query_success',
                    ResponseCode::SUCCESS
                );
            }
        }

        $descendantIds = FrontLegacyData::userScopeIds($queryAgentId, false, 1, $directOnly ? true : null);
        // 下级范围始终以查询根代理的代理树作用域为准，任何筛选参数都不能扩大该集合。
        $query = UserInfo::with(['login', 'level'])
            ->whereIn('user_id', $descendantIds)
            ->where('account_type', 1);

        if ($request->filled('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }
        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }
        if ($request->filled('username')) {
            $query->where('user_name', 'like', '%' . $request->input('username') . '%');
        }
        if ($request->filled('userstatus')) {
            $query->where('auth_status', (int) $request->input('userstatus'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $filteredIds = (clone $query)->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($filteredIds, $request, 'user_id');

        // 批量预取本页全部代理的下级/交易/资金统计：把原先每行 20+ 次查询（N+1）压缩为
        // 常数次批量查询，真实数据量下列表接口不再随页内行数线性变慢。
        $page = $query->orderBy('user_id')->paginate(FrontLegacyData::perPage($request));
        $pageAgentIds = collect($page->items())->map(function (UserInfo $agent) {
            return (int) $agent->user_id;
        })->values()->all();
        $hierarchyStatsMap = FrontLegacyData::batchSubAgentStats($pageAgentIds);
        $tradeStatsMap = FrontLegacyData::batchAgentStats($pageAgentIds);
        $financialStatsMap = FrontLegacyData::batchFinancialSummaryForAgents($pageAgentIds, $request);

        $list = $page->through(function (UserInfo $agent) use ($request, $queryAgentId, $hierarchyStatsMap, $tradeStatsMap, $financialStatsMap) {
            $agentId = (int) $agent->user_id;
            // 批量结果缺失（理论不应发生）时回退单查，保证口径与数据完整。
            $hierarchyStats = $hierarchyStatsMap[$agentId] ?? $this->familyTreeService->getSubAgentStats($agentId);
            $tradeStats = $tradeStatsMap[$agentId] ?? $this->familyTreeService->getAgentStats($agentId);
            $financialStats = $financialStatsMap[$agentId] ?? FrontLegacyData::userFinancialSummary($agent, $request, true);
            $isDirect = (int) $agent->parent_id === (int) $queryAgentId ? 1 : 0;
                $descendant = clone $agent;
                $descendant->setRelations([]);

                return array_merge(
                    FrontLegacyData::userBasicAlias($agent),
                    [
                        'depth' => $this->scopeDepth($agent, (int) $queryAgentId, $isDirect),
                        'is_direct' => $isDirect,
                        'descendant' => $descendant,
                        'stats' => array_merge($hierarchyStats, $tradeStats),
                        'agentsTotal' => (int) $hierarchyStats['total_agents'],
                        'accountTotal' => (int) $hierarchyStats['total_customers'],
                        'can_drill_agents' => (int) $hierarchyStats['total_agents'] > 0,
                        'can_drill_customers' => (int) $hierarchyStats['total_customers'] > 0,
                        'is_directly_sub' => $isDirect === 1,
                    ],
                    $financialStats
                );
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($list, $totalRow),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }

    /**
     * proxyListSearch 用于兼容旧前台代理列表搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，参数与 subList 保持一致。
     * @return JsonResponse 复用 subList 的下级代理列表响应。
     */
    public function proxyListSearch(Request $request): JsonResponse
    {
        return $this->subList($request);
    }

    /**
     * customerList 用于返回当前代理可见的下级客户列表。
     *
     * 参数逻辑说明：
     * - parent_id 表示当前要展开查询的代理业务用户 ID；为空时默认查询当前登录代理。
     * - direct_only 表示是否只查询直属客户，1=直属客户，0 或未传=包含代理树全部客户。
     * - descendant_type=2 表示普通客户，对应 agent_descendants.descendant_type。
     * - available_groups 表示当前代理可申请切换的客户组别，供客户列表快捷组别变更弹窗使用。
     *
     * @param Request $request 当前 HTTP 请求对象，承载客户筛选、分页和当前代理身份。
     * @return JsonResponse 当前代理可见的下级客户分页列表。
     */
    public function customerList(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $user->user_id;

        // 先保留现代 direct_only 请求语义，再由旧入口范围解析补充 userPId/user_pid 的直属规则。
        $directOnly = $request->has('direct_only') && $request->direct_only == 1;
        [$queryAgentId, $directOnly] = $this->legacyAgentParentScope($request, $agentId);
        if ($queryAgentId !== $agentId) {
            if (!$this->canViewUser($agentId, $queryAgentId)) {
                return $this->success(
                    ['list' => [], 'data' => [], 'count' => 0, 'totalRow' => []],
                    'response.query_success',
                    ResponseCode::SUCCESS
                );
            }
        }

        $descendantIds = FrontLegacyData::userScopeIds($queryAgentId, false, 2, $directOnly ? true : null);
        // 客户范围同样以查询根代理的代理树作用域为准，筛选参数不能扩大该集合。
        $query = UserInfo::with(['login', 'level'])
            ->whereIn('user_id', $descendantIds)
            ->where('account_type', 2);

        if ($request->filled('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }
        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }
        if ($request->filled('username')) {
            $query->where('user_name', 'like', '%' . $request->input('username') . '%');
        }
        if ($request->filled('userstatus')) {
            $query->where('auth_status', (int) $request->input('userstatus'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $filteredIds = (clone $query)->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($filteredIds, $request, 'mt4_login');

        $list = $query->orderBy('user_id')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $customer) use ($request, $queryAgentId) {
                $isDirect = (int) $customer->parent_id === (int) $queryAgentId ? 1 : 0;
                $descendant = clone $customer;
                $descendant->setRelations([]);

                return array_merge(
                    FrontLegacyData::userBasicAlias($customer),
                    [
                        'depth' => $this->scopeDepth($customer, (int) $queryAgentId, $isDirect),
                        'is_direct' => $isDirect,
                        'descendant' => $descendant,
                        'comm_trans' => '',
                        'change_group' => '',
                    ],
                    FrontLegacyData::userFinancialSummary($customer, $request, false)
                );
            });

        $userLogin = $this->legacyFrontUserLogin($request);

        return $this->success(
            FrontLegacyData::paginatedListResponse($list, $totalRow, [
                'available_groups' => $userLogin ? $this->availableGroupOptions($userLogin) : [],
            ]),
            'response.query_success',
            ResponseCode::SUCCESS
        );
    }

    /**
     * directCustListSearch 用于兼容旧前台直属客户列表搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，参数与 customerList 保持一致。
     * @return JsonResponse 只返回当前代理直属客户的分页列表。
     */
    public function directCustListSearch(Request $request): JsonResponse
    {
        $request->merge(['direct_only' => 1]);

        return $this->customerList($request);
    }

    /**
     * directUserCommTrans 用于兼容旧前台直属客户佣金转账入口。
     *
     * 参数逻辑说明：
     * - depositId 表示旧前台提交的目标用户 ID，兼容 sub_agent_id 与 userId。
     * - comm_money 表示旧前台提交的转账金额，兼容 amount。
     * - password 表示当前代理登录密码（用于 MT4 交易密码校验），由佣金转账 Saga 在资金命令前校验。
     * - DBCT 表示接收方入账流水，WBCT 表示当前代理出账流水。
     *
     * @param Request $request 当前 HTTP 请求对象，承载目标用户、金额、密码和当前代理身份。
     * @return JsonResponse 旧前台佣金转账响应结构。
     */
    public function directUserCommTrans(Request $request): JsonResponse
    {
        $userLogin = $this->legacyFrontUserLogin($request);
        if (!$userLogin) {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'LOGIN',
            ]);
        }
        if ((int) $userLogin->account_type !== 1) {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'NOTALLOW',
            ]);
        }

        $agentId = (int) $userLogin->user_id;
        $targetUserId = (int) $request->input('depositId', $request->input('sub_agent_id', $request->input('userId')));
        $rawAmount = $request->input('comm_money', $request->input('amount'));
        if (!is_scalar($rawAmount) || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', (string) $rawAmount)) {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'PARAM',
            ]);
        }
        // 金额按两位小数 USD 规整，整数部分超过 16 位直接拒绝，与 DECIMAL(18,2) 对齐避免浮点误差。
        [$amountWhole, $amountFraction] = array_pad(explode('.', (string) $rawAmount, 2), 2, '');
        if (strlen($amountWhole) > 16) {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'PARAM',
            ]);
        }
        $amount = $amountWhole . '.' . str_pad($amountFraction, 2, '0');
        $password = (string) $request->input('password', '');
        $remark = trim((string) $request->input('remark', ''));

        if ($targetUserId <= 0 || $amount <= 0) {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'PARAM',
            ]);
        }

        if ($password === '') {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'ErrorPassword',
            ]);
        }

        // 账号已进入只读/销户状态时禁止转出佣金，防止绕过资金冻结继续出金。
        $agentInfo = UserInfo::where('user_id', $agentId)->first();
        if (!$agentInfo || (int) $agentInfo->is_mt4_readonly === 1 || (int) $agentInfo->is_withdrawal_allowed === 1) {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'NOTALLOW',
            ]);
        }

        if (!UserInfo::where('user_id', $targetUserId)->exists() || !$this->isDirectTransferTarget($agentId, $targetUserId)) {
            return response()->json([
                'msg' => 'FAIL',
                'errorType' => 'NOTALLOW',
            ]);
        }

        $legacyRoute = $request->routeIs('legacy_user_proxy_commission_transfer');
        $idempotencyKey = $legacyRoute
            ? trim((string) $request->input('idempotency_key', ''))
            : trim((string) $request->header('Idempotency-Key', ''));
        if ($legacyRoute) {
            $headerKey = trim((string) $request->header('Idempotency-Key', ''));
            if ($headerKey === '' || !hash_equals($idempotencyKey, $headerKey)) {
                return response()->json([
                    'code' => ResponseCode::VALIDATION_FAILED,
                    'message' => __('response.validation_failed'),
                    'data' => (object) [],
                    'msg' => 'FAIL',
                    'errorType' => 'PARAM',
                ]);
            }
            try {
                $validIntent = app(LegacyFormIntentService::class)->validate(
                    $request,
                    'commission_transfer',
                    $agentId,
                    $idempotencyKey
                );
            } catch (\LogicException $exception) {
                $validIntent = false;
            }
            if (!$validIntent) {
                return response()->json([
                    'code' => ResponseCode::VALIDATION_FAILED,
                    'message' => __('response.validation_failed'),
                    'data' => (object) [],
                    'msg' => 'FAIL',
                    'errorType' => 'PARAM',
                ]);
            }
        } elseif (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $idempotencyKey)) {
            return response()->json([
                'code' => ResponseCode::VALIDATION_FAILED,
                'message' => __('response.validation_failed'),
                'data' => (object) [],
                'msg' => 'FAIL',
                'errorType' => 'PARAM',
            ]);
        }

        try {
            $result = $this->commissionTransferService->createOrRetrieve(
                $agentId,
                $targetUserId,
                $amount,
                $password,
                $remark,
                $legacyRoute ? 'legacy_commission_transfer' : 'front_customer_commission_transfer',
                $idempotencyKey
            );
        } catch (DomainException $exception) {
            return $this->legacyCommissionTransferDomainError($exception);
        } catch (Throwable $exception) {
            return response()->json([
                'code' => ResponseCode::SERVER_ERROR,
                'message' => __('response.server_error'),
                'data' => (object) [],
                'msg' => 'FAIL',
                'errorType' => 'MT4_data_no_sync',
            ]);
        }

        $transfer = $result['transfer'];
        $data = [
            'id' => (int) $transfer->id,
            'local_order_no' => (string) $transfer->local_order_no,
            'status' => (string) $transfer->status,
            'current_step' => (string) $transfer->current_step,
            'created' => (bool) $result['created'],
            'last_error_code' => (string) $transfer->last_error_code,
        ];
        if ((string) $transfer->status === 'completed') {
            return response()->json([
                'code' => 0,
                'message' => __('response.success'),
                'data' => (object) $data,
                'comm_money' => FrontLegacyData::money($transfer->source_balance_after),
                'msg' => $legacyRoute ? 'SUC' : 'SUCCESS',
            ]);
        }

        return $this->legacyCommissionTransferStateError(
            (string) $transfer->status,
            (string) $transfer->last_error_code,
            $data
        );
    }

    /**
     * 将佣金转账 Saga 抛出的领域异常映射为旧前台 FAIL 响应。
     *
     * transfer_target_not_allowed / transfer_not_allowed 表示目标或当前状态不允许转账，
     * 映射为 NOTALLOW 并返回权限错误；其余参数类错误统一映射为 PARAM。
     *
     * @param DomainException $exception 佣金转账领域异常，message 为内部错误码。
     * @return JsonResponse 旧前台 FAIL 响应结构，code/message/errorType 按错误类型映射。
     */
    private function legacyCommissionTransferDomainError(DomainException $exception): JsonResponse
    {
        $error = $exception->getMessage();
        $type = in_array($error, ['transfer_target_not_allowed', 'transfer_not_allowed'], true)
            ? 'NOTALLOW'
            : 'PARAM';

        return response()->json([
            'code' => $type === 'NOTALLOW' ? ResponseCode::PERMISSION_DENIED : ResponseCode::VALIDATION_FAILED,
            'message' => __($type === 'NOTALLOW' ? 'response.permission_denied' : 'response.validation_failed'),
            'data' => (object) [],
            'msg' => 'FAIL',
            'errorType' => $type,
        ]);
    }

    /**
     * 将佣金转账订单的失败状态映射为旧前台 FAIL 响应。
     *
     * - small_transfer_daily_limit 附带当日剩余秒数的 expire 字段，供旧前台倒计时提示。
     * - invalid_trade_password 映射为 ErrorPassword；rejected 状态按资金不足区分 INSUFFICIENT_BALANCE；
     * - 其余状态表示 MT4 未同步，统一返回 MT4_data_no_sync，避免伪造资金结果。
     *
     * @param string $status 转账订单当前状态，例如 pending/rejected。
     * @param string $error 最近一次失败错误码。
     * @param array<string, mixed> $data 原样透传给旧前台的订单信息。
     * @return JsonResponse 旧前台 FAIL 响应结构。
     */
    private function legacyCommissionTransferStateError(string $status, string $error, array $data): JsonResponse
    {
        if ($error === 'small_transfer_daily_limit') {
            return response()->json([
                'code' => 1013,
                'message' => __('response.operation_not_allowed'),
                'data' => (object) $data,
                'msg' => 'FAIL',
                'errorType' => '',
                'expire' => max(0, now()->diffInSeconds(now()->copy()->endOfDay(), false)),
            ]);
        }
        if ($error === 'invalid_trade_password') {
            $errorType = 'ErrorPassword';
            $code = ResponseCode::AUTH_FAILED;
        } elseif ($status === 'rejected') {
            $errorType = $error === 'insufficient_funds' ? 'INSUFFICIENT_BALANCE' : '_CONNECT_FAILED_';
            $code = $errorType === 'INSUFFICIENT_BALANCE'
                ? ResponseCode::INSUFFICIENT_BALANCE
                : ResponseCode::OPERATION_NOT_ALLOWED;
        } else {
            $errorType = 'MT4_data_no_sync';
            $code = ResponseCode::MT4_SYNC_FAILED;
        }

        return response()->json([
            'code' => $code,
            'message' => __($code === ResponseCode::AUTH_FAILED ? 'response.auth_failed' : 'response.operation_failed'),
            'data' => (object) $data,
            'msg' => 'FAIL',
            'errorType' => $errorType,
        ]);
    }

    /**
     * getSubAgentsGrpIdList 用于返回代理等级候选列表。
     *
     * 参数逻辑说明：
     * - agentGId 表示旧前台传入的当前代理等级 ID，兼容 level_id。
     * - agents_comm_prop 表示该等级的默认代理返佣比例。
     *
     * @param Request $request 当前 HTTP 请求对象，承载等级筛选参数。
     * @return JsonResponse 旧前台可直接使用的代理等级列表。
     */
    public function getSubAgentsGrpIdList(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentGroupId = (int) $request->input('agentGId', $request->input('level_id', 0));
        $query = AgentLevel::query();

        if ($agentGroupId > 0) {
            $query->where('id', '>=', $agentGroupId);
        }

        $agentList = $query->orderBy('id')
            ->get()
            ->map(function (AgentLevel $level) {
                return [
                    'id' => (int) $level->id,
                    'group_id' => (int) $level->id,
                    'level_id' => (int) $level->id,
                    'group_name' => $level->name,
                    'name' => $level->name,
                    'agents_comm_prop' => (float) $level->user_commission,
                    'user_commission' => (float) $level->user_commission,
                ];
            })
            ->values();

        return response()->json(['agentList' => $agentList]);
    }

    /**
     * getParentPath 用于返回旧前台代理层级路径 HTML。
     *
     * 参数逻辑说明：
     * - user_id/userId 表示要展示层级路径的目标用户业务 ID。
     * - event_name 表示旧前台 Layui 点击事件名称，默认 returnPreLevel。
     *
     * @param Request $request 当前 HTTP 请求对象，承载目标用户 ID、事件名和当前代理身份。
     * @return JsonResponse 旧前台层级路径 HTML 与节点数组。
     */
    public function getParentPath(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        $currentUserId = $user ? (int) $user->user_id : 0;
        $targetUserId = (int) $request->input('user_id', $request->input('userId'));
        $eventName = trim((string) $request->input('event_name', 'returnPreLevel'));

        if (!$user || (int) $user->account_type !== 1 || $targetUserId <= 0 || !$this->canViewUser($currentUserId, $targetUserId)) {
            return response()->json([
                'code' => 200,
                'msg' => 'SUCCESS',
                'data' => [
                    'path' => '',
                    'tree' => [],
                ],
            ]);
        }

        $target = UserInfo::where('user_id', $targetUserId)->first();
        $ids = $target ? $this->parentPathIds($target) : [];

        if (!in_array($targetUserId, $ids, true)) {
            $ids[] = $targetUserId;
        }

        $currentIndex = array_search($currentUserId, $ids, true);
        if ($currentIndex !== false) {
            $ids = array_slice($ids, $currentIndex);
        } elseif ($currentUserId !== $targetUserId) {
            array_unshift($ids, $currentUserId);
        }

        $users = UserInfo::whereIn('user_id', array_values(array_unique($ids)))
            ->get()
            ->keyBy('user_id');

        $tree = [];
        foreach ($ids as $id) {
            $user = $users->get($id);
            if (!$user) {
                continue;
            }

            $color = $this->legacyGroupColor((int) $user->group_id);
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
    }

    /**
     * directCustDetailList 用于返回指定代理的直属客户明细。
     *
     * 参数逻辑说明：
     * - puid 表示旧前台传入的父级代理用户 ID，兼容 parent_id 和 userId。
     * - userId、username、userstatus 表示旧前台客户明细筛选字段。
     *
     * @param Request $request 当前 HTTP 请求对象，承载父级代理、筛选、分页和当前代理身份。
     * @return JsonResponse 指定代理直属客户明细列表。
     */
    /**
     * 按 parent_id 向上补齐缺少 family_tree 时的旧代理层级路径。
     *
     * @param UserInfo $target 当前路径目标用户资料。
     * @return array<int, int> 从上级根节点到目标用户的业务用户 ID 链。
     */
    private function parentPathIds(UserInfo $target): array
    {
        $ids = [(int) $target->user_id];
        $parentId = (int) $target->parent_id;
        $visited = [(int) $target->user_id => true];

        while ($parentId > 0 && !isset($visited[$parentId])) {
            $ids[] = $parentId;
            $visited[$parentId] = true;
            $parentId = (int) UserInfo::where('user_id', $parentId)->value('parent_id');
        }

        return array_reverse($ids);
    }

    /**
     * 返回指定代理的直属客户明细列表。
     *
     * 安全边界：
     * - 父级代理 puid 必须位于当前登录代理的可见树内（canViewUser），请求参数只能在作用域内收窄。
     * - 列表固定 account_type=2（普通客户），不混入代理账号。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 puid/parent_id/userId 与筛选、分页参数。
     * @return JsonResponse 旧前台 code/msg/count/data/totalRow/list 结构的直属客户明细响应。
     */
    public function directCustDetailList(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user || (int) $user->account_type !== 1) {
            return response()->json([
                'code' => ResponseCode::SUCCESS,
                'msg' => 'SUCCESS',
                'count' => 0,
                'data' => [],
                'totalRow' => [],
            ]);
        }

        $currentUserId = (int) $user->user_id;
        $parentUserId = (int) $request->input('puid', $request->input('parent_id', $request->input('userId')));

        if ($parentUserId <= 0 || !$this->canViewUser($currentUserId, $parentUserId)) {
            return response()->json([
                'code' => ResponseCode::SUCCESS,
                'msg' => 'SUCCESS',
                'count' => 0,
                'data' => [],
                'totalRow' => [],
            ]);
        }

        $query = UserInfo::with(['login', 'level'])
            ->where('parent_id', $parentUserId)
            ->where('account_type', 2);

        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }
        if ($request->filled('username')) {
            $query->where('user_name', 'like', '%' . $request->input('username') . '%');
        }
        if ($request->filled('userstatus')) {
            $query->where('auth_status', (int) $request->input('userstatus'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $allIds = (clone $query)->pluck('user_id')->map(function ($id) {
            return (int) $id;
        })->all();
        $totalRow = FrontLegacyData::financialTotalRowForUserIds($allIds, $request, 'user_id');

        $list = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $user) use ($request) {
                return array_merge(
                    FrontLegacyData::userBasicAlias($user),
                    FrontLegacyData::userFinancialSummary($user, $request, false)
                );
            });

        return response()->json([
            'code' => ResponseCode::SUCCESS,
            'msg' => 'SUCCESS',
            'count' => $list->total(),
            'data' => $list->items(),
            'totalRow' => $totalRow,
            'list' => $list,
        ]);
    }

    /**
     * 兼容旧前台客户组别变更快速提交入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 grpName、userId 和旧前台备注参数。
     * @return JsonResponse 旧前台 SUCCESS/FAIL 响应结构。
     */
    public function changeDirectCustGroupEdit(Request $request): JsonResponse
    {
        $groupName = trim((string) $request->input('grpName', $request->input('group_name', '')));
        $group = GroupConfig::where('name', $groupName)->where('is_enabled', 1)->first();

        if (!$group) {
            return response()->json(['msg' => 'CLASSINVALID']);
        }

        $request->merge([
            'target_user_id' => $request->input('userId', $request->input('target_user_id')),
            'new_group_id' => (int) $group->id,
            'reason' => $request->input('reason', $request->input('apply_reason', $request->input('trans_apply_reason', ''))),
        ]);

        $response = $this->groupChange($request);
        $payload = $response->getData(true);

        return response()->json([
            'msg' => (int) ($payload['code'] ?? 0) === ResponseCode::SUCCESS ? 'SUCCESS' : 'FAIL',
        ]);
    }

    /**
     * statistics 用于返回当前代理统计数据。
     *
     * 参数逻辑说明：
     * - date_from 表示统计开始日期。
     * - date_to 表示统计结束日期。
     *
     * @param Request $request 当前 HTTP 请求对象，承载统计时间范围和当前代理身份。
     * @return JsonResponse 当前代理交易统计和代理树层级统计。
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $user->user_id;
        
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $stats = $this->familyTreeService->getAgentStats($agentId, $dateFrom, $dateTo);
        $hierarchy = $this->familyTreeService->getSubAgentStats($agentId);
        
        return $this->success(array_merge($stats, $hierarchy), 'response.query_success');
    }

    /**
     * userDetail 用于返回当前代理可见的单个用户详情。
     *
     * 参数逻辑说明：
     * - user_id/userId 表示要查看的代理或客户业务用户 ID。
     * - total_deposit、total_withdraw、total_rebate 表示该用户入金、出金和返佣汇总。
     * - profit_7d、profit_15d、profit_30d 表示近 7、15、30 天平仓收益。
     *
     * @param Request $request 当前 HTTP 请求对象，承载目标用户 ID 和当前代理身份。
     * @return JsonResponse 当前代理可见的用户详情响应。
     */
    public function userDetail(Request $request): JsonResponse
    {
        $targetUserId = $this->strictPositiveIntegerInput($request->input('user_id', $request->input('userId')));
        if ($targetUserId === null) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $currentUser = $this->legacyFrontUserLogin($request);
        if (!$currentUser) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $currentUser->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $currentUserId = (int) $currentUser->user_id;
        if (!$this->canViewUser($currentUserId, $targetUserId)) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $user = UserInfo::with(['login', 'level', 'groupConfig', 'parent', 'auth'])
            ->where('user_id', $targetUserId)
            ->first();
        if (!$user) {
            return $this->error(__('response.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $closedTrades = UserTrade::where('user_id', $targetUserId)->closed();
        $openTrades = UserTrade::where('user_id', $targetUserId)->open();

        return $this->success(array_merge(FrontLegacyData::userBasicAlias($user), $this->agentLevelDetailPayload($user), [
            'account_type_text' => (int) $user->account_type === 1 ? __('register.agent') : __('register.customer'),
            'group_name' => $user->groupConfig->name ?? '',
            'parent_name' => $user->parent->user_name ?? '',
            'country' => $user->country,
            'state' => $user->state,
            'city' => $user->city,
            'address' => $user->address,
            'id_card_no' => FrontLegacyData::maskIdCard((string) ($user->auth->id_card_no ?? '')),
            'auth_status_text' => (int) $user->auth_status === 1 ? __('front.status_verified') : __('front.status_unverified'),
            'total_deposit' => DepositRecord::where('user_id', $targetUserId)->sum('amount'),
            'total_withdraw' => WithdrawRecord::where('user_id', $targetUserId)->sum('apply_amount'),
            'total_rebate' => CommissionRecord::where('agent_id', $targetUserId)->sum('commission_amount'),
            'open_order_count' => (clone $openTrades)->count(),
            'closed_order_count' => (clone $closedTrades)->count(),
            'profit_7d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(7))->sum('profit'),
            'profit_15d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(15))->sum('profit'),
            'profit_30d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(30))->sum('profit'),
        ]), __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * agentLevelDetailPayload 用于只给代理账号返回等级字段。
     *
     * 参数逻辑说明：
     * - user 表示当前详情用户；account_type=1 才是代理账号，普通客户不得暴露代理等级字段。
     *
     * @param UserInfo $user 当前详情用户资料。
     * @return array<string, mixed> 代理等级展示字段；普通客户返回空数组。
     */
    private function agentLevelDetailPayload(UserInfo $user): array
    {
        if ((int) $user->account_type !== 1) {
            return [];
        }

        $rank = (int) ($user->level->level_code ?? $user->level_id ?? 5);
        if ($rank < 1 || $rank > 5) {
            $rank = 5;
        }

        return [
            'agent_level_rank' => $rank,
            'agent_level_name' => $user->level->name ?? ('Level ' . $rank),
        ];
    }

    /**
     * REST 风格用户详情入口，复用 userDetail。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param int $user 路由传入的目标业务用户 ID。
     * @return JsonResponse 当前代理可见的用户详情响应。
     */
    public function showUser(Request $request, int $user): JsonResponse
    {
        $request->merge(['user_id' => $user]);

        return $this->userDetail($request);
    }

    /**
     * userLoginHistory 用于返回当前代理可见用户的登录历史。
     *
     * 参数逻辑说明：
     * - user_id/userId 表示要查看登录历史的目标业务用户 ID。
     * - login_ip、ip_location、user_agent、created_at 表示旧前台风控查看所需登录信息。
     *
     * @param Request $request 当前 HTTP 请求对象，承载目标用户 ID 和当前代理身份。
     * @return JsonResponse 目标用户最近登录历史列表。
     */
    public function userLoginHistory(Request $request): JsonResponse
    {
        $targetUserId = $this->strictPositiveIntegerInput($request->input('user_id', $request->input('userId')));
        if ($targetUserId === null) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $currentUserId = (int) $user->user_id;
        if (!$this->canViewUser($currentUserId, $targetUserId)) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $logs = UserLoginLog::where('user_id', $targetUserId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function (UserLoginLog $log) {
                return [
                    'login_ip' => $log->login_ip,
                    'ip_location' => $log->ip_location,
                    'user_agent' => $log->user_agent,
                    'created_at' => FrontLegacyData::dateTime($log->created_at),
                ];
            })
            ->values();

        return $this->success([
            'user_id' => $targetUserId,
            'list' => $logs,
        ], __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 兼容旧前台用户详情弹层页面。
     *
     * 路由分支说明：
     * - user/cust/show_direct_cust_info/{role}/{uid} 必须重现旧直属客户 Blade 的资料字段、脱敏和登录历史入口。
     * - show/user_detail/{userId}/{role} 保留既有的汇总详情片段，避免无关旧页面的统计字段发生回归。
     *
     * @param Request $request 当前 HTTP 请求对象，用于读取当前代理身份和路由名称。
     * @param mixed $first 旧路由中的第一个参数，可能是 role 或用户 ID。
     * @param mixed $second 旧路由中的第二个参数，可能是用户 ID。
     * @return \Illuminate\Http\Response|\Illuminate\View\View 已授权时返回对应旧页面；无权限或不存在时终止为 403/404。
     */
    public function legacyUserDetailPage(Request $request, $first = null, $second = null)
    {
        $targetUserId = $this->legacyRouteUserId($first, $second);
        if ($targetUserId <= 0) {
            abort(404);
        }

        $currentUser = $this->legacyFrontUserLogin($request);
        if (!$currentUser || (int) $currentUser->account_type !== 1) {
            abort(403);
        }

        $currentUserId = (int) $currentUser->user_id;
        if (!$this->canViewUser($currentUserId, $targetUserId)) {
            abort(403);
        }

        $user = UserInfo::with(['login', 'level', 'groupConfig', 'parent', 'auth'])
            ->where('user_id', $targetUserId)
            ->first();
        if (!$user) {
            abort(404);
        }

        // 直属客户详情使用独立 Blade，严格保持旧页面字段契约，不与另一条历史汇总页面混用。
        if ($request->routeIs('legacy_user_customer_detail')) {
            return view('front.legacy.direct_customer_detail', [
                'fields' => $this->legacyDirectCustomerDetailFields($user),
                // 旧 Blade 使用严格的 $role == 'admin'，不得对 Admin/ADMIN 等变体扩大登录历史展示范围。
                'showLoginHistory' => (string) $first === 'admin',
                // 旧 WidgetPage 使用站内相对路径；保留该形式可避免部署域名变化影响弹层请求地址。
                'loginHistoryUrl' => '/user/cust/loginHistorySearch/' . (int) $user->user_id,
            ]);
        }

        $closedTrades = UserTrade::where('user_id', $targetUserId)->closed();
        $openTrades = UserTrade::where('user_id', $targetUserId)->open();
        $depositAmount = FrontLegacyData::money(DepositRecord::where('user_id', $targetUserId)->sum('amount'));
        $withdrawAmount = FrontLegacyData::money(WithdrawRecord::where('user_id', $targetUserId)->sum('apply_amount'));
        $rebateAmount = FrontLegacyData::money(CommissionRecord::where('agent_id', $targetUserId)->sum('commission_amount'));
        $profit7 = FrontLegacyData::money((clone $closedTrades)->where('close_time', '>=', now()->subDays(7)->format('Y-m-d H:i:s'))->sum('profit'));
        $profit15 = FrontLegacyData::money((clone $closedTrades)->where('close_time', '>=', now()->subDays(15)->format('Y-m-d H:i:s'))->sum('profit'));
        $profit30 = FrontLegacyData::money((clone $closedTrades)->where('close_time', '>=', now()->subDays(30)->format('Y-m-d H:i:s'))->sum('profit'));

        $html = '<div class="crm-legacy-detail">';
        $html .= '<style>.crm-legacy-detail{font-family:Arial,"Microsoft YaHei",sans-serif;padding:16px;color:#243042}.crm-legacy-detail h3{margin:0 0 12px;font-size:18px}.crm-legacy-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.crm-legacy-item{border:1px solid #e7edf3;border-radius:6px;padding:10px;background:#fff}.crm-legacy-label{font-size:12px;color:#708196}.crm-legacy-value{margin-top:4px;font-size:15px;font-weight:600;color:#1f2a37}.crm-legacy-bars{margin-top:14px}.crm-legacy-bar{display:flex;align-items:center;gap:8px;margin:8px 0}.crm-legacy-bar span{width:56px;color:#708196}.crm-legacy-bar i{height:8px;border-radius:999px;background:#4f8cff;display:block}</style>';
        $html .= '<h3>' . e($user->user_name ?: $user->user_id) . ' [' . (int) $user->user_id . ']</h3>';
        $html .= '<div class="crm-legacy-grid">';
        $html .= $this->legacyDetailItem('账户类型', (int) $user->account_type === 1 ? '代理' : '客户');
        if ((int) $user->account_type === 1) {
            $html .= $this->legacyDetailItem('代理等级', $user->level->name ?? ('Level ' . (int) $user->level_id));
        }
        $html .= $this->legacyDetailItem('交易组', trim((string) ($user->groupConfig->name ?? '')));
        $html .= $this->legacyDetailItem('上级代理', $user->parent ? $user->parent->user_name . ' [' . $user->parent->user_id . ']' : '');
        $html .= $this->legacyDetailItem('入金金额', $depositAmount);
        $html .= $this->legacyDetailItem('出金金额', $withdrawAmount);
        $html .= $this->legacyDetailItem('返佣金额', $rebateAmount);
        $html .= $this->legacyDetailItem('返佣比例', FrontLegacyData::money($user->comm_rate) . '%');
        $html .= $this->legacyDetailItem('开仓订单数', (clone $openTrades)->count());
        $html .= $this->legacyDetailItem('平仓订单数', (clone $closedTrades)->count());
        $html .= '</div>';
        $html .= '<div class="crm-legacy-bars">';
        $html .= $this->legacyProfitBar('7天', $profit7);
        $html .= $this->legacyProfitBar('15天', $profit15);
        $html .= $this->legacyProfitBar('30天', $profit30);
        $html .= '</div></div>';

        return response($html);
    }

    /**
     * 组装旧直属客户详情 Blade 的字段数据。
     *
     * 字段映射说明：
     * - total_funds、avail_margin、effective_credit 分别替代旧 user_money、available_bond_money、effective_cdt。
     * - trading_mode、auth_status、is_withdrawal_allowed 是新表收敛后的状态字段，需转换为旧页面原有中文文案。
     * - phone 与 login.email 在控制器内先完成脱敏，确保原始联系方式不会被模板、脚本或浏览器源码暴露。
     *
     * @param UserInfo $user 已完成登录、组别和上级关系预加载的目标用户资料。
     * @return array<int, array{label: string, name: string, value: string, wide?: bool}> 供 Blade 顺序渲染的旧字段列表。
     */
    private function legacyDirectCustomerDetailFields(UserInfo $user): array
    {
        $groupName = trim((string) $user->mt4_group);
        if ($groupName === '') {
            $groupName = trim((string) ($user->groupConfig->name ?? ''));
        }

        return [
            ['label' => '账户ID', 'name' => 'userid', 'value' => (string) $user->user_id],
            ['label' => '账户名称', 'name' => 'username', 'value' => (string) $user->user_name],
            ['label' => '上级ID', 'name' => 'parent_id', 'value' => (string) $user->parent_id],
            ['label' => '手机号码', 'name' => 'userphone', 'value' => $this->legacyMaskedPhone((string) $user->phone)],
            ['label' => 'E-mail', 'name' => 'useremail', 'value' => $this->legacyMaskedEmail((string) ($user->login->email ?? ''))],
            ['label' => '性别', 'name' => 'sex', 'value' => $this->legacyGenderText($user->gender)],
            ['label' => '账户余额', 'name' => 'usermoney', 'value' => $this->legacyDetailMoney($user->total_funds)],
            ['label' => '可用保证金', 'name' => 'availablebondmoney', 'value' => $this->legacyDetailMoney($user->avail_margin)],
            ['label' => '有效信用额', 'name' => 'effectivecdt', 'value' => $this->legacyDetailMoney($user->effective_credit)],
            ['label' => '账户模式', 'name' => 'transmode', 'value' => (int) $user->trading_mode === 1 ? '权益模式' : '佣金模式'],
            ['label' => '账户状态', 'name' => 'userstatus', 'value' => $this->legacyCustomerAuthenticationText($user)],
            ['label' => '出金状态', 'name' => 'isoutmoney', 'value' => (int) $user->is_withdrawal_allowed === 1 ? '不允许出金' : '允许出金'],
            ['label' => '客户组别', 'name' => 'mt4_grp', 'value' => $groupName],
            ['label' => '开户时间', 'name' => 'reccrtdate', 'value' => FrontLegacyData::dateTime($user->created_at)],
            ['label' => '备注', 'name' => 'userremark', 'value' => (string) $user->remark, 'wide' => true],
        ];
    }

    /**
     * 按旧手机号规则隐藏中间号码。
     *
     * @param string $phone 新表手机号；兼容“86-13912345678”和“13912345678”两种存量格式。
     * @return string 号码长度足够时返回前三位、五个星号和后三位；空值或异常短值不暴露原文。
     */
    private function legacyMaskedPhone(string $phone): string
    {
        $phone = trim($phone);
        $separator = strpos($phone, '-');
        if ($separator !== false) {
            $phone = substr($phone, $separator + 1);
        }

        if ($phone === '') {
            return '';
        }
        if (strlen($phone) <= 6) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 3) . '*****' . substr($phone, -3);
    }

    /**
     * 按旧邮箱规则显示前三位并隐藏本地部分剩余字符。
     *
     * @param string $email 用户登录邮箱。
     * @return string 有 @ 的邮箱返回“abc*****@domain”；缺少 @ 的异常值只保留前三位后加星号。
     */
    private function legacyMaskedEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        $atPosition = strpos($email, '@');
        if ($atPosition === false) {
            return substr($email, 0, 3) . '*****';
        }

        return substr($email, 0, 3) . '*****' . substr($email, $atPosition);
    }

    /**
     * 转换新表数字性别为旧页面中文文本。
     *
     * @param mixed $gender 新表 gender，1=男、2=女；兼容历史导入的“男”“女”文本。
     * @return string 旧详情页使用的性别文案，未知值返回“未设置”。
     */
    private function legacyGenderText($gender): string
    {
        if ((string) $gender === '男' || (int) $gender === 1) {
            return '男';
        }
        if ((string) $gender === '女' || (int) $gender === 2) {
            return '女';
        }

        return '未设置';
    }

    /**
     * 固定旧详情页资金字段的小数精度。
     *
     * @param mixed $value 用户余额、可用保证金或有效信用额。
     * @return string 两位小数的金额文本，例如 1234.50。
     */
    private function legacyDetailMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * 按旧直属客户详情页的三项审核条件生成认证文案。
     *
     * 条件映射说明：
     * - 旧 user_status=1 对应新 user_infos.auth_status=1，表示账户实名认证主状态通过。
     * - 旧 IDcard_status=2 对应新 user_auths.id_card_status=2，表示身份证审核通过。
     * - 旧 bank_status=2 对应新 user_auths.bank_status=2，表示银行卡审核通过。
     * - 任一条件不满足或认证资料不存在时均返回“未认证”，避免将未完成出金审核的客户误显示为已认证。
     *
     * @param UserInfo $user 已预加载 auth 关联的目标客户资料。
     * @return string “已认证”表示三项审核均通过；“未认证”表示至少一项未通过或资料不存在。
     */
    private function legacyCustomerAuthenticationText(UserInfo $user): string
    {
        $auth = $user->auth;
        $isAuthenticated = (int) $user->auth_status === 1
            && (int) ($auth->id_card_status ?? 0) === 2
            && (int) ($auth->bank_status ?? 0) === 2;

        return $isAuthenticated ? '已认证' : '未认证';
    }

    /**
     * 兼容旧前台用户登录历史搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数和当前代理身份。
     * @param int $uid 旧路由传入的目标业务用户 ID。
     * @return JsonResponse 旧前台表格使用的 rows/total 登录历史响应。
     */
    public function legacyLoginHistorySearch(Request $request, int $uid): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user || (int) $user->account_type !== 1) {
            return response()->json(['rows' => [], 'total' => 0]);
        }

        $currentUserId = (int) $user->user_id;
        if (!$this->canViewUser($currentUserId, $uid)) {
            return response()->json(['rows' => [], 'total' => 0]);
        }

        $query = UserLoginLog::where('user_id', $uid);
        $from = strtotime('-4 weeks 00:00:00');
        $to = strtotime(date('Y-m-d') . ' 23:59:59');
        $query->whereBetween('created_at', [$from, $to]);

        $total = (clone $query)->count();
        $rows = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->getCollection()
            ->map(function (UserLoginLog $log) {
                return [
                    // 旧 system_login_log.login_id 是业务用户号；新表 login_id 是认证表主键，必须回填 user_id 才能保持旧 Blade 语义。
                    'login_id' => $log->user_id,
                    'user_id' => $log->user_id,
                    // 旧表 login_id_desc 保存 IP 归属描述，新表将同一含义迁移为 ip_location。
                    'login_id_desc' => $log->ip_location,
                    'login_ip' => $log->login_ip,
                    'ip_location' => $log->ip_location,
                    'user_agent' => $log->user_agent,
                    'login_date' => FrontLegacyData::dateTime($log->created_at),
                    'created_at' => FrontLegacyData::dateTime($log->created_at),
                ];
            })
            ->values();

        return response()->json([
            'rows' => $rows,
            'total' => $total,
        ]);
    }

    /**
     * 严格解析前台目标业务用户 ID。
     *
     * 仅接受正整数或只含十进制数字的字符串；拒绝 `1abc`、浮点数、科学计数法、零和负数，
     * 避免在权限作用域校验前通过 `(int)` 强转命中其他用户。
     *
     * @param mixed $value 请求中的 user_id/userId。
     * @return int|null 合法正整数；非法时返回 null。
     */
    private function strictPositiveIntegerInput($value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);

        return $normalized === false ? null : (int) $normalized;
    }

    /**
     * canViewUser 用于判断当前代理是否可以查看目标用户。
     *
     * 参数逻辑说明：
     * - currentUserId 表示当前登录代理业务用户 ID。
     * - targetUserId 表示目标代理或客户业务用户 ID。
     *
     * @param int $currentUserId 当前登录代理业务用户 ID。
     * @param int $targetUserId 目标业务用户 ID。
     * @return bool true=允许查看，false=目标用户不在当前代理树中。
     */
    private function canViewUser(int $currentUserId, int $targetUserId): bool
    {
        if ($currentUserId === $targetUserId) {
            return true;
        }

        return in_array($targetUserId, FrontLegacyData::userScopeIds($currentUserId, false), true);
    }

    /**
     * isDirectTransferTarget 用于判断佣金转账目标是否为直属关系。
     *
     * @param int $agentId 当前代理业务用户 ID。
     * @param int $targetUserId 接收佣金转账的目标业务用户 ID。
     * @return bool true=直属下级或 parent_id 直属关系，false=不可转账。
     */
    private function isDirectTransferTarget(int $agentId, int $targetUserId): bool
    {
        return in_array($targetUserId, FrontLegacyData::userScopeIds($agentId, false, null, true), true);
    }

    /**
     * 旧前台代理层级路径颜色映射。
     *
     * @param int $groupId 用户组别 ID。
     * @return string 旧前台层级路径节点颜色。
     */
    private function legacyGroupColor(int $groupId): string
    {
        $colors = [
            1 => 'purple',
            2 => 'blueviolet',
            3 => 'mediumslateblue',
            4 => 'saddlebrown',
            5 => 'thistle',
        ];

        return $colors[$groupId] ?? 'black';
    }

    /**
     * 解析旧路由中可能错位的用户 ID 参数。
     *
     * @param mixed $first 旧路由第一个参数。
     * @param mixed $second 旧路由第二个参数。
     * @return int 解析出的目标业务用户 ID。
     */
    private function legacyRouteUserId($first, $second): int
    {
        if (is_numeric($first)) {
            return (int) $first;
        }

        return is_numeric($second) ? (int) $second : 0;
    }

    /**
     * 读取旧前台当前登录用户 ID。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @return int 当前登录业务用户 ID。
     */
    private function legacyCurrentUserId(Request $request): int
    {
        return $this->legacyFrontUserId($request);
    }

    /**
     * 构造旧前台用户详情弹层字段块。
     *
     * @param string $label 字段标题。
     * @param mixed $value 字段值，会经过 e() 转义。
     * @return string 旧前台详情 HTML 片段。
     */
    private function legacyDetailItem(string $label, $value): string
    {
        return '<div class="crm-legacy-item"><div class="crm-legacy-label">' . e($label) . '</div><div class="crm-legacy-value">' . e((string) $value) . '</div></div>';
    }

    /**
     * 构造旧前台用户详情收益条。
     *
     * @param string $label 时间范围标签，例如 7天、15天、30天。
     * @param float $value 对应时间范围的平仓收益。
     * @return string 旧前台收益条 HTML 片段。
     */
    private function legacyProfitBar(string $label, float $value): string
    {
        $width = min(100, max(8, abs($value)));

        return '<div class="crm-legacy-bar"><span>' . e($label) . '</span><i style="width:' . $width . '%"></i><strong>' . e((string) $value) . '</strong></div>';
    }

    /**
     * confirmLevel 用于返回待确认下级代理等级列表。
     *
     * 参数逻辑说明：
     * - userId 表示要筛选的下级代理业务用户 ID。
     * - current_level 表示当前代理自身等级。
     * - available_levels 表示可选代理等级列表。
     * - range_list 表示每个待确认代理可选择的等级和返佣比例。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选参数和当前代理身份。
     * @return JsonResponse 当前代理待确认下级代理等级列表。
     */
    public function confirmLevel(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $userInfo = $user->userInfo ?: UserInfo::where('user_id', $user->user_id)->first();
        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $level = AgentLevel::find($userInfo->level_id);
        
        $summary = [
            'current_level'     => $level,
            'is_confirmed'      => $userInfo->is_agent_confirmed,
            'commission_rate'   => $userInfo->comm_rate,
            'available_levels'  => AgentLevel::orderBy('level_code')->get(),
        ];

        $agentIds = FrontLegacyData::userScopeIds((int) $userInfo->user_id, false, 1, true);

        $listQuery = UserInfo::with(['login', 'level'])
            ->whereIn('user_id', array_values(array_unique($agentIds)))
            ->where('account_type', 1)
            ->where('is_agent_confirmed', 0);

        if ($request->filled('userId')) {
            $listQuery->where('user_id', (int) $request->input('userId'));
        }
        FrontLegacyData::applyCreatedAtFilter($listQuery, $request);

        $levels = AgentLevel::orderBy('level_code')->get();
        $list = $listQuery->orderBy('user_id')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserInfo $agent) use ($levels) {
                $row = FrontLegacyData::userBasicAlias($agent);
                $rank = (int) ($agent->level->level_code ?? $agent->level_id ?? 5);
                if ($rank < 1 || $rank > 5) {
                    $rank = 5;
                }
                $currentRate = (float) $agent->comm_rate;

                $row['level_id'] = (int) $agent->level_id;
                $row['comm_rate'] = $currentRate;
                $row['agent_level_rank'] = $rank;
                $row['agent_level_name'] = $agent->level->name ?? ('Level ' . $rank);
                $currentLevelId = (int) $agent->level_id;
                $row['range_list'] = $levels->map(function ($level) use ($agent, $currentLevelId) {
                    $rate = (float) $level->user_commission;
                    return [
                        'level_id' => (int) $level->id,
                        'level_name' => $level->name,
                        'prop' => $rate,
                        'user_min_prop' => (float) $agent->comm_rate,
                        'extra_val' => 0,
                        'def_gid' => $currentLevelId,
                        'choice_gid' => (int) $level->id,
                        'selected' => $currentLevelId > 0
                            ? (int) $level->id === $currentLevelId
                            : (string) $rate === (string) (float) $agent->comm_rate,
                    ];
                })->values();

                return $row;
            });

        return $this->success([
            'summary' => $summary,
            'list' => $list,
        ], 'response.query_success');
    }

    /**
     * 兼容旧前台代理等级确认搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，参数与 confirmLevel 保持一致。
     * @return JsonResponse 复用 confirmLevel 的等级确认列表响应。
     */
    public function proxyConfirmSearch(Request $request): JsonResponse
    {
        return $this->confirmLevel($request);
    }

    /**
     * confirmLevelChange 用于确认直属下级代理等级。
     *
     * 参数逻辑说明：
     * - userId 表示待确认的直属下级代理业务用户 ID。
     * - agent_gId 表示选择的代理等级 ID，对应 agent_levels.id。
     * - comm_prop 表示旧前台提交的返佣比例，仅兼容参数校验，真实比例以后端 agent_levels.user_commission 为准。
     * - extra_val 表示额外返佣比例增量。
     *
     * @param Request $request 当前 HTTP 请求对象，承载待确认代理、等级和当前代理身份。
     * @return JsonResponse 等级确认结果响应。
     */
    public function confirmLevelChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer',
            'comm_prop' => 'nullable|numeric',
            'agent_gId' => 'required|integer',
            'extra_val' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $user->user_id;
        $targetUserId = (int) $request->input('userId');
        $directAgentIds = FrontLegacyData::userScopeIds($agentId, false, 1, true);
        $isSubAgent = in_array($targetUserId, $directAgentIds, true);

        if (!$isSubAgent) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $level = AgentLevel::find((int) $request->input('agent_gId'));
        if (!$level) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $commissionRate = (float) $level->user_commission + (float) $request->input('extra_val', 0);
        $payload = [
            'is_agent_confirmed' => 1,
            'comm_rate' => $commissionRate,
            'level_id' => (int) $level->id,
        ];

        UserInfo::where('user_id', $targetUserId)->update($payload);

        return $this->success([], __('response.success'));
    }

    /**
     * groupChangeList 用于返回当前代理提交的客户组别变更申请列表。
     *
     * 参数逻辑说明：
     * - userId 表示申请中的目标客户业务用户 ID。
     * - groupId 表示申请切换的目标客户组别 ID。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选、分页和当前代理身份。
     * @return JsonResponse 客户组别变更申请列表和可选客户组。
     */
    public function groupChangeList(Request $request): JsonResponse
    {
        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $query = TransApplyLog::query()->where('applicant_id', (int) $user->user_id);

        if ($request->filled('userId')) {
            $query->where('user_id', (int) $request->input('userId'));
        }
        if ($request->filled('groupId')) {
            $query->where('group_id', (int) $request->input('groupId'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $list = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (TransApplyLog $log) {
                return [
                    'id' => $log->id,
                    'trans_uid' => $log->user_id,
                    'trans_type_gid' => trim((string) ($log->group_name ?? '')),
                    'trans_apply_status' => $log->status,
                    'trans_apply_reason' => $log->apply_reason ?: $log->reject_reason,
                    'rec_crt_date' => FrontLegacyData::dateTime($log->created_at),
                    'rec_upd_date' => FrontLegacyData::dateTime($log->updated_at),
                ];
            });

        return $this->success([
            'list' => $list,
            'available_groups' => $this->availableGroupOptions($user),
        ], __('response.query_success'), ResponseCode::SUCCESS);
    }

    /**
     * 兼容旧前台客户组别变更申请搜索入口。
     *
     * @param Request $request 当前 HTTP 请求对象，参数与 groupChangeList 保持一致。
     * @return JsonResponse 复用 groupChangeList 的组别变更申请列表响应。
     */
    public function directCustChangeListSearch(Request $request): JsonResponse
    {
        return $this->groupChangeList($request);
    }

    /**
     * groupChange 用于提交客户组别变更申请。
     *
     * 参数逻辑说明：
     * - target_user_id 表示申请切换组别的客户业务用户 ID。
     * - new_group_id 表示目标客户组别 ID，对应 group_configs.id。
     * - reason 表示代理提交组别变更申请的原因。
     *
     * @param Request $request 当前 HTTP 请求对象，承载目标客户、目标组别、申请原因和当前代理身份。
     * @return JsonResponse 新建的组别变更申请响应。
     */
    public function groupChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_user_id' => 'required|integer',
            'new_group_id'   => 'required|integer',
            'reason'         => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $user = $this->legacyFrontUserLogin($request);
        if (!$user) {
            return $this->legacyFrontAuthError($request);
        }
        if ((int) $user->account_type !== 1) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $agentId = (int) $user->user_id;
        $targetUserId = (int) $request->target_user_id;
        $newGroupId = (int) $request->new_group_id;

        $operatorName = $user->userInfo ? $user->userInfo->user_name : (string) $agentId;
        $reason = $request->input('reason', '');

        /**
         * 在同一事务内锁定客户资料并完成所有状态判断。
         *
         * 锁定原因：
         * - 两个并发请求若同时读到“没有待审核申请”，会重复写入转组申请。
         * - 所有转组入口最终都会经过本方法，锁定 user_infos 行可把同一客户的申请串行化。
         *
         * @return JsonResponse 成功返回新申请；失败返回业务错误码，事务内不写入申请日志。
         */
        return DB::transaction(function () use ($agentId, $targetUserId, $newGroupId, $operatorName, $reason): JsonResponse {
            // 先锁定客户资料，后续同组与待审核判断均以锁定后的当前状态为准。
            $targetInfo = UserInfo::where('user_id', $targetUserId)->lockForUpdate()->first();
            if (!$targetInfo) {
                return $this->error(__('response.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }
            if ((int) $targetInfo->account_type !== 2) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            // 转组是直属客户专属动作；canViewUser 允许整棵下级树，不能替代本处一层直属关系校验。
            if (!$this->isDirectTransferTarget($agentId, $targetUserId)) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            // 确认目标组别是真实启用的客户组，避免把代理组或失效组写入审核队列。
            $group = GroupConfig::where('id', $newGroupId)->where('is_enabled', 1)->lockForUpdate()->first();
            if (!$group || (Schema::hasColumn('group_configs', 'category') && (int) $group->category !== 2)) {
                return $this->error(__('response.invalid_group'), ResponseCode::VALIDATION_FAILED);
            }

            // 目标组与当前组一致时无需审核，直接拒绝无意义申请，保持旧 Blade 的提交限制。
            if ((int) $targetInfo->group_id === $newGroupId) {
                return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            // 一个客户同时只能有一条待审核转组记录，按客户维度锁住审核队列，避免不同申请人重复占用。
            $hasPendingApplication = TransApplyLog::where('user_id', $targetUserId)
                ->where('status', 0)
                ->lockForUpdate()
                ->exists();
            if ($hasPendingApplication) {
                return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            // 仅 MT4 交易命令 0-5 代表可持仓交易；余额类流水即使 close_time 为哨兵值也不能阻断转组。
            $hasOpenTrade = UserTrade::where('user_id', $targetUserId)
                ->open()
                ->whereIn('cmd', [0, 1, 2, 3, 4, 5])
                ->lockForUpdate()
                ->exists();
            if ($hasOpenTrade) {
                return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            // 基础字段按当前 co_crmv5 表结构写入；来自旧 hank_zl_data 的可选字段仅在字段存在时补充，兼容不同迁移进度环境。
            $applyData = [
                'user_id'        => $targetUserId,
                'group_id'       => $newGroupId,
                'group_name'     => $group->name,
                'applicant_id'   => $agentId,
                'applicant_name' => $operatorName,
                'status'         => 0,
                'reject_reason'  => '',
                'created_by'     => $operatorName,
            ];

            if (Schema::hasColumn('trans_apply_logs', 'origin_group_id')) {
                $applyData['origin_group_id'] = (int) $targetInfo->group_id;
            }
            if (Schema::hasColumn('trans_apply_logs', 'apply_reason')) {
                $applyData['apply_reason'] = $reason;
            } else {
                // 老环境没有独立申请原因字段时，暂存到 reject_reason，避免迁移未完成时丢失代理填写的原因。
                $applyData['reject_reason'] = $reason;
            }

            $apply = TransApplyLog::create($applyData);

            return $this->success($apply, __('response.success'));
        });
    }

    /**
     * 兼容旧前台客户组别变更统一提交入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 trans_uid、trans_type_gid、trans_apply_reason 等旧字段。
     * @return JsonResponse 复用 groupChange 的组别变更申请响应。
     */
    public function changeDirectCustGroupInfo(Request $request): JsonResponse
    {
        $request->merge([
            'target_user_id' => $request->input('target_user_id', $request->input('userId', $request->input('trans_uid'))),
            'new_group_id' => $request->input('new_group_id', $request->input('groupId', $request->input('trans_type_gid'))),
            'reason' => $request->input('reason', $request->input('apply_reason', $request->input('trans_apply_reason', ''))),
        ]);

        return $this->groupChange($request);
    }

    /**
     * 统一现代 parent_id 与旧页面 userPId/user_pid 的父级范围语义。
     * 旧别名来自“展开直属下级”动作，因此出现旧别名时默认只查一层；
     * 现代 parent_id 仍由 direct_only 显式决定是否递归，避免改变新 API 契约。
     *
     * @return array{0:int,1:bool} 查询根代理 ID 与是否仅查询直属下级。
     */
    private function legacyAgentParentScope(Request $request, int $currentAgentId): array
    {
        $directOnly = (int) $request->input('direct_only', 0) === 1;
        if ($request->filled('parent_id')) {
            return [(int) $request->input('parent_id'), $directOnly];
        }

        foreach (['userPId', 'user_pid'] as $legacyKey) {
            if ($request->filled($legacyKey)) {
                return [(int) $request->input($legacyKey), true];
            }
        }

        return [$currentAgentId, $directOnly];
    }

    /**
     * scopeDepth 用于在不直接读取闭包表时兼容旧列表的 depth 字段。
     *
     * @param UserInfo $user 当前列表行对应的下级用户。
     * @param int $ancestorId 当前展开节点的业务用户 ID。
     * @param int $isDirect 1=直属，0=非直属。
     * @return int 旧前台列表使用的层级深度，直属为 1，缺少 family_tree 时非直属按 2 兜底。
     */
    private function scopeDepth(UserInfo $user, int $ancestorId, int $isDirect): int
    {
        if ($isDirect === 1) {
            return 1;
        }

        return $this->parentScopeDepth($user, $ancestorId);
    }

    /**
     * 向上逐级回溯 parent_id 计算目标用户相对指定祖先的层级深度。
     *
     * family_tree 快照缺失或损坏时的兜底实现；visited 防止脏数据造成死循环，
     * 链路断裂或超过回溯上限时返回 2 作为旧列表的兜底深度。
     *
     * @param UserInfo $user 目标用户资料。
     * @param int $ancestorId 当前展开节点的业务用户 ID。
     * @return int 相对层级深度；链路不可达时返回 2。
     */
    private function parentScopeDepth(UserInfo $user, int $ancestorId): int
    {
        $depth = 0;
        $currentId = (int) $user->user_id;
        $parentId = (int) $user->parent_id;
        $visited = [$currentId => true];

        while ($parentId > 0 && !isset($visited[$parentId])) {
            $depth++;
            if ($parentId === $ancestorId) {
                return max(1, $depth);
            }

            $visited[$parentId] = true;
            $parentId = (int) UserInfo::where('user_id', $parentId)->value('parent_id');
        }

        return 2;
    }

    /**
     * availableGroupOptions 用于返回当前代理可申请切换的客户组别选项。
     *
     * 参数逻辑说明：
     * - user 表示当前登录代理账号；保留该参数用于后续按代理组名前缀收窄候选组。
     * - category=2 表示客户组别；如果迁移环境没有 category 字段，则回退到全部启用组。
     *
     * @param mixed $user 当前登录代理账号。
     * @return array<int, array<string, mixed>> 可选客户组别下拉选项。
     */
    private function availableGroupOptions($user): array
    {
        $query = GroupConfig::query()->where('is_enabled', 1);

        if (Schema::hasColumn('group_configs', 'category')) {
            $query->where('category', 2);
        }

        $groups = $query->orderBy('id')->get();
        if ($groups->isEmpty()) {
            $groups = GroupConfig::query()
                ->where('is_enabled', 1)
                ->orderBy('id')
                ->get();
        }

        return $groups->map(function (GroupConfig $group) {
            return [
                'value' => (int) $group->id,
                'label' => $group->name ?: ('Group #' . $group->id),
                'name' => $group->name,
                'category' => (int) ($group->category ?? 0),
                'radix' => $group->radix,
            ];
        })->values()->all();
    }
}
