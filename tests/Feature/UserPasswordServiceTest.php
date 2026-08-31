<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:03
 */

namespace Tests\Feature;

use App\Contracts\TradePasswordGateway;
use App\Facades\Mt4ManagerApi;
use App\Models\UserLogin;
use App\Services\CommissionTransfer\TradePasswordVerificationResult;
use App\Services\UserPasswordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 用户密码服务测试。
 *
 * 文件功能：
 * - 验证修改密码时先同步 MT4，再更新本地密码哈希。
 * - 验证敏感操作密码校验在本地模式和 MT4 模式下都返回明确状态。
 */
class UserPasswordServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_local_mode_updates_local_password(): void
    {
        $this->assertTrue(class_exists(UserPasswordService::class), 'User password service is missing.');
        $login = $this->insertLogin(418120101, 'password-service-local-418120101@example.test');
        config(['mt4.enabled' => false]);

        $this->assertTrue($this->passwordService()->change($login, 'local-new-password'));
        $this->assertTrue(Hash::check('local-new-password', $login->fresh()->password));
    }

    public function test_mt4_success_updates_local_password_after_remote_sync(): void
    {
        $login = $this->insertLogin(418120102, 'password-service-success-418120102@example.test');
        config(['mt4.enabled' => true]);
        Mt4ManagerApi::shouldReceive('changePassword')
            ->once()
            ->with((int) $login->user_id, 'remote-new-password')
            ->andReturn(['status' => 'ok']);

        $this->assertTrue($this->passwordService()->change($login, 'remote-new-password'));
        $this->assertTrue(Hash::check('remote-new-password', $login->fresh()->password));
    }

    public function test_mt4_failure_preserves_local_password(): void
    {
        $login = $this->insertLogin(418120103, 'password-service-failure-418120103@example.test');
        config(['mt4.enabled' => true]);
        Mt4ManagerApi::shouldReceive('changePassword')
            ->once()
            ->with((int) $login->user_id, 'rejected-new-password')
            ->andReturn(['status' => 'error', 'message' => 'MT4 unavailable']);

        $this->assertFalse($this->passwordService()->change($login, 'rejected-new-password'));
        $this->assertTrue(Hash::check('old-password', $login->fresh()->password));
    }

    /**
     * 验证本地模式使用 user_logins.password，正确密码和错误密码不会混淆。
     */
    public function test_local_mode_verifies_sensitive_operation_password(): void
    {
        $login = $this->insertLogin(418120104, 'password-service-verify-local-418120104@example.test');
        config(['mt4.enabled' => false]);

        $this->assertTrue($this->passwordService()->verifyLocal($login, 'old-password'));
        $this->assertFalse($this->passwordService()->verifyLocal($login, 'wrong-password'));
        $this->assertSame('verified', $this->passwordService()->verify($login, 'old-password'));
        $this->assertSame('rejected', $this->passwordService()->verify($login, 'wrong-password'));
    }

    /**
     * 验证 MT4 模式保留“密码错误”和“网络结果未知”的区别，供旧接口映射 pswErr 与 NETWORKFAIL。
     */
    public function test_mt4_mode_returns_rejected_and_network_failure_states(): void
    {
        $login = $this->insertLogin(418120105, 'password-service-verify-mt4-418120105@example.test');
        config(['mt4.enabled' => true]);
        $results = [
            TradePasswordVerificationResult::verified(),
            TradePasswordVerificationResult::rejected('bad_password'),
            TradePasswordVerificationResult::unknown('read_timeout'),
        ];
        $this->app->instance(TradePasswordGateway::class, new class($results) implements TradePasswordGateway {
            /** @var array<int, TradePasswordVerificationResult> */
            private array $results;

            /** @param array<int, TradePasswordVerificationResult> $results 预设网关返回结果。 */
            public function __construct(array $results)
            {
                $this->results = $results;
            }

            /**
             * 按调用顺序返回密码拒绝和网络未知结果。
             */
            public function verify(int $userId, string $password): TradePasswordVerificationResult
            {
                return array_shift($this->results);
            }
        });

        $service = $this->passwordService();
        $this->assertSame('verified', $service->verify($login, 'correct-password'));
        $this->assertSame('rejected', $service->verify($login, 'wrong-password'));
        $this->assertSame('network_failure', $service->verify($login, 'unknown-password'));
    }

    private function insertLogin(int $userId, string $email): UserLogin
    {
        DB::table('user_logins')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('email', $email)->delete();
        $now = time();

        $id = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('old-password'),
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

        return UserLogin::findOrFail($id);
    }

    private function passwordService(): UserPasswordService
    {
        $this->assertTrue(class_exists(UserPasswordService::class), 'User password service is missing.');

        return app(UserPasswordService::class);
    }
}
