<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:45
 */

/**
 * Mt4CommissionTransferGatewayClosureModuleTest
 *
 * 文件功能：
 * - 验证 MT4 佣金划转网关契约：旧 verify 动作携带源账户与资金密码、账户信息保留旧 free_margin 别名、无显式 err 的成功不算成功、快照仅确认匹配的纯 decimal 余额、读写失败分类。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CommissionTransfer\CommissionTransferAccountSnapshotResult;
use App\Services\CommissionTransfer\Mt4CommissionTransferAccountSnapshotGateway;
use App\Services\CommissionTransfer\Mt4TradePasswordGateway;
use App\Services\Mt4ManagerService;
use Tests\TestCase;

final class Mt4CommissionTransferGatewayClosureModuleTest extends TestCase
{
    /**
     * 为内存 Manager 与网关映射测试显式开启进程内授权；测试不会连接外部 MT4。
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mt4.enabled' => true,
            'mt4.user_sync_enabled' => true,
        ]);
    }

    public function test_manager_sends_legacy_verify_action_with_source_account_and_transaction_password(): void
    {
        $manager = new class extends Mt4ManagerService {
            /**
             * sendCommand 捕获的 [动作, 参数] 报文。断言 legacy verify 指令携带源账号与交易密码的字节级内容。
             * @var array{0: string, 1: array<string, mixed>}
             */
            public $call;

            public function __construct()
            {
            }

            protected function sendCommand($act, $params = [])
            {
                $this->call = [$act, $params];

                return ['status' => 'ok', 'err' => '0', 'acc' => '41001'];
            }
        };

        $result = $manager->verifyPassword(41001, 'trade-secret');

        $this->assertSame([
            'verify',
            ['acc' => 41001, 'ctp' => 'trade-secret'],
        ], $manager->call);
        $this->assertSame('ok', $result['status']);
    }

    public function test_manager_account_info_preserves_account_id_and_legacy_free_margin_alias(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            protected function sendCommand($act, $params = [])
            {
                return [
                    'status' => 'ok',
                    'err' => '0',
                    'acc' => '41001',
                    'bal' => '125.50',
                    'fmg' => '120.00',
                ];
            }
        };

        $result = $manager->getAccountInfo(41001);

        $this->assertSame('41001', (string) ($result['account_id'] ?? ''));
        $this->assertSame('125.50', (string) ($result['balance'] ?? ''));
        $this->assertSame('120.00', (string) ($result['free_margin'] ?? ''));
    }

    public function test_manager_does_not_treat_success_without_explicit_err_as_success(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            protected function sendCommand($act, $params = [])
            {
                return ['status' => 'ok', 'acc' => '41001', 'bal' => '125.50'];
            }
        };

        $result = $manager->getAccountInfo(41001);

        $this->assertSame('error', $result['status'] ?? null);
        $this->assertSame('malformed_response', $result['error_code'] ?? null);
    }

    public function test_password_gateway_requires_explicit_success_and_matching_source_account(): void
    {
        $verified = $this->passwordGatewayReturning([
            'status' => 'ok',
            'err' => '0',
            'acc' => '41001',
        ])->verify(41001, 'trade-secret');
        $missingErr = $this->passwordGatewayReturning([
            'status' => 'ok',
            'acc' => '41001',
        ])->verify(41001, 'trade-secret');
        $wrongAccount = $this->passwordGatewayReturning([
            'status' => 'ok',
            'err' => '0',
            'acc' => '41002',
        ])->verify(41001, 'trade-secret');

        $this->assertSame('verified', $verified->status());
        $this->assertSame('unknown', $missingErr->status());
        $this->assertSame('malformed_response', $missingErr->errorCode());
        $this->assertSame('unknown', $wrongAccount->status());
        $this->assertSame('account_mismatch', $wrongAccount->errorCode());
    }

    /** @dataProvider passwordFailureProvider */
    public function test_password_gateway_classifies_delivery_and_provider_failures(
        array $response,
        string $expectedStatus,
        string $expectedCode
    ): void {
        $result = $this->passwordGatewayReturning($response)->verify(41001, 'trade-secret');

        $this->assertSame($expectedStatus, $result->status());
        $this->assertSame($expectedCode, $result->errorCode());
    }

    public function passwordFailureProvider(): array
    {
        return [
            'connection failed before send' => [
                ['status' => 'error', 'error_code' => 'connection_failed'],
                'retryable_not_sent',
                'connection_failed',
            ],
            'write delivery unknown' => [
                ['status' => 'error', 'error_code' => 'write_failed'],
                'unknown',
                'write_failed',
            ],
            'read result unknown' => [
                ['status' => 'error', 'error_code' => 'read_timeout'],
                'unknown',
                'read_timeout',
            ],
            'transport exception unknown' => [
                ['status' => 'error', 'error_code' => 'transport_exception'],
                'unknown',
                'transport_exception',
            ],
            'provider rejection' => [
                ['status' => 'error', 'error_code' => 'invalid_password'],
                'rejected',
                'invalid_password',
            ],
        ];
    }

    public function test_account_snapshot_gateway_confirms_only_matching_plain_decimal_balance(): void
    {
        $confirmed = $this->accountGatewayReturning([
            'status' => 'ok',
            'err' => '0',
            'account_id' => '41001',
            'balance' => '00125.5',
        ])->snapshot(41001);
        $wrongAccount = $this->accountGatewayReturning([
            'status' => 'ok',
            'err' => '0',
            'account_id' => '41002',
            'balance' => '125.50',
        ])->snapshot(41001);
        $unsafeBalance = $this->accountGatewayReturning([
            'status' => 'ok',
            'err' => '0',
            'account_id' => '41001',
            'balance' => '1e2',
        ])->snapshot(41001);

        $this->assertSame('confirmed', $confirmed->status());
        $this->assertSame('125.50', $confirmed->balance());
        $this->assertSame('retryable', $wrongAccount->status());
        $this->assertSame('account_mismatch', $wrongAccount->errorCode());
        $this->assertSame('retryable', $unsafeBalance->status());
        $this->assertSame('malformed_response', $unsafeBalance->errorCode());
    }

    /** @dataProvider accountFailureProvider */
    public function test_account_snapshot_gateway_classifies_read_failures(
        array $response,
        string $expectedStatus,
        string $expectedCode
    ): void {
        $result = $this->accountGatewayReturning($response)->snapshot(41001);

        $this->assertSame($expectedStatus, $result->status());
        $this->assertSame($expectedCode, $result->errorCode());
    }

    public function accountFailureProvider(): array
    {
        return [
            'connection failure is read retryable' => [
                ['status' => 'error', 'error_code' => 'connection_failed'],
                'retryable',
                'connection_failed',
            ],
            'read timeout is read retryable' => [
                ['status' => 'error', 'error_code' => 'read_timeout'],
                'retryable',
                'read_timeout',
            ],
            'explicit account rejection is terminal' => [
                ['status' => 'error', 'error_code' => 'account_not_found'],
                'rejected',
                'account_not_found',
            ],
        ];
    }

    private function passwordGatewayReturning(array $response): Mt4TradePasswordGateway
    {
        $manager = new class($response) extends Mt4ManagerService {
            /**
             * 预设的 verifyPassword 返回报文。驱动 Mt4TradePasswordGateway 对资金密码校验结果的判定分支。
             * @var array<string, mixed>
             */
            private $response;

            public function __construct(array $response)
            {
                $this->response = $response;
            }

            public function verifyPassword($userId, $password)
            {
                return $this->response;
            }
        };

        return new Mt4TradePasswordGateway($manager);
    }

    private function accountGatewayReturning(array $response): Mt4CommissionTransferAccountSnapshotGateway
    {
        $manager = new class($response) extends Mt4ManagerService {
            /**
             * 预设的 getAccountInfo 返回报文。驱动 Mt4CommissionTransferAccountSnapshotGateway 的快照映射。
             * @var array<string, mixed>
             */
            private $response;

            public function __construct(array $response)
            {
                $this->response = $response;
            }

            public function getAccountInfo($userId)
            {
                return $this->response;
            }
        };

        return new Mt4CommissionTransferAccountSnapshotGateway($manager);
    }
}
