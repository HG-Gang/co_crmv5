<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:42
 */

/**
 * Mt4DepositSettlementGatewayClosureModuleTest
 *
 * 文件功能：
 * - 验证 MT4 入金结算网关契约：预构建测试流逐字节控制报文、连接/写入/读取失败机器码、成功票号映射、可重试/unknown/rejected 分类、容器解析生产网关包装。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\DepositSettlementGateway;
use App\Services\Mt4ManagerService;
use App\Services\Payment\Mt4DepositSettlementGateway;
use Tests\TestCase;

class Mt4DepositSettlementGatewayClosureModuleTest extends TestCase
{
    /**
     * 为内存 Manager 与网关映射测试显式开启进程内用户同步授权。
     *
     * 测试不会连接真实 MT4；需要验证总开关关闭的用例会在方法内单独覆盖 mt4.enabled。
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

    public function test_manager_returns_connection_failed_machine_code_before_send(): void
    {
        config(['mt4.enabled' => false]);
        $manager = new Mt4ManagerService('127.0.0.1', 1, 'key', 'version', 1);

        $response = $manager->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('connection_failed', $response['error_code'] ?? null);
    }

    public function test_manager_returns_write_failed_machine_code_for_uncertain_write(): void
    {
        $manager = $this->managerWithSocket(fopen(__FILE__, 'r'));
        set_error_handler(static function (): bool {
            return true;
        });
        try {
            $response = $manager->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');
        } finally {
            restore_error_handler();
        }

        $this->assertSame('write_failed', $response['error_code'] ?? null);
    }

    public function test_manager_returns_read_timeout_machine_code_after_send(): void
    {
        $manager = $this->managerWithSocket(fopen('php://memory', 'r+'));

        $response = $manager->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('read_timeout', $response['error_code'] ?? null);
    }

    public function test_maps_success_response_to_settled_ticket(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'ok',
            'message' => 'accepted',
            'data' => ['91001'],
        ])->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('settled', $result->status());
        $this->assertSame('91001', $result->providerReference());
    }

    public function test_maps_top_level_success_ticket_to_settled_reference(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'ok',
            'message' => 'accepted',
            'ticket' => '91002',
            'data' => [],
        ])->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('settled', $result->status());
        $this->assertSame('91002', $result->providerReference());
    }

    public function test_maps_connection_failure_before_send_to_retryable(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'message' => 'connection failed',
            'error_code' => 'connection_failed',
        ])->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('retryable_not_sent', $result->status());
        $this->assertSame('connection_failed', $result->errorCode());
    }

    /** @dataProvider uncertainTransportProvider */
    public function test_maps_transport_uncertainty_to_unknown(string $errorCode): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'message' => 'transport uncertain',
            'error_code' => $errorCode,
        ])->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('unknown', $result->status());
        $this->assertSame($errorCode, $result->errorCode());
    }

    public function test_maps_explicit_provider_error_to_rejected(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'message' => 'account rejected',
            'error_code' => 'provider_rejected',
        ])->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('rejected', $result->status());
        $this->assertSame('provider_rejected', $result->errorCode());
    }

    public function test_container_resolves_production_gateway_wrapper(): void
    {
        $gateway = app(DepositSettlementGateway::class);

        $this->assertInstanceOf(Mt4DepositSettlementGateway::class, $gateway);
    }

    public function test_container_gateway_uses_shared_mt4_manager_alias(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            public function deposit($userId, $amount, $comment)
            {
                return [
                    'status' => 'ok',
                    'ticket' => '91003',
                ];
            }
        };
        app()->instance('mt4.manager', $manager);
        app()->forgetInstance(DepositSettlementGateway::class);

        $result = app(DepositSettlementGateway::class)
            ->deposit(1001, '25.00', 'DBUN-1001-#ORDER-1');

        $this->assertSame('settled', $result->status());
        $this->assertSame('91003', $result->providerReference());
    }

    public function uncertainTransportProvider(): array
    {
        return [
            'write failure' => ['write_failed'],
            'read timeout' => ['read_timeout'],
        ];
    }

    private function gatewayReturning(array $response): DepositSettlementGateway
    {
        $manager = new class($response) extends Mt4ManagerService {
            /**
             * 预设的 MT4 报文返回体。驱动 DepositSettlementGateway 对 deposit 指令结果的解析分支。
             * @var array<string, mixed>
             */
            private $response;

            public function __construct(array $response)
            {
                $this->response = $response;
            }

            public function deposit($userId, $amount, $comment)
            {
                return $this->response;
            }
        };

        return new Mt4DepositSettlementGateway($manager);
    }

    /** @param resource $socket */
    private function managerWithSocket($socket): Mt4ManagerService
    {
        // New client opens a fresh socket per command (legacy behavior). Tests inject a
        // prebuilt stream by overriding connect() instead of stashing $this->socket.
        return new class($socket) extends Mt4ManagerService {
            /**
             * 替身持有的预构建测试流。connect() 直接返回它，绕过真实 socket 以便逐字节控制报文。
             * @var resource
             */
            private $testSocket;

            public function __construct($socket)
            {
                parent::__construct('127.0.0.1', 1, 'key', 'version', 1, 1, 0);
                $this->testSocket = $socket;
            }

            public function connect()
            {
                if ($this->socket) {
                    return true;
                }
                $this->socket = $this->testSocket;

                return is_resource($this->socket);
            }

            public function disconnect()
            {
                // Keep the injected resource open for PHPUnit; only drop the handle.
                $this->socket = null;
            }
        };
    }
}
