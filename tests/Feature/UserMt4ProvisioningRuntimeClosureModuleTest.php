<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 02:38
 */

declare(strict_types=1);

/**
 * 用户 MT4 开户 Outbox 与本地协议运行时闭环测试。
 *
 * 文件功能：验证注册写入、加密 payload、处理器状态机、失败日志脱敏和账户响应分类；
 * 项目 MT4 双开关始终保持关闭，只有明确传入内存 Stub 的协议分类用例使用本地授权器，
 * 不建立 socket 或 HTTP 外部连接。
 *
 * 失败边界：生产容器解析的网关仍由 Mt4SyncGate 失败关闭；夹具、缓存、自增值或建议锁
 * 任一恢复失败都会使测试失败，禁止保留 Outbox 或用户数据残留。
 */
namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Contracts\UserMt4ProvisioningGateway;
use App\Jobs\ProcessUserMt4Provisioning;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserMt4ProvisioningOutbox;
use App\Services\JwtService;
use App\Services\Mt4ManagerService;
use App\Services\Registration\Mt4UserProvisioningGateway;
use App\Services\Registration\UserMt4ProvisioningPayload;
use App\Services\Registration\UserMt4ProvisioningProcessor;
use App\Services\Registration\UserMt4ProvisioningResult;
use App\Services\UserRegistrationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Mockery;
use Tests\Support\MySqlFixtureMutex;
use Tests\TestCase;

final class UserMt4ProvisioningRuntimeClosureModuleTest extends TestCase
{
    /**
     * MT4 开户协议验证用的固定 user_id（499940001）。register/reconcile/getAccountInfo 的报文都以它为账号标识，
     * 固定值让请求-响应断言可复现；该 ID 只出现在替身协议中，不写入共享库。
     * @var int
     */
    private const USER_ID = 499940001;
    /**
     * MT4 开户使用的客户组名（demo\retail，含反斜杠转义）。
     * 用于验证报文里 grp 字段与组名的字节级一致，包括特殊字符不被吞掉。
     * @var string
     */
    private const GROUP = 'demo\\retail';

    /**
     * 本用例写入 Cache 的键清单（注册验证码等）。tearDown 逐键 forget，
     * 防止验证码等缓存项泄漏影响后续用例；清理完成后置空。
     * @var array<int, string>
     */
    private $cacheKeys = [];

    /**
     * 夹具注册流程产生的业务用户 ID。tearDown 据此清理 user_logins/user_infos 等关联夹具行。
     * @var int|null
     */
    private $fixtureUserId;

    /**
     * 夹具插入的 user_logins 主键清单。tearDown 按其删除登录行。
     * @var array<int, int>
     */
    private $fixtureLoginIds = [];

    /**
     * 夹具插入的 user_infos 主键清单。tearDown 按其删除用户信息行。
     * @var array<int, int>
     */
    private $fixtureInfoIds = [];

    /**
     * 夹具产生的 MT4 开户 outbox 行主键清单。tearDown 据其清理，防止重复投递到真实网关。
     * @var array<int, int>
     */
    private $fixtureOutboxIds = [];

    /**
     * 夹具写入前各表的 AUTO_INCREMENT 基线（表名 => 自增值或 null）。
     * tearDown 恢复这些值，防止夹具插入抬高共享库自增计数。
     * @var array<string, int|null>
     */
    private $originalAutoIncrements = [];

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
                'MT4 runtime fixture setup failed: ' . implode(' | ', $failures),
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
                    $this->cleanupCreatedProvisioningFixtures($failureMessages, $firstFailure);
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
                    $this->fixtureUserId = null;
                    $this->fixtureLoginIds = [];
                    $this->fixtureInfoIds = [];
                    $this->fixtureOutboxIds = [];
                    $this->originalAutoIncrements = [];
                }
            }
        }

        $this->throwCleanupFailures('MT4 provisioning runtime teardown', $failureMessages, $firstFailure);
    }

    public function test_read_frame_requires_terminator(): void
    {
        $manager = new InspectableProvisioningMt4Manager();

        $this->assertNull($manager->readFrame("act=register&err=0&acc=499940001\r\n"));
    }

    public function test_normalize_fields_rejects_malformed_response(): void
    {
        $manager = new InspectableProvisioningMt4Manager();

        $result = $manager->normalizeFields(['act' => 'register', 'des' => 'OK']);

        $this->assertSame('error', $result['status'] ?? null);
        $this->assertSame('malformed_response', $result['error_code'] ?? null);
    }

    /** @dataProvider unsafeQueryValueProvider */
    public function test_build_query_rejects_unsafe_query_values(string $value): void
    {
        $manager = new InspectableProvisioningMt4Manager();

        $this->expectException(InvalidArgumentException::class);
        $manager->buildQueryForTest('register', ['ctp' => $value]);
    }

    public function unsafeQueryValueProvider(): array
    {
        return [
            'ampersand' => ['secret&grp=evil'],
            'equals' => ['secret=override'],
            'carriage return' => ["secret\rQUIT"],
            'line feed' => ["secret\nQUIT"],
        ];
    }

    public function test_register_validation_rejects_query_injection_password(): void
    {
        $gateway = new RecordingUserMt4ProvisioningGateway(
            [UserMt4ProvisioningResult::rejected('should_not_be_called')]
        );
        $service = new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway));
        $method = new \ReflectionMethod($service, 'validateRegistrationData');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'email' => 'provisioning-query-injection@example.test',
            'password' => 'secret&grp=evil',
            'password_confirmation' => 'secret&grp=evil',
            'user_name' => 'Protocol Safe User',
            'phone' => '86-13999400001',
            'id_card_no' => 'PROVISIONING-PROTOCOL-1',
        ], 1, null);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame([], $gateway->calls);
    }

    public function test_provision_transport_exception_logged_without_secret_leak(): void
    {
        Log::spy();
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('socket.test', 1, 'test-key', '000005', 1);
            }

            public function registerUser($data)
            {
                throw new \Error('password=provider-secret');
            }
        };
        $gateway = $this->localProtocolGateway($manager);

        $result = $gateway->provision(['user_id' => self::USER_ID]);

        $this->assertSame('unknown', $result->status());
        $this->assertSame('transport_exception', $result->errorCode());
        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $message, array $context): bool {
                return $message === 'MT4 provisioning gateway transport exception.'
                    && $context === [
                        'exception_class' => \Error::class,
                        'mode' => 'provision',
                    ]
                    && strpos($message . json_encode($context), 'provider-secret') === false;
            }
        );
    }

    public function test_processor_gateway_exception_logged_without_secret_leak(): void
    {
        Log::spy();
        [, , $outbox] = $this->createProvisioningFixture();
        $gateway = new RecordingUserMt4ProvisioningGateway();
        $gateway->beforeProvision = static function (): void {
            throw new \Error('password=processor-secret');
        };

        $status = (new UserMt4ProvisioningProcessor($gateway))->process((int) $outbox->id);

        $this->assertSame('unknown', $status);
        $this->assertSame('transport_exception', $outbox->fresh()->last_error_code);
        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $message, array $context) use ($outbox): bool {
                return $message === 'MT4 provisioning processor gateway exception.'
                    && $context === [
                        'exception_class' => \Error::class,
                        'outbox_id' => (int) $outbox->id,
                        'mode' => 'provision',
                        'attempt' => 1,
                    ]
                    && strpos($message . json_encode($context), 'processor-secret') === false;
            }
        );
    }

    public function test_get_account_info_maps_legacy_fields(): void
    {
        $manager = new AccountInfoMappingMt4ManagerStub([
            'status' => 'ok',
            'err' => '0',
            'acc' => (string) self::USER_ID,
            'bal' => '0.00',
            'ena' => '1',
            'grp' => self::GROUP,
        ]);

        $result = $manager->getAccountInfo(self::USER_ID);

        $this->assertSame((string) self::USER_ID, (string) ($result['account_id'] ?? ''));
        $this->assertSame('0.00', (string) ($result['balance'] ?? ''));
        $this->assertSame('1', (string) ($result['is_enabled'] ?? ''));
        $this->assertSame(self::GROUP, $result['group'] ?? null);
    }

    /** @dataProvider invalidReconciliationAccountProvider */
    public function test_reconcile_rejects_invalid_account_info(
        array $response,
        string $expectedStatus,
        string $expectedError
    ): void {
        $gateway = $this->localProtocolGateway(new ProvisioningMt4ManagerStub([], [$response]));

        $result = $gateway->reconcile(self::USER_ID, self::GROUP);

        $this->assertSame($expectedStatus, $result->status());
        $this->assertSame($expectedError, $result->errorCode());
    }

    public function invalidReconciliationAccountProvider(): array
    {
        $valid = [
            'status' => 'ok',
            'err' => '0',
            'account_id' => self::USER_ID,
            'balance' => '0.00',
            'is_enabled' => 1,
            'group' => self::GROUP,
        ];

        return [
            'missing account id' => [array_diff_key($valid, ['account_id' => true]), 'unknown', 'malformed_response'],
            'wrong account id' => [array_merge($valid, ['account_id' => self::USER_ID + 1]), 'rejected', 'account_identity_mismatch'],
            'missing balance' => [array_diff_key($valid, ['balance' => true]), 'unknown', 'malformed_response'],
            'disabled account' => [array_merge($valid, ['is_enabled' => 0]), 'rejected', 'account_disabled'],
            'missing group' => [array_diff_key($valid, ['group' => true]), 'unknown', 'malformed_response'],
            'wrong group' => [array_merge($valid, ['group' => 'demo\\wrong']), 'rejected', 'account_group_mismatch'],
        ];
    }

    public function test_process_success_marks_login_and_info_provisioned(): void
    {
        [$login, $info, $outbox] = $this->createProvisioningFixture();
        $userId = (int) $outbox->user_id;
        $processor = new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::processed('MT4-' . $userId),
        ]));

        $status = $processor->process((int) $outbox->id);

        $this->assertSame('processed', $status);
        $this->assertSame(1, (int) $login->fresh()->is_enabled);
        $this->assertSame(1, (int) $info->fresh()->is_mt4_synced);
        $this->assertSame(1, (int) $info->fresh()->is_mt4_enabled);
        $this->assertSame($userId, (int) $info->fresh()->mt4_code);
        $outbox = $outbox->fresh();
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
        $this->assertNull($outbox->available_at);
        $this->assertNotNull($outbox->processed_at);
    }

    public function test_process_retryable_keeps_local_login_enabled(): void
    {
        [$login, $info, $outbox] = $this->createProvisioningFixture([], null, ['is_enabled' => 1]);
        $processor = new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::retryableNotSent('connection_failed'),
        ]));

        $status = $processor->process((int) $outbox->id);

        $outbox = $outbox->fresh();
        $this->assertSame('retryable', $status);
        $this->assertNotNull($outbox->payload_ciphertext);
        $this->assertNotNull($outbox->payload_hash);
        $this->assertTrue($outbox->available_at->isFuture());
        $this->assertSame(1, (int) $login->fresh()->is_enabled);
        $this->assertSame(0, (int) $info->fresh()->is_mt4_synced);
    }

    public function test_process_retryable_exhausts_attempts_marks_manual_review(): void
    {
        [$login, , $outbox] = $this->createProvisioningFixture(
            ['attempts' => 2],
            null,
            ['is_enabled' => 1]
        );
        $processor = new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::retryableNotSent('connection_failed'),
        ]));

        $status = $processor->process((int) $outbox->id);

        $outbox = $outbox->fresh();
        $this->assertSame('manual_reconcile_required', $status);
        $this->assertSame('provision_retry_attempts_exhausted', $outbox->last_error_code);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
        $this->assertNull($outbox->available_at);
        $this->assertNotNull($outbox->processed_at);
        $this->assertSame(1, (int) $login->fresh()->is_enabled);
    }

    public function test_process_expired_payload_marks_manual_review(): void
    {
        [$login, , $outbox] = $this->createProvisioningFixture([], null, ['is_enabled' => 1]);
        DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
            'created_at' => now()->subDay()->timestamp,
        ]);
        $processor = new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::retryableNotSent('connection_failed'),
        ]));

        $status = $processor->process((int) $outbox->id);

        $outbox = $outbox->fresh();
        $this->assertSame('manual_reconcile_required', $status);
        $this->assertSame('provision_payload_expired', $outbox->last_error_code);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
        $this->assertNull($outbox->available_at);
        $this->assertNotNull($outbox->processed_at);
        $this->assertSame(1, (int) $login->fresh()->is_enabled);
    }

    public function test_rejected_and_unknown_results_clear_payload(): void
    {
        [$rejectedLogin, , $rejected] = $this->createProvisioningFixture(
            [],
            null,
            ['is_enabled' => 1]
        );
        $rejectedStatus = (new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::rejected('invalid_group'),
        ])))->process((int) $rejected->id);

        $this->assertSame('rejected', $rejectedStatus);
        $this->assertNull($rejected->fresh()->payload_ciphertext);
        $this->assertNull($rejected->fresh()->payload_hash);
        $this->assertNull($rejected->fresh()->available_at);
        $this->assertNotNull($rejected->fresh()->processed_at);
        $this->assertSame(1, (int) $rejectedLogin->fresh()->is_enabled);

        [$login, $info, $unknown] = $this->createProvisioningFixture(
            [],
            $this->fixtureUserId(1),
            ['is_enabled' => 1]
        );
        $unknownStatus = (new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::unknown('read_timeout'),
        ])))->process((int) $unknown->id);

        $this->assertSame('unknown', $unknownStatus);
        $this->assertNull($unknown->fresh()->payload_ciphertext);
        $this->assertNull($unknown->fresh()->payload_hash);
        $this->assertSame(1, (int) $login->fresh()->is_enabled);
        $this->assertSame(0, (int) $info->fresh()->is_mt4_synced);
        $this->assertNull($unknown->fresh()->processed_at);
        $this->assertTrue($unknown->fresh()->available_at->isFuture());
    }

    public function test_unknown_then_reconcile_success_marks_processed(): void
    {
        [, , $outbox] = $this->createProvisioningFixture();
        $userId = (int) $outbox->user_id;
        $gateway = new RecordingUserMt4ProvisioningGateway(
            [UserMt4ProvisioningResult::unknown('read_timeout')],
            [UserMt4ProvisioningResult::processed('reconciled')]
        );
        $processor = new UserMt4ProvisioningProcessor($gateway);

        $this->assertSame('unknown', $processor->process((int) $outbox->id));
        DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
            'available_at' => now()->subSecond()->timestamp,
        ]);
        $this->assertSame('processed', $processor->process((int) $outbox->id));

        $this->assertSame([
            ['provision', $userId],
            ['reconcile', $userId, self::GROUP],
        ], $gateway->calls);
    }

    public function test_stale_processing_claim_blocks_process(): void
    {
        [, $info, $outbox] = $this->createProvisioningFixture();
        DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
            'status' => 'processing',
            'attempts' => 1,
            'locked_at' => now()->subMinutes(10)->timestamp,
        ]);
        $gateway = new RecordingUserMt4ProvisioningGateway();

        $status = (new UserMt4ProvisioningProcessor($gateway))->process((int) $outbox->id);

        $outbox = $outbox->fresh();
        $this->assertSame('unknown', $status);
        $this->assertSame([], $gateway->calls);
        $this->assertSame('stale_processing_claim', $outbox->last_error_code);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
        $this->assertTrue($outbox->available_at->isFuture());
        $this->assertSame(0, (int) $info->fresh()->is_mt4_synced);
    }

    public function test_stale_unknown_claim_reconciles_after_available(): void
    {
        $userId = $this->fixtureUserId();
        [, , $outbox] = $this->createProvisioningFixture(['status' => 'unknown'], $userId);
        $outbox->payload_ciphertext = null;
        $outbox->status = 'processing';
        $outbox->locked_at = now()->subMinutes(10);
        $outbox->saveOrFail();
        $gateway = new RecordingUserMt4ProvisioningGateway([], [
            UserMt4ProvisioningResult::processed('stale-reconciled'),
        ]);
        $processor = new UserMt4ProvisioningProcessor($gateway);

        $this->assertSame('unknown', $processor->process((int) $outbox->id));
        $this->assertSame([], $gateway->calls);
        DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
            'available_at' => now()->subSecond()->timestamp,
        ]);

        $this->assertSame('processed', $processor->process((int) $outbox->id));
        $this->assertSame([
            ['reconcile', $userId, self::GROUP],
        ], $gateway->calls);
    }

    public function test_reconcile_retryable_failure_counts_toward_manual_review_limit(): void
    {
        [, , $outbox] = $this->createProvisioningFixture(['status' => 'unknown']);
        $outbox->payload_ciphertext = null;
        $outbox->saveOrFail();
        $gateway = new RecordingUserMt4ProvisioningGateway([], [
            UserMt4ProvisioningResult::retryableNotSent('connection_failed'),
            UserMt4ProvisioningResult::retryableNotSent('connection_failed'),
            UserMt4ProvisioningResult::retryableNotSent('connection_failed'),
        ]);
        $processor = new UserMt4ProvisioningProcessor($gateway);

        $statuses = [];
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $statuses[] = $processor->process((int) $outbox->id);
            if ($attempt < 3) {
                DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
                    'available_at' => now()->subSecond()->timestamp,
                ]);
            }
        }

        $outbox = $outbox->fresh();
        $this->assertSame(['unknown', 'unknown', 'manual_reconcile_required'], $statuses);
        $this->assertSame(3, (int) $outbox->reconciliation_attempts);
        $this->assertSame('connection_failed', $outbox->last_error_code);
        $this->assertNull($outbox->available_at);
        $this->assertNotNull($outbox->processed_at);
        $this->assertCount(3, $gateway->calls);
    }

    /**
     * 无法确认创建时间的开户密文必须失败关闭。
     *
     * @dataProvider invalidPayloadCreatedAtProvider
     *
     * @param int|null $createdAt 无效的数据库原始创建时间。
     * @return void 任务转人工且网关零调用时无返回值。
     */
    public function test_invalid_payload_created_at_marks_manual_review_without_gateway_call(?int $createdAt): void
    {
        [, , $outbox] = $this->createProvisioningFixture();
        DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
            'created_at' => $createdAt,
        ]);
        $gateway = new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::processed('must-not-run'),
        ]);

        $status = (new UserMt4ProvisioningProcessor($gateway))->process((int) $outbox->id);

        $outbox = $outbox->fresh();
        $this->assertSame('manual_reconcile_required', $status);
        $this->assertSame('provision_payload_expired', $outbox->last_error_code);
        $this->assertSame([], $gateway->calls);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
    }

    /**
     * 返回所有无法证明开户负载年龄有效的创建时间。
     *
     * @return array<string, array{0: int|null}> 无效创建时间数据集。
     */
    public function invalidPayloadCreatedAtProvider(): array
    {
        return [
            '缺失时间' => [null],
            '零时间戳' => [0],
            '未来时间戳' => [time() + 86400],
        ];
    }

    public function test_reconcile_rejection_marks_manual_review(): void
    {
        [, , $outbox] = $this->createProvisioningFixture(['status' => 'unknown']);
        $outbox->payload_ciphertext = null;
        $outbox->saveOrFail();
        $processor = new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([], [
            UserMt4ProvisioningResult::rejected('invalid_account'),
        ]));

        $status = $processor->process((int) $outbox->id);

        $outbox = $outbox->fresh();
        $this->assertSame('manual_reconcile_required', $status);
        $this->assertSame('invalid_account', $outbox->last_error_code);
        $this->assertNotNull($outbox->processed_at);
        $this->assertNull($outbox->available_at);
    }

    public function test_claim_replaced_during_provision_keeps_processing(): void
    {
        [$login, $info, $outbox] = $this->createProvisioningFixture();
        $gateway = new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::processed('late-success'),
        ]);
        $gateway->beforeProvision = function () use ($outbox): void {
            DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
                'status' => 'processing',
                'attempts' => 2,
                'locked_at' => now()->timestamp,
            ]);
        };
        $processor = new UserMt4ProvisioningProcessor($gateway);

        $status = $processor->process((int) $outbox->id);

        $this->assertSame('processing', $status);
        $this->assertSame(0, (int) $login->fresh()->is_enabled);
        $this->assertSame(0, (int) $info->fresh()->is_mt4_synced);
    }

    /** @dataProvider incompleteLocalSuccessProvider */
    public function test_incomplete_local_success_requires_revalidation(
        int $mt4Enabled,
        bool $matchingMt4Code
    ): void
    {
        $userId = $this->fixtureUserId();
        [, , $outbox] = $this->createProvisioningFixture([], $userId, [
            'is_enabled' => 1,
        ], [
            'is_mt4_synced' => 1,
            'is_mt4_enabled' => $mt4Enabled,
            'mt4_code' => $matchingMt4Code ? $userId : 0,
        ]);
        $gateway = new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::rejected('must_revalidate'),
        ]);

        $status = (new UserMt4ProvisioningProcessor($gateway))->process((int) $outbox->id);

        $this->assertSame('rejected', $status);
        $this->assertSame([['provision', $userId]], $gateway->calls);
    }

    public function incompleteLocalSuccessProvider(): array
    {
        return [
            'MT4 disabled' => [0, true],
            'MT4 code missing' => [1, false],
        ];
    }

    public function test_local_commit_failure_after_external_success_exhausts_reconciliation(): void
    {
        [, $info, $outbox] = $this->createProvisioningFixture();
        $userId = (int) $outbox->user_id;
        $gateway = new RecordingUserMt4ProvisioningGateway(
            [UserMt4ProvisioningResult::processed('external-success')],
            [
                UserMt4ProvisioningResult::processed('external-success'),
                UserMt4ProvisioningResult::processed('external-success'),
            ]
        );
        $corruptIdentity = function () use ($info, $userId): void {
            DB::table('user_infos')->where('id', $info->id)->update([
                'user_id' => $userId + 10000,
            ]);
        };
        $gateway->beforeProvision = $corruptIdentity;
        $gateway->beforeReconcile = $corruptIdentity;
        $processor = new UserMt4ProvisioningProcessor($gateway);

        $statuses = [];
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $statuses[] = $processor->process((int) $outbox->id);
            DB::table('user_infos')->where('id', $info->id)->update(['user_id' => $userId]);
            if ($attempt < 3) {
                DB::table('user_mt4_provisioning_outbox')->where('id', $outbox->id)->update([
                    'available_at' => now()->subSecond()->timestamp,
                ]);
            }
        }

        $outbox = $outbox->fresh();
        $this->assertSame(['unknown', 'unknown', 'manual_reconcile_required'], $statuses);
        $this->assertSame(3, (int) $outbox->reconciliation_attempts);
        $this->assertSame('local_commit_after_external_success_failed', $outbox->last_error_code);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
        $this->assertNull($outbox->available_at);
        $this->assertNotNull($outbox->processed_at);
        $this->assertSame([
            ['provision', $userId],
            ['reconcile', $userId, self::GROUP],
            ['reconcile', $userId, self::GROUP],
        ], $gateway->calls);
    }

    public function test_manual_reconcile_required_result_handled(): void
    {
        [, , $outbox] = $this->createProvisioningFixture();
        $processor = new UserMt4ProvisioningProcessor(new RecordingUserMt4ProvisioningGateway([
            UserMt4ProvisioningResult::manualReconcileRequired('operator_required'),
        ]));

        try {
            $status = $processor->process((int) $outbox->id);
        } catch (\RuntimeException $exception) {
            $this->fail('Manual reconciliation result must be handled without an exception.');
        }

        $outbox = $outbox->fresh();
        $this->assertSame('manual_reconcile_required', $status);
        $this->assertSame('operator_required', $outbox->last_error_code);
        $this->assertNull($outbox->payload_ciphertext);
        $this->assertNull($outbox->payload_hash);
        $this->assertNull($outbox->available_at);
        $this->assertNotNull($outbox->processed_at);
    }

    /**
     * 任一 MT4 开关关闭时，扫描命令必须保持 Outbox 原状且不派发任务。
     *
     * @dataProvider disabledMt4SwitchProvider
     *
     * @param bool $mt4Enabled MT4 连接总开关。
     * @param bool $userSyncEnabled 用户同步业务开关。
     * @return void 零派发且记录状态不变时无返回值。
     */
    public function test_dispatch_command_noops_when_mt4_sync_is_disabled(
        bool $mt4Enabled,
        bool $userSyncEnabled
    ): void {
        [, , $outbox] = $this->createProvisioningFixture();
        config([
            'mt4.enabled' => $mt4Enabled,
            'mt4.user_sync_enabled' => $userSyncEnabled,
        ]);
        Bus::fake();

        $exitCode = Artisan::call('mt4:dispatch-user-provisioning');

        $outbox = $outbox->fresh();
        $this->assertSame(0, $exitCode);
        Bus::assertNotDispatched(ProcessUserMt4Provisioning::class);
        $this->assertSame('pending', $outbox->status);
        $this->assertSame(0, (int) $outbox->attempts);
        $this->assertSame(0, (int) $outbox->reconciliation_attempts);
        $this->assertNull($outbox->locked_at);
        $this->assertNull($outbox->processed_at);
    }

    /**
     * 返回任一远端同步开关关闭的组合。
     *
     * @return array<string, array{0: bool, 1: bool}> 关闭组合数据集。
     */
    public function disabledMt4SwitchProvider(): array
    {
        return [
            '总开关关闭' => [false, true],
            '用户同步关闭' => [true, false],
            '双开关关闭' => [false, false],
        ];
    }

    /**
     * 远端同步关闭时，本地注册 pending 状态必须允许完成登录闭环。
     *
     * @return void 返回成功令牌且不伪报 MT4 同步失败时无返回值。
     */
    public function test_local_registration_pending_status_returns_success_when_mt4_sync_is_disabled(): void
    {
        [$login] = $this->createProvisioningFixture([], null, ['is_enabled' => 1]);
        $userId = (int) $login->user_id;
        $email = (string) $login->email;
        $captchaKey = 'provisioning-local-captcha';
        $captchaCacheKey = 'front_register_captcha_' . sha1($captchaKey);
        $this->cacheKeys = [$captchaCacheKey];
        Cache::put($captchaCacheKey, 'AB12', now()->addMinutes(10));
        config(['mt4.enabled' => false, 'mt4.user_sync_enabled' => false]);
        Mail::fake();

        $registration = Mockery::mock(UserRegistrationService::class);
        $registration->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registration->shouldReceive('register')->once()->andReturn([
            'success' => true,
            'registered' => true,
            'provisioning_status' => 'pending',
            'data' => ['user_id' => $userId],
            'user_login' => $login,
        ]);
        $this->app->instance(UserRegistrationService::class, $registration);
        $jwt = Mockery::mock(JwtService::class);
        $jwt->shouldReceive('generateToken')->once()->andReturn('local-registration-token');
        $this->app->instance(JwtService::class, $jwt);

        $response = $this->postJson('/api/front/auth/register', [
            'email' => $email,
            'password' => 'RegisterPassword1',
            'password_confirmation' => 'RegisterPassword1',
            'user_name' => 'Local Registration User',
            'phone_code' => '86',
            // 手机号必须满足注册接口的 11-20 位数字契约；fixtureUserId 只有 9 位，
            // 直接当手机号会被 min:11 拒绝（VALIDATION_FAILED），故加 139 前缀构造 12 位且保持唯一。
            'phone_number' => '139' . $userId,
            'phone' => '86-139' . $userId,
            'id_card_no' => 'PROVISIONING-LOCAL-' . $userId,
            'gender' => '1',
            'account_type' => 1,
            'captcha_key' => $captchaKey,
            'captcha_code' => 'AB12',
            'agree_terms' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.access_token', 'local-registration-token');
    }

    public function test_local_registration_pending_status_returns_success_when_mt4_sync_is_enabled(): void
    {
        [$login] = $this->createProvisioningFixture([], null, ['is_enabled' => 1]);
        $userId = (int) $login->user_id;
        $email = (string) $login->email;
        $captchaKey = 'provisioning-runtime-captcha';
        $captchaCacheKey = 'front_register_captcha_' . sha1($captchaKey);
        $this->cacheKeys = [$captchaCacheKey];
        Cache::put($captchaCacheKey, 'AB12', now()->addMinutes(10));
        config(['mt4.enabled' => true, 'mt4.user_sync_enabled' => true]);

        $registration = Mockery::mock(UserRegistrationService::class);
        $registration->shouldReceive('validateRegistration')->once()->andReturn([]);
        $registration->shouldReceive('register')->once()->andReturn([
            'success' => true,
            'registered' => true,
            'provisioning_status' => 'pending',
            'user_login' => $login,
        ]);
        $this->app->instance(UserRegistrationService::class, $registration);
        $jwt = Mockery::mock(JwtService::class);
        $jwt->shouldReceive('generateToken')->once()->andReturn('enabled-registration-token');
        $this->app->instance(JwtService::class, $jwt);

        $response = $this->postJson('/api/front/auth/register', [
            'email' => $email,
            'password' => 'RegisterPassword1',
            'password_confirmation' => 'RegisterPassword1',
            'user_name' => 'JWT Gate User',
            'phone_code' => '86',
            // 同上：构造 12 位合法手机号，避免 9 位 fixtureUserId 触发 min:11 校验失败。
            'phone_number' => '139' . $userId,
            'phone' => '86-139' . $userId,
            'id_card_no' => 'PROVISIONING-JWT-' . $userId,
            'gender' => '1',
            'account_type' => 1,
            'captcha_key' => $captchaKey,
            'captcha_code' => 'AB12',
            'agree_terms' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.provisioning_status', 'pending')
            ->assertJsonPath('data.access_token', 'enabled-registration-token');
    }

    public function test_payload_hash_column_nullable(): void
    {
        $column = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'user_mt4_provisioning_outbox')
            ->where('COLUMN_NAME', 'payload_hash')
            ->first();

        $this->assertNotNull($column);
        $this->assertSame('YES', $column->IS_NULLABLE);
    }

    public function test_fixture_cleanup_restores_auto_increments_and_fingerprints(): void
    {
        $this->assertTrue(
            method_exists($this, 'tableFingerprintSnapshot'),
            'Fixture cleanup requires complete table fingerprints.'
        );
        $originalAutoIncrements = $this->autoIncrementSnapshot();
        // 断言与清理必须共用同一份基线，避免两次读取元数据产生不同恢复目标。
        $this->originalAutoIncrements = $originalAutoIncrements;
        $originalTables = $this->tableFingerprintSnapshot([
            'user_logins',
            'user_infos',
            'user_mt4_provisioning_outbox',
        ]);

        [$login, $info, $outbox] = $this->createProvisioningFixture();
        $loginId = (int) $login->id;
        $infoId = (int) $info->id;
        $outboxId = (int) $outbox->id;
        $this->assertTrue(DB::table('user_logins')->where('id', $loginId)->exists());
        $this->assertTrue(DB::table('user_infos')->where('id', $infoId)->exists());
        $this->assertTrue(DB::table('user_mt4_provisioning_outbox')->where('id', $outboxId)->exists());

        $this->cleanupCreatedProvisioningFixtures();
        $this->fixtureOutboxIds = [];
        $this->fixtureInfoIds = [];
        $this->fixtureLoginIds = [];

        $this->assertFalse(DB::table('user_logins')->where('id', $loginId)->exists());
        $this->assertFalse(DB::table('user_infos')->where('id', $infoId)->exists());
        $this->assertFalse(DB::table('user_mt4_provisioning_outbox')->where('id', $outboxId)->exists());
        $this->assertSame($originalAutoIncrements, $this->autoIncrementSnapshot());
        $this->assertSame($originalTables, $this->tableFingerprintSnapshot([
            'user_logins',
            'user_infos',
            'user_mt4_provisioning_outbox',
        ]));
    }

    public function test_run_cleanup_steps_collects_all_failures(): void
    {
        $this->assertTrue(method_exists($this, 'runCleanupSteps'));

        $attempted = [];
        $first = new \DomainException('first runtime cleanup failure');
        $failure = null;
        try {
            $this->runCleanupSteps('runtime cleanup', [
                'first' => function () use (&$attempted, $first): void {
                    $attempted[] = 'first';
                    throw $first;
                },
                'second' => function () use (&$attempted): void {
                    $attempted[] = 'second';
                },
                'third' => function () use (&$attempted): void {
                    $attempted[] = 'third';
                    throw new \RuntimeException('second runtime cleanup failure');
                },
            ]);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        $this->assertSame(['first', 'second', 'third'], $attempted);
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame($first, $failure->getPrevious());
        $this->assertStringContainsString('first runtime cleanup failure', $failure->getMessage());
        $this->assertStringContainsString('second runtime cleanup failure', $failure->getMessage());
    }

    public function test_teardown_cleans_cache_before_database(): void
    {
        $reflection = new \ReflectionMethod(self::class, 'tearDown');
        $lines = file($reflection->getFileName());
        $source = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));

        $this->assertMatchesRegularExpression(
            '/try\s*\{\s*try\s*\{.*cleanupCacheKeys.*\}\s*finally\s*\{.*cleanupCreatedProvisioningFixtures/s',
            $source
        );
    }

    public function test_setup_releases_mutex_after_database_cleanup(): void
    {
        $this->assertTrue(
            property_exists($this, 'fixtureMutex'),
            'Runtime fixtures must own a MySqlFixtureMutex.'
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
            strpos($tearDown, 'cleanupCreatedProvisioningFixtures') < strpos($tearDown, '->releaseWithDisconnectFallback()'),
            'The runtime mutex must be released after DB cleanup and AUTO_INCREMENT restore.'
        );
    }

    /**
     * @param array<string, mixed> $outboxOverrides
     * @param array<string, mixed> $loginOverrides
     * @param array<string, mixed> $infoOverrides
     * @return array{0: UserLogin, 1: UserInfo, 2: UserMt4ProvisioningOutbox}
     */
    private function createProvisioningFixture(
        array $outboxOverrides = [],
        int $userId = null,
        array $loginOverrides = [],
        array $infoOverrides = []
    ): array {
        $userId = $userId ?? $this->fixtureUserId();
        $this->rememberAutoIncrement('user_logins');
        $this->rememberAutoIncrement('user_infos');
        $this->rememberAutoIncrement('user_mt4_provisioning_outbox');
        $login = UserLogin::create(array_merge([
            'user_id' => $userId,
            'email' => 'provisioning-' . $userId . '@example.test',
            'password' => Hash::make('register-password'),
            'account_type' => 1,
            'is_enabled' => 0,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '127.0.0.1',
        ], $loginOverrides));
        $this->fixtureLoginIds[] = (int) $login->id;
        $info = UserInfo::create(array_merge([
            'user_id' => $userId,
            'login_id' => $login->id,
            'user_name' => 'Provisioning Runtime User',
            'phone' => '86-' . $userId,
            'gender' => 1,
            'level_id' => 0,
            'group_id' => 0,
            'parent_id' => 0,
            'account_type' => 1,
            'family_tree' => (string) $userId,
            'comm_rate' => 0,
            'leverage' => 100,
            'is_mt4_synced' => 0,
            'is_mt4_enabled' => 0,
            'is_agent_confirmed' => 1,
            'original_group' => self::GROUP,
            'mt4_group' => self::GROUP,
            'mt4_code' => 0,
            'country' => 'CN',
            'data_source' => 0,
            'created_by' => 0,
            'updated_by' => 0,
        ], $infoOverrides));
        $this->fixtureInfoIds[] = (int) $info->id;
        $secured = UserMt4ProvisioningPayload::encrypt([
            'user_id' => $userId,
            'name' => (string) $info->user_name,
            'user_name' => (string) $info->user_name,
            'password' => 'register-password',
            'email' => (string) $login->email,
            'phone' => (string) $info->phone,
            'id_card' => 'PROVISIONING-ID-' . $userId,
            'parent_id' => 0,
            'group' => self::GROUP,
            'country' => 'CN',
            'leverage' => 100,
        ]);
        $outbox = UserMt4ProvisioningOutbox::create(array_merge([
            'user_login_id' => $login->id,
            'user_info_id' => $info->id,
            'user_id' => $userId,
            'status' => 'pending',
            'attempts' => 0,
            'reconciliation_attempts' => 0,
            'payload_ciphertext' => $secured['ciphertext'],
            'payload_hash' => $secured['hash'],
            'available_at' => now()->subSecond(),
        ], $outboxOverrides));
        $this->fixtureOutboxIds[] = (int) $outbox->id;

        return [$login, $info, $outbox];
    }

    private function fixtureUserId(int $offset = 0): int
    {
        if ($this->fixtureUserId === null) {
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $candidate = random_int(480000000, 489999998);
                if (!UserLogin::whereIn('user_id', [$candidate, $candidate + 1])->exists()
                    && !UserInfo::whereIn('user_id', [$candidate, $candidate + 1])->exists()
                    && !UserMt4ProvisioningOutbox::whereIn('user_id', [$candidate, $candidate + 1])->exists()) {
                    $this->fixtureUserId = $candidate;
                    break;
                }
            }
        }
        if ($this->fixtureUserId === null) {
            throw new \RuntimeException('Unable to reserve an isolated MT4 provisioning test identity.');
        }

        return $this->fixtureUserId + $offset;
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
            $this->throwCleanupFailures('MT4 provisioning cache cleanup', $failureMessages, $firstFailure);
        }
    }

    private function cleanupCreatedProvisioningFixtures(
        ?array &$failureMessages = null,
        ?\Throwable &$firstFailure = null
    ): void {
        $throwOnFailure = $failureMessages === null;
        if ($failureMessages === null) {
            $failureMessages = [];
        }

        $steps = [];
        if ($this->fixtureOutboxIds !== []) {
            $steps['delete provisioning outbox rows'] = function (): void {
                DB::table('user_mt4_provisioning_outbox')
                    ->whereIn('id', $this->fixtureOutboxIds)
                    ->delete();
            };
        }
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
                'MT4 provisioning database cleanup',
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
            $checksum = DB::selectOne('CHECKSUM TABLE `' . $table . '`');
            if ($checksum === null) {
                throw new \RuntimeException('Unable to checksum fixture table ' . $table . '.');
            }
            $checksumValues = array_values((array) $checksum);
            $autoIncrement = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('AUTO_INCREMENT');

            $snapshot[$table] = [
                'show_create' => (string) ($createValues[1] ?? ''),
                'rows' => (int) DB::table($table)->count(),
                'checksum' => (string) ($checksumValues[1] ?? ''),
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
        foreach (['user_logins', 'user_infos', 'user_mt4_provisioning_outbox'] as $table) {
            $value = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('AUTO_INCREMENT');
            $snapshot[$table] = $value === null ? null : (int) $value;
        }

        return $snapshot;
    }

    /**
     * 为内存 MT4 Stub 创建显式本地协议网关。
     *
     * 项目级同步开关启动时保持关闭；当前测试在确认 Manager 为内存 Stub 后，
     * 仅于本测试应用实例内临时打开门禁，应用销毁后配置随容器一并释放。
     *
     * @param Mt4ManagerService $manager 当前测试拥有且不会建立外部连接的 Manager Stub。
     * @return Mt4UserProvisioningGateway 本地协议分类网关。
     */
    private function localProtocolGateway(Mt4ManagerService $manager): Mt4UserProvisioningGateway
    {
        $this->assertFalse(
            (bool) config('mt4.user_sync_enabled', false),
            '本地协议测试不得开启 MT4_USER_SYNC_ENABLED。'
        );

        config(['mt4.user_sync_enabled' => true]);
        $this->assertTrue((bool) config('mt4.user_sync_enabled'));

        return new Mt4UserProvisioningGateway($manager);
    }
}

final class RecordingUserMt4ProvisioningGateway implements UserMt4ProvisioningGateway
{
    /**
     * provision() 的预设结果队列，逐次弹出；为空时默认返回成功结果。
     * 用例借它构造开户失败、部分成功等分支。
     * @var array<int, UserMt4ProvisioningResult>
     */
    private $provisionResults;

    /**
     * reconcile() 的预设结果队列，逐次弹出；为空时默认返回成功结果，驱动对账分支。
     * @var array<int, UserMt4ProvisioningResult>
     */
    private $reconciliationResults;

    /**
     * 替身收到的每次调用记录（方法名与参数）。断言开户/对账的调用次数与入参完全符合预期。
     * @var array<int, array<int, mixed>>
     */
    public $calls = [];

    /**
     * provision() 执行前注入的回调钩子。用例借它在关键时点修改数据库或抛异常，构造并发/失败场景。
     * @var callable|null
     */
    public $beforeProvision;

    /**
     * reconcile() 执行前注入的回调钩子，用途同 beforeProvision，作用于对账路径。
     * @var callable|null
     */
    public $beforeReconcile;

    /**
     * @param array<int, UserMt4ProvisioningResult> $provisionResults
     * @param array<int, UserMt4ProvisioningResult> $reconciliationResults
     */
    public function __construct(array $provisionResults = [], array $reconciliationResults = [])
    {
        $this->provisionResults = $provisionResults;
        $this->reconciliationResults = $reconciliationResults;
    }

    public function provision(array $payload): UserMt4ProvisioningResult
    {
        $this->calls[] = ['provision', (int) ($payload['user_id'] ?? 0)];
        if ($this->beforeProvision !== null) {
            ($this->beforeProvision)();
        }

        return array_shift($this->provisionResults)
            ?: UserMt4ProvisioningResult::rejected('unexpected_provision_call');
    }

    public function reconcile(int $userId, string $expectedGroup = null): UserMt4ProvisioningResult
    {
        $this->calls[] = ['reconcile', $userId, $expectedGroup];
        if ($this->beforeReconcile !== null) {
            ($this->beforeReconcile)();
        }

        return array_shift($this->reconciliationResults)
            ?: UserMt4ProvisioningResult::rejected('unexpected_reconcile_call');
    }
}

final class ProvisioningMt4ManagerStub extends Mt4ManagerService
{
    /**
     * register 命令的预设 MT4 报文队列，逐次弹出。驱动开户链路对 MT4 响应的各种成功/失败形态。
     * @var array<int, array<string, mixed>>
     */
    private $registerResponses;

    /**
     * getAccountInfo 命令的预设 MT4 报文队列，逐次弹出。驱动对账时读取账户信息的响应形态。
     * @var array<int, array<string, mixed>>
     */
    private $accountInfoResponses;

    /**
     * @param array<int, array<string, mixed>> $registerResponses
     * @param array<int, array<string, mixed>> $accountInfoResponses
     */
    public function __construct(array $registerResponses = [], array $accountInfoResponses = [])
    {
        parent::__construct('socket.test', 1, 'test-key', '000005', 1);
        $this->registerResponses = $registerResponses;
        $this->accountInfoResponses = $accountInfoResponses;
    }

    public function registerUser($data)
    {
        return array_shift($this->registerResponses) ?: ['status' => 'error', 'error_code' => 'unexpected_call'];
    }

    public function getAccountInfo($userId)
    {
        return array_shift($this->accountInfoResponses) ?: ['status' => 'error', 'error_code' => 'unexpected_call'];
    }
}

final class AccountInfoMappingMt4ManagerStub extends Mt4ManagerService
{
    /**
     * 预设的 getAccountInfo 返回报文。验证报文字段（acc/grp 等）到账户信息结构的映射规则。
     * @var array<string, mixed>
     */
    private $response;

    /** @param array<string, mixed> $response */
    public function __construct(array $response)
    {
        parent::__construct('socket.test', 1, 'test-key', '000005', 1);
        $this->response = $response;
    }

    protected function sendCommand($act, $params = [])
    {
        return $this->response;
    }
}

final class InspectableProvisioningMt4Manager extends Mt4ManagerService
{
    public function __construct()
    {
        parent::__construct('socket.test', 1, 'test-key', '000005', 0);
    }

    public function readFrame(string $frame): ?string
    {
        $this->socket = fopen('php://temp', 'r+');
        fwrite($this->socket, $frame);
        rewind($this->socket);

        return $this->readUntilEnd();
    }

    /** @param array<string, string> $fields @return array<string, mixed> */
    public function normalizeFields(array $fields): array
    {
        return $this->normalizeLegacyResult($fields);
    }

    /** @param array<string, mixed> $params */
    public function buildQueryForTest(string $act, array $params): string
    {
        return $this->buildQuery($act, $params);
    }
}
