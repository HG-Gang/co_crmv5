<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:56
 */

declare(strict_types=1);

namespace App\Services\Payment;

use InvalidArgumentException;

/**
 * 支付回调值对象。
 *
 * 文件功能：
 * - 封装支付网关回调数据，统一校验回调字段格式和安全性。
 * - 提供不可变的属性访问方法供回调处理服务使用；所有字段构造时校验并归一化，构造后不可修改。
 *
 * 安全边界：
 * - 标识字段按白名单字符校验并 trim，防止空白串绕过必填校验与日志注入。
 * - 金额必须是正数两位小数的十进制串（拒绝 0），币种白名单校验，防止异常数据进入入账流程。
 * - payloadHash 必须是 64 位 SHA-256 hex，确保对账溯源用摘要可被信任。
 *
 * 适用场景：
 * - 支付网关回调到达时，由控制器将原始请求数据构造为本对象后传入 PaymentCallbackService。
 *
 * 入参例子：
 * - gatewayCode: 'tiger'
 * - localOrderNumber: 'DEP20240801120000ABC123'
 * - providerOrderNumber: 'TG20240801120000'
 * - status: 'success' | 'failed' | 'pending' | 'refunded'
 * - amount: '100.00'
 * - currency: 'CNY'
 * - merchantId: 'merchant_001'
 * - payloadHash: 'abc123...' （SHA-256 十六进制）
 *
 * 返回值：
 * - 各 getter 方法返回校验后的字符串值。
 *
 * 异常或失败场景：
 * - InvalidArgumentException：标识字段为空、含不安全字符、金额格式非法、状态不支持、payloadHash 非 SHA-256 格式。
 */
final class PaymentCallback
{
    /**
     * 回调所属网关码（如 tiger / wppay）。回调处理前必须与注册表中的网关一致，
     * 防止把 A 网关的回调套用到 B 网关的验签逻辑上；构造时按白名单字符校验。
     *
     * @var string
     */
    private $gatewayCode;

    /**
     * 本地订单号（deposit_records.order_no）。回调与本地订单的关联键，
     * 匹配不到本地订单的回调一律拒绝入账；构造时按白名单字符校验。
     *
     * @var string
     */
    private $localOrderNumber;

    /**
     * 供应商侧订单号。用于跨系统对账与去重参照，与本地订单号分离保留双口径；
     * 构造时按白名单字符校验。
     *
     * @var string
     */
    private $providerOrderNumber;

    /**
     * 回调状态：pending / success / failed / refunded 四选一（白名单外的值整体拒绝）。
     * success 才可能触发入金结算，refunded 触发退款语义，pending 不改变终态。
     *
     * @var string
     */
    private $status;

    /**
     * 回调金额（两位小数正数十进制串，拒绝 0 与负数）。入账前必须与本地订单金额一致，
     * 构造时校验格式，防止异常金额进入结算流程。
     *
     * @var string
     */
    private $amount;

    /**
     * 币种码（3~10 位字母）。与渠道配置币种白名单比对后使用，防止跨币种回调误入账。
     *
     * @var string
     */
    private $currency;

    /**
     * 商户号。网关配置的商户身份标识，参与回调归属校验；构造时按白名单字符校验。
     *
     * @var string
     */
    private $merchantId;

    /**
     * 原始回调内容的 SHA-256 十六进制摘要（64 位）。用于对账溯源与“回调未被篡改”的证据链，
     * 非 SHA-256 格式整体拒绝，保证该摘要可信。
     *
     * @var string
     */
    private $payloadHash;

    /**
     * 构造并全量校验回调值对象；任一字段非法即整体拒绝，不存在部分可用的半成品对象。
     *
     * @param string $gatewayCode 网关码。
     * @param string $localOrderNumber 本地订单号。
     * @param string $providerOrderNumber 供应商订单号。
     * @param string $status 回调状态，仅接受 pending / success / failed / refunded。
     * @param string $amount 两位小数的正数金额。
     * @param string $currency 3~10 位字母币种码。
     * @param string $merchantId 商户号。
     * @param string $payloadHash 原始回调内容的 SHA-256 十六进制摘要。
     * @throws InvalidArgumentException 任一字段校验失败时抛出。
     */
    public function __construct(
        string $gatewayCode,
        string $localOrderNumber,
        string $providerOrderNumber,
        string $status,
        string $amount,
        string $currency,
        string $merchantId,
        string $payloadHash
    ) {
        // 标识类字段统一 trim 后做必填校验，防止纯空白字符串绕过检查。
        $values = array_map('trim', [$gatewayCode, $localOrderNumber, $providerOrderNumber, $currency, $merchantId]);
        if (in_array('', $values, true)) {
            throw new InvalidArgumentException('Callback identity fields are required.');
        }
        // 订单类标识符走白名单字符校验，这些值会写入日志与出箱表，禁止特殊字符注入。
        foreach (array_slice($values, 0, 3) as $identifier) {
            if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $identifier)) {
                throw new InvalidArgumentException('Callback identifiers contain unsafe characters.');
            }
        }
        // 币种必须是纯字母；商户号复用标识符白名单规则。
        if (!preg_match('/^[A-Za-z]{3,10}$/', $values[3])) {
            throw new InvalidArgumentException('Callback currency is invalid.');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $values[4])) {
            throw new InvalidArgumentException('Callback merchant identity is invalid.');
        }
        // 状态统一小写后按白名单匹配，拒绝网关新增的未映射状态，避免误入账。
        $status = strtolower(trim($status));
        if (!in_array($status, ['pending', 'success', 'failed', 'refunded'], true)) {
            throw new InvalidArgumentException('Unsupported callback status.');
        }
        // 金额必须为正数（0 与 0.00 一并拒绝），两位小数以内，格式与后续入账/签名比对保持一致。
        if (!preg_match('/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/', $amount) || preg_match('/^0(?:\.0{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Callback amount must be a positive plain decimal string.');
        }
        $payloadHash = strtolower(trim($payloadHash));
        if (!preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
            throw new InvalidArgumentException('Callback payload hash must be SHA-256 hex.');
        }

        // 全部校验通过后统一赋值；currency 统一为大写，保证后续比对不受大小写干扰。
        [$this->gatewayCode, $this->localOrderNumber, $this->providerOrderNumber, $this->currency, $this->merchantId] = $values;
        $this->currency = strtoupper($this->currency);
        $this->status = $status;
        $this->amount = $amount;
        $this->payloadHash = $payloadHash;
    }

    /** 网关码（构造时已 trim 校验）。 */
    public function gatewayCode(): string { return $this->gatewayCode; }
    /** 本地订单号（构造时已白名单校验）。 */
    public function localOrderNumber(): string { return $this->localOrderNumber; }
    /** 供应商订单号（构造时已白名单校验）。 */
    public function providerOrderNumber(): string { return $this->providerOrderNumber; }
    /** 归一化后的回调状态：pending / success / failed / refunded。 */
    public function status(): string { return $this->status; }
    /** 两位小数正数金额（原始字符串，不参与浮点运算）。 */
    public function amount(): string { return $this->amount; }
    /** 统一大写的币种码。 */
    public function currency(): string { return $this->currency; }
    /** 商户号（构造时已白名单校验）。 */
    public function merchantId(): string { return $this->merchantId; }
    /** 原始回调内容的 SHA-256 摘要，用于对账溯源。 */
    public function payloadHash(): string { return $this->payloadHash; }
}
