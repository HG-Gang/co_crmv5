<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 05:34
 */

namespace App\Services;

use App\Models\AgentLevel;
use App\Models\IdSequence;
use App\Models\UserAuth;
use App\Models\GroupConfig;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserMt4ProvisioningOutbox;
use App\Services\Registration\UserMt4ProvisioningPayload;
use App\Services\Registration\UserMt4ProvisioningProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * 用户注册服务。
 *
 * 文件功能：
 * - 统一处理前台代理商与普通客户注册，避免控制器直接拼装多张业务表。
 * - 根据注册账号类型生成代理商或普通客户业务 user_id。
 * - 写入 user_logins 登录账号、user_infos 业务资料和 user_auths 实名认证资料。
 * - 根据邀请人资料维护 family_tree 代理家族链。
 * - agent_descendants 表用于保存代理与下级用户的祖先后代关系，便于后续数据权限、团队统计和返佣查询。
 */
class UserRegistrationService
{
    /**
     * 构造用户注册服务。
     *
     * @param UserMt4ProvisioningProcessor|null $provisioningProcessor 仅为兼容旧调用方保留；注册链路不会调用处理器或 MT4 网关。
     */
    public function __construct(UserMt4ProvisioningProcessor $provisioningProcessor = null)
    {
        // 保留可选参数兼容现有调用方；注册链路禁止调用 MT4 处理器。
    }

    /**
     * 执行用户注册并在同一个数据库事务中写入登录、资料、认证和代理关系数据。
     *
     * 参数含义：
     * - $data 表示注册表单数据，至少包含 email、password、password_confirmation、user_name、phone、id_card_no。
     * - $parentId 表示邀请人的业务 user_id；代理商和普通客户都必须最终解析到有效邀请人。
     * - $accountType 表示注册账号类型，1 表示代理商，2 表示普通客户。
     * - $commissionMode 表示注册返佣模式，兼容 commission_mode 与旧参数 comm_type。
     * - $userId 表示新生成的业务用户 ID，分别来自 agent 或 customer 编号序列。
     * - $userLogin 表示写入 user_logins 的登录账号记录。
     * - $userInfo 表示写入 user_infos 的业务资料记录。
     * - $parentInfo 表示邀请人的业务资料，用于继承等级、组别、返佣比例和 family_tree。
     *
     * @param array<string, mixed> $data 注册表单数据，控制器传入的用户输入集合。
     * @param int|null $parentId 邀请人的业务 user_id，为空时从 $data['inviter_id'] 兼容读取。
     * @param int|null $accountType 注册账号类型，为空时从 $data['account_type'] 兼容读取。
     * @return array<string, mixed> 注册结果，包含 success、message、data、user_login 和 user_info。
     */
    public function register(array $data, int $parentId = null, int $accountType = null): array
    {
        $local = DB::transaction(function () use ($data, $parentId, $accountType) {
            $accountType = (int)($accountType ?: ($data['account_type'] ?? 0));
            $parentId = $parentId ?: (isset($data['inviter_id']) ? (int)$data['inviter_id'] : null);
            if ($accountType === 2 && !$parentId) {
                $parentId = 10;
            }

            $commissionMode = (string) ($data['commission_mode'] ?? $data['comm_type'] ?? '');
            $validationResult = $this->validateRegistrationData($data, $accountType, $parentId);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            if ($this->isEmailExists($data['email'])) {
                return [
                    'success' => false,
                    'message' => __('register.email_exists'),
                    'data' => [],
                ];
            }

            $parentInfo = null;
            if ($parentId) {
                $inviterInfo = $this->validateInviter($parentId, $accountType, $commissionMode);
                if (!$inviterInfo['valid']) {
                    return [
                        'success' => false,
                        'message' => $inviterInfo['message'],
                        'data' => [],
                    ];
                }
                $parentInfo = $inviterInfo['info'];
            }

            if (!$parentInfo) {
                return [
                    'success' => false,
                    'message' => $accountType === 1
                        ? __('register.agent_inviter_required')
                        : __('register.customer_valid_inviter_required'),
                    'data' => [],
                ];
            }

            $agentLevel = $this->resolveAgentLevel($parentInfo, $accountType);
            if ($accountType === 1 && !$agentLevel) {
                return [
                    'success' => false,
                    'message' => __('register.agent_level_not_found'),
                    'data' => [],
                ];
            }

            $mt4Group = $this->resolveMt4Group($data, $parentInfo, $accountType, $commissionMode);
            if (!$this->isSafeMt4ProtocolValue($mt4Group)) {
                return [
                    'success' => false,
                    'message' => __('response.invalid_group'),
                    'data' => [],
                ];
            }

            $userId = $this->generateUserId($accountType);
            $userLogin = $this->createUserLogin($data, $userId, $accountType);
            $userInfo = $this->createUserInfo(
                $data,
                $userId,
                $accountType,
                $userLogin->id,
                $parentInfo,
                $mt4Group,
                $agentLevel
            );
            $this->createUserAuth($data, $userId);

            $this->createAgentDescendantRows($userInfo);

            $payload = $this->buildMt4ProvisioningPayload($data, $userId, $parentId, $userInfo, $mt4Group);
            $securedPayload = UserMt4ProvisioningPayload::encrypt($payload);
            $outbox = UserMt4ProvisioningOutbox::create([
                'user_login_id' => $userLogin->id,
                'user_info_id' => $userInfo->id,
                'user_id' => $userId,
                'status' => 'pending',
                'attempts' => 0,
                'reconciliation_attempts' => 0,
                'payload_ciphertext' => $securedPayload['ciphertext'],
                'payload_hash' => $securedPayload['hash'],
                'available_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => __('register.success'),
                'data' => [
                    'user_id' => $userId,
                    'email' => $data['email'],
                ],
                'user_login' => $userLogin,
                'user_info' => $userInfo,
                'outbox_id' => (int) $outbox->id,
            ];
        });

        if (($local['success'] ?? false) !== true) {
            return $local;
        }

        return array_merge($local, [
            'success' => true,
            'message' => __('register.success'),
            'registered' => true,
            'provisioning_status' => 'pending',
        ]);
    }

    /**
     * 验证注册数据结构和基础业务约束。
     *
     * 参数含义：
     * - $data 表示注册表单数据，用于 Laravel Validator 校验字段格式和长度。
     * - $accountType 表示注册账号类型，只允许 1=代理商、2=普通客户。
     * - $parentId 表示邀请人的业务 user_id，普通客户注册时不能为空。
     *
     * @param array<string, mixed> $data 注册表单数据。
     * @param int $accountType 注册账号类型。
     * @param int|null $parentId 邀请人的业务 user_id。
     * @return array<string, mixed> 校验结果，success=false 时携带可直接展示的多语言 message。
     */
    private function validateRegistrationData(array $data, int $accountType, int $parentId = null): array
    {
        $validator = Validator::make($data, [
            'email' => 'required|email|max:191',
            'password' => ['required', 'string', 'min:6', 'confirmed', 'regex:/^[a-zA-Z][\s\S]*\d$/'],
            'user_name' => 'required|string|max:100',
            'phone' => 'required|string|max:50',
            'id_card_no' => 'required|string|max:50',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'gender' => 'nullable|in:1,2,male,female',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'data' => [],
            ];
        }

        foreach (['email', 'password', 'user_name', 'phone', 'id_card_no', 'country'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && preg_match('/[&=\r\n]/', $value) === 1) {
                return [
                    'success' => false,
                    'message' => __('validation.regex', ['attribute' => $field]),
                    'data' => [],
                ];
            }
        }

        if (!in_array($accountType, [1, 2], true)) {
            return [
                'success' => false,
                'message' => __('register.invalid_account_type'),
                'data' => [],
            ];
        }

        if ($accountType === 2 && !$parentId) {
            return [
                'success' => false,
                'message' => __('register.customer_inviter_required'),
                'data' => [],
            ];
        }

        if (UserInfo::where('phone', $data['phone'] ?? '')->exists()) {
            return [
                'success' => false,
                'message' => __('response.phone_exists'),
                'data' => [],
            ];
        }

        if (UserAuth::where('id_card_no', $data['id_card_no'] ?? '')->exists()) {
            return [
                'success' => false,
                'message' => __('response.id_card_exists'),
                'data' => [],
            ];
        }

        return ['success' => true];
    }

    /**
     * 检查邮箱是否已经存在于登录账号表。
     *
     * @param string $email 注册邮箱，对应 user_logins.email。
     * @return bool true 表示该邮箱已被注册，false 表示可以继续注册。
     */
    private function isEmailExists(string $email): bool
    {
        return UserLogin::where('email', $email)->exists();
    }

    /**
     * 验证邀请人是否存在且符合当前注册规则。
     *
     * 参数含义：
     * - $inviterId 表示邀请人的业务 user_id。
     * - $accountType 表示被注册账号类型，规则服务会按代理商或普通客户分别校验。
     * - $commissionMode 表示注册返佣模式，用于校验邀请链路是否允许当前模式。
     *
     * @param int $inviterId 邀请人的业务 user_id。
     * @param int $accountType 注册账号类型，默认按普通客户注册校验。
     * @param string $commissionMode 注册返佣模式。
     * @return array<string, mixed> 邀请人校验结果，valid=true 时包含 login 和 info。
     */
    private function validateInviter(int $inviterId, int $accountType = 2, string $commissionMode = ''): array
    {
        $rules = app(FrontRegisterRuleService::class)->validate($inviterId, $accountType, $commissionMode);
        if (!$rules['valid']) {
            return [
                'valid' => false,
                'message' => __($rules['message']),
            ];
        }

        return [
            'valid' => true,
            'login' => $rules['login'],
            'info' => $rules['info'],
        ];
    }

    /**
     * 按账号类型生成业务 user_id。
     *
     * @param int $accountType 注册账号类型，1=代理商时使用 agent 序列，2=普通客户时使用 customer 序列。
     * @return int 新生成的业务用户 ID。
     */
    private function generateUserId(int $accountType): int
    {
        $type = $accountType === 1 ? 'agent' : 'customer';
        return IdSequence::nextId($type);
    }

    /**
     * 创建登录认证记录。
     *
     * 参数含义：
     * - $data 表示注册表单数据，提供邮箱、明文密码和登录 IP。
     * - $userId 表示业务用户 ID，用于关联 user_infos 与 user_auths。
     * - $accountType 表示注册账号类型，写入 user_logins.account_type。
     *
     * @param array<string, mixed> $data 注册表单数据。
     * @param int $userId 业务用户 ID。
     * @param int $accountType 注册账号类型。
     * @return UserLogin 新创建的登录账号模型。
     */
    private function createUserLogin(array $data, int $userId, int $accountType): UserLogin
    {
        return UserLogin::create([
            'user_id' => $userId,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'account_type' => $accountType,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => $data['ip'] ?? '',
        ]);
    }

    /**
     * 创建用户业务资料。
     *
     * 参数含义：
     * - $data 表示注册表单数据，提供姓名、手机、地址、性别等资料字段。
     * - $userId 表示新生成的业务用户 ID。
     * - $accountType 表示注册账号类型，1=代理商，2=普通客户。
     * - $loginId 表示 user_logins 主键，用于 user_infos.login_id 关联登录账号。
     * - $parentInfo 表示邀请人的业务资料，为空时按顶级账号处理。
     * - $familyTree 表示代理家族链，保存从顶级代理到当前用户的 user_id 路径。
     *
     * @param array<string, mixed> $data 注册表单数据。
     * @param int $userId 业务用户 ID。
     * @param int $accountType 注册账号类型。
     * @param int $loginId user_logins 主键。
     * @param UserInfo|null $parentInfo 邀请人业务资料。
     * @return UserInfo 新创建的用户业务资料模型。
     */
    private function createUserInfo(
        array $data,
        int $userId,
        int $accountType,
        int $loginId,
        UserInfo $parentInfo = null,
        string $mt4Group = '',
        AgentLevel $agentLevel = null
    ): UserInfo
    {
        $parentId = $parentInfo ? (int)$parentInfo->user_id : 0;
        $hierarchy = app(FamilyTreeService::class)->resolveCustomerHierarchy($userId, $parentId);
        $familyTree = (string) $hierarchy['family_tree'];
        $gender = $this->normalizeGender($data['gender'] ?? 1);
        $group = GroupConfig::query()
            ->where('name', $mt4Group)
            ->where('category', $accountType)
            ->where('is_enabled', 1)
            ->orderBy('id')
            ->first();
        if (!$group) {
            $group = GroupConfig::query()
                ->where('name', $mt4Group)
                ->where('is_enabled', 1)
                ->orderBy('id')
                ->first();
        }

        $commissionRate = 0;
        if ($accountType === 1 && $agentLevel && $parentInfo) {
            $commissionRate = min((int) $parentInfo->comm_rate, (int) $agentLevel->max_commission);
        }

        return UserInfo::create([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $data['user_name'],
            'phone' => $data['phone'] ?? '',
            'gender' => $gender,
            'level_id' => $agentLevel ? (int) $agentLevel->id : 0,
            'group_id' => $group ? (int) $group->id : 0,
            'parent_id' => $parentId,
            'account_type' => $accountType,
            'family_tree' => $familyTree,
            'comm_rate' => $commissionRate,
            'leverage' => 100,
            'is_mt4_synced' => 0,
            'is_mt4_enabled' => 0,
            'is_mt4_readonly' => $accountType === 1 ? 1 : 0,
            'is_agent_confirmed' => $accountType === 1 ? 0 : 1,
            'original_group' => $mt4Group,
            'mt4_group' => $mt4Group,
            'trading_mode' => $parentInfo ? (int) $parentInfo->trading_mode : 0,
            'settle_method' => $parentInfo ? (int) $parentInfo->settle_method : 1,
            'settle_cycle' => $parentInfo ? (int) $parentInfo->settle_cycle : 0,
            'country' => $data['country'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'address' => $data['address'] ?? '',
            'data_source' => 0,
            'created_by' => 0,
            'updated_by' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function buildMt4ProvisioningPayload(
        array $data,
        int $userId,
        int $parentId = null,
        UserInfo $userInfo,
        string $mt4Group
    ): array {
        return [
            'user_id' => $userId,
            'name' => (string) $userInfo->user_name,
            'user_name' => (string) $userInfo->user_name,
            'password' => (string) $data['password'],
            'email' => (string) $data['email'],
            'phone' => (string) ($data['phone'] ?? ''),
            'id_card' => (string) ($data['id_card_no'] ?? ''),
            'parent_id' => (int) $userInfo->parent_id,
            'group' => $mt4Group,
            'country' => $this->buildLegacyRelationshipCode((int) $userInfo->parent_id),
            'leverage' => 100,
        ];
    }

    private function buildLegacyRelationshipCode(int $parentId): string
    {
        $slots = [1 => '0000', 2 => '0000', 3 => '0000', 4 => '0000', 7 => '0000'];
        $visited = [];

        while ($parentId > 0) {
            if (isset($visited[$parentId])) {
                throw new \InvalidArgumentException('注册上级代理链形成循环。');
            }
            if (count($visited) >= UserInfo::MAX_HIERARCHY_DEPTH) {
                throw new \InvalidArgumentException('注册上级代理链超过安全深度限制。');
            }

            $visited[$parentId] = true;
            $info = UserInfo::where('user_id', $parentId)->first();
            if (!$info || (int) $info->account_type !== 1) {
                throw new \InvalidArgumentException('注册上级代理链包含无效节点。');
            }

            $levelCode = (int) AgentLevel::whereKey((int) $info->level_id)->value('level_code');
            if ($levelCode <= 0) {
                $levelCode = (int) $info->level_id;
            }

            $slot = $levelCode >= 5 ? 7 : $levelCode;
            if (isset($slots[$slot])) {
                $slots[$slot] = (string) $info->user_id;
            }

            $parentId = (int) $info->parent_id;
        }

        return implode('', $slots);
    }

    /**
     * 按优先级解析注册用户的 MT4 组。
     *
     * 取值顺序：请求显式 mt4_group/group/user_grp_name → 邀请人组配置（须与账号类型匹配且启用）→
     * 邀请人 mt4_group 继承 → 邀请人组配置的配对组 → 该账号类型的默认启用组；全部缺失时返回空字符串。
     *
     * @param array<string, mixed> $data 注册表单数据，可能携带 mt4_group/group/user_grp_name。
     * @param UserInfo|null $parentInfo 邀请人业务资料，用于继承组与返佣链路。
     * @param int $accountType 注册账号类型，1=代理商，2=普通客户，决定可选的组类别。
     * @return string 解析出的 MT4 组名，可能为空字符串。
     */
    private function resolveMt4Group(
        array $data,
        UserInfo $parentInfo = null,
        int $accountType,
        string $commissionMode = ''
    ): string
    {
        foreach (['mt4_group', 'group', 'user_grp_name'] as $key) {
            $explicit = trim((string) ($data[$key] ?? ''));
            if ($explicit !== '') {
                return $explicit;
            }
        }

        $parentGroup = null;
        if ($parentInfo && (int) $parentInfo->group_id > 0) {
            $parentGroup = GroupConfig::whereKey((int) $parentInfo->group_id)->first();
        }
        $hasCommission = strtoupper(trim($commissionMode)) === 'A' ? 0 : 1;
        if ($parentGroup && (int) $parentGroup->category === $accountType
            && (int) $parentGroup->has_commission === $hasCommission
            && (int) $parentGroup->is_enabled === 1) {
            return trim((string) $parentGroup->name);
        }
        if ($parentInfo && (int) $parentInfo->account_type === $accountType
            && $parentGroup && (int) $parentGroup->has_commission === $hasCommission) {
            $inherited = trim((string) $parentInfo->mt4_group);
            if ($inherited !== '') {
                return $inherited;
            }
        }

        if ($parentGroup && (int) $parentGroup->pair_id > 0) {
            $paired = GroupConfig::whereKey((int) $parentGroup->pair_id)->first();
            if ($paired && (int) $paired->category === $accountType
                && (int) $paired->is_enabled === 1) {
                return trim((string) $paired->name);
            }
        }

        $default = GroupConfig::query()
            ->where('category', $accountType)
            ->where('is_enabled', 1)
            ->where('has_commission', $hasCommission)
            ->where('is_default', 1)
            ->orderBy('id')
            ->first();
        if (!$default) {
            $default = GroupConfig::query()
                ->where('category', $accountType)
                ->where('is_enabled', 1)
                ->where('has_commission', $hasCommission)
                ->orderBy('id')
                ->first();
        }
        if (!$default) {
            $default = GroupConfig::query()
                ->where('category', $accountType)
                ->where('is_enabled', 1)
                ->where('is_default', 1)
                ->orderBy('id')
                ->first();
        }

        return $default ? trim((string) $default->name) : '';
    }

    private function resolveAgentLevel(UserInfo $parentInfo = null, int $accountType): ?AgentLevel
    {
        if ($accountType !== 1 || !$parentInfo) {
            return null;
        }

        $parentLevelCode = (int) AgentLevel::whereKey((int) $parentInfo->level_id)->value('level_code');
        if ($parentLevelCode <= 0) {
            return null;
        }

        $nextLevelCode = $parentLevelCode === 4 ? 5 : $parentLevelCode + 1;

        return AgentLevel::where('level_code', $nextLevelCode)->first();
    }

    /**
     * 校验 MT4 组名可用于协议传输。
     *
     * 组名不能为空且不能包含 &、=、回车换行，防止注册参数经 MT4 协议拼接时注入额外字段。
     *
     * @param string $value 待校验的 MT4 组名。
     * @return bool true=可以安全传输，false=必须拒绝。
     */
    private function isSafeMt4ProtocolValue(string $value): bool
    {
        return trim($value) !== '' && preg_match('/[&=\r\n]/', $value) !== 1;
    }

    /**
     * 创建实名认证资料记录。
     *
     * 参数含义：
     * - $data 表示注册表单数据，提供真实姓名和证件号码。
     * - $userId 表示业务用户 ID，用于 user_auths.user_id 关联注册用户。
     *
     * @param array<string, mixed> $data 注册表单数据。
     * @param int $userId 业务用户 ID。
     * @return UserAuth 新创建的实名认证模型。
     */
    private function createUserAuth(array $data, int $userId): UserAuth
    {
        $payload = [
            'user_id' => $userId,
            'id_card_no' => $data['id_card_no'],
            'id_card_status' => 0,
            'bank_status' => 0,
        ];
        if (Schema::hasColumn('user_auths', 'real_name')) {
            $payload['real_name'] = $data['user_name'];
        }

        return UserAuth::create($payload);
    }

    /**
     * 根据 parent_id 权威拓扑同步代理后代关系。
     *
     * family_tree 是派生快照，不能作为注册闭包写入的事实源；祖先链重新沿 parent_id 解析，
     * 遇到孤儿、非代理父级或循环时抛出异常并由注册事务整体回滚。
     *
     * @param UserInfo $userInfo 新注册用户的业务资料模型。
     * @return void
     */
    private function createAgentDescendantRows(UserInfo $userInfo): void
    {
        // $treeIds 表示 family_tree 拆分后的用户链路；注册闭包实际以 parent_id 重新解析，避免快照污染关系。
        $hierarchy = app(FamilyTreeService::class)->resolveCustomerHierarchy(
            (int) $userInfo->user_id,
            (int) $userInfo->parent_id
        );
        $ancestorIds = array_values(array_map('intval', $hierarchy['ancestor_ids']));
        if ($ancestorIds === []) {
            return;
        }

        $ancestorCount = count($ancestorIds);
        $now = time();
        foreach ($ancestorIds as $index => $agentId) {
            DB::table('agent_descendants')->updateOrInsert(
                [
                    'agent_id' => $agentId,
                    'descendant_id' => (int) $userInfo->user_id,
                ],
                [
                    'descendant_type' => (int) $userInfo->account_type,
                    'is_direct' => ((int) $userInfo->parent_id === $agentId) ? 1 : 0,
                    'depth' => $ancestorCount - $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * 将旧项目字符串性别或新页面数字性别统一转为 1/2。
     *
     * @param mixed $gender 表单性别值，支持 female、2、1 等旧新页面输入。
     * @return int 标准性别值，1=男或默认值，2=女。
     */
    private function normalizeGender($gender): int
    {
        if ($gender === 'female' || (string)$gender === '2') {
            return 2;
        }

        return 1;
    }

    /**
     * 注册前置验证，供控制器在真正写库前复用。
     *
     * 参数含义：
     * - $data 表示注册表单数据，用于检查邮箱、手机、身份证号是否重复。
     * - $parentId 表示邀请人的业务 user_id，存在时需要继续校验邀请人规则。
     * - $accountType 表示注册账号类型，传给邀请人规则服务判断注册边界。
     * - $commissionMode 表示注册返佣模式，用于兼容旧项目返佣注册规则。
     *
     * @param array<string, mixed> $data 注册表单数据。
     * @param int|null $parentId 邀请人的业务 user_id。
     * @param int $accountType 注册账号类型。
     * @param string $commissionMode 注册返佣模式。
     * @return array<int, string> 注册前置错误消息列表，空数组表示可继续提交注册。
     */
    public function validateRegistration($data, $parentId = null, int $accountType = 2, string $commissionMode = ''): array
    {
        $errors = [];

        if (!empty($data['email']) && $this->isEmailExists($data['email'])) {
            $errors[] = __('register.email_exists');
        }
        if (!empty($data['phone']) && UserInfo::where('phone', $data['phone'])->exists()) {
            $errors[] = __('response.phone_exists');
        }
        if (!empty($data['id_card_no']) && UserAuth::where('id_card_no', $data['id_card_no'])->exists()) {
            $errors[] = __('response.id_card_exists');
        }

        if ($parentId) {
            $inviter = $this->validateInviter((int) $parentId, $accountType, $commissionMode);
            if (!$inviter['valid']) {
                $errors[] = $inviter['message'];
            }
        }

        return $errors;
    }
}
