<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:31
 */

/**
 * AdminLegacyWithdrawPermissionClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台出金权限闭环：会话旧 order_status 按动作匹配权限、OTC 入口要求精确权限、标准与 OTC 单条入口的数据范围一致。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLegacyWithdrawPermissionClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @dataProvider withdrawPermissionProvider
     */
    public function test_session_legacy_order_status_requires_the_permission_matching_its_action(
        int $allowedStatus,
        string $allowedPermissionRoute,
        int $adminId
    ): void {
        $admin = $this->seedAdminWithOnlyPermission($adminId, $allowedPermissionRoute);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', $this->payloadForStatus($allowedStatus))
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        foreach (array_diff([1, 2, 3], [$allowedStatus]) as $blockedStatus) {
            $this->actingAs($admin, 'admin')
                ->postJson('/index/admin/amount/order_status', $this->payloadForStatus($blockedStatus))
                ->assertForbidden()
                ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        }
    }

    public static function withdrawPermissionProvider(): array
    {
        return [
            'processing action' => [1, 'admin_api_withdrawProcess', 992101],
            'complete action' => [2, 'admin_api_withdrawComplete', 992102],
            'reject action' => [3, 'admin_api_withdrawReject', 992103],
        ];
    }

    public function test_legacy_otc_entries_require_their_exact_permissions(): void
    {
        $listOnly = $this->seedAdminWithOnlyPermission(992104, 'admin_api_withdrawList');
        $this->actingAs($listOnly, 'admin')
            ->postJson('/index/admin/amount/generate_OTCorder', [
                'orderId' => 2147483104,
                'userId' => 992104,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $processOnly = $this->seedAdminWithOnlyPermission(992105, 'admin_api_withdrawProcess');
        $this->actingAs($processOnly, 'admin')
            ->postJson('/index/admin/amount/generate_OTCorder', [
                'orderId' => 2147483105,
                'userId' => 992105,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->actingAs($processOnly, 'admin')
            ->postJson('/index/admin/amount/order_status_OTC', [
                'orderId' => 2147483105,
                'orderStatus' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $completeOnly = $this->seedAdminWithOnlyPermission(992106, 'admin_api_withdrawComplete');
        $this->actingAs($completeOnly, 'admin')
            ->postJson('/index/admin/amount/order_status_OTC', [
                'orderId' => 2147483106,
                'orderStatus' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
    }

    public function test_created_scope_is_consistent_for_standard_and_otc_single_record_entries(): void
    {
        $admin = $this->seedAdminWithPermissions(
            992107,
            ['admin_api_withdrawList', 'admin_api_withdrawProcess', 'admin_api_withdrawComplete'],
            'created'
        );
        $ownedDetailId = $this->seedWithdraw(992116, 'WITHDRAW-CREATED-DETAIL-OWN', 0, (string) $admin->id);
        $otherDetailId = $this->seedWithdraw(992116, 'WITHDRAW-CREATED-DETAIL-OTHER', 0, 'other-admin');
        $ownedGenerateId = $this->seedWithdraw(992117, 'WITHDRAW-CREATED-GENERATE-OWN', 0, (string) $admin->id);
        $otherGenerateId = $this->seedWithdraw(992117, 'WITHDRAW-CREATED-GENERATE-OTHER', 0, 'other-admin');
        $ownedOtcId = $this->seedWithdraw(992118, 'WITHDRAW-CREATED-OTC-OWN', 1, (string) $admin->id);
        $otherOtcId = $this->seedWithdraw(992118, 'WITHDRAW-CREATED-OTC-OTHER', 1, 'other-admin');
        $ownedProcessId = $this->seedWithdraw(992119, 'WITHDRAW-CREATED-PROCESS-OWN', 0, (string) $admin->id);
        $otherProcessId = $this->seedWithdraw(992119, 'WITHDRAW-CREATED-PROCESS-OTHER', 0, 'other-admin');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/OTCwithdrawOrderIdDetail', [
                'recordId' => $ownedDetailId,
                'userId' => 992116,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR);
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/OTCwithdrawOrderIdDetail', [
                'recordId' => $otherDetailId,
                'userId' => 992116,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/generate_OTCorder', [
                'orderId' => $ownedGenerateId,
                'userId' => 992117,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR);
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/generate_OTCorder', [
                'orderId' => $otherGenerateId,
                'userId' => 992117,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status_OTC', [
                'orderId' => $ownedOtcId,
                'orderStatus' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::THIRD_PARTY_ERROR);
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status_OTC', [
                'orderId' => $otherOtcId,
                'orderStatus' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', [
                'orderId' => $ownedProcessId,
                'orderStatus' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/amount/order_status', [
                'orderId' => $otherProcessId,
                'orderStatus' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(1, (int) DB::table('withdraw_records')->where('id', $ownedProcessId)->value('status'));
        foreach ([$ownedDetailId, $otherDetailId, $otherGenerateId, $ownedOtcId, $otherOtcId, $otherProcessId] as $unchangedId) {
            $expectedStatus = in_array($unchangedId, [$ownedOtcId, $otherOtcId], true) ? 1 : 0;
            $this->assertSame($expectedStatus, (int) DB::table('withdraw_records')->where('id', $unchangedId)->value('status'));
            $this->assertSame(0, DB::table('withdraw_settlement_outbox')->where('withdraw_record_id', $unchangedId)->count());
        }
    }

    private function payloadForStatus(int $status): array
    {
        return [
            'orderId' => 2147483000 + $status,
            'orderStatus' => $status,
            'orderRemark' => 'permission boundary',
        ];
    }

    private function seedAdminWithOnlyPermission(int $adminId, string $apiRoute): Admin
    {
        return $this->seedAdminWithPermissions($adminId, [$apiRoute]);
    }

    private function seedAdminWithPermissions(int $adminId, array $apiRoutes, string $scopeType = ''): Admin
    {
        $roleId = $adminId;
        $now = time();

        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            [
                'name' => 'legacy-withdraw-permission-' . $adminId,
                'guard_type' => 'admin',
                'description' => 'Legacy withdraw permission boundary test role',
                'permissions' => json_encode([]),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        foreach ($apiRoutes as $apiRoute) {
            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $this->permissionIdForRoute($apiRoute),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
        if ($scopeType !== '') {
            DB::table('role_data_scopes')->updateOrInsert(['role_id' => $roleId], [
                'scope_type' => $scopeType,
                'agent_ids' => null,
                'user_ids' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'username' => 'legacy_withdraw_permission_' . $adminId,
                'email' => 'legacy-withdraw-permission-' . $adminId . '@example.test',
                'password' => Hash::make('password'),
                'role_id' => (string) $roleId,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function seedWithdraw(int $userId, string $localOrderNo, int $status, string $createdBy): int
    {
        $now = time();

        return (int) DB::table('withdraw_records')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'withdraw-permission-' . $userId,
            'mt4_ticket' => 'MT4-' . $localOrderNo,
            'apply_amount' => '100.00',
            'actual_amount' => '95.00',
            'fee' => '5.00',
            'exchange_rate' => '7.00000000',
            'rmb_fee' => '35.00',
            'bank_no' => '62220000' . $userId,
            'bank_name' => 'Permission Bank',
            'bank_addr' => 'Shanghai',
            'status' => $status,
            'local_order_no' => $localOrderNo,
            'third_order_no' => '',
            'reject_reason' => '',
            'mt4_return_status' => '',
            'idempotency_key' => $localOrderNo,
            'funding_status' => 'debited',
            'funding_payload_hash' => hash('sha256', $localOrderNo),
            'created_by' => $createdBy,
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
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
