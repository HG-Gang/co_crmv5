<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 12:51
 */

declare(strict_types=1);

/**
 * 支付测试夹具契约测试。
 *
 * 文件功能：
 * - 校验三个支付相关 Feature 测试套件（FrontDepositPaymentOrderIdempotencyClosureModuleTest、PaymentOrderCrossGatewayIdempotencyClosureModuleTest、PaymentGatewayRegistryTest）的夹具契约一致。
 * - 校验动态身份助手、MySQL 互斥锁全生命周期、支付表指纹严格恢复、非 MySQL fail-closed 及跨网关竞态工作进程退出兜底。
 *
 * 适用场景：
 * - 改动任一支付夹具套件的生命周期、指纹表清单或并发保护后回归。
 *
 * 入参例子：无（通过反射读取各测试套件的属性/方法源码断言）。
 *
 * 返回值：断言通过表示各支付夹具套件遵循同一契约。
 *
 * 异常或失败场景：
 * - 套件缺动态身份助手、互斥锁释放顺序错误、指纹表清单漂移、非 MySQL 环境未先失败或竞态进程无退出兜底时失败。
 */
namespace Tests\Unit;

use ReflectionClass;
use ReflectionMethod;
use Tests\Feature\FrontDepositPaymentOrderIdempotencyClosureModuleTest;
use Tests\Feature\PaymentGatewayRegistryTest;
use Tests\Feature\PaymentOrderCrossGatewayIdempotencyClosureModuleTest;
use Tests\Support\MySqlTableFingerprint;
use PHPUnit\Framework\TestCase;

final class PaymentFixtureContractTest extends TestCase
{
    /**
     * 校验支付夹具套件暴露动态身份助手。
     *
     * @return void 断言通过不返回值。
     */
    public function test_payment_fixture_suites_expose_dynamic_identity_helpers(): void
    {
        [$front, $crossGateway, $registry] = $this->paymentSuites();

        $this->assertTrue($front->hasMethod('initializeFixtureIdentity'));
        $this->assertTrue($front->hasMethod('unusedLegacyUserId'));
        foreach ([$front, $crossGateway, $registry] as $suite) {
            $this->assertTrue($suite->hasMethod('key'));
            $this->assertTrue($suite->hasMethod('order'));
        }
    }

    /**
     * 校验支付夹具套件在整个生命周期持有 MySQL 夹具互斥锁。
     *
     * @return void 断言通过不返回值。
     */
    public function test_payment_fixture_suites_hold_the_mysql_fixture_mutex_for_the_full_lifecycle(): void
    {
        foreach ($this->paymentSuites() as $suite) {
            $this->assertTrue($suite->hasProperty('fixtureMutex'), $suite->getName());

            $setUp = $this->methodSource($suite, 'setUp');
            $this->assertStringContainsString('new MySqlFixtureMutex()', $setUp, $suite->getName());
            $this->assertStringContainsString('$this->fixtureMutex->acquire()', $setUp, $suite->getName());
            $this->assertStringContainsString('$this->abortFixtureSetup($exception)', $setUp, $suite->getName());

            $abort = $this->methodSource($suite, 'abortFixtureSetup');
            $this->assertStringContainsString('releaseWithDisconnectFallback()', $abort, $suite->getName());
            $this->assertStringContainsString('parent::tearDown()', $abort, $suite->getName());

            $tearDown = $this->methodSource($suite, 'tearDown');
            $release = strpos($tearDown, 'releaseWithDisconnectFallback()');
            $parent = strpos($tearDown, 'parent::tearDown()');
            $this->assertNotFalse($release, $suite->getName());
            $this->assertNotFalse($parent, $suite->getName());
            $this->assertLessThan($parent, $release, $suite->getName());
        }
    }

    /**
     * 校验支付夹具套件严格恢复完整支付表指纹。
     *
     * @return void 断言通过不返回值。
     */
    public function test_payment_fixture_suites_strictly_restore_complete_payment_table_fingerprints(): void
    {
        $expectedTables = [
            'deposit_records',
            'user_logins',
            'user_infos',
            'payment_channels',
            'system_configs',
        ];

        foreach ($this->paymentSuites() as $suite) {
            $constant = $suite->getReflectionConstant('FINGERPRINT_TABLES');
            $this->assertNotFalse($constant, $suite->getName());
            $this->assertSame($expectedTables, $constant->getValue(), $suite->getName());
            $this->assertTrue($suite->hasProperty('tableFingerprints'), $suite->getName());

            $setUp = $this->methodSource($suite, 'setUp');
            $tearDown = $this->methodSource($suite, 'tearDown');
            $capture = 'MySqlTableFingerprint::capture(self::FINGERPRINT_TABLES)';
            $this->assertStringContainsString($capture, $setUp, $suite->getName());
            $this->assertStringContainsString($capture, $tearDown, $suite->getName());
            $this->assertStringContainsString('$after !== $this->tableFingerprints', $tearDown, $suite->getName());
        }
    }

    /**
     * 校验注册表夹具在非 MySQL 环境分配身份前 fail-closed。
     *
     * @return void 断言通过不返回值。
     */
    public function test_registry_fixture_fails_closed_on_non_mysql_before_allocating_identity(): void
    {
        $registry = new ReflectionClass(PaymentGatewayRegistryTest::class);
        $setUp = $this->methodSource($registry, 'setUp');
        $driverGuard = strpos($setUp, "assertSame('mysql', DB::getDriverName()");
        $identityAllocation = strpos($setUp, 'initializeFixtureIdentity()');

        $this->assertNotFalse($driverGuard);
        $this->assertNotFalse($identityAllocation);
        $this->assertLessThan($identityAllocation, $driverGuard);
    }

    /**
     * 校验外部支付状态探针使用确定性表指纹契约。
     *
     * 指纹锁定为 row_count/content_digest/engine/structure_hash 四字段，
     * 自增值（AUTO_INCREMENT）不纳入指纹，由自增快照门禁独立保障。
     *
     * @return void 断言通过不返回值。
     */
    public function test_external_payment_probe_uses_the_deterministic_table_fingerprint_contract(): void
    {
        $probe = file_get_contents(dirname(__DIR__) . '/Support/payment_state_probe.php') ?: '';

        $this->assertStringContainsString('use Tests\\Support\\MySqlTableFingerprint;', $probe);
        $this->assertStringContainsString('MySqlTableFingerprint::capture($tables)', $probe);

        $fingerprintSource = $this->source(MySqlTableFingerprint::class);
        foreach (['row_count', 'content_digest', 'engine', 'structure_hash'] as $field) {
            $this->assertStringContainsString("'" . $field . "'", $fingerprintSource);
        }
        $this->assertStringNotContainsString("'auto_increment'", $fingerprintSource);
        $this->assertStringContainsString('自增值（AUTO_INCREMENT）不纳入指纹', $fingerprintSource);
        $this->assertStringContainsString('MySqlAutoIncrementSnapshot::restore', $fingerprintSource);
    }

    /**
     * 校验跨网关竞态工作进程有界退出与终止兜底。
     *
     * @return void 断言通过不返回值。
     */
    public function test_cross_gateway_race_workers_have_a_bounded_exit_and_termination_fallback(): void
    {
        $suite = new ReflectionClass(PaymentOrderCrossGatewayIdempotencyClosureModuleTest::class);
        $source = $this->source(PaymentOrderCrossGatewayIdempotencyClosureModuleTest::class);
        $runner = $this->methodSource($suite, 'runRealMysqlWorkers');

        $this->assertTrue($suite->hasMethod('waitForWorkerExit'));
        $this->assertTrue($suite->hasMethod('terminateWorker'));
        $this->assertStringContainsString('$this->waitForWorkerExit(', $runner);
        $this->assertStringContainsString('proc_get_status', $source);
        $this->assertStringContainsString('proc_terminate', $source);
        $this->assertStringContainsString('exceeded the worker exit deadline', $source);
    }

    /** @return array<int, ReflectionClass> */
    private function paymentSuites(): array
    {
        return [
            new ReflectionClass(FrontDepositPaymentOrderIdempotencyClosureModuleTest::class),
            new ReflectionClass(PaymentOrderCrossGatewayIdempotencyClosureModuleTest::class),
            new ReflectionClass(PaymentGatewayRegistryTest::class),
        ];
    }

    private function methodSource(ReflectionClass $class, string $method): string
    {
        $reflection = new ReflectionMethod($class->getName(), $method);
        $lines = file($reflection->getFileName()) ?: [];

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    private function source(string $class): string
    {
        $reflection = new ReflectionClass($class);

        return file_get_contents($reflection->getFileName()) ?: '';
    }
}
