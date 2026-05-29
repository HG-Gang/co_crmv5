<?php

namespace App\Services;

use App\Models\AgentDescendant;
use App\Models\IdSequence;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserLogin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * 用户注册服务 | User Registration Service
 *
 * 该服务集中处理前台代理商、普通客户注册时涉及的核心业务：
 * - 生成代理商/客户业务 user_id。
 * - 创建 user_logins 登录认证数据。
 * - 创建 user_infos 用户业务资料。
 * - 根据邀请人维护 family_tree 家族链。
 * - 将所有上级代理与当前用户写入 agent_descendants，便于后续权限、统计、返佣查询。
 */
class UserRegistrationService
{
    /**
     * 执行用户注册。
     *
     * @param array    $data        表单提交数据，至少包含 email/password/user_name/account_type。
     * @param int|null $parentId    邀请人业务 user_id；普通客户必须提供，代理商可为空。
     * @param int|null $accountType 账户类型：1=代理商，2=普通客户。
     * @return array
     */
    public function register(array $data, ?int $parentId = null, ?int $accountType = null): array
    {
        return DB::transaction(function () use ($data, $parentId, $accountType) {
            $accountType = (int)($accountType ?: ($data['account_type'] ?? 0));
            $parentId = $parentId ?: (isset($data['inviter_id']) ? (int)$data['inviter_id'] : null);

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

            if ($accountType === 2 && !$parentInfo) {
                return [
                    'success' => false,
                    'message' => '普通客户必须提供有效邀请人ID',
                    'data' => [],
                ];
            }

            $userId = $this->generateUserId($accountType);
            $userLogin = $this->createUserLogin($data, $userId, $accountType);
            $userInfo = $this->createUserInfo($data, $userId, $accountType, $userLogin->id, $parentInfo);
            $this->createUserAuth($data, $userId);

            $this->createAgentDescendantRows($userInfo);

            return [
                'success' => true,
                'message' => '注册成功',
                'data' => [
                    'user_id' => $userId,
                    'email' => $data['email'],
                ],
                'user_login' => $userLogin,
                'user_info' => $userInfo,
            ];
        });
    }

    /**
     * 验证注册数据结构和基础业务约束。
     *
     * @param array    $data        注册数据。
     * @param int      $accountType 账户类型。
     * @param int|null $parentId    邀请人业务 user_id。
     * @return array
     */
    private function validateRegistrationData(array $data, int $accountType, ?int $parentId): array
    {
        $validator = Validator::make($data, [
            'email' => 'required|email|max:191',
            'password' => 'required|string|min:6|confirmed',
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

        if (!in_array($accountType, [1, 2], true)) {
            return [
                'success' => false,
                'message' => '账户类型无效',
                'data' => [],
            ];
        }

        if ($accountType === 2 && !$parentId) {
            return [
                'success' => false,
                'message' => '普通客户必须填写邀请人ID',
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
                'message' => __('front.id_card_no') . ' already exists',
                'data' => [],
            ];
        }

        return ['success' => true];
    }

    /**
     * 检查邮箱是否存在。
     *
     * @param string $email 邮箱。
     * @return bool
     */
    private function isEmailExists(string $email): bool
    {
        return UserLogin::where('email', $email)->exists();
    }

    /**
     * 验证邀请人是否存在且可用。
     *
     * @param int $inviterId 邀请人业务 user_id。
     * @return array
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
     * 生成业务 user_id。
     *
     * @param int $accountType 1=代理商，2=普通客户。
     * @return int
     */
    private function generateUserId(int $accountType): int
    {
        $type = $accountType === 1 ? 'agent' : 'customer';
        return IdSequence::nextId($type);
    }

    /**
     * 创建登录认证记录。
     *
     * @param array $data        注册数据。
     * @param int   $userId      业务 user_id。
     * @param int   $accountType 账户类型。
     * @return UserLogin
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
     * @param array         $data        注册数据。
     * @param int           $userId      业务 user_id。
     * @param int           $accountType 账户类型。
     * @param int           $loginId     user_logins 主键。
     * @param UserInfo|null $parentInfo  邀请人资料。
     * @return UserInfo
     */
    private function createUserInfo(array $data, int $userId, int $accountType, int $loginId, ?UserInfo $parentInfo): UserInfo
    {
        $parentId = $parentInfo ? (int)$parentInfo->user_id : 0;
        $parentTree = $parentInfo ? trim((string)$parentInfo->family_tree, ',') : '';
        $familyTree = $parentTree ? $parentTree . ',' . $userId : (string)$userId;
        $gender = $this->normalizeGender($data['gender'] ?? 1);

        return UserInfo::create([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $data['user_name'],
            'phone' => $data['phone'] ?? '',
            'gender' => $gender,
            'level_id' => $parentInfo ? (int)$parentInfo->level_id : 0,
            'group_id' => $parentInfo ? (int)$parentInfo->group_id : 0,
            'parent_id' => $parentId,
            'account_type' => $accountType,
            'family_tree' => $familyTree,
            'comm_rate' => $parentInfo ? min((int)$parentInfo->comm_rate, (int)($data['comm_rate'] ?? $parentInfo->comm_rate)) : (int)($data['comm_rate'] ?? 0),
            'is_agent_confirmed' => $accountType === 1 ? 1 : 0,
            'country' => $data['country'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'address' => $data['address'] ?? '',
            'data_source' => 0,
            'created_by' => 0,
            'updated_by' => 0,
        ]);
    }

    private function createUserAuth(array $data, int $userId): UserAuth
    {
        return UserAuth::create([
            'user_id' => $userId,
            'real_name' => $data['user_name'],
            'id_card_no' => $data['id_card_no'],
            'id_card_status' => 0,
            'bank_status' => 0,
        ]);
    }

    /**
     * 根据 family_tree 同步代理后代关系。
     *
     * @param UserInfo $userInfo 新注册用户资料。
     * @return void
     */
    private function createAgentDescendantRows(UserInfo $userInfo): void
    {
        $treeIds = array_values(array_filter(array_map('intval', explode(',', (string)$userInfo->family_tree))));
        $selfIndex = array_search((int)$userInfo->user_id, $treeIds, true);

        if ($selfIndex === false || $selfIndex === 0) {
            return;
        }

        $ancestorIds = array_slice($treeIds, 0, $selfIndex);
        foreach ($ancestorIds as $index => $agentId) {
            AgentDescendant::updateOrCreate(
                [
                    'agent_id' => $agentId,
                    'descendant_id' => $userInfo->user_id,
                ],
                [
                    'descendant_type' => $userInfo->account_type,
                    'is_direct' => ((int)$userInfo->parent_id === $agentId) ? 1 : 0,
                    'depth' => $selfIndex - $index,
                ]
            );
        }
    }

    /**
     * 将旧项目字符串性别或新页面数字性别统一转为 1/2。
     *
     * @param mixed $gender 表单性别值。
     * @return int
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
     * @param array    $data     注册数据。
     * @param int|null $parentId 邀请人业务 user_id。
     * @return array
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
            $errors[] = __('front.id_card_no') . ' already exists';
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
