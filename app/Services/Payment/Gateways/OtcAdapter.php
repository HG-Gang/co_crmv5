<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:52
 */

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGatewayAdapter;
use App\Models\DepositRecord;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentOrderResult;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * OTC 支付网关适配器（预留，暂未实现）。
 *
 * 文件功能：
 * - 实现 PaymentGatewayAdapter 接口，所有方法均抛出异常表示协议不支持。
 *
 * 适用场景：
 * - 预留给未来 OTC 场外交易支付通道接入。
 *
 * 入参例子：
 * - 所有方法调用均抛出 InvalidArgumentException。
 *
 * 返回值：
 * - 无正常返回值，调用即抛异常。
 *
 * 异常或失败场景：
 * - InvalidArgumentException('OTC payment protocol is unsupported.')。
 */
final class OtcAdapter implements PaymentGatewayAdapter
{
    /**
     * OTC 协议未实现：任何下单请求都失败关闭，防止产生无法结算的订单。
     *
     * @throws InvalidArgumentException 恒抛，表示协议暂不支持。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        throw new InvalidArgumentException('OTC payment protocol is unsupported.');
    }

    /**
     * OTC 回调一律拒绝：协议未实现时不接受任何回调（fail-closed）。
     *
     * @return bool 恒为 false。
     */
    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        return false;
    }

    /**
     * OTC 回调解析未实现：任何回调都失败关闭，防止数据被误处理。
     *
     * @throws InvalidArgumentException 恒抛，表示回调协议暂不支持。
     */
    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        throw new InvalidArgumentException('OTC callback protocol is unsupported.');
    }

    /**
     * 以 422 非成功应答通知网关回调未被接受；不返回 2xx 可避免网关误认为业务已受理。
     */
    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('UNSUPPORTED', 422, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
