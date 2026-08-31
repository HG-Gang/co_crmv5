<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:09
 */

declare(strict_types=1);

/**
 * 提现出金网关契约（接口）。
 *
 * 文件功能：
 * - 定义“从用户 MT4 账户扣减提现金额（出金）”的接口，供提现结算流程
 *   依赖注入调用。
 *
 * 适用场景：
 * - 提现结算 outbox 事件类型为 withdraw_debit 时调用，从用户账户扣减出金金额。
 *
 * 实现者：
 * - app/Services/Withdrawal/Mt4WithdrawalFundingGateway。
 * 调用方：
 * - 出金打款出箱任务（Jobs/ProcessWithdrawFunding）：声明任务后调用扣款，
 *   并按结果状态落库。
 *
 * 入参例子：
 * - withdraw(10001, '800.00', 'withdrawal debit');
 *
 * 返回值：
 * - WithdrawalFundingResult：出金结果对象，含成功/失败状态与失败原因。
 *
 * 失败语义契约：
 * - 出金失败不抛异常，以结果对象失败标记表达；调用方必须检查结果状态，
 *   未成功扣款时按 outbox 机制重试，不能按已出金推进打款。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 出金失败（余额不足、超时等）由实现将失败原因
 *   封装进结果对象返回。
 */
namespace App\Contracts;

use App\Services\Withdrawal\WithdrawalFundingResult;

interface WithdrawalFundingGateway
{
    /**
     * 从用户账户执行出金扣减。
     *
     * @param int $userId 用户主键 ID。
     * @param string $amount 出金金额（字符串十进制，如 '800.00'）。
     * @param string $comment MT4 交易备注（comment）。
     * @return WithdrawalFundingResult 出金结果；失败以结果对象失败标记表达、
     *         不抛异常，调用方需据此决定重试或转入退款。
     */
    public function withdraw(int $userId, string $amount, string $comment): WithdrawalFundingResult;
}
