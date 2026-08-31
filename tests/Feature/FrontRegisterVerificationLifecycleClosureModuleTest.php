<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 01:13
 */

/**
 * FrontRegisterVerificationLifecycleClosureModuleTest
 *
 * 文件功能：
 * - 验证前台注册验证生命周期：内部错误脱敏不泄露密钥、失败结果映射预期码、提交锁防重复注册、验证码一次性、user_logins.email 唯一迁移、夹具自增恢复与互斥锁释放、成功注册返回 token 并消费验证码。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Services\JwtService;
use App\Services\UserRegistrationService;
use Exception;
use Error;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Support\MySqlFixtureMutex;
use Tests\TestCase;

class FrontRegisterVerificationLifecycleClosureModuleTest extends TestCase
{
    /**
     * 夹具插入的 user_logins 主键清单。tearDown 据其删除注册链路产生的登录行。
     * @var array<int, int>
     */
    private $fixtureLoginIds = [];

    /**
     * 夹具插入的 user_infos 主键清单。tearDown 据其删除注册链路产生的用户信息行。
     * @var array<int, int>
     */
    private $fixtureInfoIds = [];

    /**
     * 夹具写入前各表的 AUTO_INCREMENT 基线（表名 => 自增值或 null）。tearDown 恢复，
     * 防止注册夹具抬高共享库自增计数。
     * @var array<string, int|null>
     */
    private $originalAutoIncrements = [];

    /**
     * 本用例写入 Cache 的键清单（注册验证码等）。tearDown 逐键 forget，防止缓存项跨用例泄漏。
     * @var array<int, string>
     */
    private $cacheKeys = [];

    /**
     * MySqlFixtureMutex 实例。串行化共享测试库上的夹具准备与清理，避免并行进程互相踩踏。
     * @var MySqlFixtureMutex|null
     */
    private $fixtureMutex;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->fixtureMutex = new MySqlFixtureMutex();
            $this->fixtureMutex->acquire();
        } catch (\Throwable $exception) {
            $this->abortFixtureSetup($exception);
        }
    }

    private function abortFixtureSetup(\Throwable $cause): void
    {
        $failures = [];
        $firstFailure = null;
        try {
            if ($this->fixtureMutex !== null) {
                $this->fixtureMutex->releaseWithDisconnectFallback();
            }
        } catch (\Throwable $exception) {
            $firstFailure = $firstFailure ?? $exception;
            $failures[] = 'mutex_release: ' . $exception->getMessage();
        } finally {
            $this->fixtureMutex = null;
        }
        try {
            parent::tearDown();
        } catch (\Throwable $exception) {
            $firstFailure = $firstFailure ?? $exception;
            $failures[] = 'parent_teardown: ' . $exception->getMessage();
        }
        if ($failures !== []) {
            throw new \RuntimeException(
                'Registration lifecycle fixture setup failed: ' . implode(' | ', $failures),
                0,
                $cause
            );
        }

        throw $cause;
    }

    protected function tearDown(): void
    {
        $failureMessages = [];
        $firstFailure = null;
        try {
            try {
                try {
                    $this->cleanupCacheKeys($failureMessages, $firstFailure);
                } catch (\Throwable $exception) {
                    $this->recordCleanupFailure(
                        'cache cleanup',
                        $exception,
                        $failureMessages,
                        $firstFailure
                    );
                }
            } finally {
                try {
                    $this->cleanupProvisionedFixtures($failureMessages, $firstFailure);
                } catch (\Throwable $exception) {
                    $this->recordCleanupFailure(
                        'database cleanup',
                        $exception,
                        $failureMessages,
                        $firstFailure
                    );
                }
            }
        } finally {
            try {
                if ($this->fixtureMutex !== null) {
                    $this->fixtureMutex->releaseWithDisconnectFallback();
                }
            } catch (\Throwable $exception) {
                $this->recordCleanupFailure(
                    'mutex release',
                    $exception,
                    $failureMessages,
                    $firstFailure
                );
            } finally {
                try {
                    parent::tearDown();
                } catch (\Throwable $exception) {
                    $this->recordCleanupFailure(
                        'parent teardown',
                        $exception,
                        $failureMessages,
                        $firstFailure
                    );
                } finally {
                    $this->fixtureMutex = null;
                    $this->cacheKeys = [];
                    $this->fixtureLoginIds = [];
                    $this->fixtureInfoIds = [];
                    $this->originalAutoIncrements = [];
                }
            }
        }

        $this->throwCleanupFailures('registration lifecycle teardown', $failureMessages, $firstFailure);
    }

    public function test_validation_throwable_masks_internal_error_without_leaking_secrets(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('validation-throwable@example.test');

        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andThrow(new Error('validation engine secret'));
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', $payload);

        $response->assertOk()->assertJsonPath('code', ResponseCode::INTERNAL_ERROR)
            ->assertJsonPath('message', __('response.server_error'));
        $this->assertStringNotContainsString('secret', (string) $response->json('message'));
        $this->assertSame('AB12', Cache::get($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
    }

    /** @dataProvider invalidRegistrationResultProvider */
    public function test_invalid_registration_results_map_to_expected_codes($result, int $expectedCode): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('invalid-result-' . md5(serialize($result)) . '@example.test');
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registrationService->shouldReceive('register')->once()->andReturn($result);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', $payload);

        $response->assertOk()->assertJsonPath('code', $expectedCode);
        $this->assertSame('AB12', Cache::get($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
    }

    public function invalidRegistrationResultProvider(): array
    {
        return [
            [[], ResponseCode::INTERNAL_ERROR],
            [['success' => false], ResponseCode::VALIDATION_ERROR],
            [['success' => true, 'user_login' => 'wrong-type'], ResponseCode::INTERNAL_ERROR],
        ];
    }

    public function test_missing_success_key_maps_to_internal_error(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('missing-success@example.test');
        $login = new UserLogin();
        $login->forceFill(['id' => 991103, 'user_id' => 41991103, 'email' => $payload['email']]);
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registrationService->shouldReceive('register')->once()->andReturn(['user_login' => $login]);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', $payload);

        $response->assertOk()->assertJsonPath('code', ResponseCode::INTERNAL_ERROR);
        $this->assertSame('AB12', Cache::get($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
    }

    public function test_jwt_generation_error_returns_registration_completed_login_required(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('jwt-error@example.test');
        $login = $this->createProvisionedLogin((string) $payload['email']);
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registrationService->shouldReceive('register')->once()->andReturn([
            'success' => true,
            'registered' => true,
            'provisioning_status' => 'pending',
            'user_login' => $login,
        ]);
        $this->app->instance(UserRegistrationService::class, $registrationService);
        $jwtService = Mockery::mock(JwtService::class);
        $jwtService->shouldReceive('generateToken')->once()->andThrow(new Error('jwt secret'));
        $this->app->instance(JwtService::class, $jwtService);

        $response = $this->postJson('/api/front/auth/register', $payload);

        $response->assertOk()->assertJsonPath('code', ResponseCode::INTERNAL_ERROR)
            ->assertJsonPath('message', __('response.registration_completed_login_required'))
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.login_required', true);
        $this->assertFalse(Cache::has($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
        $lockKey = 'front_register_submit_lock_' . sha1(strtolower($payload['email']));
        $releasedLock = Cache::lock($lockKey, 15);
        $this->assertTrue($releasedLock->get());
        $releasedLock->release();
    }

    public function test_submit_lock_blocks_duplicate_registration(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('locked@example.test');
        $lockKey = 'front_register_submit_lock_' . sha1(strtolower($payload['email']));
        $lock = Cache::lock($lockKey, 120);
        $this->assertTrue($lock->get());
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldNotReceive('validateRegistration');
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        try {
            $response = $this->postJson('/api/front/auth/register', $payload);
        } finally {
            $lock->release();
        }

        $response->assertOk()->assertJsonPath('code', ResponseCode::RATE_LIMITED);
        $this->assertSame('AB12', Cache::get($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
    }

    public function test_submit_lock_blocks_registration_with_different_captcha(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('same-email-lock@example.test');
        $otherCaptchaKey = 'different-valid-captcha';
        $otherCaptchaCacheKey = 'front_register_captcha_' . sha1($otherCaptchaKey);
        $this->cacheKeys[] = $otherCaptchaCacheKey;
        Cache::put($otherCaptchaCacheKey, 'CD34', now()->addMinutes(10));
        $payload['captcha_key'] = $otherCaptchaKey;
        $payload['captcha_code'] = 'CD34';
        $lock = Cache::lock('front_register_submit_lock_' . sha1(strtolower($payload['email'])), 120);
        $this->assertTrue($lock->get());
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldNotReceive('validateRegistration');
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        try {
            $response = $this->postJson('/api/front/auth/register', $payload);
        } finally {
            $lock->release();
        }

        $response->assertOk()->assertJsonPath('code', ResponseCode::RATE_LIMITED);
        $this->assertSame('CD34', Cache::get($otherCaptchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
    }

    public function test_captcha_is_single_use_after_success_without_email_code_input(): void
    {
        [$payload] = $this->registrationPayload('single-use@example.test');
        $login = $this->createProvisionedLogin((string) $payload['email']);
        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registrationService->shouldReceive('register')->once()->andReturn([
            'success' => true,
            'registered' => true,
            'provisioning_status' => 'pending',
            'user_login' => $login,
        ]);
        $this->app->instance(UserRegistrationService::class, $registrationService);
        $jwtService = Mockery::mock(JwtService::class);
        $jwtService->shouldReceive('generateToken')->once()->andReturn('single-use-token');
        $this->app->instance(JwtService::class, $jwtService);

        $this->postJson('/api/front/auth/register', $payload)->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->postJson('/api/front/auth/register', $payload)->assertJsonPath('code', ResponseCode::VALIDATION_ERROR);
    }

    public function test_user_logins_email_unique_migration(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_03_29_000010_create_user_logins_table.php')) ?: '';
        $this->assertStringContainsString("$" . "blueprint->unique('email')", $migration);
    }

    public function test_registration_completed_login_required_translation_exists(): void
    {
        $this->assertNotSame('response.registration_completed_login_required', trans('response.registration_completed_login_required', [], 'zh-CN'));
        $this->assertNotSame('response.registration_completed_login_required', trans('response.registration_completed_login_required', [], 'zh_CN'));
        $this->assertNotSame('response.registration_completed_login_required', trans('response.registration_completed_login_required', [], 'en'));
    }

    public function test_fixture_cleanup_restores_auto_increments_and_fingerprints(): void
    {
        $this->assertTrue(
            method_exists($this, 'tableFingerprintSnapshot'),
            'Fixture cleanup requires complete table fingerprints.'
        );
        $originalAutoIncrements = $this->autoIncrementSnapshot();
        $originalTables = $this->tableFingerprintSnapshot(['user_logins', 'user_infos']);

        $login = $this->createProvisionedLogin('auto-increment-fixture@example.test');
        $loginId = (int) $login->id;
        $infoId = (int) DB::table('user_infos')->where('login_id', $loginId)->value('id');
        $this->assertGreaterThan(0, $infoId);
        $this->assertTrue(DB::table('user_logins')->where('id', $loginId)->exists());
        $this->assertTrue(DB::table('user_infos')->where('id', $infoId)->exists());

        $this->cleanupProvisionedFixtures();
        $this->fixtureLoginIds = [];
        $this->fixtureInfoIds = [];

        $this->assertFalse(DB::table('user_logins')->where('id', $loginId)->exists());
        $this->assertFalse(DB::table('user_infos')->where('id', $infoId)->exists());
        $this->assertSame($originalAutoIncrements, $this->autoIncrementSnapshot());
        $this->assertSame(
            $originalTables,
            $this->tableFingerprintSnapshot(['user_logins', 'user_infos'])
        );
    }

    public function test_run_cleanup_steps_collects_all_failures(): void
    {
        $this->assertTrue(method_exists($this, 'runCleanupSteps'));

        $attempted = [];
        $first = new \DomainException('first lifecycle cleanup failure');
        $failure = null;
        try {
            $this->runCleanupSteps('lifecycle cleanup', [
                'first' => function () use (&$attempted, $first): void {
                    $attempted[] = 'first';
                    throw $first;
                },
                'second' => function () use (&$attempted): void {
                    $attempted[] = 'second';
                },
                'third' => function () use (&$attempted): void {
                    $attempted[] = 'third';
                    throw new \RuntimeException('second lifecycle cleanup failure');
                },
            ]);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        $this->assertSame(['first', 'second', 'third'], $attempted);
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame($first, $failure->getPrevious());
        $this->assertStringContainsString('first lifecycle cleanup failure', $failure->getMessage());
        $this->assertStringContainsString('second lifecycle cleanup failure', $failure->getMessage());
    }

    public function test_auto_increment_snapshot_reads_current_value(): void
    {
        $original = $this->autoIncrementSnapshot();
        $originalValue = $original['user_logins'];
        $this->assertNotNull($originalValue);
        $maxId = (int) (DB::table('user_logins')->max('id') ?? 0);
        $mutatedValue = max((int) $originalValue + 100, $maxId + 1);

        DB::statement('ALTER TABLE `user_logins` AUTO_INCREMENT = ' . $mutatedValue);
        try {
            $this->assertSame($mutatedValue, $this->autoIncrementSnapshot()['user_logins']);
        } finally {
            DB::statement('ALTER TABLE `user_logins` AUTO_INCREMENT = ' . $originalValue);
        }
    }

    public function test_cleanup_cache_keys_forgets_tracked_keys(): void
    {
        [, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload(
            'cache-tracking@example.test'
        );

        $this->assertContains($captchaCacheKey, $this->cacheKeys);
        $this->assertContains($emailCacheKey, $this->cacheKeys);
        $this->cleanupCacheKeys();
        $this->assertFalse(Cache::has($captchaCacheKey));
        $this->assertFalse(Cache::has($emailCacheKey));
    }

    public function test_setup_releases_mutex_after_database_cleanup(): void
    {
        $this->assertTrue(
            property_exists($this, 'fixtureMutex'),
            'Registration fixtures must own a MySqlFixtureMutex.'
        );
        $source = static function (string $method): string {
            $reflection = new \ReflectionMethod(self::class, $method);
            $lines = file($reflection->getFileName());

            return implode('', array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1
            ));
        };
        $abort = $source('abortFixtureSetup');
        $setUp = $source('setUp');
        $tearDown = $source('tearDown');

        $this->assertStringContainsString('new MySqlFixtureMutex', $setUp);
        $this->assertStringContainsString('->acquire()', $setUp);
        $this->assertStringContainsString('abortFixtureSetup', $setUp);
        $this->assertStringContainsString('->releaseWithDisconnectFallback()', $abort);
        $this->assertStringContainsString('->releaseWithDisconnectFallback()', $tearDown);
        $this->assertTrue(
            strpos($tearDown, 'cleanupProvisionedFixtures') < strpos($tearDown, '->releaseWithDisconnectFallback()'),
            'The registration mutex must be released after DB cleanup and AUTO_INCREMENT restore.'
        );
    }

    public function test_validation_failure_returns_validation_error_and_releases_lock(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('validation-failure@example.test');

        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')
            ->once()
            ->andReturn(['register.invalid_inviter']);
        $registrationService->shouldNotReceive('register');
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', $payload);

        $response->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_ERROR);
        $this->assertSame('AB12', Cache::get($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
        $lock = Cache::lock('front_register_submit_lock_' . sha1(strtolower($payload['email'])), 120);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_register_exception_masks_database_details(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('exception@example.test');

        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registrationService->shouldReceive('register')
            ->once()
            ->andThrow(new Exception('SQLSTATE[23000]: secret database detail'));
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $response = $this->postJson('/api/front/auth/register', $payload);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::INTERNAL_ERROR)
            ->assertJsonPath('message', __('response.server_error'))
            ->assertJsonMissing(['message' => 'SQLSTATE[23000]: secret database detail']);
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
        $this->assertSame('AB12', Cache::get($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
    }

    public function test_successful_registration_returns_token_and_consumes_codes(): void
    {
        [$payload, $captchaCacheKey, $emailCacheKey] = $this->registrationPayload('success@example.test');
        $login = $this->createProvisionedLogin((string) $payload['email']);

        $registrationService = Mockery::mock(UserRegistrationService::class);
        $registrationService->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registrationService->shouldReceive('register')->once()->andReturn([
            'success' => true,
            'registered' => true,
            'provisioning_status' => 'pending',
            'user_login' => $login,
        ]);
        $this->app->instance(UserRegistrationService::class, $registrationService);

        $jwtService = Mockery::mock(JwtService::class);
        $jwtService->shouldReceive('generateToken')->once()->andReturn('registered-token');
        $this->app->instance(JwtService::class, $jwtService);

        $response = $this->postJson('/api/front/auth/register', $payload);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.access_token', 'registered-token');
        $this->assertFalse(Cache::has($captchaCacheKey));
        $this->assertSame('654321', Cache::get($emailCacheKey)['code'] ?? null);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function registrationPayload(string $email): array
    {
        $captchaKey = 'captcha-lifecycle-' . sha1($email);
        $captchaCacheKey = 'front_register_captcha_' . sha1($captchaKey);
        $emailCacheKey = 'front_register_email_code_' . sha1($email);
        $this->cacheKeys[] = $captchaCacheKey;
        $this->cacheKeys[] = $emailCacheKey;
        Cache::put($captchaCacheKey, 'AB12', now()->addMinutes(10));
        Cache::put($emailCacheKey, [
            'email' => $email,
            'code' => '654321',
        ], now()->addMinutes(10));

        return [[
            'email' => $email,
            'password' => 'RegisterPassword1',
            'password_confirmation' => 'RegisterPassword1',
            'user_name' => 'Register Lifecycle User',
            'phone_code' => '86',
            'phone_number' => '13999110101',
            'phone' => '86-13999110101',
            'id_card_no' => 'REGISTER-LIFECYCLE-ID',
            'gender' => '1',
            'account_type' => 1,
            'captcha_key' => $captchaKey,
            'captcha_code' => 'AB12',
            'agree_terms' => 1,
        ], $captchaCacheKey, $emailCacheKey];
    }

    private function createProvisionedLogin(string $email): UserLogin
    {
        $userId = $this->unusedFixtureUserId();
        $this->rememberAutoIncrement('user_logins');
        $this->rememberAutoIncrement('user_infos');

        $login = UserLogin::create([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('RegisterPassword1'),
            'account_type' => 1,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '127.0.0.1',
        ]);
        $this->fixtureLoginIds[] = (int) $login->id;

        $info = UserInfo::create([
            'user_id' => $userId,
            'login_id' => $login->id,
            'user_name' => 'Register Lifecycle User',
            'phone' => '86-' . $userId,
            'gender' => 1,
            'level_id' => 0,
            'group_id' => 0,
            'parent_id' => 0,
            'account_type' => 1,
            'family_tree' => (string) $userId,
            'comm_rate' => 0,
            'leverage' => 100,
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => 1,
            'mt4_code' => $userId,
            'is_agent_confirmed' => 1,
            'original_group' => 'demo\\retail',
            'mt4_group' => 'demo\\retail',
            'country' => 'CN',
            'data_source' => 0,
            'created_by' => 0,
            'updated_by' => 0,
        ]);
        $this->fixtureInfoIds[] = (int) $info->id;

        return $login;
    }

    private function unusedFixtureUserId(): int
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = random_int(460000000, 469999998);
            if (!UserLogin::whereIn('user_id', [$candidate, $candidate + 1])->exists()
                && !UserInfo::whereIn('user_id', [$candidate, $candidate + 1])->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to reserve an isolated registration lifecycle fixture.');
    }

    private function rememberAutoIncrement(string $table): void
    {
        if (array_key_exists($table, $this->originalAutoIncrements)) {
            return;
        }

        DB::statement('SET SESSION information_schema_stats_expiry = 0');
        $value = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->value('AUTO_INCREMENT');
        $this->originalAutoIncrements[$table] = $value === null ? null : (int) $value;
    }

    private function cleanupCacheKeys(
        ?array &$failureMessages = null,
        ?\Throwable &$firstFailure = null
    ): void {
        $throwOnFailure = $failureMessages === null;
        if ($failureMessages === null) {
            $failureMessages = [];
        }

        $steps = [];
        foreach (array_values(array_unique($this->cacheKeys)) as $index => $key) {
            $steps['forget cache key ' . $index] = static function () use ($key): void {
                Cache::forget($key);
            };
        }
        $this->collectCleanupSteps($steps, $failureMessages, $firstFailure);

        if ($throwOnFailure) {
            $this->throwCleanupFailures('registration lifecycle cache cleanup', $failureMessages, $firstFailure);
        }
    }

    private function cleanupProvisionedFixtures(
        ?array &$failureMessages = null,
        ?\Throwable &$firstFailure = null
    ): void {
        $throwOnFailure = $failureMessages === null;
        if ($failureMessages === null) {
            $failureMessages = [];
        }

        $steps = [];
        if ($this->fixtureInfoIds !== []) {
            $steps['delete user info rows'] = function (): void {
                DB::table('user_infos')->whereIn('id', $this->fixtureInfoIds)->delete();
            };
        }
        if ($this->fixtureLoginIds !== []) {
            $steps['delete user login rows'] = function (): void {
                DB::table('user_logins')->whereIn('id', $this->fixtureLoginIds)->delete();
            };
        }
        foreach ($this->autoIncrementCleanupSteps() as $label => $step) {
            $steps[$label] = $step;
        }
        $this->collectCleanupSteps($steps, $failureMessages, $firstFailure);

        if ($throwOnFailure) {
            $this->throwCleanupFailures(
                'registration lifecycle database cleanup',
                $failureMessages,
                $firstFailure
            );
        }
    }

    /** @return array<string, callable(): void> */
    private function autoIncrementCleanupSteps(): array
    {
        $steps = [];
        foreach ($this->originalAutoIncrements as $table => $value) {
            if ($value === null) {
                continue;
            }
            $steps['restore ' . $table . ' AUTO_INCREMENT'] = function () use ($table, $value): void {
                DB::statement('SET SESSION information_schema_stats_expiry = 0');
                $maxId = DB::table($table)->max('id');
                if ($maxId !== null && $value <= (int) $maxId) {
                    throw new \RuntimeException(
                        'Refusing to lower fixture AUTO_INCREMENT below an existing ' . $table . '.id value.'
                    );
                }
                DB::statement('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . $value);
                DB::statement('SET SESSION information_schema_stats_expiry = 0');
                $actual = DB::table('information_schema.TABLES')
                    ->where('TABLE_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', $table)
                    ->value('AUTO_INCREMENT');
                $actual = $actual === null ? null : (int) $actual;
                if ($actual !== $value) {
                    throw new \RuntimeException(
                        $table . ' AUTO_INCREMENT mismatch: expected ' . $value . ', actual '
                        . var_export($actual, true) . '.'
                    );
                }
            };
        }

        return $steps;
    }

    /**
     * @param array<string, callable(): void> $steps
     */
    private function runCleanupSteps(string $scope, array $steps): void
    {
        $failureMessages = [];
        $firstFailure = null;
        $this->collectCleanupSteps($steps, $failureMessages, $firstFailure);
        $this->throwCleanupFailures($scope, $failureMessages, $firstFailure);
    }

    /**
     * @param array<string, callable(): void> $steps
     * @param array<int, string> $failureMessages
     */
    private function collectCleanupSteps(
        array $steps,
        array &$failureMessages,
        ?\Throwable &$firstFailure
    ): void {
        foreach ($steps as $label => $step) {
            try {
                $step();
            } catch (\Throwable $exception) {
                $this->recordCleanupFailure(
                    (string) $label,
                    $exception,
                    $failureMessages,
                    $firstFailure
                );
            }
        }
    }

    /** @param array<int, string> $failureMessages */
    private function recordCleanupFailure(
        string $label,
        \Throwable $exception,
        array &$failureMessages,
        ?\Throwable &$firstFailure
    ): void {
        if ($firstFailure === null) {
            $firstFailure = $exception;
        }
        $failureMessages[] = $label . ': ' . $exception->getMessage();
    }

    /** @param array<int, string> $failureMessages */
    private function throwCleanupFailures(
        string $scope,
        array $failureMessages,
        \Throwable $firstFailure = null
    ): void {
        if ($failureMessages === []) {
            return;
        }

        throw new \RuntimeException(
            $scope . ' failed: ' . implode(' | ', $failureMessages),
            0,
            $firstFailure
        );
    }

    /**
     * @param array<int, string> $tables
     * @return array<string, array<string, mixed>>
     */
    private function tableFingerprintSnapshot(array $tables): array
    {
        DB::statement('SET SESSION information_schema_stats_expiry = 0');
        $snapshot = [];
        foreach ($tables as $table) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
                throw new \RuntimeException('Unsafe fixture table identifier: ' . $table);
            }

            $create = DB::selectOne('SHOW CREATE TABLE `' . $table . '`');
            if ($create === null) {
                throw new \RuntimeException('Unable to inspect fixture table ' . $table . '.');
            }
            $createValues = array_values((array) $create);
            $logicalRows = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(static function ($row): array {
                    return (array) $row;
                })
                ->all();
            $autoIncrement = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('AUTO_INCREMENT');

            $snapshot[$table] = [
                'show_create' => (string) ($createValues[1] ?? ''),
                'rows' => (int) DB::table($table)->count(),
                'checksum' => hash('sha256', serialize($logicalRows)),
                'auto_increment' => $autoIncrement === null ? null : (int) $autoIncrement,
            ];
        }

        return $snapshot;
    }

    /** @return array<string, int|null> */
    private function autoIncrementSnapshot(): array
    {
        DB::statement('SET SESSION information_schema_stats_expiry = 0');
        $snapshot = [];
        foreach (['user_logins', 'user_infos'] as $table) {
            $value = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('AUTO_INCREMENT');
            $snapshot[$table] = $value === null ? null : (int) $value;
        }

        return $snapshot;
    }
}
