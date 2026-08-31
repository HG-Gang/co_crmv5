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
 * 入金退款网关契约（接口）。
 *
 * 文件功能：
 * - 定义“入金失败/取消时向用户账户执行退款”的接口，供入金退款流程依赖注入调用。
 *
 * 适用场景：
 * - 入金结算 outbox 事件类型为 deposit_refund 时调用，用于冲正已记入的信用额度。
 *
 * 实现者：
 * - app/Services/Payment/Mt4DepositRefundGateway。
 * 调用方：
 * - 入金退款出箱任务（Jobs/RefundDepositPayment）及后台批量金额导入
 *   （BatchAmountImportController）等需要冲正已记入金额的服务。
 *
 * 入参例子：
 * - refund(10001, '1000.00', 'deposit refund');
 *
 * 返回值：
 * - DepositSettlementResult：退款结算结果对象，含成功/失败状态。
 *
 * 失败语义契约：
 * - 退款失败不抛异常，以结果对象失败标记表达；调用方必须检查结果状态，
 *   未成功冲正时需按 outbox 机制重试，不能静默吞掉。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 执行失败时由实现将失败原因封装进结果对象返回。
 */
namespace App\Contracts;

use App\Services\Payment\DepositSettlementResult;

interface DepositRefundGateway
{
     /**
      * 为用户执行入金退款（冲正信用额度）。
      *
      * @param int $userId 用户主键 ID。
      * @param string $amount 退款金额（字符串十进制，如 '1000.00'）。
      * @param string $comment MT4 交易备注（comment）。
      * @return DepositSettlementResult 退款结果；失败以结果对象失败标记表达、
      *         不抛异常，调用方需据此决定重试或告警。
      */
    public function refund(int $userId, string $amount, string $comment): DepositSettlementResult;
}
