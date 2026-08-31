<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 后台批量信用导入 MT4 同步闭包测试。
 *
 * 文件功能：
 * - 验证信用导入同步接口（syncCreditImport）注册了权限中间件，且前端按钮、权限迁移与 CreditSettlementGateway 契约已接线。
 * - 验证待同步信用记录同步成功后写入网关并落库 mt4_order_id、is_synced=1。
 * - 验证网关返回可重试/被拒绝结果时按失败关闭处理：不伪造成功，is_synced 置 0/2 并记录失败原因。
 * - 验证非待同步记录不允许重复同步，验证同步前按管理员数据范围过滤记录。
 *
 * 适用场景：
 * - 后台批量信用导入模块的 MT4 同步、失败关闭与数据范围回归测试。
 *
 * 入参例子：
 * - POST /api/admin/syncCreditImport/{recordId}
 *
 * 方法功能：
 * - test_credit_import_sync_route_permission_and_frontend_wiring_are_declared：校验路由、权限、blade、layui 脚本与迁移声明。
 * - test_pending_credit_import_syncs_successfully_to_mt4_gateway：待同步记录同步成功，断言网关参数与落库状态。
 * - test_retryable_credit_sync_keeps_import_pending_without_success_ticket：可重试失败保留待同步且不生成成功单号。
 * - test_unknown_or_rejected_credit_sync_marks_import_failed_without_fake_success：拒绝结果标记失败且不伪造成功。
 * - test_credit_sync_rejects_non_pending_records_without_calling_gateway：非待同步记录被拒且不调用网关。
 * - test_credit_sync_respects_admin_data_scope_before_calling_gateway：数据范围外记录返回 DATA_NOT_FOUND 且不调用网关。
 *
 * 返回值：
 * - 同步成功返回 code=SUCCESS，网关失败返回 code=MT4_SYNC_FAILED，非待同步返回 code=OPERATION_NOT_ALLOWED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若同步未落库、失败时伪造成功或越权同步范围外记录，测试断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Payment\DepositSettlementResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\ReadsAggregatedLayuiScripts;
use Tests\TestCase;

class AdminBatchCreditImportMt4SyncClosureModuleTest extends TestCase
{
    use ReadsAggregatedLayuiScripts;

    /**
     * 校验信用同步命名路由、权限中间件、前端按钮与迁移声明。
     *
     * @return void
     */
    public function test_credit_import_sync_route_permission_and_frontend_wiring_are_declared(): void
    {
        $this->assertTrue(Route::has('admin_api_syncCreditImport'), 'admin_api_syncCreditImport API 路由未注册。');
        $this->assertContains('check.permission:admin', Route::getRoutes()->getByName('admin_api_syncCreditImport')->gatherMiddleware());

        $blade = file_get_contents(resource_path('admin/layui/credit-imports/index.blade.php')) ?: '';
        $this->assertStringContainsString('lay-event="syncCreditImport"', $blade);
        $this->assertStringContainsString('data-permission="admin_batch_credit_import_sync"', $blade);
        $this->assertStringContainsString('/api/admin/syncCreditImport/', $this->adminLayuiScript('credit-imports/index.js'));

        $migration = file_get_contents(database_path('migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php')) ?: '';
        $this->assertStringContainsString('admin_batch_credit_import_sync', $migration);
        $this->assertStringContainsString('admin_api_syncCreditImport', $migration);
    }

    /**
     * 待同步信用记录同步成功：断言网关调用参数与落库状态（mt4_order_id、is_synced）。
     *
     * @return void
     */
    public function test_pending_credit_import_syncs_successfully_to_mt4_gateway(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');

        $admin = $this->ensureAdmin(982721);
        $userId = 982731;
        $this->ensureImportUser($userId, 'Batch Credit Mt4 Sync User');
        $recordId = $this->createCreditImportRecord($userId, 'MT4-SYNC-CREDIT-SUCCESS', 0, 'credit sync remark');
        $calls = [];
        $this->bindCreditGateway(DepositSettlementResult::settled('890001'), $calls);

        $response = $this->postAsAdmin($admin, '/api/admin/syncCreditImport/' . $recordId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.is_synced', 1)
            ->assertJsonPath('data.mt4_order_id', 890001)
            ->assertJsonPath('data.fail_reason', '');
        $this->assertSame([[$userId, '100.25', 'credit sync remark', 0]], $calls);
        $this->assertDatabaseHas('credit_imports', [
            'id' => $recordId,
            'is_synced' => 1,
            'mt4_order_id' => 890001,
            'fail_reason' => '',
            'updated_by' => $admin->id,
        ]);
    }

    /**
     * 网关返回可重试失败：断言记录保持待同步且不生成成功单号。
     *
     * @return void
     */
    public function test_retryable_credit_sync_keeps_import_pending_without_success_ticket(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');

        $admin = $this->ensureAdmin(982722);
        $userId = 982732;
        $this->ensureImportUser($userId, 'Batch Credit Retryable Sync User');
        $recordId = $this->createCreditImportRecord($userId, 'MT4-SYNC-CREDIT-RETRYABLE', 0, 'retryable credit sync');
        $calls = [];
        $this->bindCreditGateway(DepositSettlementResult::retryableNotSent('connection_failed'), $calls);

        $response = $this->postAsAdmin($admin, '/api/admin/syncCreditImport/' . $recordId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED)
            ->assertJsonPath('data.is_synced', 0)
            ->assertJsonPath('data.mt4_order_id', 0)
            ->assertJsonPath('data.fail_reason', 'connection_failed');
        $this->assertCount(1, $calls);
        $this->assertDatabaseHas('credit_imports', [
            'id' => $recordId,
            'is_synced' => 0,
            'mt4_order_id' => 0,
            'fail_reason' => 'connection_failed',
        ]);
    }

    /**
     * 网关返回拒绝/未知结果：断言记录标记失败且不伪造成功。
     *
     * @return void
     */
    public function test_unknown_or_rejected_credit_sync_marks_import_failed_without_fake_success(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');

        $admin = $this->ensureAdmin(982723);
        $userId = 982733;
        $this->ensureImportUser($userId, 'Batch Credit Rejected Sync User');
        $recordId = $this->createCreditImportRecord($userId, 'MT4-SYNC-CREDIT-REJECTED', 0, 'rejected credit sync');
        $calls = [];
        $this->bindCreditGateway(DepositSettlementResult::rejected('provider_rejected'), $calls);

        $response = $this->postAsAdmin($admin, '/api/admin/syncCreditImport/' . $recordId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED)
            ->assertJsonPath('data.is_synced', 2)
            ->assertJsonPath('data.mt4_order_id', 0)
            ->assertJsonPath('data.fail_reason', 'provider_rejected');
        $this->assertCount(1, $calls);
        $this->assertDatabaseHas('credit_imports', [
            'id' => $recordId,
            'is_synced' => 2,
            'mt4_order_id' => 0,
            'fail_reason' => 'provider_rejected',
        ]);
    }

    /**
     * 非待同步信用记录同步被拒：断言返回 OPERATION_NOT_ALLOWED 且网关未被调用。
     *
     * @return void
     */
    public function test_credit_sync_rejects_non_pending_records_without_calling_gateway(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');

        $admin = $this->ensureAdmin(982724);
        $userId = 982734;
        $this->ensureImportUser($userId, 'Batch Credit Non Pending Sync User');
        $calls = [];
        $this->bindCreditGateway(DepositSettlementResult::settled('890002'), $calls);

        foreach ([1, 2] as $syncStatus) {
            $recordId = $this->createCreditImportRecord($userId, 'MT4-SYNC-CREDIT-NON-PENDING-' . $syncStatus, $syncStatus, 'keep credit status');

            $response = $this->postAsAdmin($admin, '/api/admin/syncCreditImport/' . $recordId);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);
            $this->assertDatabaseHas('credit_imports', [
                'id' => $recordId,
                'is_synced' => $syncStatus,
                'mt4_order_id' => 0,
                'fail_reason' => 'keep credit status',
            ]);
        }

        $this->assertSame([], $calls);
    }

    /**
     * 数据范围外信用记录同步被拒：断言返回 DATA_NOT_FOUND 且网关未被调用。
     *
     * @return void
     */
    public function test_credit_sync_respects_admin_data_scope_before_calling_gateway(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');

        $allowedUserId = 982735;
        $blockedUserId = 982736;
        $admin = $this->ensureScopedAdmin(982725, 982725, [$allowedUserId]);
        $this->ensureImportUser($allowedUserId, 'Allowed Credit Sync User');
        $this->ensureImportUser($blockedUserId, 'Blocked Credit Sync User');
        $recordId = $this->createCreditImportRecord($blockedUserId, 'MT4-SYNC-CREDIT-SCOPE-BLOCKED', 0, 'blocked credit sync');
        $calls = [];
        $this->bindCreditGateway(DepositSettlementResult::settled('890003'), $calls);

        $response = $this->postAsAdmin($admin, '/api/admin/syncCreditImport/' . $recordId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
        $this->assertSame([], $calls);
        $this->assertDatabaseHas('credit_imports', [
            'id' => $recordId,
            'is_synced' => 0,
            'mt4_order_id' => 0,
            'fail_reason' => '',
        ]);
    }

    private function postAsAdmin(Admin $admin, string $uri)
    {
        return $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->post($uri);
    }

    private function ensureAdmin(int $adminId): Admin
    {
        $roleId = $adminId;
        $now = time();
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            [
                'name' => 'credit_sync_all_' . $roleId,
                'guard_type' => 'admin',
                'description' => 'Batch credit import sync all-scope test role',
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
                'mobile' => null,
                'email' => 'credit-sync-' . $adminId . '@example.test',
                'username' => 'credit_sync_' . $adminId,
                'password' => bcrypt('password'),
                'login_count' => 0,
                'last_login_ip' => null,
                'last_login_at' => null,
                'last_login_address' => null,
                'status' => 1,
                'jwt_token_id' => null,
                'created_by' => 'test',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail($adminId);
    }

    private function ensureScopedAdmin(int $adminId, int $roleId, array $userIds): Admin
    {
        $now = time();
        $admin = $this->ensureAdmin($adminId);
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            [
                'name' => 'credit_sync_scope_' . $roleId,
                'guard_type' => 'admin',
                'description' => 'Batch credit import sync data scope test role',
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
                'scope_type' => 'custom_users',
                'agent_ids' => null,
                'user_ids' => json_encode($userIds),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $admin->role_id = (string) $roleId;
        $admin->saveOrFail();

        return $admin->fresh();
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

    private function createCreditImportRecord(int $userId, string $batchNo, int $syncStatus, string $remarks): int
    {
        DB::table('credit_imports')->where('batch_no', $batchNo)->delete();
        $now = time();

        return (int) DB::table('credit_imports')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'Batch Credit Mt4 Sync User',
            'credit_type' => 3,
            'amount' => '100.25',
            'batch_no' => $batchNo,
            'mt4_order_id' => 0,
            'is_synced' => $syncStatus,
            'fail_reason' => $syncStatus === 0 ? '' : $remarks,
            'remarks' => $remarks,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function bindCreditGateway(DepositSettlementResult $result, array &$calls): void
    {
        app()->instance('App\Contracts\CreditSettlementGateway', new class($result, $calls) implements \App\Contracts\CreditSettlementGateway {
            /**
             * 赠点结算替身预设的结果。驱动批量赠点导入后 MT4 入账的成功/失败分支。
             * @var DepositSettlementResult
             */
            private $result;
            /**
             * 引用传入的调用记录。credit() 每次调用记下入参，断言入账次数与金额。
             * @var array<int, array{0: int, 1: string, 2: string}>
             */
            private $calls;

            public function __construct(DepositSettlementResult $result, array &$calls)
            {
                $this->result = $result;
                $this->calls = &$calls;
            }

            public function creditIn(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = [$userId, $amount, $comment, DB::transactionLevel()];

                return $this->result;
            }
        });
    }
}
