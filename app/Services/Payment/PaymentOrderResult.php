<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:50
 */

/**
 * 支付订单结果值对象。
 *
 * 文件功能：
 * - 封装支付网关返回的订单结果，包括网关标识、供应商订单号、重定向 URL 或表单提交信息。
 * - 不可变对象：构造时全量校验并归一化，构造后只能通过 getter 读取。
 *
 * 适用场景：
 * - 支付流程中，将支付网关的响应转换为统一的 PaymentOrderResult 对象，供上层业务使用。
 *
 * 入参例子：
 * - gatewayCode: "exlink_fiat", providerOrderNumber: "EX20240101001", redirectUrl: "https://pay.example.com/checkout"
 *
 * 返回值：
 * - gatewayCode(): 归一化网关码。
 * - providerOrderNumber(): 供应商订单号。
 * - redirectUrl() / formAction(): 跳转 URL 或表单提交地址，两者至少其一非空。
 * - formFields(): 表单隐藏字段，仅在 formAction 存在时非空。
 * - toArray(): 全部字段的快照数组，用于响应序列化。
 *
 * 异常或失败场景：
 * - gatewayCode 或 providerOrderNumber 为空或不安全时抛出 InvalidArgumentException。
 * - URL 非 http/https 时抛出 InvalidArgumentException，防止协议注入跳转。
 * - redirectUrl 和 formAction 均为 null 时抛出 InvalidArgumentException。
 * - formFields 存在但 formAction 为 null、或字段名/值不安全时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\Payment;

use InvalidArgumentException;

final class PaymentOrderResult
{
    /**
     * 网关码（归一化后）。订单结果归属网关的标识，写入订单与日志，供回调阶段校验一致性；
     * 构造时 trim 并按白名单字符校验。
     *
     * @var string
     */
    private $gatewayCode;

    /**
     * 供应商侧订单号。跨系统对账与回调关联的参照键，构造时 trim 并按白名单字符校验。
     *
     * @var string
     */
    private $providerOrderNumber;

    /**
     * 用户跳转地址（http/https）。与 formAction 至少其一非空，否则结果对象无法引导用户完成支付；
     * 仅接受安全协议，防止协议注入跳转。
     *
     * @var string|null
     */
    private $redirectUrl;

    /**
     * 自动提交表单地址（http/https）。存在 formFields 时必填；与 redirectUrl 互补，
     * 覆盖“跳转链接型”与“表单提交型”两类网关的返回形态。
     *
     * @var string|null
     */
    private $formAction;

    /**
     * 表单隐藏字段（字段名/值均为安全标量）。仅 formAction 存在时非空，
     * 供 Blade 直接渲染自动提交表单，字段内容已在构造时校验防注入。
     *
     * @var array<string, scalar>
     */
    private $formFields;

    /**
     * 构造并全量校验订单结果；任一字段非法即整体拒绝，不存在部分可用的半成品对象。
     *
     * @param string $gatewayCode 网关码，构造时 trim 并白名单校验。
     * @param string $providerOrderNumber 供应商订单号，构造时 trim 并白名单校验。
     * @param string|null $redirectUrl 用户跳转地址，仅接受 http/https。
     * @param string|null $formAction 自动提交表单地址，仅接受 http/https；存在 formFields 时必填。
     * @param array<string, scalar> $formFields 表单隐藏字段，字段名与值均校验为安全标量。
     * @throws InvalidArgumentException 任一字段校验失败时抛出。
     */
    public function __construct(
        string $gatewayCode,
        string $providerOrderNumber,
        string $redirectUrl = null,
        string $formAction = null,
        array $formFields = []
    ) {
        $gatewayCode = trim($gatewayCode);
        $providerOrderNumber = trim($providerOrderNumber);
        // URL 先归一化：空值转 null，非法协议直接拒绝。
        $redirectUrl = $this->normalizeUrl($redirectUrl);
        $formAction = $this->normalizeUrl($formAction);
        // 标识字段必填且走白名单字符校验；这些值会写入日志与订单表，禁止特殊字符。
        if ($gatewayCode === '' || $providerOrderNumber === '') {
            throw new InvalidArgumentException('Gateway and provider order number are required.');
        }
        if (!$this->isSafeIdentifier($gatewayCode) || !$this->isSafeIdentifier($providerOrderNumber)) {
            throw new InvalidArgumentException('Gateway and provider order number contain unsafe characters.');
        }
        // 必须给用户一种跳转方式；表单字段依赖表单地址，两者同时出现才允许。
        if ($redirectUrl === null && $formAction === null) {
            throw new InvalidArgumentException('A redirect URL or form action is required.');
        }
        if ($formFields !== [] && $formAction === null) {
            throw new InvalidArgumentException('Form fields require a form action.');
        }
        // 表单字段会原样渲染进自动提交页面，字段名与值都必须安全，防止注入额外表单控件。
        foreach ($formFields as $key => $value) {
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $key) || !is_scalar($value)) {
                throw new InvalidArgumentException('Provider form fields must use safe scalar values.');
            }
        }

        $this->gatewayCode = $gatewayCode;
        $this->providerOrderNumber = $providerOrderNumber;
        $this->redirectUrl = $redirectUrl;
        $this->formAction = $formAction;
        $this->formFields = $formFields;
    }

    /** 归一化网关码。 */
    public function gatewayCode(): string { return $this->gatewayCode; }
    /** 供应商订单号。 */
    public function providerOrderNumber(): string { return $this->providerOrderNumber; }
    /** 重定向 URL；未提供时为 null。 */
    public function redirectUrl(): ?string { return $this->redirectUrl; }
    /** 表单提交地址；未提供时为 null。 */
    public function formAction(): ?string { return $this->formAction; }
    /** @return array<string, scalar> 表单隐藏字段（已校验安全）。 */
    public function formFields(): array { return $this->formFields; }

    /**
     * 输出全部字段快照，用于统一响应序列化。
     *
     * @return array<string, mixed> 键为网关字段名，值为归一化后的结果。
     */
    public function toArray(): array
    {
        return [
            'gateway_code' => $this->gatewayCode,
            'provider_order_no' => $this->providerOrderNumber,
            'redirect_url' => $this->redirectUrl,
            'form_action' => $this->formAction,
            'form_fields' => $this->formFields,
        ];
    }

    /**
     * URL 归一化：trim 后校验协议必须为 http/https。
     *
     * 跳转地址会被浏览器直接访问，禁止 javascript: 等协议注入；非法协议即失败关闭。
     *
     * @param string|null $url 原始 URL。
     * @return string|null 归一化 URL；空值返回 null。
     * @throws InvalidArgumentException 协议非 http/https 或 URL 非法时抛出。
     */
    private function normalizeUrl(string $url = null): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Provider URL must use HTTP or HTTPS.');
        }

        return $url;
    }

    /**
     * 校验标识字段是否只包含白名单字符。
     *
     * 网关码与供应商订单号会写入订单表与日志,禁止空格、引号等特殊字符,避免日志注入与标识歧义。
     *
     * @param string $value 待校验标识。
     * @return bool 全部字符在白名单内时返回 true。
     */
    private function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $value) === 1;
    }
}
