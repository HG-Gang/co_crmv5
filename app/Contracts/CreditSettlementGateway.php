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
 * 入金信用额度结算网关契约（接口）。
 *
 * 文件功能：
 * - 定义“将入金金额作为信用额度记入用户账户（credit in）”的接口，
 *   供入金结算流程依赖注入调用。
 *
 * 适用场景：
 * - 入金结算（deposit settlement）流程在第三方支付回调确认成功后调用，
 *   把充值金额计入用户 MT4 账户的信用额度。
 *
 * 实现者：
 * - app/Services/Payment/Mt4CreditSettlementGateway。
 * 调用方：
 * - 后台批量信用导入（BatchCreditImportController）等需把金额以信用额度
 *   记入账户的服务。
 *
 * 入参例子：
 * - creditIn(10001, '1000.00', 'deposit settlement credit');
 *
 * 返回值：
 * - DepositSettlementResult：结算结果对象，含成功/失败状态与结算信息。
 *
 * 失败语义契约：
 * - 记入失败不抛异常，以结果对象失败标记表达；调用方必须检查结果状态，
 *   未成功入账时不能按已入账继续后续结算。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 执行失败时由实现将失败原因封装进结果对象返回。
 */
namespace App\Contracts;

use App\Services\Payment\DepositSettlementResult;

interface CreditSettlementGateway
{
     /**
      * 将指定金额以信用额度形式记入用户账户。
      *
      * @param int $userId 用户主键 ID。
      * @param string $amount 入金金额（字符串十进制，如 '1000.00'）。
      * @param string $comment MT4 交易备注（comment）。
      * @return DepositSettlementResult 结算结果；失败以结果对象失败标记表达、
      *         不抛异常，调用方必须校验状态后再继续结算流程。
      */
    public function creditIn(int $userId, string $amount, string $comment): DepositSettlementResult;
}
