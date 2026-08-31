<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 后台批量导入卡在处理中（stuck processing）恢复闭包测试。
 *
 * 文件功能：
 * - 验证超过超时阈值的处理中（is_synced=3）记录可被重试接口重置为待同步（is_synced=0）。
 * - 验证新鲜（未超时）的处理中记录不允许重试，防止并发重复处理。
 * - 验证同步接口可回收超时的处理中记录并完成 MT4 同步。
 *
 * 适用场景：
 * - 后台批量金额导入模块处理中超时恢复与并发保护的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/retryDepositImport/{recordId}（记录 updated_at 距今超过阈值）
 *
 * 方法功能：
 * - test_stale_processing_import_can_be_retried_to_pending：超时处理中记录重试成功，断言重置为待同步。
 * - test_fresh_processing_import_cannot_be_retried：未超时处理中记录重试被拒，断言返回 OPERATION_NOT_ALLOWED。
 * - test_sync_reclaims_stale_processing_and_completes_successfully：同步接口回收超时记录并完成网关同步。
 *
 * 返回值：
 * - 重试成功返回 code=SUCCESS，被拒返回 code=OPERATION_NOT_ALLOWED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若新鲜处理中记录被重置或同步未完成，测试断言失败。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\DepositSettlementGateway;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBatchAmountImportStuckProcessingRecoveryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 超时处理中记录重试：断言重置为待同步并清空失败原因。
     *
     * @return void
     */
    public function test_stale_processing_import_can_be_retried_to_pending(): void
    {
        $admin = $this->ensureAdmin(984801);
        $userId = 984811;
        $this->ensureImportUser($userId, 'Stuck Processing Retry User');
        $staleAt = time() - 600;
        $recordId = $this->createImport('deposit_imports', $userId, 'STUCK-RETRY-1', 3, 'processing', $staleAt);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/retryDepositImport/' . $recordId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.is_synced', 0)
            ->assertJsonPath('data.fail_reason', '');

        $this->assertDatabaseHas('deposit_imports', [
            'id' => $recordId,
            'is_synced' => 0,
            'fail_reason' => '',
        ]);
    }

    /**
     * 未超时处理中记录重试被拒：断言返回 OPERATION_NOT_ALLOWED 且状态保持处理中。
     *
     * @return void
     */
    public function test_fresh_processing_import_cannot_be_retried(): void
    {
        $admin = $this->ensureAdmin(984802);
        $userId = 984812;
        $this->ensureImportUser($userId, 'Fresh Processing Guard User');
        $recordId = $this->createImport('deposit_imports', $userId, 'STUCK-FRESH-1', 3, 'processing', time());

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/retryDepositImport/' . $recordId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertDatabaseHas('deposit_imports', [
            'id' => $recordId,
            'is_synced' => 3,
            'fail_reason' => 'processing',
        ]);
    }

    /**
     * 同步接口回收超时处理中记录：断言调用网关并完成落库。
     *
     * @return void
     */
    public function test_sync_reclaims_stale_processing_and_completes_successfully(): void
    {
        $admin = $this->ensureAdmin(984803);
        $userId = 984813;
        $this->ensureImportUser($userId, 'Stuck Sync Reclaim User');
        $staleAt = time() - 600;
        $recordId = $this->createImport('deposit_imports', $userId, 'STUCK-SYNC-1', 3, 'processing', $staleAt);

        $calls = [];
        $this->app->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 结算网关替身的调用捕获表。deposit() 记下 [userId, amount, comment]，
             * 断言卡死订单恢复重放时不会产生重复入账。
             * @var array<int, array{userId: int, amount: string, comment: string}>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = compact('userId', 'amount', 'comment');

                return DepositSettlementResult::settled('99001');
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/syncDepositImport/' . $recordId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.is_synced', 1)
            ->assertJsonPath('data.mt4_order_id', 99001);

        $this->assertCount(1, $calls);
        $this->assertDatabaseHas('deposit_imports', [
            'id' => $recordId,
            'is_synced' => 1,
            'fail_reason' => '',
            'mt4_order_id' => 99001,
        ]);
    }

    private function ensureAdmin(int $adminId): Admin
    {
        $now = time();
        $roleId = 984800 + ($adminId % 10);
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            [
                'name' => 'batch_stuck_recovery_' . $roleId,
                'guard_type' => 'admin',
                'description' => 'Batch import stuck recovery test role',
                'permissions' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('role_data_scopes')->updateOrInsert(
            ['role_id' => $roleId],
            [
                'scope_type' => 'all',
                'agent_ids' => null,
                'user_ids' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('admins')->updateOrInsert(
            ['id' => $adminId],
            [
                'role_id' => (string) $roleId,
                'username' => 'admin-import-stuck-' . $adminId,
                'email' => 'admin-import-stuck-' . $adminId . '@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function ensureImportUser(int $userId, string $userName): void
    {
        $now = time();
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
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

    private function createImport(
        string $table,
        int $userId,
        string $batchNo,
        int $syncStatus,
        string $failReason,
        int $updatedAt
    ): int {
        return (int) DB::table($table)->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Stuck Processing User',
            'amount' => 55.00,
            'remarks' => 'stuck processing recovery',
            'mt4_order_id' => 0,
            'batch_no' => $batchNo,
            'is_synced' => $syncStatus,
            'fail_reason' => $failReason,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
            'deleted_at' => null,
        ]);
    }
}
