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
 * 支付网关适配器契约（接口）。
 *
 * 文件功能：
 * - 定义第三方支付渠道的统一适配接口：创建充值订单、验签回调、解析回调、
 *   应答回调，供支付服务按渠道配置（channelConfig）动态选择适配器实现。
 *
 * 回调处理四步契约（顺序固定，不可跳步）：
 * - createOrder：向渠道创建充值订单，返回下单结果。
 * - verifyCallback：校验回调签名；验签失败必须返回 false，调用方拒绝后续处理。
 * - parseCallback：仅在 verifyCallback 通过后调用，把回调解析为统一数据结构。
 * - acknowledge：向第三方服务器返回回执，防止其重复通知。
 *
 * 适用场景：
 * - 入金（deposit）流程创建支付订单时调用 createOrder；
 * - 第三方支付服务器回调本系统时依次调用 verifyCallback / parseCallback /
 *   acknowledge 完成验签、解析与应答。
 *
 * 实现者：
 * - app/Services/Payment/Gateways/ 下各渠道适配器：BtbAdapter、OtcAdapter、
 *   SwitchAdapter、WpPayAdapter、PassToAdapter、TigerPayAdapter 及
 *   AbstractExlinkAdapter 的 Exlink 系列子类。
 * 调用方：
 * - PaymentGatewayRegistry 按渠道 code 分发：前端入金（DepositController）走
 *   createOrder；回调入口（PaymentNotifyController）走验签/解析/应答三步。
 *
 * 入参例子：
 * - createOrder($depositRecord, ['merchant_id' => 'xxx', 'secret' => 'yyy']);
 * - verifyCallback($request, $channelConfig);
 *
 * 返回值：
 * - createOrder 返回 PaymentOrderResult（下单结果）；
 * - verifyCallback 返回 bool（验签是否通过）；
 * - parseCallback 返回 PaymentCallback（解析后的回调数据）；
 * - acknowledge 返回 Response（回执给第三方支付服务器的响应）。
 *
 * 失败语义契约：
 * - 验签失败时 verifyCallback 必须返回 false，调用方拒绝解析与入账，
 *   不得继续执行 parseCallback / acknowledge 之后的下单处理。
 * - 下单失败由 createOrder 以 PaymentOrderResult 的失败标记表达，不抛异常。
 *
 * 异常或失败场景：
 * - 验签失败不抛异常（返回 false）；渠道参数缺失或网络异常等由实现决定
 *   抛异常或包装进结果对象，具体见各实现类。
 */
namespace App\Contracts;

use App\Models\DepositRecord;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentOrderResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface PaymentGatewayAdapter
{
    /**
     * 向第三方支付渠道创建充值订单。
     *
     * @param DepositRecord $order 充值记录模型（含订单号、金额、用户等）。
     * @param array $channelConfig 渠道配置数组（商户号、密钥等），由
     *        渠道配置中心下发，禁止输出到日志。
     * @return PaymentOrderResult 下单结果，含支付链接/凭证；失败以结果对象
     *         失败标记表达，不抛异常。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult;

    /**
     * 校验第三方支付回调签名是否合法。
     *
     * 失败契约：验签失败必须返回 false，调用方据此拒绝解析与入账，
     * 不得继续后续回调处理；本方法不抛异常，也不记录密钥明文。
     *
     * @param Request $request 回调 HTTP 请求。
     * @param array $channelConfig 渠道配置数组（签名密钥等）。
     * @return bool true=验签通过；false=验签失败（调用方拒绝处理）。
     */
    public function verifyCallback(Request $request, array $channelConfig): bool;

    /**
     * 解析第三方支付回调为统一结构。
     *
     * 仅允许在 verifyCallback 返回 true 后调用；验签未通过时调用方
     * 不得调用本方法，否则可能把伪造回调当作有效业务数据。
     *
     * @param Request $request 回调 HTTP 请求。
     * @param array $channelConfig 渠道配置数组。
     * @return PaymentCallback 解析后的统一回调数据对象。
     */
    public function parseCallback(Request $request, array $channelConfig): PaymentCallback;

    /**
     * 生成回执响应应答第三方支付服务器（防止重复通知）。
     *
     * @param PaymentCallback $callback 已解析的回调数据。
     * @return Response 回执响应，如 success 文本或 HTTP 200。
     */
    public function acknowledge(PaymentCallback $callback): Response;
}
