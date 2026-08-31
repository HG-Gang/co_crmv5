<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:53
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
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * BTB 支付网关适配器。
 *
 * 文件功能：
 * - 实现 BTB 支付通道的创建订单、校验回调、解析回调、确认应答。
 * - 使用 MD5 签名（k=key）方式签名和验签。
 *
 * 适用场景：
 * - 用户通过 BTB 通道充值时调用。
 *
 * 入参例子：
 * - createOrder: order + channelConfig（含 gateway_url / merchant_id / key_reference 等）。
 *
 * 返回值：
 * - createOrder: PaymentOrderResult（含支付跳转 URL）。
 * - parseCallback: PaymentCallback（仅支持 success 状态）。
 * - acknowledge: Response('success', 200)。
 *
 * 安全边界：
 * - 验签失败（sign 缺失、密钥不可解析或比对不通过）一律返回 false 或抛异常拒绝回调。
 * - 密钥只经 SecretReference 引用解析，不记录真实密钥与完整签名，回调仅保存 SHA-256 摘要。
 * - 身份字段与期望值、回调 type 与配置 order_type 均用 hash_equals 常量时间比对。
 *
 * 异常或失败场景：
 * - InvalidArgumentException：配置缺失、签名失败、回调状态不支持。
 */
final class BtbAdapter implements PaymentGatewayAdapter
{
    /**
     * 密钥解析器闭包：把 SecretReference 引用解析为 BTB 签名密钥，供下单签名与回调验签使用。
     * 解析失败（返回 null）时验签失败关闭；未注入时使用默认 env: 引用实现，保证密钥不落配置。
     *
     * @var Closure
     */
    private $secretResolver;

    /**
     * 构造 BTB 适配器并注入密钥解析器。
     *
     * 未注入时默认只接受 env: 前缀引用并从环境变量读取密钥，保证真实密钥不落入配置与日志。
     *
     * @param callable|null $secretResolver 密钥引用解析回调：入参为引用字符串，返回真实密钥或 null。
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
     * 创建 BTB 支付订单：校验渠道配置、组装 payload、MD5 签名后拼接为 GET 跳转 URL。
     *
     * @param DepositRecord $order 本地充值订单，gateway_code 必须与渠道配置一致。
     * @param array<string, mixed> $channelConfig 渠道配置（gateway_url / merchant_id / order_type / 密钥引用等）。
     * @return PaymentOrderResult 统一订单结果，redirectUrl 为带全部签名参数的跳转链接。
     * @throws InvalidArgumentException 配置缺失、网关码不一致、金额单位非 decimal 时抛出。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        // 出站前先完成本地校验：网关码一致、金额单位 decimal、必填配置齐全。
        $gateway = $this->required($channelConfig, 'gateway_code');
        if (!hash_equals($gateway, (string) $order->gateway_code)) {
            throw new InvalidArgumentException('BTB payment order gateway mismatch.');
        }
        if (strtolower($this->required($channelConfig, 'amount_unit')) !== 'decimal') {
            throw new InvalidArgumentException('BTB requires decimal amount unit.');
        }
        foreach (['gateway_url', 'merchant_id', 'currency', 'notify_route', 'return_route', 'order_type'] as $key) {
            $this->required($channelConfig, $key);
        }
        // 请求密钥用于订单签名；回调密钥在此预解析，避免订单已创建后才发现在回调阶段无法验签。
        $requestSecret = $this->secret($channelConfig, ['secret_reference', 'key_reference']);
        $this->secret($channelConfig, ['callback_key_reference']);
        // name 拼接用户名与用户 ID 作为付款方标识；notify/return 由本地路由生成，保证回调落回本系统。
        $payload = [
            'pid' => $this->required($channelConfig, 'merchant_id'),
            'out_trade_no' => (string) $order->local_order_no,
            'money' => $this->decimal((string) $order->amount),
            'name' => (string) $order->user_name . '-' . (string) $order->user_id,
            'type' => $this->required($channelConfig, 'order_type'),
            'notifyUrl' => route($this->required($channelConfig, 'notify_route'), ['gateway' => $gateway]),
            'returnUrl' => route($this->required($channelConfig, 'return_route'), ['gateway' => $gateway]),
            'isHtml' => 1,
        ];
        // 追加签名后拼装跳转 URL；网关以 GET 携带参数跳转支付页。
        $payload['sign'] = $this->signature(
            $payload,
            $requestSecret
        );
        $endpoint = rtrim($this->required($channelConfig, 'gateway_url'), '?&');

        return new PaymentOrderResult(
            $gateway,
            (string) $order->local_order_no,
            $endpoint . '?' . http_build_query($payload)
        );
    }

    /**
     * 校验回调签名是否与本地重算结果一致。
     *
     * @param Request $request 网关回调请求，全部表单字段参与签名。
     * @param array<string, mixed> $channelConfig 渠道配置，含 callback_key_reference。
     * @return bool 验签通过返回 true；sign 缺失、密钥不可解析或比对不通过一律返回 false（fail-closed）。
     */
    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        $payload = $request->all();
        $provided = strtolower(trim((string) ($payload['sign'] ?? '')));
        if ($provided === '') {
            return false;
        }
        // 剥离 sign 字段自身后对剩余字段重算签名，与网关侧"除 sign 外全部字段参与签名"的算法一致。
        unset($payload['sign']);
        try {
            $secret = $this->secret($channelConfig, ['callback_key_reference']);
        } catch (InvalidArgumentException $exception) {
            // 密钥不可解析视为验签失败返回 false，避免回调方把验签失败误判为可重试的请求错误。
            return false;
        }

        // 常量时间比对，避免根据比对耗时差异探测签名内容。
        return hash_equals($this->signature($payload, $secret), $provided);
    }

    /**
     * 验签并解析回调为统一的 PaymentCallback 值对象。
     *
     * @param Request $request 网关回调请求。
     * @param array<string, mixed> $channelConfig 渠道配置，含 order_type / expected_* 期望值。
     * @return PaymentCallback 验签与字段校验全部通过后的回调值对象，状态固定为 success。
     * @throws InvalidArgumentException 验签失败、状态非 success 或期望字段不匹配时抛出。
     */
    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        if (!$this->verifyCallback($request, $channelConfig)) {
            throw new InvalidArgumentException('Invalid BTB callback signature.');
        }
        $payload = $request->all();
        // 仅接受 status=success 的成功回调，其余状态一律拒绝。
        if (strtolower(trim((string) ($payload['status'] ?? ''))) !== 'success') {
            throw new InvalidArgumentException('Unsupported BTB callback status.');
        }
        $gateway = $this->required($channelConfig, 'gateway_code');
        $localOrder = trim((string) ($payload['out_trade_no'] ?? ''));
        $providerOrder = trim((string) ($payload['trade_no'] ?? ''));
        $merchant = trim((string) ($payload['pid'] ?? ''));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $amount = $this->decimal((string) ($payload['money'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        // 回调 type 必须与下单时配置的 order_type 一致，防止跨渠道/跨类型订单被错误归属。
        if (!hash_equals($this->required($channelConfig, 'order_type'), $type)) {
            throw new InvalidArgumentException('BTB callback type mismatch.');
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
            'success',
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
     * 计算 BTB MD5 签名。
     *
     * 除 sign 外全部字段按 key 排序后用 http_build_query 生成 key=value&... 形式（含 URL 编码），
     * 以 &key=secret 结尾取小写 MD5，与网关侧算法一致。
     *
     * @param array<string, mixed> $payload 参与签名的字段，sign 会被剥离。
     * @param string $key 网关侧密钥。
     * @return string 32 位小写 MD5。
     */
    private function signature(array $payload, string $key): string
    {
        // 剥离 sign 字段自身，防止签名递归包含自身导致两侧不一致。
        unset($payload['sign']);
        // ksort(SORT_STRING) 保证拼接顺序固定；http_build_query 的编码规则必须与网关侧完全一致。
        ksort($payload, SORT_STRING);

        return md5(http_build_query($payload) . '&key=' . $key);
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
            throw new InvalidArgumentException('BTB secret reference is invalid.');
        }
        $secret = ($this->secretResolver)($reference);
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('BTB secret reference cannot be resolved.');
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
            throw new InvalidArgumentException('Missing BTB configuration: ' . $key);
        }

        return $value;
    }

    /**
     * 将金额归一化为两位小数的纯十进制字符串（如 '10' -> '10.00'），格式非法即失败关闭。
     *
     * @param string $amount 原始金额字符串。
     * @return string 归一化金额。
     * @throws InvalidArgumentException 金额格式非法时抛出。
     */
    private function decimal(string $amount): string
    {
        if (!preg_match('/^(0|[1-9]\d{0,15})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('BTB amount must be a plain decimal value.');
        }
        $whole = ltrim($matches[1], '0');

        return ($whole === '' ? '0' : $whole) . '.' . str_pad((string) ($matches[2] ?? ''), 2, '0');
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
            throw new InvalidArgumentException('BTB callback ' . $key . ' mismatch.');
        }
    }
}
