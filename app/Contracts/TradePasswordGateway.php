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
 * 交易密码校验网关契约（接口）。
 *
 * 文件功能：
 * - 定义“校验用户交易密码（MT4 密码）”的接口，供需要二次校验交易密码的
 *   业务流程（如佣金转账）依赖注入调用。
 *
 * 适用场景：
 * - 佣金转账等涉及资金操作的前置校验：用户提交交易密码后调用本接口验证。
 *
 * 实现者：
 * - app/Services/CommissionTransfer/Mt4TradePasswordGateway。
 * 调用方：
 * - 佣金转账 saga（CommissionTransferService）资金操作前校验；
 *   用户密码修改（UserPasswordService）校验旧交易密码。
 *
 * 入参例子：
 * - verify(10001, 'user-password');
 *
 * 返回值：
 * - TradePasswordVerificationResult：校验结果对象，含是否通过及失败原因。
 *
 * 失败语义契约：
 * - 密码错误不抛异常，以结果对象“未通过”标记表达；调用方必须检查结果，
 *   未通过时拒绝资金操作。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；密码错误/校验失败由实现以结果对象标记返回。
 */
namespace App\Contracts;

use App\Services\CommissionTransfer\TradePasswordVerificationResult;

interface TradePasswordGateway
{
    /**
     * 校验指定用户的交易密码。
     *
     * @param int $userId 用户主键 ID。
     * @param string $password 用户提交的交易密码明文；实现不得把密码明文
     *         写入日志或错误信息。
     * @return TradePasswordVerificationResult 校验结果；未通过以结果对象
     *         标记表达、不抛异常，调用方应据此拒绝资金操作。
     */
    public function verify(int $userId, string $password): TradePasswordVerificationResult;
}
