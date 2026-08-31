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
 * 佣金转账账户快照网关契约（接口）。
 *
 * 文件功能：
 * - 定义“转账前抓取用户账户快照”的接口，供佣金转账 saga 业务层依赖注入调用，
 *   与具体 MT4 实现解耦。
 *
 * 适用场景：
 * - 佣金转账流程（CommissionTransfer saga）在资金划拨前调用，用于记录转账前的
 *   账户资产/余额快照，便于对账与追溯。
 *
 * 实现者：
 * - app/Services/CommissionTransfer/Mt4CommissionTransferAccountSnapshotGateway。
 * 调用方：
 * - 佣金转账 saga（CommissionTransferService）：资金划拨前抓快照留痕。
 *
 * 入参例子：
 * - $userId = 10001;（用户主键，非 MT4 login）
 *
 * 返回值：
 * - CommissionTransferAccountSnapshotResult：包含快照数据的结果对象；
 *   实现失败时返回带失败标记的结果，不抛出异常。
 *
 * 失败语义契约：
 * - 快照失败不抛异常，以结果对象的失败标记表达；调用方在快照失败时应
 *   中止后续资金划拨，不能带着未知余额继续转账。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；具体实现（如 MT4 调用超时）应将失败信息封装进
 *   CommissionTransferAccountSnapshotResult 返回。
 */
namespace App\Contracts;

use App\Services\CommissionTransfer\CommissionTransferAccountSnapshotResult;

interface CommissionTransferAccountSnapshotGateway
{
    /**
     * 抓取指定用户的账户快照。
     *
     * @param int $userId 用户主键 ID（非 MT4 login）。
     * @return CommissionTransferAccountSnapshotResult 快照结果对象；失败以结果对象
     *         的失败标记表达，不抛异常，调用方应据此中止资金划拨。
     */
    public function snapshot(int $userId): CommissionTransferAccountSnapshotResult;
}
