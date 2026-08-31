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
 * 提现账户快照网关契约（接口）。
 *
 * 文件功能：
 * - 定义“提现前抓取用户账户快照”的接口，供提现流程依赖注入调用。
 *
 * 适用场景：
 * - 提现申请/结算流程在资金扣减前调用，记录提现前的账户资产/余额快照，
 *   用于对账与追溯。
 *
 * 实现者：
 * - app/Services/Withdrawal/Mt4WithdrawalAccountSnapshotGateway。
 * 调用方：
 * - 提现申请处理（WithdrawalOrderService）：扣减前抓取快照留痕。
 *
 * 入参例子：
 * - $userId = 10001;（用户主键，非 MT4 login）
 *
 * 返回值：
 * - WithdrawalAccountSnapshot：快照数据对象，含账户余额、可用余额等字段。
 *
 * 失败语义契约：
 * - 快照失败不抛异常，以快照对象状态标记表达；调用方在快照失败时应
 *   中止资金扣减，不能带着未知余额继续出金。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 读取失败时由实现将失败信息封装进快照对象返回。
 */
namespace App\Contracts;

use App\Services\Withdrawal\WithdrawalAccountSnapshot;

interface WithdrawalAccountSnapshotGateway
{
    /**
     * 抓取指定用户的账户快照。
     *
     * @param int $userId 用户主键 ID（非 MT4 login）。
     * @return WithdrawalAccountSnapshot 账户快照对象；失败以快照对象状态标记
     *         表达、不抛异常，调用方应据此中止资金扣减。
     */
    public function snapshot(int $userId): WithdrawalAccountSnapshot;
}
