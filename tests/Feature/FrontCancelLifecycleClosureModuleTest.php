<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:43
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\TradePasswordGateway;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\CancelApply;
use App\Models\UserLogin;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * 前台账号注销完整生命周期测试。
 *
 * 文件功能：
 * - 验证现代与旧注销入口都必须完成身份、一次性验证码和当前密码校验。
 * - 验证未平风险、下级关系、处理中出金和非零余额会在任何远端副作用前失败关闭。
 * - 验证 MT4 密码与锁定结果必须明确成功，随后才在同一数据库事务中创建申请并锁定本地能力。
 *
 * 返回结果：
 * - existSubUser、UnfinishedOrder、ERRBALANCE 表示对应业务前置条件不满足。
 * - NETWORKFAIL/FATALCANOTCONNECT 表示密码校验结果未知；MT4SYNCUPDATAFAIL 表示账号锁定未成功。
 * - SUC 表示 MT4 锁定、本地只读、禁止出金、申请记录和验证码消费全部完成。
 */
class FrontCancelLifecycleClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, int> 当前测试创建并需要清理缓存的业务用户 ID。 */
    private array $fixtureUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureUserIds as $userId) {
            Cache::forget('front_profile_cancel_code:' . $userId);
        }

        parent::tearDown();
    }

    /**
     * 验证现代注销接口不能只提交原因绕过身份、验证码和密码校验。
     */
    public function test_modern_cancel_application_requires_complete_sensitive_verification(): void
    {
        $userId = 419030100;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        config(['mt4.enabled' => false]);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/account/cancellation-applications', [
                'reason' => 'Missing verification must not create an application',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED)
            ->assertJsonPath('data.err', 'phoneErr');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);
    }

    /**
     * 验证负余额同样属于非零资金，不能绕过旧 ERRBALANCE 风险边界。
     */
    public function test_legacy_cancel_rejects_negative_balance(): void
    {
        $userId = 419030200;
        $login = $this->insertUser($userId, 2, 0, '-10.00');
        config(['mt4.enabled' => false]);
        $this->putCancelCode($userId, '302200', $login->email, '13919030200');

        $response = $this->postLegacyCancel($login, '302200');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'ERRBALANCE');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);
    }

    /**
     * 验证代理仍有直属下级时返回 existSubUser，不能把代理树变成无主关系。
     */
    public function test_legacy_cancel_rejects_agent_with_direct_child(): void
    {
        $agentId = 419030300;
        $childId = 419030301;
        $login = $this->insertUser($agentId, 1, 0, '0.00');
        $this->insertUser($childId, 2, $agentId, '0.00');
        config(['mt4.enabled' => false]);
        $this->putCancelCode($agentId, '303300', $login->email, '13919030300');

        $response = $this->postLegacyCancel($login, '303300');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'existSubUser')
            ->assertJsonPath('col', 'userId');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $agentId]);
    }

    /**
     * 验证待处理或处理中的出金会返回 UnfinishedOrder，避免锁号后资金任务失去处理主体。
     */
    public function test_legacy_cancel_rejects_pending_withdrawal(): void
    {
        $userId = 419030400;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        $this->insertPendingWithdrawal($userId);
        config(['mt4.enabled' => false]);
        $this->putCancelCode($userId, '304400', $login->email, '13919030400');

        $response = $this->postLegacyCancel($login, '304400');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'UnfinishedOrder');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);
    }

    /**
     * 验证 MT4 密码网关结果未知时返回网络错误，且不能继续锁定账号或创建申请。
     */
    public function test_legacy_cancel_stops_when_password_verification_is_unknown(): void
    {
        $userId = 419030500;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        $this->bindPasswordResult(TradePasswordVerificationResult::unknown('read_timeout'));
        $manager = Mockery::mock(Mt4ManagerService::class);
        $manager->shouldNotReceive('lockUser');
        $this->app->instance(Mt4ManagerService::class, $manager);
        $this->putCancelCode($userId, '305500', $login->email, '13919030500');

        $response = $this->postLegacyCancel($login, '305500');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'NETWORKFAIL')
            ->assertJsonPath('col', 'FATALCANOTCONNECT');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);
        $this->assertNotNull(Cache::get('front_profile_cancel_code:' . $userId));
    }

    /**
     * 验证 MT4 锁定失败时不写本地状态，验证码仍可用于用户稍后重试。
     */
    public function test_modern_cancel_fails_closed_when_mt4_lock_fails(): void
    {
        $userId = 419030600;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        $this->bindPasswordResult(TradePasswordVerificationResult::verified());
        $manager = Mockery::mock(Mt4ManagerService::class);
        $manager->shouldReceive('lockUser')
            ->once()
            ->with($userId)
            ->andReturn(['status' => 'error', 'error_code' => 'connect_timeout']);
        $this->app->instance(Mt4ManagerService::class, $manager);
        $this->putCancelCode($userId, '306600', $login->email, '13919030600');

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/api/front/account/cancellation-applications', $this->cancelPayload($login, '306600'));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED)
            ->assertJsonPath('data.err', 'MT4SYNCUPDATAFAIL');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
        ]);
        $this->assertNotNull(Cache::get('front_profile_cancel_code:' . $userId));
    }

    /**
     * 验证成功链路同时完成远端锁定、本地能力收口、申请创建和验证码消费。
     */
    public function test_legacy_cancel_success_closes_all_local_and_remote_steps(): void
    {
        $userId = 419030700;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        $this->bindPasswordResult(TradePasswordVerificationResult::verified());
        $manager = Mockery::mock(Mt4ManagerService::class);
        $manager->shouldReceive('lockUser')
            ->once()
            ->with($userId)
            ->andReturn(['status' => 'ok', 'err' => '0']);
        $this->app->instance(Mt4ManagerService::class, $manager);
        $this->putCancelCode($userId, '307700', $login->email, '13919030700');

        $response = $this->postLegacyCancel($login, '307700');

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOErr');
        $this->assertDatabaseHas('cancel_applies', ['user_id' => $userId, 'status' => 0]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 0,
            'is_mt4_readonly' => 1,
            'is_withdrawal_allowed' => 1,
        ]);
        $this->assertNull(Cache::get('front_profile_cancel_code:' . $userId));
    }

    /**
     * 验证远端锁号期间出现竞争待审申请时再次检查状态，不能创建第二条待审记录。
     */
    public function test_cancel_rechecks_pending_application_after_remote_lock(): void
    {
        $userId = 419031000;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        $this->bindPasswordResult(TradePasswordVerificationResult::verified());
        $manager = Mockery::mock(Mt4ManagerService::class);
        $manager->shouldReceive('lockUser')
            ->once()
            ->with($userId)
            ->andReturnUsing(function () use ($userId): array {
                $now = time();
                DB::table('cancel_applies')->insert([
                    'user_id' => $userId,
                    'user_name' => 'concurrent-cancel-' . $userId,
                    'status' => 0,
                    'cancel_remark' => 'Concurrent pending application',
                    'reject_reason' => '',
                    'created_by' => (string) $userId,
                    'updated_by' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);

                return ['status' => 'ok', 'err' => '0'];
            });
        $this->app->instance(Mt4ManagerService::class, $manager);
        $this->putCancelCode($userId, '310000', $login->email, '13919031000');

        $response = $this->postLegacyCancel($login, '310000');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'cancelApplyErr');
        $this->assertSame(1, DB::table('cancel_applies')->where('user_id', $userId)->where('status', 0)->count());
        $this->assertNotNull(Cache::get('front_profile_cancel_code:' . $userId));
    }

    /**
     * 验证远端锁号成功但本地事务失败时执行解锁补偿，并保留验证码供用户重试。
     */
    public function test_cancel_unlocks_remote_account_when_local_transaction_fails(): void
    {
        $userId = 419030800;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        $this->bindPasswordResult(TradePasswordVerificationResult::verified());
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
        $this->putCancelCode($userId, '308800', $login->email, '13919030800');

        // 在模型创建边界制造确定的数据库阶段失败，证明事务回滚和远端补偿均真实执行。
        CancelApply::creating(function (): void {
            throw new RuntimeException('forced cancellation application failure');
        });

        $response = $this->postLegacyCancel($login, '308800');

        $response->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'cancelApplyErr')
            ->assertJsonPath('col', 'NOCOL');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
        ]);
        $this->assertNotNull(Cache::get('front_profile_cancel_code:' . $userId));
    }

    /**
     * 验证销户验证码必须绑定当前联系方式，且成功提交后不能再次重放。
     */
    public function test_cancel_code_is_bound_to_contact_and_consumed_after_success(): void
    {
        $userId = 419030900;
        $login = $this->insertUser($userId, 2, 0, '0.00');
        config(['mt4.enabled' => false]);
        $this->putCancelCode(
            $userId,
            '309900',
            'another-account@example.test',
            '13919030900'
        );

        $mismatched = $this->postLegacyCancel($login, '309900');

        $mismatched->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'codeErr');
        $this->assertDatabaseMissing('cancel_applies', ['user_id' => $userId]);

        $this->putCancelCode($userId, '309900', $login->email, '13919030900');
        $accepted = $this->postLegacyCancel($login, '309900');
        $replayed = $this->postLegacyCancel($login, '309900');

        $accepted->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('err', 'NOErr');
        $replayed->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('err', 'codeErr');
        $this->assertSame(1, DB::table('cancel_applies')->where('user_id', $userId)->count());
        $this->assertNull(Cache::get('front_profile_cancel_code:' . $userId));
    }

    /**
     * 创建可登录用户、业务资料和已审核身份证，初始 MT4 与出金能力均启用。
     */
    private function insertUser(int $userId, int $accountType, int $parentId, string $totalFunds): UserLogin
    {
        $this->fixtureUserIds[] = $userId;
        $now = time();
        $email = 'front-cancel-lifecycle-' . $userId . '@example.test';
        $phone = '139' . substr((string) $userId, -8);

        DB::table('withdraw_records')->where('user_id', $userId)->delete();
        DB::table('cancel_applies')->where('user_id', $userId)->delete();
        DB::table('user_trades')->where('user_id', $userId)->delete();
        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => $accountType,
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
            'user_name' => 'cancel-lifecycle-' . $userId,
            'phone' => $phone,
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => $totalFunds,
            'used_margin' => 0,
            'avail_margin' => $totalFunds,
            'equity' => $totalFunds,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_mt4_enabled' => 1,
            'is_mt4_readonly' => 0,
            'is_withdrawal_allowed' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'id_card_no' => 'ID' . $userId,
            'id_card_status' => 2,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return UserLogin::findOrFail($loginId);
    }

    /**
     * 创建待处理出金记录；status=0 对应旧项目 apply_status=0。
     */
    private function insertPendingWithdrawal(int $userId): void
    {
        $now = time();
        DB::table('withdraw_records')->insert([
            'user_id' => $userId,
            'user_name' => 'cancel-lifecycle-' . $userId,
            'apply_amount' => 20,
            'status' => 0,
            'local_order_no' => 'CANCEL-LIFECYCLE-' . $userId,
            'funding_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 绑定 MT4 密码网关结果，并启用真实远端分支。
     */
    private function bindPasswordResult(TradePasswordVerificationResult $result): void
    {
        config(['mt4.enabled' => true]);
        $this->app->instance(TradePasswordGateway::class, new class($result) implements TradePasswordGateway {
            /**
             * 资金密码替身预设的校验结果。驱动注销生命周期中资金密码校验的成功/失败分支。
             * @var TradePasswordVerificationResult
             */
            private $result;

            public function __construct(TradePasswordVerificationResult $result)
            {
                $this->result = $result;
            }

            /**
             * 返回预设结果，测试只关注控制器如何解释三态密码语义。
             */
            public function verify(int $userId, string $password): TradePasswordVerificationResult
            {
                return $this->result;
            }
        });
    }

    /**
     * 写入销户发码接口使用的缓存结构，邮箱和手机号必须与当前用户绑定。
     */
    private function putCancelCode(int $userId, string $code, string $email, string $phone): void
    {
        Cache::put('front_profile_cancel_code:' . $userId, [
            'code' => $code,
            'email' => strtolower($email),
            'phone' => $phone,
            'type' => 'cancel',
        ], now()->addMinutes(10));
    }

    /**
     * 构建当前用户完整销户身份字段。
     *
     * @return array<string, string> 现代与旧接口共用的请求字段。
     */
    private function cancelPayload(UserLogin $login, string $code): array
    {
        $userId = (int) $login->user_id;

        return [
            'userIdcardNo' => 'ID' . $userId,
            'userphoneNo' => '139' . substr((string) $userId, -8),
            'useremail' => (string) $login->email,
            'password' => 'password',
            'userverfcode' => $code,
            'reason' => 'Verified account cancellation',
        ];
    }

    /**
     * 通过旧 URI 提交完整销户字段，返回响应供旧错误码断言。
     */
    private function postLegacyCancel(UserLogin $login, string $code)
    {
        return $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/center/ajaxCancelAccount', $this->cancelPayload($login, $code));
    }
}
