<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:24
 */

/**
 * AdminUserUpdateMt4SyncClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新 MT4 同步边界：交易资料更新先同步 MT4 失败关闭并写审计、基础资料更新不触发 MT4 同步。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台普通用户编辑 MT4 同步闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 cust_save_info 在修改用户交易组和杠杆时，必须先拿到 MT4 明确成功响应，再写本地用户资料。
 * - 新项目 admin_api_updateUser 需要延续该边界，避免本地 user_infos 与真实 MT4 账户状态分叉。
 * - 基础资料编辑不应触发交易组同步，降低外部 MT4 调用次数和失败面。
 */
class AdminUserUpdateMt4SyncClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_trading_profile_update_fails_closed_when_mt4_sync_fails(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726701;
        $this->seedUser($userId, 'Before MT4 Fail', '18826701001', 'OLD-GROUP', 100);

        $calls = [];
        $this->bindTradingProfileMt4($calls, false);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'user_id' => $userId,
                'user_name' => 'After MT4 Fail',
                'phone' => '18826701999',
                'mt4_group' => 'NEW-GROUP',
                'leverage' => 200,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);

        $this->assertSame([
            [
                'user_id' => $userId,
                'group' => 'NEW-GROUP',
                'leverage' => 200,
                'before_group' => 'OLD-GROUP',
                'before_leverage' => 100,
            ],
        ], $calls);

        $record = DB::table('user_infos')->where('user_id', $userId)->first();
        $this->assertSame('Before MT4 Fail', (string) $record->user_name);
        $this->assertSame('18826701001', (string) $record->phone);
        $this->assertSame('OLD-GROUP', (string) $record->mt4_group);
        $this->assertSame(100, (int) $record->leverage);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_trading_profile_update_syncs_mt4_before_local_write_and_logs_audit_record(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726702;
        $this->seedUser($userId, 'Before MT4 Success', '18826702001', 'OLD-SUCCESS', 100);

        $calls = [];
        $this->bindTradingProfileMt4($calls, true);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'user_id' => $userId,
                'user_name' => 'After MT4 Success',
                'phone' => '18826702999',
                'mt4_group' => 'NEW-SUCCESS',
                'leverage' => 200,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([
            [
                'user_id' => $userId,
                'group' => 'NEW-SUCCESS',
                'leverage' => 200,
                'before_group' => 'OLD-SUCCESS',
                'before_leverage' => 100,
            ],
        ], $calls);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After MT4 Success',
            'phone' => '18826702999',
            'mt4_group' => 'NEW-SUCCESS',
            'leverage' => 200,
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $userId)
            ->first();

        $this->assertNotNull($log, 'updateUser must create an operation_logs audit record.');
        $this->assertSame($admin->id, (int) $log->admin_id);
        $this->assertSame($userId, (int) $log->target_user_id);
        $this->assertStringContainsString('mt4_group:OLD-SUCCESS->NEW-SUCCESS', (string) $log->content);
        $this->assertStringContainsString('leverage:100->200', (string) $log->content);
    }

    public function test_basic_profile_update_does_not_call_mt4_trading_profile_sync(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726703;
        $this->seedUser($userId, 'Before Basic Update', '18826703001', 'UNCHANGED-GROUP', 100);

        $calls = [];
        $this->bindTradingProfileMt4($calls, true);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'user_id' => $userId,
                'user_name' => 'After Basic Update',
                'phone' => '18826703999',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Basic Update',
            'phone' => '18826703999',
            'mt4_group' => 'UNCHANGED-GROUP',
            'leverage' => 100,
        ]);
        $this->assertDatabaseHas('operation_logs', [
            'target_user_id' => $userId,
            'order_no' => 'user_update:' . $userId,
        ]);
    }

    public function test_final_checklist_records_admin_user_update_mt4_sync_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('/api/admin/updateUser', $checklist);
        $this->assertStringContainsString('Mt4ManagerService::updateUserTradingProfile', $checklist);
        $this->assertStringContainsString('user_infos.mt4_group', $checklist);
        $this->assertStringContainsString('user_infos.leverage', $checklist);
        $this->assertStringContainsString('AdminUserUpdateMt4SyncClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-mt4-super',
                'email' => 'admin-user-update-mt4-super@example.test',
                'password' => bcrypt('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function seedUser(int $userId, string $userName, string $phone, string $mt4Group, int $leverage): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-mt4-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => $phone,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'mt4_group' => $mt4Group,
            'leverage' => $leverage,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function bindTradingProfileMt4(array &$calls, bool $ok): void
    {
        $this->app->instance(Mt4ManagerService::class, new class($calls, $ok) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录 updateUserTradingProfile 的入参与改前 MT4 组/杠杆，
             * 断言同步指令只在真实变更时发出。
             * @var array<int, array<string, mixed>>
             */
            private $calls;
            /**
             * MT4 替身的成功开关。false 返回连接失败，验证本地更新在 MT4 失败时回滚。
             * @var bool
             */
            private $ok;

            public function __construct(array &$calls, bool $ok)
            {
                $this->calls = &$calls;
                $this->ok = $ok;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function updateUserTradingProfile($userId, $group, $leverage)
            {
                $before = DB::table('user_infos')->where('user_id', (int) $userId)->first();
                $this->calls[] = [
                    'user_id' => (int) $userId,
                    'group' => (string) $group,
                    'leverage' => (int) $leverage,
                    'before_group' => (string) $before->mt4_group,
                    'before_leverage' => (int) $before->leverage,
                ];

                return $this->ok
                    ? ['status' => 'ok', 'err' => '0', 'message' => 'updated', 'data' => []]
                    : ['status' => 'error', 'error_code' => 'connection_failed', 'message' => 'fail', 'data' => []];
            }
        });
    }
}
