<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:45
 */

/**
 * Mt4WithdrawalFundingGatewayClosureModuleTest
 *
 * 文件功能：
 * - 验证 MT4 出金资金化网关契约：成功票号映射 debited、不确定响应映射 unknown、连接失败映射可重试 not sent、其他供应商错误为 rejected、非标量 status 为 unknown、容器绑定共享管理器。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\WithdrawalFundingGateway;
use App\Services\Mt4ManagerService;
use App\Services\Withdrawal\Mt4WithdrawalFundingGateway;
use Tests\TestCase;

class Mt4WithdrawalFundingGatewayClosureModuleTest extends TestCase
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

    public function test_maps_success_ticket_to_debited(): void
    {
        $result = $this->gatewayReturning(['status' => 'ok', 'ticket' => '92001'])
            ->withdraw(1001, '25.00', 'WD-1001');

        $this->assertSame('debited', $result->status());
        $this->assertSame('92001', $result->providerReference());
    }

    /** @dataProvider uncertainResponseProvider */
    public function test_maps_uncertain_responses_to_unknown(array $response, string $code): void
    {
        $result = $this->gatewayReturning($response)->withdraw(1001, '25.00', 'WD-1001');

        $this->assertSame('unknown', $result->status());
        $this->assertSame($code, $result->errorCode());
    }

    public function test_maps_connection_failure_to_retryable_not_sent(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => 'connection_failed',
        ])->withdraw(1001, '25.00', 'WD-1001');

        $this->assertSame('retryable_not_sent', $result->status());
    }

    public function test_maps_other_provider_error_to_rejected(): void
    {
        $result = $this->gatewayReturning([
            'status' => 'error',
            'error_code' => 'insufficient_funds',
        ])->withdraw(1001, '25.00', 'WD-1001');

        $this->assertSame('rejected', $result->status());
        $this->assertSame('insufficient_funds', $result->errorCode());
    }

    public function test_maps_response_without_status_to_unknown(): void
    {
        $result = $this->gatewayReturning(['ticket' => '92009'])
            ->withdraw(1001, '25.00', 'WD-1001');

        $this->assertSame('unknown', $result->status());
        $this->assertSame('malformed_response', $result->errorCode());
    }

    public function test_does_not_treat_string_data_offset_as_a_ticket(): void
    {
        $result = $this->gatewayReturning(['status' => 'ok', 'data' => '92009'])
            ->withdraw(1001, '25.00', 'WD-1001');

        $this->assertSame('unknown', $result->status());
        $this->assertSame('malformed_response', $result->errorCode());
    }

    public function test_maps_non_scalar_status_to_unknown(): void
    {
        $result = $this->gatewayReturning(['status' => ['ok'], 'ticket' => '92009'])
            ->withdraw(1001, '25.00', 'WD-1001');

        $this->assertSame('unknown', $result->status());
        $this->assertSame('malformed_response', $result->errorCode());
    }

    public function test_container_binds_gateway_to_shared_manager(): void
    {
        $manager = new class extends Mt4ManagerService {
            public function __construct() {}
            public function withdrawal($userId, $amount, $comment)
            {
                return ['status' => 'ok', 'ticket' => '92002'];
            }
        };
        app()->instance('mt4.manager', $manager);
        app()->forgetInstance(WithdrawalFundingGateway::class);

        $result = app(WithdrawalFundingGateway::class)->withdraw(1001, '25.00', 'WD-1001');

        $this->assertInstanceOf(Mt4WithdrawalFundingGateway::class, app(WithdrawalFundingGateway::class));
        $this->assertSame('92002', $result->providerReference());
    }

    public function uncertainResponseProvider(): array
    {
        return [
            'write failed' => [['status' => 'error', 'error_code' => 'write_failed'], 'write_failed'],
            'read timeout' => [['status' => 'error', 'error_code' => 'read_timeout'], 'read_timeout'],
            'malformed' => [['status' => 'ok', 'ticket' => ''], 'invalid_provider_reference'],
            'transport exception' => [['status' => 'error', 'error_code' => 'transport_exception'], 'transport_exception'],
        ];
    }

    private function gatewayReturning(array $response): WithdrawalFundingGateway
    {
        $manager = new class($response) extends Mt4ManagerService {
            /**
             * 预设的 MT4 withdrawal 返回报文。驱动 WithdrawalFundingGateway 对出金命令结果的解析分支。
             * @var array<string, mixed>
             */
            private $response;
            public function __construct(array $response) { $this->response = $response; }
            public function withdrawal($userId, $amount, $comment) { return $this->response; }
        };

        return new Mt4WithdrawalFundingGateway($manager);
    }
}
