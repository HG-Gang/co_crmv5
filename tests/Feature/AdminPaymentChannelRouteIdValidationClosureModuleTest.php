<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台支付渠道更新、删除、启停接口路由 id 严格校验的功能测试。
 *
 * 文件功能：
 * - 验证路由参数 id 传入非严格数字时更新、删除、启停接口均返回校验失败。
 * - 验证校验失败后渠道记录的数据不被改动。
 *
 * 适用场景：
 * - 支付渠道管理页面的更新、删除、启停操作，防止非法路由 id 误操作。
 *
 * 入参例子：
 * - POST /api/admin/updateChannel/{id}abc、/api/admin/deleteChannel/{id}abc、/api/admin/toggleChannel/{id}abc。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 路由 id 非严格整数时接口拒绝执行并保持原渠道数据不变。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPaymentChannelRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 验证更新渠道时非严格路由 id 被拒绝且渠道原字段保持不变。
    public function test_update_channel_rejects_non_strict_route_id_without_changing_channel(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedChannel('route_id_channel_update', 'Route Id Channel Original', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateChannel/' . $targetId . 'abc', [
                'channel_name' => 'Route Id Channel Updated',
                'channel_code' => 'route_id_channel_updated',
                'exchange_rate' => 2.3456,
                'is_enabled' => 0,
                'sort' => 99,
                'config' => json_encode(['merchant' => 'updated']),
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $channel = DB::table('payment_channels')->where('id', $targetId)->first();

        $this->assertSame('Route Id Channel Original', (string) $channel->name);
        $this->assertSame('route_id_channel_update', (string) $channel->channel_code);
        $this->assertSame('1.2345', number_format((float) $channel->exchange_rate, 4, '.', ''));
        $this->assertSame(1, (int) $channel->is_enabled);
        $this->assertSame(88, (int) $channel->sort);
        $this->assertSame(['merchant' => 'original'], json_decode((string) $channel->config, true));
        $this->assertNull($channel->deleted_at);
    }

    // 验证删除渠道时非严格路由 id 被拒绝且渠道记录未被删除。
    public function test_delete_channel_rejects_non_strict_route_id_without_deleting_channel(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedChannel('route_id_channel_delete', 'Route Id Channel Delete', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteChannel/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $channel = DB::table('payment_channels')->where('id', $targetId)->first();

        $this->assertNotNull($channel);
        $this->assertNull($channel->deleted_at);
        $this->assertSame('Route Id Channel Delete', (string) $channel->name);
    }

    // 验证启停渠道时非严格路由 id 被拒绝且启用状态保持不变。
    public function test_toggle_channel_rejects_non_strict_route_id_without_changing_enabled_status(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedChannel('route_id_channel_toggle', 'Route Id Channel Toggle', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/toggleChannel/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(1, (int) DB::table('payment_channels')->where('id', $targetId)->value('is_enabled'));
    }

    // 校验最终检查清单文档记录了支付渠道路由 id 校验边界。
    public function test_final_checklist_records_payment_channel_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 291.', $checklist);
        $this->assertStringContainsString('PaymentChannelController::update', $checklist);
        $this->assertStringContainsString('PaymentChannelController::destroy', $checklist);
        $this->assertStringContainsString('PaymentChannelController::toggleEnable', $checklist);
        $this->assertStringContainsString('/api/admin/updateChannel/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/deleteChannel/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/toggleChannel/{id}', $checklist);
        $this->assertStringContainsString('payment_channels.id', $checklist);
        $this->assertStringContainsString('AdminPaymentChannelRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-payment-channel-route-id-super',
                'email' => 'admin-payment-channel-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedChannel(string $code, string $name, int $isEnabled): int
    {
        $now = time();

        DB::table('payment_channels')
            ->whereIn('channel_code', [$code, 'route_id_channel_updated'])
            ->delete();

        return (int) DB::table('payment_channels')->insertGetId([
            'name' => $name,
            'channel_code' => $code,
            'exchange_rate' => 1.2345,
            'is_enabled' => $isEnabled,
            'sort' => 88,
            'config' => json_encode(['merchant' => 'original']),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
