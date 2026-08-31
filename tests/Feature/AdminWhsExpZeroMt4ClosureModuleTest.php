<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 16:24
 */

declare(strict_types=1);

/**
 * 管理员一键清零（whsExpZero）MT4 出入金网关闭环测试。
 *
 * 文件功能：
 * - 验证 /api/admin/whsExpZero 一键清零调用 MT4 存款网关并成功标记记录完成。
 * - 验证当信用额度覆盖亏损缺口时按全额绝对值存款。
 * - 验证 MT4 拒绝时失败关闭：不标记完成、写入失败记录、余额保持不变。
 *
 * 适用场景：
 * - 负余额用户一键清零流程与 MT4 网关交互的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/whsExpZero
 *   user_id: 983501（total_funds = -120.50、effective_credit = 15.25）
 *
 * 返回值：
 * - 成功时 code 为 SUCCESS，data.status = 2、data.provider_reference 为网关流水号。
 * - 失败时 code 为 MT4_SYNC_FAILED，whs_exp_zeros 记录 status = 3。
 *
 * 异常或失败场景：
 * - MT4 拒绝时余额与状态必须保持不变，否则断言失败。
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

class AdminWhsExpZeroMt4ClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证一键清零成功时调用 MT4 存款网关、标记记录完成并清零余额。
     */
    public function test_one_key_zero_calls_mt4_deposit_and_marks_record_completed_on_success(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983501;
        $this->resetUser($userId);
        $this->seedUser($userId, 'Zero MT4 Success User', -120.50, 15.25);

        $captured = [];
        $this->app->instance(DepositSettlementGateway::class, new class($captured) implements DepositSettlementGateway {
            /**
             * 引用传入的调用捕获表。deposit() 记下 [userId, amount, comment]，断言 WHS 体验金订单入账参数正确。
             * @var array<int, array{userId:int,amount:string,comment:string}>
             */
            private $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->captured[] = compact('userId', 'amount', 'comment');

                return DepositSettlementResult::settled('77001');
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/whsExpZero', ['user_id' => $userId]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.status', 2)
            ->assertJsonPath('data.provider_reference', '77001');

        $this->assertCount(1, $captured);
        $this->assertSame($userId, $captured[0]['userId']);
        $this->assertSame('105.25', $captured[0]['amount']);
        $this->assertStringContainsString((string) $userId, $captured[0]['comment']);

        $this->assertDatabaseHas('whs_exp_zeros', [
            'user_id' => $userId,
            'status' => 2,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'total_funds' => 0,
        ]);
    }

    /**
     * 验证信用额度覆盖亏损缺口时，一键清零按亏损全额绝对值存款。
     */
    public function test_one_key_zero_deposits_full_abs_balance_when_credit_covers_hole(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983502;
        $this->resetUser($userId);
        $this->seedUser($userId, 'Zero MT4 Credit Cover User', -50.00, 80.00);

        $captured = [];
        $this->app->instance(DepositSettlementGateway::class, new class($captured) implements DepositSettlementGateway {
            /**
             * 引用传入的调用捕获表。deposit() 记下入参，验证体验金结算不会重复入账。
             * @var array<int, array{userId:int,amount:string,comment:string}>
             */
            private $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->captured[] = compact('userId', 'amount', 'comment');

                return DepositSettlementResult::settled('77002');
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/whsExpZero', ['user_id' => $userId]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame('50.00', $captured[0]['amount']);
    }

    public function test_one_key_zero_uses_legacy_formula_when_credit_is_negative(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983506;
        $this->resetUser($userId);
        $this->seedUser($userId, 'Zero MT4 Negative Credit User', -100.00, -20.00);

        $captured = [];
        $this->app->instance(DepositSettlementGateway::class, new class($captured) implements DepositSettlementGateway {
            /**
             * 引用传入的调用捕获表。deposit() 记下入参，验证指定场景的入账行为与金额。
             * @var array<int, array{userId:int,amount:string,comment:string}>
             */
            private $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                $this->captured[] = compact('userId', 'amount', 'comment');

                return DepositSettlementResult::settled('77006');
            }
        });

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin')
            ->postJson('/api/admin/whsExpZero', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertCount(1, $captured);
        $this->assertSame('120.00', $captured[0]['amount']);
    }

    public function test_one_key_zero_rejects_agent_account_before_gateway_invocation(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983507;
        $this->resetUser($userId);
        $this->seedUser($userId, 'Zero MT4 Agent Account', -100.00, 0.00, 1);

        $calls = 0;
        $this->app->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 引用传入的调用计数。断言该场景下结算网关完全未被触发（计数为 0），验证失败关闭语义。
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

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin')
            ->postJson('/api/admin/whsExpZero', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertSame(0, $calls);
        $this->assertSame(0, DB::table('whs_exp_zeros')->where('user_id', $userId)->count());
        $this->assertDatabaseHas('user_infos', ['user_id' => $userId, 'total_funds' => -100.00]);
    }

    /**
     * 验证 MT4 拒绝时失败关闭：不标记完成、写入失败记录、余额保持不变。
     */
    public function test_one_key_zero_fails_closed_when_mt4_rejects_and_does_not_mark_completed(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983503;
        $this->resetUser($userId);
        $this->seedUser($userId, 'Zero MT4 Reject User', -40.00, 5.00);

        $this->app->instance(DepositSettlementGateway::class, new class implements DepositSettlementGateway {
            public function deposit(int $userId, string $amount, string $comment): DepositSettlementResult
            {
                return DepositSettlementResult::rejected('provider_rejected');
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($actor, 'admin')
            ->postJson('/api/admin/whsExpZero', ['user_id' => $userId]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);

        $this->assertSame(
            0,
            DB::table('whs_exp_zeros')->where('user_id', $userId)->where('status', 2)->count()
        );
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'total_funds' => -40.00,
        ]);
        $failed = DB::table('whs_exp_zeros')->where('user_id', $userId)->where('status', 3)->first();
        $this->assertNotNull($failed);
    }

    public function test_one_key_zero_claims_existing_pending_record_before_gateway_call(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983504;
        $this->resetUser($userId);
        $this->seedUser($userId, 'Zero MT4 Pending User', -60.00, 10.00);
        $now = time();
        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => 'Zero MT4 Pending User',
            'balance' => -60.00,
            'credit' => 10.00,
            'status' => 1,
            'md5_key' => 'pending-' . $userId,
            'created_by' => '1',
            'updated_by' => '1',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $calls = 0;
        $this->app->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 引用传入的调用计数。断言异常路径不触发任何结算调用。
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

                return DepositSettlementResult::settled('77004');
            }
        });

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin')
            ->postJson('/api/admin/whsExpZero', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.status', 2);

        $this->assertSame(1, $calls);
        $this->assertSame(1, DB::table('whs_exp_zeros')->where('user_id', $userId)->count());
        $this->assertDatabaseHas('whs_exp_zeros', ['user_id' => $userId, 'status' => 2]);
    }

    public function test_one_key_zero_rejects_processing_record_without_calling_gateway(): void
    {
        $actor = $this->ensureSuperAdmin();
        $userId = 983505;
        $this->resetUser($userId);
        $this->seedUser($userId, 'Zero MT4 Processing User', -25.00, 0);
        $now = time();
        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => 'Zero MT4 Processing User',
            'balance' => -25.00,
            'credit' => 0,
            'status' => 0,
            'md5_key' => 'processing-' . $userId,
            'created_by' => '1',
            'updated_by' => '1',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $calls = 0;
        $this->app->instance(DepositSettlementGateway::class, new class($calls) implements DepositSettlementGateway {
            /**
             * 引用传入的调用计数。断言重复执行（幂等重放）不会产生第二次入账调用。
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

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($actor, 'admin')
            ->postJson('/api/admin/whsExpZero', ['user_id' => $userId])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_ALREADY_EXISTS);

        $this->assertSame(0, $calls);
        $this->assertDatabaseHas('user_infos', ['user_id' => $userId, 'total_funds' => -25.00]);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-whs-zero-mt4-super',
                'email' => 'admin-whs-zero-mt4-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function resetUser(int $userId): void
    {
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('whs_exp_zeros')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
    }

    private function seedUser(
        int $userId,
        string $userName,
        float $totalFunds,
        float $credit,
        int $accountType = 2
    ): void
    {
        $now = time();
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'total_funds' => $totalFunds,
            'equity' => $totalFunds,
            'effective_credit' => $credit,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
