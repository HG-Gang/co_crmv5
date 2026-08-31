<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/11
 * Time: 12:51
 */

/**
 * PaymentGatewayAdapterFixtureTest
 *
 * 文件功能：
 * - 验证支付网关适配器夹具契约：passto/switch/exlink/btb/wp/tiger 各渠道的下单字段与签名算法、回调验签与严格身份/金额/币种/状态匹配、小数与零整部分规范化、缺配置失败关闭。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DepositRecord;
use App\Services\Payment\PaymentCallback;
use App\Services\Payment\Gateways\PassToAdapter;
use App\Services\Payment\Gateways\SwitchAdapter;
use App\Services\Payment\Gateways\ExlinkFiatAdapter;
use App\Services\Payment\Gateways\ExlinkCryptoAdapter;
use App\Services\Payment\Gateways\BtbAdapter;
use App\Services\Payment\Gateways\WpPayAdapter;
use App\Services\Payment\Gateways\TigerPayAdapter;
use App\Services\Payment\Gateways\OtcAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentGatewayAdapterFixtureTest extends TestCase
{
    public function test_passto_create_order_uses_exact_minor_unit_fields_and_sorted_md5_signature(): void
    {
        $fixture = $this->fixture('passto');
        Http::fake([
            $fixture['config']['gateway_url'] => Http::response($fixture['create_response'], 200),
            '*' => Http::response([], 599),
        ]);
        $adapter = $this->passToAdapter();

        $result = $adapter->createOrder($this->order($fixture['order']), $fixture['config']);

        $this->assertSame('PASS-PROVIDER-1001', $result->providerOrderNumber());
        $this->assertSame('https://checkout.example.test/passto/1001', $result->redirectUrl());
        Http::assertSent(function ($request) use ($fixture): bool {
            $body = $request->data();
            $this->assertSame('merchant-fixture', $body['mchNo']);
            $this->assertSame('app-fixture', $body['appId']);
            $this->assertSame('DEP-FIXTURE-1001', $body['mchOrderNo']);
            $this->assertSame('CNY', $body['currency']);
            $this->assertSame(12345, $body['amount']);
            $this->assertSame('MD5', $body['signType']);
            $this->assertSame($this->passtoSign($body), $body['sign']);

            return $request->url() === $fixture['config']['gateway_url'];
        });
    }

    public function test_passto_callback_verifies_signature_and_parses_strict_identity_fields(): void
    {
        $fixture = $this->fixture('passto');
        $config = $fixture['config'] + [
            'expected_gateway' => 'passto',
            'expected_local_order_no' => 'DEP-FIXTURE-1001',
            'expected_amount' => '123.45',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'merchant-fixture',
        ];
        $payload = $fixture['callback'];
        $payload['sign'] = $this->passtoSign($payload);
        $this->assertSame($fixture['expected_callback_sign'], $payload['sign']);
        $request = Request::create('/callback', 'POST', $payload);
        $adapter = $this->passToAdapter();

        $this->assertTrue($adapter->verifyCallback($request, $config));
        $callback = $adapter->parseCallback($request, $config);
        $this->assertSame('passto', $callback->gatewayCode());
        $this->assertSame('DEP-FIXTURE-1001', $callback->localOrderNumber());
        $this->assertSame('PASS-PROVIDER-1001', $callback->providerOrderNumber());
        $this->assertSame('success', $callback->status());
        $this->assertSame('123.45', $callback->amount());
        $this->assertSame('CNY', $callback->currency());
        $this->assertSame('success', $adapter->acknowledge($callback)->getContent());
        $this->assertSame(
            'DEP-FIXTURE-1001',
            $adapter->parseCallback($request, $fixture['config'])->localOrderNumber()
        );

        $tampered = $payload;
        $tampered['amount'] = '12346';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $tampered), $config));
        unset($payload['sign']);
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $payload), $config));
    }

    /** @dataProvider passtoMinorAmountProvider */
    public function test_passto_callback_normalizes_minor_units_with_a_leading_zero(string $minor, string $decimal): void
    {
        $fixture = $this->fixture('passto');
        $payload = $fixture['callback'];
        $payload['amount'] = $minor;
        $payload['sign'] = $this->passtoSign($payload);
        $config = $fixture['config'] + [
            'expected_gateway' => 'passto',
            'expected_local_order_no' => 'DEP-FIXTURE-1001',
            'expected_amount' => $decimal,
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'merchant-fixture',
        ];

        $callback = $this->passToAdapter()->parseCallback(Request::create('/callback', 'POST', $payload), $config);

        $this->assertSame($decimal, $callback->amount());
    }

    public function passtoMinorAmountProvider(): array
    {
        return [
            'one cent' => ['1', '0.01'],
            'five cents' => ['5', '0.05'],
            'ninety nine cents' => ['99', '0.99'],
            'one yuan' => ['100', '1.00'],
        ];
    }

    public function test_passto_uses_first_non_empty_secret_reference(): void
    {
        $fixture = $this->fixture('passto');
        $config = $fixture['config'];
        $config['secret_reference'] = '';
        $config['key_reference'] = 'env:PAYMENT_FIXTURE_SECRET';
        $payload = $fixture['callback'];
        $payload['sign'] = $this->passtoSign($payload);

        $this->assertTrue($this->passToAdapter()->verifyCallback(Request::create('/callback', 'POST', $payload), $config));
    }

    /** @dataProvider passtoMismatchProvider */
    public function test_passto_callback_rejects_identity_amount_currency_and_status_mismatches(string $field, string $value): void
    {
        $fixture = $this->fixture('passto');
        $config = $fixture['config'] + [
            'expected_gateway' => 'passto',
            'expected_local_order_no' => 'DEP-FIXTURE-1001',
            'expected_amount' => '123.45',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'merchant-fixture',
        ];
        $payload = $fixture['callback'];
        $payload[$field] = $value;
        $payload['sign'] = $this->passtoSign($payload);

        $this->expectException(InvalidArgumentException::class);
        $this->passToAdapter()->parseCallback(Request::create('/callback', 'POST', $payload), $config);
    }

    public function passtoMismatchProvider(): array
    {
        return [
            'merchant' => ['mchNo', 'other-merchant'],
            'order' => ['mchOrderNo', 'DEP-FIXTURE-OTHER'],
            'amount' => ['amount', '12346'],
            'currency' => ['currency', 'USD'],
            'status' => ['state', '9'],
        ];
    }

    public function test_passto_create_order_fails_closed_when_secret_reference_cannot_resolve(): void
    {
        $fixture = $this->fixture('passto');
        $adapter = new PassToAdapter(static function (): ?string {
            return null;
        });
        Http::fake(['*' => Http::response([], 599)]);

        try {
            $adapter->createOrder($this->order($fixture['order']), $fixture['config']);
            $this->fail('PassTo must fail closed when its secret cannot resolve.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_switch_create_order_uses_profile_pay_type_decimal_amount_and_sorted_md5_signature(): void
    {
        $fixture = $this->fixture('switch');
        Http::fake([
            $fixture['config']['gateway_url'] => Http::response($fixture['create_response'], 200),
            '*' => Http::response([], 599),
        ]);
        $adapter = $this->switchAdapter();

        $result = $adapter->createOrder($this->order($fixture['order']), $fixture['config']);

        $this->assertSame('DEP-SWITCH-1001', $result->providerOrderNumber());
        $this->assertSame($fixture['create_response']['data'], $result->redirectUrl());
        Http::assertSent(function ($request) use ($fixture): bool {
            $body = $request->data();
            $this->assertSame('switch-merchant-fixture', $body['uid']);
            $this->assertSame('410002', $body['uniqueCode']);
            $this->assertSame('72.35', $body['money']);
            $this->assertSame(2, $body['payType']);
            $this->assertSame('DEP-SWITCH-1001', $body['orderId']);
            $this->assertSame($this->switchSign($body, 'fixture-request-key'), $body['signature']);

            return $request->url() === $fixture['config']['gateway_url'];
        });
    }

    public function test_switch_callback_uses_callback_key_and_parses_success(): void
    {
        $fixture = $this->fixture('switch');
        $config = $fixture['config'] + [
            'expected_gateway' => 'switch',
            'expected_local_order_no' => 'DEP-SWITCH-1001',
            'expected_amount' => '72.35',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'switch-merchant-fixture',
        ];
        $payload = $fixture['callback'];
        $payload['signature'] = $this->switchSign($payload, 'fixture-callback-key');
        $adapter = $this->switchAdapter();
        $request = Request::create('/callback', 'POST', $payload);

        $this->assertTrue($adapter->verifyCallback($request, $config));
        $callback = $adapter->parseCallback($request, $config);
        $this->assertSame('SWITCH-PROVIDER-1001', $callback->providerOrderNumber());
        $this->assertSame('success', $callback->status());
        $this->assertSame('72.35', $callback->amount());
        $ack = $adapter->acknowledge($callback);
        $this->assertSame(200, $ack->getStatusCode());
        $this->assertSame(1, json_decode($ack->getContent(), true)['code']);
        $this->assertSame('DEP-SWITCH-1001', $adapter->parseCallback($request, $fixture['config'])->localOrderNumber());

        $payload['money'] = '72.36';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $payload), $config));
    }

    public function test_switch_formats_zero_whole_part_as_canonical_decimal_before_provider_rejection(): void
    {
        $fixture = $this->fixture('switch');
        $fixture['order']['actual_amount'] = '0';
        Http::fake([
            $fixture['config']['gateway_url'] => Http::response([], 422),
            '*' => Http::response([], 599),
        ]);

        try {
            $this->switchAdapter()->createOrder($this->order($fixture['order']), $fixture['config']);
            $this->fail('The fixture provider must reject the zero-value order.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        Http::assertSent(function ($request) use ($fixture): bool {
            return $request->url() === $fixture['config']['gateway_url']
                && ($request->data()['money'] ?? null) === '0.00';
        });
    }

    /** @dataProvider switchMismatchProvider */
    public function test_switch_callback_rejects_strict_mismatches(string $field, string $value): void
    {
        $fixture = $this->fixture('switch');
        $config = $fixture['config'] + [
            'expected_gateway' => 'switch',
            'expected_local_order_no' => 'DEP-SWITCH-1001',
            'expected_amount' => '72.35',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'switch-merchant-fixture',
        ];
        $payload = $fixture['callback'];
        $payload[$field] = $value;
        $payload['signature'] = $this->switchSign($payload, 'fixture-callback-key');

        $this->expectException(InvalidArgumentException::class);
        $this->switchAdapter()->parseCallback(Request::create('/callback', 'POST', $payload), $config);
    }

    public function switchMismatchProvider(): array
    {
        return [
            'merchant' => ['mchNo', 'other-merchant'],
            'order' => ['apiOrderNo', 'DEP-SWITCH-OTHER'],
            'amount' => ['money', '72.36'],
            'currency' => ['currency', 'USD'],
            'status' => ['tradeStatus', '2'],
        ];
    }

    /** @dataProvider exlinkCreateProvider */
    public function test_exlink_create_order_uses_exact_family_fields_and_md5_signature(string $fixtureName, string $adapterClass): void
    {
        $fixture = $this->fixture($fixtureName);
        Http::fake([
            $fixture['config']['gateway_url'] => Http::response($fixture['create_response'], 200),
            '*' => Http::response([], 599),
        ]);
        $adapter = $this->exlinkAdapter($adapterClass);

        $result = $adapter->createOrder($this->order($fixture['order']), $fixture['config']);

        $this->assertSame($fixture['order']['local_order_no'], $result->providerOrderNumber());
        $this->assertSame($fixture['create_response']['data'], $result->redirectUrl());
        Http::assertSent(function ($request) use ($fixtureName, $fixture): bool {
            $body = $request->data();
            $this->assertSame($this->exlinkSign($body, 'fixture-exlink-request-key'), $body['signature']);
            if ($fixtureName === 'exlink_fiat') {
                $this->assertSame(3, $body['payType']);
                $this->assertSame('145.20', $body['money']);
            } else {
                $this->assertSame('TRC20', $body['protocol']);
                $this->assertSame('USDT', $body['coinName']);
                $this->assertSame('50.00', $body['amount']);
            }

            return $request->url() === $fixture['config']['gateway_url'];
        });
    }

    public function exlinkCreateProvider(): array
    {
        return [
            'fiat' => ['exlink_fiat', ExlinkFiatAdapter::class],
            'crypto' => ['exlink_crypto', ExlinkCryptoAdapter::class],
        ];
    }

    /** @dataProvider exlinkCallbackProvider */
    public function test_exlink_callbacks_require_signature_and_strict_protocol_identity(
        string $fixtureName,
        string $adapterClass,
        string $amountField
    ): void {
        $fixture = $this->fixture($fixtureName);
        $expectedAmount = $fixtureName === 'exlink_crypto'
            ? $fixture['order']['amount']
            : $fixture['order']['actual_amount'];
        $config = $fixture['config'] + [
            'expected_gateway' => $fixture['config']['gateway_code'],
            'expected_local_order_no' => $fixture['order']['local_order_no'],
            'expected_amount' => $expectedAmount,
            'expected_currency' => $fixture['config']['currency'],
            'expected_merchant_id' => $fixture['config']['merchant_id'],
        ];
        $payload = $fixture['callback'];
        $payload['signature'] = $this->exlinkSign($payload, 'fixture-exlink-callback-key');
        $adapter = $this->exlinkAdapter($adapterClass);
        $request = Request::create('/callback', 'POST', $payload);

        $this->assertTrue($adapter->verifyCallback($request, $config));
        $callback = $adapter->parseCallback($request, $config);
        $this->assertSame('success', $callback->status());
        $this->assertSame($expectedAmount, $callback->amount());
        $this->assertSame($fixture['config']['currency'], $callback->currency());
        $this->assertSame(
            $fixture['order']['local_order_no'],
            $adapter->parseCallback($request, $fixture['config'])->localOrderNumber()
        );

        $payload[$amountField] = '999.99';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $payload), $config));
    }

    public function exlinkCallbackProvider(): array
    {
        return [
            'fiat' => ['exlink_fiat', ExlinkFiatAdapter::class, 'money'],
            'crypto' => ['exlink_crypto', ExlinkCryptoAdapter::class, 'amount'],
        ];
    }

    /** @dataProvider exlinkMismatchProvider */
    public function test_exlink_callbacks_reject_mismatches(
        string $fixtureName,
        string $adapterClass,
        string $field,
        string $value
    ): void {
        $fixture = $this->fixture($fixtureName);
        $config = $fixture['config'] + [
            'expected_gateway' => $fixture['config']['gateway_code'],
            'expected_local_order_no' => $fixture['order']['local_order_no'],
            'expected_amount' => $fixture['order']['actual_amount'],
            'expected_currency' => $fixture['config']['currency'],
            'expected_merchant_id' => $fixture['config']['merchant_id'],
        ];
        $payload = $fixture['callback'];
        $payload[$field] = $value;
        $payload['signature'] = $this->exlinkSign($payload, 'fixture-exlink-callback-key');

        $this->expectException(InvalidArgumentException::class);
        $this->exlinkAdapter($adapterClass)->parseCallback(Request::create('/callback', 'POST', $payload), $config);
    }

    public function exlinkMismatchProvider(): array
    {
        return [
            'fiat merchant' => ['exlink_fiat', ExlinkFiatAdapter::class, 'uid', 'other-merchant'],
            'fiat order' => ['exlink_fiat', ExlinkFiatAdapter::class, 'apiOrderNo', 'DEP-OTHER'],
            'fiat currency' => ['exlink_fiat', ExlinkFiatAdapter::class, 'currency', 'USD'],
            'fiat status' => ['exlink_fiat', ExlinkFiatAdapter::class, 'tradeStatus', '2'],
            'crypto protocol' => ['exlink_crypto', ExlinkCryptoAdapter::class, 'protocol', 'ERC20'],
            'crypto coin' => ['exlink_crypto', ExlinkCryptoAdapter::class, 'coinName', 'BTC'],
            'crypto amount' => ['exlink_crypto', ExlinkCryptoAdapter::class, 'amount', '50.01'],
            'crypto status' => ['exlink_crypto', ExlinkCryptoAdapter::class, 'tradeStatus', '2'],
        ];
    }

    public function test_btb_create_order_returns_exact_signed_redirect_query(): void
    {
        $fixture = $this->fixture('btb');
        $adapter = $this->btbAdapter();

        $result = $adapter->createOrder($this->order($fixture['order']), $fixture['config']);
        $query = [];
        parse_str((string) parse_url($result->redirectUrl(), PHP_URL_QUERY), $query);

        $this->assertSame('DEP-BTB-1001', $result->providerOrderNumber());
        $this->assertSame('btb-merchant-fixture', $query['pid']);
        $this->assertSame('DEP-BTB-1001', $query['out_trade_no']);
        $this->assertSame('88.80', $query['money']);
        $this->assertSame('bank', $query['type']);
        $this->assertSame('1', (string) $query['isHtml']);
        $this->assertSame($this->btbSign($query, 'fixture-btb-request-key'), $query['sign']);
    }

    public function test_btb_callback_requires_signed_success_and_strict_order_identity(): void
    {
        $fixture = $this->fixture('btb');
        $config = $fixture['config'] + [
            'expected_gateway' => 'btb',
            'expected_local_order_no' => 'DEP-BTB-1001',
            'expected_amount' => '88.80',
            'expected_currency' => 'USD',
            'expected_merchant_id' => 'btb-merchant-fixture',
        ];
        $payload = $fixture['callback'];
        $payload['sign'] = $this->btbSign($payload, 'fixture-btb-callback-key');
        $adapter = $this->btbAdapter();
        $request = Request::create('/callback', 'POST', $payload);

        $this->assertTrue($adapter->verifyCallback($request, $config));
        $callback = $adapter->parseCallback($request, $config);
        $this->assertSame('BTB-PROVIDER-1001', $callback->providerOrderNumber());
        $this->assertSame('success', $callback->status());
        $this->assertSame('success', $adapter->acknowledge($callback)->getContent());
        $this->assertSame('DEP-BTB-1001', $adapter->parseCallback($request, $fixture['config'])->localOrderNumber());

        $payload['money'] = '88.81';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $payload), $config));
    }

    /** @dataProvider btbMismatchProvider */
    public function test_btb_callback_rejects_mismatches(string $field, string $value): void
    {
        $fixture = $this->fixture('btb');
        $config = $fixture['config'] + [
            'expected_gateway' => 'btb',
            'expected_local_order_no' => 'DEP-BTB-1001',
            'expected_amount' => '88.80',
            'expected_currency' => 'USD',
            'expected_merchant_id' => 'btb-merchant-fixture',
        ];
        $payload = $fixture['callback'];
        $payload[$field] = $value;
        $payload['sign'] = $this->btbSign($payload, 'fixture-btb-callback-key');

        $this->expectException(InvalidArgumentException::class);
        $this->btbAdapter()->parseCallback(Request::create('/callback', 'POST', $payload), $config);
    }

    public function btbMismatchProvider(): array
    {
        return [
            'merchant' => ['pid', 'other-merchant'],
            'order' => ['out_trade_no', 'DEP-BTB-OTHER'],
            'amount' => ['money', '88.81'],
            'currency' => ['currency', 'CNY'],
            'type' => ['type', 'crypto'],
            'status' => ['status', 'failed'],
        ];
    }

    public function test_wp_create_order_uses_exact_fields_and_uppercase_sha1_signature(): void
    {
        $fixture = $this->fixture('wp');
        Http::fake([
            $fixture['config']['gateway_url'] => Http::response($fixture['create_response'], 200),
            '*' => Http::response([], 599),
        ]);
        $adapter = $this->wpAdapter();

        $result = $adapter->createOrder($this->order($fixture['order']), $fixture['config']);

        $this->assertSame('DEP-WP-1001', $result->providerOrderNumber());
        $this->assertSame($fixture['create_response']['data']['pay_url'], $result->redirectUrl());
        Http::assertSent(function ($request) use ($fixture): bool {
            $body = $request->data();
            $this->assertSame('216.75', $body['amount']);
            $this->assertSame('13900000001', $body['mobile']);
            $this->assertSame('wp-fixture-user', $body['username']);
            $this->assertSame('DEP-WP-1001', $body['orderid']);
            $this->assertSame('CNY', $body['currency']);
            $this->assertSame('2', $body['type']);
            $this->assertSame('wp-app-fixture', $body['appid']);
            $this->assertSame($this->wpSign($body, 'fixture-wp-request-key'), $body['sign']);

            return $request->url() === $fixture['config']['gateway_url'];
        });
    }

    public function test_wp_callback_requires_signature_and_strict_identity(): void
    {
        $fixture = $this->fixture('wp');
        $config = $fixture['config'] + [
            'expected_gateway' => 'wppay',
            'expected_local_order_no' => 'DEP-WP-1001',
            'expected_amount' => '216.75',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'wp-app-fixture',
        ];
        $payload = $fixture['callback'];
        $payload['sign'] = $this->wpSign($payload, 'fixture-wp-callback-key');
        $adapter = $this->wpAdapter();
        $request = Request::create('/callback', 'POST', $payload);

        $this->assertTrue($adapter->verifyCallback($request, $config));
        $callback = $adapter->parseCallback($request, $config);
        $this->assertSame('WP-PROVIDER-1001', $callback->providerOrderNumber());
        $this->assertSame('success', $callback->status());
        $this->assertSame('216.75', $callback->amount());
        $this->assertSame('success', $adapter->acknowledge($callback)->getContent());
        $this->assertSame('DEP-WP-1001', $adapter->parseCallback($request, $fixture['config'])->localOrderNumber());

        $payload['total_price'] = '216.76';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $payload), $config));
    }

    /** @dataProvider wpMismatchProvider */
    public function test_wp_callback_rejects_mismatches(string $field, string $value): void
    {
        $fixture = $this->fixture('wp');
        $config = $fixture['config'] + [
            'expected_gateway' => 'wppay',
            'expected_local_order_no' => 'DEP-WP-1001',
            'expected_amount' => '216.75',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'wp-app-fixture',
        ];
        $payload = $fixture['callback'];
        $payload[$field] = $value;
        $payload['sign'] = $this->wpSign($payload, 'fixture-wp-callback-key');

        $this->expectException(InvalidArgumentException::class);
        $this->wpAdapter()->parseCallback(Request::create('/callback', 'POST', $payload), $config);
    }

    public function wpMismatchProvider(): array
    {
        return [
            'merchant' => ['appid', 'other-app'],
            'order' => ['order_id', 'DEP-WP-OTHER'],
            'amount' => ['total_price', '216.76'],
            'currency' => ['currency', 'USD'],
            'status' => ['tradeStatus', 'fail'],
        ];
    }

    public function test_tiger_create_order_returns_signed_encrypted_redirect_url(): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        $adapter = $this->tigerAdapter($keys);

        $result = $adapter->createOrder($this->order($fixture['order']), $fixture['config']);

        $this->assertSame('DEP-TIGER-1001', $result->providerOrderNumber());
        $rawQuery = (string) parse_url((string) $result->redirectUrl(), PHP_URL_QUERY);
        $this->assertStringNotContainsString('%25', $rawQuery);
        $rawData = $this->rawQueryValue($rawQuery, 'data');
        $rawSign = $this->rawQueryValue($rawQuery, 'sign');
        $this->assertNotFalse(base64_decode(rawurldecode($rawData), true));
        $this->assertNotFalse(base64_decode(rawurldecode($rawSign), true));
        parse_str($rawQuery, $query);
        $this->assertSame('tiger-app-fixture', $query['appId']);
        $this->assertSame('utf-8', $query['charset']);
        $this->assertSame('payq.trade.wap', $query['method']);
        $this->assertSame('1.0.0', $query['version']);
        $this->assertSame(1, openssl_verify(
            $rawData,
            base64_decode(rawurldecode($rawSign), true),
            $keys['app_public'],
            OPENSSL_ALGO_MD5
        ));

        $business = json_decode($this->tigerDecrypt($rawData, $keys['server_private']), true);
        $this->assertSame('DEP-TIGER-1001', $business['tradeNo']);
        $this->assertSame('321.45', $business['price']);
        $this->assertSame('410001', $business['userId']);
        $this->assertSame('tiger-fixture-user', $business['userName']);
        $this->assertSame('CNY', $business['currency']);
        $this->assertStringContainsString('/payment/notify/tigerpay', $business['notifyUrl']);
        $this->assertStringContainsString('/payment/return/tigerpay', $business['returnUrl']);
    }

    public function test_tiger_callback_verifies_rsa_chain_and_parses_strict_identity(): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        $adapter = $this->tigerAdapter($keys);
        $config = $fixture['config'] + [
            'expected_gateway' => 'tigerpay',
            'expected_local_order_no' => 'DEP-TIGER-1001',
            'expected_amount' => '321.45',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'tiger-app-fixture',
        ];
        $request = $this->tigerCallbackRequest($fixture['callback'], $keys);

        $this->assertTrue($adapter->verifyCallback($request, $config));
        $callback = $adapter->parseCallback($request, $config);
        $this->assertSame('tigerpay', $callback->gatewayCode());
        $this->assertSame('DEP-TIGER-1001', $callback->localOrderNumber());
        $this->assertSame('TIGER-PROVIDER-1001', $callback->providerOrderNumber());
        $this->assertSame('success', $callback->status());
        $this->assertSame('321.45', $callback->amount());
        $this->assertSame('CNY', $callback->currency());
        $this->assertSame('tiger-app-fixture', $callback->merchantId());
        $this->assertSame('SUCCESS', $adapter->acknowledge($callback)->getContent());
        $this->assertSame('DEP-TIGER-1001', $adapter->parseCallback($request, $fixture['config'])->localOrderNumber());

        $tampered = $request->all();
        $tampered['data'] .= 'x';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $tampered), $config));
        $tampered = $request->all();
        $tampered['sign'] .= 'x';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $tampered), $config));
    }

    public function test_tiger_callback_accepts_php_percent_decoded_form_values_and_rejects_tampering(): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        $adapter = $this->tigerAdapter($keys);
        $config = $fixture['config'] + [
            'expected_gateway' => 'tigerpay',
            'expected_local_order_no' => 'DEP-TIGER-1001',
            'expected_amount' => '321.45',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'tiger-app-fixture',
        ];
        $encoded = $this->tigerCallbackRequest($fixture['callback'], $keys)->all();
        $this->assertStringContainsString('%', $encoded['data']);
        $request = Request::create('/callback', 'POST', [
            'data' => rawurldecode($encoded['data']),
            'sign' => rawurldecode($encoded['sign']),
        ]);

        $this->assertTrue($adapter->verifyCallback($request, $config));
        $this->assertSame('success', $adapter->parseCallback($request, $config)->status());

        $tampered = $request->all();
        $tampered['data'] .= 'A';
        $this->assertFalse($adapter->verifyCallback(Request::create('/callback', 'POST', $tampered), $config));
    }

    /** @dataProvider tigerMismatchProvider */
    public function test_tiger_callback_rejects_resigned_identity_amount_currency_and_status_mismatches(string $field, string $value): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        $config = $fixture['config'] + [
            'expected_gateway' => 'tigerpay',
            'expected_local_order_no' => 'DEP-TIGER-1001',
            'expected_amount' => '321.45',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'tiger-app-fixture',
        ];
        $payload = $fixture['callback'];
        $payload[$field] = $value;

        $this->expectException(InvalidArgumentException::class);
        $this->tigerAdapter($keys)->parseCallback($this->tigerCallbackRequest($payload, $keys), $config);
    }

    public function tigerMismatchProvider(): array
    {
        return [
            'gateway' => ['gateway', 'other-gateway'],
            'merchant' => ['appId', 'other-app'],
            'order' => ['outTradeNo', 'DEP-TIGER-OTHER'],
            'amount' => ['priceCny', '321.46'],
            'currency' => ['currency', 'USD'],
            'status' => ['status', '99'],
        ];
    }

    /** @dataProvider tigerMissingConfigProvider */
    public function test_tiger_create_order_fails_closed_when_required_configuration_is_missing(string $field): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        unset($fixture['config'][$field]);

        $this->expectException(InvalidArgumentException::class);
        $this->tigerAdapter($keys)->createOrder($this->order($fixture['order']), $fixture['config']);
    }

    public function tigerMissingConfigProvider(): array
    {
        return [
            'endpoint' => ['gateway_url'],
            'app id' => ['app_id'],
            'app private key reference' => ['app_private_key_reference'],
            'server public key reference' => ['server_public_key_reference'],
        ];
    }

    /** @dataProvider tigerMalformedKeyProvider */
    public function test_tiger_create_order_fails_closed_for_malformed_rsa_keys(string $reference): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        $adapter = new TigerPayAdapter(static function (string $requested) use ($keys, $reference): ?string {
            if ($requested === $reference) {
                return 'not-a-rsa-key';
            }

            return $requested === 'env:PAYMENT_TIGER_APP_PRIVATE_KEY'
                ? $keys['app_private']
                : $keys['server_public'];
        });

        $this->expectException(InvalidArgumentException::class);
        $adapter->createOrder($this->order($fixture['order']), $fixture['config']);
    }

    public function tigerMalformedKeyProvider(): array
    {
        return [
            'app private key' => ['env:PAYMENT_TIGER_APP_PRIVATE_KEY'],
            'server public key' => ['env:PAYMENT_TIGER_SERVER_PUBLIC_KEY'],
        ];
    }

    public function test_tiger_callback_rejects_validly_signed_ciphertext_with_invalid_block_length(): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        $config = $fixture['config'] + [
            'expected_gateway' => 'tigerpay',
            'expected_local_order_no' => 'DEP-TIGER-1001',
            'expected_amount' => '321.45',
            'expected_currency' => 'CNY',
            'expected_merchant_id' => 'tiger-app-fixture',
        ];
        $data = rawurlencode(base64_encode('not-an-rsa-ciphertext-block'));
        $this->assertTrue(openssl_sign($data, $signature, $keys['server_private'], OPENSSL_ALGO_MD5));
        $request = Request::create('/callback', 'POST', [
            'data' => $data,
            'sign' => rawurlencode(base64_encode($signature)),
        ]);
        $adapter = $this->tigerAdapter($keys);
        $this->assertTrue($adapter->verifyCallback($request, $config));

        $this->expectException(InvalidArgumentException::class);
        $adapter->parseCallback($request, $config);
    }

    /** @dataProvider tigerUnresolvedRsaReferenceProvider */
    public function test_tiger_create_fails_closed_when_either_rsa_reference_cannot_resolve(string $missingReference): void
    {
        $fixture = $this->fixture('tiger');
        $keys = $this->tigerKeys();
        $adapter = new TigerPayAdapter(static function (string $reference) use ($keys, $missingReference): ?string {
            if ($reference === $missingReference) {
                return null;
            }
            $secrets = [
                'env:PAYMENT_TIGER_APP_PRIVATE_KEY' => $keys['app_private'],
                'env:PAYMENT_TIGER_SERVER_PUBLIC_KEY' => $keys['server_public'],
            ];

            return $secrets[$reference] ?? null;
        });

        $this->expectException(InvalidArgumentException::class);
        $adapter->createOrder($this->order($fixture['order']), $fixture['config']);
    }

    public function tigerUnresolvedRsaReferenceProvider(): array
    {
        return [
            'app private key' => ['env:PAYMENT_TIGER_APP_PRIVATE_KEY'],
            'server public key' => ['env:PAYMENT_TIGER_SERVER_PUBLIC_KEY'],
        ];
    }

    public function test_otc_create_and_callback_paths_fail_closed_because_protocol_is_unproven(): void
    {
        $fixture = $this->fixture('otc');
        $adapter = new OtcAdapter();
        $request = Request::create('/callback', 'POST', $fixture['callback']);

        $this->assertFalse($adapter->verifyCallback($request, $fixture['config']));

        try {
            $adapter->createOrder($this->order($fixture['order']), $fixture['config']);
            $this->fail('OTC createOrder must fail closed.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('unsupported', strtolower($exception->getMessage()));
        }

        $ack = $adapter->acknowledge(new PaymentCallback(
            'otc',
            'DEP-OTC-1001',
            'OTC-PROVIDER-1001',
            'failed',
            '50.00',
            'USDT',
            'otc-merchant-fixture',
            hash('sha256', 'otc-unsupported-fixture')
        ));
        $this->assertSame(422, $ack->getStatusCode());
        $this->assertSame('UNSUPPORTED', $ack->getContent());
        $this->assertNotSame('success', strtolower((string) $ack->getContent()));
        $this->assertNotSame('ok', strtolower((string) $ack->getContent()));
        $this->assertSame('text/plain; charset=UTF-8', $ack->headers->get('Content-Type'));

        $this->expectException(InvalidArgumentException::class);
        $adapter->parseCallback($request, $fixture['config']);
    }

    /** @dataProvider adapterRequiredCreateConfigProvider */
    public function test_every_gateway_adapter_fails_before_http_for_each_missing_required_configuration(
        string $fixtureName,
        array $requiredKeys
    ): void {
        foreach ($requiredKeys as $missingKey) {
            $fixture = $this->fixture($fixtureName);
            unset($fixture['config'][$missingKey]);
            Http::fake(['*' => Http::response([], 599)]);
            $sentBefore = count(Http::recorded());

            try {
                $this->adapterForFixture($fixtureName)->createOrder($this->order($fixture['order']), $fixture['config']);
                $this->fail($fixtureName . ' must fail closed without ' . $missingKey . '.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
            $this->assertSame(
                $sentBefore,
                count(Http::recorded()),
                $fixtureName . ' sent HTTP before rejecting missing ' . $missingKey . '.'
            );
        }
    }

    public function adapterRequiredCreateConfigProvider(): array
    {
        $common = ['gateway_code', 'gateway_url', 'currency', 'amount_unit', 'notify_route', 'return_route'];

        return [
            'Tiger' => ['tiger', array_merge($common, [
                'app_id', 'app_private_key_reference', 'server_public_key_reference', 'charset', 'method', 'version',
            ])],
            'WP' => ['wp', array_merge($common, [
                'app_id', 'secret_reference', 'callback_key_reference', 'payment_type', 'payer_mobile',
            ])],
            'Exlink fiat' => ['exlink_fiat', array_merge($common, [
                'merchant_id', 'secret_reference', 'callback_key_reference', 'pay_type',
            ])],
            'Exlink crypto' => ['exlink_crypto', array_merge($common, [
                'merchant_id', 'secret_reference', 'callback_key_reference', 'protocol', 'coin_name',
            ])],
            'BTB' => ['btb', array_merge($common, [
                'merchant_id', 'secret_reference', 'callback_key_reference', 'order_type',
            ])],
            'PassTo' => ['passto', array_merge($common, [
                'merchant_id', 'app_id', 'secret_reference', 'version',
            ])],
            'Switch' => ['switch', array_merge($common, [
                'merchant_id', 'secret_reference', 'callback_key_reference', 'pay_type',
            ])],
        ];
    }

    /** @dataProvider adapterUnresolvedSecretProvider */
    public function test_every_signed_gateway_fails_before_http_when_valid_secret_references_cannot_resolve(string $fixtureName): void
    {
        $fixture = $this->fixture($fixtureName);
        Http::fake(['*' => Http::response([], 599)]);

        try {
            $this->unresolvedAdapterForFixture($fixtureName)
                ->createOrder($this->order($fixture['order']), $fixture['config']);
            $this->fail($fixtureName . ' must fail closed when secret references cannot resolve.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function adapterUnresolvedSecretProvider(): array
    {
        return [
            'Tiger' => ['tiger'],
            'WP' => ['wp'],
            'Exlink fiat' => ['exlink_fiat'],
            'Exlink crypto' => ['exlink_crypto'],
            'BTB' => ['btb'],
            'PassTo' => ['passto'],
            'Switch' => ['switch'],
        ];
    }

    /** @dataProvider dualSecretResolutionProvider */
    public function test_dual_secret_gateways_fail_before_http_when_either_secret_cannot_resolve(
        string $fixtureName,
        string $missingReference
    ): void {
        $fixture = $this->fixture($fixtureName);
        Http::fake(['*' => Http::response([], 599)]);

        try {
            $this->adapterWithMissingReference($fixtureName, $missingReference)
                ->createOrder($this->order($fixture['order']), $fixture['config']);
            $this->fail($fixtureName . ' must fail closed when ' . $missingReference . ' cannot resolve.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function dualSecretResolutionProvider(): array
    {
        return [
            'WP request key' => ['wp', 'env:PAYMENT_WP_REQUEST_KEY'],
            'WP callback key' => ['wp', 'env:PAYMENT_WP_CALLBACK_KEY'],
            'Switch request key' => ['switch', 'env:PAYMENT_SWITCH_REQUEST_KEY'],
            'Switch callback key' => ['switch', 'env:PAYMENT_SWITCH_CALLBACK_KEY'],
            'Exlink fiat request key' => ['exlink_fiat', 'env:PAYMENT_EXLINK_REQUEST_KEY'],
            'Exlink fiat callback key' => ['exlink_fiat', 'env:PAYMENT_EXLINK_CALLBACK_KEY'],
            'Exlink crypto request key' => ['exlink_crypto', 'env:PAYMENT_EXLINK_REQUEST_KEY'],
            'Exlink crypto callback key' => ['exlink_crypto', 'env:PAYMENT_EXLINK_CALLBACK_KEY'],
            'BTB request key' => ['btb', 'env:PAYMENT_BTB_REQUEST_KEY'],
            'BTB callback key' => ['btb', 'env:PAYMENT_BTB_CALLBACK_KEY'],
        ];
    }

    private function passToAdapter(): PassToAdapter
    {
        return new PassToAdapter(static function (string $reference): ?string {
            return $reference === 'env:PAYMENT_FIXTURE_SECRET' ? 'fixture-signing-key' : null;
        });
    }

    private function switchAdapter(): SwitchAdapter
    {
        return new SwitchAdapter(static function (string $reference): ?string {
            $secrets = [
                'env:PAYMENT_SWITCH_REQUEST_KEY' => 'fixture-request-key',
                'env:PAYMENT_SWITCH_CALLBACK_KEY' => 'fixture-callback-key',
            ];

            return $secrets[$reference] ?? null;
        });
    }

    private function exlinkAdapter(string $adapterClass)
    {
        return new $adapterClass(static function (string $reference): ?string {
            $secrets = [
                'env:PAYMENT_EXLINK_REQUEST_KEY' => 'fixture-exlink-request-key',
                'env:PAYMENT_EXLINK_CALLBACK_KEY' => 'fixture-exlink-callback-key',
            ];

            return $secrets[$reference] ?? null;
        });
    }

    private function btbAdapter(): BtbAdapter
    {
        return new BtbAdapter(static function (string $reference): ?string {
            $secrets = [
                'env:PAYMENT_BTB_REQUEST_KEY' => 'fixture-btb-request-key',
                'env:PAYMENT_BTB_CALLBACK_KEY' => 'fixture-btb-callback-key',
            ];

            return $secrets[$reference] ?? null;
        });
    }

    private function wpAdapter(): WpPayAdapter
    {
        return new WpPayAdapter(static function (string $reference): ?string {
            $secrets = [
                'env:PAYMENT_WP_REQUEST_KEY' => 'fixture-wp-request-key',
                'env:PAYMENT_WP_CALLBACK_KEY' => 'fixture-wp-callback-key',
            ];

            return $secrets[$reference] ?? null;
        });
    }

    /** @param array<string, string> $keys */
    private function tigerAdapter(array $keys): TigerPayAdapter
    {
        return new TigerPayAdapter(static function (string $reference) use ($keys): ?string {
            $secrets = [
                'env:PAYMENT_TIGER_APP_PRIVATE_KEY' => $keys['app_private'],
                'env:PAYMENT_TIGER_SERVER_PUBLIC_KEY' => $keys['server_public'],
            ];

            return $secrets[$reference] ?? null;
        });
    }

    private function adapterForFixture(string $fixtureName)
    {
        switch ($fixtureName) {
            case 'tiger':
                return $this->tigerAdapter($this->tigerKeys());
            case 'wp':
                return $this->wpAdapter();
            case 'exlink_fiat':
                return $this->exlinkAdapter(ExlinkFiatAdapter::class);
            case 'exlink_crypto':
                return $this->exlinkAdapter(ExlinkCryptoAdapter::class);
            case 'btb':
                return $this->btbAdapter();
            case 'passto':
                return $this->passToAdapter();
            case 'switch':
                return $this->switchAdapter();
            case 'otc':
                return new OtcAdapter();
        }

        throw new InvalidArgumentException('Unknown payment fixture: ' . $fixtureName);
    }

    private function unresolvedAdapterForFixture(string $fixtureName)
    {
        $resolver = static function (): ?string {
            return null;
        };
        switch ($fixtureName) {
            case 'tiger':
                return new TigerPayAdapter($resolver);
            case 'wp':
                return new WpPayAdapter($resolver);
            case 'exlink_fiat':
                return new ExlinkFiatAdapter($resolver);
            case 'exlink_crypto':
                return new ExlinkCryptoAdapter($resolver);
            case 'btb':
                return new BtbAdapter($resolver);
            case 'passto':
                return new PassToAdapter($resolver);
            case 'switch':
                return new SwitchAdapter($resolver);
        }

        throw new InvalidArgumentException('Unknown signed payment fixture: ' . $fixtureName);
    }

    private function adapterWithMissingReference(string $fixtureName, string $missingReference)
    {
        $resolver = static function (string $reference) use ($missingReference): ?string {
            if ($reference === $missingReference) {
                return null;
            }
            $secrets = [
                'env:PAYMENT_WP_REQUEST_KEY' => 'fixture-wp-request-key',
                'env:PAYMENT_WP_CALLBACK_KEY' => 'fixture-wp-callback-key',
                'env:PAYMENT_SWITCH_REQUEST_KEY' => 'fixture-request-key',
                'env:PAYMENT_SWITCH_CALLBACK_KEY' => 'fixture-callback-key',
                'env:PAYMENT_EXLINK_REQUEST_KEY' => 'fixture-exlink-request-key',
                'env:PAYMENT_EXLINK_CALLBACK_KEY' => 'fixture-exlink-callback-key',
                'env:PAYMENT_BTB_REQUEST_KEY' => 'fixture-btb-request-key',
                'env:PAYMENT_BTB_CALLBACK_KEY' => 'fixture-btb-callback-key',
            ];

            return $secrets[$reference] ?? null;
        };
        switch ($fixtureName) {
            case 'wp':
                return new WpPayAdapter($resolver);
            case 'switch':
                return new SwitchAdapter($resolver);
            case 'exlink_fiat':
                return new ExlinkFiatAdapter($resolver);
            case 'exlink_crypto':
                return new ExlinkCryptoAdapter($resolver);
            case 'btb':
                return new BtbAdapter($resolver);
        }

        throw new InvalidArgumentException('Unknown dual-secret fixture: ' . $fixtureName);
    }

    /** @return array<string, string> */
    private function tigerKeys(): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $windowsConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($windowsConfig)) {
            $options['config'] = $windowsConfig;
        }
        $app = openssl_pkey_new($options);
        $server = openssl_pkey_new($options);
        $this->assertNotFalse($app);
        $this->assertNotFalse($server);
        $this->assertTrue(openssl_pkey_export($app, $appPrivate, null, $options));
        $this->assertTrue(openssl_pkey_export($server, $serverPrivate, null, $options));
        $appDetails = openssl_pkey_get_details($app);
        $serverDetails = openssl_pkey_get_details($server);
        $this->assertIsArray($appDetails);
        $this->assertIsArray($serverDetails);

        return [
            'app_private' => $appPrivate,
            'app_public' => $appDetails['key'],
            'server_private' => $serverPrivate,
            'server_public' => $serverDetails['key'],
        ];
    }

    /** @param array<string, mixed> $business @param array<string, string> $keys */
    private function tigerCallbackRequest(array $business, array $keys): Request
    {
        $data = $this->tigerEncrypt((string) json_encode($business), $keys['app_public']);
        $this->assertTrue(openssl_sign($data, $signature, $keys['server_private'], OPENSSL_ALGO_MD5));

        return Request::create('/callback', 'POST', [
            'data' => $data,
            'sign' => rawurlencode(base64_encode($signature)),
        ]);
    }

    private function tigerEncrypt(string $plain, string $publicKey): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));
        $this->assertIsArray($details);
        $chunkSize = intdiv($details['bits'], 8) - 11;
        $encrypted = '';
        foreach (str_split($plain, $chunkSize) as $chunk) {
            $this->assertTrue(openssl_public_encrypt($chunk, $block, $publicKey, OPENSSL_PKCS1_PADDING));
            $encrypted .= $block;
        }

        return rawurlencode(base64_encode($encrypted));
    }

    private function tigerDecrypt(string $encoded, string $privateKey): string
    {
        $cipher = base64_decode(rawurldecode($encoded), true);
        $details = openssl_pkey_get_details(openssl_pkey_get_private($privateKey));
        $this->assertIsArray($details);
        $plain = '';
        foreach (str_split((string) $cipher, intdiv($details['bits'], 8)) as $block) {
            $this->assertTrue(openssl_private_decrypt($block, $chunk, $privateKey, OPENSSL_PKCS1_PADDING));
            $plain .= $chunk;
        }

        return $plain;
    }

    private function rawQueryValue(string $query, string $name): string
    {
        $matched = preg_match('/(?:^|&)' . preg_quote($name, '/') . '=([^&]*)/', $query, $matches);
        $this->assertSame(1, $matched);

        return $matches[1];
    }

    /** @return array<string, mixed> */
    private function fixture(string $gateway): array
    {
        $json = file_get_contents(base_path('tests/Fixtures/payment/' . $gateway . '.json'));
        $fixture = json_decode((string) $json, true);
        $this->assertIsArray($fixture);

        return $fixture;
    }

    /** @param array<string, mixed> $attributes */
    private function order(array $attributes): DepositRecord
    {
        $order = new DepositRecord();
        $order->forceFill($attributes);

        return $order;
    }

    /** @param array<string, mixed> $payload */
    private function passtoSign(array $payload): string
    {
        unset($payload['sign']);
        ksort($payload, SORT_STRING);
        $signing = '';
        foreach ($payload as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signing .= $key . '=' . $value . '&';
            }
        }

        return strtoupper(md5($signing . 'key=fixture-signing-key'));
    }

    /** @param array<string, mixed> $payload */
    private function switchSign(array $payload, string $key): string
    {
        unset($payload['signature'], $payload['sign']);
        ksort($payload);
        $parts = [];
        foreach ($payload as $name => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $name . '=' . $value;
            }
        }

        return md5(implode('&', $parts) . '&key=' . $key);
    }

    /** @param array<string, mixed> $payload */
    private function exlinkSign(array $payload, string $key): string
    {
        unset($payload['signature']);
        ksort($payload, SORT_STRING);
        $parts = [];
        foreach ($payload as $name => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $name . '=' . $value;
            }
        }

        return md5(implode('&', $parts) . '&key=' . $key);
    }

    /** @param array<string, mixed> $payload */
    private function btbSign(array $payload, string $key): string
    {
        unset($payload['sign']);
        ksort($payload, SORT_STRING);

        return md5(http_build_query($payload) . '&key=' . $key);
    }

    /** @param array<string, mixed> $payload */
    private function wpSign(array $payload, string $key): string
    {
        unset($payload['sign']);
        ksort($payload, SORT_STRING);
        $signing = '';
        foreach ($payload as $name => $value) {
            $signing .= $name . $value;
        }

        return strtoupper(sha1($signing . $key));
    }
}
