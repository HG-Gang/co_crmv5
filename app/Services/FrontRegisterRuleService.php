<?php

namespace App\Services;

use App\Models\UserInfo;
use App\Models\UserLogin;

/**
 * Port of legacy RegisterEnMiddleware / RegisterGmtkCnEnMiddleware invite rules.
 */
class FrontRegisterRuleService
{
    public function validate(int $inviterId, int $accountType = 2, string $commissionMode = ''): array
    {
        $login = UserLogin::where('user_id', $inviterId)->first();
        $info = UserInfo::where('user_id', $inviterId)->first();

        if (!$login || !$info) {
            return ['valid' => false, 'message' => 'register.inviter_not_found'];
        }

        if (!$login->isActive()) {
            return ['valid' => false, 'message' => 'register.inviter_disabled'];
        }

        if ((int) $info->account_type !== 1) {
            return ['valid' => false, 'message' => 'register.inviter_not_agent'];
        }

        if ($commissionMode !== '' && strtoupper($commissionMode) !== 'A') {
            return ['valid' => false, 'message' => 'register.invalid_commission_mode'];
        }

        if ($accountType === 1) {
            if ((int) $info->group_id >= 7) {
                return ['valid' => false, 'message' => 'register.inviter_no_agent_invite'];
            }
            if (!(int) $info->is_agent_confirmed) {
                return ['valid' => false, 'message' => 'register.inviter_level_unconfirmed'];
            }
        }

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
