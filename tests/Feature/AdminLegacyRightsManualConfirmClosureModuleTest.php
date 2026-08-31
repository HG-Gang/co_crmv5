<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:44
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
 * 旧后台权益手动确认兼容入口闭环测试。
 *
 * 文件功能：
 * - 验证旧 `manual_confirm_options` 能转发现代 `manualConfirmRightsSettlement`。
 * - 验证旧字段 `manual_reason` 会归一化为 `manual_confirm_reason` 并写入审计备注。
 * - 验证缺少明确 `rights_settlements.id` 时返回参数失败，避免把 user_id 或金额误当结算主键。
 */
class AdminLegacyRightsManualConfirmClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 旧手动确认入口应确认指定待处理权益结算记录。
     *
     * @return void
     */
    public function test_legacy_manual_confirm_options_confirms_pending_settlement_with_legacy_reason(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 982871;
        $this->ensureUser($userId);
        $settlementId = $this->createPendingSettlement($userId, 'pending legacy manual confirm');
        $otherSettlementId = $this->createPendingSettlement($userId, 'other settlement must stay pending');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/manual_confirm_options', [
                'settlement_id' => $settlementId,
                'manual_uid' => $userId,
                'manual_sumdata' => '123.45670000',
                'manual_status' => 1,
                'manual_reason' => 'legacy manual rights settlement',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('rights_settlements', [
            'id' => $settlementId,
            'user_id' => $userId,
            'status' => 1,
            'remark' => 'legacy manual rights settlement',
        ]);
        $this->assertDatabaseHas('rights_settlements', [
            'id' => $otherSettlementId,
            'user_id' => $userId,
            'status' => 0,
            'remark' => 'other settlement must stay pending',
        ]);
    }

    /**
     * 旧手动确认缺少结算主键时必须失败。
     *
     * @return void
     */
    public function test_legacy_manual_confirm_options_rejects_missing_settlement_id_without_writing(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 982872;
        $this->ensureUser($userId);
        $settlementId = $this->createPendingSettlement($userId, 'missing id should stay pending');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/manual_confirm_options', [
                'manual_uid' => $userId,
                'manual_sumdata' => '123.45670000',
                'manual_status' => 1,
                'manual_reason' => 'missing settlement id',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('rights_settlements', [
            'id' => $settlementId,
            'user_id' => $userId,
            'status' => 0,
            'remark' => 'missing id should stay pending',
        ]);
    }

    /**
     * 旧手动确认状态不是 1 时不能推进结算状态。
     *
     * @return void
     */
    public function test_legacy_manual_confirm_options_rejects_non_confirm_status_without_writing(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 982873;
        $this->ensureUser($userId);
        $settlementId = $this->createPendingSettlement($userId, 'invalid status should stay pending');

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            LegacyAdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/index/admin/amount/manual_confirm_options', [
                'settlement_id' => $settlementId,
                'manual_uid' => $userId,
                'manual_sumdata' => '123.45670000',
                'manual_status' => 2,
                'manual_reason' => 'invalid manual status',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('rights_settlements', [
            'id' => $settlementId,
            'user_id' => $userId,
            'status' => 0,
            'remark' => 'invalid status should stay pending',
        ]);
    }

    /**
     * 创建测试后台管理员。
     *
     * @return Admin 可绑定 admin guard 的后台管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-legacy-rights-manual-super',
                'email' => 'admin-legacy-rights-manual-super@example.test',
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
     * 创建权益结算归属用户。
     *
     * @param int $userId 业务用户 ID，写入 `user_infos.user_id`。
     * @return void
     */
    private function ensureUser(int $userId): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => 'Legacy Rights Manual User ' . $userId,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 创建待处理权益结算记录。
     *
     * @param int $userId 结算记录归属用户 ID。
     * @param string $remark 初始备注，用于断言未被误改。
     * @return int 新增 `rights_settlements.id`。
     */
    private function createPendingSettlement(int $userId, string $remark): int
    {
        $now = time();

        return (int) DB::table('rights_settlements')->insertGetId([
            'user_id' => $userId,
            'amount' => 123.4567,
            'status' => 0,
            'remark' => $remark,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
