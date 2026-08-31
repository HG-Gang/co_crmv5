<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:59
 */

/**
 * Switch 支付网关适配器。
 *
 * 文件功能：
 * - 实现 Switch 支付通道的创建订单、回调验签、回调解析与确认应答。
 * - 创建订单时校验渠道配置与 pay_type（1/2/3），组装 payload（uid、uniqueCode、money、payType、orderId、payerName、jumpUrl）并用 MD5（k=key）签名后 POST 到网关。
 * - 回调验签基于除 signature/sign 外全部字段按 key 排序后拼接的 MD5 签名。
 * - 回调解析要求 tradeStatus=1，并校验网关、订单号、金额、币种、商户号与期望值一致。
 *
 * 适用场景：
 * - 用户通过 Switch 通道充值时，由 PaymentGatewayRegistry 解析本适配器调用。
 *
 * 入参例子：
 * - createOrder($order, ['gateway_code' => 'switch', 'gateway_url' => 'https://pay.example.com/create', 'merchant_id' => 'M001', 'pay_type' => 1, 'secret_reference' => 'env:SWITCH_SECRET', 'return_route' => 'front.deposit.return'])
 * - parseCallback($request, ['callback_key_reference' => 'env:SWITCH_CALLBACK_KEY', 'expected_gateway' => 'switch'])
 *
 * 返回值：
 * - createOrder() 返回 PaymentOrderResult（网关码、本地订单号、供应商订单号）。
 * - verifyCallback() 返回 bool 是否验签通过。
 * - parseCallback() 返回 PaymentCallback（状态固定为 success）。
 * - acknowledge() 返回统一 JSON 应答（code=1, success=true）。
 *
 * 安全边界：
 * - 验签失败（signature 缺失、密钥不可解析或比对不通过）一律返回 false 或抛异常拒绝回调，绝不进入业务处理。
 * - 密钥只经 SecretReference 引用解析，不记录真实密钥与完整签名，回调仅保存 SHA-256 摘要。
 * - 身份字段与期望值用 hash_equals 常量时间比对，防止时序侧信道。
 *
 * 异常或失败场景：
 * - 渠道配置缺失、订单网关码不一致、金额单位非 decimal、pay_type 非法时抛出 InvalidArgumentException。
 * - 网关请求失败或响应 code 非 1 时抛出 InvalidArgumentException。
 * - 回调签名无效、tradeStatus 非 1、期望字段不匹配时抛出 InvalidArgumentException。
 */

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGatewayAdapter;
use App\Models\DepositRecord;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentOrderResult;
use App\Support\SecretReference;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class SwitchAdapter implements PaymentGatewayAdapter
{
    /**
     * 密钥解析器闭包：把 SecretReference 引用解析为 Switch 网关密钥，供下单签名与回调验签使用。
     * 解析失败（返回 null）时验签失败关闭；未注入时使用默认 env: 引用实现，保证密钥不落配置。
     *
     * @var Closure
     */
    private $secretResolver;

    /**
     * 构造 Switch 适配器。
     *
     * 支持注入密钥解析器便于测试；默认实现只接受 env: 前缀引用并读取对应环境变量，
     * 引用不合法或解析为空时后续 secret() 调用会失败关闭。
     *
     * @param callable|null $secretResolver 给定密钥引用返回真实密钥的闭包，默认读取环境变量。
     */
    public function __construct(callable $secretResolver = null)
    {
        $this->secretResolver = Closure::fromCallable($secretResolver ?: static function (string $reference): ?string {
            if (strpos($reference, 'env:') !== 0) {
                return null;
            }
            $value = getenv(substr($reference, 4));

            return is_string($value) && $value !== '' ? $value : null;
        });
    }

    /**
     * 创建 Switch 支付订单：校验渠道配置与支付类型后组装 payload、MD5 签名并 POST 到网关。
     *
     * @param DepositRecord $order 本地充值订单，gateway_code 必须与渠道配置一致。
     * @param array<string, mixed> $channelConfig 渠道配置（gateway_url / merchant_id / pay_type / 密钥引用等）。
     * @return PaymentOrderResult 统一订单结果（含网关码、本地订单号、供应商订单号）。
     * @throws InvalidArgumentException 配置缺失、网关码不一致、金额单位非 decimal、pay_type 非法或网关拒绝订单时抛出。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        // 出站前先完成本地校验：网关码一致、金额单位 decimal、必填配置齐全。
        $gateway = $this->required($channelConfig, 'gateway_code');
        if (!hash_equals($gateway, (string) $order->gateway_code)) {
            throw new InvalidArgumentException('Switch payment order gateway mismatch.');
        }
        if (strtolower($this->required($channelConfig, 'amount_unit')) !== 'decimal') {
            throw new InvalidArgumentException('Switch requires decimal amount unit.');
        }
        foreach (['gateway_url', 'merchant_id', 'currency', 'notify_route', 'return_route'] as $key) {
            $this->required($channelConfig, $key);
        }
        // 请求密钥用于订单签名；回调密钥在此预解析，避免订单已创建后才发现在回调阶段无法验签。
        $requestSecret = $this->secret($channelConfig, ['secret_reference', 'key_reference']);
        $this->secret($channelConfig, ['callback_key_reference']);
        $payType = (int) ($channelConfig['pay_type'] ?? 0);
        // 支付类型不在网关支持范围内时失败关闭，避免把未知类型发给网关造成不可控的支付流程。
        if (!in_array($payType, [1, 2, 3], true)) {
            throw new InvalidArgumentException('Switch pay type is invalid.');
        }

        // 组装业务字段并追加签名；签名不参与自身计算，见 signature()。
        $payload = [
            'uid' => $this->required($channelConfig, 'merchant_id'),
            'uniqueCode' => (string) $order->user_id,
            'money' => $this->decimal((string) $order->actual_amount),
            'payType' => $payType,
            'orderId' => (string) $order->local_order_no,
            'payerName' => (string) $order->user_name,
            'jumpUrl' => route($this->required($channelConfig, 'return_route'), ['gateway' => $gateway]),
        ];
        $payload['signature'] = $this->signature($payload, $requestSecret);

        // 带超时的出站请求；网关必须返回 code=1 才视为下单成功，其余一律拒绝。
        $response = Http::timeout(10)->acceptJson()->asJson()
            ->post($this->required($channelConfig, 'gateway_url'), $payload);
        if (!$response->successful()) {
            throw new InvalidArgumentException('Switch provider create-order request failed.');
        }
        $body = $response->json();
        if (!is_array($body) || (int) ($body['code'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Switch provider rejected the order.');
        }

        return new PaymentOrderResult(
            $gateway,
            (string) $order->local_order_no,
            trim((string) ($body['data'] ?? ''))
        );
    }

    /**
     * 校验回调签名是否与本地重算结果一致。
     *
     * @param Request $request 网关回调请求，全部表单字段参与签名。
     * @param array<string, mixed> $channelConfig 渠道配置，含 callback_key_reference。
     * @return bool 验签通过返回 true；signature 缺失、密钥不可解析或比对不通过一律返回 false（fail-closed）。
     */
    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        $payload = $request->all();
        $provided = strtolower(trim((string) ($payload['signature'] ?? '')));
        if ($provided === '') {
            return false;
        }
        // 剥离 signature 字段自身后对剩余字段重算签名，与网关侧"除 signature 外全部字段参与签名"的算法一致。
        unset($payload['signature']);

        try {
            $key = $this->secret($channelConfig, ['callback_key_reference']);
        } catch (InvalidArgumentException $exception) {
            // 密钥不可解析视为验签失败返回 false，避免回调方把验签失败误判为可重试的请求错误。
            return false;
        }

        // 常量时间比对，避免根据比对耗时差异探测签名内容。
        return hash_equals($this->signature($payload, $key), $provided);
    }

    /**
     * 验签并解析回调为统一的 PaymentCallback 值对象。
     *
     * @param Request $request 网关回调请求。
     * @param array<string, mixed> $channelConfig 渠道配置，含 expected_* 期望值。
     * @return PaymentCallback 验签与字段校验全部通过后的回调值对象，状态固定为 success。
     * @throws InvalidArgumentException 验签失败、tradeStatus 非 1 或期望字段不匹配时抛出。
     */
    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        if (!$this->verifyCallback($request, $channelConfig)) {
            throw new InvalidArgumentException('Invalid Switch callback signature.');
        }
        $payload = $request->all();
        // tradeStatus=1 是网关定义的支付成功标志，其余状态（含 pending）一律拒绝解析。
        if ((string) ($payload['tradeStatus'] ?? '') !== '1') {
            throw new InvalidArgumentException('Unsupported Switch callback status.');
        }
        $gateway = $this->required($channelConfig, 'gateway_code');
        $localOrder = trim((string) ($payload['apiOrderNo'] ?? ''));
        $providerOrder = trim((string) ($payload['tradeId'] ?? ''));
        $merchant = trim((string) ($payload['mchNo'] ?? ''));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $amount = $this->decimal((string) ($payload['money'] ?? ''));

        // 身份字段与渠道配置的期望值逐一比对；期望值未配置时跳过，不匹配即拒绝。
        $this->assertExpected($channelConfig, 'expected_gateway', $gateway);
        $this->assertExpected($channelConfig, 'expected_local_order_no', $localOrder);
        $this->assertExpected($channelConfig, 'expected_amount', $amount);
        $this->assertExpected($channelConfig, 'expected_currency', $currency);
        $this->assertExpected($channelConfig, 'expected_merchant_id', $merchant);

        // 仅保存原始回调内容的 SHA-256 摘要用于对账溯源，不落完整明文。
        return new PaymentCallback(
            $gateway,
            $localOrder,
            $providerOrder,
            'success',
            $amount,
            $currency,
            $merchant,
            hash('sha256', $request->getContent() !== '' ? $request->getContent() : (string) json_encode($payload))
        );
    }

    /**
     * 向网关返回统一 JSON 成功应答；网关收到即认为回调已被业务接受。
     */
    public function acknowledge(PaymentCallback $callback): Response
    {
        return new JsonResponse([
            'code' => 1,
            'success' => true,
            'message' => '成功',
            'data' => [],
        ]);
    }

    /**
     * 计算 Switch MD5 签名。
     *
     * 除 signature/sign 外全部字段按 key 排序后拼接为 name=value，空字符串与 null 字段不参与，
     * 以 &key=secret 结尾取小写 MD5，与网关侧签名算法一致。
     *
     * @param array<string, mixed> $payload 参与签名的字段，signature/sign 会被剥离。
     * @param string $key 网关侧密钥。
     * @return string 32 位小写 MD5。
     */
    private function signature(array $payload, string $key): string
    {
        // 剥离全部签名字段自身，防止签名递归包含自身导致两侧永远不一致。
        unset($payload['signature'], $payload['sign']);
        // ksort(SORT_STRING) 保证拼接顺序固定，是两侧算法一致的先决条件。
        ksort($payload, SORT_STRING);
        $parts = [];
        foreach ($payload as $name => $value) {
            // 空值与 null 不参与拼接，与网关侧算法一致，避免两侧因缺省字段产生签名差异。
            if ($value !== '' && $value !== null) {
                $parts[] = $name . '=' . $value;
            }
        }

        return md5(implode('&', $parts) . '&key=' . $key);
    }

    /**
     * 解析密钥引用：按优先级取第一个非空的引用字段，经 SecretReference 校验后解析出真实密钥。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @param array<int, string> $keys 候选引用字段名。
     * @return string 解析出的真实密钥，仅存在于内存中。
     * @throws InvalidArgumentException 引用非法或解析失败时抛出（fail-closed）。
     */
    private function secret(array $config, array $keys): string
    {
        $reference = '';
        foreach ($keys as $key) {
            $candidate = trim((string) ($config[$key] ?? ''));
            if ($candidate !== '') {
                $reference = $candidate;
                break;
            }
        }
        // 密钥只以引用形式出现在配置中；引用不合法或无法解析时直接失败，不落入默认值。
        if (!SecretReference::isValid($reference)) {
            throw new InvalidArgumentException('Switch secret reference is invalid.');
        }
        $secret = ($this->secretResolver)($reference);
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('Switch secret reference cannot be resolved.');
        }

        return $secret;
    }

    /**
     * 读取必填渠道配置并 trim，缺失或为空即失败关闭。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @param string $key 配置键名。
     * @return string 非空配置值。
     * @throws InvalidArgumentException 配置缺失时抛出。
     */
    private function required(array $config, string $key): string
    {
        $value = trim((string) ($config[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Missing Switch configuration: ' . $key);
        }

        return $value;
    }

    /**
     * 将金额归一化为两位小数的纯十进制字符串，格式非法即失败关闭。
     *
     * @param string $amount 原始金额字符串。
     * @return string 归一化金额。
     * @throws InvalidArgumentException 金额格式非法时抛出。
     */
    private function decimal(string $amount): string
    {
        if (!preg_match('/^(0|[1-9]\d{0,15})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Switch amount must be a plain decimal value.');
        }
        $fraction = str_pad((string) ($matches[2] ?? ''), 2, '0');

        $whole = ltrim($matches[1], '0');

        return ($whole === '' ? '0' : $whole) . '.' . $fraction;
    }

    /**
     * 校验回调字段与配置期望值一致；期望值未配置时跳过，兼容未声明期望的旧渠道。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @param string $key 期望值配置键（expected_*）。
     * @param string $actual 回调中的实际值。
     * @throws InvalidArgumentException 期望值已配置但不一致时抛出。
     */
    private function assertExpected(array $config, string $key, string $actual): void
    {
        $expected = trim((string) ($config[$key] ?? ''));
        if ($expected === '') {
            return;
        }
        if (!hash_equals($expected, $actual)) {
            throw new InvalidArgumentException('Switch callback ' . $key . ' mismatch.');
        }
    }
}
