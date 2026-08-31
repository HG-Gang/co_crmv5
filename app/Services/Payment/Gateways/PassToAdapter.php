<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:59
 */

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGatewayAdapter;
use App\Models\DepositRecord;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\PaymentOrderResult;
use App\Support\SecretReference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * PassTo 支付网关适配器。
 *
 * 文件功能：
 * - 实现 PassTo 支付通道的创建订单、校验回调、解析回调、确认应答。
 * - 通过 HTTP POST 创建订单，使用签名验签回调。
 *
 * 适用场景：
 * - 用户通过 PassTo 通道充值时调用。
 *
 * 入参例子：
 * - createOrder: order + channelConfig（含 gateway_url / merchant_id / secret_reference 等）。
 *
 * 返回值：
 * - createOrder: PaymentOrderResult（含支付跳转 URL）。
 * - parseCallback: PaymentCallback。
 * - acknowledge: 返回 SUCCESS 纯文本。
 *
 * 安全边界：
 * - 验签失败（sign 缺失、密钥不可解析或比对不通过）一律返回 false 或抛异常拒绝回调。
 * - 密钥只经 SecretReference 引用解析，不记录真实密钥与完整签名，回调仅保存 SHA-256 摘要。
 * - 身份字段与期望值用 hash_equals 常量时间比对，防止时序侧信道。
 *
 * 异常或失败场景：
 * - InvalidArgumentException：配置缺失、密钥无效、签名失败。
 */
final class PassToAdapter implements PaymentGatewayAdapter
{
    /**
     * 密钥解析器闭包：把 SecretReference 引用解析为 PassTo 密钥，签名与验签的唯一密钥来源。
     * 解析失败即失败关闭拒绝回调；未注入时使用默认 env: 引用实现。
     *
     * @var Closure
     */
    private $secretResolver;

    /**
     * 构造 PassTo 适配器。
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
     * 创建 PassTo 支付订单：校验渠道配置、组装分单位金额的 payload、MD5 签名后 POST 到网关。
     *
     * @param DepositRecord $order 本地充值订单，gateway_code 必须与渠道配置一致。
     * @param array<string, mixed> $channelConfig 渠道配置（gateway_url / version / merchant_id / app_id / 密钥引用等）。
     * @return PaymentOrderResult 统一订单结果（含网关订单号与跳转数据）。
     * @throws InvalidArgumentException 配置缺失、网关码不一致、金额单位非 cent 或网关拒绝订单时抛出。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        // 出站前先完成本地校验：网关码一致、金额单位为分（cent），并确认版本与端点配置存在。
        $gateway = $this->required($channelConfig, 'gateway_code');
        if (!hash_equals($gateway, (string) $order->gateway_code)) {
            throw new InvalidArgumentException('Payment order gateway mismatch.');
        }
        if (strtolower($this->required($channelConfig, 'amount_unit')) !== 'cent') {
            throw new InvalidArgumentException('PassTo requires cent amount unit.');
        }

        $endpoint = $this->required($channelConfig, 'gateway_url');
        $version = $this->required($channelConfig, 'version');
        $secret = $this->secret($channelConfig);
        // 时间戳统一为毫秒，且与网关时区无关；金额必须转成分（cent）以避免浮点误差。
        $payload = [
            'mchNo' => $this->required($channelConfig, 'merchant_id'),
            'appId' => $this->required($channelConfig, 'app_id'),
            'mchOrderNo' => (string) $order->local_order_no,
            'currency' => strtoupper($this->required($channelConfig, 'currency')),
            'amount' => $this->decimalToMinor((string) $order->actual_amount),
            'reqTime' => (int) round(microtime(true) * 1000),
            'registerTime' => (int) round(microtime(true) * 1000),
            'activeTime' => (int) round(microtime(true) * 1000),
            'custNo' => (string) $order->user_id,
            'userName' => (string) $order->user_name,
            'version' => $version,
            'signType' => 'MD5',
            'notifyUrl' => $this->routeUrl($channelConfig, 'notify_route', $gateway),
            'returnUrl' => $this->routeUrl($channelConfig, 'return_route', $gateway),
            // 支付链接默认 15 分钟有效期，防止长时间挂起未支付的订单。
            'expiredTime' => (int) ($channelConfig['expired_time'] ?? 900),
        ];
        // 全部字段签名后一并提交，网关要求 signType=MD5 且签名大写。
        $payload['sign'] = $this->signature($payload, $secret);

        // 带超时的出站请求；HTTP 2xx 后还需业务码 code=0 才算下单成功，缺一即拒绝。
        $response = Http::timeout(10)->acceptJson()->asJson()
            ->post($endpoint, $payload);
        if (!$response->successful()) {
            throw new InvalidArgumentException('PassTo provider create-order request failed.');
        }
        $body = $response->json();
        if (!is_array($body) || (int) ($body['code'] ?? -1) !== 0) {
            throw new InvalidArgumentException('PassTo provider rejected the order.');
        }
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return new PaymentOrderResult(
            $gateway,
            trim((string) ($data['payOrderId'] ?? '')),
            trim((string) ($data['payData'] ?? ''))
        );
    }

    /**
     * 校验回调签名是否与本地重算结果一致。
     *
     * @param Request $request 网关回调请求，全部表单字段参与签名。
     * @param array<string, mixed> $channelConfig 渠道配置，含密钥引用。
     * @return bool 验签通过返回 true；sign 缺失、密钥不可解析或比对不通过一律返回 false（fail-closed）。
     */
    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        $payload = $request->all();
        $provided = trim((string) ($payload['sign'] ?? ''));
        if ($provided === '') {
            return false;
        }
        // 剥离 sign 字段自身后对剩余字段重算签名，与网关侧"除 sign 外全部字段参与签名"的算法一致。
        unset($payload['sign']);

        try {
            $expected = $this->signature($payload, $this->secret($channelConfig));
        } catch (InvalidArgumentException $exception) {
            // 密钥不可解析视为验签失败返回 false，避免回调方把验签失败误判为可重试的请求错误。
            return false;
        }

        // 常量时间比对；网关签名为大写，回调侧统一转大写后再比对。
        return hash_equals($expected, strtoupper($provided));
    }

    /**
     * 验签并解析回调为统一的 PaymentCallback 值对象。
     *
     * @param Request $request 网关回调请求。
     * @param array<string, mixed> $channelConfig 渠道配置，含 expected_* 期望值。
     * @return PaymentCallback 验签与字段校验全部通过后的回调值对象，状态按网关 state 映射。
     * @throws InvalidArgumentException 验签失败、状态未知或期望字段不匹配时抛出。
     */
    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        if (!$this->verifyCallback($request, $channelConfig)) {
            throw new InvalidArgumentException('Invalid PassTo callback signature.');
        }
        $payload = $request->all();
        $gateway = $this->required($channelConfig, 'gateway_code');
        $localOrder = trim((string) ($payload['mchOrderNo'] ?? ''));
        $providerOrder = trim((string) ($payload['payOrderId'] ?? ''));
        $merchant = trim((string) ($payload['mchNo'] ?? ''));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $amount = $this->minorToDecimal((string) ($payload['amount'] ?? ''));
        // 网关 state 数字映射为统一状态；不在映射表内的状态（含新出的支付中变体）一律拒绝，避免误入账。
        $statusMap = ['0' => 'pending', '1' => 'pending', '2' => 'success', '3' => 'failed', '4' => 'failed', '5' => 'refunded', '6' => 'failed'];
        $providerStatus = (string) ($payload['state'] ?? '');
        if (!isset($statusMap[$providerStatus])) {
            throw new InvalidArgumentException('Unsupported PassTo callback status.');
        }

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
            $statusMap[$providerStatus],
            $amount,
            $currency,
            $merchant,
            hash('sha256', $request->getContent() !== '' ? $request->getContent() : (string) json_encode($payload))
        );
    }

    /**
     * 向网关返回固定纯文本应答 'success'；网关收到即认为回调已被业务接受。
     */
    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('success', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * 计算 PassTo MD5 签名。
     *
     * 除 sign 外全部字段按 key 排序后拼接为 key=value&，空字符串与 null 字段不参与，
     * 以 key=secret 结尾取大写 MD5，与网关侧算法一致（网关要求大写）。
     *
     * @param array<string, mixed> $payload 参与签名的字段，sign 会被剥离。
     * @param string $secret 网关侧密钥。
     * @return string 32 位大写 MD5。
     */
    private function signature(array $payload, string $secret): string
    {
        // ksort(SORT_STRING) 保证拼接顺序固定，是两侧算法一致的先决条件。
        ksort($payload, SORT_STRING);
        $signing = '';
        foreach ($payload as $key => $value) {
            // 空值与 null 不参与拼接，与网关侧算法一致，避免两侧因缺省字段产生签名差异。
            if ($value !== '' && $value !== null) {
                $signing .= $key . '=' . $value . '&';
            }
        }

        return strtoupper(md5($signing . 'key=' . $secret));
    }

    /**
     * 解析密钥引用：按优先级取第一个非空的引用字段，经 SecretReference 校验后解析出真实密钥。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @return string 解析出的真实密钥，仅存在于内存中。
     * @throws InvalidArgumentException 引用非法或解析失败时抛出（fail-closed）。
     */
    private function secret(array $config): string
    {
        $reference = '';
        foreach (['secret_reference', 'key_reference'] as $key) {
            $candidate = trim((string) ($config[$key] ?? ''));
            if ($candidate !== '') {
                $reference = $candidate;
                break;
            }
        }
        // 密钥只以引用形式出现在配置中；引用不合法或无法解析时直接失败，不落入默认值。
        if (!SecretReference::isValid($reference)) {
            throw new InvalidArgumentException('PassTo secret reference is invalid.');
        }
        $secret = ($this->secretResolver)($reference);
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('PassTo secret reference cannot be resolved.');
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
            throw new InvalidArgumentException('Missing PassTo configuration: ' . $key);
        }

        return $value;
    }

    /**
     * 根据路由名生成带 gateway 参数的回调 URL。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @param string $key 路由名配置键（notify_route / return_route）。
     * @param string $gateway 网关码，用于回调路由定位本渠道。
     * @return string 完整 URL。
     */
    private function routeUrl(array $config, string $key, string $gateway): string
    {
        $routeName = $this->required($config, $key);

        return route($routeName, ['gateway' => $gateway]);
    }

    /**
     * 十进制金额转分（整数）：避免浮点误差，并校验结果在 32 位 int 范围内（上限 18 位且不超 PHP_INT_MAX）。
     *
     * @param string $amount 两位小数的十进制金额。
     * @return int 以分为单位的整数。
     * @throws InvalidArgumentException 金额格式非法或超出范围时抛出。
     */
    private function decimalToMinor(string $amount): int
    {
        if (!preg_match('/^(0|[1-9]\d{0,15})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('PassTo amount must be a plain decimal value.');
        }
        $fraction = str_pad((string) ($matches[2] ?? ''), 2, '0');
        $minor = ltrim($matches[1] . $fraction, '0');
        // 网关侧金额为 int；超范围的值直接拒绝，避免溢出后发送错误金额。
        if ($minor === '' || strlen($minor) > 18 || (strlen($minor) === 18 && strcmp($minor, (string) PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('PassTo amount is outside the supported range.');
        }

        return (int) $minor;
    }

    /**
     * 分转十进制金额：网关回调金额最小为 1 分，需补足三位后再拆分为整数部分与两位小数。
     *
     * @param string $minor 以分为单位的整数。
     * @return string 两位小数的十进制金额。
     * @throws InvalidArgumentException 分值格式非法时抛出。
     */
    private function minorToDecimal(string $minor): string
    {
        if (!preg_match('/^[1-9]\d{0,17}$/', $minor)) {
            throw new InvalidArgumentException('PassTo callback amount is invalid.');
        }
        // 不足三位左侧补零，保证 1~2 位分值的整数部分能被正确拆出（如 '5' -> '0.05'）。
        $minor = str_pad($minor, 3, '0', STR_PAD_LEFT);

        $whole = ltrim(substr($minor, 0, -2), '0');

        return ($whole === '' ? '0' : $whole) . '.' . substr($minor, -2);
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
            throw new InvalidArgumentException('PassTo callback ' . $key . ' mismatch.');
        }
    }
}
