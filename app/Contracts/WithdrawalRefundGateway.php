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
 * 提现退款网关契约（接口）。
 *
 * 文件功能：
 * - 定义“提现失败/取消时向用户账户退回金额”的接口，供提现结算流程
 *   依赖注入调用。
 *
 * 适用场景：
 * - 提现结算 outbox 事件类型为 withdraw_refund 时调用：将已扣减但未成功
 *   出金的金额退回用户账户。
 *
 * 实现者：
 * - app/Services/Withdrawal/Mt4WithdrawalRefundGateway。
 * 调用方：
 * - 出金退款出箱任务（Jobs/RefundWithdrawFunding）：传输异常时同样走
 *   unknown 结果，由任务统一落库。
 *
 * 入参例子：
 * - refund(10001, '800.00', 'withdrawal refund');
 *
 * 返回值：
 * - WithdrawalFundingResult：退款结果对象，含成功/失败状态与失败原因。
 *
 * 失败语义契约：
 * - 退款失败不抛异常，以结果对象失败标记表达；调用方必须检查结果状态，
 *   未成功退回时按 outbox 机制重试，防止资金悬空。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 退款失败时由实现将失败原因封装进结果对象返回。
 */
namespace App\Contracts;

use App\Services\Withdrawal\WithdrawalFundingResult;

interface WithdrawalRefundGateway
{
    /**
     * 向用户账户退回提现金额。
     *
     * @param int $userId 用户主键 ID。
     * @param string $amount 退款金额（字符串十进制，如 '800.00'）。
     * @param string $comment MT4 交易备注（comment）。
     * @return WithdrawalFundingResult 退款结果；失败以结果对象失败标记表达、
     *         不抛异常，调用方需据此决定重试或告警。
     */
    public function refund(int $userId, string $amount, string $comment): WithdrawalFundingResult;
}
