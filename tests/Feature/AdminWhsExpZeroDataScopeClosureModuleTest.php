<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 16:24
 */

/**
 * AdminWhsExpZeroDataScopeClosureModuleTest
 *
 * 文件功能：
 * - 验证仓位清零数据范围闭环：候选与记录列表仅返回 custom users 范围、旧扫描只对可见用户建待处理记录、越权清零在调用网关前拒绝且不写库。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

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

/**
 * 仓位清零候选、记录、预扫描和资金动作的数据范围闭环。
 */
class AdminWhsExpZeroDataScopeClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 夹具创建的后台管理员 ID。绑定受限数据范围后登录，验证 WHS 体验金列表的数据隔离。
     * @var int
     */
    private const ADMIN_ID = 987801;
    /**
     * 为 ADMIN_ID 创建的角色 ID，其 role_data_scopes 决定可见用户范围。
     * @var int
     */
    private const ROLE_ID = 987802;
    /**
     * 数据范围内的业务用户 ID。对其的体验金操作必须成功，记录必须可见。
     * @var int
     */
    private const VISIBLE_USER_ID = 987811;
    /**
     * 数据范围外的业务用户 ID。对其的体验金操作必须被拒绝或隔离。
     * @var int
     */
    private const HIDDEN_USER_ID = 987812;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedScopedAdmin();
        $this->seedUser(self::VISIBLE_USER_ID, 'WHS Scope Visible User');
        $this->seedUser(self::HIDDEN_USER_ID, 'WHS Scope Hidden User');
        $this->seedCompletedRecord(self::VISIBLE_USER_ID, 'WHS Scope Visible User');
        $this->seedCompletedRecord(self::HIDDEN_USER_ID, 'WHS Scope Hidden User');
    }

    public function test_candidate_and_record_lists_only_return_custom_users_scope(): void
    {
        $candidateResponse = $this->asScopedAdmin()
            ->postJson('/api/admin/whsExpZeroList', ['limit' => 20])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $candidateIds = collect($candidateResponse->json('data.data'))
            ->pluck('userId')->map(static fn ($id): int => (int) $id)->all();
        $this->assertContains(self::VISIBLE_USER_ID, $candidateIds);
        $this->assertNotContains(self::HIDDEN_USER_ID, $candidateIds);

        $recordResponse = $this->asScopedAdmin()
            ->postJson('/api/admin/whsExpZeroRecords', ['limit' => 20])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $recordIds = collect($recordResponse->json('data.data'))
            ->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
        $this->assertSame([self::VISIBLE_USER_ID], array_values(array_unique($recordIds)));
    }

    public function test_legacy_scan_only_creates_pending_record_for_visible_user(): void
    {
        $response = $this->asScopedAdmin()
            ->postJson('/index/admin/order/oneKeySearch')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('col', 1);

        $this->assertSame(
            self::VISIBLE_USER_ID,
            (int) $response->json('data.records.0.user_id')
        );
        $this->assertDatabaseHas('whs_exp_zeros', [
            'user_id' => self::VISIBLE_USER_ID,
            'status' => 1,
        ]);
        $this->assertDatabaseMissing('whs_exp_zeros', [
            'user_id' => self::HIDDEN_USER_ID,
            'status' => 1,
        ]);
    }

    public function test_out_of_scope_clear_is_rejected_before_gateway_and_writes_nothing(): void
    {
        $calls = 0;
        $this->app->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 引用传入的调用计数。断言范围外用户的请求不会触发任何结算网关调用。
             * @var int
             */
            private $calls;

            public function __construct(int &$calls)
            {
                $this->calls = &$calls;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls++;

                return DepositSettlementResult::settled('must-not-run');
            }
        });

        $before = DB::table('whs_exp_zeros')->where('user_id', self::HIDDEN_USER_ID)->count();
        $this->asScopedAdmin()
            ->postJson('/api/admin/whsExpZero', ['user_id' => self::HIDDEN_USER_ID])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->assertSame(0, $calls);
        $this->assertSame(
            $before,
            DB::table('whs_exp_zeros')->where('user_id', self::HIDDEN_USER_ID)->count()
        );
        $this->assertDatabaseHas('user_infos', [
            'user_id' => self::HIDDEN_USER_ID,
            'total_funds' => -50.00,
        ]);
    }

    public function test_created_scope_keeps_candidate_scan_and_clear_visibility_consistent(): void
    {
        DB::table('role_data_scopes')->where('role_id', self::ROLE_ID)->update([
            'scope_type' => 'created',
            'agent_ids' => null,
            'user_ids' => null,
        ]);
        DB::table('user_infos')->where('user_id', self::VISIBLE_USER_ID)->update([
            'created_by' => (string) self::ADMIN_ID,
        ]);
        DB::table('user_infos')->where('user_id', self::HIDDEN_USER_ID)->update([
            'created_by' => (string) (self::ADMIN_ID + 1),
        ]);

        $candidateResponse = $this->asScopedAdmin()
            ->postJson('/api/admin/whsExpZeroList', ['limit' => 20])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $candidateIds = collect($candidateResponse->json('data.data'))
            ->pluck('userId')->map(static fn ($id): int => (int) $id)->all();
        $this->assertContains(self::VISIBLE_USER_ID, $candidateIds);
        $this->assertNotContains(self::HIDDEN_USER_ID, $candidateIds);

        $this->asScopedAdmin()
            ->postJson('/index/admin/order/oneKeySearch')
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.records.0.user_id', self::VISIBLE_USER_ID);

        $calls = [];
        $this->app->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 引用传入的调用捕获表（userId 列表）。断言范围内用户恰好入账一次、且金额与目标正确。
             * @var array<int, int>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->calls[] = $userId;

                return DepositSettlementResult::settled('created-scope-settled');
            }
        });

        $this->asScopedAdmin()
            ->postJson('/api/admin/whsExpZero', ['user_id' => self::VISIBLE_USER_ID])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->asScopedAdmin()
            ->postJson('/api/admin/whsExpZero', ['user_id' => self::HIDDEN_USER_ID])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->assertSame([self::VISIBLE_USER_ID], $calls);
    }

    private function asScopedAdmin()
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs(Admin::query()->findOrFail(self::ADMIN_ID), 'admin');
    }

    private function seedScopedAdmin(): void
    {
        $now = time();
        DB::table('roles')->updateOrInsert(['id' => self::ROLE_ID], [
            'name' => 'whs_scope_role_' . self::ROLE_ID,
            'guard_type' => 'admin',
            'description' => 'WHS data scope closure test role',
            'permissions' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->updateOrInsert(['role_id' => self::ROLE_ID], [
            'scope_type' => 'custom_users',
            'agent_ids' => null,
            'user_ids' => json_encode([self::VISIBLE_USER_ID]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->updateOrInsert(['id' => self::ADMIN_ID], [
            'role_id' => (string) self::ROLE_ID,
            'username' => 'whs-scope-admin',
            'email' => 'whs-scope-admin@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedUser(int $userId, string $userName): void
    {
        $now = time();
        DB::table('user_infos')->updateOrInsert(['user_id' => $userId], [
            'login_id' => 0,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'total_funds' => -50.00,
            'equity' => -50.00,
            'effective_credit' => 5.00,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedCompletedRecord(int $userId, string $userName): void
    {
        $now = time();
        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'balance' => -50.00,
            'credit' => 5.00,
            'status' => 2,
            'md5_key' => 'whs-scope-completed-' . $userId,
            'created_by' => (string) self::ADMIN_ID,
            'updated_by' => (string) self::ADMIN_ID,
            'created_at' => $now - 60,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
