<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/11
 * Time: 10:34
 */

/**
 * AdminPaymentChannelToggleModuleTest
 *
 * 文件功能：
 * - 验证后台支付通道启停闭环：启停接口只翻转 is_enabled、textarea JSON 解码不双重编码、非法 JSON 与明文密钥被拒绝、引用字段混入明文密钥被拒绝。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Controllers\Admin\PaymentChannelController;
use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台支付通道启停闭环测试。
 *
 * 测试目标：
 * - 启停接口必须只翻转 payment_channels.is_enabled。
 * - 通道名称、编码、汇率和排序等配置字段不能被状态切换误改。
 */
class AdminPaymentChannelToggleModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 支付通道启停动作必须翻转 is_enabled 并保留其他配置。
     *
     * @return void
     */
    public function test_payment_channel_toggle_flips_enabled_status_only(): void
    {
        $id = DB::table('payment_channels')->insertGetId([
            'name' => 'Toggle Test Channel',
            'channel_code' => 'toggle_test_channel',
            'exchange_rate' => 1.2345,
            'is_enabled' => 1,
            'sort' => 88,
            'config' => json_encode(['merchant' => 'demo']),
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $response = (new PaymentChannelController())->toggleEnable($id);
        $payload = $response->getData(true);

        $this->assertSame(1000, (int) $payload['code']);
        $this->assertDatabaseHas('payment_channels', [
            'id' => $id,
            'name' => 'Toggle Test Channel',
            'channel_code' => 'toggle_test_channel',
            'exchange_rate' => 1.2345,
            'is_enabled' => 0,
            'sort' => 88,
        ]);

        (new PaymentChannelController())->toggleEnable($id);

        $this->assertDatabaseHas('payment_channels', [
            'id' => $id,
            'is_enabled' => 1,
        ]);
    }

    public function test_store_decodes_textarea_json_to_an_array_without_double_encoding(): void
    {
        $code = 'json_config_' . random_int(100000, 999999);
        $response = (new PaymentChannelController())->store(Request::create('/admin/channels', 'POST', [
            'name' => 'JSON Config Channel',
            'channel_code' => $code,
            'exchange_rate' => '1.00000000',
            'config' => json_encode([
                'adapter' => 'tiger',
                'merchant_id' => 'merchant-demo',
                'secret_reference' => 'env:PAYMENT_TIGER_SECRET',
            ]),
        ]));

        $this->assertSame(ResponseCode::CREATED, (int) $response->getData(true)['code']);
        $raw = DB::table('payment_channels')->where('channel_code', $code)->value('config');
        $decoded = json_decode((string) $raw, true);
        $this->assertIsArray($decoded);
        $this->assertSame('tiger', $decoded['adapter']);
    }

    /** @dataProvider forbiddenSecretConfigProvider */
    public function test_store_rejects_invalid_json_and_plain_secret_values(string $secretKey): void
    {
        $controller = new PaymentChannelController();
        $invalidCode = 'invalid_json_' . random_int(100000, 999999);
        $invalid = $controller->store(Request::create('/admin/channels', 'POST', [
            'name' => 'Invalid JSON Channel',
            'channel_code' => $invalidCode,
            'config' => '{invalid-json',
        ]));
        $this->assertSame(ResponseCode::VALIDATION_FAILED, (int) $invalid->getData(true)['code']);
        $this->assertDatabaseMissing('payment_channels', ['channel_code' => $invalidCode]);

        $secretCode = 'plain_secret_' . random_int(100000, 999999);
        $secret = $controller->store(Request::create('/admin/channels', 'POST', [
            'name' => 'Plain Secret Channel',
            'channel_code' => $secretCode,
            'config' => json_encode(['adapter' => 'tiger', $secretKey => 'must-not-persist']),
        ]));
        $this->assertSame(ResponseCode::VALIDATION_FAILED, (int) $secret->getData(true)['code']);
        $this->assertDatabaseMissing('payment_channels', ['channel_code' => $secretCode]);
    }

    public function forbiddenSecretConfigProvider(): array
    {
        return [
            'secret' => ['secret'],
            'client secret' => ['clientSecret'],
            'access token' => ['access-token'],
            'private key' => ['PRIVATE_KEY'],
            'api key' => ['api.key'],
            'notify key' => ['notifyKey'],
            'request key' => ['request-key'],
            'hmac key' => ['hmac_key'],
            'encryption key' => ['encryption.key'],
        ];
    }

    public function test_store_allows_explicit_secret_reference_suffixes(): void
    {
        $code = 'secret_refs_' . random_int(100000, 999999);
        $response = (new PaymentChannelController())->store(Request::create('/admin/channels', 'POST', [
            'name' => 'Reference Config Channel',
            'channel_code' => $code,
            'config' => json_encode([
                'secret_reference' => 'env:PAYMENT_SECRET_REFERENCE',
                'notify_key_ref' => 'env:PAYMENT_NOTIFY_KEY_REFERENCE',
            ]),
        ]));

        $this->assertSame(ResponseCode::CREATED, (int) $response->getData(true)['code']);
    }

    public function test_store_rejects_reference_fields_that_contain_plain_secret_values(): void
    {
        $code = 'plain_reference_' . random_int(100000, 999999);
        $response = (new PaymentChannelController())->store(Request::create('/admin/channels', 'POST', [
            'name' => 'Plain Reference Channel',
            'channel_code' => $code,
            'config' => json_encode([
                'adapter' => 'tiger',
                'secret_reference' => "actual secret\nvalue",
            ]),
        ]));

        $this->assertSame(ResponseCode::VALIDATION_FAILED, (int) $response->getData(true)['code']);
        $this->assertDatabaseMissing('payment_channels', ['channel_code' => $code]);
    }

    public function test_store_rejects_safe_character_plaintext_disguised_as_reference(): void
    {
        $code = 'plain_reference_safe_' . random_int(100000, 999999);
        $response = (new PaymentChannelController())->store(Request::create('/admin/channels', 'POST', [
            'name' => 'Disguised Plain Secret Channel',
            'channel_code' => $code,
            'config' => json_encode(['secret_reference' => 'sk-live-abcdef123']),
        ]));

        $this->assertSame(ResponseCode::VALIDATION_FAILED, (int) $response->getData(true)['code']);
        $this->assertDatabaseMissing('payment_channels', ['channel_code' => $code]);
    }
}
