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
 * 风险强平网关契约（接口）。
 *
 * 文件功能：
 * - 定义“对指定 MT4 订单执行强制平仓”的接口，供风控模块依赖注入调用。
 *
 * 适用场景：
 * - 风控规则触发（如保证金不足、强平阈值）时，由风控服务调用对用户订单强平。
 *
 * 实现者：
 * - app/Services/Risk/Mt4RiskForceCloseGateway。
 * 调用方：
 * - 后台风控（Admin/RiskController）：人工或规则触发强平时调用。
 *
 * 入参例子：
 * - close(123456, 987654, 'risk force close');
 *
 * 返回值：
 * - RiskForceCloseResult：强平结果对象，含成功/失败状态与失败原因。
 *
 * 失败语义契约：
 * - 强平失败不抛异常，以结果对象失败标记表达；调用方必须检查结果状态，
 *   未平掉的持仓不能按已强平处理，需再次触发风控评估。
 *
 * 异常或失败场景：
 * - 契约本身不强制抛异常；MT4 强平失败时由实现将失败原因封装进结果对象返回。
 */
namespace App\Contracts;

use App\Services\Risk\RiskForceCloseResult;

interface RiskForceCloseGateway
{
    /**
     * 对指定 MT4 订单执行强制平仓。
     *
     * @param int $login MT4 登录账号。
     * @param int $ticket MT4 订单号（ticket）。
     * @param string $comment 平仓备注（comment）。
     * @return RiskForceCloseResult 强平结果；失败以结果对象失败标记表达、
     *         不抛异常，调用方需据此重新评估风险。
     */
    public function close(int $login, int $ticket, string $comment): RiskForceCloseResult;
}
