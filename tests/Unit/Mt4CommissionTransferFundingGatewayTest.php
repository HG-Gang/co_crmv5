<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/06
 * Time: 01:08
 */

declare(strict_types=1);

/**
 * 返佣转移 MT4 出资网关单元测试。
 *
 * 文件功能：
 * - 校验 Mt4CommissionTransferFundingGateway::withdraw 对 MT4 命令响应进行分类且绝不重复下发。
 * - 校验成功、明确拒绝、传输/写入/读取不确定与响应畸形等场景的状态与错误码映射。
 *
 * 适用场景：
 * - 改动返佣转移 MT4 出资命令响应分类逻辑后回归。
 *
 * 入参例子：
 * - withdraw(1001, '25.00', 'WBCT-2001') 传入用户 ID、金额与业务单号；
 * - 响应示例：['status' => 'ok', 'ticket' => '92001'] => processed；['status' => 'error', 'error_code' => 'insufficient_funds'] => rejected。
 *
 * 返回值：断言通过表示状态、提供方引用与错误码完全一致，且 withdrawal 仅调用一次。
 *
 * 异常或失败场景：
 * - 响应畸形或传输不确定被误判为成功，或重复下发出资命令时失败。
 */
namespace Tests\Unit;

use App\Services\CommissionTransfer\Mt4CommissionTransferFundingGateway;
use App\Services\Mt4ManagerService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class Mt4CommissionTransferFundingGatewayTest extends TestCase
{
    /** @var Container 测试开始前的进程级容器，结束后必须恢复。 */
    private $previousContainer;

    /**
     * 为内存 Manager 安装最小同步配置，避免依赖无容器时的隐式放行。
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
     * 恢复进程级容器，防止本地假 Manager 的授权配置泄漏到其他 Unit。
     *
     * @return void 容器恢复完成时无返回值。
     */
    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    /**
     * @dataProvider commandResponseProvider
     * 校验出资命令响应分类且不重复下发。
     *
     * @param array $response MT4 命令响应。
     * @param string $expectedStatus 期望状态。
     * @param string|null $expectedReference 期望提供方引用，可为 null。
     * @param string|null $expectedErrorCode 期望错误码，可为 null。
     * @return void 断言通过不返回值。
     */
    public function test_withdraw_normalizes_provider_responses(
        array $response,
        string $expectedStatus,
        string $expectedReference = null,
        string $expectedErrorCode = null
    ): void {
        $manager = new class($response) extends Mt4ManagerService {
            /**
             * 测试替身统计的 withdrawal() 调用次数。用于断言“明确拒绝后不重复下发资金命令”；
             * 大于 1 即说明网关在 rejected 后仍在重试，违反失败关闭契约。
             *
             * @var int
             */
            public $withdrawalCalls = 0;

            /**
             * 固定的 MT4 命令响应。让测试替身对每次 withdrawal 调用返回同一预置响应，
             * 使结果分类（processed/unknown/rejected）完全可复现，不受真实 MT4 影响。
             *
             * @var array<string, mixed>
             */
            private $response;

            public function __construct(array $response)
            {
                $this->response = $response;
            }

            public function withdrawal($userId, $amount, $comment)
            {
                $this->withdrawalCalls++;

                return $this->response;
            }
        };

        $result = (new Mt4CommissionTransferFundingGateway($manager))
            ->withdraw(1001, '25.00', 'WBCT-2001');

        $this->assertSame($expectedStatus, $result->status());
        $this->assertSame($expectedReference, $result->providerReference());
        $this->assertSame($expectedErrorCode, $result->errorCode());
        $this->assertSame(1, $manager->withdrawalCalls);
    }

    /**
     * 提供 MT4 出金命令响应归一化用例。
     *
     * @return array<string, array<int, mixed>> dataProvider 用例集合。
     */
    public static function commandResponseProvider(): array
    {
        return [
            'malformed response after command delivery' => [
                ['status' => 'error', 'error_code' => 'malformed_response'],
                'unknown',
                null,
                'malformed_response',
            ],
            'write delivery uncertainty' => [
                ['status' => 'error', 'error_code' => 'write_failed'],
                'unknown',
                null,
                'write_failed',
            ],
            'read result uncertainty' => [
                ['status' => 'error', 'error_code' => 'read_timeout'],
                'unknown',
                null,
                'read_timeout',
            ],
            'transport uncertainty' => [
                ['status' => 'error', 'error_code' => 'transport'],
                'unknown',
                null,
                'transport',
            ],
            'transport exception uncertainty' => [
                ['status' => 'error', 'error_code' => 'transport_exception'],
                'unknown',
                null,
                'transport_exception',
            ],
            'explicit provider rejection' => [
                ['status' => 'error', 'error_code' => 'insufficient_funds'],
                'rejected',
                null,
                'insufficient_funds',
            ],
            'explicit provider success' => [
                ['status' => 'ok', 'ticket' => '92001'],
                'processed',
                '92001',
                null,
            ],
        ];
    }
}
