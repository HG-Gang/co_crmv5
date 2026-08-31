<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:45
 */

/**
 * Mt4DepositRefundGatewayClosureModuleTest
 *
 * 文件功能：
 * - 验证 MT4 入金退款网关契约：成功报文映射退款票号、发送前连接失败可重试、传输不确定为 unknown、显式供应商错误为 rejected、容器绑定共享 MT4 管理器。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\DepositRefundGateway;
use App\Services\Mt4ManagerService;
use App\Services\Payment\Mt4DepositRefundGateway;
use Tests\TestCase;

class Mt4DepositRefundGatewayClosureModuleTest extends TestCase
{
    /**
     * 为内存 Manager 响应映射测试显式开启进程内授权；测试不会连接外部 MT4。
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

    public function test_maps_success_response_to_refunded_ticket(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'ok',
            'ticket' => '92001',
        ])->refund(1001, '25.00', 'DBRF-1001-#ORDER-1');

        $this->assertSame('settled', $result->status());
        $this->assertSame('92001', $result->providerReference());
    }

    public function test_maps_connection_failure_before_send_to_retryable(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => 'connection_failed',
        ])->refund(1001, '25.00', 'DBRF-1001-#ORDER-1');

        $this->assertSame('retryable_not_sent', $result->status());
        $this->assertSame('connection_failed', $result->errorCode());
    }

    /** @dataProvider uncertainTransportProvider */
    public function test_maps_transport_uncertainty_to_unknown(string $errorCode): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => $errorCode,
        ])->refund(1001, '25.00', 'DBRF-1001-#ORDER-1');

        $this->assertSame('unknown', $result->status());
        $this->assertSame($errorCode, $result->errorCode());
    }

    public function test_maps_explicit_provider_error_to_rejected(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => 'provider_rejected',
        ])->refund(1001, '25.00', 'DBRF-1001-#ORDER-1');

        $this->assertSame('rejected', $result->status());
        $this->assertSame('provider_rejected', $result->errorCode());
    }

    public function test_container_refund_gateway_uses_shared_mt4_manager_alias(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            public function withdrawal($userId, $amount, $comment)
            {
                return [
                    'status' => 'ok',
                    'ticket' => '92002',
                ];
            }
        };
        app()->instance('mt4.manager', $manager);
        app()->forgetInstance(DepositRefundGateway::class);

        $result = app(DepositRefundGateway::class)
            ->refund(1001, '25.00', 'DBRF-1001-#ORDER-1');

        $this->assertInstanceOf(Mt4DepositRefundGateway::class, app(DepositRefundGateway::class));
        $this->assertSame('settled', $result->status());
        $this->assertSame('92002', $result->providerReference());
    }

    public function uncertainTransportProvider(): array
    {
        return [
            'write failure' => ['write_failed'],
            'read timeout' => ['read_timeout'],
        ];
    }

    private function gatewayReturning(array $response): DepositRefundGateway
    {
        $manager = new class($response) extends Mt4ManagerService {
            /**
             * 预设的 MT4 refund 返回报文。驱动 DepositRefundGateway 对退款指令结果的解析分支。
             * @var array<string, mixed>
             */
            private $response;

            public function __construct(array $response)
            {
                $this->response = $response;
            }

            public function withdrawal($userId, $amount, $comment)
            {
                return $this->response;
            }
        };

        return new Mt4DepositRefundGateway($manager);
    }
}
