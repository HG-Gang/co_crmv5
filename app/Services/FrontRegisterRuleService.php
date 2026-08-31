<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 00:43
 */

namespace App\Services;

use App\Models\AgentLevel;
use App\Models\UserInfo;
use App\Models\UserLogin;

/**
 * 前台注册邀请规则服务。
 *
 * 文件功能：
 * - 复用旧项目前台注册中间件的邀请人校验规则。
 * - 代理商和普通客户注册都必须通过本服务确认邀请人是否存在、是否启用、是否为代理账号。
 * - message 返回 register 语言包 key，由控制器或上层服务通过 __() 转换为当前语言文案。
 */
class FrontRegisterRuleService
{
    /**
     * 校验注册邀请关系是否允许继续提交。
     *
     * 参数含义：
     * - $inviterId 表示邀请人的业务 user_id，对应 user_logins.user_id 和 user_infos.user_id。
     * - $accountType 表示被注册账号类型，1=代理商，2=普通客户。
     * - $commissionMode 表示注册返佣模式，空字符串表示普通注册，A 表示旧项目零佣金模式。
     * - $login 表示邀请人的登录账号记录，用于判断账号是否存在且启用。
     * - $info 表示邀请人的业务资料记录，用于判断账号类型、组别、代理确认状态和返佣比例。
     *
     * @param int $inviterId 邀请人的业务 user_id。
     * @param int $accountType 被注册账号类型。
     * @param string $commissionMode 注册返佣模式。
     * @return array<string, mixed> 邀请校验结果；valid=false 时 message 为 register 语言包 key。
     */
    public function validate(int $inviterId, int $accountType = 2, string $commissionMode = ''): array
    {
        // 邀请人登录记录与业务资料必须同时存在，任一缺失视为无效邀请人。
        $login = UserLogin::where('user_id', $inviterId)->first();
        $info = UserInfo::where('user_id', $inviterId)->first();

        if (!$login || !$info) {
            return ['valid' => false, 'message' => 'register.inviter_not_found'];
        }

        // 被禁用账号不能作为邀请人。
        if (!$login->isActive()) {
            return ['valid' => false, 'message' => 'register.inviter_disabled'];
        }

        // 只有代理账号能邀请下级，普通客户邀请一律拒绝。
        if ((int) $info->account_type !== 1) {
            return ['valid' => false, 'message' => 'register.inviter_not_agent'];
        }

        // 返佣模式只允许空（普通注册）或 A（旧项目零佣金模式）。
        if ($commissionMode !== '' && strtoupper($commissionMode) !== 'A') {
            return ['valid' => false, 'message' => 'register.invalid_commission_mode'];
        }

        // 注册代理商时额外校验邀请能力：旧项目约定组别 7 及以上不可再邀请下级，且代理等级必须先确认。
        if ($accountType === 1) {
            $levelCode = (int) AgentLevel::whereKey((int) $info->level_id)->value('level_code');
            if ($levelCode >= 5) {
                return ['valid' => false, 'message' => 'register.inviter_no_agent_invite'];
            }
            if (!(int) $info->is_agent_confirmed) {
                return ['valid' => false, 'message' => 'register.inviter_level_unconfirmed'];
            }
        }

        // 零佣金模式要求邀请人返佣比例高于 50，否则没有可返还给下级的比例空间。
        if (strtoupper($commissionMode) === 'A' && (float) $info->comm_rate <= 50) {
            return ['valid' => false, 'message' => 'register.inviter_no_zero_commission'];
        }

        return [
            'valid' => true,
            'message' => 'register.inviter_valid',
            'inviter_name' => $info->user_name,
            'account_type' => (int) $info->account_type,
            'comm_rate' => (float) $info->comm_rate,
            'info' => $info,
            'login' => $login,
        ];
    }
}
