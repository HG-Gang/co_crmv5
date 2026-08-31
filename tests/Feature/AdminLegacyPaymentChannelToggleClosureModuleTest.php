<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:38
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\LegacyAdminAuthenticate;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 旧后台支付通道批量配置兼容入口闭环测试。
 *
 * 文件功能：
 * - 验证项目1 `channel_enable` 与 `channel_enableV2` 的 11 路通道批量配置协议。
 * - 验证旧字段 `channel_N`、`channel_N_money`、`sort_N` 和 `default_channel` 会写入项目2 `payment_channels`。
 * - 验证旧批量请求不会被误转发为现代单条 toggle，避免把批量字段当成 payment_channels.id。
 */
class AdminLegacyPaymentChannelToggleClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 旧 `channel_enableV2` 入口应批量更新 1 到 11 号旧支付通道。
     *
     * 执行链路：
     * - 旧页面提交 channel_N 表示是否显示，写入 payment_channels.is_enabled。
     * - channel_N_money 表示旧 SystemParam.para_data1，项目2写入 config.min_amount。
     * - default_channel 表示默认通道编号，项目2写入 config.is_default。
     */
    public function test_legacy_channel_enable_v2_updates_legacy_channel_batch_configuration(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->seedLegacyChannels(3);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/channel_enableV2', $this->legacyBatchPayload([
                'channel_5' => '0',
                'channel_2_money' => '88.80',
                'sort_2' => '5',
                'default_channel' => '2',
            ]));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOERR')
            ->assertJsonPath('col', 'NOTCOL')
            ->assertJsonPath('data.total', 11)
            ->assertJsonPath('data.updated', 11)
            ->assertJsonPath('data.default_channel', 2);

        $channelTwo = $this->legacyChannel('2');
        $channelFive = $this->legacyChannel('5');
        $channelThree = $this->legacyChannel('3');

        $this->assertSame(1, (int) $channelTwo->is_enabled);
        $this->assertSame(5, (int) $channelTwo->sort);
        $this->assertSame(88.8, (float) $this->legacyChannelConfig($channelTwo)['min_amount']);
        $this->assertSame(1, (int) $this->legacyChannelConfig($channelTwo)['is_default']);
        $this->assertSame(0, (int) $channelFive->is_enabled);
        $this->assertSame(0, (int) $this->legacyChannelConfig($channelFive)['is_default']);
        $this->assertSame(0, (int) $this->legacyChannelConfig($channelThree)['is_default']);
    }

    /**
     * 旧 `channel_enable` 入口应更新启用、最低金额和排序，但不改默认通道。
     *
     * 执行链路：
     * - 项目1非 V2 方法没有 default_channel 写入逻辑。
     * - 项目2兼容层保留该边界，只改 is_enabled、config.min_amount 和 sort。
     */
    public function test_legacy_channel_enable_updates_batch_fields_without_changing_default_flags(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->seedLegacyChannels(4);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/channel_enable', $this->legacyBatchPayload([
                'channel_1' => '0',
                'channel_1_money' => '12.50',
                'sort_1' => '7',
                'default_channel' => null,
            ]));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('data.default_channel', null);

        $channelOne = $this->legacyChannel('1');
        $channelFour = $this->legacyChannel('4');

        $this->assertSame(0, (int) $channelOne->is_enabled);
        $this->assertSame(7, (int) $channelOne->sort);
        $this->assertSame(12.5, (float) $this->legacyChannelConfig($channelOne)['min_amount']);
        $this->assertSame(1, (int) $this->legacyChannelConfig($channelFour)['is_default']);
    }

    /**
     * 缺少任一通道启用字段时必须返回参数错误并保持原数据。
     *
     * 执行链路：
     * - 旧页面 radio 正常会提交 channel_1 到 channel_11。
     * - 缺字段代表请求体不完整，兼容层不构造默认值，避免关闭或开启错误通道。
     */
    public function test_legacy_channel_enable_v2_rejects_missing_channel_flag_without_mutating_channels(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->seedLegacyChannels(2);
        $payload = $this->legacyBatchPayload(['default_channel' => '2']);
        unset($payload['channel_1']);
        $before = $this->legacyChannel('1');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/channel_enableV2', $payload);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $after = $this->legacyChannel('1');
        $this->assertSame((int) $before->is_enabled, (int) $after->is_enabled);
        $this->assertSame((int) $before->sort, (int) $after->sort);
        $this->assertSame($this->legacyChannelConfig($before), $this->legacyChannelConfig($after));
    }

    /**
     * 创建测试管理员账号。
     *
     * @return Admin 后台管理员模型，供 actingAs 绑定 admin guard。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-legacy-channel-toggle-super',
                'email' => 'admin-legacy-channel-toggle-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 写入 1 到 11 号旧通道测试数据。
     *
     * @param int $defaultChannel 默认通道编号，对应 config.is_default=1。
     * @return void
     */
    private function seedLegacyChannels(int $defaultChannel): void
    {
        $now = time();

        DB::table('payment_channels')
            ->whereIn('channel_code', $this->legacyChannelCodes())
            ->delete();

        for ($index = 1; $index <= 11; $index++) {
            DB::table('payment_channels')->insert([
                'name' => 'Legacy Channel ' . $index,
                'channel_code' => (string) $index,
                'exchange_rate' => 1.2345,
                'is_enabled' => 1,
                'sort' => 100 + $index,
                'config' => json_encode([
                    'label_key' => 'front.channel_' . $index,
                    'min_amount' => (float) ($index * 10),
                    'max_amount' => 500000,
                    'daily_amount' => null,
                    'is_default' => $index === $defaultChannel ? 1 : 0,
                    'legacy_para_name' => 'PAYMENT_CHANNEL_' . $index,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }

    /**
     * 构造旧 Layui 批量通道表单。
     *
     * @param array<string, string|null> $overrides 覆盖字段；传入 null 表示移除该字段。
     * @return array<string, string> 旧页面提交字段。
     */
    private function legacyBatchPayload(array $overrides = []): array
    {
        $payload = [];
        for ($index = 1; $index <= 11; $index++) {
            $payload['channel_' . $index] = '1';
            $payload['channel_' . $index . '_money'] = '';
            $payload['sort_' . $index] = '';
            $payload['default_' . $index] = '0';
        }
        $payload['default_channel'] = '1';

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);
                continue;
            }
            $payload[$key] = $value;
        }

        return $payload;
    }

    /** @return array<int, string> 旧通道编号列表。 */
    private function legacyChannelCodes(): array
    {
        return array_map('strval', range(1, 11));
    }

    /**
     * 读取指定旧通道。
     *
     * @param string $code 旧通道编号，示例：`1`。
     * @return object 数据库行对象。
     */
    private function legacyChannel(string $code): object
    {
        $channel = DB::table('payment_channels')->where('channel_code', $code)->first();
        $this->assertNotNull($channel, '测试支付通道不存在：' . $code);

        return $channel;
    }

    /**
     * 解析支付通道 JSON 配置。
     *
     * @param object $channel payment_channels 行对象。
     * @return array<string, mixed> config 字段解析结果。
     */
    private function legacyChannelConfig(object $channel): array
    {
        return json_decode((string) $channel->config, true) ?: [];
    }
}
