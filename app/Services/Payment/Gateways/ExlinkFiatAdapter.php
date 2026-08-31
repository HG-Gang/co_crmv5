<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:52
 */

/**
 * Exlink 法币支付适配器。
 *
 * 文件功能：
 * - 处理 Exlink 支付网关的法币入金请求，构造支付 payload 并处理回调验证。
 *
 * 适用场景：
 * - 用户选择法币（银行转账、网银等）方式充值时的支付通道对接。
 *
 * 入参例子：
 * - createPayload($order, ["merchant_id" => "M001", "pay_type" => 1])
 *
 * 返回值：
 * - createPayload() 返回包含 uid、money、uniqueCode、payerName、payType、orderId 的数组。
 * - callbackAmount() 返回回调中的金额字符串。
 *
 * 安全边界：
 * - pay_type 白名单（1/2/3）校验失败即抛异常拒绝下单，不向网关发送未声明类型。
 * - 金额与身份字段的防篡改由父类签名验签与 expected_* 期望值校验兜底。
 *
 * 异常或失败场景：
 * - pay_type 不合法（非 1/2/3）时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Models\DepositRecord;
use InvalidArgumentException;

final class ExlinkFiatAdapter extends AbstractExlinkAdapter
{
    /**
     * 组装法币订单 payload：金额取用户实际支付金额，支付类型必须是配置白名单内的值。
     *
     * @param DepositRecord $order 本地充值订单。
     * @param array<string, mixed> $channelConfig 渠道配置（merchant_id / pay_type）。
     * @return array<string, mixed> 网关下单请求字段。
     * @throws InvalidArgumentException pay_type 非 1/2/3 时抛出。
     */
    protected function createPayload(DepositRecord $order, array $channelConfig): array
    {
        $payType = (int) ($channelConfig['pay_type'] ?? 0);
        // 支付类型不在网关支持范围内时失败关闭，避免把未知类型发给网关造成不可控的支付流程。
        if (!in_array($payType, [1, 2, 3], true)) {
            throw new InvalidArgumentException('Exlink fiat pay type is invalid.');
        }

        return [
            'uid' => $this->required($channelConfig, 'merchant_id'),
            'money' => $this->decimal((string) $order->actual_amount),
            'uniqueCode' => (string) $order->user_id,
            'payerName' => (string) $order->user_name,
            'payType' => $payType,
            'orderId' => (string) $order->local_order_no,
        ];
    }

    /**
     * 提取回调金额：法币回调金额取自 money 字段，格式与范围由 decimal() 统一校验。
     *
     * @param array<string, mixed> $payload 已验签的回调字段。
     * @return string 归一化的回调金额。
     */
    protected function callbackAmount(array $payload): string
    {
        return $this->decimal((string) ($payload['money'] ?? ''));
    }

    /**
     * 法币回调无额外协议字段：通用身份字段（网关/订单号/金额/币种/商户号）已由父类验签与期望值校验覆盖。
     *
     * @param array<string, mixed> $payload 已验签的回调字段。
     * @param array<string, mixed> $config 渠道配置。
     */
    protected function validateCallbackSpecific(array $payload, array $config): void
    {
        // 法币回调无额外协议字段，通用身份字段已由父类签名与期望值校验覆盖。
    }
}
