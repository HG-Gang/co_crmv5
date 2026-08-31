<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

/**
 * Exlink 支付网关抽象适配器。
 *
 * 文件功能：
 * - 定义 Exlink 支付通道（法币/加密货币）共用的创建订单、回调验签、回调解析与确认应答逻辑。
 * - 创建订单时校验渠道配置（gateway_code、amount_unit、gateway_url、merchant_id、currency、notify_route、return_route），组装 payload 并用 MD5（k=key）签名后 POST 到网关。
 * - 回调验签基于除 signature 外全部字段按 key 排序后拼接的 MD5 签名，与回调携带签名做 hash_equals 比对。
 * - 回调解析要求 tradeStatus=1，并通过子类钩子（createPayload / callbackAmount / validateCallbackSpecific）差异化处理法币与加密货币字段。
 *
 * 适用场景：
 * - ExlinkFiatAdapter、ExlinkCryptoAdapter 继承本抽象类实现各自渠道差异。
 *
 * 入参例子：
 * - createOrder($order, ['gateway_code' => 'exlink_fiat', 'gateway_url' => 'https://pay.example.com/create', 'merchant_id' => 'M001', 'currency' => 'CNY', 'secret_reference' => 'env:EXLINK_SECRET', ...])
 * - verifyCallback($request, ['callback_key_reference' => 'env:EXLINK_CALLBACK_KEY'])
 *
 * 返回值：
 * - createOrder() 返回 PaymentOrderResult（含网关码、本地订单号、供应商订单号）。
 * - verifyCallback() 返回 bool 是否验签通过。
 * - parseCallback() 返回 PaymentCallback（状态固定为 success）。
 * - acknowledge() 返回统一 JSON 应答（success=true, code=1）。
 *
 * 安全边界：
 * - 验签失败（signature 缺失、密钥不可解析或比对不通过）一律返回 false 或抛异常拒绝回调，绝不进入业务处理。
 * - 密钥只经 SecretReference 引用解析，代码与注释中不出现真实密钥；不记录完整签名与回调明文，仅保存 SHA-256 摘要。
 * - 身份字段（网关/订单号/金额/币种/商户号）与期望值用 hash_equals 常量时间比对，防止时序侧信道。
 *
 * 异常或失败场景：
 * - 渠道配置缺失、金额单位非 decimal、订单网关码不一致时抛出 InvalidArgumentException。
 * - 网关 HTTP 非 2xx 或响应 success/code 不满足要求时抛出 InvalidArgumentException。
 * - 回调签名无效、tradeStatus 非 1、期望字段（网关/订单号/金额/币种/商户号）不匹配时抛出 InvalidArgumentException。
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

abstract class AbstractExlinkAdapter implements PaymentGatewayAdapter
{
    /**
     * 密钥解析器闭包：把 SecretReference 引用解析为真实签名密钥。所有验签/签名都经它取密钥，
     * 真实密钥因此永远不出现在配置与代码里；解析失败返回 null，secret() 调用随之失败关闭拒绝回调。
     * 未注入时使用默认 env: 引用实现。
     *
     * @var Closure
     */
    private $secretResolver;

    /**
     * 构造函数注入密钥解析器。
     *
     * 未提供解析器时使用默认实现：仅接受 env: 前缀引用并读取对应环境变量，
     * 保证密钥始终以引用形式存在，代码与配置中不出现真实密钥。
     *
     * @param callable|null $secretResolver 将密钥引用解析为真实密钥的回调，返回 null 表示解析失败。
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
     * 创建 Exlink 支付订单：校验渠道配置后组装 payload、MD5 签名并 POST 到网关。
     *
     * @param DepositRecord $order 本地充值订单，gateway_code 必须与渠道配置一致。
     * @param array<string, mixed> $channelConfig 渠道配置（gateway_code / amount_unit / 密钥引用 / 网关 URL 等）。
     * @return PaymentOrderResult 统一订单结果（含网关码、本地订单号、供应商订单号）。
     * @throws InvalidArgumentException 配置缺失、网关码不一致、金额单位非 decimal 或网关拒绝订单时抛出。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        // 出站前先完成本地校验：订单与渠道网关码一致、金额单位 decimal，并确认全部必填配置存在。
        $gateway = $this->required($channelConfig, 'gateway_code');
        if (!hash_equals($gateway, (string) $order->gateway_code)) {
            throw new InvalidArgumentException('Exlink payment order gateway mismatch.');
        }
        if (strtolower($this->required($channelConfig, 'amount_unit')) !== 'decimal') {
            throw new InvalidArgumentException('Exlink requires decimal amount unit.');
        }
        foreach (['gateway_url', 'merchant_id', 'currency', 'notify_route', 'return_route'] as $key) {
            $this->required($channelConfig, $key);
        }
        // 请求密钥用于订单签名；回调密钥在此预解析，避免订单已创建后才发现在回调阶段无法验签。
        $requestSecret = $this->secret($channelConfig, ['secret_reference', 'key_reference']);
        $this->secret($channelConfig, ['callback_key_reference']);
        // 组装业务字段并追加签名；签名不参与自身计算，见 signature()。
        $payload = $this->createPayload($order, $channelConfig);
        $payload['signature'] = $this->signature(
            $payload,
            $requestSecret
        );
        // 带超时的出站请求；网关必须返回 success=true 且 code=1 才视为下单成功，其余一律拒绝。
        $response = Http::timeout(10)->acceptJson()->asJson()
            ->post($this->required($channelConfig, 'gateway_url'), $payload);
        $body = $response->json();
        if (!$response->successful() || !is_array($body)
            || empty($body['success']) || (int) ($body['code'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Exlink provider rejected the order.');
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
        // 剥离签名字段自身后对剩余字段重算签名，与网关侧"除 signature 外全部字段参与签名"的算法一致。
        unset($payload['signature']);
        try {
            $secret = $this->secret($channelConfig, ['callback_key_reference']);
        } catch (InvalidArgumentException $exception) {
            // 密钥不可解析视为验签失败返回 false，不向上抛异常，避免回调方把验签失败误判为可重试的请求错误。
            return false;
        }

        // 常量时间比对，避免根据比对耗时差异探测签名内容。
        return hash_equals($this->signature($payload, $secret), $provided);
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
            throw new InvalidArgumentException('Invalid Exlink callback signature.');
        }
        $payload = $request->all();
        // tradeStatus=1 是网关定义的支付成功标志，其余状态（含 pending）一律拒绝解析。
        if ((string) ($payload['tradeStatus'] ?? '') !== '1') {
            throw new InvalidArgumentException('Unsupported Exlink callback status.');
        }
        $this->validateCallbackSpecific($payload, $channelConfig);
        $gateway = $this->required($channelConfig, 'gateway_code');
        $localOrder = trim((string) ($payload['apiOrderNo'] ?? ''));
        $providerOrder = trim((string) ($payload['tradeId'] ?? ''));
        $merchant = trim((string) ($payload['uid'] ?? ''));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $amount = $this->callbackAmount($payload);

        // 身份字段与渠道配置的期望值逐一比对；期望值未配置时跳过（兼容旧渠道），不匹配即拒绝。
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
     * 向网关返回统一成功应答；网关收到即认为回调已被业务接受。
     */
    public function acknowledge(PaymentCallback $callback): Response
    {
        return new JsonResponse(['success' => true, 'code' => 1, 'message' => '成功', 'data' => []]);
    }

    /** @return array<string, mixed> 子类渠道差异：法币/加密货币各自组装 payload 字段。 */
    abstract protected function createPayload(DepositRecord $order, array $channelConfig): array;

    /** @param array<string, mixed> $payload @return string 回调金额，子类按渠道字段差异提取。 */
    abstract protected function callbackAmount(array $payload): string;

    /** @param array<string, mixed> $payload @param array<string, mixed> $config */
    abstract protected function validateCallbackSpecific(array $payload, array $config): void;

    /**
     * 计算 Exlink MD5 签名。
     *
     * 除 signature 外全部字段按 key 排序后拼接为 name=value，空字符串与 null 字段不参与，
     * 最终以 &key=secret 结尾后取小写 MD5，与网关侧签名算法一致。
     *
     * @param array<string, mixed> $payload 参与签名的字段，signature 会被剥离。
     * @param string $key 网关侧密钥。
     * @return string 32 位小写 MD5。
     */
    protected function signature(array $payload, string $key): string
    {
        // 剥离签名字段自身，防止签名递归包含自身导致两侧永远不一致。
        unset($payload['signature']);
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
     * 将金额归一化为两位小数的纯十进制字符串（如 '10' -> '10.00'）。
     *
     * 签名与回调比对都依赖固定格式，格式非法（超长、负数、非数字）即失败关闭。
     *
     * @param string $amount 原始金额字符串。
     * @return string 归一化金额。
     * @throws InvalidArgumentException 金额格式非法时抛出。
     */
    protected function decimal(string $amount): string
    {
        if (!preg_match('/^(0|[1-9]\d{0,15})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Exlink amount must be a plain decimal value.');
        }
        $whole = ltrim($matches[1], '0');

        return ($whole === '' ? '0' : $whole) . '.' . str_pad((string) ($matches[2] ?? ''), 2, '0');
    }

    /**
     * 读取必填渠道配置并 trim，缺失或为空即失败关闭。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @param string $key 配置键名。
     * @return string 非空配置值。
     * @throws InvalidArgumentException 配置缺失时抛出。
     */
    protected function required(array $config, string $key): string
    {
        $value = trim((string) ($config[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Missing Exlink configuration: ' . $key);
        }

        return $value;
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
            throw new InvalidArgumentException('Exlink secret reference is invalid.');
        }
        $secret = ($this->secretResolver)($reference);
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('Exlink secret reference cannot be resolved.');
        }

        return $secret;
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
            throw new InvalidArgumentException('Exlink callback ' . $key . ' mismatch.');
        }
    }
}
