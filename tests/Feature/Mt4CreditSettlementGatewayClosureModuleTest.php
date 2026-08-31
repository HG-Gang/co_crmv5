<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:45
 */

/**
 * Mt4CreditSettlementGatewayClosureModuleTest
 *
 * 文件功能：
 * - 验证 MT4 赠点结算网关契约：发送前连接失败机器码、成功报文映射赠点票号、发送前失败可重试、传输不确定为 unknown、显式供应商错误为 rejected、容器绑定共享 MT4 管理器。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mt4ManagerService;
use Tests\TestCase;

class Mt4CreditSettlementGatewayClosureModuleTest extends TestCase
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

    public function test_manager_returns_connection_failed_machine_code_before_credit_send(): void
    {
        config(['mt4.enabled' => false]);
        $manager = new Mt4ManagerService('127.0.0.1', 1, 'key', 'version', 1);

        $this->assertTrue(method_exists($manager, 'creditIn'), 'Mt4ManagerService::creditIn 尚未声明。');
        $response = $manager->creditIn(1001, '25.00', 'DCAA-1001-#ORDER-1');

        $this->assertSame('connection_failed', $response['error_code'] ?? null);
    }

    public function test_maps_success_response_to_credit_ticket(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');
        $this->assertTrue(class_exists('App\Services\Payment\Mt4CreditSettlementGateway'), 'Mt4CreditSettlementGateway 尚未声明。');

        $result = $this->gatewayReturning([
            'status' => 'ok',
            'ticket' => '93001',
        ])->creditIn(1001, '25.00', 'DCAA-1001-#ORDER-1');

        $this->assertSame('settled', $result->status());
        $this->assertSame('93001', $result->providerReference());
    }

    public function test_maps_connection_failure_before_send_to_retryable(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');
        $this->assertTrue(class_exists('App\Services\Payment\Mt4CreditSettlementGateway'), 'Mt4CreditSettlementGateway 尚未声明。');

        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => 'connection_failed',
        ])->creditIn(1001, '25.00', 'DCAA-1001-#ORDER-1');

        $this->assertSame('retryable_not_sent', $result->status());
        $this->assertSame('connection_failed', $result->errorCode());
    }

    /** @dataProvider uncertainTransportProvider */
    public function test_maps_transport_uncertainty_to_unknown(string $errorCode): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');
        $this->assertTrue(class_exists('App\Services\Payment\Mt4CreditSettlementGateway'), 'Mt4CreditSettlementGateway 尚未声明。');

        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => $errorCode,
        ])->creditIn(1001, '25.00', 'DCAA-1001-#ORDER-1');

        $this->assertSame('unknown', $result->status());
        $this->assertSame($errorCode, $result->errorCode());
    }

    public function test_maps_explicit_provider_error_to_rejected(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');
        $this->assertTrue(class_exists('App\Services\Payment\Mt4CreditSettlementGateway'), 'Mt4CreditSettlementGateway 尚未声明。');

        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => 'provider_rejected',
        ])->creditIn(1001, '25.00', 'DCAA-1001-#ORDER-1');

        $this->assertSame('rejected', $result->status());
        $this->assertSame('provider_rejected', $result->errorCode());
    }

    public function test_container_credit_gateway_uses_shared_mt4_manager_alias(): void
    {
        $this->assertTrue(interface_exists('App\Contracts\CreditSettlementGateway'), 'CreditSettlementGateway 契约未声明。');
        $this->assertTrue(class_exists('App\Services\Payment\Mt4CreditSettlementGateway'), 'Mt4CreditSettlementGateway 尚未声明。');

        $manager = new class extends Mt4ManagerService {
            public function __construct()
            {
            }

            public function creditIn($userId, $amount, $comment, $expires = 999)
            {
                return [
                    'status' => 'ok',
                    'ticket' => '93002',
                ];
            }
        };
        app()->instance('mt4.manager', $manager);
        app()->forgetInstance('App\Contracts\CreditSettlementGateway');

        $gateway = app('App\Contracts\CreditSettlementGateway');
        $result = $gateway->creditIn(1001, '25.00', 'DCAA-1001-#ORDER-1');

        $this->assertInstanceOf('App\Services\Payment\Mt4CreditSettlementGateway', $gateway);
        $this->assertSame('settled', $result->status());
        $this->assertSame('93002', $result->providerReference());
    }

    public function uncertainTransportProvider(): array
    {
        return [
            'write failure' => ['write_failed'],
            'read timeout' => ['read_timeout'],
        ];
    }

    private function gatewayReturning(array $response)
    {
        $manager = new class($response) extends Mt4ManagerService {
            /**
             * 预设的 MT4 credit 返回报文。驱动赠点结算网关对入账指令结果的解析分支。
             * @var array<string, mixed>
             */
            private $response;

            public function __construct(array $response)
            {
                $this->response = $response;
            }

            public function creditIn($userId, $amount, $comment, $expires = 999)
            {
                return $this->response;
            }
        };

        return new \App\Services\Payment\Mt4CreditSettlementGateway($manager);
    }
}
