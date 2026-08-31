<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:11
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Models\OperationLog;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * 后台销户审核完整生命周期测试。
 *
 * 文件功能：
 * - 验证通过审核后登录、MT4、只读和出金能力全部关闭。
 * - 验证拒绝审核必须先解锁 MT4，再恢复本地能力并写入拒绝状态。
 * - 验证远端失败或本地事务失败时保持待审，并执行方向相反的远端补偿。
 *
 * 返回结果：
 * - SUCCESS 表示远端和本地状态全部闭环。
 * - MT4_SYNC_FAILED 表示远端没有明确成功，本地状态保持原值。
 * - SERVER_ERROR 表示本地事务失败，数据库回滚且已尝试重新锁定远端账号。
 */
class AdminCancelApplyLifecycleClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证拒绝成功会解锁 MT4，并恢复登录、交易和出金能力。
     */
    public function test_reject_unlocks_mt4_and_restores_local_capabilities(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 419040100;
        $applyId = $this->createUserAndApply($userId, true);
        $calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($calls) extends Mt4ManagerService {
            /** @var array<int, int> 记录实际解锁的业务用户 ID。 */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
                parent::__construct('127.0.0.1', 0, 'test', '1', 1);
            }

            /**
             * 返回明确解锁成功，并记录审核目标。
             */
            public function unlockUser($userId)
            {
                $this->calls[] = (int) $userId;

                return ['status' => 'ok', 'err' => '0'];
            }
        });

        $response = $this->asAdmin($admin)
            ->postJson('/api/admin/cancelApplyReject/' . $applyId, [
                'reason' => 'Identity review did not pass',
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([$userId], $calls);
        $this->assertDatabaseHas('cancel_applies', [
            'id' => $applyId,
            'status' => -1,
            'reject_reason' => 'Identity review did not pass',
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 0,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
        ]);
    }

    /**
     * 验证 MT4 解锁失败时审核保持待处理，本地限制不能提前恢复。
     */
    public function test_reject_fails_closed_when_mt4_unlock_fails(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 419040200;
        $applyId = $this->createUserAndApply($userId, true);
        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'test', '1', 1);
            }

            /**
             * 模拟远端连接失败，控制器必须失败关闭。
             */
            public function unlockUser($userId)
            {
                return ['status' => 'error', 'error_code' => 'connect_timeout'];
            }
        });

        $response = $this->asAdmin($admin)
            ->postJson('/api/admin/cancelApplyReject/' . $applyId, [
                'reason' => 'Remote unlock must succeed first',
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertDatabaseHas('cancel_applies', ['id' => $applyId, 'status' => 0]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 0,
            'is_mt4_readonly' => 1,
            'is_withdrawal_allowed' => 1,
        ]);
        $this->assertDatabaseMissing('operation_logs', ['order_no' => 'cancel_apply:' . $applyId]);
    }

    /**
     * 验证通过审核会强制写入禁止出金标志，兼容未经过新前台锁定的历史待审申请。
     */
    public function test_approve_closes_withdrawal_capability_before_soft_delete(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 419040300;
        $applyId = $this->createUserAndApply($userId, false);
        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'test', '1', 1);
            }

            /**
             * 模拟审核通过前的 MT4 锁号成功。
             */
            public function lockUser($userId)
            {
                return ['status' => 'ok', 'err' => '0'];
            }
        });

        $response = $this->asAdmin($admin)
            ->postJson('/api/admin/cancelApplyApprove/' . $applyId);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 0,
            'is_mt4_readonly' => 1,
            'is_withdrawal_allowed' => 1,
        ]);
        $this->assertSoftDeleted('user_infos', ['user_id' => $userId]);
    }

    /**
     * 验证 MT4 虽返回 status=ok，但业务错误码非 0 时仍按远端失败处理。
     */
    public function test_approve_fails_closed_when_mt4_err_is_nonzero(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 419040350;
        $applyId = $this->createUserAndApply($userId, false);
        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'test', '1', 1);
            }

            /**
             * 模拟传输层成功但 MT4 业务执行失败，控制器不能误判为锁号成功。
             */
            public function lockUser($userId)
            {
                return ['status' => 'ok', 'err' => '1006', 'error_code' => '1006'];
            }
        });

        $response = $this->asAdmin($admin)
            ->postJson('/api/admin/cancelApplyApprove/' . $applyId);

        $response->assertOk()->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertDatabaseHas('cancel_applies', ['id' => $applyId, 'status' => 0]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 0,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'deleted_at' => null,
        ]);
    }

    /**
     * 验证审核通过新锁定 MT4 后若本地事务失败，会解锁远端并回滚全部本地状态。
     */
    public function test_approve_unlocks_new_remote_lock_when_local_transaction_fails(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 419040375;
        $applyId = $this->createUserAndApply($userId, false);
        $manager = Mockery::mock(Mt4ManagerService::class);
        $manager->shouldReceive('lockUser')
            ->once()
            ->with($userId)
            ->andReturn(['status' => 'ok', 'err' => '0']);
        $manager->shouldReceive('unlockUser')
            ->once()
            ->with($userId)
            ->andReturn(['status' => 'ok', 'err' => '0']);
        $this->app->instance(Mt4ManagerService::class, $manager);
        OperationLog::creating(function (): void {
            throw new RuntimeException('forced cancel approve log failure');
        });

        $response = $this->asAdmin($admin)
            ->postJson('/api/admin/cancelApplyApprove/' . $applyId);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SERVER_ERROR);
        $this->assertDatabaseHas('cancel_applies', ['id' => $applyId, 'status' => 0]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'is_cancelled' => 0,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'deleted_at' => null,
        ]);
    }

    /**
     * 验证远端解锁成功但本地拒绝事务失败时重新锁号，并回滚全部本地状态。
     */
    public function test_reject_relocks_mt4_when_local_transaction_fails(): void
    {
        $admin = $this->ensureAdmin();
        $userId = 419040400;
        $applyId = $this->createUserAndApply($userId, true);
        $manager = Mockery::mock(Mt4ManagerService::class);
        $manager->shouldReceive('unlockUser')
            ->once()
            ->with($userId)
            ->andReturn(['status' => 'ok', 'err' => '0']);
        $manager->shouldReceive('lockUser')
            ->once()
            ->with($userId)
            ->andReturn(['status' => 'ok', 'err' => '0']);
        $this->app->instance(Mt4ManagerService::class, $manager);
        OperationLog::creating(function (): void {
            throw new RuntimeException('forced cancel reject log failure');
        });

        $response = $this->asAdmin($admin)
            ->postJson('/api/admin/cancelApplyReject/' . $applyId, [
                'reason' => 'Force local rollback',
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SERVER_ERROR);
        $this->assertDatabaseHas('cancel_applies', ['id' => $applyId, 'status' => 0]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 0,
            'is_mt4_readonly' => 1,
            'is_withdrawal_allowed' => 1,
        ]);
    }

    /**
     * 创建启用状态的后台审核管理员。
     */
    private function ensureAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'cancel-lifecycle-admin',
                'email' => 'cancel-lifecycle-admin@example.test',
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
     * 创建用户和待审申请；locked=true 表示前台申请阶段已完成本地能力收口。
     */
    private function createUserAndApply(int $userId, bool $locked): int
    {
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-cancel-lifecycle-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => 'admin-cancel-lifecycle-' . $userId,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => '',
            'total_funds' => 0,
            'equity' => 0,
            'is_mt4_enabled' => $locked ? 0 : 1,
            'is_mt4_readonly' => $locked ? 1 : 0,
            'is_withdrawal_allowed' => $locked ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) DB::table('cancel_applies')->insertGetId([
            'user_id' => $userId,
            'user_name' => 'admin-cancel-lifecycle-' . $userId,
            'status' => 0,
            'cancel_remark' => 'Verified cancellation application',
            'reject_reason' => '',
            'created_by' => (string) $userId,
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 构造绕过认证和权限中间件的管理员测试客户端，业务数据范围仍由超级管理员身份校验。
     */
    private function asAdmin(Admin $admin): self
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin');
    }
}
