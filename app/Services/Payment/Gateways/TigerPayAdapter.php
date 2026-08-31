<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
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
 * TigerPay 支付网关适配器。
 *
 * 文件功能：
 * - 实现 TigerPay 的创建订单、校验回调、解析回调、确认应答四个接口。
 * - 使用 RSA 公钥加密订单数据、私钥签名，回调时验签并解密。
 *
 * 适用场景：
 * - 用户通过 TigerPay 通道充值时，由 PaymentGatewayRegistry 解析此适配器调用。
 *
 * 入参例子：
 * - createOrder: order (DepositRecord) + channelConfig (含 gateway_url / app_id / 密钥引用等)。
 * - parseCallback: request (TigerPay 回调 HTTP 请求) + channelConfig。
 *
 * 返回值：
 * - createOrder: PaymentOrderResult（含跳转 URL）。
 * - parseCallback: PaymentCallback（含校验后的订单号和状态）。
 * - acknowledge: Response('SUCCESS', 200)。
 *
 * 安全边界：
 * - 私钥仅用于本地签名与回调解密，公钥仅用于加密与验签；密钥只经 SecretReference 引用解析，不记录真实密钥。
 * - 验签失败（data/sign 缺失、公钥不可用、openssl_verify 非 1）一律返回 false 拒绝回调；解密失败抛异常失败关闭。
 * - 不记录完整签名与密文，回调明文仅保存 SHA-256 摘要；回调解密数据不再外传。
 *
 * 异常或失败场景：
 * - InvalidArgumentException：配置缺失、密钥无效、加解密失败、签名校验失败。
 */
final class TigerPayAdapter implements PaymentGatewayAdapter
{
    /**
     * 密钥解析器闭包：把 SecretReference 引用解析为 TigerPay 的 RSA 公钥/私钥。
     * 私钥用于本地签名与回调解密，公钥用于验签与加密；解析失败时验签失败关闭，
     * 未注入时使用默认 env: 引用实现，保证密钥对始终以引用形式存在。
     *
     * @var Closure
     */
    private $secretResolver;

    /**
     * 构造函数注入密钥解析器。
     *
     * 未提供解析器时使用默认实现：仅接受 env: 前缀引用并读取对应环境变量，
     * 保证公钥/私钥始终以引用形式存在，代码与配置中不出现真实密钥。
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
     * 创建 TigerPay 支付订单：业务字段 JSON 化后用网关公钥加密，再用本地私钥签名，拼装 GET 跳转 URL。
     *
     * @param DepositRecord $order 本地充值订单，gateway_code 必须与渠道配置一致。
     * @param array<string, mixed> $channelConfig 渠道配置（app_id / gateway_url / charset / method / version / 密钥引用等）。
     * @return PaymentOrderResult 统一订单结果，redirectUrl 为带加密数据与签名的跳转链接。
     * @throws InvalidArgumentException 配置缺失、网关码不一致、金额单位非 decimal、加解密或签名失败时抛出。
     */
    public function createOrder(DepositRecord $order, array $channelConfig): PaymentOrderResult
    {
        // 出站前先完成本地校验：网关码一致、金额单位 decimal，并确认应用标识与端点配置存在。
        $gateway = $this->required($channelConfig, 'gateway_code');
        if (!hash_equals($gateway, trim((string) $order->gateway_code))) {
            throw new InvalidArgumentException('TigerPay payment order gateway mismatch.');
        }
        if (strtolower($this->required($channelConfig, 'amount_unit')) !== 'decimal') {
            throw new InvalidArgumentException('TigerPay requires decimal amount unit.');
        }
        $appId = $this->required($channelConfig, 'app_id');
        $endpoint = $this->required($channelConfig, 'gateway_url');
        $currency = strtoupper($this->required($channelConfig, 'currency'));
        // 组装业务 JSON：金额两位小数，body 固定为 DBUN-用户ID 便于网关对账，notify/return 由本地路由生成。
        $business = [
            'timestamp' => (int) floor(microtime(true) * 1000),
            'tradeNo' => trim((string) $order->local_order_no),
            'price' => $this->decimal((string) $order->actual_amount),
            'userName' => trim((string) $order->user_name),
            'userId' => trim((string) $order->user_id),
            'body' => 'DBUN-' . trim((string) $order->user_id),
            'payType' => 1,
            'returnUrl' => route($this->required($channelConfig, 'return_route'), ['gateway' => $gateway]),
            'notifyUrl' => route($this->required($channelConfig, 'notify_route'), ['gateway' => $gateway]),
            'currency' => $currency,
        ];
        $json = json_encode($business, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new InvalidArgumentException('TigerPay order payload cannot be encoded.');
        }
        // 业务数据用网关公钥加密（网关私钥可解），签名用本地私钥（网关公钥可验），两类密钥职责分离。
        $data = $this->encryptWithPublicKey(
            $json,
            $this->secret($channelConfig, 'server_public_key_reference')
        );
        $signature = $this->sign(
            $data,
            $this->secret($channelConfig, 'app_private_key_reference')
        );
        // 查询参数固定顺序拼装；data 已 URL 编码，sign 再经 base64 + URL 编码。
        $query = implode('&', [
            'appId=' . rawurlencode($appId),
            'charset=' . rawurlencode($this->required($channelConfig, 'charset')),
            'data=' . $data,
            'method=' . rawurlencode($this->required($channelConfig, 'method')),
            'sign=' . rawurlencode(base64_encode($signature)),
            'version=' . rawurlencode($this->required($channelConfig, 'version')),
        ]);

        return new PaymentOrderResult(
            $gateway,
            trim((string) $order->local_order_no),
            rtrim($endpoint, '?&') . '?' . $query
        );
    }

    /**
     * 校验回调签名：对规范化后的 data 用网关公钥验签（MD5 算法），任何一步失败都返回 false（fail-closed）。
     *
     * @param Request $request 网关回调请求，data/sign 为表单字段。
     * @param array<string, mixed> $channelConfig 渠道配置，含 server_public_key_reference。
     * @return bool 验签通过返回 true；data/sign 缺失、公钥不可用或 openssl_verify 非 1 时返回 false。
     */
    public function verifyCallback(Request $request, array $channelConfig): bool
    {
        // 回调 data 可能被网关重复 URL 编码，先解码再校验 base64，最后重新编码，保证与签名输入字节一致。
        $data = $this->canonicalSignedData((string) $request->input('data', ''));
        $encodedSignature = trim((string) $request->input('sign', ''));
        if ($data === '' || $encodedSignature === '') {
            return false;
        }
        $signature = base64_decode(rawurldecode($encodedSignature), true);
        if (!is_string($signature) || $signature === '') {
            return false;
        }

        try {
            $publicKey = $this->secret($channelConfig, 'server_public_key_reference');
            $key = openssl_pkey_get_public($publicKey);
            if ($key === false) {
                return false;
            }

            // openssl_verify 返回 1 表示签名有效；0 与 -1（错误）都视为验签失败。
            return openssl_verify($data, $signature, $key, OPENSSL_ALGO_MD5) === 1;
        } catch (InvalidArgumentException $exception) {
            // 密钥不可解析视为验签失败返回 false，避免回调方把验签失败误判为可重试的请求错误。
            return false;
        }
    }

    /**
     * 验签并解析回调为统一的 PaymentCallback 值对象：用本地私钥解密 data 后映射状态并校验身份字段。
     *
     * @param Request $request 网关回调请求。
     * @param array<string, mixed> $channelConfig 渠道配置，含 app_private_key_reference / expected_* 期望值。
     * @return PaymentCallback 验签、解密与字段校验全部通过后的回调值对象。
     * @throws InvalidArgumentException 验签失败、解密失败、状态未知或期望字段不匹配时抛出。
     */
    public function parseCallback(Request $request, array $channelConfig): PaymentCallback
    {
        if (!$this->verifyCallback($request, $channelConfig)) {
            throw new InvalidArgumentException('Invalid TigerPay callback signature.');
        }
        // 验签通过后才允许解密；私钥只用于解密，不经此路径签发任何数据。
        $json = $this->decryptWithPrivateKey(
            trim((string) $request->input('data', '')),
            $this->secret($channelConfig, 'app_private_key_reference')
        );
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('TigerPay callback payload is invalid.');
        }
        $gateway = trim((string) ($payload['gateway'] ?? ''));
        $merchant = trim((string) ($payload['appId'] ?? ''));
        $localOrder = trim((string) ($payload['outTradeNo'] ?? ''));
        $providerOrder = trim((string) ($payload['tradeNo'] ?? ''));
        $amount = $this->decimal((string) ($payload['priceCny'] ?? ''));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $providerStatus = trim((string) ($payload['status'] ?? ''));
        // 网关状态数字映射为统一状态；不在映射表内的状态一律拒绝，避免误入账。
        $statusMap = ['0' => 'pending', '1' => 'pending', '2' => 'success', '-1' => 'failed', '-2' => 'failed'];
        if (!isset($statusMap[$providerStatus])) {
            throw new InvalidArgumentException('Unsupported TigerPay callback status.');
        }
        // 网关码与 appId 是回调归属校验的最后一道防线，不匹配直接拒绝，防止跨渠道错账。
        if (!hash_equals($this->required($channelConfig, 'gateway_code'), $gateway)) {
            throw new InvalidArgumentException('TigerPay callback gateway mismatch.');
        }
        if (!hash_equals($this->required($channelConfig, 'app_id'), $merchant)) {
            throw new InvalidArgumentException('TigerPay callback merchant mismatch.');
        }

        // 身份字段与渠道配置的期望值逐一比对；期望值未配置时跳过，不匹配即拒绝。
        $this->assertExpected($channelConfig, 'expected_gateway', $gateway);
        $this->assertExpected($channelConfig, 'expected_local_order_no', $localOrder);
        $this->assertExpected($channelConfig, 'expected_amount', $amount);
        $this->assertExpected($channelConfig, 'expected_currency', $currency);
        $this->assertExpected($channelConfig, 'expected_merchant_id', $merchant);

        // 仅保存解密后业务 JSON 的 SHA-256 摘要用于对账溯源，不落明文。
        return new PaymentCallback(
            $gateway,
            $localOrder,
            $providerOrder,
            $statusMap[$providerStatus],
            $amount,
            $currency,
            $merchant,
            hash('sha256', $json)
        );
    }

    /**
     * 向网关返回固定纯文本应答 'SUCCESS'；网关收到即认为回调已被业务接受。
     */
    public function acknowledge(PaymentCallback $callback): Response
    {
        return new Response('SUCCESS', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * 用网关公钥分块加密明文。
     *
     * RSA 单块明文上限为 bits/8 - 11 字节（PKCS#1 v1.5 填充占用），超长数据必须分块；
     * 结果做 base64 后 URL 编码，作为 data 参数传给网关。
     *
     * @param string $plain 待加密的业务 JSON。
     * @param string $publicKey PEM 格式网关公钥。
     * @return string URL 编码后的密文。
     * @throws InvalidArgumentException 公钥非法或加密失败时抛出。
     */
    private function encryptWithPublicKey(string $plain, string $publicKey): string
    {
        $key = openssl_pkey_get_public($publicKey);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($key === false || !is_array($details) || empty($details['bits'])) {
            throw new InvalidArgumentException('TigerPay server public key is invalid.');
        }
        $encrypted = '';
        foreach (str_split($plain, intdiv((int) $details['bits'], 8) - 11) as $chunk) {
            if (!openssl_public_encrypt($chunk, $block, $key, OPENSSL_PKCS1_PADDING)) {
                throw new InvalidArgumentException('TigerPay order encryption failed.');
            }
            $encrypted .= $block;
        }

        return rawurlencode(base64_encode($encrypted));
    }

    /**
     * 用本地私钥解密回调密文。
     *
     * @param string $encoded URL 编码并 base64 的密文。
     * @param string $privateKey PEM 格式本地私钥。
     * @return string 解密后的业务 JSON 明文。
     * @throws InvalidArgumentException 密文非法、私钥不可用或任一数据块解密失败时抛出。
     */
    private function decryptWithPrivateKey(string $encoded, string $privateKey): string
    {
        $cipher = base64_decode(rawurldecode($encoded), true);
        $key = openssl_pkey_get_private($privateKey);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (!is_string($cipher) || $cipher === '' || $key === false || !is_array($details) || empty($details['bits'])) {
            throw new InvalidArgumentException('TigerPay callback encryption is invalid.');
        }
        $blockSize = intdiv((int) $details['bits'], 8);
        // 密文长度必须是分块大小的整数倍，否则说明数据被截断或篡改，直接失败关闭。
        if (strlen($cipher) % $blockSize !== 0) {
            throw new InvalidArgumentException('TigerPay callback ciphertext length is invalid.');
        }
        $plain = '';
        foreach (str_split($cipher, $blockSize) as $block) {
            if (!openssl_private_decrypt($block, $chunk, $key, OPENSSL_PKCS1_PADDING)) {
                throw new InvalidArgumentException('TigerPay callback decryption failed.');
            }
            $plain .= $chunk;
        }

        return $plain;
    }

    /**
     * 用本地私钥对加密后的 data 做 MD5 签名，供网关验签。
     *
     * @param string $data URL 编码后的密文。
     * @param string $privateKey PEM 格式本地私钥。
     * @return string 原始二进制签名。
     * @throws InvalidArgumentException 私钥不可用或签名失败时抛出。
     */
    private function sign(string $data, string $privateKey): string
    {
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false || !openssl_sign($data, $signature, $key, OPENSSL_ALGO_MD5)) {
            throw new InvalidArgumentException('TigerPay order signature failed.');
        }

        return $signature;
    }

    /**
     * 规范化回调 data 供验签使用：URL 解码后必须是合法 base64，再重新 URL 编码。
     *
     * @param string $data 网关回调的 data 字段。
     * @return string 规范化后的签名输入；解码失败返回空字符串。
     */
    private function canonicalSignedData(string $data): string
    {
        $decoded = rawurldecode(trim($data));
        if ($decoded === '' || base64_decode($decoded, true) === false) {
            return '';
        }

        return rawurlencode($decoded);
    }

    /**
     * 解析指定密钥引用，经 SecretReference 校验后解析出真实密钥。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @param string $key 密钥引用字段名（server_public_key_reference / app_private_key_reference）。
     * @return string 解析出的密钥或证书内容，仅存在于内存中。
     * @throws InvalidArgumentException 引用非法或解析失败时抛出（fail-closed）。
     */
    private function secret(array $config, string $key): string
    {
        $reference = trim((string) ($config[$key] ?? ''));
        // 密钥只以引用形式出现在配置中；引用不合法或无法解析时直接失败，不落入默认值。
        if (!SecretReference::isValid($reference)) {
            throw new InvalidArgumentException('TigerPay secret reference is invalid: ' . $key);
        }
        $secret = ($this->secretResolver)($reference);
        if (!is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('TigerPay secret reference cannot be resolved: ' . $key);
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
            throw new InvalidArgumentException('Missing TigerPay configuration: ' . $key);
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
            throw new InvalidArgumentException('TigerPay amount must be a plain decimal value.');
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
            throw new InvalidArgumentException('TigerPay callback ' . $key . ' mismatch.');
        }
    }
}
