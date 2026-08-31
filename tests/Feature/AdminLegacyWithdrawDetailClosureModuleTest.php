<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:21
 */

/**
 * AdminLegacyWithdrawDetailClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台出金详情页闭环：只读快照与动态文本转义、大精度金额无浮点损失、非法/缺失记录 404、越权记录隐藏、Blade 样式遵循视觉契约。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLegacyWithdrawDetailClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detail_renders_the_read_only_withdraw_snapshot_and_escapes_dynamic_text(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $recordId = $this->seedWithdraw(993201, 'WITHDRAW-DETAIL-ORDER', 3, '<script>alert("x")</script>');

        $response = $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/' . $recordId)
            ->assertOk()
            ->assertViewIs('admin_layui::withdrawals.detail')
            ->assertViewHas('withdraw', function ($value) use ($recordId): bool {
                return (int) $value->id === $recordId;
            })
            ->assertSee('WITHDRAW-DETAIL-ORDER')
            ->assertSee('62220000993201')
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false)
            ->assertSee('data-withdraw-detail-page="1"', false)
            ->assertSee('data-status="3"', false);

        $this->assertStringNotContainsString('data-withdraw-action=', $response->getContent());
        $this->assertStringNotContainsString('<script>alert("x")</script>', $response->getContent());
    }

    public function test_detail_preserves_large_decimal_amounts_without_float_precision_loss(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $recordId = $this->seedWithdraw(993207, 'WITHDRAW-DETAIL-LARGE-DECIMAL', 0, '');
        DB::table('withdraw_records')->where('id', $recordId)->update([
            'apply_amount' => '99999999999999.99',
            'actual_amount' => '99999999999999.96',
            'fee' => '99999999999999.93',
            'rmb_fee' => '99999999999999.87',
        ]);

        $content = $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/' . $recordId)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('99999999999999.99', $content);
        $this->assertStringContainsString('99999999999999.96', $content);
        $this->assertStringContainsString('99999999999999.93', $content);
        $this->assertStringContainsString('99999999999999.87', $content);
        $this->assertStringNotContainsString('99999999999999.98', $content);
        $this->assertStringNotContainsString('99999999999999.95', $content);
        $this->assertStringNotContainsString('99999999999999.94', $content);
        $this->assertStringNotContainsString('99999999999999.88', $content);
    }

    public function test_detail_returns_not_found_for_invalid_or_missing_record_ids(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/not-an-id')
            ->assertNotFound();

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/2147483000')
            ->assertNotFound();
    }

    public function test_detail_hides_out_of_scope_records_as_not_found(): void
    {
        $allowedUserId = 993202;
        $blockedUserId = 993203;
        $admin = $this->seedScopedAdmin(993204, 993204, [$allowedUserId]);
        $allowedId = $this->seedWithdraw($allowedUserId, 'WITHDRAW-DETAIL-ALLOWED', 0, '');
        $blockedId = $this->seedWithdraw($blockedUserId, 'WITHDRAW-DETAIL-BLOCKED', 0, '');

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/' . $allowedId)
            ->assertOk()
            ->assertViewIs('admin_layui::withdrawals.detail');

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/' . $blockedId)
            ->assertNotFound();
    }

    public function test_created_scope_matches_list_and_single_record_visibility(): void
    {
        $admin = $this->seedCreatedScopeAdmin(993205, 993205);
        $userId = 993206;
        $ownedId = $this->seedWithdraw(
            $userId,
            'WITHDRAW-DETAIL-CREATED-OWN',
            0,
            '',
            (string) $admin->id
        );
        $otherId = $this->seedWithdraw(
            $userId,
            'WITHDRAW-DETAIL-CREATED-OTHER',
            0,
            '',
            'another-admin'
        );

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/withdrawApplySearch', ['userId' => $userId])
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.record_id', $ownedId);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/' . $ownedId)
            ->assertOk()
            ->assertViewIs('admin_layui::withdrawals.detail');

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/amount/orderId_detail/' . $otherId)
            ->assertNotFound();
    }

    public function test_detail_blade_and_styles_use_the_scoped_visual_c_contract(): void
    {
        $bladePath = resource_path('admin/layui/withdrawals/detail.blade.php');
        $this->assertFileExists($bladePath);
        $blade = file_get_contents($bladePath) ?: '';
        $css = file_get_contents(public_path('css/layui/visual-c.css')) ?: '';

        $this->assertStringContainsString("@extends('admin_layui::layouts.app')", $blade);
        $this->assertStringContainsString('data-withdraw-detail-page="1"', $blade);
        $this->assertStringContainsString('<dl class="withdraw-detail-facts">', $blade);
        $this->assertStringContainsString('data-lucide="arrow-left"', $blade);
        $this->assertStringNotContainsString('{!!', $blade);

        $this->assertStringContainsString(
            'body[data-ui-family="layui"][data-visual-direction="c"] .withdraw-detail-page',
            $css
        );
        $this->assertStringContainsString('.withdraw-detail-status[data-status="3"]', $css);
        $this->assertStringContainsString('@media (max-width: 768px)', $css);
        $this->assertStringContainsString('@media (max-width: 480px)', $css);
    }

    private function seedWithdraw(
        int $userId,
        string $localOrderNo,
        int $status,
        string $reason,
        string $createdBy = 'detail-test'
    ): int
    {
        $now = time();

        return (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'withdraw-detail-' . $userId,
            'mt4_ticket' => 'MT4-DETAIL-' . $userId,
            'apply_amount' => '125.00',
            'actual_amount' => '120.00',
            'fee' => '5.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '35.00',
            'bank_no' => '62220000' . $userId,
            'bank_name' => 'Detail Bank',
            'bank_addr' => 'Shanghai Branch',
            'status' => $status,
            'local_order_no' => $localOrderNo,
            'third_order_no' => 'THIRD-' . $userId,
            'reject_reason' => $reason,
            'mt4_return_status' => '',
            'idempotency_key' => $localOrderNo,
            'funding_status' => $status === 2 ? 'completed' : 'debited',
            'funding_payload_hash' => hash('sha256', $localOrderNo),
            'created_by' => $createdBy,
            'updated_by' => 'detail-reviewer',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedScopedAdmin(int $adminId, int $roleId, array $userIds): Admin
    {
        $now = time();
        DB::table('roles')->updateOrInsert(['id' => $roleId], [
            'name' => 'legacy-withdraw-detail-' . $roleId,
            'guard_type' => 'admin',
            'description' => 'Legacy withdrawal detail scope',
            'permissions' => json_encode([]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->updateOrInsert(['role_id' => $roleId], [
            'scope_type' => 'custom_users',
            'agent_ids' => null,
            'user_ids' => json_encode($userIds),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $this->permissionIdForRoute('admin_api_withdrawList'),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->updateOrInsert(['id' => $adminId], [
            'role_id' => (string) $roleId,
            'email' => 'legacy-withdraw-detail-' . $adminId . '@example.test',
            'username' => 'legacy_withdraw_detail_' . $adminId,
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return Admin::query()->findOrFail($adminId);
    }

    private function seedCreatedScopeAdmin(int $adminId, int $roleId): Admin
    {
        $admin = $this->seedScopedAdmin($adminId, $roleId, []);

        DB::table('role_data_scopes')->where('role_id', $roleId)->update([
            'scope_type' => 'created',
            'agent_ids' => null,
            'user_ids' => null,
            'updated_at' => time(),
        ]);

        return $admin->fresh('role');
    }

    private function permissionIdForRoute(string $apiRoute): int
    {
        $permission = DB::table('permissions')
            ->where('guard_type', 'admin')
            ->where('api_route', $apiRoute)
            ->orderBy('id')
            ->first();

        if ($permission) {
            DB::table('permissions')->where('id', $permission->id)->update([
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

            return (int) $permission->id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'parent_id' => 0,
            'name' => $apiRoute,
            'slug' => 'test_' . md5($apiRoute),
            'api_route' => $apiRoute,
            'route' => '',
            'icon' => '',
            'type' => 3,
            'guard_type' => 'admin',
            'sort' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
}
