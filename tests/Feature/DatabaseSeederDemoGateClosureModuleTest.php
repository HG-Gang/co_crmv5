<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:06
 */

/**
 * DatabaseSeederDemoGateClosureModuleTest
 *
 * 文件功能：
 * - 验证数据库种子演示数据闸门：生产环境拒绝前台演示种子、本地/测试环境需显式开关、非布尔配置不生效、生产仅跑初始种子。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FrontDemoDataSeeder;
use Database\Seeders\InitialDataSeeder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * 默认数据库 Seeder 的演示业务数据门禁。
 *
 * 基础字典仍可由 DatabaseSeeder 正常初始化，但会写用户、资金和交易表的
 * FrontDemoDataSeeder 必须同时满足安全环境与显式开关，避免误污染正式库。
 */
class DatabaseSeederDemoGateClosureModuleTest extends TestCase
{
    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnvironment = $this->app->environment();
    }

    protected function tearDown(): void
    {
        $originalEnvironment = $this->originalEnvironment;
        $this->app->detectEnvironment(static function () use ($originalEnvironment): string {
            return $originalEnvironment;
        });
        config()->offsetUnset('seeding.front_demo_enabled');

        parent::tearDown();
    }

    public function test_production_rejects_front_demo_seeding_even_when_flag_is_enabled(): void
    {
        $this->assertFalse($this->frontDemoAllowed('production', true));
    }

    public function test_local_environment_requires_explicit_front_demo_flag(): void
    {
        $this->assertFalse($this->frontDemoAllowed('local', false));
    }

    public function test_local_environment_allows_explicit_front_demo_seeding(): void
    {
        $this->assertTrue($this->frontDemoAllowed('local', true));
    }

    public function test_testing_environment_allows_explicit_front_demo_seeding(): void
    {
        $this->assertTrue($this->frontDemoAllowed('testing', true));
    }

    public function test_non_boolean_config_values_do_not_enable_front_demo_seeding(): void
    {
        foreach (['true', '1', 'yes', 1] as $enabled) {
            $this->assertFalse(
                $this->frontDemoAllowed('local', $enabled),
                'Only the normalized boolean true value may enable demo seeding.'
            );
        }
    }

    public function test_run_keeps_initial_seeder_and_skips_front_demo_in_production(): void
    {
        $this->assertSame(
            [InitialDataSeeder::class],
            $this->runSeederAndCaptureCalls('production', true)
        );
    }

    public function test_run_calls_front_demo_after_initial_when_gate_allows(): void
    {
        $this->assertSame(
            [InitialDataSeeder::class, FrontDemoDataSeeder::class],
            $this->runSeederAndCaptureCalls('testing', true)
        );
    }

    public function test_example_environment_keeps_front_demo_seeding_disabled_by_default(): void
    {
        $example = file_get_contents(base_path('.env.example')) ?: '';

        $this->assertMatchesRegularExpression(
            '/^FRONT_DEMO_SEEDER_ENABLED=false$/m',
            str_replace("\r\n", "\n", $example)
        );
    }

    /**
     * @param mixed $enabled
     */
    private function frontDemoAllowed(string $environment, $enabled): bool
    {
        $this->app->detectEnvironment(static function () use ($environment): string {
            return $environment;
        });
        config(['seeding.front_demo_enabled' => $enabled]);

        $seeder = new class extends DatabaseSeeder {
            public function frontDemoAllowed(): bool
            {
                return $this->shouldSeedFrontDemoData();
            }
        };

        return $seeder->frontDemoAllowed();
    }

    /**
     * @param mixed $enabled
     * @return array<int, class-string>
     */
    private function runSeederAndCaptureCalls(string $environment, $enabled): array
    {
        $this->app->detectEnvironment(static function () use ($environment): string {
            return $environment;
        });
        config(['seeding.front_demo_enabled' => $enabled]);

        $query = Mockery::mock();
        $query->shouldReceive('updateOrInsert')->atLeast()->once()->andReturnTrue();
        DB::shouldReceive('table')->atLeast()->once()->andReturn($query);

        $seeder = new class extends DatabaseSeeder {
            /** @var array<int, class-string> */
            public array $calls = [];

            public function call($class, $silent = false, array $parameters = [])
            {
                foreach ((array) $class as $seederClass) {
                    $this->calls[] = $seederClass;
                }

                return $this;
            }
        };
        $seeder->run();

        return $seeder->calls;
    }
}
