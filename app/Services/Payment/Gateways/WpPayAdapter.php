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
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * WpPay 支付网关适配器。
 *
 * 文件功能：
 * - 实现 WpPay 通道的创建订单、校验回调、解析回调、确认应答。
 * - 通过 HTTP POST 提交支付表单，使用 MD5 签名（k=secret）验签。
 *
 * 适用场景：
 * - 用户通过 WpPay 通道充值时调用。
 *
 * 入参例子：
 * - createOrder: order + channelConfig（含 gateway_url / app_id / payment_type 等）。
 *
 * 返回值：
 * - createOrder: PaymentOrderResult（含 formAction 和 formFields）。
 * - parseCallback: PaymentCallback。
 * - acknowledge: Response('SUCCESS', 200)。
 *
 * 安全边界：
 * - 验签失败（sign 缺失、密钥不可解析或比对不通过）一律返回 false 或抛异常拒绝回调。
 * - 密钥只经 SecretReference 引用解析，不记录真实密钥与完整签名，回调仅保存 SHA-256 摘要。
 * - 回调金额的 amount 与 real_amount 两个字段必须一致，防止单一字段被篡改。
 *
 * 异常或失败场景：
 * - InvalidArgumentException：配置缺失、金额格式非法、签名校验失败。
 */
final class WpPayAdapter implements PaymentGatewayAdapter
{
    /**
     * 密钥解析器闭包：把 SecretReference 引用解析为 WpPay 签名密钥，供下单签名与回调验签使用。
     * 解析失败即失败关闭拒绝回调；未注入时使用默认 env: 引用实现。
     *
     * @var Closure
     */
    private $secretResolver;

    /**
     * 构造 WpPay 适配器并注入密钥解析器。
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
     * 创建 WpPay 支付订单：校验渠道配置、组装 payload、SHA1 签名后 POST 到网关。
     *
     * @param DepositRecord $order 本地充值订单，gateway_code 必须与渠道配置一致。
     * @param array<string, mixed> $channelConfig 渠道配置（gateway_url / app_id / payment_type / 密钥引用等）。
     * @return PaymentOrderResult 统一订单结果（含网关支付 URL）。
     * @throws InvalidArgumentException 配置缺失、网关码不一致、金额单位非 decimal、手机号非法或网关拒绝订单时抛出。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        // 出站前先完成本地校验：网关码一致、金额单位 decimal、必填配置齐全。
        $gateway = $this->required($channelConfig, 'gateway_code');
        if (!hash_equals($gateway, (string) $order->gateway_code)) {
            throw new InvalidArgumentException('WP payment order gateway mismatch.');
        }
        if (strtolower($this->required($channelConfig, 'amount_unit')) !== 'decimal') {
            throw new InvalidArgumentException('WP requires decimal amount unit.');
        }
        foreach (['gateway_url', 'currency', 'notify_route', 'return_route', 'app_id', 'payment_type'] as $key) {
            $this->required($channelConfig, $key);
        }
        // 请求密钥用于订单签名；回调密钥在此预解析，避免订单已创建后才发现在回调阶段无法验签。
        $requestSecret = $this->secret($channelConfig, ['secret_reference', 'key_reference']);
        $this->secret($channelConfig, ['callback_key_reference']);
        // 付款手机号优先取渠道配置（可覆盖），否则取用户档案；格式不合法即失败关闭。
        $mobile = $this->payerMobile($order, $channelConfig);
        // 组装业务字段并追加签名；签名不参与自身计算，见 signature()。
        $payload = [
            'amount' => $this->decimal((string) $order->actual_amount),
            'mobile' => $mobile,
            'username' => (string) $order->user_name,
            'orderid' => (string) $order->local_order_no,
            'notify_url' => route($this->required($channelConfig, 'notify_route'), ['gateway' => $gateway]),
            'return_url' => route($this->required($channelConfig, 'return_route'), ['gateway' => $gateway]),
            'currency' => strtoupper($this->required($channelConfig, 'currency')),
            'type' => $this->required($channelConfig, 'payment_type'),
            'appid' => $this->required($channelConfig, 'app_id'),
        ];
        $payload['sign'] = $this->signature(
            $payload,
            $requestSecret
        );
        // 带超时的出站请求；网关必须返回 status=1 才视为下单成功，其余一律拒绝。
        $response = Http::timeout(10)->acceptJson()->asJson()
            ->post($this->required($channelConfig, 'gateway_url'), $payload);
        $body = $response->json();
        if (!$response->successful() || !is_array($body) || (int) ($body['status'] ?? 0) !== 1) {
            throw new InvalidArgumentException('WP provider rejected the order.');
        }
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return new PaymentOrderResult(
            $gateway,
            (string) $order->local_order_no,
            trim((string) ($data['pay_url'] ?? ''))
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
        $provided = strtoupper(trim((string) ($payload['sign'] ?? '')));
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

        // 常量时间比对；网关签名为大写 SHA1，回调侧统一转大写后再比对。
        return hash_equals($this->signature($payload, $secret), $provided);
    }

    /**
     * 验签并解析回调为统一的 PaymentCallback 值对象。
     *
     * @param Request $request 网关回调请求。
     * @param array<string, mixed> $channelConfig 渠道配置，含 expected_* 期望值。
     * @return PaymentCallback 验签与字段校验全部通过后的回调值对象，状态固定为 success。
     * @throws InvalidArgumentException 验签失败、状态非 success、金额字段不一致或期望字段不匹配时抛出。
     */
    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        if (!$this->verifyCallback($request, $channelConfig)) {
            throw new InvalidArgumentException('Invalid WP callback signature.');
        }
        $payload = $request->all();
        // 仅接受 tradeStatus=success 的成功回调，其余状态一律拒绝。
        if (strtolower(trim((string) ($payload['tradeStatus'] ?? ''))) !== 'success') {
            throw new InvalidArgumentException('Unsupported WP callback status.');
        }
        $gateway = $this->required($channelConfig, 'gateway_code');
        $localOrder = trim((string) ($payload['order_id'] ?? ''));
        $providerOrder = trim((string) ($payload['trader_no'] ?? ''));
        $merchant = trim((string) ($payload['appid'] ?? ''));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $amount = $this->decimal((string) ($payload['total_price'] ?? ''));
        // 网关同时回传 amount 与 real_amount，两个字段必须一致，防止仅靠单一字段被篡改。
        foreach (['amount', 'real_amount'] as $field) {
            if (!hash_equals($amount, $this->decimal((string) ($payload[$field] ?? '')))) {
                throw new InvalidArgumentException('WP callback amount fields mismatch.');
            }
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
     * 计算 WpPay SHA1 签名。
     *
     * 除 sign 外全部字段按 key 排序后，字段值直接拼接（无分隔符、无 key= 前缀），
     * 追加密钥后取大写 SHA1，与网关侧特有算法一致。
     *
     * @param array<string, mixed> $payload 参与签名的字段，sign 会被剥离。
     * @param string $key 网关侧密钥。
     * @return string 40 位大写 SHA1。
     */
    private function signature(array $payload, string $key): string
    {
        // 剥离 sign 字段自身，防止签名递归包含自身导致两侧不一致。
        unset($payload['sign']);
        // ksort(SORT_STRING) 保证字段顺序固定；值直接串联是网关侧约定的拼接方式，不要改成 key=value 形式。
        ksort($payload, SORT_STRING);
        $signing = '';
        foreach ($payload as $name => $value) {
            $signing .= $name . $value;
        }

        return strtoupper(sha1($signing . $key));
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
            throw new InvalidArgumentException('WP secret reference is invalid.');
        }
        $secret = ($this->secretResolver)($reference);
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('WP secret reference cannot be resolved.');
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
            throw new InvalidArgumentException('Missing WP configuration: ' . $key);
        }

        return $value;
    }

    /**
     * 解析付款手机号：优先取渠道配置，缺省时读取用户档案 phone；格式不合法即失败关闭。
     *
     * @param DepositRecord $order 本地充值订单。
     * @param array<string, mixed> $config 渠道配置。
     * @return string 合法手机号（7~20 位数字，可带 + 前缀）。
     * @throws InvalidArgumentException 手机号缺失或格式非法时抛出。
     */
    private function payerMobile(DepositRecord $order, array $config): string
    {
        $mobile = trim((string) ($config['payer_mobile'] ?? ''));
        if ($mobile === '') {
            // 懒加载用户档案取 phone；查询失败时回退空串让后续格式校验明确失败。
            $user = $order->relationLoaded('user') ? $order->getRelation('user') : $order->user()->first();
            $mobile = $user === null ? '' : trim((string) $user->phone);
        }
        if (!preg_match('/^\+?[0-9]{7,20}$/', $mobile)) {
            throw new InvalidArgumentException('WP payer mobile is invalid.');
        }

        return $mobile;
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
            throw new InvalidArgumentException('WP amount must be a plain decimal value.');
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
            throw new InvalidArgumentException('WP callback ' . $key . ' mismatch.');
        }
    }
}
