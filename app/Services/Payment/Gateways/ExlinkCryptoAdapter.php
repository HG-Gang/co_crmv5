<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:52
 */

/**
 * Exlink 加密货币支付适配器。
 *
 * 文件功能：
 * - 处理 Exlink 支付网关的加密货币入金请求，构造支付 payload 并处理回调验证。
 *
 * 适用场景：
 * - 用户选择加密货币（USDT、BTC 等）方式充值时的支付通道对接。
 *
 * 入参例子：
 * - createPayload($order, ["merchant_id" => "M001", "protocol" => "TRC20", "coin_name" => "USDT"])
 *
 * 返回值：
 * - createPayload() 返回包含 uid、uniqueCode、protocol、coinName、orderId、amount 的数组。
 * - callbackAmount() 返回回调中的金额字符串。
 *
 * 安全边界：
 * - 回调 amount 必须与 orderAmount 一致，防止金额被篡改后入账。
 * - 回调 protocol / coinName 必须与渠道配置一致，防止其他币种/链的回调被错误归属。
 * - 任一校验失败均抛异常拒绝回调；金额比较使用 hash_equals 常量时间比对。
 *
 * 异常或失败场景：
 * - 回调中 amount 与 orderAmount 不匹配时抛出 InvalidArgumentException。
 * - 回调中 protocol 或 coinName 与配置不匹配时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Models\DepositRecord;
use InvalidArgumentException;

final class ExlinkCryptoAdapter extends AbstractExlinkAdapter
{
    /**
     * 组装加密货币订单 payload：商户号、用户 ID、协议与币种取自渠道配置，金额归一化为两位小数。
     *
     * @param DepositRecord $order 本地充值订单。
     * @param array<string, mixed> $channelConfig 渠道配置（merchant_id / protocol / coin_name）。
     * @return array<string, mixed> 网关下单请求字段。
     */
    protected function createPayload(DepositRecord $order, array $channelConfig): array
    {
        return [
            'uid' => $this->required($channelConfig, 'merchant_id'),
            'uniqueCode' => (string) $order->user_id,
            'protocol' => $this->required($channelConfig, 'protocol'),
            'coinName' => $this->required($channelConfig, 'coin_name'),
            'orderId' => (string) $order->local_order_no,
            'amount' => $this->decimal((string) $order->amount),
        ];
    }

    /**
     * 提取回调金额：amount 与 orderAmount 必须一致，防止仅靠单一字段被篡改。
     *
     * @param array<string, mixed> $payload 已验签的回调字段。
     * @return string 归一化的回调金额。
     * @throws InvalidArgumentException 两个金额字段不一致或格式非法时抛出。
     */
    protected function callbackAmount(array $payload): string
    {
        $amount = $this->decimal((string) ($payload['amount'] ?? ''));
        $orderAmount = $this->decimal((string) ($payload['orderAmount'] ?? ''));
        // 常量时间比对，任一字段被篡改都直接拒绝回调。
        if (!hash_equals($amount, $orderAmount)) {
            throw new InvalidArgumentException('Exlink crypto callback amount mismatch.');
        }

        return $amount;
    }

    /**
     * 校验加密货币回调特有字段：协议与币种必须与渠道配置一致。
     *
     * @param array<string, mixed> $payload 已验签的回调字段。
     * @param array<string, mixed> $config 渠道配置。
     * @throws InvalidArgumentException 协议或币种不匹配时抛出。
     */
    protected function validateCallbackSpecific(array $payload, array $config): void
    {
        // 协议与币种不匹配说明回调不属于本渠道的下单参数，直接拒绝，避免跨渠道错账。
        foreach (['protocol' => 'protocol', 'coinName' => 'coin_name'] as $payloadKey => $configKey) {
            $expected = $this->required($config, $configKey);
            $actual = trim((string) ($payload[$payloadKey] ?? ''));
            if (!hash_equals($expected, $actual)) {
                throw new InvalidArgumentException('Exlink crypto callback ' . $payloadKey . ' mismatch.');
            }
        }
    }
}
