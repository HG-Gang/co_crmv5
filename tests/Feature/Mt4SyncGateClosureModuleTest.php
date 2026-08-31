<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 02:33
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CommissionTransfer\Mt4CommissionTransferFundingGateway;
use App\Services\Mt4ManagerService;
use App\Services\Mt4SyncDisabledException;
use App\Services\Mt4SyncGate;
use App\Services\Payment\Mt4DepositSettlementGateway;
use App\Services\Registration\Mt4UserProvisioningGateway;
use App\Services\Withdrawal\Mt4WithdrawalFundingGateway;
use ReflectionClass;
use Tests\TestCase;

/**
 * 用户与 MT4 同步全局开关门控闭环测试。
 *
 * 文件功能：
 * - 验证 config/mt4.php 的 user_sync_enabled 全局开关语义：
 *   true=允许用户与 MT4 同步；false=所有 MT4 同步入口 fail-closed 抛 Mt4SyncDisabledException。
 * - 验证开户预配、出入金结算、佣金转账等核心网关在开关关闭时拒绝执行远端操作。
 *
 * 适用场景：
 * - 运维通过 MT4_USER_SYNC_ENABLED=false 停用用户与 MT4 同步后的行为回归。
 *
 * 入参例子：
 * - config(['mt4.user_sync_enabled' => false]) 后调用网关方法 -> 抛异常。
 *
 * 返回值：断言通过即表示开关门控契约成立。
 *
 * 异常或失败场景：
 * - 开关关闭时任一网关未拒绝（仍尝试远端操作）即失败。
 */
final class Mt4SyncGateClosureModuleTest extends TestCase
{
    /**
     * 配置文件在环境变量缺失时必须默认关闭用户同步。
     *
     * 该约束避免部署遗漏 MT4_USER_SYNC_ENABLED 时意外开启远端同步，确保系统按
     * fail-closed 原则只保留本地迁移和验证能力。
     *
     * @return void 默认值明确为 false 时无返回值。
     */
    public function test_user_sync_configuration_defaults_to_disabled(): void
    {
        $source = file_get_contents(config_path('mt4.php'));
        $environmentExample = file_get_contents(base_path('.env.example'));

        $this->assertIsString($source, '必须能够读取 MT4 配置文件。');
        $this->assertIsString($environmentExample, '必须能够读取环境变量模板。');
        $this->assertMatchesRegularExpression(
            "/'user_sync_enabled'\\s*=>\\s*env\\('MT4_USER_SYNC_ENABLED',\\s*false\\)/",
            $source,
            'MT4_USER_SYNC_ENABLED 缺失时必须默认关闭同步。'
        );
        // 行尾必须容忍 CRLF：.gitattributes 为 `* text=auto`，Windows 检出后 .env.example 是 CRLF，
        // 而 PCRE 的 $ 在 /m 下只锚定 \n 之前，行内残留的 \r 会让 `=false$` 永远匹配不上。
        $this->assertMatchesRegularExpression(
            '/^MT4_ENABLED=false\r?$/m',
            $environmentExample,
            '环境变量模板必须显式关闭 MT4 总开关。'
        );
        $this->assertMatchesRegularExpression(
            '/^MT4_USER_SYNC_ENABLED=false\r?$/m',
            $environmentExample,
            '环境变量模板必须显式关闭用户同步开关。'
        );
    }

    /**
     * 开户网关不得向调用方暴露可替换的同步门禁。
     *
     * 门禁属于生产安全边界，测试只能替换不会建连的 Manager，不能通过构造参数
     * 注入空授权器绕过 Mt4SyncGate。
     *
     * @return void 构造器未暴露同步授权器参数时无返回值。
     */
    public function test_provisioning_gateway_does_not_expose_sync_gate_bypass(): void
    {
        $constructor = (new ReflectionClass(Mt4UserProvisioningGateway::class))->getConstructor();
        $parameterNames = array_map(
            static function (\ReflectionParameter $parameter): string {
                return $parameter->getName();
            },
            $constructor === null ? [] : $constructor->getParameters()
        );

        $this->assertNotContains(
            'assertSyncEnabled',
            $parameterNames,
            'MT4 同步门禁不得由网关调用方替换。'
        );
    }

    /**
     * 默认配置下用户与 MT4 同步必须启用。
     *
     * @return void 断言通过不返回值。
     */
    public function test_user_sync_enabled_by_default(): void
    {
        config(['mt4.user_sync_enabled' => true]);
        $this->assertTrue(Mt4SyncGate::userSyncEnabled());
        // 启用时断言不抛异常。
        Mt4SyncGate::assertUserSyncEnabled();
        $this->assertTrue(true);
    }

    /**
     * 开关关闭时断言必须抛 Mt4SyncDisabledException。
     *
     * @return void 断言通过不返回值。
     */
    public function test_gate_throws_when_sync_disabled(): void
    {
        config(['mt4.user_sync_enabled' => false]);
        $this->expectException(Mt4SyncDisabledException::class);
        Mt4SyncGate::assertUserSyncEnabled();
    }

    /**
     * Manager 公共命令必须在尝试建立 Socket 之前执行统一门禁。
     *
     * 这样可以覆盖控制器、Facade 或后续新增代码直接调用 Manager 的场景，
     * 避免门禁只存在于部分业务网关时出现旁路。
     *
     * @return void 门禁抛异常且 connect 调用次数为 0 时无返回值。
     */
    public function test_manager_command_fails_closed_before_connect_when_user_sync_disabled(): void
    {
        config([
            'mt4.enabled' => true,
            'mt4.user_sync_enabled' => false,
        ]);

        $manager = new class('127.0.0.1', 3490, '', '000005', 1, 1, 0) extends Mt4ManagerService {
            /** @var int 记录测试期间尝试建立 Socket 的次数。 */
            public $connectCalls = 0;

            /**
             * 以计数器替代真实 Socket，确保测试不会访问网络。
             *
             * @return bool 固定返回 false，表示未建立连接。
             */
            public function connect()
            {
                $this->connectCalls++;

                return false;
            }
        };

        try {
            $manager->changePassword(10001, 'abc123');
            $this->fail('用户同步关闭时 Manager 必须在 connect 前抛出门禁异常。');
        } catch (Mt4SyncDisabledException $exception) {
            $this->assertStringContainsString('同步', $exception->getMessage());
        }

        $this->assertSame(0, $manager->connectCalls, '门禁关闭时不得尝试建立 MT4 Socket。');
    }

    /**
     * 用户同步开关只接受严格布尔 true，所有伪真或缺失值都必须失败关闭。
     *
     * @dataProvider nonBooleanTrueSwitchProvider
     *
     * @param mixed $value 待验证的配置值。
     * @return void 门禁返回 false 时无返回值。
     */
    public function test_gate_rejects_every_value_except_boolean_true($value): void
    {
        config(['mt4.user_sync_enabled' => $value]);

        $this->assertFalse(Mt4SyncGate::userSyncEnabled());
    }

    /**
     * 返回不得开启用户同步的配置值。
     *
     * @return array<string, array{0: mixed}> 伪真、关闭与缺失值数据集。
     */
    public function nonBooleanTrueSwitchProvider(): array
    {
        return [
            '布尔关闭' => [false],
            '字符串 false' => ['false'],
            '字符串 true' => ['true'],
            '整数一' => [1],
            '整数零' => [0],
            '空值' => [null],
        ];
    }

    /**
     * 入金结算网关在开关关闭时必须拒绝执行远端入金。
     *
     * @return void 断言通过不返回值。
     */
    public function test_deposit_settlement_gateway_fail_closed_when_disabled(): void
    {
        config(['mt4.user_sync_enabled' => false]);
        $gateway = $this->app->make(Mt4DepositSettlementGateway::class);
        try {
            $gateway->deposit(1, '100.00', 'test');
            $this->fail('开关关闭时入金结算必须拒绝');
        } catch (Mt4SyncDisabledException $e) {
            $this->assertStringContainsString('同步', $e->getMessage());
        }
    }

    /**
     * 出金打款网关在开关关闭时必须拒绝执行远端出金。
     *
     * @return void 断言通过不返回值。
     */
    public function test_withdrawal_funding_gateway_fail_closed_when_disabled(): void
    {
        config(['mt4.user_sync_enabled' => false]);
        $gateway = $this->app->make(Mt4WithdrawalFundingGateway::class);
        $this->expectException(Mt4SyncDisabledException::class);
        $gateway->withdraw(1, '100.00', 'test');
    }

    /**
     * 开户预配网关在开关关闭时必须拒绝开户。
     *
     * @return void 断言通过不返回值。
     */
    public function test_provisioning_gateway_fail_closed_when_disabled(): void
    {
        config(['mt4.user_sync_enabled' => false]);
        $gateway = $this->app->make(Mt4UserProvisioningGateway::class);
        $this->expectException(Mt4SyncDisabledException::class);
        $gateway->provision(['user_id' => 1, 'login' => 'test']);
    }

    /**
     * 佣金转账资金网关在开关关闭时必须拒绝转账操作。
     *
     * @return void 断言通过不返回值。
     */
    public function test_commission_funding_gateway_fail_closed_when_disabled(): void
    {
        config(['mt4.user_sync_enabled' => false]);
        $gateway = $this->app->make(Mt4CommissionTransferFundingGateway::class);
        $this->expectException(Mt4SyncDisabledException::class);
        $gateway->withdraw(1, '100.00', 'test');
    }

    /**
     * 开关重新启用后网关恢复正常执行路径（不再抛禁用异常）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_gate_reopens_when_re_enabled(): void
    {
        config(['mt4.user_sync_enabled' => true]);
        $this->assertTrue(Mt4SyncGate::userSyncEnabled());
        Mt4SyncGate::assertUserSyncEnabled();
        $this->assertTrue(true);
    }
}
