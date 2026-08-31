<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:02
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Models\BigAgent;
use App\Models\GroupConfig;
use App\Models\UserLogin;
use App\Services\AdminDataScopeService;
use App\Services\UserPasswordService;
use App\Services\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 旧版后台遗留操作控制器。
 *
 * 文件功能：
 * - 实现旧项目 V3 中无等效 CRUD 端点的遗留操作（管理员状态变更、大代理状态变更、创建用户、创建代理、重置密码）。
 * - 保持旧项目 POST 参数命名兼容（如 id/status 而非 RESTful 参数）。
 *
 * 适用场景：
 * - 旧版后台管理界面通过 POST 表单调用这些遗留接口。
 * - 新版 Blade 后台若有兼容需求也可调用。
 *
 * 兼容语义：
 * - 保持旧项目 POST 参数命名（id/status、userphoneNo、againpassword、comm_type 等）；新字段名优先，旧字段名兜底。
 * - 旧请求可能把业务字段包在 data 子对象内，flattenLegacyPayload 展开后以顶层字段为准。
 * - 本控制器全部接口仍注册在后台 admin 路由组（jwt.auth:admin、sso:admin、check.permission:admin）内，
 *   旧协议请求同样经过该鉴权链路，不存在绕过路径。
 *
 * 入参例子（仅示意字段形状，不含真实值）：
 * - changeAdminStatus: {id=1, status=1}
 * - createUser: {username="zhangsan", email="user@example.com", password=<旧协议明文密码字段>, role=3}
 * - createAgent: {username="agent01", password=<旧协议明文密码字段>, parent_id=600001, group_id=1, level_id=1, comm_prop=50}
 * - resetUserPassword: {id=600002}
 *
 * 返回值：
 * - 成功时返回 {code:0, msg:"操作成功", data:null}。
 * - 失败时返回 {code:1001~1039, msg: 具体错误信息, data:null}。
 *
 * 失败语义：
 * - 数据范围之外的父代理/用户直接拒绝（PERMISSION_DENIED），不做部分写入。
 * - 重置密码以 MT4 侧同步结果为准，同步失败返回 MT4_SYNC_FAILED；邮件发送失败返回 EMAIL_SEND_FAILED
 *   并携带 password_changed=true，提示调用方密码已改但通知未送达。
 * - 管理员禁止停用自己，防止误操作后失去管理入口。
 * - 管理员/用户不存在返回 DATA_NOT_FOUND；参数验证失败返回 VALIDATION_FAILED；邮箱/手机号重复返回 1002。
 */

class LegacyAdminActionController extends AdminBaseController
{
    /**
     * 旧后台建档允许 parent_id 为空时落到的固定根代理业务 ID。
     * 与 seeder 的固定邀请代理基线一致：新模型用该根代理承载旧协议 parent_id=0 的一级代理关系；
     * 改成其他值会导致新建一级代理挂到不存在的父节点，数据范围校验与 agent_descendants 关系码解析随之失败。
     *
     * @var int
     */
    private const LEGACY_ROOT_AGENT_ID = 10;

    private UserRegistrationService $registrationService;
    private UserPasswordService $passwordService;
    private AdminDataScopeService $dataScopeService;

    /**
     * 构造旧版后台遗留操作控制器。
     *
     * @param UserRegistrationService $registrationService 注册服务，负责创建代理/用户账号的落库与业务校验。
     * @param UserPasswordService $passwordService 密码服务，负责重置密码并同步 MT4 侧结果。
     * @param AdminDataScopeService $dataScopeService 数据范围服务，用于父代理归属与目标账号越权校验。
     */
    public function __construct(
        UserRegistrationService $registrationService,
        UserPasswordService $passwordService,
        AdminDataScopeService $dataScopeService
    ) {
        $this->registrationService = $registrationService;
        $this->passwordService = $passwordService;
        $this->dataScopeService = $dataScopeService;
    }

    /**
     * 兼容旧后台 POST /changeAdminStatus：启停后台管理员。
     *
     * @param Request $request 承载 id=管理员ID、status=0 停用/1 启用。
     * @return \Illuminate\Http\JsonResponse 更新成功返回最新管理员信息；管理员不存在或禁止停用自己时返回对应错误码。
     */
    public function changeAdminStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $admin = Admin::find((int) $request->input('id'));
        if (!$admin) {
            return $this->error(__('admin.admin_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        // 禁止管理员停用自己，避免状态变更后失去当前管理会话入口。
        if ((int) optional($request->user('admin'))->getKey() === (int) $admin->getKey()
            && (int) $request->input('status') === 0) {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $admin->update(['status' => (int) $request->input('status')]);

        return $this->success($admin->fresh(), __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * 兼容旧后台 POST /changeBigAgentStatus：启停大代理。
     *
     * @param Request $request 承载 id=大代理ID、is_enabled=0 停用/1 启用。
     * @return \Illuminate\Http\JsonResponse 更新成功返回最新大代理信息；记录不存在时返回 DATA_NOT_FOUND。
     */
    public function changeBigAgentStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'is_enabled' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $agent = BigAgent::find((int) $request->input('id'));
        if (!$agent) {
            return $this->error(__('admin.big_agent_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $agent->update(['is_enabled' => (int) $request->input('is_enabled')]);

        return $this->success($agent->fresh(), __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * 兼容旧后台 POST /createAgent：创建代理账号（account_type=1）。
     *
     * @param Request $request 承载旧协议注册字段。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新账号信息；越权或注册失败返回对应错误码。
     */
    public function createAgent(Request $request)
    {
        return $this->createAccount($request, 1);
    }

    /**
     * 兼容旧后台 POST /createUser：创建普通用户账号（account_type=2）。
     *
     * @param Request $request 承载旧协议注册字段。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新账号信息；越权或注册失败返回对应错误码。
     */
    public function createUser(Request $request)
    {
        return $this->createAccount($request, 2);
    }

    /**
     * 兼容旧后台 POST /resetUserPassword：重置用户登录密码。
     *
     * 兼容语义：user_id/userId 与 password/password1 两组旧字段均可作为入参。
     * 失败语义：密码以 MT4 侧同步结果为准，同步失败返回 MT4_SYNC_FAILED；同步成功但通知邮件失败时
     * 返回 EMAIL_SEND_FAILED 并携带 password_changed=true，明确告知密码已改但通知未送达。
     *
     * @param Request $request 承载 user_id/userId 与 password/password1。
     * @return \Illuminate\Http\JsonResponse 重置成功返回成功响应；校验、越权、同步或邮件失败返回对应错误码。
     */
    public function resetUserPassword(Request $request)
    {
        $payload = $this->flattenLegacyPayload($request);
        $userId = $payload['user_id'] ?? $payload['userId'] ?? null;
        $password = $payload['password'] ?? $payload['password1'] ?? null;

        $validator = Validator::make([
            'user_id' => $userId,
            'password' => $password,
        ], [
            'user_id' => 'required|integer',
            'password' => 'required|string|min:6|max:100',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $login = UserLogin::where('user_id', (int) $userId)->first();
        if (!$login) {
            return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        // 目标账号类型决定数据范围口径（代理/用户），越权重置同样拒绝。
        $admin = $request->user('admin');
        $targetType = (int) $login->account_type === 1 ? 'agent' : 'user';
        if ($admin && !$this->dataScopeService->canAccessUser($admin, (int) $userId, $targetType)) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        // 密码变更以 MT4 侧同步结果为准，同步失败即失败关闭，不返回成功。
        if (!$this->passwordService->change($login, (string) $password)) {
            return $this->error(__('response.mt4_sync_failed'), ResponseCode::MT4_SYNC_FAILED);
        }

        // 密码已由 MT4 侧修改；邮件仅作通知，失败时明确告知调用方密码已改但通知未送达。
        try {
            Mail::raw(
                'Your account password was reset by an administrator. User ID: ' . (int) $userId
                    . '; temporary password: ' . (string) $password,
                static function ($message) use ($login): void {
                    $message->to($login->email)->subject('Account password reset');
                }
            );
        } catch (Throwable $exception) {
            return $this->error(__('response.email_send_failed'), ResponseCode::EMAIL_SEND_FAILED, [
                'password_changed' => true,
            ]);
        }

        return $this->success([], __('response.updated'), ResponseCode::UPDATED);
    }

    /**
     * 创建代理或用户账号的公共入口。
     *
     * @param Request $request 当前请求对象，承载旧协议注册字段。
     * @param int $accountType 目标账号类型；1=代理，2=普通用户。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新账号信息；父代理越权或注册失败返回对应错误码。
     */
    private function createAccount(Request $request, int $accountType)
    {
        $payload = $this->registrationPayload($request, $accountType);
        $groupError = $this->validateLegacyCustomerGroupSelection($payload, $accountType);
        if ($groupError !== null) {
            return $this->error($groupError, ResponseCode::VALIDATION_FAILED);
        }

        $parentId = isset($payload['inviter_id']) && (int) $payload['inviter_id'] > 0
            ? (int) $payload['inviter_id']
            : null;

        // 旧后台新增客户必须显式选择邀请代理，不能沿用前台注册的默认邀请人。
        if ($accountType === 2 && $parentId === null) {
            return $this->error(
                __('register.customer_inviter_required'),
                ResponseCode::VALIDATION_FAILED
            );
        }

        // 旧后台允许创建一级代理；新模型以固定根代理承载原 parent_id=0 的一级关系。
        if ($accountType === 1 && $parentId === null) {
            $parentId = self::LEGACY_ROOT_AGENT_ID;
        }

        // 父代理必须落在当前管理员数据范围内，防止跨代理树挂靠。
        $admin = $request->user('admin');
        if ($admin && $parentId && !$this->dataScopeService->canAccessUser($admin, $parentId, 'agent')) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        // 账号创建统一交给 UserRegistrationService，业务校验与落库逻辑不在此重复。
        try {
            $result = $this->registrationService->register($payload, $parentId, $accountType);
        } catch (Throwable $exception) {
            return $this->serverErrorResponse();
        }

        if (($result['success'] ?? false) !== true) {
            return $this->error(
                (string) ($result['message'] ?? __('response.validation_failed')),
                ResponseCode::VALIDATION_FAILED
            );
        }

        return $this->success($result['data'] ?? [], __('response.created'), ResponseCode::CREATED);
    }

    /**
     * 将旧协议字段映射为注册服务需要的 payload。
     *
     * @param Request $request 当前请求对象，承载旧字段名（userphoneNo、againpassword、userInviterId 等）。
     * @param int $accountType 目标账号类型。
     * @return array<string, mixed> 映射后的注册字段集合，新字段名优先。
     */
    private function registrationPayload(Request $request, int $accountType): array
    {
        $payload = $this->flattenLegacyPayload($request);
        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone === '' && !empty($payload['userphoneNo'])) {
            $phoneCode = trim((string) ($payload['modules'] ?? '86'));
            $phone = $phoneCode . '-' . trim((string) $payload['userphoneNo']);
        }

        $password = (string) ($payload['password'] ?? '');

        return [
            'email' => strtolower(trim((string) ($payload['email'] ?? $payload['useremail'] ?? ''))),
            'password' => $password,
            'password_confirmation' => (string) ($payload['password_confirmation'] ?? $payload['againpassword'] ?? $password),
            'user_name' => trim((string) ($payload['user_name'] ?? $payload['username'] ?? '')),
            'phone' => $phone,
            'id_card_no' => trim((string) ($payload['id_card_no'] ?? $payload['userIdcardNo'] ?? '')),
            'gender' => $payload['gender'] ?? $payload['sex'] ?? null,
            'address' => trim((string) ($payload['address'] ?? '')),
            'inviter_id' => $payload['inviter_id'] ?? $payload['userInviterId'] ?? $payload['parent_id'] ?? null,
            'account_type' => $accountType,
            'commission_mode' => (string) ($payload['commission_mode'] ?? $payload['comm_type'] ?? ''),
            'mt4_group' => trim((string) ($payload['mt4_group'] ?? $payload['usergrpName'] ?? '')),
            'legacy_group_id' => $payload['usergrpId'] ?? $payload['group_id'] ?? null,
        ];
    }

    /**
     * 展开旧协议可能存在的 data 嵌套参数。
     *
     * @param Request $request 当前请求对象。
     * @return array<string, mixed> 合并后的参数集合；顶层字段优先覆盖 data 内的同名键。
     */
    private function flattenLegacyPayload(Request $request): array
    {
        $payload = $request->all();
        $nested = $request->input('data');
        if (is_array($nested)) {
            // 旧请求可能把业务字段包在 data 子对象里；array_replace 以 request->all() 为准覆盖嵌套值。
            unset($payload['data']);
            $payload = array_replace($nested, $payload);
        }

        return $payload;
    }

    /**
     * 校验旧客户新增表单提交的组 ID/名称，未提交时保留注册服务的默认组兼容行为。
     *
     * @param array<string, mixed> $payload 已归一化的注册参数。
     * @param int $accountType 目标账号类型。
     * @return string|null 无错误时返回 null，否则返回可展示的错误消息。
     */
    private function validateLegacyCustomerGroupSelection(array $payload, int $accountType): ?string
    {
        if ($accountType !== 2) {
            return null;
        }

        $rawGroupId = $payload['legacy_group_id'] ?? null;
        $groupName = trim((string) ($payload['mt4_group'] ?? ''));
        $hasGroupId = $rawGroupId !== null && trim((string) $rawGroupId) !== '';
        $hasGroupName = $groupName !== '';
        if (!$hasGroupId && !$hasGroupName) {
            return null;
        }

        if (!$hasGroupId || !$hasGroupName || !ctype_digit((string) $rawGroupId) || (int) $rawGroupId <= 0) {
            return __('response.invalid_group');
        }

        $valid = GroupConfig::query()
            ->whereKey((int) $rawGroupId)
            ->where('name', $groupName)
            ->where('category', 2)
            ->where('is_enabled', 1)
            ->exists();

        return $valid ? null : __('response.invalid_group');
    }
}
