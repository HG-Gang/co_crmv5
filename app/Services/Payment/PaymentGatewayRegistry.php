<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayAdapter;
use App\Models\PaymentChannel;
use App\Support\SecretReference;

/**
 * 支付网关适配器注册中心。
 *
 * 文件功能：
 * - 管理所有支付网关适配器别名与货币支持的注册表。
 * - 根据支付渠道配置解析并返回可用的支付网关适配器实例。
 * - 校验渠道状态、配置完整性、货币支持等前置条件。
 *
 * 适用场景：
 * - 创建支付订单前，通过 PaymentChannel 记录解析对应的 PaymentGatewayAdapter。
 * - 支付回调时校验回调所属网关与注册表一致。
 *
 * 入参例子：
 * - channel: PaymentChannel 实例，含 is_enabled / channel_code / config。
 * - expectedGateway: 'tiger'（回调校验时可指定期望的网关码）。
 *
 * 返回值：
 * - ['adapter' => PaymentGatewayAdapter, 'config' => [...]]：解析成功。
 * - null：渠道未启用、配置不完整、适配器不支持时。
 *
 * 异常或失败场景：
 * - 不抛出异常，校验失败时统一返回 null。
 */
final class PaymentGatewayRegistry
{
    /**
     * 网关别名注册表：别名 => [适配器类/实例, 支持币种, 附加配置 profile]。
     * 是“网关码是否可用、支持哪些币种”的唯一事实来源；渠道解析与回调网关校验都查它，
     * 未注册的别名一律返回 null（失败关闭）。
     *
     * @var array<string, array{adapter: PaymentGatewayAdapter|string, currencies: array<int, string>, profile: array<string, mixed>}>
     */
    private $aliases = [];

    /**
     * 构造函数初始化内置适配器别名表。
     */
    public function __construct()
    {
        $this->aliases = $this->defaultAliases();
    }

    /**
     * 检查给定别名是否已注册。
     *
     * @param string $alias 适配器别名，如 'tiger' / 'wppay'。
     * @return bool 是否已注册。
     */
    public function supportsAlias(string $alias): bool
    {
        return isset($this->aliases[$this->normalizeAlias($alias)]);
    }

    /**
     * 注册支付网关适配器。
     *
     * @param string $alias 适配器别名，多个别名可指向同一适配器。
     * @param PaymentGatewayAdapter $adapter 适配器实例。
     * @param array<int, string> $supportedCurrencies 支持的币种列表，如 ['USD', 'CNY', 'USDT']。
     * @param array $profile 附加配置，如 ['pay_type' => 1]。
     *
     * @return void
     */
    public function register(string $alias, PaymentGatewayAdapter $adapter, array $supportedCurrencies, array $profile = []): void
    {
        // 别名归一化为小写；重复注册时保留原 profile，再合并新 profile，避免覆盖渠道特有参数。
        $key = $this->normalizeAlias($alias);
        $existingProfile = $this->aliases[$key]['profile'] ?? [];
        $this->aliases[$key] = [
            'adapter' => $adapter,
            'currencies' => array_values(array_unique(array_map('strtoupper', $supportedCurrencies))),
            'profile' => $existingProfile + $profile,
        ];
    }

    /**
     * 解析支付渠道：校验启用状态、网关码、配置完整性与币种支持，返回适配器实例与归一化配置。
     *
     * 任一步骤失败都返回 null 而不抛异常，调用方据此决定是否拒绝下单；这是失败关闭策略。
     *
     * @param PaymentChannel $channel 支付渠道记录。
     * @param string|null $expectedGateway 期望的网关码；指定后用于回调归属校验。
     * @return array{adapter: PaymentGatewayAdapter, config: array<string, mixed>}|null 解析失败返回 null。
     */
    public function resolve(PaymentChannel $channel, string $expectedGateway = null): ?array
    {
        // 渠道必须启用；停用渠道不允许再创建订单。
        if ((int) $channel->is_enabled !== 1) {
            return null;
        }

        // 网关码非空，且与期望值（回调场景）一致，防止把 A 渠道回调路由给 B 渠道。
        $gateway = trim((string) $channel->channel_code);
        if ($gateway === '' || ($expectedGateway !== null && !hash_equals($gateway, trim($expectedGateway)))) {
            return null;
        }

        // 配置必须完整，且密钥引用必须合法；无效配置在到达适配器前就被拦截。
        $config = is_array($channel->config) ? $channel->config : [];
        if (!$this->hasCompleteConfig($config)) {
            return null;
        }
        if (isset($config['gateway_code']) && !hash_equals($gateway, trim((string) $config['gateway_code']))) {
            return null;
        }

        $alias = $this->normalizeAlias((string) $config['adapter']);
        $definition = $this->aliases[$alias] ?? null;
        if ($definition === null) {
            return null;
        }

        // 渠道配置的币种必须在适配器声明支持的范围内，防止不支持币种被放行。
        $currency = strtoupper(trim((string) $config['currency']));
        if (!in_array($currency, $definition['currencies'], true)) {
            return null;
        }

        // 适配器可配置为类名（延迟实例化）：类不存在或实例化结果不是适配器时同样返回 null。
        $adapter = $definition['adapter'];
        if (is_string($adapter)) {
            if (!class_exists($adapter)) {
                return null;
            }
            $adapter = app($adapter);
        }
        if (!$adapter instanceof PaymentGatewayAdapter) {
            return null;
        }

        // 归一化币种大写，并合并渠道级 profile（如 pay_type），保证适配器拿到统一的最终配置。
        $config['currency'] = $currency;
        $config = array_replace($config, $definition['profile']);

        return ['adapter' => $adapter, 'config' => $config];
    }

    /**
     * 校验渠道配置完整性：必填字段齐全、商户号非空、密钥引用合法、端点 URL 存在（支持多端点键名兼容）。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @return bool 配置完整返回 true。
     */
    private function hasCompleteConfig(array $config): bool
    {
        foreach (['adapter', 'currency', 'amount_unit', 'notify_route', 'return_route'] as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                return false;
            }
        }

        $merchant = $this->firstNonEmpty($config, ['merchant_id', 'app_id']);
        $secretReference = $this->firstNonEmpty($config, ['secret_reference', 'key_reference']);
        $endpoint = '';
        foreach (['gateway_url', 'payment_url', 'pay_url', 'endpoint', 'url'] as $key) {
            $endpoint = trim((string) ($config[$key] ?? ''));
            if ($endpoint !== '') {
                break;
            }
        }

        return $merchant !== '' && SecretReference::isValid($secretReference) && $endpoint !== '';
    }

    /**
     * 归一化适配器别名：去首尾空白并转小写。
     *
     * 注册、查询与解析共用同一归一化规则，保证大小写差异的别名命中同一注册项。
     *
     * @param string $alias 原始别名。
     * @return string 归一化后的别名。
     */
    private function normalizeAlias(string $alias): string
    {
        return strtolower(trim($alias));
    }

    /**
     * 按优先级返回候选配置键中的第一个非空值。
     *
     * 用于兼容同名配置的不同历史键名（如 merchant_id/app_id），全部为空时返回空字符串。
     *
     * @param array<string, mixed> $config 渠道配置。
     * @param array<int, string> $keys 候选键名，按优先级排序。
     * @return string 第一个非空值；全部缺失或为空时返回空字符串。
     */
    private function firstNonEmpty(array $config, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * 内置适配器别名表：数字别名兼容历史渠道编号（1=tiger、2=wp 等），
     * 同一适配器可按不同 pay_type profile 注册为多个渠道。
     *
     * @return array<string, array{adapter: string, currencies: array<int, string>, profile: array<string, mixed>}>
     */
    private function defaultAliases(): array
    {
        $tiger = \App\Services\Payment\Gateways\TigerPayAdapter::class;
        $wp = \App\Services\Payment\Gateways\WpPayAdapter::class;
        $exlinkFiat = \App\Services\Payment\Gateways\ExlinkFiatAdapter::class;
        $exlinkCrypto = \App\Services\Payment\Gateways\ExlinkCryptoAdapter::class;
        $btb = \App\Services\Payment\Gateways\BtbAdapter::class;
        $passTo = \App\Services\Payment\Gateways\PassToAdapter::class;
        $switch = \App\Services\Payment\Gateways\SwitchAdapter::class;
        $otc = \App\Services\Payment\Gateways\OtcAdapter::class;
        $currencies = ['USD', 'CNY', 'USDT'];

        return [
            'tiger' => ['adapter' => $tiger, 'currencies' => $currencies, 'profile' => []],
            'tigerpay' => ['adapter' => $tiger, 'currencies' => $currencies, 'profile' => []],
            '1' => ['adapter' => $tiger, 'currencies' => $currencies, 'profile' => []],
            'wp' => ['adapter' => $wp, 'currencies' => $currencies, 'profile' => []],
            'wppay' => ['adapter' => $wp, 'currencies' => $currencies, 'profile' => []],
            '2' => ['adapter' => $wp, 'currencies' => $currencies, 'profile' => []],
            'exlink_fb' => ['adapter' => $exlinkFiat, 'currencies' => $currencies, 'profile' => ['pay_type' => 1]],
            '3' => ['adapter' => $exlinkFiat, 'currencies' => $currencies, 'profile' => ['pay_type' => 1]],
            '6' => ['adapter' => $exlinkFiat, 'currencies' => $currencies, 'profile' => ['pay_type' => 3]],
            '7' => ['adapter' => $exlinkFiat, 'currencies' => $currencies, 'profile' => ['pay_type' => 2]],
            'exlink_bb' => ['adapter' => $exlinkCrypto, 'currencies' => $currencies, 'profile' => []],
            '4' => ['adapter' => $exlinkCrypto, 'currencies' => $currencies, 'profile' => []],
            'btb' => ['adapter' => $btb, 'currencies' => $currencies, 'profile' => []],
            '5' => ['adapter' => $btb, 'currencies' => $currencies, 'profile' => []],
            'passto' => ['adapter' => $passTo, 'currencies' => $currencies, 'profile' => []],
            '8' => ['adapter' => $passTo, 'currencies' => $currencies, 'profile' => []],
            'switch' => ['adapter' => $switch, 'currencies' => $currencies, 'profile' => []],
            '9' => ['adapter' => $switch, 'currencies' => $currencies, 'profile' => ['pay_type' => 1]],
            '10' => ['adapter' => $switch, 'currencies' => $currencies, 'profile' => ['pay_type' => 2]],
            '11' => ['adapter' => $switch, 'currencies' => $currencies, 'profile' => ['pay_type' => 3]],
            'otc' => ['adapter' => $otc, 'currencies' => $currencies, 'profile' => []],
        ];
    }
}
