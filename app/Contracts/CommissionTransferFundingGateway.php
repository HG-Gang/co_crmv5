<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:07
 */

declare(strict_types=1);

/**
 * 佣金转账资金网关契约（接口）。
 *
 * 文件功能：
 * - 定义佣金转账三种资金指令（转出 withdraw / 转入 deposit / 补偿 compensate）
 *   的统一接口，业务层只依赖本契约，不关心 MT4 具体实现。
 *
 * 适用场景：
 * - 佣金转账 saga 执行资金划拨步骤时调用：先从佣金账户转出，再向目标账户转入，
 *   异常补偿时执行 compensate。
 *
 * 实现者：
 * - app/Services/CommissionTransfer/Mt4CommissionTransferFundingGateway。
 * 调用方：
 * - 佣金转账 saga（CommissionTransferService）：按 withdraw → deposit 顺序执行，
 *   失败或超时按业务规则执行 compensate 补偿。
 *
 * 入参例子：
 * - withdraw(10001, '500.00', 'commission transfer out');
 * - deposit(10001, '500.00', 'commission transfer in');
 * - compensate(10001, '500.00', 'commission transfer compensate');
 *
 * 返回值：
 * - CommissionTransferCommandResult：包含指令是否成功、失败原因等信息的
 *   结果对象；失败不抛异常，通过结果标记表达。
 *
 * 失败语义契约：
 * - 三个方法都以结果对象状态表达成败，不抛异常；调用方必须检查结果状态，
 *   不能仅凭方法未被抛出异常就判定成功。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 执行失败（余额不足、超时等）由实现封装进
 *   CommissionTransferCommandResult 返回。
 */
namespace App\Contracts;

use App\Services\CommissionTransfer\CommissionTransferCommandResult;

interface CommissionTransferFundingGateway
{
     /**
      * 从用户佣金账户转出指定金额（资金划拨第一步）。
      *
      * @param int $userId 用户主键 ID。
      * @param string $amount 转出金额（字符串十进制，如 '500.00'）。
      * @param string $comment MT4 交易备注（comment）。
      * @return CommissionTransferCommandResult 指令执行结果；成功/失败均以结果对象
      *         状态表达，失败不抛异常，由调用方依据状态决定是否补偿。
      */
    public function withdraw(int $userId, string $amount, string $comment): CommissionTransferCommandResult;

    /**
     * 向用户佣金账户转入指定金额（资金划拨第二步）。
     *
     * @param int $userId 用户主键 ID。
     * @param string $amount 转入金额（字符串十进制，如 '500.00'）。
     * @param string $comment MT4 交易备注（comment）。
     * @return CommissionTransferCommandResult 指令执行结果；成功/失败均以结果对象
     *         状态表达，失败不抛异常，由调用方依据状态决定是否补偿。
     */
    public function deposit(int $userId, string $amount, string $comment): CommissionTransferCommandResult;

    /**
     * 向用户佣金账户执行补偿划拨（用于补偿已发生但未成功的资金变动）。
     *
     * @param int $userId 用户主键 ID。
     * @param string $amount 补偿金额（字符串十进制，如 '500.00'）。
     * @param string $comment MT4 交易备注（comment）。
     * @return CommissionTransferCommandResult 指令执行结果；成功/失败均以结果对象
     *         状态表达，失败不抛异常，由调用方依据状态决定是否补偿。
     */
    public function compensate(int $userId, string $amount, string $comment): CommissionTransferCommandResult;
}
