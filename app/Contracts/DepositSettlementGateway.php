<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */

declare(strict_types=1);

/**
 * 入金结算网关契约（接口）。
 *
 * 文件功能：
 * - 定义“将入金金额实际计入用户账户”的接口，供入金结算流程依赖注入调用。
 *
 * 适用场景：
 * - 入金结算 outbox 事件类型为 deposit_settlement 时调用，
 *   在第三方支付回调确认后把充值金额记入用户 MT4 账户。
 *
 * 实现者：
 * - app/Services/Payment/Mt4DepositSettlementGateway。
 * 调用方：
 * - 入金结算出箱任务（Jobs/SettleDepositPayment）、后台批量金额导入
 *   （BatchAmountImportController）、爆仓清零（AdminWhsExpZeroController）及
 *   旧佣金结算（LegacyCommissionSummaryService）。
 *
 * 入参例子：
 * - deposit(10001, '1000.00', 'deposit settlement');
 *
 * 返回值：
 * - DepositSettlementResult：结算结果对象，含成功/失败状态。
 *
 * 失败语义契约：
 * - 入金失败不抛异常，以结果对象失败标记表达；调用方必须检查结果状态，
 *   未成功入账时按 outbox 机制重试，不能按已入账推进后续流程。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 执行失败时由实现将失败原因封装进结果对象返回。
 */
namespace App\Contracts;

use App\Services\Payment\DepositSettlementResult;

interface DepositSettlementGateway
{
     /**
      * 将入金金额计入用户账户。
      *
      * @param int $userId 用户主键 ID。
      * @param string $amount 入金金额（字符串十进制，如 '1000.00'）。
      * @param string $comment MT4 交易备注（comment）。
      * @return DepositSettlementResult 结算结果；失败以结果对象失败标记表达、
      *         不抛异常，调用方必须校验状态后再推进结算。
      */
    public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult;
}
