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
 * 用户 MT4 开户/同步网关契约（接口）。
 *
 * 文件功能：
 * - 定义“在 MT4 服务器创建用户账号（provision）”与“核对/修复 MT4 账号状态
 *   （reconcile）”的接口，供注册流程与定时任务依赖注入调用。
 *
 * 适用场景：
 * - 用户注册/开户流程调用 provision 在 MT4 建号；
 * - 定时任务或开户补偿流程调用 reconcile 核对 MT4 账号组别是否与期望一致。
 *
 * 实现者：
 * - app/Services/Registration/Mt4UserProvisioningGateway。
 * 调用方：
 * - 注册开户处理（UserMt4ProvisioningProcessor）：注册后建号，
 *   并可用 reconcile 核对/修复账号状态。
 *
 * 入参例子：
 * - provision(['user_id' => 10001, 'group' => 'demo\\group1', 'leverage' => 100]);
 * - reconcile(10001, 'demo\\group1');
 *
 * 返回值：
 * - UserMt4ProvisioningResult：开户/核对结果对象，含成功/失败状态。
 *
 * 失败语义契约：
 * - 建号/核对失败不抛异常，以结果对象失败标记表达；调用方必须检查结果状态，
 *   建号未成功时不能把用户标记为已开户，需走补偿或重试。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 开户失败（如账号已存在、参数非法）由实现
 *   将失败原因封装进结果对象返回。
 */
namespace App\Contracts;

use App\Services\Registration\UserMt4ProvisioningResult;

interface UserMt4ProvisioningGateway
{
    /**
     * 在 MT4 服务器为指定用户创建账号。
     *
     * @param array<string, mixed> $payload 开户参数数组，如
     *        ['user_id' => 10001, 'group' => 'demo\\group1', 'leverage' => 100]。
     * @return UserMt4ProvisioningResult 开户结果；失败以结果对象失败标记表达、
     *         不抛异常，调用方未确认成功前不得将用户标记为已开户。
     */
    public function provision(array $payload): UserMt4ProvisioningResult;

    /**
     * 核对指定用户的 MT4 账号状态（组别等），不一致时尝试修复。
     *
     * @param int $userId 用户主键 ID。
     * @param string|null $expectedGroup 期望的 MT4 组别；null 表示以配置为准。
     * @return UserMt4ProvisioningResult 核对结果；不一致且修复失败时以
     *         结果对象失败标记表达，不抛异常。
     */
    public function reconcile(int $userId, string $expectedGroup = null): UserMt4ProvisioningResult;
}
