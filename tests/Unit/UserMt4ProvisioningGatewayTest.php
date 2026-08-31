<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:05
 */

declare(strict_types=1);

/**
 * 用户 MT4 开通网关单元测试。
 *
 * 文件功能：
 * - 校验 Mt4UserProvisioningGateway 的 provision/reconcile 对 MT4 注册与账户信息响应的分类（processed / rejected / retryable_not_sent / unknown）。
 * - 校验传输异常、协议值非法、账号身份不合法、响应畸形等失败场景的 fail-closed 行为。
 *
 * 适用场景：
 * - 改动 MT4 用户开通网关的响应分类、账号对账或错误码映射后回归。
 *
 * 入参例子：
 * - provision($payload) 入参含 user_id、user_name、password、email、group=demo\\retail 等注册字段；
 * - 响应示例：['status' => 'ok', 'err' => '0', 'acc' => '412300001'] => processed；
 * - reconcile(412300001, 'demo\\retail') 对账账户信息。
 *
 * 返回值：断言通过表示状态、错误码、提供方引用与调用次数完全符合预期。
 *
 * 异常或失败场景：
 * - 传输异常未归为 unknown、畸形响应被误判成功、重复注册/对账调用、空错误码未被拒绝时失败。
 */
namespace Tests\Unit;

use App\Services\Mt4ManagerService;
use App\Services\Mt4SyncGate;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserMt4ProvisioningGatewayTest extends TestCase
{
    /** @var Container 测试开始前的进程级容器，结束后必须原样恢复。 */
    private $previousContainer;

    /**
     * 为纯内存 Manager 测试安装最小配置容器。
     *
     * 这里只打开同步门禁，不注册网络服务；所有测试 Manager 均为当前文件内的内存替身，
     * 因而能够验证网关编排与响应分类，同时不会触发真实 MT4 连接。
     *
     * @return void 配置容器安装完成时无返回值。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository([
            'mt4' => ['user_sync_enabled' => true],
        ]));
        Container::setInstance($container);
    }

    /**
     * 恢复进程级容器，避免本文件的本地授权配置污染后续 Unit。
     *
     * @return void 容器恢复完成时无返回值。
     */
    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    /**
     * 校验结果对象暴露显式终态与重试状态。
     *
     * @return void 断言通过不返回值。
     */
    public function test_result_exposes_terminal_and_retry_states(): void
    {
        $resultClass = $this->resultClass();

        $this->assertSame('processed', $resultClass::processed()->status());
        $this->assertNull($resultClass::processed()->errorCode());
        $this->assertSame('retryable_not_sent', $resultClass::retryableNotSent('connection_failed')->status());
        $this->assertSame('unknown', $resultClass::unknown('read_timeout')->status());
        $this->assertSame('rejected', $resultClass::rejected('provider_rejected')->status());
    }

    /**
     * 校验失败结果拒绝空错误码。
     *
     * @return void 断言通过不返回值。
     */
    public function test_failure_rejects_empty_error_code(): void
    {
        $resultClass = $this->resultClass();

        $this->expectException(InvalidArgumentException::class);
        $resultClass::unknown('   ');
    }

    /**
     * 校验协议分类单测只通过显式本地配置进入假 Manager，不依赖残留 Laravel 容器。
     *
     * @return void 门禁获准且开户结果正确时无返回值。
     */
    public function test_local_fake_manager_uses_isolated_test_configuration(): void
    {
        $gateway = $this->newGateway($this->managerReturning([
            'status' => 'ok',
            'err' => '0',
            'acc' => '412300001',
        ]));

        $result = $gateway->provision($this->payload());

        $this->assertTrue(Mt4SyncGate::userSyncEnabled());
        $this->assertSame('processed', $result->status());
    }

    /**
     * @dataProvider registerResponseProvider
     * 校验 provision 分类 MT4 注册响应且不额外查询账户信息。
     *
     * @param array $response MT4 注册响应。
     * @param string $status 期望状态。
     * @param string|null $errorCode 期望错误码，可为 null。
     * @return void 断言通过不返回值。
     */
    public function test_provision_classifies_register_response_without_account_info(array $response, string $status, string $errorCode = null): void
    {
        $manager = $this->managerReturning($response);
        $gateway = $this->newGateway($manager);

        $result = $gateway->provision($this->payload());

        $this->assertSame($status, $result->status());
        $this->assertSame($errorCode, $result->errorCode());
        $this->assertSame(1, $manager->registerCalls);
        $this->assertSame(0, $manager->accountInfoCalls);
        $this->assertSame($this->payload(), $manager->lastRegisterPayload);
    }

    /**
     * 校验注册期间传输异常归类为 unknown。
     *
     * @return void 断言通过不返回值。
     */
    public function test_transport_exception_during_registration_is_unknown(): void
    {
        $manager = new class extends Mt4ManagerService {
            /**
             * registerUser 的调用计数。断言传输异常场景下网关只调用一次注册、
             * 不在 unknown 状态下重复下发开户指令。
             *
             * @var int
             */
            public $registerCalls = 0;

            /**
             * getAccountInfo 的调用计数。provision 阶段应为 0（对账是独立模式），
             * 大于 0 即说明开户与对账流程被混用。
             *
             * @var int
             */
            public $accountInfoCalls = 0;

            public function __construct()
            {
            }

            public function registerUser($data)
            {
                $this->registerCalls++;
                throw new RuntimeException('socket interrupted');
            }

            public function getAccountInfo($userId)
            {
                $this->accountInfoCalls++;
                return ['status' => 'error', 'error_code' => 'unexpected_call'];
            }
        };

        $result = $this->newGateway($manager)->provision($this->payload());

        $this->assertSame('unknown', $result->status());
        $this->assertSame('transport_exception', $result->errorCode());
        $this->assertSame(1, $manager->registerCalls);
        $this->assertSame(0, $manager->accountInfoCalls);
    }

    /**
     * 校验传输异常通过显式本地日志接收器记录脱敏上下文，不访问残留的 Laravel Log 容器。
     *
     * @return void 日志级别、消息和上下文符合约定时无返回值。
     */
    public function test_transport_exception_uses_explicit_local_log_receiver(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            public function registerUser($data)
            {
                throw new RuntimeException('socket payload must not reach logs');
            }
        };
        $logs = [];
        $gateway = $this->newGateway(
            $manager,
            static function (string $level, string $message, array $context) use (&$logs): void {
                $logs[] = compact('level', 'message', 'context');
            }
        );

        $result = $gateway->provision($this->payload());

        $this->assertSame('unknown', $result->status());
        $this->assertSame('transport_exception', $result->errorCode());
        $this->assertSame([[
            'level' => 'error',
            'message' => 'MT4 provisioning gateway transport exception.',
            'context' => [
                'exception_class' => RuntimeException::class,
                'mode' => 'provision',
            ],
        ]], $logs);
        $this->assertStringNotContainsString('payload', json_encode($logs, JSON_THROW_ON_ERROR));
    }

    /**
     * 校验协议分隔符等非法协议值在对账前被拒绝。
     *
     * @return void 断言通过不返回值。
     */
    public function test_invalid_protocol_value_rejected_before_reconcile(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            public function registerUser($data)
            {
                throw new InvalidArgumentException('MT4 command value contains a protocol delimiter.');
            }
        };

        $payload = $this->payload();
        $payload['group'] = 'demo&grp=evil';
        $result = $this->newGateway($manager)->provision($payload);

        $this->assertSame('rejected', $result->status());
        $this->assertSame('invalid_protocol_value', $result->errorCode());
    }

    /**
     * 校验注册身份校验通过时保留提供方引用。
     *
     * @return void 断言通过不返回值。
     */
    public function test_identity_verified_preserves_provider_reference(): void
    {
        $manager = $this->managerReturning([
            'status' => 'ok',
            'err' => '0',
            'acc' => '412300001',
            'ticket' => 'REGISTER-412300001',
        ]);

        $result = $this->newGateway($manager)->provision($this->payload());

        $this->assertSame('processed', $result->status());
        $this->assertSame('REGISTER-412300001', $result->providerReference());
    }

    /**
     * 校验未知状态对账仅查询账户信息、不重复注册。
     *
     * @return void 断言通过不返回值。
     */
    public function test_unknown_status_reconcile_queries_account_info_only(): void
    {
        $manager = $this->managerReturning(
            ['status' => 'error', 'error_code' => 'unexpected_register'],
            [
                'status' => 'ok',
                'err' => '0',
                'account_id' => 412300001,
                'balance' => '0.00',
                'is_enabled' => 1,
                'group' => 'demo\\retail',
            ]
        );
        $gateway = $this->newGateway($manager);

        $result = $gateway->reconcile(412300001, 'demo\\retail');

        $this->assertSame('processed', $result->status());
        $this->assertSame(0, $manager->registerCalls);
        $this->assertSame(1, $manager->accountInfoCalls);
        $this->assertSame(412300001, $manager->lastAccountInfoUserId);
    }

    /**
     * @dataProvider nonPositiveReconciliationUserIdProvider
     * 校验对账拒绝非正数用户 ID 且不查询管理器。
     *
     * @param int $userId 用户 ID。
     * @return void 断言通过不返回值。
     */
    public function test_reconcile_rejects_non_positive_user_id(int $userId): void
    {
        $manager = $this->managerReturning(
            ['status' => 'error', 'error_code' => 'unexpected_register'],
            [
                'status' => 'ok',
                'err' => '0',
                'account_id' => $userId,
                'balance' => '0.00',
                'is_enabled' => 1,
                'group' => 'demo\\retail',
            ]
        );

        $result = $this->newGateway($manager)->reconcile($userId, 'demo\\retail');

        $this->assertSame('rejected', $result->status());
        $this->assertSame('account_identity_invalid', $result->errorCode());
        $this->assertSame(0, $manager->accountInfoCalls);
    }

    public function nonPositiveReconciliationUserIdProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    /**
     * 校验账户信息映射不臆造缺失的余额字段。
     *
     * @return void 断言通过不返回值。
     */
    public function test_account_info_mapping_does_not_invent_balance(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            public function registerUser($data)
            {
                return $this->getAccountInfo($data);
            }

            public function getAccountInfo($userId)
            {
                return [
                    'status' => 'ok',
                    'err' => '0',
                    'acc' => '412300001',
                    'ena' => '1',
                    'grp' => 'demo\\retail',
                ];
            }
        };

        $result = $this->newGateway($manager)->reconcile(412300001, 'demo\\retail');

        $this->assertSame('unknown', $result->status());
        $this->assertSame('malformed_response', $result->errorCode());
    }

    /**
     * @dataProvider reconciliationResponseProvider
     * 校验对账分类账户信息响应且不重发注册命令。
     *
     * @param array $response 账户信息响应。
     * @param string $status 期望状态。
     * @param string|null $errorCode 期望错误码，可为 null。
     * @return void 断言通过不返回值。
     */
    public function test_reconcile_classifies_account_info_response_without_register(
        array $response,
        string $status,
        string $errorCode = null
    ): void {
        $manager = $this->managerReturning(['status' => 'error'], $response);

        $result = $this->newGateway($manager)->reconcile(412300002, 'demo\\retail');

        $this->assertSame($status, $result->status());
        $this->assertSame($errorCode, $result->errorCode());
        $this->assertSame(0, $manager->registerCalls);
        $this->assertSame(1, $manager->accountInfoCalls);
    }

    public function registerResponseProvider(): array
    {
        return [
            'accepted' => [[
                'status' => 'ok',
                'err' => '0',
                'acc' => '412300001',
            ], 'processed', null],
            'missing account identity' => [[
                'status' => 'ok',
                'err' => '0',
            ], 'unknown', 'malformed_response'],
            'wrong account identity' => [[
                'status' => 'ok',
                'err' => '0',
                'acc' => '412300099',
            ], 'rejected', 'account_identity_mismatch'],
            'not sent' => [['status' => 'error', 'error_code' => 'connection_failed'], 'retryable_not_sent', 'connection_failed'],
            'write uncertain' => [['status' => 'error', 'error_code' => 'write_failed'], 'unknown', 'write_failed'],
            'read uncertain' => [['status' => 'error', 'error_code' => 'read_timeout'], 'unknown', 'read_timeout'],
            'malformed response' => [['status' => 'error', 'error_code' => 'malformed_response'], 'unknown', 'malformed_response'],
            'provider rejected' => [['status' => 'error', 'error_code' => 'invalid_group'], 'rejected', 'invalid_group'],
            'oversized error code' => [[
                'status' => 'error',
                'error_code' => str_repeat('e', 101),
            ], 'unknown', 'malformed_response'],
            'oversized provider reference' => [[
                'status' => 'ok',
                'err' => '0',
                'acc' => '412300001',
                'ticket' => str_repeat('T', 101),
            ], 'unknown', 'malformed_response'],
            'contradictory provider success' => [['status' => 'ok', 'err' => '13'], 'unknown', 'malformed_response'],
            'missing status' => [['err' => '0'], 'unknown', 'malformed_response'],
            'invalid status' => [['status' => 'sometimes'], 'unknown', 'malformed_response'],
        ];
    }

    public function reconciliationResponseProvider(): array
    {
        return [
            'account exists' => [[
                'status' => 'ok',
                'err' => '0',
                'account_id' => 412300002,
                'balance' => '0.00',
                'is_enabled' => 1,
                'group' => 'demo\\retail',
            ], 'processed', null],
            'connection failure' => [['status' => 'error', 'error_code' => 'connection_failed'], 'retryable_not_sent', 'connection_failed'],
            'read uncertainty' => [['status' => 'error', 'error_code' => 'read_timeout'], 'unknown', 'read_timeout'],
            'malformed response' => [['status' => 'error', 'error_code' => 'malformed_response'], 'unknown', 'malformed_response'],
            'explicit missing account' => [['status' => 'error', 'error_code' => 'invalid_account'], 'rejected', 'invalid_account'],
            'missing balance' => [[
                'status' => 'ok',
                'err' => '0',
                'account_id' => 412300002,
                'is_enabled' => 1,
                'group' => 'demo\\retail',
            ], 'unknown', 'malformed_response'],
            'non scalar balance' => [[
                'status' => 'ok',
                'err' => '0',
                'account_id' => 412300002,
                'balance' => ['0.00'],
                'is_enabled' => 1,
                'group' => 'demo\\retail',
            ], 'unknown', 'malformed_response'],
            'scientific balance' => [[
                'status' => 'ok',
                'err' => '0',
                'account_id' => 412300002,
                'balance' => '1e3',
                'is_enabled' => 1,
                'group' => 'demo\\retail',
            ], 'unknown', 'malformed_response'],
            'malformed response' => [['balance' => '0.00'], 'unknown', 'malformed_response'],
        ];
    }

    private function resultClass(): string
    {
        $class = 'App\\Services\\Registration\\UserMt4ProvisioningResult';
        $this->assertTrue(class_exists($class), $class . ' must define the provisioning state contract.');

        return $class;
    }

    /**
     * 创建只调用内存假 Manager 的网关。
     *
     * 测试级最小容器只在 setUp/tearDown 之间开启门禁；Manager 始终为内存替身，
     * 不会建立 socket、HTTP 或其他外部连接。
     *
     * @param Mt4ManagerService $manager 当前测试拥有的内存假 Manager。
     * @param callable|null $logReceiver 本地结构化日志接收器。
     * @return object 实现 UserMt4ProvisioningGateway 契约的网关。
     */
    private function newGateway(
        Mt4ManagerService $manager,
        callable $logReceiver = null
    )
    {
        $contract = 'App\\Contracts\\UserMt4ProvisioningGateway';
        $gatewayClass = 'App\\Services\\Registration\\Mt4UserProvisioningGateway';
        $this->assertTrue(interface_exists($contract), $contract . ' must be defined.');
        $this->assertTrue(class_exists($gatewayClass), $gatewayClass . ' must wrap Mt4ManagerService.');

        $gateway = new $gatewayClass(
            $manager,
            $logReceiver ?? static function (): void {
                // 假 Manager 的日志在内存中终止，避免依赖 PHPUnit 进程内的 Laravel 全局容器。
            }
        );
        $this->assertInstanceOf($contract, $gateway);

        return $gateway;
    }

    private function managerReturning(array $registerResponse, array $accountInfoResponse = null): Mt4ManagerService
    {
        return new class($registerResponse, $accountInfoResponse) extends Mt4ManagerService {
            /**
             * registerUser 调用计数。断言开户只下发一次、重试语义由处理器而非 Manager 承担。
             *
             * @var int
             */
            public $registerCalls = 0;

            /**
             * getAccountInfo 调用计数。对账模式按它确认确实执行了账户信息查询。
             *
             * @var int
             */
            public $accountInfoCalls = 0;

            /**
             * 最近一次 registerUser 收到的注册载荷。断言网关把身份字段原样传给 MT4、未做静默改写。
             *
             * @var array<string, mixed>|null
             */
            public $lastRegisterPayload;

            /**
             * 最近一次 getAccountInfo 收到的用户 ID。用于断言对账查询指向正确的 MT4 登录号。
             *
             * @var int|string|null
             */
            public $lastAccountInfoUserId;

            /**
             * 预置的 registerUser 响应。每次注册调用返回它，使开户结果分类完全可复现。
             *
             * @var array<string, mixed>
             */
            private $registerResponse;

            /**
             * 预置的 getAccountInfo 响应。未显式传入时默认返回 error（unexpected_call），
             * 让“不该发生的对账调用”在断言中显性暴露。
             *
             * @var array<string, mixed>|null
             */
            private $accountInfoResponse;

            public function __construct(array $registerResponse, array $accountInfoResponse = null)
            {
                $this->registerResponse = $registerResponse;
                $this->accountInfoResponse = $accountInfoResponse ?: ['status' => 'error', 'error_code' => 'unexpected_call'];
            }

            public function registerUser($data)
            {
                $this->registerCalls++;
                $this->lastRegisterPayload = $data;

                return $this->registerResponse;
            }

            public function getAccountInfo($userId)
            {
                $this->accountInfoCalls++;
                $this->lastAccountInfoUserId = $userId;

                return $this->accountInfoResponse;
            }
        };
    }

    private function payload(): array
    {
        return [
            'user_id' => 412300001,
            'user_name' => 'Provisioning User',
            'password' => 'plain-text-only-for-mt4',
            'email' => 'provisioning@example.test',
            'phone' => '86-13900000001',
            'id_card_no' => 'PROVISIONING-ID',
            'parent_id' => 0,
            'group' => 'demo\\retail',
            'country' => 'CN',
            'leverage' => 100,
        ];
    }
}
